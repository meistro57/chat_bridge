#!/usr/bin/env python3
"""
Chat Bridge Setup Script
Handles database initialization, dependency checks, and initial configuration
"""

import os
import sys
import subprocess
from pathlib import Path

def check_python_version():
    """Check if Python version is compatible"""
    print("🐍 Checking Python version...")
    
    if sys.version_info < (3, 8):
        print(f"❌ Python 3.8+ required. Current version: {sys.version}")
        return False
    
    print(f"✅ Python {sys.version_info.major}.{sys.version_info.minor} is compatible")
    return True

def install_dependencies():
    """Install required dependencies"""
    print("\n📦 Installing dependencies...")
    
    requirements_file = Path("requirements.txt")
    if not requirements_file.exists():
        print("❌ requirements.txt not found!")
        return False
    
    try:
        # Install dependencies
        subprocess.check_call([
            sys.executable, "-m", "pip", "install", "-r", "requirements.txt"
        ])
        print("✅ Dependencies installed successfully")
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ Failed to install dependencies: {e}")
        return False

def check_environment_variables():
    """Check and setup environment variables"""
    print("\n🔐 Checking environment variables...")
    
    required_vars = {
        "OPENAI_API_KEY": "OpenAI API key for GPT models",
        "ANTHROPIC_API_KEY": "Anthropic API key for Claude models"
    }
    
    optional_vars = {
        "GOOGLE_API_KEY": "Google API key for Gemini models",
        "DATABASE_URL": "Database connection URL (defaults to SQLite in db/ folder)"
    }
    
    env_file = Path(".env")
    missing_vars = []
    
    for var, description in required_vars.items():
        if not os.getenv(var):
            missing_vars.append((var, description, True))
    
    for var, description in optional_vars.items():
        if not os.getenv(var):
            missing_vars.append((var, description, False))
    
    if missing_vars:
        print("\n📝 Environment variables needed:")
        
        # Create or update .env file
        env_content = []
        if env_file.exists():
            env_content = env_file.read_text().split('\n')
        
        for var, description, required in missing_vars:
            status = "REQUIRED" if required else "OPTIONAL"
            print(f"   {var} ({status}): {description}")
            
            # Add to .env if not present
            if not any(line.startswith(f"{var}=") for line in env_content):
                if var == "DATABASE_URL":
                    env_content.append(f"{var}=sqlite:///./db/chat_bridge.db")
                else:
                    env_content.append(f"{var}=your_{var.lower()}_here")
        
        # Write updated .env file
        with open(".env", "w") as f:
            f.write('\n'.join(filter(None, env_content)) + '\n')
        
        print(f"\n📄 Created/updated .env file. Please edit it with your API keys.")
        
        required_missing = [var for var, _, required in missing_vars if required]
        if required_missing:
            print(f"⚠️  Required variables missing: {', '.join(required_missing)}")
            return False
    
    print("✅ Environment variables configured")
    return True

def setup_database():
    """Initialize the database"""
    print("\n🗄️  Setting up database...")
    
    try:
        # Import and run database initialization
        from migrate_db import migrate_database
        
        if migrate_database():
            print("✅ Database setup completed")
            return True
        else:
            print("❌ Database setup failed")
            return False
            
    except ImportError as e:
        print(f"❌ Could not import database modules: {e}")
        return False
    except Exception as e:
        print(f"❌ Database setup error: {e}")
        return False

def test_database():
    """Run database tests"""
    print("\n🧪 Testing database functionality...")
    
    try:
        from test_database import test_database_operations, test_error_handling
        
        # Run basic tests
        if test_database_operations():
            test_error_handling()
            print("✅ Database tests passed")
            return True
        else:
            print("❌ Database tests failed")
            return False
            
    except Exception as e:
        print(f"❌ Database test error: {e}")
        return False

def check_frontend():
    """Check if frontend is built"""
    print("\n🌐 Checking frontend...")
    
    frontend_dist = Path("web_gui/frontend/dist")
    
    if frontend_dist.exists() and (frontend_dist / "index.html").exists():
        print("✅ Frontend build found")
        return True
    else:
        print("⚠️  Frontend not built. Run 'npm run build' in web_gui/frontend/")
        
        # Check if we can build it
        frontend_dir = Path("web_gui/frontend")
        if frontend_dir.exists() and (frontend_dir / "package.json").exists():
            print("📦 Frontend source found. Attempting to build...")
            
            try:
                os.chdir(frontend_dir)
                
                # Install dependencies
                subprocess.check_call(["npm", "install"])
                
                # Build frontend
                subprocess.check_call(["npm", "run", "build"])
                
                os.chdir("../..")
                print("✅ Frontend built successfully")
                return True
                
            except subprocess.CalledProcessError:
                os.chdir("../..")
                print("❌ Frontend build failed. Please build manually.")
                return False
        else:
            print("❌ Frontend source not found")
            return False

def create_startup_script():
    """Create a startup script for convenience"""
    print("\n🚀 Creating startup script...")
    
    startup_script = """#!/bin/bash
# Chat Bridge Startup Script

echo "🌉 Starting Chat Bridge..."

# Check if virtual environment exists
if [ -d "venv" ]; then
    echo "📦 Activating virtual environment..."
    source venv/bin/activate
fi

# Check environment variables
if [ ! -f ".env" ]; then
    echo "❌ .env file not found! Please run setup first."
    exit 1
fi

# Start the server
echo "🚀 Starting Chat Bridge server..."
python main.py

echo "👋 Chat Bridge stopped."
"""
    
    with open("start_chat_bridge.sh", "w") as f:
        f.write(startup_script)
    
    # Make executable on Unix systems
    try:
        os.chmod("start_chat_bridge.sh", 0o755)
        print("✅ Startup script created: start_chat_bridge.sh")
    except:
        print("✅ Startup script created (you may need to make it executable)")
    
    return True

def main():
    """Main setup function"""
    print("🌉 Chat Bridge Setup")
    print("=" * 40)
    print("Setting up your Chat Bridge installation...\n")
    
    steps = [
        ("Python Version", check_python_version),
        ("Dependencies", install_dependencies),
        ("Environment Variables", check_environment_variables),
        ("Database", setup_database),
        ("Database Tests", test_database),
        ("Frontend", check_frontend),
        ("Startup Script", create_startup_script),
    ]
    
    completed_steps = 0
    
    for step_name, step_function in steps:
        print(f"\n{'='*50}")
        print(f"Step {completed_steps + 1}/{len(steps)}: {step_name}")
        print('='*50)
        
        if step_function():
            completed_steps += 1
            print(f"✅ {step_name} completed")
        else:
            print(f"❌ {step_name} failed")
            
            # Ask if user wants to continue
            if step_name in ["Environment Variables", "Frontend"]:
                response = input(f"\n⚠️  {step_name} failed but setup can continue. Continue? (y/N): ")
                if response.lower() == 'y':
                    completed_steps += 1
                    continue
            
            print(f"\n❌ Setup failed at step: {step_name}")
            print("\n🔧 Troubleshooting tips:")
            print("1. Make sure you have Python 3.8+ installed")
            print("2. Ensure you have internet connection for downloading dependencies")
            print("3. Check that you have write permissions in this directory")
            print("4. For API keys, sign up at OpenAI and Anthropic websites")
            
            return False
    
    # Setup complete
    print(f"\n{'='*50}")
    print("🎉 Chat Bridge Setup Complete!")
    print('='*50)
    
    print(f"\n📊 Setup Summary:")
    print(f"   ✅ {completed_steps}/{len(steps)} steps completed")
    
    print(f"\n🚀 Next Steps:")
    print("1. Edit .env file with your API keys")
    print("2. Run: python main.py (or ./start_chat_bridge.sh)")
    print("3. Open your browser to: http://localhost:8000")
    
    print(f"\n📚 Useful Commands:")
    print("   python migrate_db.py info    - Show database info")
    print("   python test_database.py      - Run database tests")
    print("   python main.py               - Start the server")
    
    return True

if __name__ == "__main__":
    main()
