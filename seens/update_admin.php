<?php
/**
 * Update Admin Account to Root
 */

include('database_init.php');

// Check if root account exists
$result = $conn->query("SELECT COUNT(*) as count FROM seens_account WHERE username = 'root'");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Check if admin account exists and update it
    $result = $conn->query("SELECT COUNT(*) as count FROM seens_account WHERE username = 'admin'");
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        // Update admin to root
        $update_sql = "UPDATE seens_account SET username = 'root', password = ? WHERE username = 'admin'";
        $stmt = $conn->prepare($update_sql);
        $new_password = password_hash('', PASSWORD_DEFAULT); // No password
        $stmt->bind_param("s", $new_password);
        
        if ($stmt->execute()) {
            echo "✅ Admin account updated to root successfully!\n";
            echo "📝 New credentials:\n";
            echo "   Username: root\n";
            echo "   Password: none\n";
        } else {
            echo "❌ Failed to update admin account: " . $stmt->error . "\n";
        }
        $stmt->close();
    } else {
        // Create new root account
        $insert_sql = "INSERT INTO seens_account (username, password, role) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $username = 'root';
        $password = password_hash('', PASSWORD_DEFAULT); // No password
        $role = 'administrator';
        $stmt->bind_param("sss", $username, $password, $role);
        
        if ($stmt->execute()) {
            echo "✅ Root account created successfully!\n";
            echo "📝 Credentials:\n";
            echo "   Username: root\n";
            echo "   Password: none\n";
        } else {
            echo "❌ Failed to create root account: " . $stmt->error . "\n";
        }
        $stmt->close();
    }
} else {
    echo "✅ Root account already exists.\n";
}

$conn->close();
?>
