<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Load FTP credentials
$ftp_accounts = require_once '../../config/ftp-accounts.php';

function connectFTP($account_name = 'default') {
    global $ftp_accounts;
    
    if (!isset($ftp_accounts[$account_name])) {
        throw new Exception("FTP account '$account_name' not found");
    }
    
    $config = $ftp_accounts[$account_name];
    
    $ftp_conn = ftp_connect($config['host'], $config['port'], $config['timeout']);
    
    if (!$ftp_conn) {
        throw new Exception("Could not connect to FTP server: " . $config['host']);
    }
    
    if (!ftp_login($ftp_conn, $config['username'], $config['password'])) {
        ftp_close($ftp_conn);
        throw new Exception("FTP login failed for user: " . $config['username']);
    }
    
    if ($config['passive']) {
        ftp_pasv($ftp_conn, true);
    }
    
    return $ftp_conn;
}

function createDirectoryStructure($ftp_conn, $base_directory, $relative_path) {
    // Split the relative path into directory parts
    $path_parts = explode('/', trim($relative_path, '/'));
    
    // Remove the filename (last part) if it's a file path
    if (count($path_parts) > 1) {
        array_pop($path_parts); // Remove filename
    }
    
    $current_path = $base_directory;
    
    // Create each directory in the path
    foreach ($path_parts as $dir) {
        if (empty($dir)) continue;
        
        // Sanitize directory name
        $dir = preg_replace('/[^a-zA-Z0-9._-]/', '_', $dir);
        if (empty($dir)) continue;
        
        $current_path .= '/' . $dir;
        
        // Check if directory exists
        $current_dir = ftp_pwd($ftp_conn);
        
        // Try to change to the directory to see if it exists
        if (!@ftp_chdir($ftp_conn, $current_path)) {
            // Directory doesn't exist, create it
            if (!ftp_mkdir($ftp_conn, $current_path)) {
                throw new Exception("Failed to create directory: $current_path");
            }
        }
        
        // Go back to base directory for next iteration
        ftp_chdir($ftp_conn, $base_directory);
    }
    
    return $current_path;
}

function ensureDirectoryExists($ftp_conn, $directory) {
    // Change to the directory, creating it if it doesn't exist
    if (!@ftp_chdir($ftp_conn, $directory)) {
        // Try to create the directory
        if (!ftp_mkdir($ftp_conn, $directory)) {
            throw new Exception("Could not create or access directory: $directory");
        }
    }
}

try {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("No file uploaded or upload error occurred");
    }
    
    $directory = $_POST['directory'] ?? '/';
    $account = $_POST['account'] ?? 'default';
    $uploaded_file = $_FILES['file'];
    $relative_path = $_POST['relative_path'] ?? ''; // New parameter for folder structure
    
    // Validate relative path if provided
    if (!empty($relative_path)) {
        // Check for path traversal attempts
        if (strpos($relative_path, '..') !== false || strpos($relative_path, '//') !== false) {
            throw new Exception("Invalid relative path: path traversal not allowed");
        }
        
        // Limit path depth to prevent abuse
        $path_parts = explode('/', trim($relative_path, '/'));
        if (count($path_parts) > 10) {
            throw new Exception("Relative path too deep: maximum 10 levels allowed");
        }
    }
    
    // Validate file
    $max_size = 50 * 1024 * 1024; // 50MB limit
    if ($uploaded_file['size'] > $max_size) {
        throw new Exception("File too large. Maximum size is 50MB");
    }
    
    // Connect to FTP
    $ftp_conn = connectFTP($account);
    
    // Change to base directory
    if (!ftp_chdir($ftp_conn, $directory)) {
        throw new Exception("Could not change to directory: $directory");
    }
    
    $base_directory = ftp_pwd($ftp_conn);
    
    // If we have a relative path, create the directory structure
    if (!empty($relative_path)) {
        createDirectoryStructure($ftp_conn, $base_directory, $relative_path);
        
        // Get the target directory for the file
        $path_parts = explode('/', trim($relative_path, '/'));
        $target_directory = $base_directory;
        
        // Build the target directory path
        for ($i = 0; $i < count($path_parts) - 1; $i++) {
            $target_directory .= '/' . $path_parts[$i];
        }
        
        // Change to the target directory
        if (!ftp_chdir($ftp_conn, $target_directory)) {
            throw new Exception("Could not change to target directory: $target_directory");
        }
        
        $remote_file = end($path_parts); // Use the filename from the path
    } else {
        // Single file upload - use the original filename
        $remote_file = $uploaded_file['name'];
    }
    
    // Upload file
    $local_file = $uploaded_file['tmp_name'];
    
    if (!ftp_put($ftp_conn, $remote_file, $local_file, FTP_BINARY)) {
        throw new Exception("Failed to upload file: " . $remote_file);
    }
    
    ftp_close($ftp_conn);
    
    echo json_encode([
        'success' => true,
        'message' => "File '{$remote_file}' uploaded successfully to " . ftp_pwd($ftp_conn),
        'file_name' => $remote_file,
        'file_size' => $uploaded_file['size'],
        'relative_path' => $relative_path,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
