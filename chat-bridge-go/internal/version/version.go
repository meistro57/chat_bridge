package version

// Version information - can be overridden at build time
var (
	Version   = "1.0.0"
	GitCommit = "dev"
	BuildDate = "unknown"
)

// GetVersion returns the full version string
func GetVersion() string {
	return Version
}

// GetFullVersion returns version with git commit and build date
func GetFullVersion() string {
	return Version + " (" + GitCommit + ", built " + BuildDate + ")"
}
