@echo off
chcp 65001 >nul

echo === RFID Python API Server Startup ===
echo.

REM Check if Python is installed
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Python is not installed or not in PATH. Please install Python first.
    pause
    exit /b 1
)

REM Check if pip is installed
pip --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ pip is not installed. Please install pip first.
    pause
    exit /b 1
)

echo ✅ Python and pip are available
echo.

REM Install required packages
echo Installing required Python packages...
pip install -r requirements.txt

if %errorlevel% neq 0 (
    echo ❌ Failed to install required packages. Please check the requirements.txt file.
    pause
    exit /b 1
)

echo ✅ Required packages installed successfully
echo.

REM Check if Arduino is connected and get available ports
echo Checking for available serial ports...
python -c "import serial.tools.list_ports; ports = list(serial.tools.list_ports.comports()); print('Available serial ports:' if ports else 'No serial ports found. Make sure Arduino is connected.'); [print(f'  - {port.device}: {port.description}') for port in ports]"

echo.

REM Start the API server
echo 🚀 Starting RFID Python API Server...
echo    Server will run on http://localhost:5000
echo    Press Ctrl+C to stop the server
echo.

REM Set environment variables and start server
set RFID_API_PORT=5001
set RFID_API_HOST=0.0.0.0

python rfid_api.py

pause
