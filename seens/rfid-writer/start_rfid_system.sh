#!/bin/bash

echo "========================================"
echo "   PORTABLE RFID SYSTEM LAUNCHER"
echo "========================================"
echo ""
echo "Starting portable Python environment..."
echo ""

# Change to script directory
cd "$(dirname "$0")"

# Check if portable Python exists
if [ ! -f "./portable_python/bin/python3" ]; then
    echo "ERROR: Portable Python not found!"
    echo "Please run setup_portable_system.py first"
    exit 1
fi

echo "Starting RFID API server..."
echo ""
echo "Access the system at: http://localhost:5001"
echo "Press Ctrl+C to stop the server"
echo ""

./portable_python/bin/python3 portable_python_launcher.py

echo ""
echo "Server stopped."
