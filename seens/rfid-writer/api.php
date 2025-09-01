<?php
/**
 * RFID Writer API for SEENS System
 * Simple API endpoint for writing student IDs to RFID cards
 */

require_once 'rfid_writer.php';

header('Content-Type: application/json');

// Enable CORS for local development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Simple authentication (you can enhance this)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? '';
$validApiKey = 'seens_rfid_2024'; // Change this to a secure key

if ($apiKey !== $validApiKey) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'write_student_id':
        $studentId = $_POST['student_id'] ?? '';
        $port = $_POST['port'] ?? ((PHP_OS === 'WINNT') ? 'COM3' : '/dev/ttyUSB0');
        $block = intval($_POST['block'] ?? 4);
        
        if (empty($studentId)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Student ID is required'
            ]);
            exit;
        }
        
        $rfid = new RFIDWriter($port);
        
        if (!$rfid->connect()) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to connect to Arduino'
            ]);
            exit;
        }
        
        $result = $rfid->writeToCard($studentId, $block);
        $rfid->disconnect();
        
        if ($result['status'] === 'success') {
            // Log the successful write operation
            $logData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'student_id' => $studentId,
                'block' => $block,
                'action' => 'write',
                'status' => 'success'
            ];
            
            // You can save this to a log file or database
            error_log("RFID Write Success: " . json_encode($logData));
        }
        
        echo json_encode($result);
        break;
        
    case 'read_student_id':
        $port = $_POST['port'] ?? ((PHP_OS === 'WINNT') ? 'COM3' : '/dev/ttyUSB0');
        $block = intval($_POST['block'] ?? 4);
        
        $rfid = new RFIDWriter($port);
        
        if (!$rfid->connect()) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to connect to Arduino'
            ]);
            exit;
        }
        
        $result = $rfid->readFromCard($block);
        $rfid->disconnect();
        
        echo json_encode($result);
        break;
        
    case 'get_ports':
        $ports = RFIDWriter::getAvailablePorts();
        echo json_encode([
            'status' => 'success',
            'ports' => $ports
        ]);
        break;
        
    case 'test_connection':
        $port = $_POST['port'] ?? ((PHP_OS === 'WINNT') ? 'COM3' : '/dev/ttyUSB0');
        
        $rfid = new RFIDWriter($port);
        $connected = $rfid->connect();
        $rfid->disconnect();
        
        echo json_encode([
            'status' => $connected ? 'success' : 'error',
            'message' => $connected ? 'Connection successful' : 'Connection failed',
            'port' => $port
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid action. Available actions: write_student_id, read_student_id, get_ports, test_connection'
        ]);
}

// Example usage:
/*
// Write student ID to RFID card
curl -X POST http://localhost/seens/rfid-writer/api.php \
  -H "X-API-Key: seens_rfid_2024" \
  -d "action=write_student_id&student_id=2021-0001&port=/dev/ttyUSB0&block=4"

// Read student ID from RFID card
curl -X POST http://localhost/seens/rfid-writer/api.php \
  -H "X-API-Key: seens_rfid_2024" \
  -d "action=read_student_id&port=/dev/ttyUSB0&block=4"

// Get available ports
curl -X GET http://localhost/seens/rfid-writer/api.php?action=get_ports \
  -H "X-API-Key: seens_rfid_2024"

// Test connection
curl -X POST http://localhost/seens/rfid-writer/api.php \
  -H "X-API-Key: seens_rfid_2024" \
  -d "action=test_connection&port=/dev/ttyUSB0"
*/
?>
