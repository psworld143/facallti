<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>PHP 8.1+ Login Debug Test</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test 1: Database connection
echo "<h3>1. Database Connection Test</h3>";
try {
    require_once 'config/database.php';
    if ($conn) {
        echo "<p>✅ Database connection successful</p>";
        echo "<p>MySQL version: " . mysqli_get_server_info($conn) . "</p>";
    } else {
        echo "<p>❌ Database connection failed</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Database connection error: " . $e->getMessage() . "</p>";
}

// Test 2: Functions file
echo "<h3>2. Functions File Test</h3>";
try {
    require_once 'includes/functions.php';
    echo "<p>✅ Functions file loaded successfully</p>";

    // Test sanitize_input function
    $test_input = "  test<script>alert('xss')</script>  ";
    $sanitized = sanitize_input($test_input);
    echo "<p>✅ sanitize_input function works: '" . $sanitized . "'</p>";
} catch (Exception $e) {
    echo "<p>❌ Functions file error: " . $e->getMessage() . "</p>";
}

// Test 3: Session handling
echo "<h3>3. Session Test</h3>";
try {
    session_start();
    echo "<p>✅ Session started successfully</p>";
    echo "<p>Session ID: " . session_id() . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Session error: " . $e->getMessage() . "</p>";
}

// Test 4: Prepared statements
echo "<h3>4. Prepared Statement Test</h3>";
try {
    $query = "SELECT COUNT(*) as count FROM users";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        echo "<p>✅ Prepared statement works: " . $row['count'] . " users found</p>";
        mysqli_stmt_close($stmt);
    } else {
        echo "<p>❌ Prepared statement failed</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Prepared statement error: " . $e->getMessage() . "</p>";
}

// Test 5: Password verification
echo "<h3>5. Password Verification Test</h3>";
try {
    $test_password = 'test123';
    $test_hash = password_hash($test_password, PASSWORD_DEFAULT);
    if (password_verify($test_password, $test_hash)) {
        echo "<p>✅ Password verification works</p>";
    } else {
        echo "<p>❌ Password verification failed</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Password verification error: " . $e->getMessage() . "</p>";
}

// Test 6: Check for deprecated functions
echo "<h3>6. Deprecated Functions Check</h3>";
$deprecated_functions = [
    'mysql_connect',
    'mysql_query',
    'mysql_fetch_array',
    'ereg',
    'split'
];

foreach ($deprecated_functions as $func) {
    if (function_exists($func)) {
        echo "<p>⚠️ Deprecated function found: $func</p>";
    } else {
        echo "<p>✅ Deprecated function not used: $func</p>";
    }
}

// Test 7: Test actual login logic
echo "<h3>7. Login Logic Test</h3>";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];

        echo "<p>Testing login for username: $username</p>";

        // Test users table query
        $query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($conn, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $username, $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($user = mysqli_fetch_assoc($result)) {
                echo "<p>✅ User found in users table</p>";
                if (password_verify($password, $user['password'])) {
                    echo "<p>✅ Password verified successfully</p>";
                    echo "<p>User role: " . $user['role'] . "</p>";
                } else {
                    echo "<p>❌ Password verification failed</p>";
                }
            } else {
                echo "<p>⚠️ User not found in users table, checking students table...</p>";

                // Test students table query
                $query = "SELECT * FROM students WHERE email = ? OR student_id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "ss", $username, $username);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if ($student = mysqli_fetch_assoc($result)) {
                    echo "<p>✅ Student found in students table</p>";
                    if (password_verify($password, $student['password_hash'])) {
                        echo "<p>✅ Student password verified successfully</p>";
                    } else {
                        echo "<p>❌ Student password verification failed</p>";
                    }
                } else {
                    echo "<p>⚠️ User not found in students table, checking faculty table...</p>";

                    // Test faculty table query
                    $query = "SELECT * FROM faculty WHERE email = ? AND is_active = 1";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "s", $username);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);

                    if ($faculty = mysqli_fetch_assoc($result)) {
                        echo "<p>✅ Faculty found in faculty table</p>";
                        if (password_verify($password, $faculty['password'])) {
                            echo "<p>✅ Faculty password verified successfully</p>";
                        } else {
                            echo "<p>❌ Faculty password verification failed</p>";
                        }
                    } else {
                        echo "<p>❌ User not found in any table</p>";
                    }
                }
            }
        } else {
            echo "<p>❌ Prepared statement failed</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Login logic error: " . $e->getMessage() . "</p>";
        echo "<p>Error trace: " . $e->getTraceAsString() . "</p>";
    }
}

// Test 8: Check for common PHP 8.1+ issues
echo "<h3>8. PHP 8.1+ Compatibility Check</h3>";

// Check for undefined array key access
echo "<p>Testing undefined array key access...</p>";
$test_array = [];
try {
    $value = $test_array['nonexistent_key'] ?? 'default';
    echo "<p>✅ Null coalescing operator works: $value</p>";
} catch (Exception $e) {
    echo "<p>❌ Array access error: " . $e->getMessage() . "</p>";
}

// Check for strict type declarations
echo "<p>Testing strict type handling...</p>";
try {
    $test_string = "123";
    $test_int = (int)$test_string;
    echo "<p>✅ Type casting works: $test_int</p>";
} catch (Exception $e) {
    echo "<p>❌ Type handling error: " . $e->getMessage() . "</p>";
}
?>

<form method="POST">
    <h3>Test Login</h3>
    <p>
        <label>Username/Email: <input type="text" name="username" placeholder="Enter username or email"></label>
    </p>
    <p>
        <label>Password: <input type="password" name="password" placeholder="Enter password"></label>
    </p>
    <p>
        <button type="submit">Test Login</button>
    </p>
</form>

<p><a href="login.php">Go to actual login page</a></p>
<p><a href="test-login.php">Go to original test page</a></p>
