<?php
include('../configuration.php');

// Set response header
header('Content-Type: application/json');

// Check if source file path is provided
if (!isset($_POST['source_path']) || empty($_POST['source_path'])) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Source file path is required'
    ));
    exit;
}

$source_path = $_POST['source_path'];
$upload_dir = '../big-dump';

// Validate source file exists
if (!file_exists($source_path)) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Source file does not exist: ' . $source_path
    ));
    exit;
}

// Validate file type
$allowed_extensions = array('sql', 'gz');
$file_extension = strtolower(pathinfo($source_path, PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Invalid file type. Only .sql and .gz files are allowed.'
    ));
    exit;
}

// Get file info
$file_size = filesize($source_path);
$original_filename = basename($source_path);

// Check file size (500MB limit)
$max_size = 500 * 1024 * 1024; // 500MB in bytes
if ($file_size > $max_size) {
    echo json_encode(array(
        'success' => false,
        'message' => 'File too large. Maximum size is 500MB. Your file is ' . round($file_size / 1024 / 1024, 2) . 'MB.'
    ));
    exit;
}

// Sanitize filename
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $original_filename);
$filename = time() . '_' . $filename; // Add timestamp to prevent conflicts
$destination = $upload_dir . '/' . $filename;

// Ensure upload directory exists
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(array(
            'success' => false,
            'message' => 'Could not create upload directory'
        ));
        exit;
    }
}

// Check if directory is writable
if (!is_writable($upload_dir)) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Upload directory is not writable'
    ));
    exit;
}

// Copy file
if (copy($source_path, $destination)) {
    echo json_encode(array(
        'success' => true,
        'message' => 'File copied successfully',
        'filename' => $filename,
        'original_name' => $original_filename,
        'size_mb' => round($file_size / 1024 / 1024, 2)
    ));
} else {
    echo json_encode(array(
        'success' => false,
        'message' => 'Failed to copy file. Check file permissions and available disk space.'
    ));
}
?>
