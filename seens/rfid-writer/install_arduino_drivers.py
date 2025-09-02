#!/usr/bin/env python3
"""
Arduino Driver Installer
Installs Arduino drivers for different platforms
"""

import os
import sys
import platform
import subprocess
import urllib.request
from pathlib import Path

# Driver download URLs for different platforms
DRIVER_DOWNLOADS = {
    "windows": {
        "ch340": "https://github.com/wch/CH340/raw/master/CH341SER.EXE",
        "cp210x": "https://www.silabs.com/documents/public/software/CP210x_Windows_Drivers.zip",
        "ftdi": "https://ftdichip.com/drivers/vcp-drivers/"
    },
    "darwin": {
        "ch340": "https://github.com/wch/CH340/raw/master/CH341SER_MAC.zip",
        "cp210x": "https://www.silabs.com/documents/public/software/CP210x_MacOS_Drivers.zip",
        "ftdi": "https://ftdichip.com/drivers/vcp-drivers/"
    },
    "linux": {
        "ch340": "Built-in support",
        "cp210x": "Built-in support", 
        "ftdi": "Built-in support"
    }
}

def get_system_info():
    """Get current system information"""
    system = platform.system().lower()
    machine = platform.machine().lower()
    
    print(f"🖥️  System: {system}")
    print(f"🏗️  Architecture: {machine}")
    
    return system, machine

def check_arduino_connection():
    """Check if Arduino is connected and detected"""
    print("\n🔍 Checking Arduino connection...")
    
    system = platform.system().lower()
    
    if system == "windows":
        # Check Windows device manager
        try:
            result = subprocess.run(["wmic", "path", "Win32_PnPEntity", "where", "DeviceID", "like", "%USB%", "get", "Name"], 
                                  capture_output=True, text=True, timeout=10)
            if result.returncode == 0:
                output = result.stdout.lower()
                if any(keyword in output for keyword in ['arduino', 'ch340', 'cp210x', 'ftdi', 'usb serial']):
                    print("✅ Arduino device detected in Windows")
                    return True
        except:
            pass
    else:
        # Check Unix-like systems
        try:
            # Check for USB serial devices
            result = subprocess.run(["ls", "/dev/tty*", "/dev/cu.*"], 
                                  capture_output=True, text=True, timeout=5)
            if result.returncode == 0:
                output = result.stdout.lower()
                if any(keyword in output for keyword in ['usbserial', 'usbmodem', 'tty.usb']):
                    print("✅ USB serial device detected")
                    return True
        except:
            pass
    
    print("❌ No Arduino device detected")
    return False

def install_windows_drivers():
    """Install drivers for Windows"""
    print("\n🪟 Installing Windows drivers...")
    
    drivers_dir = Path("arduino_drivers")
    drivers_dir.mkdir(exist_ok=True)
    
    # Download CH340 driver
    print("📥 Downloading CH340 driver...")
    ch340_url = DRIVER_DOWNLOADS["windows"]["ch340"]
    ch340_file = drivers_dir / "CH341SER.EXE"
    
    try:
        urllib.request.urlretrieve(ch340_url, ch340_file)
        print("✅ CH340 driver downloaded")
        print("💡 Run CH341SER.EXE to install the driver")
    except Exception as e:
        print(f"❌ Failed to download CH340 driver: {e}")
    
    # Download CP210x driver
    print("📥 Downloading CP210x driver...")
    cp210x_url = DRIVER_DOWNLOADS["windows"]["cp210x"]
    cp210x_file = drivers_dir / "CP210x_Windows_Drivers.zip"
    
    try:
        urllib.request.urlretrieve(cp210x_url, cp210x_file)
        print("✅ CP210x driver downloaded")
        print("💡 Extract and run the installer")
    except Exception as e:
        print(f"❌ Failed to download CP210x driver: {e}")
    
    print("\n📋 Windows Driver Installation Steps:")
    print("1. Download drivers from the arduino_drivers folder")
    print("2. Run CH341SER.EXE for CH340 chips")
    print("3. Extract and run CP210x installer")
    print("4. Restart computer if prompted")
    print("5. Check Device Manager for COM ports")

def install_macos_drivers():
    """Install drivers for macOS"""
    print("\n🍎 Installing macOS drivers...")
    
    drivers_dir = Path("arduino_drivers")
    drivers_dir.mkdir(exist_ok=True)
    
    # Download CH340 driver
    print("📥 Downloading CH340 driver...")
    ch340_url = DRIVER_DOWNLOADS["darwin"]["ch340"]
    ch340_file = drivers_dir / "CH341SER_MAC.zip"
    
    try:
        urllib.request.urlretrieve(ch340_url, ch340_file)
        print("✅ CH340 driver downloaded")
        print("💡 Extract and install the .pkg file")
    except Exception as e:
        print(f"❌ Failed to download CH340 driver: {e}")
    
    # Download CP210x driver
    print("📥 Downloading CP210x driver...")
    cp210x_url = DRIVER_DOWNLOADS["darwin"]["cp210x"]
    cp210x_file = drivers_dir / "CP210x_MacOS_Drivers.zip"
    
    try:
        urllib.request.urlretrieve(cp210x_url, cp210x_file)
        print("✅ CP210x driver downloaded")
        print("💡 Extract and install the .pkg file")
    except Exception as e:
        print(f"❌ Failed to download CP210x driver: {e}")
    
    print("\n📋 macOS Driver Installation Steps:")
    print("1. Download drivers from the arduino_drivers folder")
    print("2. Extract the .zip files")
    print("3. Double-click .pkg files to install")
    print("4. Restart computer if prompted")
    print("5. Check System Preferences > Security & Privacy")

def install_linux_drivers():
    """Install drivers for Linux"""
    print("\n🐧 Linux driver installation...")
    
    print("✅ Linux has built-in support for most Arduino USB chips")
    print("💡 If you have issues, try these commands:")
    print("   sudo usermod -a -G dialout $USER")
    print("   sudo chmod 666 /dev/ttyUSB*")
    print("   sudo chmod 666 /dev/ttyACM*")
    
    # Check if user is in dialout group
    try:
        result = subprocess.run(["groups"], capture_output=True, text=True, timeout=5)
        if result.returncode == 0:
            groups = result.stdout.strip()
            if "dialout" in groups:
                print("✅ User is in dialout group")
            else:
                print("⚠️  User not in dialout group - run: sudo usermod -a -G dialout $USER")
    except:
        pass

def create_driver_guide():
    """Create a comprehensive driver installation guide"""
    guide_content = """# Arduino Driver Installation Guide

## Overview
This guide helps you install the necessary drivers for Arduino boards with different USB-to-Serial chips.

## Common Arduino USB Chips

### 1. CH340/CH341
- **Used in**: Many Arduino Uno clones, ESP8266, ESP32
- **Driver**: CH341SER.EXE (Windows) / CH341SER_MAC.pkg (macOS)
- **Installation**: Run the installer and restart

### 2. CP210x
- **Used in**: ESP32, some Arduino clones
- **Driver**: Silicon Labs CP210x drivers
- **Installation**: Extract and run installer

### 3. FTDI
- **Used in**: Official Arduino boards, some clones
- **Driver**: Usually built-in, download from FTDI website if needed

## Platform-Specific Instructions

### Windows
1. Download drivers from arduino_drivers folder
2. Run CH341SER.EXE for CH340 chips
3. Extract and run CP210x installer
4. Restart computer if prompted
5. Check Device Manager for COM ports

### macOS
1. Download drivers from arduino_drivers folder
2. Extract .zip files
3. Double-click .pkg files to install
4. Restart computer if prompted
5. Check System Preferences > Security & Privacy

### Linux
1. Linux has built-in support for most chips
2. Add user to dialout group: `sudo usermod -a -G dialout $USER`
3. Set permissions: `sudo chmod 666 /dev/ttyUSB*`
4. Log out and back in

## Troubleshooting

### Device Not Recognized
- Try different USB cable
- Try different USB port
- Check if drivers are installed
- Restart computer

### Permission Denied (Linux/macOS)
- Add user to dialout group
- Set proper permissions
- Check USB port permissions

### Port Not Available
- Close Arduino IDE Serial Monitor
- Close other applications using the port
- Check Device Manager (Windows) or System Information (macOS)

## Testing Connection

After installing drivers:
1. Connect Arduino
2. Check if port appears in device list
3. Try uploading a simple sketch
4. Check Serial Monitor communication

## Support

If you continue having issues:
- Check Arduino forum
- Verify board compatibility
- Try different USB cable/port
- Contact board manufacturer
"""
    
    with open("DRIVER_INSTALLATION_GUIDE.md", "w") as f:
        f.write(guide_content)
    
    print("✅ Created DRIVER_INSTALLATION_GUIDE.md")

def main():
    """Main function"""
    print("🔌 Arduino Driver Installer")
    print("=" * 50)
    
    # Change to script directory
    script_dir = Path(__file__).parent
    os.chdir(script_dir)
    
    # Get system info
    system, machine = get_system_info()
    
    # Check Arduino connection
    arduino_detected = check_arduino_connection()
    
    # Install drivers based on platform
    if system == "windows":
        install_windows_drivers()
    elif system == "darwin":
        install_macos_drivers()
    elif system == "linux":
        install_linux_drivers()
    else:
        print(f"❌ Unsupported system: {system}")
        return 1
    
    # Create driver guide
    create_driver_guide()
    
    print("\n🎉 Driver installation setup completed!")
    print("\n📋 Next steps:")
    print("1. Install the downloaded drivers")
    print("2. Restart your computer if prompted")
    print("3. Connect your Arduino")
    print("4. Check if the device is recognized")
    print("5. Run the RFID system")
    
    if not arduino_detected:
        print("\n⚠️  Note: No Arduino detected yet. Install drivers first, then reconnect.")
    
    return 0

if __name__ == "__main__":
    sys.exit(main())
