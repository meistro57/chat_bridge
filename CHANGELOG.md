# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2025-10-16

### Added
- **OpenRouter Provider Integration**: Access 200+ AI models through unified OpenRouter API with categorized browsing
- **HTTP-based MCP Memory System**: RESTful API endpoints for conversation memory and contextual awareness
- **Enhanced OpenRouter Support**: Added app name and referer headers for better logging, provider filtering detection
- **MCP HTTP Endpoints**: 6 RESTful endpoints (health, stats, recent chats, search, contextual memory, conversation details)
- **Improved Error Handling**: OpenRouter-specific error messages for provider filtering and model availability
- **Continuous Memory Integration**: Fresh context retrieved on every conversation turn via MCP
- **Database Migration**: Unified SQLAlchemy database for both web GUI and MCP system

### Changed
- **MCP Architecture**: Migrated from stdio-based FastMCP to HTTP-based integration via main.py FastAPI server
- **Import Fallback**: Improved MCP server import fallback handling for better reliability
- **Documentation Updates**: Comprehensive updates to README, CLAUDE.md, and web_gui/README.md with OpenRouter and MCP details

### Fixed
- **OpenRouter Provider Filtering**: Better error detection and user guidance for blocked providers
- **MCP Server Stability**: Enhanced error handling and graceful degradation when MCP unavailable

### Technical Details
- Modified `bridge_agents.py` to add OpenRouter provider with OpenAI-compatible format
- Enhanced `main.py` with MCP memory endpoints and SQLAlchemy integration
- Updated provider registry to include OpenRouter with proper authentication headers
- Added environment variables: `OPENROUTER_API_KEY`, `OPENROUTER_MODEL`, `OPENROUTER_APP_NAME`, `OPENROUTER_REFERER`, `MCP_BASE_URL`

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