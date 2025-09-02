<?php
/**
 * RFID Writing Backend Script for SEENS
 * Handles writing student IDs to RFID cards via the rfid-writer API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include configuration
include('../configuration.php');

// Simple authentication
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$validToken = 'Seait123';

if ($token !== $validToken) {
    echo json_encode([
        'success' => 0,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'write_rfid':
        $studentId = $_POST['student_id'] ?? '';
        
        if (empty($studentId)) {
            echo json_encode([
                'success' => 0,
                'message' => 'Student ID is required'
            ]);
            exit;
        }
        
        // Validate that the student exists in the database
        try {
            $conn = establishConnection();
            if (!$conn) {
                echo json_encode([
                    'success' => 0,
                    'message' => 'Database connection failed'
                ]);
                exit;
            }
            
            $stmt = $conn->prepare("SELECT ss_id_no, ss_photo_location FROM seens_student WHERE ss_id_no = ?");
            $stmt->bind_param("s", $studentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode([
                    'success' => 0,
                    'message' => 'Student not found in database'
                ]);
                exit;
            }
            
            $student = $result->fetch_assoc();
            $stmt->close();
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => 0,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
            exit;
        }
        
        // Call the RFID writer API directly
        $rfidApiPath = __DIR__ . '/../rfid-writer/api.php';
        
        if (!file_exists($rfidApiPath)) {
            echo json_encode([
                'success' => 0,
                'message' => 'RFID Writer API file not found'
            ]);
            exit;
        }
        
        // Set up the request data
        $_POST['action'] = 'write_student_id';
        $_POST['student_id'] = $studentId;
        $_POST['api_key'] = 'seens_rfid_2024';
        
        // Capture output from the RFID API
        ob_start();
        include $rfidApiPath;
        $response = ob_get_clean();
        
        // Parse the JSON response
        $rfidResponse = json_decode($response, true);
        
        if (!$rfidResponse) {
            echo json_encode([
                'success' => 0,
                'message' => 'Invalid response from RFID Writer API: ' . $response
            ]);
            exit;
        }
        
        $rfidResponse = json_decode($response, true);
        
        if (!$rfidResponse) {
            echo json_encode([
                'success' => 0,
                'message' => 'Invalid response from RFID Writer API'
            ]);
            exit;
        }
        
        // Log the RFID writing attempt
        try {
            $logData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'student_id' => $studentId,
                'student_name' => 'Student ID: ' . $studentId, // Since we don't have name field
                'action' => 'rfid_write',
                'status' => $rfidResponse['status'],
                'message' => $rfidResponse['message'] ?? ''
            ];
            
            $logStmt = $conn->prepare("INSERT INTO activity_logs (timestamp, student_id, student_name, action, status, details) VALUES (?, ?, ?, ?, ?, ?)");
            $logStmt->bind_param("ssssss", 
                $logData['timestamp'],
                $logData['student_id'],
                $logData['student_name'],
                $logData['action'],
                $logData['status'],
                $logData['message']
            );
            $logStmt->execute();
            $logStmt->close();
            
        } catch (Exception $e) {
            // Log error but don't fail the operation
            error_log("Failed to log RFID write activity: " . $e->getMessage());
        }
        
        // Return the RFID writer response
        if ($rfidResponse['status'] === 'success') {
            echo json_encode([
                'success' => 1,
                'message' => 'RFID code written successfully to card',
                'student_id' => $studentId,
                'student_name' => 'Student ID: ' . $studentId
            ]);
        } else {
            echo json_encode([
                'success' => 0,
                'message' => 'Failed to write RFID code: ' . ($rfidResponse['message'] ?? 'Unknown error'),
                'student_id' => $studentId
            ]);
        }
        break;
        
    case 'check_rfid_status':
        $studentId = $_POST['student_id'] ?? '';
        
        if (empty($studentId)) {
            echo json_encode([
                'success' => 0,
                'message' => 'Student ID is required'
            ]);
            exit;
        }
        
        // Call the RFID reader API directly
        $rfidApiPath = __DIR__ . '/../rfid-writer/api.php';
        
        if (!file_exists($rfidApiPath)) {
            echo json_encode([
                'success' => 0,
                'message' => 'RFID Writer API file not found'
            ]);
            exit;
        }
        
        // Set up the request data
        $_POST['action'] = 'read_student_id';
        $_POST['api_key'] = 'seens_rfid_2024';
        
        // Capture output from the RFID API
        ob_start();
        include $rfidApiPath;
        $response = ob_get_clean();
        
        // Parse the JSON response
        $rfidResponse = json_decode($response, true);
        
        if ($rfidResponse && $rfidResponse['status'] === 'success') {
            $cardStudentId = trim($rfidResponse['data'] ?? '');
            if ($cardStudentId === $studentId) {
                echo json_encode([
                    'success' => 1,
                    'message' => 'RFID card contains correct student ID',
                    'card_student_id' => $cardStudentId
                ]);
            } else {
                echo json_encode([
                    'success' => 0,
                    'message' => 'RFID card contains different student ID: ' . $cardStudentId
                ]);
            }
        } else {
            echo json_encode([
                'success' => 0,
                'message' => 'Failed to read RFID card: ' . ($rfidResponse['message'] ?? 'Unknown error')
            ]);
        }
        break;
        
    default:
        echo json_encode([
            'success' => 0,
            'message' => 'Invalid action'
        ]);
        break;
}
?>
