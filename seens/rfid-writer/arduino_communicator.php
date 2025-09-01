<?php
/**
 * Simple Arduino Communicator for SEENS RFID System
 * Uses direct file operations and system commands for reliable communication
 */

class ArduinoCommunicator {
    private $port;
    private $handle = null;
    private $isConnected = false;
    private $baudRate = 9600;
    
    public function __construct($port = null) {
        if ($port === null) {
            $port = $this->getDefaultPort();
        }
        $this->port = $port;
    }
    
    /**
     * Get default port based on OS
     */
    private function getDefaultPort() {
        if (PHP_OS === 'WINNT') {
            return 'COM3';
        } else {
            // For macOS/Linux, try to find Arduino port
            $ports = $this->getAvailablePorts();
            foreach ($ports as $port) {
                if (strpos($port, 'usbserial') !== false || 
                    strpos($port, 'usbmodem') !== false) {
                    return $port;
                }
            }
            return '/dev/ttyUSB0';
        }
    }
    
    /**
     * Get available serial ports
     */
    public static function getAvailablePorts() {
        $ports = [];
        
        if (PHP_OS === 'WINNT') {
            // Windows - check COM ports
            for ($i = 1; $i <= 20; $i++) {
                $port = "COM{$i}";
                if (file_exists($port)) {
                    $ports[] = $port;
                }
            }
        } else {
            // macOS/Linux
            $devices = glob('/dev/tty*');
            $cuDevices = glob('/dev/cu*');
            $allDevices = array_merge($devices, $cuDevices);
            
            foreach ($allDevices as $device) {
                if (strpos($device, 'USB') !== false || 
                    strpos($device, 'ACM') !== false || 
                    strpos($device, 'usbserial') !== false ||
                    strpos($device, 'usbmodem') !== false) {
                    $ports[] = $device;
                }
            }
        }
        
        return array_unique($ports);
    }
    
    /**
     * Connect to Arduino
     */
    public function connect() {
        try {
            // Check if port exists
            if (!file_exists($this->port)) {
                error_log("Arduino Communicator: Port {$this->port} does not exist");
                return false;
            }
            
            // Configure port using stty (macOS/Linux)
            if (PHP_OS !== 'WINNT') {
                $this->configurePort();
            }
            
            // Open port for reading and writing
            $this->handle = fopen($this->port, 'r+b');
            if ($this->handle === false) {
                error_log("Arduino Communicator: Failed to open port {$this->port}");
                return false;
            }
            
            // Set non-blocking mode
            stream_set_blocking($this->handle, 0);
            
            // Wait for Arduino to initialize
            sleep(2);
            
            // Test communication
            $this->sendCommand("STATUS");
            $response = $this->readResponse(3);
            
            $this->isConnected = ($response && strpos($response, 'READY') !== false);
            
            if ($this->isConnected) {
                error_log("Arduino Communicator: Successfully connected to {$this->port}");
            } else {
                error_log("Arduino Communicator: Arduino not responding on {$this->port}");
            }
            
            return $this->isConnected;
            
        } catch (Exception $e) {
            error_log("Arduino Communicator Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Configure port settings using stty
     */
    private function configurePort() {
        $sttyCmd = "stty -f {$this->port} {$this->baudRate} cs8 -cstopb -parenb -echo -icanon min 1 time 0";
        exec($sttyCmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            error_log("Arduino Communicator: Failed to configure port with stty");
        }
    }
    
    /**
     * Disconnect from Arduino
     */
    public function disconnect() {
        if ($this->handle) {
            fclose($this->handle);
            $this->handle = null;
        }
        $this->isConnected = false;
    }
    
    /**
     * Send command to Arduino
     */
    public function sendCommand($command) {
        if (!$this->isConnected || !$this->handle) {
            return false;
        }
        
        $message = $command . "\n";
        $result = fwrite($this->handle, $message);
        
        if ($result === false) {
            error_log("Arduino Communicator: Failed to send command: {$command}");
            return false;
        }
        
        return true;
    }
    
    /**
     * Read response from Arduino
     */
    public function readResponse($timeout = 5) {
        if (!$this->handle) {
            return false;
        }
        
        $response = '';
        $startTime = time();
        
        while ((time() - $startTime) < $timeout) {
            $data = fread($this->handle, 128);
            if ($data !== false && $data !== '') {
                $response .= $data;
                if (strpos($response, "\n") !== false) {
                    break;
                }
            }
            usleep(10000); // 10ms delay
        }
        
        return trim($response);
    }
    
    /**
     * Get Arduino status
     */
    public function getStatus() {
        if (!$this->isConnected) {
            return false;
        }
        
        $this->sendCommand("STATUS");
        return $this->readResponse(3);
    }
    
    /**
     * Write data to RFID card
     */
    public function writeToCard($data, $block = 4) {
        if (!$this->isConnected) {
            return false;
        }
        
        $command = "WRITE:{$block}:{$data}";
        $this->sendCommand($command);
        
        $response = $this->readResponse(10); // Increased timeout for card operations
        
        if (strpos($response, 'WRITE_ERROR') !== false) {
            error_log("Arduino Communicator: Write error - " . $response);
            return false;
        }
        
        return ($response && strpos($response, 'WRITE_OK') !== false);
    }
    
    /**
     * Read data from RFID card
     */
    public function readFromCard($block = 4) {
        if (!$this->isConnected) {
            return false;
        }
        
        $command = "READ:{$block}";
        $this->sendCommand($command);
        
        $response = $this->readResponse(10); // Increased timeout for card operations
        
        if (strpos($response, 'READ_ERROR') !== false) {
            error_log("Arduino Communicator: Read error - " . $response);
            return false;
        }
        
        if ($response && strpos($response, 'READ_OK:') === 0) {
            $parts = explode(':', $response);
            return isset($parts[2]) ? $parts[2] : false;
        }
        
        return false;
    }
    
    /**
     * Test basic communication
     */
    public function testCommunication() {
        if (!$this->isConnected) {
            return false;
        }
        
        $this->sendCommand("PING");
        $response = $this->readResponse(2);
        
        return ($response && strpos($response, 'PONG') !== false);
    }
}

// API endpoint for AJAX requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        $data = $_POST['data'] ?? '';
        $block = intval($_POST['block'] ?? 4);
        $port = $_POST['port'] ?? '';
        
        $arduino = new ArduinoCommunicator($port);
        
        switch ($action) {
            case 'status':
                if ($arduino->connect()) {
                    $status = $arduino->getStatus();
                    $arduino->disconnect();
                    
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Connected to Arduino',
                        'arduino_status' => $status
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Failed to connect to Arduino. Check if Arduino is connected and the correct port is selected.'
                    ]);
                }
                break;
                
            case 'write':
                if ($arduino->connect()) {
                    $result = $arduino->writeToCard($data, $block);
                    $arduino->disconnect();
                    
                    if ($result) {
                        echo json_encode([
                            'status' => 'success',
                            'message' => "Data written successfully to block {$block}"
                        ]);
                    } else {
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Failed to write data to card. Make sure card is placed on reader.'
                        ]);
                    }
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Failed to connect to Arduino'
                    ]);
                }
                break;
                
            case 'read':
                if ($arduino->connect()) {
                    $result = $arduino->readFromCard($block);
                    $arduino->disconnect();
                    
                    if ($result !== false) {
                        echo json_encode([
                            'status' => 'success',
                            'message' => "Data read successfully from block {$block}",
                            'data' => $result,
                            'block' => $block
                        ]);
                    } else {
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Failed to read data from card. Make sure card is placed on reader.'
                        ]);
                    }
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Failed to connect to Arduino'
                    ]);
                }
                break;
                
            case 'test':
                if ($arduino->connect()) {
                    $testResult = $arduino->testCommunication();
                    $arduino->disconnect();
                    
                    if ($testResult) {
                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Communication test successful'
                        ]);
                    } else {
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Communication test failed'
                        ]);
                    }
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Failed to connect to Arduino'
                    ]);
                }
                break;
                
            default:
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid action'
                ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Exception: ' . $e->getMessage()
        ]);
    }
}
?>
