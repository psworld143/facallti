<?php
session_start();

echo "<h1>Session Test</h1>";

echo "<h2>Session Variables:</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "Role: " . ($_SESSION['role'] ?? 'NOT SET') . "<br>";
echo "Username: " . ($_SESSION['username'] ?? 'NOT SET') . "<br>";

echo "<h2>Authentication Check:</h2>";
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "❌ Not logged in - No user_id in session<br>";
} else {
    echo "✅ Logged in - User ID: " . $_SESSION['user_id'] . "<br>";
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'human_resource') {
    echo "❌ Wrong role - Current role: " . ($_SESSION['role'] ?? 'NOT SET') . "<br>";
    echo "Required role: human_resource<br>";
} else {
    echo "✅ Correct role - human_resource<br>";
}

echo "<h2>All Session Data:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>
