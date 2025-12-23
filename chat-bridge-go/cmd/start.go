package cmd

import (
	"context"
	"fmt"
	"strings"
	"time"

	"github.com/markjamesm/chat-bridge-go/pkg/config"
	"github.com/markjamesm/chat-bridge-go/pkg/providers"
	"github.com/markjamesm/chat-bridge-go/pkg/ui"
	"github.com/spf13/cobra"
)

var (
	providerA   string
	providerB   string
	modelA      string
	modelB      string
	tempA       float64
	tempB       float64
	starter     string
	maxRounds   int
)

// startCmd represents the start command
var startCmd = &cobra.Command{
	Use:   "start",
	Short: "Start a conversation between two AI assistants",
	Long: `Start a conversation between two AI assistants.

This command initiates a multi-turn conversation where two AI assistants
from different providers (or the same provider) converse with each other.

Examples:
  # Start with defaults (OpenAI vs Anthropic)
  chat-bridge start

  # Specify providers and models
  chat-bridge start --provider-a openai --provider-b anthropic

  # Custom conversation starter
  chat-bridge start --starter "Discuss the nature of consciousness"

  # Limit rounds
  chat-bridge start --max-rounds 5
`,
	RunE: runStart,
}

func init() {
	rootCmd.AddCommand(startCmd)

	startCmd.Flags().StringVar(&providerA, "provider-a", "openai", "Provider for Agent A")
	startCmd.Flags().StringVar(&providerB, "provider-b", "anthropic", "Provider for Agent B")
	startCmd.Flags().StringVar(&modelA, "model-a", "", "Model for Agent A (default: provider default)")
	startCmd.Flags().StringVar(&modelB, "model-b", "", "Model for Agent B (default: provider default)")
	startCmd.Flags().Float64Var(&tempA, "temp-a", 0.7, "Temperature for Agent A")
	startCmd.Flags().Float64Var(&tempB, "temp-b", 0.7, "Temperature for Agent B")
	startCmd.Flags().StringVar(&starter, "starter", "Hello! How are you today?", "Conversation starter")
	startCmd.Flags().IntVar(&maxRounds, "max-rounds", 10, "Maximum conversation rounds")
}

func runStart(cmd *cobra.Command, args []string) error {
	// Show banner
	ui.PrintBanner()

	// Load configuration
	cfg, err := config.Load()
	if err != nil {
		return fmt.Errorf("failed to load configuration: %w", err)
	}

	// Validate configuration
	if err := cfg.Validate(); err != nil {
		ui.PrintError("Configuration error:")
		ui.PrintWarning(err.Error())
		ui.PrintInfo("Please set API keys in .env file or environment variables")
		return err
	}

	// Show session configuration
	ui.PrintSectionHeader("Session Configuration", "⚙️")
	fmt.Printf("  %s: %s\n", ui.Colorize("Agent A", ui.Green, true), providerA)
	if modelA != "" {
		fmt.Printf("  %s: %s\n", ui.Colorize("Model A", ui.Yellow, false), modelA)
	}
	fmt.Printf("  %s: %.1f\n", ui.Colorize("Temperature A", ui.Cyan, false), tempA)
	fmt.Println()
	fmt.Printf("  %s: %s\n", ui.Colorize("Agent B", ui.Magenta, true), providerB)
	if modelB != "" {
		fmt.Printf("  %s: %s\n", ui.Colorize("Model B", ui.Yellow, false), modelB)
	}
	fmt.Printf("  %s: %.1f\n", ui.Colorize("Temperature B", ui.Cyan, false), tempB)
	fmt.Println()
	fmt.Printf("  %s: %d\n", ui.Colorize("Max Rounds", ui.Blue, false), maxRounds)
	fmt.Printf("  %s: %s\n", ui.Colorize("Starter", ui.White, false), starter)
	fmt.Println()

	// Create providers
	ui.PrintInfo("Initializing providers...")

	// Get API keys
	apiKeyA := cfg.GetAPIKey(providerA)
	apiKeyB := cfg.GetAPIKey(providerB)

	// Get models
	if modelA == "" {
		modelA = cfg.GetDefaultModel(providerA)
	}
	if modelB == "" {
		modelB = cfg.GetDefaultModel(providerB)
	}

	// Create Provider A
	var agentA providers.Provider
	switch providerA {
	case "openai":
		agentA = providers.NewOpenAIProvider(providers.ProviderConfig{
			APIKey:      apiKeyA,
			Model:       modelA,
			Temperature: tempA,
		})
	default:
		return fmt.Errorf("provider '%s' not yet implemented", providerA)
	}

	// Create Provider B
	var agentB providers.Provider
	switch providerB {
	case "openai":
		agentB = providers.NewOpenAIProvider(providers.ProviderConfig{
			APIKey:      apiKeyB,
			Model:       modelB,
			Temperature: tempB,
		})
	default:
		return fmt.Errorf("provider '%s' not yet implemented", providerB)
	}

	// Health check
	ui.PrintInfo("Checking provider connectivity...")
	ctx := context.Background()

	if err := agentA.Health(ctx); err != nil {
		return fmt.Errorf("Agent A health check failed: %w", err)
	}
	ui.PrintSuccess(fmt.Sprintf("Agent A (%s) ready", providerA))

	if err := agentB.Health(ctx); err != nil {
		return fmt.Errorf("Agent B health check failed: %w", err)
	}
	ui.PrintSuccess(fmt.Sprintf("Agent B (%s) ready", providerB))

	fmt.Println()

	// Start conversation
	ui.PrintSectionHeader("Conversation", "💬")

	// Initialize conversation history
	messages := []providers.Message{}

	currentText := starter
	currentAgent := agentA
	agentName := "Agent A"
	agentColor := ui.Green

	for round := 1; round <= maxRounds; round++ {
		// Add user message to history
		messages = append(messages, providers.Message{
			Role:    "user",
			Content: currentText,
		})

		// Show round number
		fmt.Printf("\n%s\n", ui.Colorize(fmt.Sprintf("═══ Round %d/%d ═══", round, maxRounds), ui.Dim, false))
		fmt.Println()

		// Show typing indicator
		fmt.Printf("%s %s\n",
			ui.Colorize(agentName, agentColor, true),
			ui.Colorize("is thinking...", ui.Dim, false),
		)

		// Stream response
		textChan, errChan := currentAgent.StreamChat(ctx, &providers.ChatRequest{
			Model:       currentAgent.DefaultModel(),
			Messages:    messages,
			Temperature: tempA,
			MaxTokens:   800,
		})

		var fullResponse strings.Builder
		fmt.Print(ui.Colorize(agentName+": ", agentColor, true))

		for {
			select {
			case text, ok := <-textChan:
				if !ok {
					goto StreamDone
				}
				fmt.Print(text)
				fullResponse.WriteString(text)

			case err := <-errChan:
				if err != nil {
					ui.PrintError(fmt.Sprintf("Stream error: %v", err))
					return err
				}

			case <-time.After(30 * time.Second):
				return fmt.Errorf("stream timeout")
			}
		}

	StreamDone:
		fmt.Println("\n")

		// Add assistant response to history
		responseText := fullResponse.String()
		messages = append(messages, providers.Message{
			Role:    "assistant",
			Content: responseText,
		})

		// Prepare for next round
		currentText = responseText

		// Switch agents
		if currentAgent == agentA {
			currentAgent = agentB
			agentName = "Agent B"
			agentColor = ui.Magenta
		} else {
			currentAgent = agentA
			agentName = "Agent A"
			agentColor = ui.Green
		}

		// Small delay between rounds
		time.Sleep(500 * time.Millisecond)
	}

	// Show completion message
	ui.PrintSuccess(fmt.Sprintf("Conversation completed! %d rounds", maxRounds))

	return nil
}
