<?php
include('configuration.php');
$conn = establishConnection();

if ($conn && !$conn->connect_error) {
    echo "Connection successful\n";
    
    // Test query
    $result = $conn->query("SELECT COUNT(*) as count FROM seens_student");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "Student count: " . $row['count'] . "\n";
    } else {
        echo "Query failed: " . $conn->error . "\n";
    }
} else {
    echo "Connection failed: " . ($conn ? $conn->connect_error : 'null connection') . "\n";
}
?>
