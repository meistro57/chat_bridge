package ui

import (
	"fmt"
	"strings"

	"github.com/charmbracelet/lipgloss"
)

// Retro color scheme - matching the Python version's beautiful aesthetic
var (
	// ANSI color codes
	Cyan    = lipgloss.Color("14")  // Bright cyan
	Green   = lipgloss.Color("10")  // Bright green
	Yellow  = lipgloss.Color("11")  // Bright yellow
	Red     = lipgloss.Color("9")   // Bright red
	Magenta = lipgloss.Color("13")  // Bright magenta
	Blue    = lipgloss.Color("12")  // Bright blue
	White   = lipgloss.Color("15")  // Bright white
	Dim     = lipgloss.Color("240") // Dim gray
)

// Retro styles for different UI elements
var (
	// Banner style - bold cyan for the welcome banner
	Banner = lipgloss.NewStyle().
		Foreground(Cyan).
		Bold(true).
		Align(lipgloss.Center)

	// Section header style - yellow and bold with margins
	SectionHeader = lipgloss.NewStyle().
		Foreground(Yellow).
		Bold(true).
		MarginTop(1).
		MarginBottom(1)

	// Menu option style - cyan for menu items
	MenuOption = lipgloss.NewStyle().
		Foreground(Cyan).
		Bold(true).
		MarginLeft(2)

	// Menu description style - dimmed text
	MenuDescription = lipgloss.NewStyle().
		Foreground(Dim).
		MarginLeft(8)

	// Agent A style - green and bold
	AgentA = lipgloss.NewStyle().
		Foreground(Green).
		Bold(true)

	// Agent B style - magenta and bold
	AgentB = lipgloss.NewStyle().
		Foreground(Magenta).
		Bold(true)

	// Success message style
	Success = lipgloss.NewStyle().
		Foreground(Green)

	// Error message style
	Error = lipgloss.NewStyle().
		Foreground(Red).
		Bold(true)

	// Warning message style
	Warning = lipgloss.NewStyle().
		Foreground(Yellow)

	// Info message style
	Info = lipgloss.NewStyle().
		Foreground(Blue)

	// Provider badge style
	ProviderBadge = lipgloss.NewStyle().
		Foreground(Green).
		Bold(true)

	// Model badge style
	ModelBadge = lipgloss.NewStyle().
		Foreground(Yellow)
)

// PrintBanner displays the beautiful retro welcome banner
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

// PrintSectionHeader prints a styled section header with an icon
func PrintSectionHeader(title, icon string) {
	line := strings.Repeat("─", 60)
	fmt.Println()
	fmt.Println(lipgloss.NewStyle().Foreground(Dim).Render(line))
	fmt.Println(
		lipgloss.NewStyle().Foreground(Yellow).Render(icon) + " " +
			SectionHeader.Render(strings.ToUpper(title)),
	)
	fmt.Println(lipgloss.NewStyle().Foreground(Dim).Render(line))
}

// PrintMenuOption prints a styled menu option with number, title, and description
func PrintMenuOption(number, title, description string) {
	numStyled := lipgloss.NewStyle().
		Foreground(Cyan).
		Bold(true).
		Render(fmt.Sprintf("[%s]", number))

	titleStyled := lipgloss.NewStyle().
		Foreground(White).
		Bold(true).
		Render(title)

	descStyled := lipgloss.NewStyle().
		Foreground(Dim).
		Render(description)

	fmt.Printf("  %s %s\n", numStyled, titleStyled)
	fmt.Printf("      %s\n", descStyled)
}

// PrintProviderOption prints a provider option with model info
func PrintProviderOption(number, provider, model, description string) {
	numStyled := lipgloss.NewStyle().
		Foreground(Cyan).
		Bold(true).
		Render(fmt.Sprintf("[%s]", number))

	providerStyled := ProviderBadge.Render(provider)
	modelStyled := ModelBadge.Render(model)
	descStyled := MenuDescription.Render(description)

	fmt.Printf("  %s %s - %s\n", numStyled, providerStyled, modelStyled)
	fmt.Printf("      %s\n", descStyled)
}

// PrintSuccess prints a success message with checkmark
func PrintSuccess(message string) {
	fmt.Printf("%s %s\n",
		Success.Render("✅"),
		Success.Render(message),
	)
}

// PrintError prints an error message with X mark
func PrintError(message string) {
	fmt.Printf("%s %s\n",
		Error.Render("❌"),
		Error.Render(message),
	)
}

// PrintWarning prints a warning message with warning icon
func PrintWarning(message string) {
	fmt.Printf("%s %s\n",
		Warning.Render("⚠️"),
		Warning.Render(message),
	)
}

// PrintInfo prints an info message with info icon
func PrintInfo(message string) {
	fmt.Printf("%s %s\n",
		Info.Render("ℹ️"),
		Info.Render(message),
	)
}

// Colorize applies a color to text (convenience function)
func Colorize(text string, color lipgloss.Color, bold bool) string {
	style := lipgloss.NewStyle().Foreground(color)
	if bold {
		style = style.Bold(true)
	}
	return style.Render(text)
}

// RainbowText applies rainbow colors to each character
func RainbowText(text string) string {
	colors := []lipgloss.Color{Red, Yellow, Green, Cyan, Blue, Magenta}
	var result strings.Builder

	colorIndex := 0
	for _, char := range text {
		if char == ' ' || char == '\n' || char == '\t' {
			result.WriteRune(char)
		} else {
			style := lipgloss.NewStyle().Foreground(colors[colorIndex%len(colors)])
			result.WriteString(style.Render(string(char)))
			colorIndex++
		}
	}

	return result.String()
}
