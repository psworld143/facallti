<?php
// Check subdomain directory contents
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Checking Directory: /home/seaitedu/home.seait-edu.ph</h2>";

$directory = '/home/seaitedu/home.seait-edu.ph';

// Check if directory exists
if (!is_dir($directory)) {
    echo "<p style='color: red;'>❌ Directory does not exist: $directory</p>";
    exit;
}

echo "<p style='color: green;'>✅ Directory exists: $directory</p>";

// Get directory contents
$files = scandir($directory);

if ($files === false) {
    echo "<p style='color: red;'>❌ Failed to read directory contents</p>";
    exit;
}

// Filter out . and ..
$files = array_filter($files, function($file) {
    return $file !== '.' && $file !== '..';
});

if (empty($files)) {
    echo "<p style='color: orange;'>⚠️ Directory is empty</p>";
} else {
    echo "<p style='color: green;'>✅ Found " . count($files) . " items in directory:</p>";
    
    echo "<ul style='list-style: none; padding: 0;'>";
    foreach ($files as $file) {
        $fullPath = $directory . '/' . $file;
        $isDir = is_dir($fullPath);
        $icon = $isDir ? '📁' : '📄';
        $type = $isDir ? 'Directory' : 'File';
        
        if ($isDir) {
            // Count files in subdirectory
            $subFiles = scandir($fullPath);
            $subFileCount = count(array_filter($subFiles, function($f) { return $f !== '.' && $f !== '..'; }));
            echo "<li style='margin: 5px 0; padding: 5px; background: #f0f8ff; border-radius: 4px;'>";
            echo "$icon <strong>$file</strong> ($type) - $subFileCount items";
            echo "</li>";
        } else {
            $size = filesize($fullPath);
            $sizeFormatted = formatBytes($size);
            echo "<li style='margin: 5px 0; padding: 5px; background: #f0f8ff; border-radius: 4px;'>";
            echo "$icon <strong>$file</strong> ($type) - $sizeFormatted";
            echo "</li>";
        }
    }
    echo "</ul>";
}

// Check permissions
$perms = fileperms($directory);
$permsString = substr(sprintf('%o', $perms), -4);
echo "<p><strong>Directory Permissions:</strong> $permsString</p>";

// Check owner
$owner = posix_getpwuid(fileowner($directory));
echo "<p><strong>Directory Owner:</strong> " . $owner['name'] . "</p>";

// Check if we can read the directory
if (is_readable($directory)) {
    echo "<p style='color: green;'>✅ Directory is readable</p>";
} else {
    echo "<p style='color: red;'>❌ Directory is not readable</p>";
}

function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

echo "<h3>🔧 Next Steps:</h3>";
echo "<p>If files are found in this directory, we can update the FTP Manager configuration to use this path.</p>";
echo "<p>If the directory is empty or doesn't exist, we may need to check other common paths.</p>";
?>
