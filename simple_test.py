#!/usr/bin/env python3
"""
Simple functional test to verify basic chat bridge functionality.
This focuses on testing the core components that can be tested without API keys.
"""

import sys
import tempfile
import unittest
import sqlite3
import os
from unittest.mock import patch, MagicMock

# Import the modules to test
import chat_bridge
from bridge_agents import Turn, provider_choices, get_spec


class BasicFunctionalTests(unittest.TestCase):
    """Basic tests that verify core functionality works"""

    def test_import_all_modules(self):
        """Test that all modules can be imported without errors"""
        # These should not raise any exceptions
        self.assertIsNotNone(chat_bridge)
        self.assertTrue(hasattr(chat_bridge, 'Colors'))
        self.assertTrue(hasattr(chat_bridge, 'Transcript'))
        self.assertTrue(hasattr(chat_bridge, 'ConversationHistory'))

    def test_database_operations(self):
        """Test database operations work correctly"""
        # Create temporary database
        with tempfile.NamedTemporaryFile(delete=False) as f:
            db_path = f.name

        try:
            # Setup database
            conn = sqlite3.connect(db_path)
            conn.execute("""
                CREATE TABLE IF NOT EXISTS conversations (
                    id TEXT PRIMARY KEY,
                    timestamp TEXT,
                    starter TEXT,
                    agent_a_provider TEXT,
                    agent_b_provider TEXT,
                    status TEXT
                )
            """)
            conn.execute("""
                CREATE TABLE IF NOT EXISTS messages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    conversation_id TEXT,
                    agent_provider TEXT,
                    role TEXT,
                    content TEXT,
                    timestamp TEXT,
                    FOREIGN KEY (conversation_id) REFERENCES conversations(id)
                )
            """)
            conn.commit()

            # Test logging conversation start
            chat_bridge.log_conversation_start(
                conn, "test_conv", "Hello", "openai", "anthropic"
            )

            # Verify data was inserted
            cursor = conn.execute("SELECT * FROM conversations WHERE id = ?", ("test_conv",))
            row = cursor.fetchone()
            self.assertIsNotNone(row)
            self.assertEqual(row[0], "test_conv")
            self.assertEqual(row[2], "Hello")

        finally:
            conn.close()
            os.unlink(db_path)

    def test_transcript_operations(self):
        """Test transcript creation and dumping"""
        transcript = chat_bridge.Transcript()
        transcript.add("Agent A", "assistant", "2023-01-01 12:00:00", "Hello")
        transcript.add("Agent B", "assistant", "2023-01-01 12:01:00", "Hi there")

        with tempfile.NamedTemporaryFile(mode='w', delete=False) as f:
            temp_path = f.name

        try:
            transcript.dump(temp_path)

            with open(temp_path, 'r') as f:
                content = f.read()
                self.assertIn("# Chat Bridge Transcript", content)
                self.assertIn("Hello", content)
                self.assertIn("Hi there", content)
        finally:
            os.unlink(temp_path)

    def test_conversation_history(self):
        """Test conversation history functionality"""
        history = chat_bridge.ConversationHistory()
        history.add_turn("human", "Hello")
        history.add_turn("agent_a", "Hi there")
        history.add_turn("agent_b", "How can I help?")

        # Test basic functionality
        self.assertEqual(len(history.turns), 3)
        self.assertEqual(history.flat_texts, ["Hello", "Hi there", "How can I help?"])

        # Test recent turns
        recent = history.recent_turns(2)
        self.assertEqual(len(recent), 2)
        self.assertEqual(recent[0].text, "Hi there")
        self.assertEqual(recent[1].text, "How can I help?")

    def test_utility_functions(self):
        """Test various utility functions"""
        # Test stop word detection
        stop_words = {"goodbye", "end"}
        self.assertTrue(chat_bridge.contains_stop_word("Well, goodbye!", stop_words))
        self.assertFalse(chat_bridge.contains_stop_word("Hello world", stop_words))

        # Test session path creation
        md_path, log_path = chat_bridge.create_session_paths("Test conversation")
        self.assertTrue(md_path.endswith(".md"))
        self.assertTrue(log_path.endswith(".log"))
        self.assertIn("transcripts", md_path)
        self.assertIn("logs", log_path)

        # Test repetition detection
        normal_texts = ["Hello", "How are you?", "I'm fine", "Great"]
        self.assertFalse(chat_bridge.is_repetitive(normal_texts))

        repetitive_texts = ["Hello"] * 8
        self.assertTrue(chat_bridge.is_repetitive(repetitive_texts))

    def test_color_functions(self):
        """Test color utility functions"""
        result = chat_bridge.colorize("test", chat_bridge.Colors.RED)
        self.assertIn("test", result)
        self.assertIn(chat_bridge.Colors.RED, result)
        self.assertTrue(result.endswith(chat_bridge.Colors.RESET))

        rainbow = chat_bridge.rainbow_text("hello")
        self.assertIn("h", rainbow)
        self.assertTrue(rainbow.endswith(chat_bridge.Colors.RESET))

    def test_provider_registry(self):
        """Test provider registry functionality"""
        providers = provider_choices()
        self.assertIsInstance(providers, list)
        self.assertGreater(len(providers), 0)

        # Test getting specs for available providers
        for provider in providers[:3]:  # Test first 3 providers
            spec = get_spec(provider)
            self.assertIsNotNone(spec.label)
            self.assertIsNotNone(spec.default_model)

    def test_cli_argument_parsing(self):
        """Test CLI argument parsing"""
        with patch('sys.argv', ['chat_bridge.py', '--max-rounds', '50']):
            args = chat_bridge.parse_args()
            self.assertEqual(args.max_rounds, 50)

    @patch('builtins.input', return_value='1')
    def test_menu_selection(self, mock_input):
        """Test menu selection functionality"""
        options = [("option1", "First option"), ("option2", "Second option")]

        with patch('chat_bridge.print_section_header'):
            with patch('chat_bridge.print_menu_option'):
                result = chat_bridge.select_from_menu(options, "Test Menu")
                self.assertEqual(result, "1")

    def test_roles_loading(self):
        """Test roles file loading"""
        # Test with non-existent file
        result = chat_bridge.load_roles_file("nonexistent.json")
        self.assertIsNone(result)

        # Test with valid JSON
        import json
        test_roles = {
            "persona_library": {
                "test": {"name": "test", "provider": "openai", "system": "You are helpful"}
            }
        }

        with tempfile.NamedTemporaryFile(mode='w', suffix='.json', delete=False) as f:
            json.dump(test_roles, f)
            roles_path = f.name

        try:
            loaded = chat_bridge.load_roles_file(roles_path)
            self.assertEqual(loaded, test_roles)
        finally:
            os.unlink(roles_path)


def run_tests():
    """Run the functional tests"""
    print("🧪 Running Chat Bridge Functional Tests")
    print("=" * 50)

    # Create a test suite
    suite = unittest.TestLoader().loadTestsFromTestCase(BasicFunctionalTests)
    runner = unittest.TextTestRunner(verbosity=2)
    result = runner.run(suite)

    print("\n" + "=" * 50)
    if result.wasSuccessful():
        print("✅ All tests passed!")
        return 0
    else:
        print(f"❌ {len(result.failures)} failures, {len(result.errors)} errors")
        return 1


if __name__ == "__main__":
    exit_code = run_tests()
    sys.exit(exit_code)