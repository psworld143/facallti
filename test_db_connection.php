<?php
// Test database connection
require_once 'config/database.php';

if ($conn) {
    echo "✅ Database connection successful!\n";
    echo "Connected to: " . mysqli_get_host_info($conn) . "\n";
    echo "Server version: " . mysqli_get_server_info($conn) . "\n";
    
    // Test a simple query
    $result = mysqli_query($conn, "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'seait_website'");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "Number of tables in database: " . $row['table_count'] . "\n";
    }
    
    mysqli_close($conn);
} else {
    echo "❌ Database connection failed!\n";
    echo "Error: " . mysqli_connect_error() . "\n";
}
?>

