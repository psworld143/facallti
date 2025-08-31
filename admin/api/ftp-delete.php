<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE');
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

function deleteRecursive($ftp_conn, $path) {
    // Try to delete as file first
    if (ftp_delete($ftp_conn, $path)) {
        return true;
    }
    
    // If that fails, try as directory
    $files = ftp_nlist($ftp_conn, $path);
    if ($files) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $full_path = $path . '/' . $file;
            deleteRecursive($ftp_conn, $full_path);
        }
    }
    
    // Remove the directory itself
    return ftp_rmdir($ftp_conn, $path);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $file_path = $input['file'] ?? '';
    $account = $input['account'] ?? 'default';
    $is_directory = $input['is_directory'] ?? false;
    
    if (empty($file_path)) {
        throw new Exception("No file or directory specified for deletion");
    }
    
    // Connect to FTP
    $ftp_conn = connectFTP($account);
    
    $success = false;
    $message = '';
    
    if ($is_directory) {
        $success = deleteRecursive($ftp_conn, $file_path);
        $message = $success ? "Directory deleted successfully" : "Failed to delete directory";
    } else {
        $success = ftp_delete($ftp_conn, $file_path);
        $message = $success ? "File deleted successfully" : "Failed to delete file";
    }
    
    ftp_close($ftp_conn);
    
    if (!$success) {
        throw new Exception($message);
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'deleted_item' => basename($file_path),
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
