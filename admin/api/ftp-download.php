<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

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

try {
    $file_path = $_GET['file'] ?? '';
    $account = $_GET['account'] ?? 'default';
    
    if (empty($file_path)) {
        throw new Exception("No file specified for download");
    }
    
    // Connect to FTP
    $ftp_conn = connectFTP($account);
    
    // Get file size
    $file_size = ftp_size($ftp_conn, $file_path);
    
    // Create temporary file
    $temp_file = tempnam(sys_get_temp_dir(), 'ftp_download_');
    
    // Download file to temporary location
    if (!ftp_get($ftp_conn, $temp_file, $file_path, FTP_BINARY)) {
        unlink($temp_file);
        throw new Exception("Failed to download file: " . $file_path);
    }
    
    ftp_close($ftp_conn);
    
    // Get file info
    $file_name = basename($file_path);
    $mime_type = mime_content_type($temp_file) ?: 'application/octet-stream';
    
    // Set headers for download
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . filesize($temp_file));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Output file
    readfile($temp_file);
    
    // Clean up
    unlink($temp_file);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
