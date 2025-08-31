<?php
// Check multiple possible subdomain paths
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Checking All Possible Subdomain Paths</h2>";

$possiblePaths = [
    '/home/seaitedu/home.seait-edu.ph',
    '/home/seaitedu/public_html/home.seait-edu.ph',
    '/home/seaitedu/domains/home.seait-edu.ph',
    '/home/seaitedu/subdomains/home',
    '/home/seaitedu/subdomains/home.seait-edu.ph',
    '/home/seaitedu/www/home.seait-edu.ph',
    '/home/seaitedu/htdocs/home.seait-edu.ph',
    '/home/seaitedu/public_html/subdomains/home',
    '/home/seaitedu/public_html/subdomains/home.seait-edu.ph'
];

$foundPaths = [];

foreach ($possiblePaths as $path) {
    echo "<h3>Checking: $path</h3>";
    
    if (is_dir($path)) {
        echo "<p style='color: green;'>✅ Directory exists</p>";
        
        $files = scandir($path);
        $fileCount = count(array_filter($files, function($f) { return $f !== '.' && $f !== '..'; }));
        
        if ($fileCount > 0) {
            echo "<p style='color: green; font-weight: bold;'>🎯 FOUND FILES: $fileCount items</p>";
            $foundPaths[] = $path;
            
            // Show first few files
            $files = array_slice(array_filter($files, function($f) { return $f !== '.' && $f !== '..'; }), 0, 5);
            echo "<ul>";
            foreach ($files as $file) {
                $fullPath = $path . '/' . $file;
                $isDir = is_dir($fullPath) ? '📁' : '📄';
                echo "<li>$isDir $file</li>";
            }
            if ($fileCount > 5) {
                echo "<li>... and " . ($fileCount - 5) . " more files</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: orange;'>⚠️ Directory is empty</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Directory does not exist</p>";
    }
    echo "<hr>";
}

if (!empty($foundPaths)) {
    echo "<h2 style='color: green;'>🎉 Results Summary</h2>";
    echo "<p><strong>Found files in these paths:</strong></p>";
    echo "<ul>";
    foreach ($foundPaths as $path) {
        echo "<li style='color: green; font-weight: bold;'>$path</li>";
    }
    echo "</ul>";
    
    echo "<h3>🔧 Recommended Action:</h3>";
    echo "<p>Update the FTP Manager configuration to use one of these paths:</p>";
    echo "<ul>";
    foreach ($foundPaths as $path) {
        echo "<li><code>$path</code></li>";
    }
    echo "</ul>";
} else {
    echo "<h2 style='color: red;'>❌ No Files Found</h2>";
    echo "<p>No files were found in any of the checked paths. The subdomain files might be in a different location.</p>";
}
?>
