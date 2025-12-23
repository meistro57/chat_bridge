package providers

import (
	"bufio"
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
)

func init() {
	// Register OpenAI provider in the global registry
	RegisterProvider(ProviderSpec{
		Key:          "openai",
		Name:         "OpenAI",
		Description:  "GPT models from OpenAI",
		DefaultModel: "gpt-4o-mini",
		NeedsAPIKey:  true,
		Models: []string{
			"gpt-4o",
			"gpt-4o-mini",
			"gpt-4-turbo",
			"gpt-4",
			"gpt-3.5-turbo",
		},
	})
}

// OpenAIProvider implements the Provider interface for OpenAI
type OpenAIProvider struct {
	apiKey  string
	baseURL string
	model   string
	client  *http.Client
}

// NewOpenAIProvider creates a new OpenAI provider instance
func NewOpenAIProvider(config ProviderConfig) *OpenAIProvider {
	baseURL := config.BaseURL
	if baseURL == "" {
		baseURL = "https://api.openai.com/v1"
	}

	model := config.Model
	if model == "" {
		model = "gpt-4o-mini"
	}

	return &OpenAIProvider{
		apiKey:  config.APIKey,
		baseURL: baseURL,
		model:   model,
		client:  &http.Client{},
	}
}

// Name returns the provider identifier
func (p *OpenAIProvider) Name() string {
	return "openai"
}

// DefaultModel returns the default model
func (p *OpenAIProvider) DefaultModel() string {
	return p.model
}

// Models returns available models
func (p *OpenAIProvider) Models(ctx context.Context) ([]string, error) {
	spec, _ := GetProviderSpec("openai")
	return spec.Models, nil
}

// Health checks if the provider is accessible
func (p *OpenAIProvider) Health(ctx context.Context) error {
	req, err := http.NewRequestWithContext(ctx, "GET", p.baseURL+"/models", nil)
	if err != nil {
		return err
	}

	req.Header.Set("Authorization", "Bearer "+p.apiKey)

	resp, err := p.client.Do(req)
	if err != nil {
		return fmt.Errorf("connection failed: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode == 401 {
		return ErrInvalidCredentials
	}

	if resp.StatusCode != 200 {
		body, _ := io.ReadAll(resp.Body)
		return fmt.Errorf("API error (status %d): %s", resp.StatusCode, string(body))
	}

	return nil
}

// StreamChat initiates a streaming chat completion
func (p *OpenAIProvider) StreamChat(ctx context.Context, req *ChatRequest) (<-chan string, <-chan error) {
	textChan := make(chan string)
	errChan := make(chan error, 1)

	go func() {
		defer close(textChan)
		defer close(errChan)

		// Build request body
		requestBody := map[string]interface{}{
			"model":       req.Model,
			"messages":    p.convertMessages(req.Messages),
			"temperature": req.Temperature,
			"stream":      true,
		}

		if req.MaxTokens > 0 {
			requestBody["max_tokens"] = req.MaxTokens
		}

		jsonData, err := json.Marshal(requestBody)
		if err != nil {
			errChan <- err
			return
		}

		// Create HTTP request
		httpReq, err := http.NewRequestWithContext(
			ctx,
			"POST",
			p.baseURL+"/chat/completions",
			bytes.NewBuffer(jsonData),
		)
		if err != nil {
			errChan <- err
			return
		}

		httpReq.Header.Set("Content-Type", "application/json")
		httpReq.Header.Set("Authorization", "Bearer "+p.apiKey)

		// Make request
		resp, err := p.client.Do(httpReq)
		if err != nil {
			errChan <- err
			return
		}
		defer resp.Body.Close()

		// Check status
		if resp.StatusCode != 200 {
			body, _ := io.ReadAll(resp.Body)
			errChan <- fmt.Errorf("API error (status %d): %s", resp.StatusCode, string(body))
			return
		}

		// Stream response
		reader := bufio.NewReader(resp.Body)
		for {
			select {
			case <-ctx.Done():
				errChan <- ErrContextCancelled
				return
			default:
			}

			line, err := reader.ReadString('\n')
			if err != nil {
				if err == io.EOF {
					return
				}
				errChan <- err
				return
			}

			line = strings.TrimSpace(line)
			if line == "" || line == "data: [DONE]" {
				continue
			}

			if !strings.HasPrefix(line, "data: ") {
				continue
			}

			// Parse SSE data
			jsonData := strings.TrimPrefix(line, "data: ")
			var chunk struct {
				Choices []struct {
					Delta struct {
						Content string `json:"content"`
					} `json:"delta"`
				} `json:"choices"`
			}

			if err := json.Unmarshal([]byte(jsonData), &chunk); err != nil {
				continue // Skip malformed chunks
			}

			if len(chunk.Choices) > 0 && chunk.Choices[0].Delta.Content != "" {
				select {
				case textChan <- chunk.Choices[0].Delta.Content:
				case <-ctx.Done():
					errChan <- ErrContextCancelled
					return
				}
			}
		}
	}()

	return textChan, errChan
}

// convertMessages converts internal message format to OpenAI format
func (p *OpenAIProvider) convertMessages(messages []Message) []map[string]string {
	result := make([]map[string]string, len(messages))
	for i, msg := range messages {
		result[i] = map[string]string{
			"role":    msg.Role,
			"content": msg.Content,
		}
	}
	return result
}
