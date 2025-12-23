# Contributing to Chat Bridge Go

Thank you for your interest in contributing to Chat Bridge Go! This document provides guidelines and instructions for contributing.

## 🎯 Project Goals

- Preserve the beautiful retro aesthetic from the Python version
- Deliver 10x performance improvements with Go
- Maintain clean, idiomatic Go code
- Achieve feature parity with Python version
- Keep the single-binary distribution simple

## 🚀 Getting Started

### Prerequisites

- Go 1.23 or higher
- Git
- Make (optional but recommended)

### Setup

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/YOUR_USERNAME/chat-bridge-go
   cd chat-bridge-go
   ```

3. Install dependencies:
   ```bash
   make deps
   ```

4. Build and test:
   ```bash
   make build
   make test
   ```

## 📝 Development Workflow

### 1. Create a Branch

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/your-bug-fix
```

### 2. Make Your Changes

- Write clean, idiomatic Go code
- Follow existing patterns and style
- Add comments for exported functions
- Keep functions focused and small
- Use meaningful variable names

### 3. Test Your Changes

```bash
# Format code
make fmt

# Run tests
make test

# Build to ensure it compiles
make build

# Try it out
./bin/chat-bridge start --help
```

### 4. Commit Your Changes

Use clear, descriptive commit messages:

```bash
git add .
git commit -m "feat: add Anthropic provider support"
# or
git commit -m "fix: correct streaming timeout issue"
# or
git commit -m "docs: update README with new examples"
```

Commit message format:
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `style:` - Code style changes (formatting, etc.)
- `refactor:` - Code refactoring
- `test:` - Adding or updating tests
- `chore:` - Maintenance tasks

### 5. Push and Create Pull Request

```bash
git push origin feature/your-feature-name
```

Then create a Pull Request on GitHub with:
- Clear title describing the change
- Description of what changed and why
- Any relevant issue numbers (#123)
- Screenshots for UI changes

## 🎨 Code Style Guidelines

### Go Best Practices

1. **Error Handling**
   ```go
   if err != nil {
       return fmt.Errorf("failed to do X: %w", err)
   }
   ```

2. **Context Usage**
   ```go
   func (p *Provider) DoSomething(ctx context.Context) error {
       select {
       case <-ctx.Done():
           return ctx.Err()
       // ... your code
       }
   }
   ```

3. **Interface Design**
   ```go
   // Keep interfaces small and focused
   type Provider interface {
       Name() string
       StreamChat(ctx context.Context, req *ChatRequest) (<-chan string, <-chan error)
   }
   ```

4. **Naming Conventions**
   - Use camelCase for unexported names
   - Use PascalCase for exported names
   - Keep names short but descriptive
   - Avoid stuttering (e.g., `provider.ProviderName` → `provider.Name`)

### Retro UI Guidelines

When working on UI code:

1. **Use the existing color palette**
   ```go
   ui.Cyan    // Banners, headers
   ui.Green   // Agent A, success
   ui.Magenta // Agent B
   ui.Yellow  // Highlights
   ui.Red     // Errors
   ui.Blue    // Info
   ui.Dim     // Descriptions
   ```

2. **Use helper functions**
   ```go
   ui.PrintSuccess("Operation completed!")
   ui.PrintError("Something went wrong")
   ui.PrintSectionHeader("Configuration", "⚙️")
   ```

3. **Maintain the aesthetic**
   - Keep the retro feel
   - Use emojis consistently
   - Box-drawing characters for borders
   - Consistent spacing and alignment

## 🔌 Adding a New Provider

Example for adding "Anthropic" provider:

1. Create `pkg/providers/anthropic.go`:
   ```go
   package providers

   import "context"

   func init() {
       RegisterProvider(ProviderSpec{
           Key:          "anthropic",
           Name:         "Anthropic",
           Description:  "Claude models from Anthropic",
           DefaultModel: "claude-3-5-sonnet-20241022",
           NeedsAPIKey:  true,
           Models:       []string{"claude-3-5-sonnet-20241022", "claude-3-opus-20240229"},
       })
   }

   type AnthropicProvider struct {
       apiKey string
       model  string
       client *http.Client
   }

   func NewAnthropicProvider(config ProviderConfig) *AnthropicProvider {
       // Implementation
   }

   // Implement Provider interface methods
   func (p *AnthropicProvider) Name() string { return "anthropic" }
   func (p *AnthropicProvider) DefaultModel() string { return p.model }
   // ... etc
   ```

2. Add to `cmd/start.go` switch:
   ```go
   case "anthropic":
       agentA = providers.NewAnthropicProvider(providers.ProviderConfig{
           APIKey: apiKeyA,
           Model:  modelA,
       })
   ```

3. Update documentation

4. Test thoroughly

## 🧪 Testing Guidelines

### Unit Tests

- Test public functions and exported interfaces
- Use table-driven tests for multiple cases
- Mock external dependencies (HTTP calls, etc.)

Example:
```go
func TestProviderName(t *testing.T) {
    provider := NewOpenAIProvider(ProviderConfig{})
    if provider.Name() != "openai" {
        t.Errorf("expected 'openai', got '%s'", provider.Name())
    }
}
```

### Integration Tests

- Test end-to-end flows
- Use test API keys or mocks
- Clean up resources after tests

### Coverage Target

- Aim for 80%+ coverage
- Focus on critical paths
- Don't test trivial getters/setters

## 📚 Documentation

### Code Comments

- Comment all exported types, functions, and constants
- Explain "why" not "what"
- Keep comments up to date

### README Updates

- Update usage examples for new features
- Add new flags to command reference
- Update feature list

### CHANGELOG

- Add entry under `[Unreleased]`
- Follow Keep a Changelog format
- Move to versioned section on release

## 🐛 Bug Reports

When filing a bug report, include:

1. **Description** - What happened vs what you expected
2. **Steps to reproduce** - Exact commands and configuration
3. **Environment**:
   - Go version: `go version`
   - OS: Linux/macOS/Windows
   - Chat Bridge version: `./bin/chat-bridge --version`
4. **Logs** - Relevant error messages or logs
5. **Configuration** - .env file contents (remove API keys!)

## ✨ Feature Requests

When requesting a feature:

1. **Use case** - Why do you need this feature?
2. **Proposed solution** - How should it work?
3. **Alternatives** - What else have you considered?
4. **Examples** - Show usage examples

## 📋 Pull Request Checklist

Before submitting a PR, ensure:

- [ ] Code follows Go best practices
- [ ] All tests pass (`make test`)
- [ ] Code is formatted (`make fmt`)
- [ ] New features have tests
- [ ] Documentation is updated
- [ ] CHANGELOG has entry under `[Unreleased]`
- [ ] Commit messages are clear
- [ ] No merge conflicts with main branch
- [ ] Retro aesthetic is preserved (for UI changes)

## 🎯 Priority Areas

Current focus areas for contributions:

### High Priority
- Additional provider implementations (Anthropic, Gemini, Ollama)
- Interactive menu system
- Unit tests and test coverage

### Medium Priority
- Database logging
- Transcript generation
- Persona system
- Stop word detection

### Nice to Have
- CI/CD pipeline
- Docker support
- Homebrew formula
- Additional documentation

## 💬 Communication

- **Issues** - For bugs and feature requests
- **Discussions** - For questions and ideas
- **Pull Requests** - For code contributions

## 📄 License

By contributing, you agree that your contributions will be licensed under the MIT License.

## 🙏 Thank You!

Your contributions help make Chat Bridge Go better for everyone. Thank you for taking the time to contribute!

---

**Questions?** Open an issue or start a discussion. We're here to help! 🚀
