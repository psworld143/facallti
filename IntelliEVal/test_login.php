<?php
session_start();
require_once '../config/database.php';

// Simulate a logged-in guidance officer session
$_SESSION['user_id'] = 5;
$_SESSION['username'] = 'guidance';
$_SESSION['email'] = 'guidance@seait.edu.ph';
$_SESSION['role'] = 'guidance_officer';
$_SESSION['first_name'] = 'Guidance';
$_SESSION['last_name'] = 'Officer';

echo "Session created successfully!<br>";
echo "User ID: " . $_SESSION['user_id'] . "<br>";
echo "Role: " . $_SESSION['role'] . "<br>";
echo "Name: " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . "<br>";

// Now test the students.php page
echo "<br><a href='students.php?search=Sorioso&status='>Test Students Page with Search</a><br>";
echo "<a href='students.php'>Test Students Page without Search</a><br>";
?>
