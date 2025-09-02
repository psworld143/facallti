<?php
// Check the exact structure of seens_student table
echo "=== Checking seens_student Table Structure ===\n\n";

include('configuration.php');

// Check database connection
if (!$conn || $conn->connect_error) {
    echo "❌ Database connection failed!\n";
    exit(1);
}

echo "✅ Database connected\n";

// Check table structure
$structure_query = "DESCRIBE seens_student";
$structure_result = $conn->query($structure_query);

if (!$structure_result) {
    echo "❌ Failed to get table structure: " . $conn->error . "\n";
    exit(1);
}

echo "📋 Table Structure:\n";
while ($row = $structure_result->fetch_assoc()) {
    echo "   " . $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . " - " . $row['Key'] . " - " . $row['Default'] . "\n";
}

// Now check the specific student record
$student_id = "2022-04077";
echo "\n=== Checking Student ID: $student_id ===\n";

$query = "SELECT * FROM seens_student WHERE ss_id_no = '$student_id'";
$result = $conn->query($query);

if (!$result) {
    echo "❌ Query failed: " . $conn->error . "\n";
    exit(1);
}

if ($result->num_rows == 0) {
    echo "❌ Student ID '$student_id' NOT FOUND in database!\n";
} else {
    echo "✅ Student ID '$student_id' FOUND!\n";
    $row = $result->fetch_assoc();
    
    echo "📋 Student Details:\n";
    foreach ($row as $column => $value) {
        echo "   $column: $value\n";
    }
    
    // Check if photo path exists
    if (isset($row['ss_photo_location'])) {
        $photo_path = $row['ss_photo_location'];
        echo "\n📸 Photo Path: $photo_path\n";
        
        if (empty($photo_path) || $photo_path == 'null' || $photo_path == 'NULL') {
            echo "❌ Photo path is empty or null - this is causing Access Denied!\n";
        } else {
            echo "✅ Photo path exists\n";
            
            // Check if file actually exists
            if (file_exists($photo_path)) {
                echo "✅ Photo file exists on disk\n";
            } else {
                echo "❌ Photo file does not exist on disk - this is causing Access Denied!\n";
            }
        }
    } else {
        echo "❌ ss_photo_location column not found!\n";
    }
}

$conn->close();
?>
