#!/usr/bin/env python3
"""
Simple test for web backend functionality
"""
import json
from pathlib import Path
import sys

# Add parent directory to path for imports
sys.path.insert(0, str(Path(__file__).parent.parent.parent.resolve()))

try:
    # Import web backend
    sys.path.insert(0, str(Path(__file__).parent.parent.resolve()))
    from web_gui.backend.main import app

    # Simulate a request to /api/personas
    import os
    from fastapi.testclient import TestClient

    client = TestClient(app)

    # Test the personas endpoint
    response = client.get("/api/personas")
    print(f"Personas endpoint status: {response.status_code}")

    if response.status_code == 200:
        data = response.json()
        print(f"Loaded {len(data.get('personas', []))} personas")
        print("✅ Backend API working correctly")
    else:
        print(f"❌ API test failed: {response.text}")

except ImportError as e:
    print(f"❌ Import error: {e}")
except Exception as e:
    print(f"❌ General error: {e}")</content>
<parameter name="file_path">test_backend_quick.py