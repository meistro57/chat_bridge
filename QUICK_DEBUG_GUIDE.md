# Chat Bridge Quick Debug Guide

## Quick Commands

### Check if roles.json is valid
```bash
python -c "from roles_manager import RolesManager; m = RolesManager('roles.json'); valid, err = m.validate_json_file(); print('✅ Valid' if valid else f'❌ {err}')"
```

### Run full diagnostic test suite
```bash
python test_roles_debug.py
```

### Check for JSON issues (dry run)
```bash
python fix_roles_json.py --dry-run
```

### Fix JSON issues automatically
```bash
python fix_roles_json.py
```

### Run Chat Bridge with debug mode
```bash
python chat_bridge.py --debug --roles
```

### Validate JSON syntax only
```bash
python -m json.tool roles.json > /dev/null && echo "✅ Valid JSON" || echo "❌ Invalid JSON"
```

---

## Common Issues and Solutions

### Issue: "Persona 'X' is missing required field 'name'"

**Solution:**
```bash
python fix_roles_json.py
```

This automatically adds missing `name` fields to all personas.

---

### Issue: "Configuration file not found"

**Check:**
```bash
ls -la roles.json
pwd
```

**Solution:**
Run from the correct directory or specify full path:
```bash
python chat_bridge.py --roles-path /full/path/to/roles.json
```

---

### Issue: "Invalid JSON in roles.json"

**Diagnose:**
```bash
python -m json.tool roles.json
```

Look for the error line/column, then:

**Debug with details:**
```python
from roles_manager import RolesManager
manager = RolesManager("roles.json", debug=True)
```

This will show you exactly where the JSON error is with context.

---

### Issue: "Failed to create agents"

**Check credentials:**
```bash
# Check if environment variables are set
echo $OPENAI_API_KEY
echo $ANTHROPIC_API_KEY
echo $GEMINI_API_KEY
```

**Run with debug:**
```bash
python chat_bridge.py --debug --roles
```

Debug mode will show exactly which credential check failed.

---

### Issue: Persona not applying correctly

**Test the persona:**
```python
from roles_manager import RolesManager

manager = RolesManager("roles.json", debug=True)

# Check if persona exists
personas = manager.list_all_personas()
print("Available:", personas)

# Get persona details
info = manager.get_persona_info("scientist")
print("Persona:", info)

# Test if valid for agent creation
valid = manager.test_agent_creation("scientist")
print("Valid for agents:", valid)
```

---

## Debug Mode Output Examples

### Normal mode (no debug):
```
Loading roles from roles.json...
Starting conversation...
```

### Debug mode:
```
🔍 DEBUG: Attempting to load config from: /home/user/roles.json
🔍 DEBUG: File size: 31928 bytes
✅ JSON parsed successfully
🔍 DEBUG: Top-level keys: ['agent_a', 'agent_b', 'persona_library', ...]
✅ Configuration schema validation passed
🔍 DEBUG: Starting agent creation process
✅ Credentials verified for openai
✅ Agent A created successfully
```

---

## Testing Specific Components

### Test just JSON loading
```python
from roles_manager import RolesManager
m = RolesManager("roles.json", debug=True)
print("Config loaded:", "✅" if m.config else "❌")
```

### Test specific persona
```python
from roles_manager import RolesManager
m = RolesManager("roles.json")
if m.test_agent_creation("philosopher"):
    print("✅ Philosopher persona is valid")
else:
    print("❌ Philosopher persona has issues")
```

### Test all personas
```python
from roles_manager import RolesManager
m = RolesManager("roles.json", debug=True)
personas = m.list_all_personas()
for p in personas:
    result = "✅" if m.test_agent_creation(p) else "❌"
    print(f"{result} {p}")
```

---

## Environment Setup Check

### Verify all dependencies
```bash
python -c "import anthropic, openai, google.generativeai, httpx; print('✅ All imports OK')"
```

### Check Python version
```bash
python --version  # Should be 3.8+
```

### Verify .env file
```bash
cat .env | grep -E "API_KEY|MODEL"
```

---

## Log File Locations

- Main log: `chat_bridge.log`
- Error log: `chat_bridge_errors.log`
- Test transcripts: `transcripts/`
- Conversation logs: `chatlogs/`

### View recent errors
```bash
tail -n 50 chat_bridge_errors.log
```

### View debug output
```bash
tail -f chat_bridge.log | grep DEBUG
```

---

## Best Practices

1. **Always run tests after modifying roles.json**
   ```bash
   python test_roles_debug.py
   ```

2. **Use dry-run before fixing**
   ```bash
   python fix_roles_json.py --dry-run
   ```

3. **Enable debug mode when troubleshooting**
   ```bash
   python chat_bridge.py --debug
   ```

4. **Check backups before major changes**
   ```bash
   ls -lt roles.json.backup.*
   ```

5. **Validate JSON syntax first**
   ```bash
   python -m json.tool roles.json > /dev/null
   ```

---

## Emergency Reset

If roles.json is completely broken:

```bash
# Backup current (broken) file
cp roles.json roles.json.broken

# Let RolesManager create defaults
rm roles.json
python -c "from roles_manager import RolesManager; RolesManager('roles.json')"

# This creates a fresh roles.json with default personas
```

---

## Getting Help

1. Run full diagnostic: `python test_roles_debug.py`
2. Enable debug mode: `python chat_bridge.py --debug`
3. Check logs: `tail -f chat_bridge_errors.log`
4. Validate JSON: `python fix_roles_json.py --dry-run`
5. Check this guide: `DEBUGGING_IMPROVEMENTS.md`

---

## Quick Validation Checklist

- [ ] JSON syntax is valid: `python -m json.tool roles.json`
- [ ] Schema validation passes: `python fix_roles_json.py --dry-run`
- [ ] All personas have 'name' field
- [ ] Environment variables set: `echo $OPENAI_API_KEY`
- [ ] Dependencies installed: `pip install -r requirements.txt`
- [ ] Tests pass: `python test_roles_debug.py`
- [ ] No errors in logs: `tail chat_bridge_errors.log`

---

## Contact & Support

For issues not covered in this guide:
1. Check `DEBUGGING_IMPROVEMENTS.md` for detailed documentation
2. Review `devplan.md` for system architecture
3. Check test output for specific error details
4. Enable debug mode for maximum visibility
