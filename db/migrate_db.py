#!/usr/bin/env python3
"""
Database migration script for Chat Bridge
Run this to initialize or upgrade your database schema
"""

import os
import sys
from datetime import datetime
from pathlib import Path

# Add the project root to Python path
project_root = Path(__file__).parent
sys.path.insert(0, str(project_root))

from database import init_db, engine, SessionLocal, Base
from sqlalchemy import inspect, text

def check_database_exists():
    """Check if database file exists"""
    db_path = "chat_bridge.db"
    return os.path.exists(db_path)

def backup_database():
    """Create a backup of existing database"""
    if check_database_exists():
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        backup_name = f"chat_bridge_backup_{timestamp}.db"
        
        try:
            import shutil
            shutil.copy2("chat_bridge.db", backup_name)
            print(f"✅ Database backed up to: {backup_name}")
            return backup_name
        except Exception as e:
            print(f"⚠️  Warning: Could not create backup: {e}")
            return None

def get_existing_tables():
    """Get list of existing tables"""
    inspector = inspect(engine)
    return inspector.get_table_names()

def migrate_database():
    """Run database migration"""
    print("🚀 Chat Bridge Database Migration")
    print("=" * 40)
    
    # Check if this is a fresh install
    existing_tables = get_existing_tables()
    is_fresh_install = len(existing_tables) == 0
    
    if not is_fresh_install:
        print(f"📊 Found existing tables: {', '.join(existing_tables)}")
        
        # Create backup
        backup_file = backup_database()
        
        # Ask for confirmation
        response = input("\n🤔 This will modify your existing database. Continue? (y/N): ")
        if response.lower() != 'y':
            print("❌ Migration cancelled")
            return False
    else:
        print("🆕 Fresh installation detected")
    
    try:
        # Create all tables
        print("\n📝 Creating/updating database schema...")
        init_db()
        
        # Verify tables were created
        new_tables = get_existing_tables()
        expected_tables = ['conversations', 'chat_messages', 'conversation_memory']
        
        print(f"✅ Tables created: {', '.join(new_tables)}")
        
        # Check if all expected tables exist
        missing_tables = set(expected_tables) - set(new_tables)
        if missing_tables:
            print(f"⚠️  Warning: Missing tables: {', '.join(missing_tables)}")
        
        print("\n🎉 Database migration completed successfully!")
        
        # Show some basic info
        with SessionLocal() as db:
            # Count existing conversations
            try:
                result = db.execute(text("SELECT COUNT(*) FROM conversations")).scalar()
                print(f"📈 Existing conversations: {result}")
                
                result = db.execute(text("SELECT COUNT(*) FROM chat_messages")).scalar()
                print(f"💬 Existing messages: {result}")
            except Exception as e:
                print(f"📊 Could not get stats: {e}")
        
        return True
        
    except Exception as e:
        print(f"❌ Migration failed: {e}")
        print("\n🔧 Troubleshooting tips:")
        print("1. Make sure no other Chat Bridge instances are running")
        print("2. Check file permissions in the current directory") 
        print("3. Ensure you have write access to create the database file")
        
        if backup_file:
            print(f"4. Your backup is saved as: {backup_file}")
        
        return False

def show_database_info():
    """Show database information"""
    print("\n📊 Database Information")
    print("=" * 30)
    
    if not check_database_exists():
        print("❌ Database does not exist. Run migration first.")
        return
    
    try:
        tables = get_existing_tables()
        print(f"📋 Tables: {', '.join(tables)}")
        
        with SessionLocal() as db:
            for table in tables:
                try:
                    count = db.execute(text(f"SELECT COUNT(*) FROM {table}")).scalar()
                    print(f"   {table}: {count} records")
                except Exception as e:
                    print(f"   {table}: Error reading ({e})")
                    
    except Exception as e:
        print(f"❌ Error reading database: {e}")

def main():
    """Main function"""
    if len(sys.argv) > 1:
        command = sys.argv[1].lower()
        
        if command == "info":
            show_database_info()
        elif command == "migrate":
            migrate_database()
        elif command == "backup":
            backup_database()
        else:
            print("Usage: python migrate_db.py [migrate|info|backup]")
    else:
        # Default action - migrate
        migrate_database()

if __name__ == "__main__":
    main()
