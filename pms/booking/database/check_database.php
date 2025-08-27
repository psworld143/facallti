<?php
// Database Check Script
// This script will check which database the system is using and test the connection

echo "<h2>Hotel PMS Database Configuration Check</h2>";

// Check configuration file
echo "<h3>Configuration File Analysis:</h3>";
$configFile = '../includes/config.php';

if (file_exists($configFile)) {
    echo "<p>✅ Configuration file found: <code>$configFile</code></p>";
    
    // Read and parse the config file
    $configContent = file_get_contents($configFile);
    
    // Extract database configuration
    preg_match("/define\('DB_HOST',\s*'([^']+)'\);/", $configContent, $hostMatch);
    preg_match("/define\('DB_NAME',\s*'([^']+)'\);/", $configContent, $nameMatch);
    preg_match("/define\('DB_USER',\s*'([^']+)'\);/", $configContent, $userMatch);
    preg_match("/define\('DB_PASS',\s*'([^']+)'\);/", $configContent, $passMatch);
    
    $dbHost = $hostMatch[1] ?? 'Not found';
    $dbName = $nameMatch[1] ?? 'Not found';
    $dbUser = $userMatch[1] ?? 'Not found';
    $dbPass = $passMatch[1] ?? 'Not found';
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Setting</th><th>Value</th></tr>";
    echo "<tr><td>DB_HOST</td><td>$dbHost</td></tr>";
    echo "<tr><td>DB_NAME</td><td><strong>$dbName</strong></td></tr>";
    echo "<tr><td>DB_USER</td><td>$dbUser</td></tr>";
    echo "<tr><td>DB_PASS</td><td>" . (empty($dbPass) ? '(empty)' : '***') . "</td></tr>";
    echo "</table>";
    
} else {
    echo "<p>❌ Configuration file not found: <code>$configFile</code></p>";
}

// Check schema file
echo "<h3>Schema File Analysis:</h3>";
$schemaFile = 'schema.sql';

if (file_exists($schemaFile)) {
    echo "<p>✅ Schema file found: <code>$schemaFile</code></p>";
    
    $schemaContent = file_get_contents($schemaFile);
    
    // Extract database name from schema
    preg_match("/CREATE DATABASE IF NOT EXISTS ([^;]+);/", $schemaContent, $schemaDbMatch);
    preg_match("/USE ([^;]+);/", $schemaContent, $useDbMatch);
    
    $schemaDbName = trim($schemaDbMatch[1] ?? 'Not found');
    $useDbName = trim($useDbMatch[1] ?? 'Not found');
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Schema Setting</th><th>Value</th></tr>";
    echo "<tr><td>CREATE DATABASE</td><td><strong>$schemaDbName</strong></td></tr>";
    echo "<tr><td>USE DATABASE</td><td><strong>$useDbName</strong></td></tr>";
    echo "</table>";
    
} else {
    echo "<p>❌ Schema file not found: <code>$schemaFile</code></p>";
}

// Test database connection
echo "<h3>Database Connection Test:</h3>";

try {
    // Include the config file to get the connection
    require_once '../includes/config.php';
    
    echo "<p>✅ Database connection successful!</p>";
    
    // Get current database name
    $stmt = $pdo->query("SELECT DATABASE() as current_database");
    $currentDb = $stmt->fetch()['current_database'];
    
    echo "<p><strong>Currently connected to database:</strong> <code>$currentDb</code></p>";
    
    // Check if the database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE '$currentDb'");
    $dbExists = $stmt->rowCount() > 0;
    
    if ($dbExists) {
        echo "<p>✅ Database <code>$currentDb</code> exists</p>";
        
        // Check tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<p><strong>Tables in database:</strong> " . count($tables) . " tables found</p>";
        
        if (count($tables) > 0) {
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li>$table</li>";
            }
            echo "</ul>";
        }
        
    } else {
        echo "<p>❌ Database <code>$currentDb</code> does not exist</p>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Check for database mismatch
echo "<h3>Database Configuration Analysis:</h3>";

if (isset($dbName) && isset($schemaDbName)) {
    if ($dbName === $schemaDbName) {
        echo "<p>✅ Database names match between config and schema</p>";
    } else {
        echo "<p>⚠️ <strong>Database name mismatch detected!</strong></p>";
        echo "<ul>";
        echo "<li>Config file uses: <code>$dbName</code></li>";
        echo "<li>Schema file creates: <code>$schemaDbName</code></li>";
        echo "</ul>";
        echo "<p>This could cause issues. The system will try to connect to <code>$dbName</code> but the schema creates <code>$schemaDbName</code>.</p>";
    }
}

// Recommendations
echo "<h3>Recommendations:</h3>";

if (isset($currentDb) && isset($dbName) && $currentDb !== $dbName) {
    echo "<p>⚠️ <strong>Warning:</strong> The system is connected to <code>$currentDb</code> but configured to use <code>$dbName</code>.</p>";
}

if (isset($currentDb) && isset($schemaDbName) && $currentDb !== $schemaDbName) {
    echo "<p>⚠️ <strong>Warning:</strong> The system is connected to <code>$currentDb</code> but the schema creates <code>$schemaDbName</code>.</p>";
}

echo "<p><strong>To fix database issues:</strong></p>";
echo "<ol>";
echo "<li>Ensure the database exists: <code>CREATE DATABASE IF NOT EXISTS hotel_pms_clean;</code></li>";
echo "<li>Run the schema: <code>mysql -u root -p hotel_pms_clean < schema.sql</code></li>";
echo "<li>Run the seeding: <a href='run_seed.php'>Execute Seed Data</a></li>";
echo "</ol>";

echo "<h3>Quick Actions:</h3>";
echo "<p><a href='run_seed.php'>🔧 Run Database Seeding</a></p>";
echo "<p><a href='../modules/front-desk/index.php'>🏠 Go to Front Desk Dashboard</a></p>";
echo "<p><a href='../login.php'>🔐 Go to Login Page</a></p>";
?>
