# KODING.md - Chat Bridge Project Guidelines

## Build/Lint/Test Commands

### Testing
- All tests: `python run_tests.py` - Runs comprehensive test suite using unittest
- Single test file: `python -m unittest <test_file.py>` (e.g., `python -m unittest test_chat_bridge.py::TestColors::test_colorize`)
- Functional tests: `python simple_test.py` - Basic connectivity checks

### Dependencies
- Install: `pip install -r requirements.txt` (httpx, python-dotenv, google-generativeai, inquirer)

## Code Style Guidelines

### Imports & Organization
- Use `from __future__ import annotations`
- Standard library imports first, then third-party, then local imports
- Group imports with blank lines: stdlib, blank line, third-party, blank line, local
- Prefer explicit imports over wildcard imports
- Use absolute imports for clarity

### Naming Conventions
- Classes: PascalCase (`ChatBridgeApp`, `Colors`)
- Functions: snake_case (`colorize_text`, `print_banner`)
- Constants: UPPER_SNAKE_CASE (`MAX_ROUNDS = 30`)
- Variables: snake_case (`provider_a`, `conversation_history`)

### Type Hints
- Use comprehensive type hints with `typing` module
- Use unions, Optional, List, Dict as needed
- Prefer generics over Any when possible

### Documentation
- Triple-quoted docstrings for modules, classes, functions
- One-line for simple functions, multi-line for complex
- Docstring format: brief description, then detailed explanation if needed

### Code Style
- PEP 8 compliant with type hints
- Use dataclasses for simple data structures (`@dataclass`)
- Consistent indentation (4 spaces)
- Max line length: 88 characters recommended
- Use context managers for resource management

### Error Handling
- Use specific exception types over generic Exception
- Provide meaningful error messages
- Use logging for debugging and errors

### AI/LLM Integration
- Validate API keys before use
- Use environment variables for secrets (via python-dotenv)
- Implement proper timeouts and retries for API calls
- Log requests/responses for debugging (securely)

### Performance
- Use async/await for I/O operations (httpx)
- Implement proper rate limiting for API calls
- Use efficient data structures for conversation memory