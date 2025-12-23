# Chat Bridge - Go Port Architecture Plan

## Overview
Port the Chat Bridge CLI from Python to Go while preserving the beautiful retro terminal aesthetic and enhancing with Go's strengths: concurrency, performance, and single-binary distribution.

## Why Go?

### Advantages
1. **Single Binary** - No Python interpreter required, easy distribution
2. **Performance** - Faster startup (< 50ms vs ~500ms Python), lower memory usage
3. **Concurrency** - Goroutines perfect for streaming AI responses
4. **Type Safety** - Compile-time error catching
5. **Excellent Libraries** - Rich ecosystem for CLI, HTTP, and terminal UI
6. **Cross-compilation** - Build for Linux, macOS, Windows from one machine

### Preserved Features
- Beautiful retro terminal styling
- Interactive menus and wizards
- Real-time streaming responses
- All AI provider support (OpenAI, Anthropic, Gemini, etc.)
- MCP memory integration
- Persona system
- Comprehensive logging

## Technology Stack

### Core Libraries

#### 1. **CLI Framework**
**Choice**: [cobra](https://github.com/spf13/cobra) + [viper](https://github.com/spf13/viper)
- Industry standard (used by kubectl, docker, hugo)
- Subcommand support
- Auto-generated help
- Configuration management (env, file, flags)

```go
// Example command structure
chat-bridge start --provider-a openai --provider-b anthropic
chat-bridge test --provider openai
chat-bridge personas list
chat-bridge personas create "Steel Worker"
```

#### 2. **Terminal UI**
**Choice**: [lipgloss](https://github.com/charmbracelet/lipgloss) + [bubbles](https://github.com/charmbracelet/bubbles)
- Modern, composable terminal styling
- Colors, borders, padding, alignment
- Retro theme support (cyan, green, yellow colors)
- Interactive components (spinners, progress, input)

**Alternative**: [termenv](https://github.com/muesli/termenv) for lower-level control

```go
// Lipgloss retro styling
retroStyle := lipgloss.NewStyle().
    Foreground(lipgloss.Color("14")).  // Cyan
    Border(lipgloss.RoundedBorder()).
    Padding(1)

fmt.Println(retroStyle.Render("🌉 CHAT BRIDGE"))
```

#### 3. **Interactive Menus**
**Choice**: [promptui](https://github.com/manifoldco/promptui) or [survey](https://github.com/AlecAivazis/survey)
- Select from lists
- Multi-select
- Text input with validation
- Styled prompts

```go
// Example persona selection
prompt := promptui.Select{
    Label: "Select Agent A Persona",
    Items: []string{"Scientist", "Philosopher", "Steel Worker"},
}

_, persona, _ := prompt.Run()
```

#### 4. **HTTP Client**
**Choice**: Standard `net/http` + [go-retryablehttp](https://github.com/hashicorp/go-retryablehttp)
- Native Go HTTP client is excellent
- Retry logic for API failures
- Context support for timeouts

```go
// Streaming response handling
resp, _ := client.Get("https://api.openai.com/v1/chat/completions")
reader := bufio.NewReader(resp.Body)
for {
    line, _ := reader.ReadString('\n')
    // Stream to terminal
}
```

#### 5. **Database**
**Choice**: [go-sqlite3](https://github.com/mattn/go-sqlite3)
- Pure Go SQLite driver
- Same schema as Python version
- Conversation and message logging

#### 6. **Logging**
**Choice**: [zap](https://github.com/uber-go/zap) or [zerolog](https://github.com/rs/zerolog)
- Structured logging
- High performance
- Multiple outputs (file, stderr)

#### 7. **Configuration**
**Choice**: [viper](https://github.com/spf13/viper)
- .env file loading
- Environment variables
- Config file (YAML/JSON)
- Unified with cobra

## Architecture

### Project Structure

```
chat-bridge-go/
├── cmd/
│   ├── root.go          # Main cobra command
│   ├── start.go         # Start conversation command
│   ├── test.go          # Provider testing command
│   ├── personas.go      # Persona management commands
│   └── version.go       # Version command
├── pkg/
│   ├── providers/       # AI provider clients
│   │   ├── provider.go      # Provider interface
│   │   ├── openai.go        # OpenAI implementation
│   │   ├── anthropic.go     # Anthropic implementation
│   │   ├── gemini.go        # Gemini implementation
│   │   ├── ollama.go        # Ollama implementation
│   │   ├── openrouter.go    # OpenRouter implementation
│   │   └── deepseek.go      # DeepSeek implementation
│   ├── ui/              # Terminal UI components
│   │   ├── colors.go        # Color constants and styling
│   │   ├── banner.go        # Welcome banner
│   │   ├── menus.go         # Interactive menus
│   │   └── progress.go      # Streaming indicators
│   ├── mcp/             # MCP memory integration
│   │   ├── client.go        # MCP HTTP/stdio client
│   │   └── memory.go        # Memory enhancement
│   ├── storage/         # Data persistence
│   │   ├── database.go      # SQLite operations
│   │   ├── transcript.go    # Markdown transcripts
│   │   └── logger.go        # Session logging
│   ├── personas/        # Persona system
│   │   ├── manager.go       # Persona CRUD
│   │   ├── library.go       # Built-in personas
│   │   └── validator.go     # Schema validation
│   ├── conversation/    # Conversation orchestration
│   │   ├── bridge.go        # Main bridge logic
│   │   ├── history.go       # Message history
│   │   ├── validator.go     # Stop word detection
│   │   └── streaming.go     # Stream handling
│   └── config/          # Configuration management
│       ├── config.go        # Config loading
│       └── defaults.go      # Default values
├── internal/            # Private application code
│   └── version/
│       └── version.go       # Version info
├── main.go              # Entry point
├── go.mod               # Go modules
├── go.sum               # Dependency checksums
├── .env.example         # Environment template
├── Makefile             # Build automation
└── README.md            # Documentation
```

### Core Interfaces

#### Provider Interface
```go
// Provider defines AI provider capabilities
type Provider interface {
    // Name returns provider identifier
    Name() string

    // Models lists available models
    Models(ctx context.Context) ([]string, error)

    // StreamChat streams a chat completion response
    StreamChat(ctx context.Context, req ChatRequest) (<-chan string, <-chan error)

    // Health checks provider connectivity
    Health(ctx context.Context) error
}

// ChatRequest encapsulates a chat request
type ChatRequest struct {
    Model       string
    Messages    []Message
    Temperature float64
    MaxTokens   int
}

// Message represents a chat message
type Message struct {
    Role    string // "system", "user", "assistant"
    Content string
}
```

#### Streaming Pattern
```go
// Streaming with goroutines and channels
func (p *OpenAIProvider) StreamChat(ctx context.Context, req ChatRequest) (<-chan string, <-chan error) {
    textChan := make(chan string)
    errChan := make(chan error, 1)

    go func() {
        defer close(textChan)
        defer close(errChan)

        // Make API request
        resp, err := p.makeRequest(ctx, req)
        if err != nil {
            errChan <- err
            return
        }
        defer resp.Body.Close()

        // Stream response line by line
        scanner := bufio.NewScanner(resp.Body)
        for scanner.Scan() {
            if ctx.Err() != nil {
                return // Context cancelled
            }

            line := scanner.Text()
            // Parse SSE format and extract text
            text := parseSSE(line)
            if text != "" {
                textChan <- text
            }
        }

        if err := scanner.Err(); err != nil {
            errChan <- err
        }
    }()

    return textChan, errChan
}
```

#### Bridge Orchestration
```go
// Bridge orchestrates conversation between two agents
type Bridge struct {
    agentA   Agent
    agentB   Agent
    history  *History
    database *Database
    mcp      *MCPClient
    ui       *UI
}

// Run executes the conversation
func (b *Bridge) Run(ctx context.Context, opts Options) error {
    // Initialize session
    sessionID := generateID()
    transcript := NewTranscript(sessionID)

    // Start conversation loop
    currentAgent := b.agentA
    for round := 1; round <= opts.MaxRounds; round++ {
        // Get memory context
        memCtx := ""
        if opts.EnableMCP {
            memCtx = b.mcp.GetTurnContext(b.history.Recent(3))
        }

        // Build prompt
        prompt := b.buildPrompt(currentAgent, memCtx)

        // Stream response
        textChan, errChan := currentAgent.Provider.StreamChat(ctx, ChatRequest{
            Model:       currentAgent.Model,
            Messages:    prompt,
            Temperature: currentAgent.Temperature,
        })

        // Display stream with indicator
        var fullText string
        b.ui.ShowTypingIndicator(currentAgent.Name)

        for {
            select {
            case text, ok := <-textChan:
                if !ok {
                    goto StreamDone
                }
                fullText += text
                b.ui.PrintStreamChunk(text)
            case err := <-errChan:
                return fmt.Errorf("stream error: %w", err)
            case <-ctx.Done():
                return ctx.Err()
            }
        }

    StreamDone:
        b.ui.HideTypingIndicator()

        // Add to history
        b.history.AddTurn(currentAgent.Name, fullText)
        transcript.AddTurn(currentAgent.Name, fullText, round)

        // Log to database
        go b.database.LogMessage(sessionID, currentAgent.Name, fullText)

        // Check stop conditions
        if containsStopWord(fullText, opts.StopWords) {
            b.ui.Info("Stop word detected")
            break
        }

        // Switch agent
        currentAgent = b.switchAgent(currentAgent)
    }

    // Save transcript
    transcript.SaveMarkdown(fmt.Sprintf("transcripts/%s.md", sessionID))

    return nil
}
```

### Retro UI Implementation

```go
package ui

import (
    "github.com/charmbracelet/lipgloss"
)

// Retro color scheme
var (
    Cyan    = lipgloss.Color("14")
    Green   = lipgloss.Color("10")
    Yellow  = lipgloss.Color("11")
    Red     = lipgloss.Color("9")
    Magenta = lipgloss.Color("13")
    Blue    = lipgloss.Color("12")
    Dim     = lipgloss.Color("240")
)

// Retro styles
var (
    Banner = lipgloss.NewStyle().
        Foreground(Cyan).
        Bold(true).
        Align(lipgloss.Center)

    SectionHeader = lipgloss.NewStyle().
        Foreground(Yellow).
        Bold(true).
        MarginTop(1).
        MarginBottom(1)

    MenuOption = lipgloss.NewStyle().
        Foreground(Cyan).
        MarginLeft(2)

    AgentA = lipgloss.NewStyle().
        Foreground(Green).
        Bold(true)

    AgentB = lipgloss.NewStyle().
        Foreground(Magenta).
        Bold(true)

    Success = lipgloss.NewStyle().
        Foreground(Green)

    Error = lipgloss.NewStyle().
        Foreground(Red).
        Bold(true)
)

// PrintBanner displays the welcome banner
func PrintBanner() {
    banner := `
╔══════════════════════════════════════════════════════════════════╗
║                          🌉 CHAT BRIDGE 🌉                        ║
║                     Connect Two AI Assistants                     ║
║                                                                    ║
║                    🎭 Personas  ⚙️ Configurable                   ║
╚══════════════════════════════════════════════════════════════════╝
    `
    fmt.Println(Banner.Render(banner))
}

// ShowTypingIndicator displays an animated typing indicator
func (ui *UI) ShowTypingIndicator(agentName string) {
    ui.spinner = spinner.New()
    ui.spinner.Spinner = spinner.Dot
    ui.spinner.Style = lipgloss.NewStyle().Foreground(Cyan)
    ui.spinner.Start()

    go func() {
        for {
            select {
            case <-ui.stopSpinner:
                ui.spinner.Stop()
                return
            case <-time.After(100 * time.Millisecond):
                fmt.Printf("\r%s %s is typing... %s",
                    AgentA.Render(agentName),
                    ui.spinner.View(),
                    strings.Repeat(" ", 10),
                )
            }
        }
    }()
}
```

## Build System

### Makefile
```makefile
# Makefile for Chat Bridge Go

.PHONY: build test clean install

# Variables
BINARY_NAME=chat-bridge
VERSION=$(shell git describe --tags --always --dirty)
LDFLAGS=-ldflags "-X github.com/yourusername/chat-bridge/internal/version.Version=${VERSION}"

# Build for current platform
build:
	go build ${LDFLAGS} -o bin/${BINARY_NAME} .

# Build for all platforms
build-all:
	GOOS=linux GOARCH=amd64 go build ${LDFLAGS} -o bin/${BINARY_NAME}-linux-amd64 .
	GOOS=darwin GOARCH=amd64 go build ${LDFLAGS} -o bin/${BINARY_NAME}-darwin-amd64 .
	GOOS=darwin GOARCH=arm64 go build ${LDFLAGS} -o bin/${BINARY_NAME}-darwin-arm64 .
	GOOS=windows GOARCH=amd64 go build ${LDFLAGS} -o bin/${BINARY_NAME}-windows-amd64.exe .

# Run tests
test:
	go test -v ./...

# Run tests with coverage
test-coverage:
	go test -v -cover -coverprofile=coverage.out ./...
	go tool cover -html=coverage.out

# Install locally
install:
	go install ${LDFLAGS} .

# Clean build artifacts
clean:
	rm -rf bin/
	rm -f coverage.out

# Run with live reload (requires air)
dev:
	air

# Format code
fmt:
	go fmt ./...
	goimports -w .

# Lint code
lint:
	golangci-lint run

# Generate mocks for testing
mocks:
	mockgen -source=pkg/providers/provider.go -destination=pkg/providers/mocks/provider.go
```

## Migration Strategy

### Phase 1: Core Infrastructure (Week 1)
1. **Setup project structure**
   - Initialize Go modules
   - Create directory layout
   - Setup build system

2. **Implement provider interface**
   - Define `Provider` interface
   - Implement OpenAI provider (reference)
   - Add streaming support

3. **Basic CLI**
   - Cobra command structure
   - Configuration loading (viper)
   - Simple start command

### Phase 2: Providers (Week 2)
1. **Implement all providers**
   - Anthropic (Claude)
   - Gemini (Google)
   - Ollama (local)
   - OpenRouter
   - DeepSeek

2. **Provider testing**
   - Health checks
   - Model listing
   - Connectivity tests

### Phase 3: UI & Interaction (Week 3)
1. **Terminal UI**
   - Retro color scheme
   - Banner and headers
   - Menu system
   - Typing indicators

2. **Interactive wizards**
   - Provider selection
   - Persona configuration
   - Model selection

### Phase 4: Core Features (Week 4)
1. **Conversation orchestration**
   - Bridge logic
   - Message history
   - Round management
   - Stop word detection

2. **Streaming display**
   - Real-time output
   - Typing indicators
   - Progress feedback

### Phase 5: Persistence (Week 5)
1. **Database integration**
   - SQLite setup
   - Conversation logging
   - Message storage

2. **Transcripts**
   - Markdown generation
   - Session metadata
   - File management

### Phase 6: Advanced Features (Week 6)
1. **MCP integration**
   - HTTP client
   - Memory queries
   - Context enhancement

2. **Persona system**
   - Load from JSON
   - Validation
   - Management commands

### Phase 7: Polish & Release (Week 7)
1. **Error handling**
   - Graceful degradation
   - Helpful messages
   - Recovery strategies

2. **Documentation**
   - README
   - Usage examples
   - API docs

3. **Testing**
   - Unit tests (70%+ coverage)
   - Integration tests
   - Provider mocks

4. **Release**
   - GitHub releases
   - Pre-built binaries
   - Installation instructions

## Example Usage

```bash
# Build
make build

# Install globally
sudo make install

# Run with interactive mode
chat-bridge start

# Run with CLI flags
chat-bridge start \
  --provider-a openai \
  --model-a gpt-4o-mini \
  --provider-b anthropic \
  --model-b claude-3-5-sonnet-20241022 \
  --starter "Discuss the nature of consciousness" \
  --max-rounds 10 \
  --enable-mcp

# Test provider connectivity
chat-bridge test openai
chat-bridge test --all

# Manage personas
chat-bridge personas list
chat-bridge personas create "Cyberpunk Hacker"
chat-bridge personas show "Steel Worker"

# Show version
chat-bridge version
```

## Performance Targets

| Metric | Python | Go Target | Improvement |
|--------|--------|-----------|-------------|
| **Startup Time** | 500ms | <50ms | 10x faster |
| **Memory Usage** | 80MB | 15MB | 5x less |
| **Binary Size** | N/A (interpreter) | <20MB | Single file |
| **Stream Latency** | 50ms | 10ms | 5x faster |
| **Compilation** | Runtime | Compile-time | Type safety |

## Testing Strategy

### Unit Tests
- Provider interfaces (mocked HTTP)
- UI formatting functions
- Validation logic
- Configuration parsing

### Integration Tests
- End-to-end conversation flow
- Database operations
- File I/O (transcripts, logs)
- MCP integration

### Manual Testing
- All provider combinations
- Interactive menus
- Persona system
- Error scenarios

### Test Coverage Goal
- **Minimum**: 70%
- **Target**: 85%
- **Focus**: Core business logic

## Distribution

### Release Artifacts
```
chat-bridge-v1.0.0/
├── chat-bridge-linux-amd64       # Linux binary
├── chat-bridge-darwin-amd64      # macOS Intel
├── chat-bridge-darwin-arm64      # macOS Apple Silicon
├── chat-bridge-windows-amd64.exe # Windows binary
├── README.md                     # Installation guide
├── LICENSE                       # MIT or similar
└── .env.example                  # Environment template
```

### Installation Methods

#### 1. Pre-built Binaries
```bash
# Download and install
curl -L https://github.com/user/chat-bridge/releases/latest/download/chat-bridge-linux-amd64 -o chat-bridge
chmod +x chat-bridge
sudo mv chat-bridge /usr/local/bin/
```

#### 2. Go Install
```bash
go install github.com/user/chat-bridge@latest
```

#### 3. Homebrew (macOS/Linux)
```bash
brew tap user/chat-bridge
brew install chat-bridge
```

#### 4. From Source
```bash
git clone https://github.com/user/chat-bridge
cd chat-bridge
make install
```

## Advantages Over Python Version

### 1. **Distribution**
- Single binary (no dependencies)
- Cross-platform compilation
- No Python version conflicts

### 2. **Performance**
- 10x faster startup
- 5x lower memory usage
- Instant response times

### 3. **Concurrency**
- Goroutines for streaming
- Parallel provider testing
- Non-blocking UI updates

### 4. **Type Safety**
- Compile-time error detection
- Better IDE support
- Refactoring confidence

### 5. **Production Ready**
- Built-in testing framework
- Excellent error handling
- Standard logging

## Conclusion

The Go port will deliver:
- ✅ Same beautiful retro aesthetic
- ✅ All features from Python version
- ✅ 10x faster performance
- ✅ Single binary distribution
- ✅ Better concurrency
- ✅ Type safety
- ✅ Production-grade code

**Estimated Timeline**: 6-8 weeks (1 developer)
**Complexity**: Medium (Go experience helpful but not required)
**Risk**: Low (proven architecture from Python version)

**Status**: Ready to implement 🚀
