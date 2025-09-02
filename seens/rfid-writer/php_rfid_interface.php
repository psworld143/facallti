<?php
/**
 * PHP RFID Interface
 * Communicates with Python RFID API server instead of directly with Arduino
 */

class PHPRFIDInterface {
    private $api_base_url;
    private $is_connected = false;
    private $current_port = null;
    
    // Static property to maintain connection state across instances
    private static $global_connection = null;
    private static $global_port = null;
    
    public function __construct($api_base_url = 'http://localhost:5001') {
        $this->api_base_url = rtrim($api_base_url, '/');
    }
    
    /**
     * Make HTTP request to Python API
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->api_base_url . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen(json_encode($data))
                ]);
            }
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $error
            ];
        }
        
        if ($http_code >= 200 && $http_code < 300) {
            $decoded = json_decode($response, true);
            if ($decoded === null) {
                return [
                    'success' => false,
                    'error' => 'Invalid JSON response: ' . $response
                ];
            }
            return $decoded;
        } else {
            return [
                'success' => false,
                'error' => 'HTTP Error ' . $http_code . ': ' . $response
            ];
        }
    }
    
    /**
     * Get available serial ports
     */
    public function getAvailablePorts() {
        $result = $this->makeRequest('/api/ports');
        
        if ($result['success']) {
            return $result['ports'];
        } else {
            return [];
        }
    }
    
    /**
     * Connect to Arduino via Python API
     */
    public function connect($port) {
        $data = ['port' => $port];
        $result = $this->makeRequest('/api/connect', 'POST', $data);
        
        if ($result['success']) {
            $this->is_connected = true;
            $this->current_port = $port;
            // Update global state
            self::$global_connection = true;
            self::$global_port = $port;
            return true;
        } else {
            $this->is_connected = false;
            $this->current_port = null;
            // Update global state
            self::$global_connection = false;
            self::$global_port = null;
            return false;
        }
    }
    
    /**
     * Disconnect from Arduino
     */
    public function disconnect() {
        $result = $this->makeRequest('/api/disconnect', 'POST');
        
        if ($result['success']) {
            $this->is_connected = false;
            $this->current_port = null;
            return true;
        }
        return false;
    }
    
    /**
     * Test connection to Arduino
     */
    public function testConnection() {
        if (!$this->is_connected) {
            return false;
        }
        
        $result = $this->makeRequest('/api/test', 'POST');
        return $result['success'];
    }
    
    /**
     * Get connection status
     */
    public function getStatus() {
        $result = $this->makeRequest('/api/status');
        return $result;
    }
    
    /**
     * Read UID from RFID card
     */
    public function readUID() {
        if (!$this->isConnected()) {
            return [
                'success' => false,
                'error' => 'Not connected to Arduino'
            ];
        }
        
        return $this->makeRequest('/api/read_uid', 'POST');
    }
    
    /**
     * Read data from specific block on RFID card
     */
    public function readFromCard($block_number) {
        if (!$this->isConnected()) {
            return [
                'success' => false,
                'error' => 'Not connected to Arduino'
            ];
        }
        
        $data = ['block_number' => $block_number];
        return $this->makeRequest('/api/read', 'POST', $data);
    }
    
    /**
     * Write data to specific block on RFID card
     */
    public function writeToCard($block_number, $data) {
        if (!$this->isConnected()) {
            return [
                'success' => false,
                'error' => 'Not connected to Arduino'
            ];
        }
        
        $post_data = [
            'block_number' => $block_number,
            'data' => $data
        ];
        
        return $this->makeRequest('/api/write', 'POST', $post_data);
    }
    
    /**
     * Check if connected
     */
    public function isConnected() {
        // Always check the actual Python API connection status
        $status = $this->getStatus();
        return isset($status['connected']) && $status['connected'] === true;
    }
    
    /**
     * Get current port
     */
    public function getCurrentPort() {
        // Return either instance or global port
        return $this->current_port ?: self::$global_port;
    }
    
    /**
     * Check if Python API is running
     */
    public function checkAPIAvailability() {
        $result = $this->makeRequest('/health');
        return isset($result['status']) && $result['status'] === 'healthy';
    }
}

// API endpoint for AJAX calls
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $interface = new PHPRFIDInterface('http://localhost:5001');
    $action = $_POST['action'];
    $response = [];
    
    // For connect action, we need to check if already connected first
    if ($action === 'connect') {
        // Check current status first
        $current_status = $interface->getStatus();
        if ($current_status['connected']) {
            $response = [
                'success' => true,
                'message' => "Already connected to " . $current_status['port'],
                'port' => $current_status['port']
            ];
            echo json_encode($response);
            exit;
        }
    }
    
    try {
        switch ($action) {
            case 'ports':
                $response = [
                    'success' => true,
                    'ports' => $interface->getAvailablePorts()
                ];
                break;
                
            case 'connect':
                if (!isset($_POST['port'])) {
                    $response = ['success' => false, 'error' => 'Port is required'];
                } else {
                    $port = $_POST['port'];
                    if ($interface->connect($port)) {
                        $response = [
                            'success' => true,
                            'message' => "Connected to $port",
                            'port' => $port
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'error' => "Failed to connect to $port"
                        ];
                    }
                }
                break;
                
            case 'disconnect':
                if ($interface->disconnect()) {
                    $response = [
                        'success' => true,
                        'message' => 'Disconnected from Arduino'
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'error' => 'Failed to disconnect'
                    ];
                }
                break;
                
            case 'test':
                if ($interface->testConnection()) {
                    $response = [
                        'success' => true,
                        'message' => 'Arduino is responding'
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'error' => 'Arduino is not responding'
                    ];
                }
                break;
                
            case 'status':
                $response = $interface->getStatus();
                break;
                
            case 'read_uid':
                $result = $interface->readUID();
                $response = $result;
                break;
                
            case 'read':
                if (!isset($_POST['block_number'])) {
                    $response = ['success' => false, 'error' => 'Block number is required'];
                } else {
                    $block_number = $_POST['block_number'];
                    $result = $interface->readFromCard($block_number);
                    $response = $result;
                }
                break;
                
            case 'write':
                if (!isset($_POST['block_number']) || !isset($_POST['data'])) {
                    $response = ['success' => false, 'error' => 'Block number and data are required'];
                } else {
                    $block_number = $_POST['block_number'];
                    $data = $_POST['data'];
                    $result = $interface->writeToCard($block_number, $data);
                    $response = $result;
                }
                break;
                
            case 'check_api':
                if ($interface->checkAPIAvailability()) {
                    $response = [
                        'success' => true,
                        'message' => 'Python API is running'
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'error' => 'Python API is not responding'
                    ];
                }
                break;
                
            default:
                $response = ['success' => false, 'error' => 'Unknown action'];
        }
        
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'error' => 'Exception: ' . $e->getMessage()
        ];
    }
    
    echo json_encode($response);
    exit;
}
?>
