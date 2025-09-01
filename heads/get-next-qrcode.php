<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get the current year
    $current_year = date('Y');
    
    // Query to find the highest QR code number for the current year
    $query = "SELECT qrcode FROM faculty 
              WHERE qrcode LIKE ? 
              ORDER BY CAST(SUBSTRING(qrcode, 6) AS UNSIGNED) DESC 
              LIMIT 1";
    
    $pattern = $current_year . '-%';
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "s", $pattern);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error executing statement: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    if ($row && $row['qrcode']) {
        // Extract the number part and increment
        $last_qrcode = $row['qrcode'];
        $number_part = (int)substr($last_qrcode, 5); // Remove "YYYY-" prefix
        $next_number = $number_part + 1;
    } else {
        // No existing QR codes for this year, start with 1
        $next_number = 1;
    }
    
    // Format the next QR code with leading zeros (4 digits)
    $next_qrcode = $current_year . '-' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
    
    echo json_encode([
        'success' => true,
        'next_qrcode' => $next_qrcode,
        'current_year' => $current_year
    ]);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Get Next QR Code Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
?>
