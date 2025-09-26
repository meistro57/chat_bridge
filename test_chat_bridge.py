#!/usr/bin/env python3
"""
Comprehensive tests for Chat Bridge application.
Tests core functionality including database operations, agent creation,
conversation flow, menu interactions, and file operations.
"""

import asyncio
import json
import os
import sqlite3
import tempfile
import unittest
from datetime import datetime
from unittest.mock import AsyncMock, MagicMock, mock_open, patch
from dataclasses import dataclass

# Import the modules to test
import chat_bridge
from bridge_agents import Turn, ProviderSpec


class TestColors(unittest.TestCase):
    """Test color utility functions"""

    def test_colorize(self):
        """Test colorize function"""
        result = chat_bridge.colorize("test", chat_bridge.Colors.RED)
        self.assertIn("test", result)
        self.assertIn(chat_bridge.Colors.RED, result)
        self.assertTrue(result.endswith(chat_bridge.Colors.RESET))

    def test_colorize_with_bold(self):
        """Test colorize function with bold"""
        result = chat_bridge.colorize("test", chat_bridge.Colors.BLUE, bold=True)
        self.assertIn("test", result)
        self.assertIn(chat_bridge.Colors.BOLD, result)
        self.assertIn(chat_bridge.Colors.BLUE, result)

    def test_rainbow_text(self):
        """Test rainbow text function"""
        result = chat_bridge.rainbow_text("hello")
        # Strip all ANSI codes to check the base text
        import re
        clean_result = re.sub(r'\x1b\[[0-9;]*m', '', result)
        self.assertEqual(clean_result, "hello")
        self.assertTrue(result.endswith(chat_bridge.Colors.RESET))


class TestTranscript(unittest.TestCase):
    """Test Transcript class"""

    def setUp(self):
        self.transcript = chat_bridge.Transcript()

    def test_add_turn(self):
        """Test adding a turn to transcript"""
        self.transcript.add("Agent A", "assistant", "2023-01-01 12:00:00", "Hello world")
        self.assertEqual(len(self.transcript.turns), 1)
        self.assertEqual(self.transcript.turns[0]["agent"], "Agent A")
        self.assertEqual(self.transcript.turns[0]["content"], "Hello world")

    def test_dump_transcript(self):
        """Test dumping transcript to file"""
        self.transcript.add("Agent A", "assistant", "2023-01-01 12:00:00", "Hello")
        self.transcript.add("Agent B", "assistant", "2023-01-01 12:01:00", "Hi there")

        with tempfile.NamedTemporaryFile(mode='w', delete=False) as f:
            temp_path = f.name

        try:
            self.transcript.dump(temp_path)

            with open(temp_path, 'r') as f:
                content = f.read()
                self.assertIn("# Chat Bridge Transcript", content)
                self.assertIn("## Agent A", content)
                self.assertIn("## Agent B", content)
                self.assertIn("Hello", content)
                self.assertIn("Hi there", content)
        finally:
            os.unlink(temp_path)


class TestConversationHistory(unittest.TestCase):
    """Test ConversationHistory class"""

    def setUp(self):
        self.history = chat_bridge.ConversationHistory()

    def test_add_turn(self):
        """Test adding turns to history"""
        self.history.add_turn("human", "Hello")
        self.history.add_turn("agent_a", "Hi there")

        self.assertEqual(len(self.history.turns), 2)
        self.assertEqual(self.history.turns[0].author, "human")
        self.assertEqual(self.history.turns[1].text, "Hi there")

    def test_flat_texts(self):
        """Test getting flat text list"""
        self.history.add_turn("human", "Hello")
        self.history.add_turn("agent_a", "Hi there")

        texts = self.history.flat_texts
        self.assertEqual(texts, ["Hello", "Hi there"])

    def test_recent_turns(self):
        """Test getting recent turns with limit"""
        for i in range(5):
            self.history.add_turn("agent", f"Message {i}")

        recent = self.history.recent_turns(3)
        self.assertEqual(len(recent), 3)
        self.assertEqual(recent[0].text, "Message 2")
        self.assertEqual(recent[2].text, "Message 4")


class TestDatabaseOperations(unittest.TestCase):
    """Test database operations"""

    def setUp(self):
        self.temp_db = tempfile.NamedTemporaryFile(delete=False)
        self.temp_db.close()
        self.conn = sqlite3.connect(self.temp_db.name)

        # Setup tables
        self.conn.execute("""
            CREATE TABLE IF NOT EXISTS conversations (
                id TEXT PRIMARY KEY,
                timestamp TEXT,
                starter TEXT,
                agent_a_provider TEXT,
                agent_b_provider TEXT,
                status TEXT
            )
        """)
        self.conn.execute("""
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
        self.conn.commit()

    def tearDown(self):
        self.conn.close()
        os.unlink(self.temp_db.name)

    def test_setup_database(self):
        """Test database setup"""
        with tempfile.NamedTemporaryFile(delete=False) as f:
            db_path = f.name

        try:
            # Mock sqlite3.connect to return our test connection
            with patch('sqlite3.connect', return_value=self.conn):
                conn = chat_bridge.setup_database()

                # Verify tables exist
                cursor = conn.execute("SELECT name FROM sqlite_master WHERE type='table'")
                tables = [row[0] for row in cursor.fetchall()]
                self.assertIn("conversations", tables)
                self.assertIn("messages", tables)
        finally:
            if os.path.exists(db_path):
                os.unlink(db_path)

    def test_log_conversation_start(self):
        """Test logging conversation start"""
        chat_bridge.log_conversation_start(
            self.conn, "test_conv_123", "Hello world", "openai", "anthropic"
        )

        cursor = self.conn.execute("SELECT * FROM conversations WHERE id = ?", ("test_conv_123",))
        row = cursor.fetchone()

        self.assertIsNotNone(row)
        self.assertEqual(row[0], "test_conv_123")  # id
        self.assertEqual(row[2], "Hello world")    # starter
        self.assertEqual(row[3], "openai")         # agent_a_provider
        self.assertEqual(row[4], "anthropic")      # agent_b_provider
        self.assertEqual(row[5], "active")         # status

    @patch('sqlite3.connect')
    def test_log_message_sql(self, mock_connect):
        """Test logging message to SQL"""
        mock_conn = MagicMock()
        mock_connect.return_value = mock_conn

        chat_bridge.log_message_sql("conv_123", "openai", "assistant", "Test message")

        mock_connect.assert_called_once_with("bridge.db")
        mock_conn.execute.assert_called_once()
        mock_conn.commit.assert_called_once()
        mock_conn.close.assert_called_once()


class TestUtilityFunctions(unittest.TestCase):
    """Test utility functions"""

    def test_contains_stop_word(self):
        """Test stop word detection"""
        stop_words = {"goodbye", "end chat", "terminate"}

        self.assertTrue(chat_bridge.contains_stop_word("Well, goodbye everyone!", stop_words))
        self.assertTrue(chat_bridge.contains_stop_word("Let's end chat now", stop_words))
        self.assertFalse(chat_bridge.contains_stop_word("Hello world", stop_words))

    def test_is_repetitive(self):
        """Test repetitive message detection"""
        # Non-repetitive messages
        texts = ["Hello", "How are you?", "I'm fine", "Great day"]
        self.assertFalse(chat_bridge.is_repetitive(texts))

        # Repetitive messages
        repetitive = ["Hello there", "Hello there", "Hello there", "Hello there", "Hello there", "Hello there"]
        self.assertTrue(chat_bridge.is_repetitive(repetitive))

        # Too few messages
        short = ["Hello", "Hi"]
        self.assertFalse(chat_bridge.is_repetitive(short))

    def test_create_session_paths(self):
        """Test session path creation"""
        starter = "What is artificial intelligence?"
        md_path, log_path = chat_bridge.create_session_paths(starter)

        self.assertTrue(md_path.startswith("transcripts/"))
        self.assertTrue(md_path.endswith(".md"))
        self.assertTrue(log_path.startswith("logs/"))
        self.assertTrue(log_path.endswith(".log"))
        self.assertIn("what-is-artificial-intelligence", md_path.lower())


class TestLogging(unittest.TestCase):
    """Test logging functionality"""

    def test_setup_logging(self):
        """Test logger setup"""
        bridge_logger, session_logger = chat_bridge.setup_logging()

        self.assertEqual(bridge_logger.name, "bridge")
        self.assertEqual(bridge_logger.level, 20)  # INFO level
        self.assertEqual(session_logger.name, "session")

    def test_setup_session_logger(self):
        """Test session logger setup"""
        with tempfile.TemporaryDirectory() as temp_dir:
            log_path = os.path.join(temp_dir, "test_session.log")

            logger = chat_bridge.setup_session_logger(log_path)

            self.assertTrue(logger.name.startswith("session_"))
            self.assertEqual(logger.level, 20)  # INFO level
            self.assertTrue(len(logger.handlers) > 0)

            # Test logging
            logger.info("Test message")

            # Verify file was created and contains message
            self.assertTrue(os.path.exists(log_path))
            with open(log_path, 'r') as f:
                content = f.read()
                self.assertIn("Test message", content)


class TestRolesAndPersonas(unittest.TestCase):
    """Test roles and persona functionality"""

    def test_load_roles_file_success(self):
        """Test successful roles file loading"""
        roles_data = {
            "persona_library": {
                "scientist": {
                    "provider": "openai",
                    "system": "You are a scientist",
                    "guidelines": ["Be precise", "Use data"]
                }
            },
            "stop_words": ["end", "goodbye"]
        }

        with tempfile.NamedTemporaryFile(mode='w', suffix='.json', delete=False) as f:
            json.dump(roles_data, f)
            roles_path = f.name

        try:
            loaded_data = chat_bridge.load_roles_file(roles_path)
            self.assertEqual(loaded_data, roles_data)
        finally:
            os.unlink(roles_path)

    def test_load_roles_file_not_found(self):
        """Test roles file loading when file doesn't exist"""
        result = chat_bridge.load_roles_file("nonexistent.json")
        self.assertIsNone(result)

    def test_load_roles_file_invalid_json(self):
        """Test roles file loading with invalid JSON"""
        with tempfile.NamedTemporaryFile(mode='w', suffix='.json', delete=False) as f:
            f.write("invalid json {")
            invalid_path = f.name

        try:
            result = chat_bridge.load_roles_file(invalid_path)
            self.assertIsNone(result)
        finally:
            os.unlink(invalid_path)

    @patch('chat_bridge.get_spec')
    def test_apply_persona(self, mock_get_spec):
        """Test applying persona to agent"""
        # Mock agent
        mock_agent = MagicMock()
        mock_agent.system_prompt = "Default prompt"

        # Mock spec
        mock_spec = MagicMock()
        mock_spec.label = "OpenAI"
        mock_get_spec.return_value = mock_spec

        # Test data
        roles_data = {
            "persona_library": {
                "scientist": {
                    "provider": "openai",
                    "system": "You are a scientist",
                    "guidelines": ["Be precise", "Use data"]
                }
            }
        }

        # Apply persona
        result = chat_bridge.apply_persona(mock_agent, "scientist", roles_data)

        # Verify system prompt was updated
        expected_system = "You are a scientist\n\nGuidelines:\n• Be precise\n• Use data"
        self.assertEqual(result.system_prompt, expected_system)

    def test_apply_persona_no_persona(self):
        """Test applying persona when no persona specified"""
        mock_agent = MagicMock()
        result = chat_bridge.apply_persona(mock_agent, None, {})
        self.assertEqual(result, mock_agent)


class TestMenuInteractions(unittest.TestCase):
    """Test menu interaction functions"""

    @patch('chat_bridge.get_user_input')
    @patch('chat_bridge.print_section_header')
    @patch('chat_bridge.print_menu_option')
    def test_select_from_menu_single(self, mock_print_option, mock_print_header, mock_input):
        """Test single selection from menu"""
        options = [("option1", "First option"), ("option2", "Second option")]
        mock_input.return_value = "1"

        result = chat_bridge.select_from_menu(options, "Test Menu")

        self.assertEqual(result, "1")
        mock_print_header.assert_called_once_with("Test Menu")
        self.assertEqual(mock_print_option.call_count, 2)

    @patch('chat_bridge.get_user_input')
    @patch('chat_bridge.print_section_header')
    def test_select_from_menu_multiple(self, mock_print_header, mock_input):
        """Test multiple selection from menu"""
        options = [("opt1", "Option 1"), ("opt2", "Option 2"), ("opt3", "Option 3")]
        mock_input.return_value = "1,3"

        result = chat_bridge.select_from_menu(options, "Test Menu", allow_multiple=True)

        self.assertEqual(result, "1,3")

    @patch('chat_bridge.get_user_input')
    @patch('chat_bridge.print_error')
    def test_select_from_menu_invalid_input(self, mock_print_error, mock_input):
        """Test menu selection with invalid input"""
        options = [("option1", "First option")]
        mock_input.side_effect = ["invalid", "5", "1"]

        result = chat_bridge.select_from_menu(options, "Test Menu")

        self.assertEqual(result, "1")
        self.assertEqual(mock_print_error.call_count, 2)

    @patch('builtins.input')
    def test_get_user_input_with_default(self, mock_input):
        """Test get_user_input with default value"""
        mock_input.return_value = ""

        result = chat_bridge.get_user_input("Enter value", "default")

        self.assertEqual(result, "default")

    @patch('builtins.input')
    def test_get_user_input_keyboard_interrupt(self, mock_input):
        """Test get_user_input with keyboard interrupt"""
        mock_input.side_effect = KeyboardInterrupt()

        with self.assertRaises(SystemExit):
            chat_bridge.get_user_input("Enter value")

    @patch('builtins.input')
    def test_get_user_input_eof_with_default(self, mock_input):
        """Test get_user_input with EOF and default value"""
        mock_input.side_effect = EOFError()

        result = chat_bridge.get_user_input("Enter value", "default")

        self.assertEqual(result, "default")


class TestCLIArguments(unittest.TestCase):
    """Test CLI argument parsing"""

    def test_parse_args_defaults(self):
        """Test parsing arguments with defaults"""
        # Mock sys.argv to avoid interference
        with patch('sys.argv', ['chat_bridge.py']):
            args = chat_bridge.parse_args()

            self.assertEqual(args.temp_a, 0.7)
            self.assertEqual(args.temp_b, 0.7)
            self.assertEqual(args.max_rounds, 30)
            self.assertEqual(args.mem_rounds, 8)

    def test_parse_args_with_values(self):
        """Test parsing arguments with specified values"""
        test_args = [
            'chat_bridge.py',
            '--provider-a', 'openai',
            '--provider-b', 'anthropic',
            '--max-rounds', '50',
            '--temp-a', '0.5',
            '--starter', 'Test conversation'
        ]

        with patch('sys.argv', test_args):
            args = chat_bridge.parse_args()

            self.assertEqual(args.provider_a, 'openai')
            self.assertEqual(args.provider_b, 'anthropic')
            self.assertEqual(args.max_rounds, 50)
            self.assertEqual(args.temp_a, 0.5)
            self.assertEqual(args.starter, 'Test conversation')


class TestPrintFunctions(unittest.TestCase):
    """Test printing functions"""

    @patch('builtins.print')
    def test_print_banner(self, mock_print):
        """Test print banner function"""
        chat_bridge.print_banner()

        mock_print.assert_called()
        call_args = mock_print.call_args[0][0]
        self.assertIn("CHAT BRIDGE", call_args)

    @patch('builtins.print')
    def test_print_success(self, mock_print):
        """Test print success function"""
        chat_bridge.print_success("Test success message")

        mock_print.assert_called_once()
        call_args = mock_print.call_args[0][0]
        self.assertIn("✅", call_args)
        self.assertIn("Test success message", call_args)

    @patch('builtins.print')
    def test_print_error(self, mock_print):
        """Test print error function"""
        chat_bridge.print_error("Test error message")

        mock_print.assert_called_once()
        call_args = mock_print.call_args[0][0]
        self.assertIn("❌", call_args)
        self.assertIn("Test error message", call_args)

    @patch('builtins.print')
    def test_print_section_header(self, mock_print):
        """Test print section header function"""
        chat_bridge.print_section_header("Test Section", "🔧")

        # Should call print multiple times for the header
        self.assertGreaterEqual(mock_print.call_count, 3)

        # Check that "TEST SECTION" appears in one of the calls
        calls_text = ' '.join([str(call) for call in mock_print.call_args_list])
        self.assertIn("TEST SECTION", calls_text)


if __name__ == "__main__":
    # Run tests
    unittest.main(verbosity=2)