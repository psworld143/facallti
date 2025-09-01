<?php
/**
 * RFID Writer Interface for SEENS System
 * Communicates with Arduino Uno via Serial Port
 * 
 * Dependencies: php-serial (already in vendor/gregwar/php-serial)
 */

require_once '../vendor/autoload.php';

// Fallback: Try to load PhpSerial manually if autoload fails
if (!class_exists('PhpSerial\PhpSerial')) {
    $manualPath = '../vendor/gregwar/php-serial/PhpSerial/PhpSerial.php';
    if (file_exists($manualPath)) {
        require_once $manualPath;
    }
}

use PhpSerial\PhpSerial as Serial;

class RFIDWriter {
    private $serial;
    private $port;
    private $baudRate;
    private $timeout;
    
    public function __construct($port = null, $baudRate = 9600, $timeout = 5) {
        // Check if PhpSerial class is available
        if (!class_exists('PhpSerial\PhpSerial')) {
            throw new Exception('PhpSerial\PhpSerial class not found. Please ensure the php-serial library is properly installed.');
        }
        
        // Set default port based on platform
        if ($port === null) {
            $port = (PHP_OS === 'WINNT') ? 'COM3' : '/dev/ttyUSB0';
        }
        
        $this->port = $port;
        $this->baudRate = $baudRate;
        $this->timeout = $timeout;
        $this->serial = new Serial();
    }
    
    /**
     * Initialize serial connection
     */
    public function connect() {
        try {
            // Check if port exists (for Linux/Mac)
            if (PHP_OS !== 'WINNT' && !file_exists($this->port)) {
                error_log("RFID Writer: Port {$this->port} does not exist. Arduino may not be connected.");
                return false;
            }
            
            $this->serial->deviceSet($this->port);
            $this->serial->confBaudRate($this->baudRate);
            $this->serial->confParity("none");
            $this->serial->confCharacterLength(8);
            $this->serial->confStopBits(1);
            $this->serial->confFlowControl("none");
            $this->serial->deviceOpen();
            
            // Wait for Arduino to initialize
            sleep(2);
            
            // Check if Arduino is ready
            $status = $this->getStatus();
            return $status === 'RFID_WRITER_READY';
            
        } catch (Exception $e) {
            error_log("RFID Writer Connection Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Disconnect from serial port
     */
    public function disconnect() {
        if ($this->serial->_dState === SERIAL_DEVICE_OPENED) {
            $this->serial->deviceClose();
        }
    }
    
    /**
     * Get Arduino status
     */
    public function getStatus() {
        if ($this->serial->_dState !== SERIAL_DEVICE_OPENED) {
            return false;
        }
        
        $this->serial->sendMessage("STATUS\n");
        $response = $this->readResponse();
        
        return $response;
    }
    
    /**
     * Write data to RFID card
     * 
     * @param string $data Data to write (e.g., "2021-0001")
     * @param int $block Block number (0-63 for MiFare Classic)
     * @return array Response with status and message
     */
    public function writeToCard($data, $block = 4) {
        if ($this->serial->_dState !== SERIAL_DEVICE_OPENED) {
            return ['status' => 'error', 'message' => 'Serial port not connected'];
        }
        
        // Validate input
        if (empty($data) || strlen($data) > 16) {
            return ['status' => 'error', 'message' => 'Invalid data length (max 16 characters)'];
        }
        
        if ($block < 0 || $block > 63) {
            return ['status' => 'error', 'message' => 'Invalid block number (0-63)'];
        }
        
        // Send write command
        $command = "WRITE:" . $data . ":" . $block . "\n";
        $this->serial->sendMessage($command);
        
        // Read response
        $response = $this->readResponse();
        
        if (strpos($response, 'SUCCESS:DATA_WRITTEN') !== false) {
            return [
                'status' => 'success',
                'message' => 'Data written successfully',
                'data' => $data,
                'block' => $block
            ];
        } elseif (strpos($response, 'ERROR:') !== false) {
            return [
                'status' => 'error',
                'message' => $response
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unknown response from Arduino'
            ];
        }
    }
    
    /**
     * Read data from RFID card
     * 
     * @param int $block Block number to read
     * @return array Response with status and data
     */
    public function readFromCard($block = 4) {
        if ($this->serial->_dState !== SERIAL_DEVICE_OPENED) {
            return ['status' => 'error', 'message' => 'Serial port not connected'];
        }
        
        if ($block < 0 || $block > 63) {
            return ['status' => 'error', 'message' => 'Invalid block number (0-63)'];
        }
        
        // Send read command
        $command = "READ:" . $block . "\n";
        $this->serial->sendMessage($command);
        
        // Read response
        $response = $this->readResponse();
        
        if (strpos($response, 'SUCCESS:DATA_READ') !== false) {
            // Extract data from response
            $lines = explode("\n", $response);
            $data = '';
            foreach ($lines as $line) {
                if (strpos($line, 'DATA:') === 0) {
                    $data = substr($line, 5);
                    break;
                }
            }
            
            return [
                'status' => 'success',
                'message' => 'Data read successfully',
                'data' => $data,
                'block' => $block
            ];
        } elseif (strpos($response, 'ERROR:') !== false) {
            return [
                'status' => 'error',
                'message' => $response
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unknown response from Arduino'
            ];
        }
    }
    
    /**
     * Read response from Arduino with timeout
     */
    private function readResponse() {
        $response = '';
        $startTime = time();
        
        while (time() - $startTime < $this->timeout) {
            $data = $this->serial->readLine();
            if ($data) {
                $response .= $data;
                // Check if we have a complete response
                if (strpos($response, 'SUCCESS:') !== false || 
                    strpos($response, 'ERROR:') !== false ||
                    strpos($response, 'RFID_WRITER_READY') !== false) {
                    break;
                }
            }
            usleep(100000); // 100ms delay
        }
        
        return trim($response);
    }
    
    /**
     * Get available serial ports (platform specific)
     */
    public static function getAvailablePorts() {
        $ports = [];
        
        if (PHP_OS === 'WINNT') {
            // Windows - Check COM ports more thoroughly
            for ($i = 1; $i <= 50; $i++) {
                $port = "COM{$i}";
                // Try to open the port to check if it exists and is accessible
                try {
                    $handle = fopen($port, 'r+b');
                    if ($handle !== false) {
                        fclose($handle);
                        $ports[] = $port;
                    }
                } catch (Exception $e) {
                    // Port doesn't exist or is not accessible
                }
            }
            
            // If no ports found with fopen method, try alternative method
            if (empty($ports)) {
                // Use Windows Management Instrumentation (WMI) if available
                if (function_exists('shell_exec')) {
                    $output = shell_exec('wmic path Win32_SerialPort get DeviceID 2>nul');
                    if ($output) {
                        $lines = explode("\n", trim($output));
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (preg_match('/^COM\d+$/', $line)) {
                                $ports[] = $line;
                            }
                        }
                    }
                }
            }
            
            // If still no ports found, add common COM ports
            if (empty($ports)) {
                $commonPorts = ['COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8'];
                foreach ($commonPorts as $port) {
                    $ports[] = $port;
                }
            }
        } else {
            // Linux/Mac
            $devices = glob('/dev/tty*');
            foreach ($devices as $device) {
                if (strpos($device, 'USB') !== false || strpos($device, 'ACM') !== false) {
                    $ports[] = $device;
                }
            }
            
            // Add common Linux/Mac ports if none found
            if (empty($ports)) {
                $commonPorts = ['/dev/ttyUSB0', '/dev/ttyUSB1', '/dev/ttyACM0', '/dev/ttyACM1'];
                foreach ($commonPorts as $port) {
                    $ports[] = $port;
                }
            }
        }
        
        return array_unique($ports);
    }
}

// Example usage and API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prevent any output before JSON response
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        $data = $_POST['data'] ?? '';
        $block = intval($_POST['block'] ?? 4);
        $port = $_POST['port'] ?? ((PHP_OS === 'WINNT') ? 'COM3' : '/dev/ttyUSB0');
        
        $rfid = new RFIDWriter($port);
        
        if (!$rfid->connect()) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to connect to Arduino'
            ]);
            exit;
        }
        
        switch ($action) {
        case 'write':
            $result = $rfid->writeToCard($data, $block);
            break;
            
        case 'read':
            $result = $rfid->readFromCard($block);
            break;
            
        case 'status':
            $status = $rfid->getStatus();
            $result = [
                'status' => 'success',
                'message' => $status
            ];
            break;
            
        default:
            $result = [
                'status' => 'error',
                'message' => 'Invalid action'
            ];
        }
        
        $rfid->disconnect();
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Exception: ' . $e->getMessage()
        ]);
    }
    exit;
}
?>
