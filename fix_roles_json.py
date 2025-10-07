#!/usr/bin/env python3
"""
Utility script to fix roles.json by adding missing 'name' fields to personas.

The validation requires that each persona in the persona_library must have a 'name'
field that matches its key in the library.
"""

import json
import os
import sys
from datetime import datetime

def fix_roles_json(input_file="roles.json", output_file=None):
    """Fix roles.json by adding missing name fields to personas."""

    if output_file is None:
        # Create backup
        backup_file = f"{input_file}.backup.{datetime.now().strftime('%Y%m%d_%H%M%S')}"
        output_file = input_file

    print(f"Reading configuration from: {input_file}")

    # Read the configuration
    try:
        with open(input_file, 'r', encoding='utf-8') as f:
            config = json.load(f)
    except Exception as e:
        print(f"❌ Error reading file: {e}")
        return False

    # Create backup before modifying
    if output_file == input_file:
        backup_file = f"{input_file}.backup.{datetime.now().strftime('%Y%m%d_%H%M%S')}"
        print(f"Creating backup: {backup_file}")
        with open(backup_file, 'w', encoding='utf-8') as f:
            json.dump(config, f, indent=2, ensure_ascii=False)

    # Fix persona library
    persona_library = config.get('persona_library', {})
    fixed_count = 0

    print(f"\nChecking {len(persona_library)} personas...")

    for persona_key, persona_data in persona_library.items():
        if not isinstance(persona_data, dict):
            print(f"⚠️  Skipping {persona_key} - not a dictionary")
            continue

        # Check if 'name' field is missing or doesn't match the key
        if 'name' not in persona_data or persona_data['name'] != persona_key:
            old_name = persona_data.get('name', 'MISSING')
            persona_data['name'] = persona_key
            print(f"✅ Fixed: {persona_key} (was: {old_name})")
            fixed_count += 1

    if fixed_count > 0:
        print(f"\n✅ Fixed {fixed_count} personas")

        # Write the corrected configuration
        print(f"Writing corrected configuration to: {output_file}")
        try:
            with open(output_file, 'w', encoding='utf-8') as f:
                json.dump(config, f, indent=2, ensure_ascii=False)
            print(f"✅ Successfully wrote corrected configuration")
            return True
        except Exception as e:
            print(f"❌ Error writing file: {e}")
            return False
    else:
        print("\n✅ No fixes needed - all personas already have correct name fields")
        return True

def main():
    """Main entry point."""
    import argparse

    parser = argparse.ArgumentParser(
        description='Fix roles.json by adding missing name fields to personas'
    )
    parser.add_argument(
        '--input',
        default='roles.json',
        help='Input roles.json file (default: roles.json)'
    )
    parser.add_argument(
        '--output',
        default=None,
        help='Output file (default: overwrite input with backup)'
    )
    parser.add_argument(
        '--dry-run',
        action='store_true',
        help='Show what would be fixed without making changes'
    )

    args = parser.parse_args()

    if args.dry_run:
        print("🔍 DRY RUN MODE - No changes will be made\n")
        # Read and analyze only
        try:
            with open(args.input, 'r', encoding='utf-8') as f:
                config = json.load(f)

            persona_library = config.get('persona_library', {})
            issues = []

            for persona_key, persona_data in persona_library.items():
                if not isinstance(persona_data, dict):
                    continue
                if 'name' not in persona_data or persona_data['name'] != persona_key:
                    issues.append(persona_key)

            if issues:
                print(f"Found {len(issues)} personas that need fixing:")
                for persona in issues:
                    print(f"  - {persona}")
                print(f"\nRun without --dry-run to apply fixes")
            else:
                print("✅ No issues found")

            return True
        except Exception as e:
            print(f"❌ Error during dry run: {e}")
            return False
    else:
        return fix_roles_json(args.input, args.output)

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)
