<?php
// Test subdomain FTP access
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Subdomain FTP Directory Test</h2>";

$ftp_accounts = require_once 'config/ftp-accounts.php';
$config = $ftp_accounts['default'];

echo "<h3>Testing Subdomain Access</h3>";
echo "<p><strong>FTP connects to:</strong> /home/seaitedu/</p>";
echo "<p><strong>Looking for subdomain files in:</strong> home.seait-edu.ph</p>";

$ftp_conn = ftp_connect($config['host'], $config['port'], $config['timeout']);

if (!$ftp_conn || !ftp_login($ftp_conn, $config['username'], $config['password'])) {
    echo "<p style='color: red;'>Connection failed</p>";
    exit;
}

ftp_pasv($ftp_conn, true);

// Get the initial directory
$initial_dir = ftp_pwd($ftp_conn);
echo "<p><strong>Initial FTP directory:</strong> " . htmlspecialchars($initial_dir) . "</p>";

// Common subdomain paths in cPanel
$subdomain_paths = [
    'public_html',
    'public_html/home.seait-edu.ph',
    'subdomains/home',
    'subdomains/home/public_html',
    'domains/home.seait-edu.ph',
    'domains/home.seait-edu.ph/public_html',
    'home.seait-edu.ph',
    'www/home.seait-edu.ph'
];

echo "<h4>Testing Subdomain Paths:</h4>";

foreach ($subdomain_paths as $path) {
    echo "<div style='border: 1px solid #ddd; margin: 10px 0; padding: 10px; border-radius: 5px;'>";
    echo "<p><strong>Testing: " . htmlspecialchars($path) . "</strong></p>";
    
    if (@ftp_chdir($ftp_conn, $path)) {
        $current = ftp_pwd($ftp_conn);
        echo "<p style='color: green;'>✓ Successfully accessed: " . htmlspecialchars($current) . "</p>";
        
        $files = @ftp_nlist($ftp_conn, '.');
        if ($files && count($files) > 2) {
            echo "<p style='color: green;'>✓ Found " . count($files) . " items:</p>";
            echo "<ul>";
            foreach (array_slice($files, 0, 8) as $file) {
                if ($file !== '.' && $file !== '..') {
                    $size = @ftp_size($ftp_conn, $file);
                    $size_text = $size > 0 ? " (" . number_format($size) . " bytes)" : "";
                    echo "<li>" . htmlspecialchars($file) . $size_text . "</li>";
                }
            }
            echo "</ul>";
            
            // Check if this looks like a website directory
            $web_files = array_filter($files, function($file) {
                return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['php', 'html', 'htm', 'css', 'js']) 
                       || in_array(strtolower($file), ['index.php', 'index.html', 'wp-config.php', '.htaccess']);
            });
            
            if (!empty($web_files)) {
                echo "<p style='color: blue; font-weight: bold;'>🎯 This looks like a website directory!</p>";
                echo "<p><strong>Recommended path for FTP Manager:</strong> <code>" . htmlspecialchars($path) . "</code></p>";
            }
        } else {
            echo "<p style='color: orange;'>Directory is empty or access denied</p>";
        }
        
        // Go back to root
        ftp_chdir($ftp_conn, $initial_dir);
    } else {
        echo "<p style='color: red;'>❌ Cannot access this path</p>";
    }
    echo "</div>";
}

ftp_close($ftp_conn);

echo "<h3>🔧 How to Create Subdomain FTP in cPanel:</h3>";
echo "<ol>";
echo "<li><strong>Login to cPanel</strong></li>";
echo "<li>Go to <strong>Files → FTP Accounts</strong></li>";
echo "<li>Click <strong>Create FTP Account</strong></li>";
echo "<li>Set <strong>Username:</strong> home (or any name you prefer)</li>";
echo "<li>Set <strong>Directory:</strong> public_html/home.seait-edu.ph (or the correct path found above)</li>";
echo "<li>Set a <strong>Password</strong></li>";
echo "<li>Click <strong>Create FTP Account</strong></li>";
echo "</ol>";

echo "<h3>📝 Alternative: Update Current FTP Configuration</h3>";
echo "<p>If you want to use the current FTP account but start in the subdomain directory, ";
echo "I can update the FTP manager to automatically navigate to the correct subdomain folder.</p>";
?>
