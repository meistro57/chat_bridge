# 🚀 Migration Guide - Old Scripts to Unified Chat Bridge

This guide helps you transition from the old separate scripts to the new unified `chat_bridge.py`.

## 📁 What Changed

### Old Structure (Archived)
- `chat_bridge_pro.py` - Interactive mode with provider selection
- `chat_bridge_roles.py` - Role-based personas
- `chat_bridge_roles_gemini.py` - Gemini-specific variant

### New Structure
- `chat_bridge.py` - **Single unified script** with all features
- `launch.py` - **Quick launcher** with preset configurations
- `archive/` - **Old scripts preserved** for reference

## 🔄 Command Equivalents

### From chat_bridge_pro.py
```bash
# OLD
python chat_bridge_pro.py --max-rounds 40 --mem-rounds 12

# NEW
python chat_bridge.py --max-rounds 40 --mem-rounds 12
# OR use the interactive launcher
python launch.py
```

### From chat_bridge_roles.py
```bash
# OLD
python chat_bridge_roles.py --roles roles.json --max-rounds 40

# NEW
python chat_bridge.py --roles roles.json --max-rounds 40
# OR use persona mode in launcher
python launch.py  # Select option [5] Persona Mode
```

### From chat_bridge_roles_gemini.py
```bash
# OLD
python chat_bridge_roles_gemini.py --provider-b gemini

# NEW
python chat_bridge.py --provider-b gemini
# OR select Gemini in interactive mode
```

## ✨ New Features Available

### 🎨 Beautiful Interface
- Colorful menus and progress indicators
- Real-time streaming with visual feedback
- Clear agent identification with colors

### 🚀 Quick Launcher
```bash
python launch.py
```
Choose from preset configurations:
- Interactive Mode
- OpenAI vs Anthropic
- Gemini vs Claude
- Local Models
- Persona Mode
- Long Discussion
- Quick Test

### 🎭 Enhanced Personas
The persona system now supports a full library of reusable personalities:

```json
{
  "persona_library": {
    "philosopher": { ... },
    "scientist": { ... },
    "creative_writer": { ... }
  }
}
```

### 🔒 Better Security
- API keys properly protected with .gitignore
- .env.example template for easy setup
- No more accidental key commits

## 🛠️ Troubleshooting

### My old command doesn't work
**Solution**: Use the equivalent new command above, or run interactive mode:
```bash
python chat_bridge.py  # Interactive setup
```

### Missing my custom roles.json settings
**Solution**: Your `roles.json` file still works! The new script is backward compatible.
```bash
python chat_bridge.py --roles roles.json
```

### Want the old interface back
**Solution**: The old scripts are preserved in `archive/` if needed:
```bash
python archive/chat_bridge_pro.py  # Still works
```

## 📚 Additional Resources

- **Main Documentation**: [README.md](README.md)
- **Persona Guide**: [docs/roles.md](docs/roles.md)
- **Quick Start**: Run `python launch.py`

## 🎯 Recommended Workflow

1. **Try the launcher**: `python launch.py`
2. **Use interactive mode**: `python chat_bridge.py`
3. **Customize personas**: Edit `roles.json` and use `--roles` flag
4. **Command line usage**: Use specific flags when needed

The new unified system provides all the old functionality with a much better user experience!