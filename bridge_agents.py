"""Shared provider adapters and agent helpers for Chat Bridge scripts."""

from __future__ import annotations

import contextlib
import json
import logging
import os
from dataclasses import dataclass
from typing import AsyncGenerator, Dict, Iterable, List, Optional

import httpx

STALL_TIMEOUT_SEC = 90
MAX_TOKENS = 800


@dataclass
class Turn:
    """Lightweight representation of a single utterance in the dialogue."""

    author: str  # "human", "a", or "b"
    text: str


@dataclass
class ProviderSpec:
    key: str
    label: str
    kind: str  # chatml | anthropic | gemini | ollama | openai
    default_model: str
    default_system: str
    needs_key: bool
    key_env: Optional[str]
    model_env: Optional[str]
    description: str


def _env(name: Optional[str]) -> Optional[str]:
    if not name:
        return None
    val = os.getenv(name)
    return val.strip() if isinstance(val, str) and val.strip() else None


PROVIDER_REGISTRY: Dict[str, ProviderSpec] = {
    "openai": ProviderSpec(
        key="openai",
        label="OpenAI",
        kind="chatml",
        default_model=_env("OPENAI_MODEL") or "gpt-4.1-mini",
        default_system="You are ChatGPT. Be concise, helpful, and witty.",
        needs_key=True,
        key_env="OPENAI_API_KEY",
        model_env="OPENAI_MODEL",
        description="OpenAI Chat Completions API using the latest turbo-tier GPT-4.1 Mini.",
    ),
    "anthropic": ProviderSpec(
        key="anthropic",
        label="Anthropic",
        kind="anthropic",
        default_model=_env("ANTHROPIC_MODEL") or "claude-3-5-sonnet-20240620",
        default_system="You are Claude. Be concise, helpful, and witty.",
        needs_key=True,
        key_env="ANTHROPIC_API_KEY",
        model_env="ANTHROPIC_MODEL",
        description="Anthropic Messages API targeting Claude 3.5 Sonnet (turbo-tier).",
    ),
    "gemini": ProviderSpec(
        key="gemini",
        label="Gemini",
        kind="gemini",
        default_model=_env("GEMINI_MODEL") or "gemini-1.5-pro-latest",
        default_system="You are Gemini. Respond with structured, thoughtful, and well-cited answers when possible.",
        needs_key=True,
        key_env="GEMINI_API_KEY",
        model_env="GEMINI_MODEL",
        description="Google Gemini API using the latest 1.5 Pro turbo configuration.",
    ),
    "ollama": ProviderSpec(
        key="ollama",
        label="Ollama",
        kind="ollama",
        default_model=_env("OLLAMA_MODEL") or "llama3.1:8b-instruct",
        default_system="You are a local Ollama assistant. Be direct, cite sources when available, and keep responses crisp.",
        needs_key=False,
        key_env=None,
        model_env="OLLAMA_MODEL",
        description="Local Ollama server chat endpoint (defaults to llama3.1 8B instruct turbo).",
    ),
    "lmstudio": ProviderSpec(
        key="lmstudio",
        label="LM Studio",
        kind="chatml",
        default_model=_env("LMSTUDIO_MODEL") or "lmstudio-community/Meta-Llama-3-8B-Instruct-GGUF",
        default_system="You are an LM Studio-hosted assistant. Reason carefully and keep answers grounded in evidence.",
        needs_key=False,
        key_env=None,
        model_env="LMSTUDIO_MODEL",
        description="LM Studio local OpenAI-compatible server (turbo tuned).",
    ),
}


class OpenAIChat:
    """Minimal OpenAI (or OpenAI-compatible) streaming client."""

    def __init__(self, model: str, api_key: Optional[str] = None, base_url: Optional[str] = None):
        self.model = model
        self.api_key = api_key
        base = base_url or "https://api.openai.com/v1"
        self.url = f"{base.rstrip('/')}/chat/completions"
        self.headers = {"Content-Type": "application/json"}
        if api_key:
            self.headers["Authorization"] = f"Bearer {api_key}"

    async def stream(
        self,
        messages: List[Dict[str, str]],
        temperature: float = 0.7,
        max_tokens: int = MAX_TOKENS,
    ) -> AsyncGenerator[str, None]:
        payload = {
            "model": self.model,
            "messages": messages,
            "temperature": temperature,
            "stream": True,
            "max_tokens": max_tokens,
        }
        async with httpx.AsyncClient(timeout=httpx.Timeout(STALL_TIMEOUT_SEC)) as client:
            async with client.stream("POST", self.url, headers=self.headers, json=payload) as r:
                r.raise_for_status()
                req_id = r.headers.get("x-request-id") or r.headers.get("request-id")
                if req_id:
                    logging.getLogger("bridge").info("OpenAI-style request-id: %s", req_id)
                async for line in r.aiter_lines():
                    if not line:
                        continue
                    if line.startswith("data: "):
                        data = line[6:].strip()
                        if data == "[DONE]":
                            break
                        with contextlib.suppress(Exception):
                            obj = json.loads(data)
                            delta = obj["choices"][0]["delta"].get("content")
                            if delta:
                                yield delta


class AnthropicChat:
    """Anthropic Messages API client with fallback to the legacy completions endpoint."""

    def __init__(self, api_key: str, model: str):
        self.api_key = api_key
        self.model = model
        self.base_messages = "https://api.anthropic.com/v1/messages"
        self.base_complete = "https://api.anthropic.com/v1/complete"
        self.msg_headers = {
            "x-api-key": api_key,
            "anthropic-version": "2023-06-01",
            "content-type": "application/json",
            "accept": "text/event-stream",
        }
        self.cpl_headers = {
            "x-api-key": api_key,
            "content-type": "application/json",
            "accept": "text/event-stream",
        }

    async def _stream_messages(
        self,
        system_prompt: str,
        messages_for_claude: List[Dict[str, str]],
        temperature: float,
        max_tokens: int,
    ) -> AsyncGenerator[str, None]:
        payload = {
            "model": self.model,
            "system": system_prompt,
            "max_tokens": max_tokens,
            "temperature": temperature,
            "messages": messages_for_claude,
            "stream": True,
        }
        async with httpx.AsyncClient(timeout=httpx.Timeout(STALL_TIMEOUT_SEC)) as client:
            async with client.stream("POST", self.base_messages, headers=self.msg_headers, json=payload) as r:
                if r.status_code in (404, 405):
                    raise httpx.HTTPStatusError(
                        "Messages endpoint unsupported; falling back.",
                        request=r.request,
                        response=r,
                    )
                r.raise_for_status()
                req_id = r.headers.get("x-request-id") or r.headers.get("request-id")
                if req_id:
                    logging.getLogger("bridge").info("Anthropic request-id: %s", req_id)
                async for raw in r.aiter_lines():
                    if not raw:
                        continue
                    if raw.startswith("data: "):
                        data = raw[6:].strip()
                        if data == "[DONE]":
                            break
                        with contextlib.suppress(Exception):
                            obj = json.loads(data)
                            if obj.get("type") in ("content_block_delta", "content_block"):
                                delta = obj.get("delta", {})
                                if delta.get("type") == "text_delta":
                                    text = delta.get("text")
                                    if text:
                                        yield text

    async def _stream_complete(
        self,
        prompt: str,
        temperature: float,
        max_tokens: int,
    ) -> AsyncGenerator[str, None]:
        payload = {
            "model": self.model,
            "prompt": prompt,
            "max_tokens_to_sample": max_tokens,
            "temperature": temperature,
            "stream": True,
        }
        async with httpx.AsyncClient(timeout=httpx.Timeout(STALL_TIMEOUT_SEC)) as client:
            async with client.stream("POST", self.base_complete, headers=self.cpl_headers, json=payload) as r:
                r.raise_for_status()
                req_id = r.headers.get("x-request-id") or r.headers.get("request-id")
                if req_id:
                    logging.getLogger("bridge").info("Anthropic request-id (complete): %s", req_id)
                async for raw in r.aiter_lines():
                    if not raw:
                        continue
                    if raw.startswith("data: "):
                        data = raw[6:].strip()
                        if data == "[DONE]":
                            break
                        with contextlib.suppress(Exception):
                            obj = json.loads(data)
                            if "completion" in obj:
                                text = obj.get("completion")
                                if text:
                                    yield text
                            elif obj.get("type") == "completion" and "delta" in obj:
                                delta = obj["delta"]
                                if isinstance(delta, str):
                                    yield delta

    async def stream(
        self,
        system_prompt: str,
        messages_for_claude: List[Dict[str, str]],
        temperature: float = 0.7,
        max_tokens: int = MAX_TOKENS,
    ) -> AsyncGenerator[str, None]:
        try:
            async for piece in self._stream_messages(system_prompt, messages_for_claude, temperature, max_tokens):
                yield piece
            return
        except httpx.HTTPStatusError as exc:
            if exc.response is None or exc.response.status_code not in (404, 405):
                raise
        except httpx.RequestError:
            pass

        prompt_lines: List[str] = []
        if system_prompt:
            prompt_lines.append(f"[System]: {system_prompt}")
        for item in messages_for_claude:
            role = item.get("role", "user")
            parts = []
            for block in item.get("content", []):
                if block.get("type") == "text":
                    parts.append(block.get("text", ""))
            text = "\n".join(parts).strip()
            if not text:
                continue
            label = "Assistant" if role == "assistant" else "User"
            prompt_lines.append(f"[{label}]: {text}")
        prompt_lines.append("\n[Assistant]:")
        prompt = "\n".join(prompt_lines)

        async for piece in self._stream_complete(prompt, temperature, max_tokens):
            yield piece


class GeminiChat:
    """Thin wrapper over the Gemini REST API (non-streaming with async generator)."""

    def __init__(self, api_key: str, model: str):
        self.api_key = api_key
        self.model = model
        self.base_url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent"

    async def stream(
        self,
        system_instruction: str,
        contents: List[Dict[str, object]],
        temperature: float = 0.7,
        max_tokens: int = MAX_TOKENS,
    ) -> AsyncGenerator[str, None]:
        payload: Dict[str, object] = {
            "contents": contents,
            "generationConfig": {
                "temperature": temperature,
                "maxOutputTokens": max_tokens,
            },
        }
        if system_instruction:
            payload["systemInstruction"] = {
                "role": "system",
                "parts": [{"text": system_instruction}],
            }

        params = {"key": self.api_key}
        async with httpx.AsyncClient(timeout=httpx.Timeout(STALL_TIMEOUT_SEC)) as client:
            response = await client.post(self.base_url, params=params, json=payload)
            response.raise_for_status()
            data = response.json()

        texts: List[str] = []
        for candidate in data.get("candidates", []):
            content = candidate.get("content", {})
            parts = content.get("parts", []) if isinstance(content, dict) else []
            for part in parts:
                txt = part.get("text") if isinstance(part, dict) else None
                if txt:
                    texts.append(txt)
        final = "\n".join(texts).strip()
        if final:
            yield final


class OllamaChat:
    """Client for the Ollama chat endpoint."""

    def __init__(self, model: str, host: Optional[str] = None):
        base = host or os.getenv("OLLAMA_HOST", "http://localhost:11434")
        self.base = base.rstrip("/")
        self.model = model

    async def stream(
        self,
        messages: List[Dict[str, str]],
        system_prompt: str,
        temperature: float = 0.7,
        max_tokens: int = MAX_TOKENS,
    ) -> AsyncGenerator[str, None]:
        payload: Dict[str, object] = {
            "model": self.model,
            "messages": messages,
            "stream": True,
            "options": {
                "temperature": temperature,
                "num_predict": max_tokens,
            },
        }
        if system_prompt:
            payload["system"] = system_prompt

        url = f"{self.base}/api/chat"
        async with httpx.AsyncClient(timeout=httpx.Timeout(STALL_TIMEOUT_SEC)) as client:
            async with client.stream("POST", url, json=payload) as r:
                r.raise_for_status()
                async for line in r.aiter_lines():
                    if not line:
                        continue
                    with contextlib.suppress(Exception):
                        data = json.loads(line)
                        if data.get("done"):
                            break
                        if "message" in data:
                            content = data["message"].get("content")
                            if content:
                                yield content
                        elif "response" in data:
                            resp = data.get("response")
                            if resp:
                                yield resp


def provider_choices() -> List[str]:
    return list(PROVIDER_REGISTRY.keys())


def get_spec(key: str) -> ProviderSpec:
    if key not in PROVIDER_REGISTRY:
        raise KeyError(f"Unknown provider: {key}")
    return PROVIDER_REGISTRY[key]


def resolve_model(provider_key: str, override: Optional[str] = None, agent_env: Optional[str] = None) -> str:
    if override:
        return override
    agent_override = _env(agent_env) if agent_env else None
    if agent_override:
        return agent_override
    spec = get_spec(provider_key)
    env_override = _env(spec.model_env)
    if env_override:
        return env_override
    return spec.default_model


def ensure_credentials(provider_key: str) -> Optional[str]:
    spec = get_spec(provider_key)
    if not spec.needs_key:
        return None
    key = _env(spec.key_env)
    if not key:
        raise RuntimeError(
            f"Missing API key for {spec.label}. Set {spec.key_env} in your environment or .env file."
        )
    return key


def select_turns(turns: Iterable[Turn], mem_rounds: int) -> List[Turn]:
    items = list(turns)
    if mem_rounds <= 0 or mem_rounds >= len(items):
        return items
    return items[-mem_rounds:]


def build_chatml(turns: List[Turn], agent_id: str, system_prompt: str) -> List[Dict[str, str]]:
    messages: List[Dict[str, str]] = []
    if system_prompt:
        messages.append({"role": "system", "content": system_prompt})
    for turn in turns:
        role = "assistant" if turn.author == agent_id else "user"
        messages.append({"role": role, "content": turn.text})
    return messages


def build_anthropic(turns: List[Turn], agent_id: str) -> List[Dict[str, object]]:
    formatted: List[Dict[str, object]] = []
    for turn in turns:
        role = "assistant" if turn.author == agent_id else "user"
        formatted.append({
            "role": role,
            "content": [{"type": "text", "text": turn.text}],
        })
    return formatted


def build_gemini(turns: List[Turn], agent_id: str) -> List[Dict[str, object]]:
    formatted: List[Dict[str, object]] = []
    for turn in turns:
        role = "model" if turn.author == agent_id else "user"
        formatted.append({
            "role": role,
            "parts": [{"text": turn.text}],
        })
    return formatted


def build_ollama(turns: List[Turn], agent_id: str) -> List[Dict[str, str]]:
    formatted: List[Dict[str, str]] = []
    for turn in turns:
        role = "assistant" if turn.author == agent_id else "user"
        formatted.append({"role": role, "content": turn.text})
    return formatted


@dataclass
class AgentRuntime:
    agent_id: str
    provider_key: str
    model: str
    temperature: float
    system_prompt: str
    client: object

    @property
    def spec(self) -> ProviderSpec:
        return get_spec(self.provider_key)

    @property
    def label(self) -> str:
        return self.spec.label

    @property
    def identifier(self) -> str:
        return f"{self.provider_key}:{self.model}"

    def stream_reply(self, turns: Iterable[Turn], mem_rounds: int) -> AsyncGenerator[str, None]:
        recent = select_turns(turns, mem_rounds)
        if self.provider_key in {"openai", "lmstudio"}:
            messages = build_chatml(recent, self.agent_id, self.system_prompt)
            return self.client.stream(messages, temperature=self.temperature)
        if self.provider_key == "anthropic":
            messages = build_anthropic(recent, self.agent_id)
            return self.client.stream(
                system_prompt=self.system_prompt,
                messages_for_claude=messages,
                temperature=self.temperature,
            )
        if self.provider_key == "gemini":
            contents = build_gemini(recent, self.agent_id)
            return self.client.stream(
                system_instruction=self.system_prompt,
                contents=contents,
                temperature=self.temperature,
            )
        if self.provider_key == "ollama":
            messages = build_ollama(recent, self.agent_id)
            return self.client.stream(
                messages=messages,
                system_prompt=self.system_prompt,
                temperature=self.temperature,
            )
        raise RuntimeError(f"Unsupported provider: {self.provider_key}")


def create_agent(agent_id: str, provider_key: str, model: str, temperature: float, system_prompt: str) -> AgentRuntime:
    if provider_key == "openai":
        api_key = ensure_credentials(provider_key)
        client = OpenAIChat(model=model, api_key=api_key)
    elif provider_key == "anthropic":
        api_key = ensure_credentials(provider_key)
        client = AnthropicChat(api_key=api_key, model=model)
    elif provider_key == "gemini":
        api_key = ensure_credentials(provider_key)
        client = GeminiChat(api_key=api_key, model=model)
    elif provider_key == "ollama":
        client = OllamaChat(model=model)
    elif provider_key == "lmstudio":
        base = os.getenv("LMSTUDIO_BASE_URL", "http://localhost:1234/v1")
        client = OpenAIChat(model=model, api_key=None, base_url=base)
    else:
        raise RuntimeError(f"Unsupported provider: {provider_key}")

    return AgentRuntime(
        agent_id=agent_id,
        provider_key=provider_key,
        model=model,
        temperature=temperature,
        system_prompt=system_prompt,
        client=client,
    )


__all__ = [
    "AgentRuntime",
    "PROVIDER_REGISTRY",
    "ProviderSpec",
    "Turn",
    "create_agent",
    "ensure_credentials",
    "get_spec",
    "provider_choices",
    "resolve_model",
    "select_turns",
    "STALL_TIMEOUT_SEC",
]

