<?php
include('../configuration.php');

// Set response header
header('Content-Type: application/json');

// Check if filename was provided
if (!isset($_POST['filename']) || empty($_POST['filename'])) {
    echo json_encode(array(
        'success' => false,
        'message' => 'No filename provided'
    ));
    exit;
}

$upload_dir = '../big-dump';
$filename = $_POST['filename'];

// Sanitize filename to prevent directory traversal
$filename = basename($filename);
$file_path = $upload_dir . '/' . $filename;

// Validate file exists and is in the correct directory
if (!file_exists($file_path)) {
    echo json_encode(array(
        'success' => false,
        'message' => 'File not found'
    ));
    exit;
}

// Validate file type
$allowed_extensions = array('sql', 'gz');
$file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Invalid file type'
    ));
    exit;
}

// Prevent deletion of bigdump.php
if ($filename === 'bigdump.php') {
    echo json_encode(array(
        'success' => false,
        'message' => 'Cannot delete system files'
    ));
    exit;
}

// Delete the file
if (unlink($file_path)) {
    echo json_encode(array(
        'success' => true,
        'message' => 'File deleted successfully'
    ));
} else {
    echo json_encode(array(
        'success' => false,
        'message' => 'Failed to delete file'
    ));
}
?>
