<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Load FTP credentials
$ftp_accounts = require_once '../../config/ftp-accounts.php';

function connectFTP($account_name = 'default') {
    global $ftp_accounts;
    
    if (!isset($ftp_accounts[$account_name])) {
        throw new Exception("FTP account '$account_name' not found");
    }
    
    $config = $ftp_accounts[$account_name];
    
    // Create FTP connection
    $ftp_conn = ftp_connect($config['host'], $config['port'], $config['timeout']);
    
    if (!$ftp_conn) {
        throw new Exception("Could not connect to FTP server: " . $config['host']);
    }
    
    // Login to FTP server
    if (!ftp_login($ftp_conn, $config['username'], $config['password'])) {
        ftp_close($ftp_conn);
        throw new Exception("FTP login failed for user: " . $config['username']);
    }
    
    // Set passive mode if configured
    if ($config['passive']) {
        ftp_pasv($ftp_conn, true);
    }
    
    return $ftp_conn;
}

function browseFTPDirectory($ftp_conn, $directory = '/', $config = null) {
    // Change to directory
    if (!ftp_chdir($ftp_conn, $directory)) {
        throw new Exception("Could not change to directory: $directory");
    }
    
    // Get current directory
    $current_dir = ftp_pwd($ftp_conn);
    
    // Get directory listing
    $files = ftp_nlist($ftp_conn, '.');
    $detailed_files = [];
    
    if ($files) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $file_path = $current_dir . '/' . $file;
            $size = ftp_size($ftp_conn, $file);
            $modified = ftp_mdtm($ftp_conn, $file);
            
            // Try to determine if it's a directory
            $is_dir = false;
            $original_dir = ftp_pwd($ftp_conn);
            if (@ftp_chdir($ftp_conn, $file)) {
                $is_dir = true;
                ftp_chdir($ftp_conn, $original_dir);
            }
            
            $detailed_files[] = [
                'name' => $file,
                'path' => $file_path,
                'size' => $size > 0 ? $size : 0,
                'modified' => $modified > 0 ? date('Y-m-d H:i:s', $modified) : 'Unknown',
                'is_directory' => $is_dir,
                'type' => $is_dir ? 'directory' : pathinfo($file, PATHINFO_EXTENSION),
                'permissions' => 'Unknown' // FTP doesn't easily provide permissions
            ];
        }
    }
    
    // Sort: directories first, then files alphabetically
    usort($detailed_files, function($a, $b) {
        if ($a['is_directory'] && !$b['is_directory']) return -1;
        if (!$a['is_directory'] && $b['is_directory']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    
    return [
        'current_directory' => $current_dir,
        'files' => $detailed_files,
        'total_files' => count($detailed_files)
    ];
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $directory = $input['directory'] ?? $_GET['directory'] ?? '/';
    $account = $input['account'] ?? $_GET['account'] ?? 'default';
    
    $ftp_conn = connectFTP($account);
    $result = browseFTPDirectory($ftp_conn, $directory);
    ftp_close($ftp_conn);
    
    echo json_encode([
        'success' => true,
        'data' => $result,
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
