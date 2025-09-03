<?php
session_start();
require_once '../../includes/error_handler.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // For now, return a sample PDF or CSV
    // In a real implementation, this would generate an actual report
    
    // Set headers for file download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="training_progress_' . date('Y-m-d') . '.pdf"');
    
    // Create a simple PDF content (this is just a placeholder)
    $content = "Training Progress Report\n";
    $content .= "Generated on: " . date('Y-m-d H:i:s') . "\n";
    $content .= "User: " . $_SESSION['user_name'] ?? 'Unknown' . "\n\n";
    $content .= "Completed Scenarios: 5\n";
    $content .= "Average Score: 85%\n";
    $content .= "Training Hours: 12.5\n";
    $content .= "Certificates Earned: 2\n";
    
    echo $content;
    
} catch (Exception $e) {
    error_log("Error in export-training-progress.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
