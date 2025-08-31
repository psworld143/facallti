<?php
// Debug FTP directory listing issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>FTP Directory Debug</h2>";

$ftp_accounts = require_once 'config/ftp-accounts.php';
$config = $ftp_accounts['default'];

echo "<h3>Testing Different Directory Listing Methods</h3>";

$ftp_conn = ftp_connect($config['host'], $config['port'], $config['timeout']);

if (!$ftp_conn) {
    echo "<p style='color: red;'>Connection failed</p>";
    exit;
}

if (!ftp_login($ftp_conn, $config['username'], $config['password'])) {
    echo "<p style='color: red;'>Login failed</p>";
    ftp_close($ftp_conn);
    exit;
}

echo "<p style='color: green;'>✓ Connected and logged in</p>";

// Get current directory
$current_dir = ftp_pwd($ftp_conn);
echo "<p><strong>Current directory:</strong> " . htmlspecialchars($current_dir) . "</p>";

// Test different passive modes
echo "<h4>Testing Passive Mode OFF:</h4>";
ftp_pasv($ftp_conn, false);
$files_active = @ftp_nlist($ftp_conn, '.');
if ($files_active) {
    echo "<p style='color: green;'>✓ Found " . count($files_active) . " items with active mode</p>";
    echo "<ul>";
    foreach (array_slice($files_active, 0, 10) as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "<li>" . htmlspecialchars($file) . "</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>❌ No files found with active mode</p>";
}

echo "<h4>Testing Passive Mode ON:</h4>";
ftp_pasv($ftp_conn, true);
$files_passive = @ftp_nlist($ftp_conn, '.');
if ($files_passive) {
    echo "<p style='color: green;'>✓ Found " . count($files_passive) . " items with passive mode</p>";
    echo "<ul>";
    foreach (array_slice($files_passive, 0, 10) as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "<li>" . htmlspecialchars($file) . "</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>❌ No files found with passive mode</p>";
}

// Try different directory paths
echo "<h4>Testing Different Directory Paths:</h4>";

$test_paths = [
    '.',
    '/',
    '/public_html',
    '/www',
    '/htdocs',
    '/domains',
    '/domains/home.seait-edu.ph',
    '/domains/home.seait-edu.ph/public_html',
    '/home.seait-edu.ph',
    '/home.seait-edu.ph/public_html',
    '/subdomains/home',
    '/subdomains/home/public_html',
    'public_html',
    'www',
    'htdocs',
    'domains',
    'domains/home.seait-edu.ph',
    'home.seait-edu.ph'
];

foreach ($test_paths as $path) {
    echo "<p><strong>Testing path: " . htmlspecialchars($path) . "</strong></p>";
    
    // Try to change to directory first
    $original_dir = ftp_pwd($ftp_conn);
    if (@ftp_chdir($ftp_conn, $path)) {
        $new_dir = ftp_pwd($ftp_conn);
        echo "<p style='color: blue;'>  → Changed to: " . htmlspecialchars($new_dir) . "</p>";
        
        $files = @ftp_nlist($ftp_conn, '.');
        if ($files && count($files) > 2) { // More than just . and ..
            echo "<p style='color: green;'>  ✓ Found " . count($files) . " items!</p>";
            echo "<ul>";
            foreach (array_slice($files, 0, 5) as $file) {
                if ($file !== '.' && $file !== '..') {
                    $size = @ftp_size($ftp_conn, $file);
                    $size_text = $size > 0 ? " (" . number_format($size) . " bytes)" : "";
                    echo "<li>" . htmlspecialchars($file) . $size_text . "</li>";
                }
            }
            echo "</ul>";
        } else {
            echo "<p style='color: orange;'>  → Directory appears empty</p>";
        }
        
        // Go back to original directory
        ftp_chdir($ftp_conn, $original_dir);
    } else {
        echo "<p style='color: red;'>  ❌ Cannot access this path</p>";
    }
}

// Try raw directory listing
echo "<h4>Raw FTP Commands:</h4>";
ftp_pasv($ftp_conn, true);

// Try LIST command
echo "<p><strong>Trying LIST command:</strong></p>";
$list_output = @ftp_rawlist($ftp_conn, '.');
if ($list_output) {
    echo "<p style='color: green;'>✓ LIST command successful:</p>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
    foreach (array_slice($list_output, 0, 10) as $line) {
        echo htmlspecialchars($line) . "\n";
    }
    echo "</pre>";
} else {
    echo "<p style='color: red;'>❌ LIST command failed</p>";
}

// Try NLST command with different options
echo "<p><strong>Trying NLST with -la:</strong></p>";
$nlst_output = @ftp_nlist($ftp_conn, '-la');
if ($nlst_output) {
    echo "<p style='color: green;'>✓ NLST -la successful:</p>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
    foreach (array_slice($nlst_output, 0, 10) as $line) {
        echo htmlspecialchars($line) . "\n";
    }
    echo "</pre>";
} else {
    echo "<p style='color: red;'>❌ NLST -la failed</p>";
}

ftp_close($ftp_conn);

echo "<h3>Recommendations:</h3>";
echo "<ul>";
echo "<li>If any method above shows files, we can update the FTP manager to use that method</li>";
echo "<li>Check if your website files are in a subdirectory like 'public_html'</li>";
echo "<li>Verify FTP user has proper permissions to list directory contents</li>";
echo "<li>Some servers require specific FTP modes (active vs passive)</li>";
echo "</ul>";
?>
