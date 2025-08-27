<?php
session_start();
require_once '../config/database.php';

// Test the consultation system
echo "<h1>Consultation System Test</h1>";

// Check if consultation_requests table exists
$check_table = "SHOW TABLES LIKE 'consultation_requests'";
$result = mysqli_query($conn, $check_table);

if (mysqli_num_rows($result) > 0) {
    echo "<p style='color: green;'>✓ consultation_requests table exists</p>";
} else {
    echo "<p style='color: red;'>✗ consultation_requests table does not exist</p>";
}

// Test inserting a consultation request
$test_teacher_id = 1; // Assuming teacher ID 1 exists
$test_session_id = uniqid('test_', true);
$test_student_name = 'Test Student';
$test_student_dept = 'Computer Science';

$insert_query = "INSERT INTO consultation_requests (teacher_id, student_name, student_dept, session_id, status, request_time) 
                 VALUES (?, ?, ?, ?, 'pending', NOW())";
$stmt = mysqli_prepare($conn, $insert_query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "isss", $test_teacher_id, $test_student_name, $test_student_dept, $test_session_id);
    $insert_result = mysqli_stmt_execute($stmt);
    
    if ($insert_result) {
        echo "<p style='color: green;'>✓ Successfully inserted test consultation request</p>";
        echo "<p>Session ID: $test_session_id</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to insert test consultation request</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Failed to prepare insert statement</p>";
}

// Test checking for consultation requests
$check_query = "SELECT * FROM consultation_requests WHERE teacher_id = ? AND status = 'pending' ORDER BY request_time DESC LIMIT 1";
$check_stmt = mysqli_prepare($conn, $check_query);

if ($check_stmt) {
    mysqli_stmt_bind_param($check_stmt, "i", $test_teacher_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $request = mysqli_fetch_assoc($check_result);
    
    if ($request) {
        echo "<p style='color: green;'>✓ Successfully retrieved consultation request</p>";
        echo "<p>Request ID: {$request['id']}</p>";
        echo "<p>Student: {$request['student_name']}</p>";
        echo "<p>Status: {$request['status']}</p>";
    } else {
        echo "<p style='color: red;'>✗ No consultation requests found</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Failed to prepare check statement</p>";
}

// Test updating consultation request status
if (isset($request['id'])) {
    $update_query = "UPDATE consultation_requests SET status = 'accepted', response_time = NOW() WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    
    if ($update_stmt) {
        mysqli_stmt_bind_param($update_stmt, "i", $request['id']);
        $update_result = mysqli_stmt_execute($update_stmt);
        
        if ($update_result) {
            echo "<p style='color: green;'>✓ Successfully updated consultation request status</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to update consultation request status</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Failed to prepare update statement</p>";
    }
}

// Clean up test data
$cleanup_query = "DELETE FROM consultation_requests WHERE session_id = ?";
$cleanup_stmt = mysqli_prepare($conn, $cleanup_query);

if ($cleanup_stmt) {
    mysqli_stmt_bind_param($cleanup_stmt, "s", $test_session_id);
    mysqli_stmt_execute($cleanup_stmt);
    echo "<p style='color: blue;'>✓ Cleaned up test data</p>";
}

echo "<h2>Test Links</h2>";
echo "<p><a href='student-screen.php'>Student Screen</a></p>";
echo "<p><a href='teacher-screen.php?teacher_id=1'>Teacher Screen (Teacher ID: 1)</a></p>";
echo "<p><a href='index.php'>Consultation Index</a></p>";
?>
