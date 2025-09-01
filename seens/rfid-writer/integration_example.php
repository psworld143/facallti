<?php
/**
 * Integration Example for SEENS System
 * Shows how to integrate RFID writing functionality into the main SEENS system
 */

require_once 'rfid_writer.php';

class SEENS_RFID_Integration {
    private $rfid;
    private $port;
    
    public function __construct($port = null) {
        // Set default port based on platform
        if ($port === null) {
            $port = (PHP_OS === 'WINNT') ? 'COM3' : '/dev/ttyUSB0';
        }
        
        $this->port = $port;
        $this->rfid = new RFIDWriter($port);
    }
    
    /**
     * Write student ID to RFID card when registering a new student
     */
    public function writeStudentID($studentId, $studentName = '') {
        try {
            if (!$this->rfid->connect()) {
                return [
                    'success' => false,
                    'message' => 'Failed to connect to RFID writer',
                    'error_type' => 'connection'
                ];
            }
            
            // Write student ID to block 4 (data block)
            $result = $this->rfid->writeToCard($studentId, 4);
            $this->rfid->disconnect();
            
            if ($result['status'] === 'success') {
                // Log the successful RFID write
                $this->logRFIDWrite($studentId, $studentName, 'write', true);
                
                return [
                    'success' => true,
                    'message' => 'Student ID written to RFID card successfully',
                    'student_id' => $studentId,
                    'block' => 4
                ];
            } else {
                // Log the failed RFID write
                $this->logRFIDWrite($studentId, $studentName, 'write', false, $result['message']);
                
                return [
                    'success' => false,
                    'message' => 'Failed to write to RFID card: ' . $result['message'],
                    'error_type' => 'write_error'
                ];
            }
            
        } catch (Exception $e) {
            $this->logRFIDWrite($studentId, $studentName, 'write', false, $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'RFID write error: ' . $e->getMessage(),
                'error_type' => 'exception'
            ];
        }
    }
    
    /**
     * Read student ID from RFID card for verification
     */
    public function readStudentID() {
        try {
            if (!$this->rfid->connect()) {
                return [
                    'success' => false,
                    'message' => 'Failed to connect to RFID writer',
                    'error_type' => 'connection'
                ];
            }
            
            $result = $this->rfid->readFromCard(4);
            $this->rfid->disconnect();
            
            if ($result['status'] === 'success') {
                return [
                    'success' => true,
                    'message' => 'Student ID read successfully',
                    'student_id' => $result['data'],
                    'block' => $result['block']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to read from RFID card: ' . $result['message'],
                    'error_type' => 'read_error'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'RFID read error: ' . $e->getMessage(),
                'error_type' => 'exception'
            ];
        }
    }
    
    /**
     * Verify if a student ID exists in the database
     */
    public function verifyStudentID($studentId) {
        // This would typically connect to your SEENS database
        // For now, we'll simulate a database check
        
        // Example database query (replace with your actual database connection)
        /*
        $pdo = new PDO("mysql:host=localhost;dbname=seens_db", "username", "password");
        $stmt = $pdo->prepare("SELECT id, name FROM students WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $student ?: false;
        */
        
        // Simulated response for demonstration
        return [
            'id' => 1,
            'student_id' => $studentId,
            'name' => 'John Doe',
            'exists' => true
        ];
    }
    
    /**
     * Log RFID operations for audit purposes
     */
    private function logRFIDWrite($studentId, $studentName, $action, $success, $errorMessage = '') {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'student_id' => $studentId,
            'student_name' => $studentName,
            'action' => $action,
            'success' => $success,
            'error_message' => $errorMessage,
            'port' => $this->port
        ];
        
        // Log to file
        $logEntry = json_encode($logData) . "\n";
        file_put_contents('../logs/rfid_operations.log', $logEntry, FILE_APPEND | LOCK_EX);
        
        // You could also log to database
        /*
        $pdo = new PDO("mysql:host=localhost;dbname=seens_db", "username", "password");
        $stmt = $pdo->prepare("INSERT INTO rfid_logs (timestamp, student_id, student_name, action, success, error_message, port) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $logData['timestamp'],
            $logData['student_id'],
            $logData['student_name'],
            $logData['action'],
            $logData['success'],
            $logData['error_message'],
            $logData['port']
        ]);
        */
    }
    
    /**
     * Get available serial ports
     */
    public function getAvailablePorts() {
        return RFIDWriter::getAvailablePorts();
    }
    
    /**
     * Test RFID writer connection
     */
    public function testConnection() {
        try {
            $connected = $this->rfid->connect();
            $this->rfid->disconnect();
            
            return [
                'success' => $connected,
                'message' => $connected ? 'Connection successful' : 'Connection failed',
                'port' => $this->port
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test error: ' . $e->getMessage(),
                'port' => $this->port
            ];
        }
    }
}

// Example usage in your SEENS system:

/*
// 1. When registering a new student
function registerNewStudent($studentId, $studentName, $otherData) {
    // First, save student to database
    $studentSaved = saveStudentToDatabase($studentId, $studentName, $otherData);
    
    if ($studentSaved) {
        // Then write to RFID card
        $rfid = new SEENS_RFID_Integration();
        $rfidResult = $rfid->writeStudentID($studentId, $studentName);
        
        if ($rfidResult['success']) {
            return [
                'success' => true,
                'message' => 'Student registered and RFID card written successfully',
                'student_id' => $studentId
            ];
        } else {
            // Student saved but RFID failed - you might want to handle this case
            return [
                'success' => false,
                'message' => 'Student saved but RFID write failed: ' . $rfidResult['message'],
                'student_id' => $studentId
            ];
        }
    } else {
        return [
            'success' => false,
            'message' => 'Failed to save student to database'
        ];
    }
}

// 2. When a student scans their card for entry/exit
function processStudentEntry($rfidData) {
    $rfid = new SEENS_RFID_Integration();
    $readResult = $rfid->readStudentID();
    
    if ($readResult['success']) {
        $studentId = $readResult['student_id'];
        $student = $rfid->verifyStudentID($studentId);
        
        if ($student) {
            // Process entry/exit logic
            $entryResult = processEntryExit($student['id']);
            return [
                'success' => true,
                'message' => 'Entry/Exit processed successfully',
                'student' => $student
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Student ID not found in database'
            ];
        }
    } else {
        return [
            'success' => false,
            'message' => 'Failed to read RFID card: ' . $readResult['message']
        ];
    }
}

// 3. Admin function to test RFID writer
function testRFIDWriter() {
    $rfid = new SEENS_RFID_Integration();
    
    // Test connection
    $connectionTest = $rfid->testConnection();
    if (!$connectionTest['success']) {
        return $connectionTest;
    }
    
    // Test read/write cycle
    $testStudentId = 'TEST-001';
    $writeResult = $rfid->writeStudentID($testStudentId, 'Test Student');
    
    if ($writeResult['success']) {
        $readResult = $rfid->readStudentID();
        
        if ($readResult['success'] && $readResult['student_id'] === $testStudentId) {
            return [
                'success' => true,
                'message' => 'RFID writer test completed successfully',
                'test_data' => $testStudentId
            ];
        } else {
            return [
                'success' => false,
                'message' => 'RFID read test failed: ' . $readResult['message']
            ];
        }
    } else {
        return [
            'success' => false,
            'message' => 'RFID write test failed: ' . $writeResult['message']
        ];
    }
}
*/

// Example AJAX endpoint for integration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $rfid = new SEENS_RFID_Integration();
    
    switch ($_POST['action']) {
        case 'write_student':
            $studentId = $_POST['student_id'] ?? '';
            $studentName = $_POST['student_name'] ?? '';
            
            if (empty($studentId)) {
                echo json_encode(['success' => false, 'message' => 'Student ID is required']);
                exit;
            }
            
            $result = $rfid->writeStudentID($studentId, $studentName);
            echo json_encode($result);
            break;
            
        case 'read_student':
            $result = $rfid->readStudentID();
            echo json_encode($result);
            break;
            
        case 'test_connection':
            $result = $rfid->testConnection();
            echo json_encode($result);
            break;
            
        case 'get_ports':
            $ports = $rfid->getAvailablePorts();
            echo json_encode(['success' => true, 'ports' => $ports]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}
?>
