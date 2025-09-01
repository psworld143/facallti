# SEENS RFID Writer Module

This module provides RFID card writing capabilities for the SEENS (Student Entry and Exit Notification System) using Arduino Uno and RC522 RFID module with Generic MiFare cards.

## Features

- Write student ID numbers (e.g., "2021-0001") to MiFare RFID cards
- Read data from RFID cards for verification
- Web interface for easy operation
- REST API for integration with main SEENS system
- Support for multiple serial ports
- Real-time status monitoring

## Hardware Requirements

### Arduino Uno Setup
- Arduino Uno board
- RC522 RFID module
- MiFare Classic RFID cards (1K or 4K)
- USB cable for Arduino

### Wiring Diagram
```
RC522 Module -> Arduino Uno
SDA   -> Pin 10
SCK   -> Pin 13
MOSI  -> Pin 11
MISO  -> Pin 12
RST   -> Pin 9
3.3V  -> 3.3V
GND   -> GND
```

## Software Dependencies

### Arduino Dependencies
1. **MFRC522 Library** - Install via Arduino IDE Library Manager
   - Open Arduino IDE
   - Go to Tools > Manage Libraries
   - Search for "MFRC522"
   - Install "MFRC522 by GithubCommunity"

### PHP Dependencies
The module uses the existing `php-serial` library in your vendor folder:
- `vendor/gregwar/php-serial` (already included)

## Installation

### 1. Arduino Setup
1. Connect the RC522 module to Arduino Uno according to the wiring diagram
2. Open `arduino_rfid_writer.ino` in Arduino IDE
3. Install the MFRC522 library if not already installed
4. Upload the sketch to your Arduino Uno
5. Open Serial Monitor to verify the Arduino is ready (should show "RFID_WRITER_READY")

### 2. PHP Setup
1. Ensure the `php-serial` library is available in your vendor folder
2. The module is ready to use - no additional PHP setup required

### 3. Platform-Specific Setup

#### Linux/Mac Permissions
If you're on Linux or Mac, you may need to grant permissions to access the serial port:
```bash
sudo usermod -a -G dialout www-data
sudo chmod 666 /dev/ttyUSB0
```

#### Windows Setup
1. **Install Arduino Drivers**: Download and install Arduino drivers from the official Arduino website
2. **Check Device Manager**: 
   - Open Device Manager (Win+X → Device Manager)
   - Look for "Ports (COM & LPT)" → "Arduino Uno (COM#)"
   - Note the COM port number (usually COM3, COM4, or COM5)
3. **Common Windows Ports**: COM1-COM8 are typically available
4. **Driver Issues**: If Arduino doesn't appear, try:
   - Unplugging and reconnecting the Arduino
   - Installing/reinstalling Arduino drivers
   - Using a different USB cable

## Usage

### Web Interface
1. Navigate to `http://localhost/seens/rfid-writer/`
2. Select the correct serial port:
   - **Windows**: Usually COM3, COM4, or COM5 (check Device Manager)
   - **Linux/Mac**: Usually `/dev/ttyUSB0` or `/dev/ttyACM0`
3. Click "Connect" to establish connection with Arduino
4. Enter student ID (e.g., "2021-0001") and click "Write to Card"
5. Place the MiFare card on the RFID reader when prompted
6. Use "Read from Card" to verify the data was written correctly

### API Usage

#### Write Student ID to RFID Card
```bash
# Windows
curl -X POST http://localhost/seens/rfid-writer/api.php \
  -H "X-API-Key: seens_rfid_2024" \
  -d "action=write_student_id&student_id=2021-0001&port=COM3&block=4"

# Linux/Mac
curl -X POST http://localhost/seens/rfid-writer/api.php \
  -H "X-API-Key: seens_rfid_2024" \
  -d "action=write_student_id&student_id=2021-0001&port=/dev/ttyUSB0&block=4"
```

#### Read Student ID from RFID Card
```bash
# Windows
curl -X POST http://localhost/seens/rfid-writer/api.php \
  -H "X-API-Key: seens_rfid_2024" \
  -d "action=read_student_id&port=COM3&block=4"

# Linux/Mac
curl -X POST http://localhost/seens/rfid-writer/api.php \
  -H "X-API-Key: seens_rfid_2024" \
  -d "action=read_student_id&port=/dev/ttyUSB0&block=4"
```

#### Get Available Serial Ports
```bash
curl -X GET http://localhost/seens/rfid-writer/api.php?action=get_ports \
  -H "X-API-Key: seens_rfid_2024"
```

#### Test Connection
```bash
# Windows
curl -X POST http://localhost/seens/rfid-writer/api.php \
  -H "X-API-Key: seens_rfid_2024" \
  -d "action=test_connection&port=COM3"

# Linux/Mac
curl -X POST http://localhost/seens/rfid-writer/api.php \
  -H "X-API-Key: seens_rfid_2024" \
  -d "action=test_connection&port=/dev/ttyUSB0"
```

### PHP Integration
```php
require_once 'rfid-writer/rfid_writer.php';

// Platform-aware port selection
$port = (PHP_OS === 'WINNT') ? 'COM3' : '/dev/ttyUSB0';
$rfid = new RFIDWriter($port);

if ($rfid->connect()) {
    $result = $rfid->writeToCard('2021-0001', 4);
    if ($result['status'] === 'success') {
        echo "Student ID written successfully!";
    } else {
        echo "Error: " . $result['message'];
    }
    $rfid->disconnect();
}
```

## File Structure

```
rfid-writer/
├── arduino_rfid_writer.ino    # Arduino sketch for RFID writing
├── rfid_writer.php            # PHP class for serial communication
├── index.php                  # Web interface
├── api.php                    # REST API endpoint
└── README.md                  # This file
```

## Configuration

### Default Settings
- **Baud Rate**: 9600
- **Default Block**: 4 (commonly used for data storage)
- **Default Key**: 0xFF 0xFF 0xFF 0xFF 0xFF 0xFF (factory default)
- **API Key**: seens_rfid_2024 (change in api.php for security)
- **Default Ports**:
  - **Windows**: COM3
  - **Linux/Mac**: /dev/ttyUSB0

### Customization
You can modify these settings in the respective files:
- Arduino settings: `arduino_rfid_writer.ino`
- PHP settings: `rfid_writer.php`
- API settings: `api.php`

## Troubleshooting

### Common Issues

1. **"Failed to connect to Arduino"**
   - Check if Arduino is connected and powered
   - Verify the correct serial port is selected
   - Ensure Arduino sketch is uploaded and running

2. **"Serial port not connected"**
   - Check USB cable connection
   - Try a different USB port
   - Restart Arduino IDE and re-upload sketch
   - **Windows**: Check Device Manager for correct COM port
   - **Windows**: Install/reinstall Arduino drivers if needed

3. **"Authentication failed"**
   - The RFID card may have a different key
   - Try using a new/unused MiFare card
   - Check if the card is properly placed on the reader

4. **"Not MiFare card"**
   - Ensure you're using MiFare Classic cards (1K or 4K)
   - Some cards may be incompatible

### Debug Mode
To enable debug output, modify the Arduino sketch to include more detailed serial output:
```cpp
#define DEBUG_MODE true
```

## Security Considerations

1. **Change the default API key** in `api.php`
2. **Use HTTPS** in production environments
3. **Implement proper authentication** for the web interface
4. **Validate all input data** before writing to cards
5. **Log all RFID operations** for audit purposes

## Support

For issues and questions:
1. Check the troubleshooting section above
2. Verify hardware connections
3. Test with known working MiFare cards
4. Check Arduino Serial Monitor for error messages

## License

This module is part of the SEENS system and follows the same licensing terms.
