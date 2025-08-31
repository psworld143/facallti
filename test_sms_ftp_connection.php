<?php
// Test SMS FTP Connection
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing SMS FTP Connection</h2>";

// Load FTP configuration
$ftp_accounts = require_once 'config/ftp-accounts.php';
$config = $ftp_accounts['sms'];

echo "<h3>Connection Details:</h3>";
echo "<ul>";
echo "<li><strong>Host:</strong> " . htmlspecialchars($config['host']) . "</li>";
echo "<li><strong>Username:</strong> " . htmlspecialchars($config['username']) . "</li>";
echo "<li><strong>Port:</strong> " . htmlspecialchars($config['port']) . "</li>";
echo "<li><strong>Passive Mode:</strong> " . ($config['passive'] ? 'Yes' : 'No') . "</li>";
echo "<li><strong>SSL:</strong> " . ($config['ssl'] ? 'Yes' : 'No') . "</li>";
echo "</ul>";

// Test connection
echo "<h3>Connection Test:</h3>";

try {
    // Connect to FTP server
    $ftp_conn = ftp_connect($config['host'], $config['port'], $config['timeout']);
    
    if (!$ftp_conn) {
        throw new Exception("Failed to connect to FTP server");
    }
    
    echo "<p style='color: green;'>✅ Connected to FTP server</p>";
    
    // Login
    if (!ftp_login($ftp_conn, $config['username'], $config['password'])) {
        throw new Exception("Failed to login with provided credentials");
    }
    
    echo "<p style='color: green;'>✅ Login successful</p>";
    
    // Set passive mode
    if ($config['passive']) {
        ftp_pasv($ftp_conn, true);
        echo "<p style='color: green;'>✅ Passive mode enabled</p>";
    }
    
    // Get current directory
    $current_dir = ftp_pwd($ftp_conn);
    echo "<p><strong>Current Directory:</strong> " . htmlspecialchars($current_dir) . "</p>";
    
    // List files in current directory
    echo "<h3>Files in Current Directory:</h3>";
    $files = ftp_nlist($ftp_conn, '.');
    
    if ($files === false) {
        echo "<p style='color: red;'>❌ Failed to list files</p>";
    } else {
        $file_count = count($files);
        echo "<p style='color: green;'>✅ Found $file_count items:</p>";
        
        if ($file_count > 0) {
            echo "<ul style='list-style: none; padding: 0;'>";
            foreach ($files as $file) {
                $file_name = basename($file);
                if ($file_name !== '.' && $file_name !== '..') {
                    $is_dir = ftp_size($ftp_conn, $file_name) === -1;
                    $icon = $is_dir ? '📁' : '📄';
                    $type = $is_dir ? 'Directory' : 'File';
                    
                    echo "<li style='margin: 5px 0; padding: 5px; background: #f0f8ff; border-radius: 4px;'>";
                    echo "$icon <strong>" . htmlspecialchars($file_name) . "</strong> ($type)";
                    echo "</li>";
                }
            }
            echo "</ul>";
        } else {
            echo "<p style='color: orange;'>⚠️ Directory appears empty</p>";
        }
    }
    
    // Test directory navigation
    echo "<h3>Directory Navigation Test:</h3>";
    
    // Try to navigate to common subdomain paths
    $test_paths = [
        'public_html',
        'www',
        'htdocs',
        'domains',
        'subdomains'
    ];
    
    foreach ($test_paths as $path) {
        if (@ftp_chdir($ftp_conn, $path)) {
            echo "<p style='color: green;'>✅ Successfully navigated to: $path</p>";
            $new_dir = ftp_pwd($ftp_conn);
            echo "<p><strong>New Directory:</strong> " . htmlspecialchars($new_dir) . "</p>";
            
            // List files in this directory
            $path_files = ftp_nlist($ftp_conn, '.');
            $path_file_count = count(array_filter($path_files, function($f) { 
                return basename($f) !== '.' && basename($f) !== '..'; 
            }));
            echo "<p><strong>Files in $path:</strong> $path_file_count</p>";
            
            // Go back to root
            ftp_chdir($ftp_conn, $current_dir);
            break;
        } else {
            echo "<p style='color: red;'>❌ Could not navigate to: $path</p>";
        }
    }
    
    // Close connection
    ftp_close($ftp_conn);
    echo "<p style='color: green;'>✅ Connection closed successfully</p>";
    
    echo "<h3 style='color: green;'>🎉 SMS FTP Connection Test Successful!</h3>";
    echo "<p>The SMS FTP account is working correctly. You can now use this account in the FTP Manager.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>🔧 Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Check if the FTP server is accessible</li>";
    echo "<li>Verify the username and password</li>";
    echo "<li>Ensure the port is correct (21 for FTP)</li>";
    echo "<li>Check if passive mode is required</li>";
    echo "</ul>";
}

echo "<h3>🔧 Next Steps:</h3>";
echo "<p>If the connection is successful, you can:</p>";
echo "<ul>";
echo "<li>Use the 'sms' account in the FTP Manager</li>";
echo "<li>Navigate to the correct subdomain directory</li>";
echo "<li>Upload and manage files for the SMS subdomain</li>";
echo "</ul>";
?>
