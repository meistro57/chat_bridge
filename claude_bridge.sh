#!/bin/bash
# ============================================================
# Claude Bridge - API Toolkit for Claude ↔ Chat Bridge
# ============================================================
# This script lets Claude interact with Chat Bridge's MCP API
# by writing results to a shared filesystem location.
#
# Usage:
#   ./claude_bridge.sh <command> [args...]
#
# Commands:
#   health                    - Check system health
#   stats                     - Get conversation/message counts
#   recent [limit]            - Get recent conversations (default: 10)
#   search <keyword>          - Search messages by keyword
#   conversation <id>         - Get full conversation with messages
#   memory <topic> [limit]    - Semantic/contextual memory search
#   models <provider>         - List available models for a provider
#   dump                      - Dump all endpoints to a single JSON file
#   watch                     - Continuous mode: watch for commands from Claude

BASE_URL="${CHAT_BRIDGE_URL:-http://localhost:8000}"
OUTPUT_DIR="/home/mark/chat_bridge/.claude_bridge"
COMMAND_FILE="$OUTPUT_DIR/command.txt"
RESULT_FILE="$OUTPUT_DIR/result.json"

mkdir -p "$OUTPUT_DIR"

# Colors
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log() { echo -e "${CYAN}[Claude Bridge]${NC} $1"; }
success() { echo -e "${GREEN}[✓]${NC} $1"; }
error() { echo -e "${RED}[✗]${NC} $1"; }

write_result() {
    local endpoint="$1"
    local data="$2"
    local timestamp=$(date -Iseconds)
    
    jq -n \
        --arg endpoint "$endpoint" \
        --arg timestamp "$timestamp" \
        --argjson data "$data" \
        '{endpoint: $endpoint, timestamp: $timestamp, data: $data}' \
        > "$RESULT_FILE" 2>/dev/null || \
    echo "{\"endpoint\":\"$endpoint\",\"timestamp\":\"$timestamp\",\"data\":$data}" > "$RESULT_FILE"
    
    success "Result written to $RESULT_FILE"
}

cmd_health() {
    log "Checking Chat Bridge health..."
    local result=$(curl -s --connect-timeout 5 "$BASE_URL/api/mcp/health")
    if [ $? -eq 0 ] && [ -n "$result" ]; then
        write_result "health" "$result"
        echo "$result" | jq . 2>/dev/null || echo "$result"
    else
        error "Chat Bridge is not reachable at $BASE_URL"
        write_result "health" '{"status":"unreachable","error":"Connection failed"}'
    fi
}

cmd_stats() {
    log "Fetching stats..."
    local result=$(curl -s --connect-timeout 5 "$BASE_URL/api/mcp/stats")
    write_result "stats" "$result"
    echo "$result" | jq . 2>/dev/null || echo "$result"
}

cmd_recent() {
    local limit="${1:-10}"
    log "Fetching $limit recent conversations..."
    local result=$(curl -s --connect-timeout 5 "$BASE_URL/api/mcp/recent-chats?limit=$limit")
    write_result "recent-chats" "$result"
    echo "$result" | jq . 2>/dev/null || echo "$result"
}

cmd_search() {
    local keyword="$1"
    if [ -z "$keyword" ]; then
        error "Usage: claude_bridge.sh search <keyword>"
        return 1
    fi
    log "Searching for: $keyword"
    local encoded=$(python3 -c "import urllib.parse; print(urllib.parse.quote('$keyword'))" 2>/dev/null || echo "$keyword")
    local result=$(curl -s --connect-timeout 10 "$BASE_URL/api/mcp/search-chats?keyword=$encoded")
    write_result "search" "$result"
    echo "$result" | jq . 2>/dev/null || echo "$result"
}

cmd_conversation() {
    local id="$1"
    if [ -z "$id" ]; then
        error "Usage: claude_bridge.sh conversation <id>"
        return 1
    fi
    log "Fetching conversation #$id..."
    local result=$(curl -s --connect-timeout 10 "$BASE_URL/api/mcp/conversation/$id")
    write_result "conversation" "$result"
    echo "$result" | jq . 2>/dev/null || echo "$result"
}

cmd_memory() {
    local topic="$1"
    local limit="${2:-5}"
    if [ -z "$topic" ]; then
        error "Usage: claude_bridge.sh memory <topic> [limit]"
        return 1
    fi
    log "Searching contextual memory for: $topic (limit: $limit)"
    local encoded=$(python3 -c "import urllib.parse; print(urllib.parse.quote('$topic'))" 2>/dev/null || echo "$topic")
    local result=$(curl -s --connect-timeout 15 "$BASE_URL/api/mcp/contextual-memory?topic=$encoded&limit=$limit")
    write_result "contextual-memory" "$result"
    echo "$result" | jq . 2>/dev/null || echo "$result"
}

cmd_models() {
    local provider="$1"
    if [ -z "$provider" ]; then
        error "Usage: claude_bridge.sh models <provider>"
        echo "  Providers: openai, anthropic, gemini, deepseek, openrouter, ollama, lmstudio"
        return 1
    fi
    log "Fetching models for: $provider"
    local result=$(curl -s --connect-timeout 10 "$BASE_URL/api/providers/models?provider=$provider")
    write_result "models" "$result"
    echo "$result" | jq . 2>/dev/null || echo "$result"
}

cmd_dump() {
    log "Dumping all endpoints..."
    local health=$(curl -s --connect-timeout 5 "$BASE_URL/api/mcp/health")
    local stats=$(curl -s --connect-timeout 5 "$BASE_URL/api/mcp/stats")
    local recent=$(curl -s --connect-timeout 5 "$BASE_URL/api/mcp/recent-chats?limit=5")
    
    local dump=$(jq -n \
        --argjson health "${health:-null}" \
        --argjson stats "${stats:-null}" \
        --argjson recent "${recent:-null}" \
        --arg timestamp "$(date -Iseconds)" \
        '{
            timestamp: $timestamp,
            health: $health,
            stats: $stats,
            recent_conversations: $recent
        }')
    
    echo "$dump" > "$OUTPUT_DIR/full_dump.json"
    success "Full dump written to $OUTPUT_DIR/full_dump.json"
    echo "$dump" | jq . 2>/dev/null || echo "$dump"
}

cmd_watch() {
    log "Starting watch mode... Claude can send commands via $COMMAND_FILE"
    log "Press Ctrl+C to stop"
    echo ""
    
    # Clear any old commands
    > "$COMMAND_FILE"
    
    while true; do
        if [ -s "$COMMAND_FILE" ]; then
            local cmd=$(cat "$COMMAND_FILE")
            > "$COMMAND_FILE"  # Clear it
            
            log "Received command: $cmd"
            
            # Parse and execute
            local action=$(echo "$cmd" | awk '{print $1}')
            local args=$(echo "$cmd" | cut -d' ' -f2-)
            
            case "$action" in
                health)       cmd_health ;;
                stats)        cmd_stats ;;
                recent)       cmd_recent $args ;;
                search)       cmd_search "$args" ;;
                conversation) cmd_conversation $args ;;
                memory)       cmd_memory $args ;;
                models)       cmd_models $args ;;
                dump)         cmd_dump ;;
                quit|exit)    log "Shutting down watch mode."; exit 0 ;;
                *)            error "Unknown command: $action" ;;
            esac
            
            echo ""
        fi
        sleep 1
    done
}

# ============================================================
# Main
# ============================================================
case "${1:-help}" in
    health)       cmd_health ;;
    stats)        cmd_stats ;;
    recent)       cmd_recent "$2" ;;
    search)       cmd_search "$2" ;;
    conversation) cmd_conversation "$2" ;;
    memory)       cmd_memory "$2" "$3" ;;
    models)       cmd_models "$2" ;;
    dump)         cmd_dump ;;
    watch)        cmd_watch ;;
    *)
        echo ""
        echo -e "${CYAN}╔══════════════════════════════════════════╗${NC}"
        echo -e "${CYAN}║      🤖 Claude Bridge Toolkit 🌉        ║${NC}"
        echo -e "${CYAN}╠══════════════════════════════════════════╣${NC}"
        echo -e "${CYAN}║${NC}  Connect Claude to Chat Bridge's API     ${CYAN}║${NC}"
        echo -e "${CYAN}╚══════════════════════════════════════════╝${NC}"
        echo ""
        echo "Usage: ./claude_bridge.sh <command> [args...]"
        echo ""
        echo "Commands:"
        echo "  health                  System health check"
        echo "  stats                   Conversation & message counts"
        echo "  recent [limit]          Recent conversations (default: 10)"
        echo "  search <keyword>        Search messages by keyword"
        echo "  conversation <id>       Full conversation with messages"
        echo "  memory <topic> [limit]  Semantic memory search"
        echo "  models <provider>       List models for a provider"
        echo "  dump                    Dump all endpoints to JSON"
        echo "  watch                   Watch mode: accept commands from Claude"
        echo ""
        echo "Environment:"
        echo "  CHAT_BRIDGE_URL         Base URL (default: http://localhost:8000)"
        echo ""
        echo "Output:"
        echo "  Results are written to: $OUTPUT_DIR/result.json"
        echo "  Full dumps written to:  $OUTPUT_DIR/full_dump.json"
        echo ""
        ;;
esac
