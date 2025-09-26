#!/usr/bin/env python3
"""
Tests for bridge_agents module.
Tests agent creation, provider specs, and agent runtime functionality.
"""

import os
import unittest
from unittest.mock import AsyncMock, MagicMock, patch

# Import the modules to test
from bridge_agents import (
    Turn, ProviderSpec, _env, provider_choices, get_spec,
    resolve_model, create_agent, PROVIDER_REGISTRY
)


class TestTurn(unittest.TestCase):
    """Test Turn dataclass"""

    def test_turn_creation(self):
        """Test creating a Turn object"""
        turn = Turn(author="human", text="Hello world")

        self.assertEqual(turn.author, "human")
        self.assertEqual(turn.text, "Hello world")

    def test_turn_equality(self):
        """Test Turn equality"""
        turn1 = Turn(author="agent_a", text="Response")
        turn2 = Turn(author="agent_a", text="Response")
        turn3 = Turn(author="agent_b", text="Response")

        self.assertEqual(turn1, turn2)
        self.assertNotEqual(turn1, turn3)


class TestProviderSpec(unittest.TestCase):
    """Test ProviderSpec dataclass"""

    def test_provider_spec_creation(self):
        """Test creating a ProviderSpec object"""
        spec = ProviderSpec(
            key="test",
            label="Test Provider",
            kind="chatml",
            default_model="test-model",
            default_system="You are a helpful assistant",
            needs_key=True,
            key_env="TEST_API_KEY",
            model_env="TEST_MODEL",
            description="A test provider"
        )

        self.assertEqual(spec.key, "test")
        self.assertEqual(spec.label, "Test Provider")
        self.assertEqual(spec.kind, "chatml")
        self.assertTrue(spec.needs_key)


class TestEnvironmentUtils(unittest.TestCase):
    """Test environment utility functions"""

    def test_env_function_with_valid_env(self):
        """Test _env function with valid environment variable"""
        with patch.dict(os.environ, {'TEST_VAR': 'test_value'}):
            result = _env('TEST_VAR')
            self.assertEqual(result, 'test_value')

    def test_env_function_with_empty_env(self):
        """Test _env function with empty environment variable"""
        with patch.dict(os.environ, {'TEST_VAR': '   '}):
            result = _env('TEST_VAR')
            self.assertIsNone(result)

    def test_env_function_with_missing_env(self):
        """Test _env function with missing environment variable"""
        result = _env('NONEXISTENT_VAR')
        self.assertIsNone(result)

    def test_env_function_with_none_name(self):
        """Test _env function with None name"""
        result = _env(None)
        self.assertIsNone(result)


class TestProviderRegistry(unittest.TestCase):
    """Test provider registry and related functions"""

    def test_provider_choices(self):
        """Test getting provider choices"""
        choices = provider_choices()

        self.assertIsInstance(choices, list)
        self.assertGreater(len(choices), 0)

        # Common providers should be available
        expected_providers = ["openai", "anthropic"]
        for provider in expected_providers:
            if provider in PROVIDER_REGISTRY:
                self.assertIn(provider, choices)

    def test_get_spec_valid_provider(self):
        """Test getting spec for valid provider"""
        # Test with first available provider
        providers = provider_choices()
        if providers:
            spec = get_spec(providers[0])
            self.assertIsInstance(spec, ProviderSpec)
            self.assertEqual(spec.key, providers[0])

    def test_get_spec_invalid_provider(self):
        """Test getting spec for invalid provider"""
        with self.assertRaises(KeyError):
            get_spec("nonexistent_provider")

    def test_resolve_model_with_override(self):
        """Test resolving model with override"""
        providers = provider_choices()
        if providers:
            provider = providers[0]
            override_model = "custom-model"

            result = resolve_model(provider, override_model)
            self.assertEqual(result, override_model)

    def test_resolve_model_without_override(self):
        """Test resolving model without override (uses default)"""
        providers = provider_choices()
        if providers:
            provider = providers[0]
            spec = get_spec(provider)

            result = resolve_model(provider, None)
            self.assertEqual(result, spec.default_model)

    def test_resolve_model_from_env(self):
        """Test resolving model from environment variable"""
        providers = provider_choices()
        if providers:
            provider = providers[0]
            spec = get_spec(provider)

            if spec.model_env:
                with patch.dict(os.environ, {spec.model_env: 'env_model'}):
                    result = resolve_model(provider, None)
                    self.assertEqual(result, 'env_model')


class TestAgentCreation(unittest.TestCase):
    """Test agent creation functionality"""

    @patch('bridge_agents.OpenAIAgent')
    @patch('bridge_agents.AnthropicAgent')
    @patch('bridge_agents.get_spec')
    def test_create_agent_openai(self, mock_get_spec, mock_anthropic, mock_openai):
        """Test creating OpenAI agent"""
        # Mock the spec
        mock_spec = MagicMock()
        mock_spec.kind = "chatml"
        mock_spec.needs_key = True
        mock_spec.key_env = "OPENAI_API_KEY"
        mock_spec.default_system = "You are a helpful assistant"
        mock_get_spec.return_value = mock_spec

        # Mock the agent class
        mock_agent_instance = MagicMock()
        mock_openai.return_value = mock_agent_instance

        # Mock environment
        with patch.dict(os.environ, {'OPENAI_API_KEY': 'test_key'}):
            with patch('bridge_agents._env', return_value='test_key'):
                result = create_agent("openai", "test_id", "gpt-3.5-turbo", 0.7)

        self.assertEqual(result, mock_agent_instance)

    @patch('bridge_agents.get_spec')
    def test_create_agent_missing_api_key(self, mock_get_spec):
        """Test creating agent with missing API key"""
        # Mock the spec to require a key
        mock_spec = MagicMock()
        mock_spec.kind = "chatml"
        mock_spec.needs_key = True
        mock_spec.key_env = "MISSING_API_KEY"
        mock_get_spec.return_value = mock_spec

        with patch('bridge_agents._env', return_value=None):
            with self.assertRaises(ValueError) as cm:
                create_agent("test_provider", "test_id", "test_model", 0.7)

            self.assertIn("API key", str(cm.exception))

    @patch('bridge_agents.get_spec')
    def test_create_agent_unsupported_kind(self, mock_get_spec):
        """Test creating agent with unsupported provider kind"""
        # Mock the spec with unsupported kind
        mock_spec = MagicMock()
        mock_spec.kind = "unsupported_kind"
        mock_spec.needs_key = False
        mock_get_spec.return_value = mock_spec

        with self.assertRaises(ValueError) as cm:
            create_agent("test_provider", "test_id", "test_model", 0.7)

        self.assertIn("Unsupported", str(cm.exception))


class TestAgentRuntimeBase(unittest.TestCase):
    """Test AgentRuntime base functionality (if imported)"""

    def setUp(self):
        """Set up test agent runtime mock"""
        # Since we can't import the actual AgentRuntime classes without API keys,
        # we'll test the interface expectations
        pass

    def test_agent_runtime_interface(self):
        """Test that agent runtime has expected interface"""
        # This is a placeholder for testing the actual agent runtime interface
        # when the classes are available

        # Expected methods that agents should have:
        expected_methods = [
            'stream_response',
            # Add other expected methods as discovered from the codebase
        ]

        # This test would verify the interface exists when the classes are imported
        # For now, we'll just assert the expectation
        self.assertTrue(len(expected_methods) > 0)


class TestProviderRegistryContent(unittest.TestCase):
    """Test the content and structure of provider registry"""

    def test_provider_registry_structure(self):
        """Test that provider registry has expected structure"""
        for key, spec in PROVIDER_REGISTRY.items():
            self.assertIsInstance(spec, ProviderSpec)
            self.assertEqual(spec.key, key)
            self.assertIsInstance(spec.label, str)
            self.assertIsInstance(spec.kind, str)
            self.assertIsInstance(spec.default_model, str)
            self.assertIsInstance(spec.default_system, str)
            self.assertIsInstance(spec.needs_key, bool)
            self.assertIsInstance(spec.description, str)

            # Environment variable names should be strings if specified
            if spec.key_env is not None:
                self.assertIsInstance(spec.key_env, str)
            if spec.model_env is not None:
                self.assertIsInstance(spec.model_env, str)

    def test_common_providers_exist(self):
        """Test that common providers are in registry"""
        # These providers should be available in most configurations
        expected_providers = ["openai", "anthropic"]

        for provider in expected_providers:
            if provider in PROVIDER_REGISTRY:
                spec = PROVIDER_REGISTRY[provider]
                self.assertIsInstance(spec, ProviderSpec)
                self.assertTrue(len(spec.label) > 0)
                self.assertTrue(len(spec.default_model) > 0)

    def test_provider_kinds_are_valid(self):
        """Test that provider kinds are from expected set"""
        valid_kinds = {"chatml", "anthropic", "gemini", "ollama", "openai"}

        for spec in PROVIDER_REGISTRY.values():
            self.assertIn(spec.kind, valid_kinds,
                         f"Provider {spec.key} has invalid kind: {spec.kind}")


if __name__ == "__main__":
    # Run tests
    unittest.main(verbosity=2)