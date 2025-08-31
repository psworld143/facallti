<?php
// Find the correct subdomain path
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Finding Subdomain Directory Path</h2>";

$ftp_accounts = require_once 'config/ftp-accounts.php';
$config = $ftp_accounts['default'];

$ftp_conn = ftp_connect($config['host'], $config['port'], $config['timeout']);

if (!$ftp_conn || !ftp_login($ftp_conn, $config['username'], $config['password'])) {
    echo "<p style='color: red;'>Connection failed</p>";
    exit;
}

ftp_pasv($ftp_conn, true);

// Get the initial directory
$initial_dir = ftp_pwd($ftp_conn);
echo "<p><strong>FTP Root Directory:</strong> " . htmlspecialchars($initial_dir) . "</p>";

// List what's in the root
echo "<h3>Contents of Root Directory:</h3>";
$root_files = ftp_nlist($ftp_conn, '.');
if ($root_files) {
    echo "<ul>";
    foreach ($root_files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "<li>" . htmlspecialchars($file) . "</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p>Root directory appears empty</p>";
}

// Try to find public_html
echo "<h3>Looking for public_html:</h3>";
if (@ftp_chdir($ftp_conn, 'public_html')) {
    echo "<p style='color: green;'>✓ Found public_html directory</p>";
    $public_html_dir = ftp_pwd($ftp_conn);
    echo "<p><strong>Public HTML Path:</strong> " . htmlspecialchars($public_html_dir) . "</p>";
    
    // List contents of public_html
    $public_files = ftp_nlist($ftp_conn, '.');
    if ($public_files) {
        echo "<p><strong>Contents of public_html:</strong></p>";
        echo "<ul>";
        foreach ($public_files as $file) {
            if ($file !== '.' && $file !== '..') {
                echo "<li>" . htmlspecialchars($file) . "</li>";
            }
        }
        echo "</ul>";
        
        // Look for home.seait-edu.ph in public_html
        if (in_array('home.seait-edu.ph', $public_files)) {
            echo "<p style='color: green; font-weight: bold;'>🎯 Found home.seait-edu.ph in public_html!</p>";
            echo "<p><strong>Correct path:</strong> <code>public_html/home.seait-edu.ph</code></p>";
        }
    }
    
    // Go back to root
    ftp_chdir($ftp_conn, $initial_dir);
} else {
    echo "<p style='color: red;'>❌ public_html directory not found</p>";
}

// Try other common paths
echo "<h3>Testing Other Common Paths:</h3>";
$test_paths = ['www', 'htdocs', 'domains', 'subdomains'];

foreach ($test_paths as $path) {
    if (@ftp_chdir($ftp_conn, $path)) {
        echo "<p style='color: green;'>✓ Found $path directory</p>";
        $current = ftp_pwd($ftp_conn);
        echo "<p><strong>Path:</strong> " . htmlspecialchars($current) . "</p>";
        
        $files = ftp_nlist($ftp_conn, '.');
        if ($files) {
            echo "<ul>";
            foreach (array_slice($files, 0, 5) as $file) {
                if ($file !== '.' && $file !== '..') {
                    echo "<li>" . htmlspecialchars($file) . "</li>";
                }
            }
            echo "</ul>";
        }
        
        // Go back to root
        ftp_chdir($ftp_conn, $initial_dir);
    } else {
        echo "<p style='color: red;'>❌ $path directory not found</p>";
    }
}

ftp_close($ftp_conn);

echo "<h3>🔧 Next Steps:</h3>";
echo "<p>Once we find the correct path, I'll update the FTP manager configuration.</p>";
echo "<p>Common scenarios:</p>";
echo "<ul>";
echo "<li><strong>Addon Domain:</strong> public_html/home.seait-edu.ph</li>";
echo "<li><strong>Subdomain:</strong> public_html/subdomains/home</li>";
echo "<li><strong>Separate Domain:</strong> domains/home.seait-edu.ph</li>";
echo "</ul>";
?>
