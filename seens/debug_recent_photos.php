<?php
include('configuration.php');

echo "<h2>Database Debug Information</h2>";

// Check if tables exist
echo "<h3>Table Check:</h3>";
$tables = ['logs', 'seens_logs', 'seens_student', 'student'];
foreach($tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if(mysqli_num_rows($result) > 0) {
        echo "✓ Table '$table' exists<br>";
    } else {
        echo "✗ Table '$table' does not exist<br>";
    }
}

// Check logs table structure
echo "<h3>Logs Table Structure:</h3>";
$result = mysqli_query($conn, "DESCRIBE logs");
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        echo "Column: " . $row['Field'] . " - Type: " . $row['Type'] . "<br>";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "<br>";
}

// Check seens_logs table structure
echo "<h3>Seens_Logs Table Structure:</h3>";
$result = mysqli_query($conn, "DESCRIBE seens_logs");
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        echo "Column: " . $row['Field'] . " - Type: " . $row['Type'] . "<br>";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "<br>";
}

// Check seens_student table structure
echo "<h3>Seens_Student Table Structure:</h3>";
$result = mysqli_query($conn, "DESCRIBE seens_student");
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        echo "Column: " . $row['Field'] . " - Type: " . $row['Type'] . "<br>";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "<br>";
}

// Check recent logs
echo "<h3>Recent Logs (Last 10):</h3>";
$result = mysqli_query($conn, "SELECT * FROM logs ORDER BY date_added DESC LIMIT 10");
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        echo "QR: " . $row['qr_code'] . " - Date: " . $row['date_added'] . "<br>";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "<br>";
}

// Check recent seens_logs
echo "<h3>Recent Seens_Logs (Last 10):</h3>";
$result = mysqli_query($conn, "SELECT * FROM seens_logs ORDER BY date_added DESC LIMIT 10");
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        echo "QR: " . $row['qr_code'] . " - Date: " . $row['date_added'] . "<br>";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "<br>";
}

// Check recent photos query
echo "<h3>Recent Photos Query Test:</h3>";
$result = mysqli_query($conn, "SELECT logs.qr_code, logs.date_added, seens_student.ss_photo_location 
                               FROM logs 
                               LEFT JOIN seens_student ON seens_student.ss_id_no = logs.qr_code 
                               ORDER BY logs.date_added DESC LIMIT 4");
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        echo "QR: " . $row['qr_code'] . " - Photo: " . ($row['ss_photo_location'] ? 'Yes' : 'No') . " - Date: " . $row['date_added'] . "<br>";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "<br>";
}

// Check if there are any students with photos
echo "<h3>Students with Photos:</h3>";
$result = mysqli_query($conn, "SELECT ss_id_no, ss_photo_location FROM seens_student WHERE ss_photo_location IS NOT NULL AND ss_photo_location != '' LIMIT 5");
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        echo "ID: " . $row['ss_id_no'] . " - Has Photo: " . (strlen($row['ss_photo_location']) > 10 ? 'Yes' : 'No') . "<br>";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "<br>";
}
?>
