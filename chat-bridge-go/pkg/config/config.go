package config

import (
	"fmt"
	"os"

	"github.com/joho/godotenv"
)

// Config holds the application configuration
type Config struct {
	// API Keys
	OpenAIKey     string
	AnthropicKey  string
	GeminiKey     string
	DeepSeekKey   string
	OpenRouterKey string

	// Base URLs (optional overrides)
	OpenAIBaseURL     string
	OllamaHost        string
	LMStudioBaseURL   string
	DeepSeekBaseURL   string
	OpenRouterBaseURL string

	// Default models
	OpenAIModel     string
	AnthropicModel  string
	GeminiModel     string
	OllamaModel     string
	LMStudioModel   string
	DeepSeekModel   string
	OpenRouterModel string

	// MCP Configuration
	MCPMode    string
	MCPBaseURL string

	// Default providers
	DefaultProviderA string
	DefaultProviderB string
}

// Load loads configuration from environment variables and .env file
func Load() (*Config, error) {
	// Try to load .env file (it's okay if it doesn't exist)
	_ = godotenv.Load()

	config := &Config{
		// API Keys
		OpenAIKey:     os.Getenv("OPENAI_API_KEY"),
		AnthropicKey:  os.Getenv("ANTHROPIC_API_KEY"),
		GeminiKey:     os.Getenv("GEMINI_API_KEY"),
		DeepSeekKey:   os.Getenv("DEEPSEEK_API_KEY"),
		OpenRouterKey: os.Getenv("OPENROUTER_API_KEY"),

		// Base URLs
		OpenAIBaseURL:     getEnvOrDefault("OPENAI_BASE_URL", "https://api.openai.com/v1"),
		OllamaHost:        getEnvOrDefault("OLLAMA_HOST", "http://localhost:11434"),
		LMStudioBaseURL:   getEnvOrDefault("LMSTUDIO_BASE_URL", "http://localhost:1234/v1"),
		DeepSeekBaseURL:   getEnvOrDefault("DEEPSEEK_BASE_URL", "https://api.deepseek.com/v1"),
		OpenRouterBaseURL: getEnvOrDefault("OPENROUTER_BASE_URL", "https://openrouter.ai/api/v1"),

		// Default models
		OpenAIModel:     getEnvOrDefault("OPENAI_MODEL", "gpt-4o-mini"),
		AnthropicModel:  getEnvOrDefault("ANTHROPIC_MODEL", "claude-3-5-sonnet-20241022"),
		GeminiModel:     getEnvOrDefault("GEMINI_MODEL", "gemini-2.0-flash-exp"),
		OllamaModel:     getEnvOrDefault("OLLAMA_MODEL", "llama3.1:8b-instruct"),
		LMStudioModel:   getEnvOrDefault("LMSTUDIO_MODEL", "local-model"),
		DeepSeekModel:   getEnvOrDefault("DEEPSEEK_MODEL", "deepseek-chat"),
		OpenRouterModel: getEnvOrDefault("OPENROUTER_MODEL", "openai/gpt-4o-mini"),

		// MCP Configuration
		MCPMode:    getEnvOrDefault("MCP_MODE", "http"),
		MCPBaseURL: getEnvOrDefault("MCP_BASE_URL", "http://localhost:8000"),

		// Default providers
		DefaultProviderA: getEnvOrDefault("BRIDGE_PROVIDER_A", "openai"),
		DefaultProviderB: getEnvOrDefault("BRIDGE_PROVIDER_B", "anthropic"),
	}

	return config, nil
}

// Validate checks if the configuration is valid
func (c *Config) Validate() error {
	// Check if at least one provider has credentials
	if c.OpenAIKey == "" && c.AnthropicKey == "" && c.GeminiKey == "" &&
		c.DeepSeekKey == "" && c.OpenRouterKey == "" {
		return fmt.Errorf("no API keys configured; set at least one of: OPENAI_API_KEY, ANTHROPIC_API_KEY, GEMINI_API_KEY, DEEPSEEK_API_KEY, or OPENROUTER_API_KEY")
	}

	return nil
}

// GetAPIKey returns the API key for a given provider
func (c *Config) GetAPIKey(provider string) string {
	switch provider {
	case "openai":
		return c.OpenAIKey
	case "anthropic":
		return c.AnthropicKey
	case "gemini":
		return c.GeminiKey
	case "deepseek":
		return c.DeepSeekKey
	case "openrouter":
		return c.OpenRouterKey
	default:
		return ""
	}
}

// GetDefaultModel returns the default model for a provider
func (c *Config) GetDefaultModel(provider string) string {
	switch provider {
	case "openai":
		return c.OpenAIModel
	case "anthropic":
		return c.AnthropicModel
	case "gemini":
		return c.GeminiModel
	case "ollama":
		return c.OllamaModel
	case "lmstudio":
		return c.LMStudioModel
	case "deepseek":
		return c.DeepSeekModel
	case "openrouter":
		return c.OpenRouterModel
	default:
		return ""
	}
}

// getEnvOrDefault returns environment variable value or default
func getEnvOrDefault(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}
