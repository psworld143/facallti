<?php
require_once '../config/database.php';

echo "Testing database connection and departments...\n";

// Check if we can connect to the database
if (!$conn) {
    echo "Database connection failed\n";
    exit;
}

echo "Database connection successful\n";

// Get all departments
$query = "SELECT DISTINCT department FROM faculty WHERE department IS NOT NULL AND department != '' ORDER BY department";
$result = mysqli_query($conn, $query);

if ($result) {
    echo "Departments found:\n";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "- " . $row['department'] . "\n";
    }
} else {
    echo "Error querying departments: " . mysqli_error($conn) . "\n";
}

// Get total faculty count
$count_query = "SELECT COUNT(*) as total FROM faculty WHERE is_active = 1";
$count_result = mysqli_query($conn, $count_query);
if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    echo "Total active faculty: " . $count_row['total'] . "\n";
}

// Get consultation hours count
$ch_query = "SELECT COUNT(*) as total FROM consultation_hours WHERE is_active = 1";
$ch_result = mysqli_query($conn, $ch_query);
if ($ch_result) {
    $ch_row = mysqli_fetch_assoc($ch_result);
    echo "Total consultation hours: " . $ch_row['total'] . "\n";
}

mysqli_close($conn);
?>
