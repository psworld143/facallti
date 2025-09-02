# RFID Card Reader/Writer - Python API Solution

This solution provides a more reliable approach to RFID communication by using a Python API server as an intermediary between PHP and Arduino.

## Architecture

```
PHP Web Interface → PHP Interface Class → Python Flask API → Arduino (Serial)
```

## Benefits of This Approach

1. **More Reliable**: Python's serial communication is more stable than PHP's
2. **Better Error Handling**: Comprehensive logging and error reporting
3. **Cross-Platform**: Works on Windows, macOS, and Linux
4. **Easier Debugging**: Clear separation of concerns
5. **Better Performance**: Python handles serial communication efficiently

## Files Overview

- **`rfid_api.py`** - Python Flask API server
- **`php_rfid_interface.php`** - PHP class that communicates with Python API
- **`new_interface.php`** - Modern web interface using the new system
- **`requirements.txt`** - Python dependencies
- **`start_python_api.sh`** - Startup script for macOS/Linux
- **`start_python_api.bat`** - Startup script for Windows

## Prerequisites

1. **Python 3.7+** installed on your system
2. **Arduino IDE** with the RFID sketch uploaded
3. **RFID hardware**: Arduino Uno + RC522 module
4. **Web server** (XAMPP, Apache, etc.)

## Setup Instructions

### Step 1: Install Python Dependencies

```bash
# Navigate to the rfid-writer directory
cd seens/rfid-writer

# Install required packages
pip3 install -r requirements.txt
```

### Step 2: Upload Arduino Sketch

Make sure you have uploaded the `arduino_rfid_simple.ino` sketch to your Arduino. This sketch supports the following commands:

- `PING` - Test connection
- `STATUS` - Get Arduino status
- `READ_UID` - Read card UID
- `READ:block` - Read data from specific block
- `WRITE:block:data` - Write data to specific block

### Step 3: Start Python API Server

#### On macOS/Linux:
```bash
chmod +x start_python_api.sh
./start_python_api.sh
```

#### On Windows:
```cmd
start_python_api.bat
```

The server will start on `http://localhost:5001`

### Step 4: Access Web Interface

Open `new_interface.php` in your web browser. The interface will:

1. Check if the Python API is running
2. Display available serial ports
3. Allow you to connect to Arduino
4. Provide RFID read/write operations

## API Endpoints

The Python API provides these endpoints:

- `POST /api/connect` - Connect to Arduino
- `POST /api/disconnect` - Disconnect from Arduino
- `GET /api/status` - Get connection status
- `POST /api/read_uid` - Read card UID
- `POST /api/read` - Read data from block
- `POST /api/write` - Write data to block
- `POST /api/test` - Test Arduino connection
- `GET /api/ports` - Get available serial ports
- `GET /health` - Health check

## Usage

### 1. Check API Status
The interface automatically checks if the Python API is running.

### 2. Connect to Arduino
1. Click "Refresh Ports" to see available serial ports
2. Select the correct port (usually `/dev/cu.usbserial-*` on macOS)
3. Click "Connect"

### 3. Read Card UID
1. Place an RFID card on the reader
2. Click "Read UID"
3. The UID will be displayed

### 4. Read Data from Block
1. Enter the block number (default: 4)
2. Click "Read Data"
3. The data will be displayed

### 5. Write Data to Block
1. Enter the block number
2. Enter the data to write (max 16 characters)
3. Click "Write Data"

## Troubleshooting

### Python API Won't Start
- Check if Python 3 is installed: `python3 --version`
- Check if pip is installed: `pip3 --version`
- Install missing packages manually: `pip3 install Flask Flask-CORS pyserial`

### No Serial Ports Found
- Make sure Arduino is connected via USB
- Check if Arduino IDE Serial Monitor is closed
- Try a different USB cable or port
- On macOS, look for `/dev/cu.usbserial-*` ports

### Connection Fails
- Verify the correct port is selected
- Check if Arduino sketch is uploaded
- Ensure Arduino is powered and responding
- Check the Python API logs for detailed error messages

### Can't Read/Write Cards
- Make sure the card is properly placed on the reader
- Check if the card is MiFare Classic compatible
- Verify the block number is valid (0-63)
- Check Arduino Serial Monitor for debugging output

## Logging

The Python API creates detailed logs in `rfid_api.log` and also displays them in the web interface. Use these logs to debug issues.

## Security Notes

- The API runs on localhost by default
- If deploying to production, consider adding authentication
- Serial port access requires appropriate permissions

## Performance Tips

- Keep the Python API running between operations
- Avoid frequent connect/disconnect cycles
- Use appropriate timeouts for card operations

## Support

If you encounter issues:

1. Check the Python API logs
2. Verify Arduino Serial Monitor output
3. Ensure all dependencies are installed
4. Check serial port permissions
5. Verify hardware connections

## Migration from Old System

This new system replaces the direct PHP-to-Arduino communication. The old files can be removed:

- `arduino_communicator_simple.php`
- `simple_interface.php`
- Other test files

The new system provides the same functionality with better reliability and easier debugging.
