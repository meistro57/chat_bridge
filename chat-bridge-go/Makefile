# Makefile for Chat Bridge Go

.PHONY: build test clean install run help

# Variables
BINARY_NAME=chat-bridge
VERSION=$(shell git describe --tags --always --dirty 2>/dev/null || echo "1.0.0")
LDFLAGS=-ldflags "-X github.com/markjamesm/chat-bridge-go/internal/version.Version=${VERSION}"
GOPATH=$(HOME)/gopath
GOBIN=$(HOME)/go/bin

# Default target
all: build

# Build for current platform
build:
	@echo "Building Chat Bridge..."
	@export PATH=$(GOBIN):$$PATH && export GOPATH=$(GOPATH) && go build $(LDFLAGS) -o bin/$(BINARY_NAME) .
	@echo "✅ Build complete: bin/$(BINARY_NAME)"

# Build for all platforms
build-all:
	@echo "Building for all platforms..."
	@export PATH=$(GOBIN):$$PATH && export GOPATH=$(GOPATH) && \
	GOOS=linux GOARCH=amd64 go build $(LDFLAGS) -o bin/$(BINARY_NAME)-linux-amd64 . && \
	GOOS=darwin GOARCH=amd64 go build $(LDFLAGS) -o bin/$(BINARY_NAME)-darwin-amd64 . && \
	GOOS=darwin GOARCH=arm64 go build $(LDFLAGS) -o bin/$(BINARY_NAME)-darwin-arm64 . && \
	GOOS=windows GOARCH=amd64 go build $(LDFLAGS) -o bin/$(BINARY_NAME)-windows-amd64.exe .
	@echo "✅ Cross-compilation complete"

# Run the application
run: build
	@./bin/$(BINARY_NAME)

# Run with OpenAI test
demo: build
	@echo "Starting demo conversation (requires OPENAI_API_KEY)..."
	@./bin/$(BINARY_NAME) start --provider-a openai --provider-b openai --max-rounds 3

# Run tests
test:
	@export PATH=$(GOBIN):$$PATH && export GOPATH=$(GOPATH) && go test -v ./...

# Run tests with coverage
test-coverage:
	@export PATH=$(GOBIN):$$PATH && export GOPATH=$(GOPATH) && \
	go test -v -cover -coverprofile=coverage.out ./... && \
	go tool cover -html=coverage.out

# Install locally
install: build
	@echo "Installing to $(GOBIN)..."
	@cp bin/$(BINARY_NAME) $(GOBIN)/
	@echo "✅ Installed: $(GOBIN)/$(BINARY_NAME)"

# Clean build artifacts
clean:
	@echo "Cleaning build artifacts..."
	@rm -rf bin/
	@rm -f coverage.out
	@echo "✅ Clean complete"

# Format code
fmt:
	@export PATH=$(GOBIN):$$PATH && export GOPATH=$(GOPATH) && go fmt ./...

# Get dependencies
deps:
	@echo "Installing dependencies..."
	@export PATH=$(GOBIN):$$PATH && export GOPATH=$(GOPATH) && \
	go get github.com/charmbracelet/lipgloss && \
	go get github.com/spf13/cobra && \
	go get github.com/joho/godotenv && \
	go mod tidy
	@echo "✅ Dependencies installed"

# Show help
help:
	@echo "Chat Bridge - Makefile Commands"
	@echo ""
	@echo "Usage:"
	@echo "  make build        Build for current platform"
	@echo "  make build-all    Build for all platforms"
	@echo "  make run          Build and run"
	@echo "  make demo         Run a quick demo (requires OpenAI API key)"
	@echo "  make test         Run tests"
	@echo "  make test-coverage Run tests with coverage report"
	@echo "  make install      Install to GOBIN"
	@echo "  make clean        Remove build artifacts"
	@echo "  make fmt          Format code"
	@echo "  make deps         Install dependencies"
	@echo "  make help         Show this help message"
