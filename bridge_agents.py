"""Shared provider adapters and agent helpers for Chat Bridge scripts."""

from __future__ import annotations

import contextlib
import json
import logging
import os
from dataclasses import dataclass
from typing import AsyncGenerator, Dict, Iterable, List, Optional

import httpx
import google.generativeai as genai

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
        default_model=_env("OPENAI_MODEL") or "gpt-4o-mini",
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
        default_model=_env("ANTHROPIC_MODEL") or "claude-3-5-sonnet-20241022",
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
        default_model=_env("GEMINI_MODEL") or "gemini-2.5-flash",
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
    "deepseek": ProviderSpec(
        key="deepseek",
        label="DeepSeek",
        kind="chatml",
        default_model=_env("DEEPSEEK_MODEL") or "deepseek-chat",
        default_system="You are DeepSeek. Think like a research engineer who balances rigorous analysis with actionable recommendations.",
        needs_key=True,
        key_env="DEEPSEEK_API_KEY",
        model_env="DEEPSEEK_MODEL",
        description="DeepSeek OpenAI-compatible API for advanced reasoning and coding.",
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
        logger = logging.getLogger("bridge")
        payload = {
            "model": self.model,
            "messages": messages,
            "temperature": temperature,
            "stream": True,
            "max_tokens": max_tokens,
        }

        logger.debug(f"OpenAI request: model={self.model}, messages={len(messages)}, temp={temperature}")

        try:
            async with httpx.AsyncClient(timeout=httpx.Timeout(STALL_TIMEOUT_SEC)) as client:
                async with client.stream("POST", self.url, headers=self.headers, json=payload) as r:
                    logger.debug(f"OpenAI response status: {r.status_code}")

                    if r.status_code != 200:
                        error_text = await r.aread() if hasattr(r, 'aread') else "Unknown error"
                        logger.error(f"OpenAI API error {r.status_code}: {error_text}")
                        logger.error(f"Request URL: {self.url}")
                        logger.error(f"Request payload: {json.dumps(payload, indent=2)}")

                    r.raise_for_status()
                    req_id = r.headers.get("x-request-id") or r.headers.get("request-id")
                    if req_id:
                        logger.info("OpenAI-style request-id: %s", req_id)

                    chunk_count = 0
                    async for line in r.aiter_lines():
                        if not line:
                            continue
                        if line.startswith("data: "):
                            data = line[6:].strip()
                            if data == "[DONE]":
                                logger.debug(f"OpenAI stream completed, processed {chunk_count} chunks")
                                break
                            try:
                                obj = json.loads(data)
                                delta = obj["choices"][0]["delta"].get("content")
                                if delta:
                                    chunk_count += 1
                                    yield delta
                            except (json.JSONDecodeError, KeyError, IndexError) as parse_error:
                                logger.error(f"Failed to parse OpenAI chunk: {parse_error}")
                                logger.error(f"Raw chunk data: {data}")
                                continue

        except httpx.TimeoutException as timeout_error:
            logger.error(f"OpenAI request timeout after {STALL_TIMEOUT_SEC}s: {timeout_error}")
            logger.error(f"Model: {self.model}, Messages: {len(messages)}")
            raise RuntimeError(f"OpenAI request timed out after {STALL_TIMEOUT_SEC} seconds")
        except httpx.HTTPStatusError as http_error:
            logger.error(f"OpenAI HTTP error: {http_error}")
            logger.error(f"Response status: {http_error.response.status_code}")
            logger.error(f"Response text: {http_error.response.text}")
            raise
        except Exception as unexpected_error:
            logger.error(f"Unexpected OpenAI error: {unexpected_error}", exc_info=True)
            logger.error(f"Model: {self.model}, URL: {self.url}")
            raise


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
        logger = logging.getLogger("bridge")
        logger.debug(f"Anthropic request: model={self.model}, messages={len(messages_for_claude)}, temp={temperature}")

        try:
            async for piece in self._stream_messages(system_prompt, messages_for_claude, temperature, max_tokens):
                yield piece
            logger.debug("Anthropic messages API completed successfully")
            return
        except httpx.HTTPStatusError as exc:
            logger.warning(f"Anthropic messages API failed with {exc.response.status_code}, falling back to completions")
            if exc.response is None or exc.response.status_code not in (404, 405):
                logger.error(f"Anthropic HTTP error: {exc}", exc_info=True)
                raise
        except httpx.RequestError as req_error:
            logger.warning(f"Anthropic messages API request error, falling back to completions: {req_error}")
        except Exception as unexpected_error:
            logger.error(f"Unexpected error in Anthropic messages API: {unexpected_error}", exc_info=True)
            raise

        # Fallback to completions API
        logger.info("Using Anthropic completions API fallback")
        try:
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

            logger.debug(f"Fallback prompt length: {len(prompt)} characters")
            async for piece in self._stream_complete(prompt, temperature, max_tokens):
                yield piece
            logger.debug("Anthropic completions API completed successfully")
        except Exception as fallback_error:
            logger.error(f"Anthropic completions API also failed: {fallback_error}", exc_info=True)
            raise


class GeminiChat:
    """Wrapper using google-genai library with async generator interface."""

    def __init__(self, api_key: str, model: str):
        self.api_key = api_key
        self.model = model
        # Configure the client with API key
        genai.configure(api_key=api_key)
        self.client = genai.GenerativeModel(model)

    async def stream(
        self,
        system_instruction: str,
        contents: List[Dict[str, object]],
        temperature: float = 0.7,
        max_tokens: int = MAX_TOKENS,
    ) -> AsyncGenerator[str, None]:
        logger = logging.getLogger("bridge")
        logger.debug(f"Gemini request: model={self.model}, contents={len(contents)}, temp={temperature}")

        try:
            # Convert contents to Gemini format
            history = []
            current_message = None

            # Process conversation history
            for content in contents:
                role = content.get("role")
                parts = content.get("parts", [])

                if parts and len(parts) > 0:
                    text = parts[0].get("text", "")
                    if text.strip():
                        if role == "user":
                            history.append({"role": "user", "parts": [{"text": text}]})
                        elif role == "model":
                            history.append({"role": "model", "parts": [{"text": text}]})

            logger.debug(f"Processed Gemini history: {len(history)} messages")

            # Get the last user message as the current prompt
            if history and history[-1]["role"] == "user":
                current_message = history[-1]["parts"][0]["text"]
                history = history[:-1]  # Remove last message from history
            else:
                current_message = "Hello"
                logger.warning("No user message found in history, using default")

            logger.debug(f"Current message: {current_message[:100]}...")

            # Create chat session
            try:
                chat = self.client.start_chat(history=history)
                logger.debug("Gemini chat session created successfully")
            except Exception as chat_error:
                logger.error(f"Failed to create Gemini chat session: {chat_error}")
                logger.error(f"History length: {len(history)}")
                raise

            # Generate config
            generation_config = genai.GenerationConfig(
                temperature=temperature,
                max_output_tokens=max_tokens
            )

            # Send message and get response
            try:
                response = chat.send_message(
                    current_message,
                    generation_config=generation_config,
                    stream=False  # Use non-streaming for now
                )
                logger.debug("Gemini response received successfully")

                # Check if response has valid content before accessing text
                if hasattr(response, 'candidates') and response.candidates:
                    candidate = response.candidates[0]
                    # finish_reason: 1=STOP (normal), 2=MAX_TOKENS, 3=SAFETY, 4=RECITATION, 5=OTHER
                    if hasattr(candidate, 'finish_reason') and candidate.finish_reason not in [1, 2]:
                        finish_reason_map = {3: "SAFETY", 4: "RECITATION", 5: "OTHER"}
                        reason = finish_reason_map.get(candidate.finish_reason, f"UNKNOWN({candidate.finish_reason})")
                        logger.warning(f"Gemini blocked response due to: {reason}")
                        yield f"[Response blocked by Gemini: {reason}]"
                        return

                try:
                    if response.text:
                        logger.debug(f"Gemini response length: {len(response.text)} characters")
                        yield response.text
                    else:
                        logger.warning("Gemini returned empty response")
                        yield "[Gemini returned empty response]"
                except ValueError as ve:
                    # Handle case where response.text accessor fails
                    logger.warning(f"Could not access response.text: {ve}")
                    if hasattr(response, 'candidates') and response.candidates:
                        candidate = response.candidates[0]
                        finish_reason = getattr(candidate, 'finish_reason', 'unknown')
                        logger.warning(f"Response finish_reason: {finish_reason}")
                        yield f"[Gemini response unavailable, finish_reason={finish_reason}]"
                    else:
                        yield "[Gemini response unavailable]"

            except Exception as send_error:
                logger.error(f"Failed to send message to Gemini: {send_error}")
                logger.error(f"Message: {current_message[:200]}...")
                logger.error(f"Generation config: temp={temperature}, max_tokens={max_tokens}")
                raise

        except Exception as e:
            logger.error(f"Gemini API error: {e}", exc_info=True)
            logger.error(f"Model: {self.model}, API Key set: {bool(self.api_key)}")
            import asyncio
            await asyncio.sleep(0)  # Make it properly async
            raise RuntimeError(f"Gemini API error: {str(e)}")


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
        logger = logging.getLogger("bridge")
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
        logger.debug(f"Ollama request: {url}, model={self.model}, messages={len(messages)}")

        try:
            async with httpx.AsyncClient(timeout=httpx.Timeout(STALL_TIMEOUT_SEC)) as client:
                async with client.stream("POST", url, json=payload) as r:
                    logger.debug(f"Ollama response status: {r.status_code}")

                    if r.status_code != 200:
                        error_text = await r.aread() if hasattr(r, 'aread') else "Unknown error"
                        logger.error(f"Ollama API error {r.status_code}: {error_text}")
                        logger.error(f"URL: {url}")
                        logger.error(f"Model: {self.model}")

                    r.raise_for_status()

                    chunk_count = 0
                    async for line in r.aiter_lines():
                        if not line:
                            continue
                        try:
                            data = json.loads(line)
                            if data.get("done"):
                                logger.debug(f"Ollama stream completed, processed {chunk_count} chunks")
                                break
                            if "message" in data:
                                content = data["message"].get("content")
                                if content:
                                    chunk_count += 1
                                    yield content
                            elif "response" in data:
                                resp = data.get("response")
                                if resp:
                                    chunk_count += 1
                                    yield resp
                        except json.JSONDecodeError as parse_error:
                            logger.error(f"Failed to parse Ollama response: {parse_error}")
                            logger.error(f"Raw line: {line}")
                            continue

        except httpx.ConnectError as connect_error:
            logger.error(f"Cannot connect to Ollama server at {self.base}: {connect_error}")
            raise RuntimeError(f"Ollama server unreachable at {self.base}. Is Ollama running?")
        except httpx.TimeoutException as timeout_error:
            logger.error(f"Ollama request timeout after {STALL_TIMEOUT_SEC}s: {timeout_error}")
            logger.error(f"Model: {self.model}, Messages: {len(messages)}")
            raise RuntimeError(f"Ollama request timed out after {STALL_TIMEOUT_SEC} seconds")
        except Exception as unexpected_error:
            logger.error(f"Unexpected Ollama error: {unexpected_error}", exc_info=True)
            logger.error(f"Model: {self.model}, URL: {url}")
            raise


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
    logger = logging.getLogger("bridge")
    spec = get_spec(provider_key)

    if not spec.needs_key:
        logger.debug(f"Provider {provider_key} does not require API key")
        return None

    key = _env(spec.key_env)
    if not key:
        logger.error(f"Missing API key for {spec.label} (env var: {spec.key_env})")
        logger.error(f"Available env vars: {', '.join(k for k in os.environ.keys() if 'API' in k or 'KEY' in k)}")
        raise RuntimeError(
            f"Missing API key for {spec.label}. Set {spec.key_env} in your environment or .env file."
        )

    # Log masked key for debugging
    masked_key = key[:4] + "*" * (len(key) - 8) + key[-4:] if len(key) > 8 else "***"
    logger.debug(f"Found API key for {spec.label}: {masked_key}")
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
        if self.provider_key in {"openai", "lmstudio", "deepseek"}:
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
    logger = logging.getLogger("bridge")
    logger.info(f"Creating agent {agent_id}: provider={provider_key}, model={model}, temp={temperature}")

    try:
        if provider_key == "openai":
            api_key = ensure_credentials(provider_key)
            client = OpenAIChat(model=model, api_key=api_key)
            logger.debug(f"OpenAI client created for agent {agent_id}")
        elif provider_key == "anthropic":
            api_key = ensure_credentials(provider_key)
            client = AnthropicChat(api_key=api_key, model=model)
            logger.debug(f"Anthropic client created for agent {agent_id}")
        elif provider_key == "gemini":
            api_key = ensure_credentials(provider_key)
            client = GeminiChat(api_key=api_key, model=model)
            logger.debug(f"Gemini client created for agent {agent_id}")
        elif provider_key == "ollama":
            client = OllamaChat(model=model)
            logger.debug(f"Ollama client created for agent {agent_id}")
        elif provider_key == "lmstudio":
            base = os.getenv("LMSTUDIO_BASE_URL", "http://localhost:1234/v1")
            client = OpenAIChat(model=model, api_key=None, base_url=base)
            logger.debug(f"LM Studio client created for agent {agent_id} at {base}")
        elif provider_key == "deepseek":
            api_key = ensure_credentials(provider_key)
            base = os.getenv("DEEPSEEK_BASE_URL", "https://api.deepseek.com/v1")
            client = OpenAIChat(model=model, api_key=api_key, base_url=base)
            logger.debug(f"DeepSeek client created for agent {agent_id}")
        else:
            logger.error(f"Unsupported provider requested: {provider_key}")
            raise RuntimeError(f"Unsupported provider: {provider_key}")

        agent = AgentRuntime(
            agent_id=agent_id,
            provider_key=provider_key,
            model=model,
            temperature=temperature,
            system_prompt=system_prompt,
            client=client,
        )
        logger.info(f"Agent {agent_id} created successfully")
        return agent

    except Exception as e:
        logger.error(f"Failed to create agent {agent_id}: {e}", exc_info=True)
        logger.error(f"Provider: {provider_key}, Model: {model}")
        raise


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

