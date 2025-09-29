import socket
import os
from dotenv import load_dotenv

def check_port(host, port, timeout=5):
    """Check if a specific port is open on a host."""
    print(f"Checking if {host}:{port} is reachable...")
    
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(timeout)
        result = sock.connect_ex((host, port))
        sock.close()
        
        if result == 0:
            print(f"✅ Port {port} is OPEN on {host}")
            return True
        else:
            print(f"❌ Port {port} is CLOSED or FILTERED on {host}")
            print(f"\nPossible issues:")
            print(f"  1. Firewall blocking port {port}")
            print(f"  2. MySQL not configured for remote connections")
            print(f"  3. Wrong host/port in .env")
            return False
            
    except socket.gaierror:
        print(f"❌ Could not resolve hostname: {host}")
        print(f"   Check if the domain name is correct")
        return False
    except socket.timeout:
        print(f"❌ Connection timed out after {timeout} seconds")
        print(f"   Port {port} is likely blocked by a firewall")
        return False
    except Exception as e:
        print(f"❌ Error: {e}")
        return False

if __name__ == "__main__":
    load_dotenv()
    
    host = os.getenv('DB_HOST', 'localhost')
    port = int(os.getenv('DB_PORT', 3306))
    
    print("=" * 50)
    print("MySQL Port Connection Test")
    print("=" * 50)
    print(f"Host: {host}")
    print(f"Port: {port}")
    print("-" * 50)
    
    if check_port(host, port):
        print("\n✅ Port is accessible. Now try the full database test:")
        print("   python test_db.py")
    else:
        print("\n⚠️  You need to:")
        print("   1. Enable remote MySQL connections on the server")
        print("   2. Open port 3306 in the firewall")
        print("   3. Or use SSH tunneling instead")
