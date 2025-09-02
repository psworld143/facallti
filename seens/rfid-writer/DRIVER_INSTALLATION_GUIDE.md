# Arduino Driver Installation Guide

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
