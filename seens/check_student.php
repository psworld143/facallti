<?php
// Check specific student ID
$student_id = "2022-04077";

echo "=== Checking Student ID: $student_id ===\n\n";

include('configuration.php');

// Check database connection
if (!$conn || $conn->connect_error) {
    echo "❌ Database connection failed!\n";
    exit(1);
}

echo "✅ Database connected\n";

// Check if student exists
$query = "SELECT * FROM seens_student WHERE ss_id_no = '$student_id'";
$result = $conn->query($query);

if (!$result) {
    echo "❌ Query failed: " . $conn->error . "\n";
    exit(1);
}

if ($result->num_rows == 0) {
    echo "❌ Student ID '$student_id' NOT FOUND in database!\n";
    echo "\n🔍 Let's check what student IDs are available:\n";
    
    // Show some sample student IDs
    $sample_query = "SELECT ss_id_no, ss_name FROM seens_student LIMIT 10";
    $sample_result = $conn->query($sample_query);
    
    if ($sample_result && $sample_result->num_rows > 0) {
        echo "\n📋 Sample student IDs in database:\n";
        while ($row = $sample_result->fetch_assoc()) {
            echo "   - {$row['ss_id_no']}: {$row['ss_name']}\n";
        }
    } else {
        echo "⚠️  No students found in database at all!\n";
        echo "   The database might be empty.\n";
    }
    
    // Check for similar IDs
    echo "\n🔍 Checking for similar IDs:\n";
    $similar_query = "SELECT ss_id_no, ss_name FROM seens_student WHERE ss_id_no LIKE '%2022%' OR ss_id_no LIKE '%04077%' LIMIT 5";
    $similar_result = $conn->query($similar_query);
    
    if ($similar_result && $similar_result->num_rows > 0) {
        echo "   Found similar IDs:\n";
        while ($row = $similar_result->fetch_assoc()) {
            echo "   - {$row['ss_id_no']}: {$row['ss_name']}\n";
        }
    } else {
        echo "   No similar IDs found\n";
    }
    
} else {
    echo "✅ Student ID '$student_id' FOUND in database!\n";
    
    $student = $result->fetch_assoc();
    echo "\n📋 Student Details:\n";
    echo "   - ID: {$student['ss_id_no']}\n";
    echo "   - Name: {$student['ss_name']}\n";
    echo "   - Photo: {$student['ss_photo_location']}\n";
    
    // Check if photo file exists
    if (!empty($student['ss_photo_location'])) {
        $photo_path = $student['ss_photo_location'];
        if (file_exists($photo_path)) {
            echo "   - Photo file: ✅ EXISTS\n";
        } else {
            echo "   - Photo file: ❌ NOT FOUND at: $photo_path\n";
            echo "     This might cause the scanner to fail.\n";
        }
    } else {
        echo "   - Photo file: ⚠️  NO PHOTO PATH SET\n";
    }
    
    // Test the exact query from check_id.php
    echo "\n🔍 Testing exact query from check_id.php:\n";
    $test_query = "SELECT * FROM seens_student WHERE ss_id_no ='$student_id'";
    echo "   Query: $test_query\n";
    
    $test_result = $conn->query($test_query);
    if ($test_result && $test_result->num_rows > 0) {
        echo "   ✅ Query returns results\n";
        $test_row = $test_result->fetch_assoc();
        echo "   ✅ Photo location: {$test_row['ss_photo_location']}\n";
    } else {
        echo "   ❌ Query returns no results\n";
    }
}

echo "\n=== Check Complete ===\n";
?>
