#!/usr/bin/env python3
"""
Fix missing provider assignments in roles.json personas
This script adds the correct provider based on persona name patterns
"""

import json
from pathlib import Path

def fix_persona_providers():
    # Read the current roles.json
    roles_path = Path('roles.json')
    with open(roles_path, 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    # Provider mappings based on persona name patterns
    provider_map = {
        'openai': ['openai_chatgpt'],
        'anthropic': ['anthropic_claude'],
        'gemini': ['gemini_researcher'],
        'deepseek': ['deepseek_strategist'],
        'ollama': ['ollama_local_expert', 'Ollama_tester'],
        'lmstudio': ['lmstudio_analyst']
    }
    
    # Default provider for personas without specific mappings
    default_provider = 'openai'
    
    # Track changes
    changes_made = 0
    
    # Fix persona_library
    if 'persona_library' in data:
        for persona_key, persona_data in data['persona_library'].items():
            # Determine the correct provider
            provider = None
            
            # Check provider mappings
            for provider_key, persona_patterns in provider_map.items():
                if persona_key in persona_patterns:
                    provider = provider_key
                    break
            
            # If no specific mapping found, use default
            if provider is None:
                provider = default_provider
            
            # Update the persona data
            persona_data['provider'] = provider
            changes_made += 1
            
            print(f"✓ {persona_key}: provider='{provider}'")
    
    # Write the fixed file
    backup_path = roles_path.with_suffix('.json.backup.before_provider_fix')
    
    # Create backup first
    with open(backup_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)
    
    # Write fixed file
    with open(roles_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)
    
    print(f"\n✅ Fixed {changes_made} personas")
    print(f"📁 Backup saved to: {backup_path}")
    print(f"📁 Updated file: {roles_path}")

if __name__ == "__main__":
    fix_persona_providers()