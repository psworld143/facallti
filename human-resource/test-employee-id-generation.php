<?php
require_once '../config/database.php';
require_once 'includes/employee_id_generator.php';

echo "<h2>Employee ID Generation Test</h2>";

// Test 1: Check current faculty qrcode entries
echo "<h3>Current Faculty QR Codes:</h3>";
$query = "SELECT id, first_name, last_name, qrcode FROM faculty WHERE qrcode IS NOT NULL ORDER BY qrcode";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Name</th><th>QR Code</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
        echo "<td>" . $row['qrcode'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No faculty members with QR codes found.</p>";
}

// Test 2: Generate next employee ID
echo "<h3>Next Employee ID Generation:</h3>";
try {
    $next_id = generateEmployeeID($conn);
    echo "<p><strong>Next Employee ID:</strong> " . $next_id . "</p>";
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

// Test 3: Check the query logic
echo "<h3>Query Logic Test:</h3>";
$current_year = date('Y');
$year_pattern = $current_year . '-%';

echo "<p>Current Year: " . $current_year . "</p>";
echo "<p>Year Pattern: " . $year_pattern . "</p>";

$query = "SELECT qrcode FROM faculty 
          WHERE qrcode LIKE ? 
          ORDER BY CAST(SUBSTRING_INDEX(qrcode, '-', -1) AS UNSIGNED) DESC 
          LIMIT 1";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $year_pattern);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $last_employee_id = $row['qrcode'];
    echo "<p><strong>Highest QR Code for " . $current_year . ":</strong> " . $last_employee_id . "</p>";
    
    // Extract the series number and increment it
    $parts = explode('-', $last_employee_id);
    $last_series = (int)$parts[1];
    $new_series = $last_series + 1;
    $formatted_series = str_pad($new_series, 4, '0', STR_PAD_LEFT);
    
    echo "<p><strong>Last Series:</strong> " . $last_series . "</p>";
    echo "<p><strong>New Series:</strong> " . $new_series . "</p>";
    echo "<p><strong>Formatted Series:</strong> " . $formatted_series . "</p>";
    echo "<p><strong>Next Employee ID:</strong> " . $current_year . "-" . $formatted_series . "</p>";
} else {
    echo "<p>No QR codes found for year " . $current_year . ". Starting with 0001.</p>";
    echo "<p><strong>Next Employee ID:</strong> " . $current_year . "-0001</p>";
}

mysqli_close($conn);
?>
