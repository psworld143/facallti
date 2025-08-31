<?php
/**
 * Script to apply universal error handling to all user pages in SEAIT
 * Run this script once to update all files
 */

// Define the directories to process
$directories = [
    'faculty/',
    'admin/',
    'students/',
    'human-resource/',
    'heads/',
    'content-creator/',
    'consultation/',
    'IntelliEVal/',
    'pms/',
    'social-media/'
];

// Define files to skip (system files, not user pages)
$skip_files = [
    'includes/',
    'config/',
    'assets/',
    'vendor/',
    'logs/',
    'uploads/',
    'database/',
    'templates/',
    'tailwind/',
    'api/',
    '404.php',
    '505.php',
    'maintenance.php',
    'update_error_handling.php'
];

// Error handler include line
$error_handler_include = "require_once '../includes/error_handler.php';";

// Function to process a file
function processFile($file_path) {
    global $error_handler_include;
    
    // Read file content
    $content = file_get_contents($file_path);
    if ($content === false) {
        echo "Error reading file: $file_path\n";
        return false;
    }
    
    // Check if error handler is already included
    if (strpos($content, 'error_handler.php') !== false) {
        echo "Skipping $file_path - error handler already included\n";
        return true;
    }
    
    // Check if it's a PHP file with session_start
    if (strpos($content, 'session_start()') === false) {
        echo "Skipping $file_path - no session_start found\n";
        return true;
    }
    
    // Find the line after session_start
    $lines = explode("\n", $content);
    $new_lines = [];
    $session_found = false;
    $error_handler_added = false;
    
    foreach ($lines as $line) {
        $new_lines[] = $line;
        
        // Check if this line contains session_start
        if (strpos($line, 'session_start()') !== false && !$session_found) {
            $session_found = true;
            continue;
        }
        
        // Add error handler after session_start and before other includes
        if ($session_found && !$error_handler_added && 
            (strpos($line, 'require_once') !== false || strpos($line, 'include') !== false)) {
            
            // Insert error handler before the first include
            array_splice($new_lines, count($new_lines) - 1, 0, $error_handler_include);
            $error_handler_added = true;
            echo "Added error handler to $file_path\n";
        }
    }
    
    // If session was found but no includes, add error handler after session_start
    if ($session_found && !$error_handler_added) {
        $new_content = implode("\n", $new_lines);
        $new_content = str_replace(
            "session_start();",
            "session_start();\n$error_handler_include",
            $new_content
        );
        
        // Write back to file
        if (file_put_contents($file_path, $new_content) !== false) {
            echo "Added error handler to $file_path\n";
            return true;
        } else {
            echo "Error writing to file: $file_path\n";
            return false;
        }
    }
    
    return true;
}

// Function to recursively process directories
function processDirectory($dir) {
    global $skip_files;
    
    if (!is_dir($dir)) {
        echo "Directory not found: $dir\n";
        return;
    }
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $file_path = $dir . $file;
        
        // Check if file should be skipped
        $skip = false;
        foreach ($skip_files as $skip_pattern) {
            if (strpos($file_path, $skip_pattern) !== false) {
                $skip = true;
                break;
            }
        }
        
        if ($skip) {
            continue;
        }
        
        if (is_dir($file_path)) {
            processDirectory($file_path . '/');
        } elseif (pathinfo($file_path, PATHINFO_EXTENSION) === 'php') {
            processFile($file_path);
        }
    }
}

// Main execution
echo "Starting error handler update for SEAIT system...\n\n";

$total_processed = 0;
$total_updated = 0;

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "Processing directory: $dir\n";
        processDirectory($dir);
        $total_processed++;
    } else {
        echo "Directory not found: $dir\n";
    }
}

echo "\nError handler update completed!\n";
echo "Directories processed: $total_processed\n";
echo "All user pages now have comprehensive error handling.\n";
echo "\nThe universal error handler provides:\n";
echo "- Automatic redirection to 505.php for server errors\n";
echo "- Automatic redirection to 404.php for client errors\n";
echo "- Database connection and query error handling\n";
echo "- Input validation and sanitization\n";
echo "- Rate limiting protection\n";
echo "- CSRF protection\n";
echo "- User activity logging\n";
echo "- Maintenance mode support\n";
echo "- Comprehensive error logging\n";
?>
