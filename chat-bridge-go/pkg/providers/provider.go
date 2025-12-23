package providers

import (
	"context"
	"errors"
)

// Common errors
var (
	ErrProviderNotFound    = errors.New("provider not found")
	ErrInvalidCredentials  = errors.New("invalid credentials")
	ErrRateLimitExceeded   = errors.New("rate limit exceeded")
	ErrContextCancelled    = errors.New("context cancelled")
	ErrStreamingFailed     = errors.New("streaming failed")
)

// Provider defines the interface that all AI providers must implement
type Provider interface {
	// Name returns the provider identifier (e.g., "openai", "anthropic")
	Name() string

	// Models returns the list of available models for this provider
	Models(ctx context.Context) ([]string, error)

	// StreamChat initiates a streaming chat completion
	// Returns channels for text chunks and errors
	StreamChat(ctx context.Context, req *ChatRequest) (<-chan string, <-chan error)

	// Health checks if the provider is accessible and credentials are valid
	Health(ctx context.Context) error

	// DefaultModel returns the default model for this provider
	DefaultModel() string
}

// ChatRequest encapsulates a chat completion request
type ChatRequest struct {
	Model       string    // Model ID to use
	Messages    []Message // Conversation history
	Temperature float64   // Sampling temperature (0.0 - 2.0)
	MaxTokens   int       // Maximum tokens to generate
	SystemPrompt string   // Optional system prompt override
}

// Message represents a single message in the conversation
type Message struct {
	Role    string // "system", "user", or "assistant"
	Content string // The message content
}

// StreamResponse encapsulates a chunk of streamed response
type StreamResponse struct {
	Text   string // The text content
	Done   bool   // Whether this is the final chunk
}

// ProviderConfig holds provider-specific configuration
type ProviderConfig struct {
	APIKey      string // API key for the provider
	BaseURL     string // Optional custom base URL
	Model       string // Default model to use
	Temperature float64 // Default temperature
}

// ProviderSpec describes a provider's metadata
type ProviderSpec struct {
	Key          string   // Provider key (e.g., "openai")
	Name         string   // Display name (e.g., "OpenAI")
	Description  string   // Human-readable description
	DefaultModel string   // Default model
	NeedsAPIKey  bool     // Whether an API key is required
	Models       []string // List of supported models
}

// Registry holds all registered providers
var providerRegistry = make(map[string]ProviderSpec)

// RegisterProvider registers a provider spec in the global registry
func RegisterProvider(spec ProviderSpec) {
	providerRegistry[spec.Key] = spec
}

// GetProviderSpec returns the spec for a given provider key
func GetProviderSpec(key string) (ProviderSpec, bool) {
	spec, ok := providerRegistry[key]
	return spec, ok
}

// ListProviders returns all registered provider specs
func ListProviders() []ProviderSpec {
	specs := make([]ProviderSpec, 0, len(providerRegistry))
	for _, spec := range providerRegistry {
		specs = append(specs, spec)
	}
	return specs
}
