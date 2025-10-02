<?php
// Start output buffering to catch any unexpected output
ob_start();

// Suppress any warnings or notices that might interfere with JSON output
error_reporting(E_ERROR | E_PARSE);

session_start();
require_once 'config/database.php';

// Set JSON header for CKEditor response
header('Content-Type: application/json');

// Clear any output that might have been generated
ob_clean();

// Debug logging
error_log("Upload attempt - Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET'));
error_log("Upload attempt - Files: " . print_r($_FILES, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("Upload failed - User not logged in. Session data: " . print_r($_SESSION, true));
    http_response_code(401);
    echo json_encode(['error' => ['message' => 'Please log in to upload images. Your session may have expired.']]);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['upload']) || $_FILES['upload']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'No file uploaded or upload error']]);
    exit();
}

$file = $_FILES['upload'];
$file_name = $file['name'];
$file_tmp = $file['tmp_name'];
$file_size = $file['size'];
$file_error = $file['error'];

// Validate file size (max 2MB to match PHP config)
$max_size = 2 * 1024 * 1024; // 2MB
if ($file_size > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'File size too large. Maximum size is 2MB.']]);
    exit();
}

// Validate file type
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$file_type = mime_content_type($file_tmp);

if (!in_array($file_type, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.']]);
    exit();
}

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/ckeditor/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
$unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
$upload_path = $upload_dir . $unique_filename;

// Move uploaded file
if (move_uploaded_file($file_tmp, $upload_path)) {
    // Return success response for CKEditor with correct project path
    $file_url = '/seait/' . $upload_path;
    
    // Log successful upload for debugging
    error_log("Image uploaded successfully: " . $file_url);
    
    $response = ['url' => $file_url];
    error_log("Sending response: " . json_encode($response));
    
    echo json_encode($response);
} else {
    // Log upload failure for debugging
    error_log("Failed to upload image: " . $file_tmp . " to " . $upload_path);
    
    http_response_code(500);
    echo json_encode(['error' => ['message' => 'Failed to upload file']]);
}

// End output buffering and clean up
ob_end_flush();
?>
