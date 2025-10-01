import json
import os
import tempfile
import unittest
from unittest.mock import patch

import roles_manager


class TestRolesManagerDiagnostics(unittest.TestCase):
    def test_missing_file_reports_error(self):
        with tempfile.TemporaryDirectory() as tmpdir:
            config_path = os.path.join(tmpdir, "missing_roles.json")
            with patch("roles_manager.print_error") as mock_error:
                manager = roles_manager.RolesManager(config_path)
            abs_path = os.path.abspath(config_path)
            self.assertTrue(any(abs_path in call.args[0] and "not found" in call.args[0]
                                for call in mock_error.call_args_list))
            self.assertTrue(os.path.exists(abs_path))
            self.assertTrue(manager.config)

    def test_invalid_schema_falls_back_to_defaults(self):
        with tempfile.NamedTemporaryFile(mode="w", suffix=".json", delete=False) as tmp:
            invalid_config = {
                "agent_a": {"provider": "openai", "system": "a", "guidelines": []},
                "agent_b": {"provider": "openai", "system": "b", "guidelines": []},
                "persona_library": {
                    "broken": {
                        "provider": "openai",
                        "system": "broken persona",
                        "guidelines": []
                    }
                },
                "stop_words": ["end"],
                "temp_a": 0.5,
                "temp_b": 0.5
            }
            json.dump(invalid_config, tmp)
            tmp_path = tmp.name

        try:
            with patch("roles_manager.print_error") as mock_error, patch("roles_manager.print_warning") as mock_warning:
                manager = roles_manager.RolesManager(tmp_path)
            self.assertTrue(any("missing required field 'name'" in call.args[0]
                                for call in mock_error.call_args_list))
            self.assertTrue(any("invalid configuration schema" in call.args[0].lower()
                                for call in mock_warning.call_args_list))
            self.assertNotEqual(manager.config.get("persona_library", {}), invalid_config["persona_library"])
        finally:
            os.unlink(tmp_path)

    def test_save_config_blocks_invalid_data(self):
        with tempfile.TemporaryDirectory() as tmpdir:
            config_path = os.path.join(tmpdir, "roles.json")
            manager = roles_manager.RolesManager(config_path)

            invalid_config = dict(manager.config)
            invalid_config["temp_a"] = 3.5

            with patch("roles_manager.print_error") as mock_error:
                success = manager.save_config(invalid_config)

            self.assertFalse(success)
            self.assertTrue(any("temp_a" in call.args[0] and "between 0 and 2" in call.args[0]
                                for call in mock_error.call_args_list))
            self.assertTrue(any("Aborting save" in call.args[0] for call in mock_error.call_args_list))


if __name__ == "__main__":
    unittest.main()
