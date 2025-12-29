# web_gui/backend/tests/test_main.py
"""Tests for the Chat Bridge web UI FastAPI backend."""

from __future__ import annotations

import json
import importlib.util
import sys
from pathlib import Path
from types import SimpleNamespace
from typing import Any, Dict, List

import pytest
from fastapi.testclient import TestClient

BACKEND_DIR = (
    Path(__file__).resolve().parents[1] / "web_gui" / "backend"
)
ROOT_DIR = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT_DIR))
MAIN_PATH = BACKEND_DIR / "main.py"
SPEC = importlib.util.spec_from_file_location("web_gui_backend_main", MAIN_PATH)
main = importlib.util.module_from_spec(SPEC)
assert SPEC and SPEC.loader  # Defensive check for loader availability
SPEC.loader.exec_module(main)  # type: ignore[arg-type]


@pytest.fixture(autouse=True)
def stub_backend_dependencies(monkeypatch: pytest.MonkeyPatch, tmp_path: Any) -> None:
    """Stub external dependencies so tests run in isolation."""

    def fake_provider_choices() -> List[SimpleNamespace]:
        return [
            SimpleNamespace(
                key="demo", label="Demo", description="Demonstration provider"
            )
        ]

    def fake_get_spec(provider: str) -> SimpleNamespace:
        return SimpleNamespace(
            default_system=f"Default system for {provider}",
            key_env=None,
            label=f"{provider.title()} Provider",
            description=f"Provider {provider}",
            needs_key=False,
        )

    def fake_resolve_model(provider: str, model: str | None = None) -> str:
        return model or f"{provider}-model"

    class DummyAgent:
        """Minimal agent stub that echoes a deterministic response."""

        def __init__(
            self,
            name: str,
            provider: str,
            model: str,
            temperature: float,
            system_prompt: str,
        ) -> None:
            self.name = name
            self.provider = provider
            self.model = model
            self.temperature = temperature
            self.system_prompt = system_prompt

        def generate_response(self, context: str) -> str:
            return f"{self.name} response to: {context.strip()}"

    def fake_create_agent(
        name: str, provider: str, model: str, temperature: float, system_prompt: str
    ) -> DummyAgent:
        return DummyAgent(name, provider, model, temperature, system_prompt)

    monkeypatch.setattr(main, "provider_choices", fake_provider_choices)
    monkeypatch.setattr(main, "get_spec", fake_get_spec)
    monkeypatch.setattr(main, "resolve_model", fake_resolve_model)
    monkeypatch.setattr(main, "create_agent", fake_create_agent)
    monkeypatch.setattr(main, "ensure_credentials", lambda provider: None)

    roles_content: Dict[str, Any] = {
        "persona_library": {
            "helper": {
                "name": "Helper",
                "provider": "demo",
                "system": "Be helpful",
                "temperature": 0.5,
                "model": "demo-model",
                "guidelines": ["Keep it short"],
            }
        }
    }
    roles_path = tmp_path / "roles.json"
    roles_path.write_text(json.dumps(roles_content), encoding="utf-8")

    monkeypatch.setattr(main.persona_manager, "script_dir", tmp_path)
    main.persona_manager.persona_library = main.persona_manager.load_personas_from_config()

    main.conversations.clear()


@pytest.fixture()
def client() -> TestClient:
    """FastAPI test client with lifecycle events enabled."""
    with TestClient(main.app) as fastapi_client:
        yield fastapi_client


def test_health_endpoint(client: TestClient) -> None:
    """Health endpoint should return a friendly status message."""
    response = client.get("/")

    assert response.status_code == 200
    body = response.json()
    assert body["message"] == "Chat Bridge Web API is running"
    assert body["version"] == "0.1.0"


def test_persona_listing(client: TestClient) -> None:
    """Personas should be listed from the stubbed roles file."""
    response = client.get("/api/personas")

    assert response.status_code == 200
    personas = response.json()["personas"]
    assert len(personas) == 1
    persona = personas[0]
    assert persona["id"] == "helper"
    assert persona["name"] == "Helper"
    assert persona["provider"] == "demo"
    assert "description" in persona


def test_create_conversation_and_transcript(client: TestClient) -> None:
    """Creating a conversation should store messages and expose a transcript."""
    payload = {
        "persona_a": "helper",
        "persona_b": "helper",
        "provider_a": "demo",
        "provider_b": "demo",
        "starter_message": "Hello team",
        "max_rounds": 1,
    }
    response = client.post("/api/conversations", json=payload)

    assert response.status_code == 200
    data = response.json()
    conversation_id = data["conversation_id"]
    assert conversation_id in main.conversations

    transcript_response = client.get(
        f"/api/conversations/{conversation_id}/transcript"
    )
    assert transcript_response.status_code == 200
    transcript = transcript_response.json()
    assert transcript["conversation_id"] == conversation_id
    assert "Hello team" in transcript["transcript"]
    assert transcript["message_count"] == 1


def test_websocket_conversation_stream(client: TestClient) -> None:
    """WebSocket endpoint should stream conversation messages and end cleanly."""
    payload = {
        "persona_a": "helper",
        "persona_b": "helper",
        "provider_a": "demo",
        "provider_b": "demo",
        "starter_message": "Hello through websocket",
        "max_rounds": 1,
    }
    conversation_id = client.post("/api/conversations", json=payload).json()[
        "conversation_id"
    ]

    with client.websocket_connect(f"/ws/conversations/{conversation_id}") as websocket:
        received: List[Dict[str, Any]] = []
        while True:
            message = websocket.receive_json()
            if message.get("type") == "conversation_end":
                break
            received.append(message)

    senders = [msg["data"]["sender"] for msg in received if msg.get("data")]
    assert "user" in senders
    assert any(sender.startswith("agent_") for sender in senders)
    assert any("response to" in msg["data"]["content"] for msg in received)
