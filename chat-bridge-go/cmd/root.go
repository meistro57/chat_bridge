package cmd

import (
	"fmt"
	"os"

	"github.com/markjamesm/chat-bridge-go/internal/version"
	"github.com/markjamesm/chat-bridge-go/pkg/ui"
	"github.com/spf13/cobra"
)

var (
	showVersion bool
)

// rootCmd represents the base command
var rootCmd = &cobra.Command{
	Use:   "chat-bridge",
	Short: "🌉 Chat Bridge - Connect two AI assistants",
	Long: `
🌉 Chat Bridge - Connect Two AI Assistants

A beautiful, interactive chat bridge that enables conversations between
AI assistants from different providers (OpenAI, Anthropic, Gemini, etc.).

Features:
  • 🎭 Persona system for customizable AI personalities
  • ⚡ Real-time streaming responses
  • 🎨 Beautiful retro terminal UI
  • 💾 Conversation logging and transcripts
  • 🧠 Optional MCP memory integration
  • 🔄 Support for multiple AI providers
`,
	Run: func(cmd *cobra.Command, args []string) {
		if showVersion {
			fmt.Printf("Chat Bridge v%s\n", version.GetVersion())
			return
		}

		// Show banner and help if no subcommand
		ui.PrintBanner()
		cmd.Help()
	},
}

// Execute runs the root command
func Execute() {
	if err := rootCmd.Execute(); err != nil {
		ui.PrintError(fmt.Sprintf("Error: %v", err))
		os.Exit(1)
	}
}

func init() {
	rootCmd.Flags().BoolVarP(&showVersion, "version", "v", false, "Show version information")
}
