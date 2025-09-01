<?php
/**
 * Export Local Database Schema (Structure Only)
 * This script exports the database structure without any data
 */

// Include configuration
include('configuration.php');

// Output file name
$output_file = 'seens_schema_' . date('Y-m-d_H-i-s') . '.sql';

// mysqldump command to export only structure (no data)
if ($socket) {
    $command = "mysqldump --host={$host} --user={$username} --password={$password} --socket={$socket} --no-data --routines --triggers --single-transaction {$dbname} > {$output_file}";
} else {
    $command = "mysqldump --host={$host} --user={$username} --password={$password} --no-data --routines --triggers --single-transaction {$dbname} > {$output_file}";
}

echo "Exporting database schema for: {$dbname}\n";
echo "Output file: {$output_file}\n";
echo "Command: {$command}\n\n";

// Execute the command
$result = system($command, $return_code);

if ($return_code === 0) {
    echo "✅ Database schema exported successfully!\n";
    echo "📁 File saved as: {$output_file}\n";
    
    // Get file size
    if (file_exists($output_file)) {
        $file_size = filesize($output_file);
        echo "📊 File size: " . number_format($file_size) . " bytes (" . round($file_size / 1024, 2) . " KB)\n";
    }
} else {
    echo "❌ Error exporting database schema. Return code: {$return_code}\n";
    echo "Please check your database connection and permissions.\n";
}

echo "\n--- Export Complete ---\n";
?>
