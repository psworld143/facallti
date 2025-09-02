#!/bin/bash

# RFID Python API Server Startup Script

echo "=== RFID Python API Server Startup ==="
echo ""

# Check if Python 3 is installed
if ! command -v python3 &> /dev/null; then
    echo "❌ Python 3 is not installed. Please install Python 3 first."
    exit 1
fi

# Check if pip is installed
if ! command -v pip3 &> /dev/null; then
    echo "❌ pip3 is not installed. Please install pip3 first."
    exit 1
fi

echo "✅ Python 3 and pip3 are available"
echo ""

# Install required packages
echo "Installing required Python packages..."
pip3 install -r requirements.txt

if [ $? -ne 0 ]; then
    echo "❌ Failed to install required packages. Please check the requirements.txt file."
    exit 1
fi

echo "✅ Required packages installed successfully"
echo ""

# Check if Arduino is connected and get available ports
echo "Checking for available serial ports..."
python3 -c "
import serial.tools.list_ports
ports = list(serial.tools.list_ports.comports())
if ports:
    print('Available serial ports:')
    for port in ports:
        print(f'  - {port.device}: {port.description}')
else:
    print('No serial ports found. Make sure Arduino is connected.')
"

echo ""

# Start the API server
echo "🚀 Starting RFID Python API Server..."
echo "   Server will run on http://localhost:5000"
echo "   Press Ctrl+C to stop the server"
echo ""

# Set environment variables and start server
export RFID_API_PORT=5001
export RFID_API_HOST=0.0.0.0

python3 rfid_api.py
