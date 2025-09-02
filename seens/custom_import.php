<?php
include('../configuration.php');

// Set unlimited execution time and memory
set_time_limit(0);
ini_set('memory_limit', '512M');

class CustomSQLImporter {
    private $conn;
    private $filename;
    private $log = [];
    
    public function __construct($conn, $filename) {
        $this->conn = $conn;
        $this->filename = $filename;
    }
    
    public function import() {
        $filepath = dirname(__FILE__) . '/' . $this->filename;
        
        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'File not found: ' . $this->filename];
        }
        
        $content = file_get_contents($filepath);
        if ($content === false) {
            return ['success' => false, 'message' => 'Cannot read file: ' . $this->filename];
        }
        
        // Split content into individual statements
        $statements = $this->parseSQLStatements($content);
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) continue;
            
            // Skip comments
            if (preg_match('/^(--|#|\/\*)/', $statement)) {
                continue;
            }
            
            // Handle CREATE TABLE statements
            if (preg_match('/^CREATE\s+TABLE/i', $statement)) {
                $result = $this->handleCreateTable($statement);
                if ($result['success']) {
                    $success_count++;
                    $this->log[] = "✅ " . $result['message'];
                } else {
                    $error_count++;
                    $this->log[] = "❌ " . $result['message'];
                }
                continue;
            }
            
            // Handle INSERT statements
            if (preg_match('/^INSERT/i', $statement)) {
                $result = $this->handleInsert($statement);
                if ($result['success']) {
                    $success_count++;
                    $this->log[] = "✅ " . $result['message'];
                } else {
                    $error_count++;
                    $this->log[] = "❌ " . $result['message'];
                }
                continue;
            }
            
            // Skip other statements for now
            $this->log[] = "⚠️ Skipped: " . substr($statement, 0, 50) . "...";
        }
        
        return [
            'success' => $error_count === 0,
            'message' => "Import completed. Success: $success_count, Errors: $error_count",
            'log' => $this->log
        ];
    }
    
    private function parseSQLStatements($content) {
        // Remove comments
        $content = preg_replace('/--.*$/m', '', $content);
        $content = preg_replace('/#.*$/m', '', $content);
        $content = preg_replace('/\/\*.*?\*\//s', '', $content);
        
        // Split by semicolon, but be careful with semicolons in strings
        $statements = [];
        $current_statement = '';
        $in_string = false;
        $string_char = '';
        
        for ($i = 0; $i < strlen($content); $i++) {
            $char = $content[$i];
            
            if (!$in_string && ($char === "'" || $char === '"')) {
                $in_string = true;
                $string_char = $char;
            } elseif ($in_string && $char === $string_char) {
                // Check for escaped quotes
                if ($i > 0 && $content[$i-1] !== '\\') {
                    $in_string = false;
                }
            }
            
            if (!$in_string && $char === ';') {
                $statements[] = trim($current_statement);
                $current_statement = '';
            } else {
                $current_statement .= $char;
            }
        }
        
        // Add the last statement if it's not empty
        if (trim($current_statement) !== '') {
            $statements[] = trim($current_statement);
        }
        
        return $statements;
    }
    
    private function handleCreateTable($statement) {
        // Extract table name
        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
            $table_name = $matches[1];
            
            // Check if table already exists
            $check = mysqli_query($this->conn, "SHOW TABLES LIKE '$table_name'");
            if (mysqli_num_rows($check) > 0) {
                return ['success' => true, 'message' => "Table '$table_name' already exists, skipping"];
            }
            
            // Execute CREATE TABLE
            $result = mysqli_query($this->conn, $statement);
            if ($result) {
                return ['success' => true, 'message' => "Created table '$table_name'"];
            } else {
                return ['success' => false, 'message' => "Failed to create table '$table_name': " . mysqli_error($this->conn)];
            }
        }
        
        return ['success' => false, 'message' => 'Could not extract table name from CREATE TABLE statement'];
    }
    
    private function handleInsert($statement) {
        // Extract table name
        if (preg_match('/INSERT\s+(?:INTO\s+)?`?(\w+)`?/i', $statement, $matches)) {
            $table_name = $matches[1];
            
            // For student table, convert to INSERT ... ON DUPLICATE KEY UPDATE
            if ($table_name === 'student') {
                $statement = $this->convertToUpsert($statement);
            }
            
            $result = mysqli_query($this->conn, $statement);
            if ($result) {
                $affected_rows = mysqli_affected_rows($this->conn);
                return ['success' => true, 'message' => "Inserted into '$table_name' ($affected_rows rows affected)"];
            } else {
                return ['success' => false, 'message' => "Failed to insert into '$table_name': " . mysqli_error($this->conn)];
            }
        }
        
        return ['success' => false, 'message' => 'Could not extract table name from INSERT statement'];
    }
    
    private function convertToUpsert($statement) {
        // Remove semicolon
        $statement = rtrim($statement, ';');
        
        // Add ON DUPLICATE KEY UPDATE clause for student table
        $statement .= " ON DUPLICATE KEY UPDATE 
            ss_photo_location = VALUES(ss_photo_location),
            ss_date_added = CURRENT_TIMESTAMP";
        
        // Add semicolon back
        $statement .= ';';
        
        return $statement;
    }
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'] ?? '';
    
    if (empty($filename)) {
        echo json_encode(['success' => false, 'message' => 'No filename provided']);
        exit;
    }
    
    // Validate filename
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
        echo json_encode(['success' => false, 'message' => 'Invalid filename']);
        exit;
    }
    
    $importer = new CustomSQLImporter($conn, $filename);
    $result = $importer->import();
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// HTML interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom SQL Importer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-black min-h-screen text-white">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-blue-400 mb-2">
                    <i class="fas fa-database mr-2"></i>Custom SQL Importer
                </h1>
                <p class="text-gray-400">Advanced SQL file import with proper table handling</p>
            </div>
            
            <div class="bg-gray-800 rounded-2xl shadow-2xl border border-gray-700 p-6 mb-6">
                <h2 class="text-xl font-semibold text-white mb-4">
                    <i class="fas fa-upload mr-2"></i>Import SQL File
                </h2>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Select SQL File:</label>
                    <select id="sqlFileSelect" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Choose a file...</option>
                    </select>
                </div>
                
                <button id="startImport" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-play mr-2"></i>Start Import
                </button>
            </div>
            
            <div id="importProgress" class="bg-gray-800 rounded-2xl shadow-2xl border border-gray-700 p-6 hidden">
                <h3 class="text-lg font-semibold text-white mb-4">
                    <i class="fas fa-cog fa-spin mr-2"></i>Import Progress
                </h3>
                <div id="importStatus" class="text-gray-300 mb-4">Preparing import...</div>
                <div id="importLog" class="bg-gray-900 rounded-lg p-4 max-h-96 overflow-y-auto text-sm font-mono">
                    <div class="text-gray-400">Import log will appear here...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            loadFileList();
            
            $('#startImport').on('click', function() {
                const filename = $('#sqlFileSelect').val();
                if (!filename) {
                    alert('Please select a file to import');
                    return;
                }
                
                if (confirm('Are you sure you want to import ' + filename + '? This may take a while.')) {
                    startImport(filename);
                }
            });
        });
        
        function loadFileList() {
            $.ajax({
                url: '../backend_scripts/get_sql_files.php',
                method: 'GET',
                success: function(response) {
                    $('#sqlFileSelect').html('<option value="">Choose a file...</option>');
                    
                    // Parse the HTML response to extract file names
                    const tempDiv = $('<div>').html(response);
                    tempDiv.find('h3').each(function() {
                        const filename = $(this).text();
                        $('#sqlFileSelect').append(`<option value="${filename}">${filename}</option>`);
                    });
                },
                error: function() {
                    $('#sqlFileSelect').html('<option value="">Error loading files</option>');
                }
            });
        }
        
        function startImport(filename) {
            $('#importProgress').removeClass('hidden');
            $('#importStatus').text('Starting import...');
            $('#importLog').html('<div class="text-blue-400">Starting import of ' + filename + '...</div>');
            
            $.ajax({
                url: 'custom_import.php',
                method: 'POST',
                data: { filename: filename },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        
                        if (result.success) {
                            $('#importStatus').html('<span class="text-green-400">✅ ' + result.message + '</span>');
                        } else {
                            $('#importStatus').html('<span class="text-red-400">❌ ' + result.message + '</span>');
                        }
                        
                        // Display log entries
                        if (result.log && result.log.length > 0) {
                            let logHtml = '';
                            result.log.forEach(function(entry) {
                                logHtml += '<div class="mb-1">' + entry + '</div>';
                            });
                            $('#importLog').html(logHtml);
                        }
                        
                    } catch (e) {
                        $('#importStatus').html('<span class="text-red-400">❌ Error parsing response</span>');
                        $('#importLog').html('<div class="text-red-400">Raw response: ' + response + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#importStatus').html('<span class="text-red-400">❌ Import failed: ' + error + '</span>');
                    $('#importLog').html('<div class="text-red-400">Network error occurred</div>');
                }
            });
        }
    </script>
</body>
</html>
