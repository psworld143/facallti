<?php
/**
 * Create Default Admin Account
 */

include('database_init.php');

// Check if admin account already exists
$result = $conn->query("SELECT COUNT(*) as count FROM seens_account WHERE username = 'admin'");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Create default admin account
    $default_username = 'root';
    $default_password = password_hash('', PASSWORD_DEFAULT); // No password
    $default_role = 'administrator';
    
    $insert_sql = "INSERT INTO seens_account (username, password, role) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("sss", $default_username, $default_password, $default_role);
    
    if ($stmt->execute()) {
        echo "✅ Default admin account created successfully!\n";
        echo "📝 Credentials:\n";
        echo "   Username: root\n";
        echo "   Password: none\n";
    } else {
        echo "❌ Failed to create admin account: " . $stmt->error . "\n";
    }
    $stmt->close();
} else {
    echo "✅ Admin account already exists.\n";
}

$conn->close();
?>
