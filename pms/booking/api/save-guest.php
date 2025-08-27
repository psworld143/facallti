<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has front desk access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['front_desk', 'manager'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input data');
    }
    
    // Validate required fields
    if (empty($input['first_name'])) {
        throw new Exception('First name is required');
    }
    
    if (empty($input['last_name'])) {
        throw new Exception('Last name is required');
    }
    
    if (empty($input['phone'])) {
        throw new Exception('Phone number is required');
    }
    
    if (empty($input['id_type'])) {
        throw new Exception('ID type is required');
    }
    
    if (empty($input['id_number'])) {
        throw new Exception('ID number is required');
    }
    
    // Save guest
    $result = saveGuest($input);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error saving guest: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Save guest information
 */
function saveGuest($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $guest_id = $data['guest_id'] ?? null;
        $is_update = !empty($guest_id);
        
        if ($is_update) {
            // Update existing guest
            $stmt = $pdo->prepare("
                UPDATE guests SET 
                    first_name = ?, last_name = ?, email = ?, phone = ?, 
                    date_of_birth = ?, nationality = ?, address = ?, 
                    id_type = ?, id_number = ?, is_vip = ?, 
                    preferences = ?, service_notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'] ?? '',
                $data['phone'],
                $data['date_of_birth'] ?? null,
                $data['nationality'] ?? '',
                $data['address'] ?? '',
                $data['id_type'],
                $data['id_number'],
                $data['is_vip'] ?? false,
                $data['preferences'] ?? '',
                $data['service_notes'] ?? '',
                $guest_id
            ]);
            
            $action = 'guest_updated';
            $message = 'Guest updated successfully';
            
        } else {
            // Check if guest already exists
            $stmt = $pdo->prepare("
                SELECT id FROM guests 
                WHERE (email = ? AND email != '') OR (phone = ? AND phone != '') OR (id_number = ? AND id_number != '')
            ");
            $stmt->execute([
                $data['email'] ?? '',
                $data['phone'],
                $data['id_number']
            ]);
            $existing_guest = $stmt->fetch();
            
            if ($existing_guest) {
                throw new Exception('A guest with this email, phone, or ID number already exists');
            }
            
            // Create new guest
            $stmt = $pdo->prepare("
                INSERT INTO guests (
                    first_name, last_name, email, phone, date_of_birth, 
                    nationality, address, id_type, id_number, is_vip, 
                    preferences, service_notes, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'] ?? '',
                $data['phone'],
                $data['date_of_birth'] ?? null,
                $data['nationality'] ?? '',
                $data['address'] ?? '',
                $data['id_type'],
                $data['id_number'],
                $data['is_vip'] ?? false,
                $data['preferences'] ?? '',
                $data['service_notes'] ?? ''
            ]);
            
            $guest_id = $pdo->lastInsertId();
            $action = 'guest_created';
            $message = 'Guest created successfully';
        }
        
        // Log activity
        logActivity($_SESSION['user_id'], $action, "{$action}: {$data['first_name']} {$data['last_name']}");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => $message,
            'guest_id' => $guest_id
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error saving guest: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
