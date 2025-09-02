#!/usr/bin/env python3
"""
Portable Python Launcher for RFID API
This script ensures the RFID API runs with the portable Python environment
"""

import os
import sys
import subprocess
import platform
import time
from pathlib import Path

def get_portable_python_path():
    """Get the path to the portable Python executable"""
    script_dir = Path(__file__).parent
    system = platform.system().lower()
    
    if system == "darwin":  # macOS
        return script_dir / "portable_python" / "bin" / "python3"
    elif system == "windows":
        return script_dir / "portable_python" / "Scripts" / "python.exe"
    else:  # Linux
        return script_dir / "portable_python" / "bin" / "python3"

def get_python_executable():
    """Get the best available Python executable"""
    # First try portable Python
    portable_python = get_portable_python_path()
    if portable_python.exists():
        return str(portable_python)
    
    # Fallback to system Python
    system_python = None
    for cmd in ['python3', 'python']:
        try:
            result = subprocess.run([cmd, '--version'], 
                                  capture_output=True, text=True, timeout=5)
            if result.returncode == 0:
                system_python = cmd
                break
        except (subprocess.TimeoutExpired, FileNotFoundError):
            continue
    
    return system_python

def check_dependencies(python_exec):
    """Check if required packages are available"""
    required_packages = ['flask', 'flask_cors', 'serial']
    missing_packages = []
    
    for package in required_packages:
        try:
            subprocess.run([python_exec, '-c', f'import {package}'], 
                          capture_output=True, check=True, timeout=5)
        except (subprocess.TimeoutExpired, subprocess.CalledProcessError):
            missing_packages.append(package)
    
    return missing_packages

def install_dependencies(python_exec):
    """Install missing dependencies"""
    print("Installing missing dependencies...")
    
    # Try to install from requirements.txt first
    requirements_file = Path(__file__).parent / "requirements.txt"
    if requirements_file.exists():
        try:
            subprocess.run([python_exec, '-m', 'pip', 'install', '-r', str(requirements_file)], 
                          check=True, timeout=300)
            return True
        except (subprocess.TimeoutExpired, subprocess.CalledProcessError):
            print("Failed to install from requirements.txt, trying individual packages...")
    
    # Install packages individually
    packages = ['Flask==2.3.3', 'Flask-CORS==4.0.0', 'pyserial==3.5']
    for package in packages:
        try:
            print(f"Installing {package}...")
            subprocess.run([python_exec, '-m', 'pip', 'install', package], 
                          check=True, timeout=120)
        except (subprocess.TimeoutExpired, subprocess.CalledProcessError) as e:
            print(f"Failed to install {package}: {e}")
            return False
    
    return True

def main():
    """Main launcher function"""
    print("🚀 Portable Python RFID API Launcher")
    print("=" * 50)
    
    # Get Python executable
    python_exec = get_python_executable()
    if not python_exec:
        print("❌ Error: No Python executable found!")
        print("Please install Python 3.7+ or ensure the portable Python environment is available.")
        return 1
    
    print(f"✅ Using Python: {python_exec}")
    
    # Check if it's portable Python
    if "portable_python" in python_exec:
        print("🔧 Using portable Python environment")
    else:
        print("⚠️  Using system Python (portable environment not found)")
    
    # Check dependencies
    print("\n📦 Checking dependencies...")
    missing_packages = check_dependencies(python_exec)
    
    if missing_packages:
        print(f"❌ Missing packages: {', '.join(missing_packages)}")
        if not install_dependencies(python_exec):
            print("❌ Failed to install dependencies!")
            return 1
        print("✅ Dependencies installed successfully!")
    else:
        print("✅ All dependencies are available!")
    
    # Launch the RFID API
    print("\n🚀 Launching RFID API...")
    api_script = Path(__file__).parent / "rfid_api.py"
    
    if not api_script.exists():
        print(f"❌ Error: RFID API script not found at {api_script}")
        return 1
    
    # Set environment variables
    env = os.environ.copy()
    env['RFID_API_PORT'] = '5001'
    env['PYTHONPATH'] = str(Path(__file__).parent)
    
    try:
        print(f"📍 Starting API on port 5001...")
        print(f"🌐 Access: http://localhost:5001")
        print(f"📁 Working directory: {Path(__file__).parent}")
        print("\n" + "=" * 50)
        print("Press Ctrl+C to stop the server")
        print("=" * 50)
        
        # Launch the API
        subprocess.run([python_exec, str(api_script)], 
                      env=env, cwd=str(Path(__file__).parent))
        
    except KeyboardInterrupt:
        print("\n\n🛑 Server stopped by user")
    except Exception as e:
        print(f"\n❌ Error launching API: {e}")
        return 1
    
    return 0

if __name__ == "__main__":
    sys.exit(main())
