<?php
// Database Seeding Execution Script
// This script will populate the database with sample data

require_once '../includes/config.php';

echo "<h2>Hotel PMS Database Seeding</h2>";
echo "<p>Starting database seeding process...</p>";

try {
    // Read the seed data SQL file
    $seedFile = __DIR__ . '/seed_data.sql';
    
    if (!file_exists($seedFile)) {
        throw new Exception("Seed data file not found: $seedFile");
    }
    
    $sql = file_get_contents($seedFile);
    
    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    echo "<h3>Executing SQL Statements:</h3>";
    echo "<ul>";
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip comments and empty lines
        }
        
        try {
            $pdo->exec($statement);
            $successCount++;
            echo "<li style='color: green;'>✓ " . substr($statement, 0, 50) . "...</li>";
        } catch (PDOException $e) {
            $errorCount++;
            $errors[] = $e->getMessage();
            echo "<li style='color: red;'>✗ Error: " . $e->getMessage() . "</li>";
        }
    }
    
    echo "</ul>";
    
    echo "<h3>Seeding Results:</h3>";
    echo "<p><strong>Successful statements:</strong> $successCount</p>";
    echo "<p><strong>Failed statements:</strong> $errorCount</p>";
    
    if ($errorCount > 0) {
        echo "<h3>Errors:</h3>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li style='color: red;'>$error</li>";
        }
        echo "</ul>";
    }
    
    // Verify the data was inserted correctly
    echo "<h3>Data Verification:</h3>";
    
    $tables = [
        'users' => 'SELECT COUNT(*) as count FROM users',
        'rooms' => 'SELECT COUNT(*) as count FROM rooms',
        'guests' => 'SELECT COUNT(*) as count FROM guests',
        'reservations' => 'SELECT COUNT(*) as count FROM reservations',
        'billing' => 'SELECT COUNT(*) as count FROM billing',
        'check_ins' => 'SELECT COUNT(*) as count FROM check_ins',
        'additional_services' => 'SELECT COUNT(*) as count FROM additional_services',
        'service_charges' => 'SELECT COUNT(*) as count FROM service_charges',
        'housekeeping_tasks' => 'SELECT COUNT(*) as count FROM housekeeping_tasks',
        'maintenance_requests' => 'SELECT COUNT(*) as count FROM maintenance_requests',
        'guest_feedback' => 'SELECT COUNT(*) as count FROM guest_feedback',
        'activity_logs' => 'SELECT COUNT(*) as count FROM activity_logs',
        'notifications' => 'SELECT COUNT(*) as count FROM notifications',
        'inventory_categories' => 'SELECT COUNT(*) as count FROM inventory_categories',
        'inventory_items' => 'SELECT COUNT(*) as count FROM inventory_items',
        'inventory_transactions' => 'SELECT COUNT(*) as count FROM inventory_transactions',
        'bills' => 'SELECT COUNT(*) as count FROM bills',
        'bill_items' => 'SELECT COUNT(*) as count FROM bill_items',
        'payments' => 'SELECT COUNT(*) as count FROM payments',
        'discounts' => 'SELECT COUNT(*) as count FROM discounts',
        'vouchers' => 'SELECT COUNT(*) as count FROM vouchers',
        'voucher_usage' => 'SELECT COUNT(*) as count FROM voucher_usage',
        'loyalty_points' => 'SELECT COUNT(*) as count FROM loyalty_points',
        'training_scenarios' => 'SELECT COUNT(*) as count FROM training_scenarios',
        'scenario_questions' => 'SELECT COUNT(*) as count FROM scenario_questions',
        'question_options' => 'SELECT COUNT(*) as count FROM question_options',
        'customer_service_scenarios' => 'SELECT COUNT(*) as count FROM customer_service_scenarios',
        'problem_scenarios' => 'SELECT COUNT(*) as count FROM problem_scenarios',
        'training_attempts' => 'SELECT COUNT(*) as count FROM training_attempts',
        'training_certificates' => 'SELECT COUNT(*) as count FROM training_certificates'
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Table</th><th>Record Count</th></tr>";
    
    foreach ($tables as $tableName => $query) {
        try {
            $stmt = $pdo->query($query);
            $count = $stmt->fetch()['count'];
            echo "<tr><td>$tableName</td><td>$count</td></tr>";
        } catch (PDOException $e) {
            echo "<tr><td>$tableName</td><td style='color: red;'>Error: " . $e->getMessage() . "</td></tr>";
        }
    }
    
    echo "</table>";
    
    // Display sample login credentials
    echo "<h3>Sample Login Credentials:</h3>";
    echo "<p><strong>Front Desk:</strong></p>";
    echo "<ul>";
    echo "<li>Username: frontdesk1, Password: password</li>";
    echo "<li>Username: frontdesk2, Password: password</li>";
    echo "</ul>";
    
    echo "<p><strong>Housekeeping:</strong></p>";
    echo "<ul>";
    echo "<li>Username: housekeeping1, Password: password</li>";
    echo "<li>Username: housekeeping2, Password: password</li>";
    echo "</ul>";
    
    echo "<p><strong>Management:</strong></p>";
    echo "<ul>";
    echo "<li>Username: manager1, Password: password</li>";
    echo "<li>Username: manager2, Password: password</li>";
    echo "</ul>";
    
    if ($errorCount === 0) {
        echo "<h2 style='color: green;'>✅ Database seeding completed successfully!</h2>";
        echo "<p>The database has been populated with comprehensive sample data including:</p>";
        echo "<ul>";
        echo "<li>6 users (front desk, housekeeping, management)</li>";
        echo "<li>10 rooms (standard, deluxe, suite, presidential)</li>";
        echo "<li>10 guests (including VIP guests)</li>";
        echo "<li>10 reservations (various statuses)</li>";
        echo "<li>Complete billing and payment records</li>";
        echo "<li>Housekeeping and maintenance tasks</li>";
        echo "<li>Guest feedback and activity logs</li>";
        echo "<li>Inventory management data</li>";
        echo "<li>Training scenarios and certificates</li>";
        echo "</ul>";
    } else {
        echo "<h2 style='color: orange;'>⚠️ Database seeding completed with errors</h2>";
        echo "<p>Some data may not have been inserted correctly. Please check the errors above.</p>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Database seeding failed</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='../modules/front-desk/index.php'>Go to Front Desk Dashboard</a></p>";
echo "<p><a href='../login.php'>Go to Login Page</a></p>";
?>
