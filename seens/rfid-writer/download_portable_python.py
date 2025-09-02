#!/usr/bin/env python3
"""
Portable Python Downloader
Downloads and sets up portable Python for the RFID system
"""

import os
import sys
import platform
import subprocess
import urllib.request
import zipfile
import tarfile
from pathlib import Path

# Python download URLs for different platforms
PYTHON_DOWNLOADS = {
    "windows": {
        "url": "https://www.python.org/ftp/python/3.11.5/python-3.11.5-embed-amd64.zip",
        "filename": "python-3.11.5-embed-amd64.zip",
        "extract_dir": "portable_python"
    },
    "darwin": {
        "url": "https://www.python.org/ftp/python/3.11.5/python-3.11.5-macos11.pkg",
        "filename": "python-3.11.5-macos11.pkg",
        "extract_dir": "portable_python"
    },
    "linux": {
        "url": "https://www.python.org/ftp/python/3.11.5/Python-3.11.5.tgz",
        "filename": "Python-3.11.5.tgz",
        "extract_dir": "portable_python"
    }
}

def get_system_info():
    """Get current system information"""
    system = platform.system().lower()
    machine = platform.machine().lower()
    
    print(f"🖥️  System: {system}")
    print(f"🏗️  Architecture: {machine}")
    
    return system, machine

def download_file(url, filename):
    """Download a file from URL"""
    print(f"📥 Downloading {filename}...")
    print(f"🔗 URL: {url}")
    
    try:
        urllib.request.urlretrieve(url, filename)
        print(f"✅ Downloaded {filename}")
        return True
    except Exception as e:
        print(f"❌ Download failed: {e}")
        return False

def extract_archive(archive_path, extract_dir):
    """Extract archive file"""
    print(f"📦 Extracting {archive_path}...")
    
    try:
        if archive_path.endswith('.zip'):
            with zipfile.ZipFile(archive_path, 'r') as zip_ref:
                zip_ref.extractall(extract_dir)
        elif archive_path.endswith('.tar.gz') or archive_path.endswith('.tgz'):
            with tarfile.open(archive_path, 'r:gz') as tar_ref:
                tar_ref.extractall(extract_dir)
        else:
            print(f"❌ Unsupported archive format: {archive_path}")
            return False
        
        print(f"✅ Extracted to {extract_dir}")
        return True
    except Exception as e:
        print(f"❌ Extraction failed: {e}")
        return False

def setup_portable_python():
    """Set up portable Python environment"""
    system, machine = get_system_info()
    
    # Check if portable Python already exists
    portable_dir = Path("portable_python")
    if portable_dir.exists():
        print(f"✅ Portable Python already exists at {portable_dir}")
        return True
    
    # Get download info for current system
    if system not in PYTHON_DOWNLOADS:
        print(f"❌ Unsupported system: {system}")
        return False
    
    download_info = PYTHON_DOWNLOADS[system]
    url = download_info["url"]
    filename = download_info["filename"]
    extract_dir = download_info["extract_dir"]
    
    print(f"\n🚀 Setting up portable Python for {system}...")
    
    # Download Python
    if not download_file(url, filename):
        return False
    
    # Extract Python
    if not extract_archive(filename, extract_dir):
        return False
    
    # Clean up downloaded file
    try:
        os.remove(filename)
        print(f"🧹 Cleaned up {filename}")
    except:
        pass
    
    # Set up virtual environment
    print("🔧 Setting up virtual environment...")
    try:
        if system == "windows":
            python_exe = Path(extract_dir) / "python.exe"
            if python_exe.exists():
                subprocess.run([str(python_exe), "-m", "venv", "portable_python"], check=True)
        else:
            # For macOS and Linux, create a proper virtual environment
            subprocess.run(["python3", "-m", "venv", "portable_python"], check=True)
        
        print("✅ Virtual environment created")
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ Failed to create virtual environment: {e}")
        return False

def install_dependencies():
    """Install required dependencies in portable Python"""
    print("\n📦 Installing dependencies...")
    
    portable_dir = Path("portable_python")
    if not portable_dir.exists():
        print("❌ Portable Python not found!")
        return False
    
    # Determine Python executable path
    system = platform.system().lower()
    if system == "windows":
        python_exe = portable_dir / "Scripts" / "python.exe"
    else:
        python_exe = portable_dir / "bin" / "python3"
    
    if not python_exe.exists():
        print(f"❌ Python executable not found at {python_exe}")
        return False
    
    print(f"🔧 Using Python: {python_exe}")
    
    # Install dependencies
    try:
        # Upgrade pip first
        subprocess.run([str(python_exe), "-m", "pip", "install", "--upgrade", "pip"], check=True)
        
        # Install from requirements.txt
        requirements_file = Path("requirements.txt")
        if requirements_file.exists():
            subprocess.run([str(python_exe), "-m", "pip", "install", "-r", str(requirements_file)], check=True)
        else:
            # Install packages individually
            packages = ['Flask==2.3.3', 'Flask-CORS==4.0.0', 'pyserial==3.5']
            for package in packages:
                subprocess.run([str(python_exe), "-m", "pip", "install", package], check=True)
        
        print("✅ Dependencies installed successfully!")
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ Failed to install dependencies: {e}")
        return False

def create_launcher_scripts():
    """Create platform-specific launcher scripts"""
    print("\n📝 Creating launcher scripts...")
    
    system = platform.system().lower()
    
    if system == "windows":
        # Create batch file
        batch_content = """@echo off
echo Starting Portable Python RFID API...
cd /d "%~dp0"
portable_python\\Scripts\\python.exe portable_python_launcher.py
pause
"""
        with open("start_rfid_api.bat", "w") as f:
            f.write(batch_content)
        print("✅ Created start_rfid_api.bat")
        
    else:
        # Create shell script
        shell_content = """#!/bin/bash
echo "Starting Portable Python RFID API..."
cd "$(dirname "$0")"
./portable_python/bin/python3 portable_python_launcher.py
"""
        with open("start_rfid_api.sh", "w") as f:
            f.write(shell_content)
        
        # Make executable
        os.chmod("start_rfid_api.sh", 0o755)
        print("✅ Created start_rfid_api.sh")

def main():
    """Main function"""
    print("🚀 Portable Python RFID System Setup")
    print("=" * 50)
    
    # Change to script directory
    script_dir = Path(__file__).parent
    os.chdir(script_dir)
    
    # Set up portable Python
    if not setup_portable_python():
        print("❌ Failed to set up portable Python!")
        return 1
    
    # Install dependencies
    if not install_dependencies():
        print("❌ Failed to install dependencies!")
        return 1
    
    # Create launcher scripts
    create_launcher_scripts()
    
    print("\n🎉 Setup completed successfully!")
    print("\n📋 Next steps:")
    print("1. Connect your Arduino with RFID module")
    print("2. Run the launcher script:")
    
    system = platform.system().lower()
    if system == "windows":
        print("   - Double-click: start_rfid_api.bat")
    else:
        print("   - Run: ./start_rfid_api.sh")
    
    print("3. Or use the main SEENS dashboard (auto-starts)")
    
    return 0

if __name__ == "__main__":
    sys.exit(main())
