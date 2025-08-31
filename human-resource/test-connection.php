<?php
// Test database connection
require_once '../config/database.php';

echo "<h1>Database Connection Test</h1>";

if ($conn) {
    echo "✅ Database connected successfully<br>";
    echo "Database: " . mysqli_get_dbname($conn) . "<br>";
    
    // Test faculty query
    $query = "SELECT COUNT(*) as count FROM faculty";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "✅ Faculty table accessible. Total records: " . $row['count'] . "<br>";
    } else {
        echo "❌ Faculty table query failed: " . mysqli_error($conn) . "<br>";
    }
    
    // Test faculty_details query
    $query = "SELECT COUNT(*) as count FROM faculty_details";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "✅ Faculty_details table accessible. Total records: " . $row['count'] . "<br>";
    } else {
        echo "❌ Faculty_details table query failed: " . mysqli_error($conn) . "<br>";
    }
    
} else {
    echo "❌ Database connection failed<br>";
}

// Test includes
echo "<h2>Include Files Test</h2>";

if (file_exists('../includes/functions.php')) {
    echo "✅ functions.php exists<br>";
} else {
    echo "❌ functions.php missing<br>";
}

if (file_exists('../includes/id_encryption.php')) {
    echo "✅ id_encryption.php exists<br>";
} else {
    echo "❌ id_encryption.php missing<br>";
}

if (file_exists('includes/employee_id_generator.php')) {
    echo "✅ employee_id_generator.php exists<br>";
} else {
    echo "❌ employee_id_generator.php missing<br>";
}

if (file_exists('includes/header.php')) {
    echo "✅ header.php exists<br>";
} else {
    echo "❌ header.php missing<br>";
}

// Test encryption
echo "<h2>Encryption Test</h2>";
require_once '../includes/id_encryption.php';

$test_id = 8;
$encrypted = encrypt_id($test_id);
$decrypted = safe_decrypt_id($encrypted);

echo "Original ID: $test_id<br>";
echo "Encrypted: $encrypted<br>";
echo "Decrypted: $decrypted<br>";

if ($test_id == $decrypted) {
    echo "✅ Encryption/Decryption working correctly<br>";
} else {
    echo "❌ Encryption/Decryption failed<br>";
}
?>
