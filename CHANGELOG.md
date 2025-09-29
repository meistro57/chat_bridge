# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.2] - 2025-09-29

### Fixed
- **Model Name Updates**: Updated all default model names to current API-verified versions
  - OpenAI: `gpt-4.1-mini` → `gpt-4o-mini`
  - Anthropic: `claude-3-5-sonnet-20240620` → `claude-3-5-sonnet-20241022`
  - Gemini: `gemini-flash-latest` → `gemini-2.5-flash`
- **Documentation Updates**: Corrected all model references across README.md, TESTING.md, and ENHANCEMENTS.md
- **DeepSeek Documentation**: Enhanced documentation to include DeepSeek provider in testing checklist and troubleshooting
- **Certification Status**: PASSED (90.3% success rate) with updated model names

### Technical Details
- Modified `bridge_agents.py:52,63,74` to use current API-verified model names
- Updated documentation to reflect current provider capabilities and model names
- Added DeepSeek provider to testing checklist (6 providers total)
- Added DeepSeek-specific troubleshooting section in README.md
- Updated rate limiting documentation to include DeepSeek
- All changes maintain backward compatibility through environment variable overrides

## [1.2.1] - 2025-09-29

### Fixed
- **Roles Manager Integration**: Fixed compatibility issue where personas created with roles_manager.py were not showing up in the main app's role selection menu
- **Unified Role Selection**: Updated `select_role_modes()` function to dynamically load all personas from the persona_library instead of checking only hardcoded role names
- **Enhanced Persona Display**: Role selection menu now shows provider information and system prompt previews for all personas

### Changed
- Role selection now displays all available personas (both hardcoded and custom) in a unified interface
- Improved persona descriptions with provider names and truncated system prompt previews
- Maintained backward compatibility with existing hardcoded role modes (scientist, philosopher, comedian, steel_worker)

### Technical Details
- Modified `chat_bridge.py:349-420` to replace hardcoded role checking with dynamic persona enumeration
- Updated role selection logic to work with any persona key from the persona_library
- Preserved custom role creation functionality and permanent saving options

## [1.2.0] - 2025-09-26

### Added
- **Stop Word Detection Toggle**: Enable/disable conversation termination control through interactive menu
- **Enhanced Session Transcripts**: Comprehensive session configuration tracking in transcript headers
- **Simplified Menu Structure**: Quick Start, Role Personalities, and Advanced Setup modes
- **Role Personality First Flow**: Choose personalities first with providers auto-selected
- **Custom Role Creation**: Create fully customizable AI roles with user-defined settings
- **Provider Connectivity Testing**: Ping and diagnose AI provider connections
- **Enhanced Error Handling**: Improved error messages and troubleshooting guidance

### Changed
- Streamlined main menu navigation with fewer levels for common use cases
- Enhanced role selection with direct access to preset personas
- Improved session summary display with stop word detection status
- Unified script combining all previous functionality

### Fixed
- Various stability improvements and error handling enhancements
- Better handling of non-interactive mode scenarios
- Improved logging and transcript generation

## [1.1.0] - Previous versions

- Initial release with multi-provider support
- Basic role system implementation
- SQLite logging and transcript generation
- Interactive setup and provider selection