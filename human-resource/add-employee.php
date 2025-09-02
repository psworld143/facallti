<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Validate and sanitize input data
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name = sanitize_input($_POST['last_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $employee_id = sanitize_input($_POST['employee_id'] ?? '');
    $hire_date = sanitize_input($_POST['hire_date'] ?? '');
    $position = sanitize_input($_POST['position'] ?? '');
    $department = sanitize_input($_POST['department'] ?? '');
    $employee_type = sanitize_input($_POST['employee_type'] ?? '');
    $is_active = (int)($_POST['is_active'] ?? 1);
    $password = $_POST['password'] ?? '';

    // Validate required fields
    $required_fields = [
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email Address',
        'phone' => 'Phone Number',
        'address' => 'Address',
        'employee_id' => 'Employee ID',
        'hire_date' => 'Date of Hire',
        'position' => 'Position',
        'department' => 'Department',
        'employee_type' => 'Employee Type',
        'password' => 'Password'
    ];

    $missing_fields = [];
    foreach ($required_fields as $field => $label) {
        if (empty($$field)) {
            $missing_fields[] = $label;
        }
    }

    if (!empty($missing_fields)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
        ]);
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        exit();
    }

    // Validate password length
    if (strlen($password) < 8) {
        echo json_encode([
            'success' => false,
            'message' => 'Password must be at least 8 characters long'
        ]);
        exit();
    }

    // Validate employee ID format (YYYY-XXXX)
    if (!preg_match('/^\d{4}-\d{4}$/', $employee_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'Employee ID must be in format YYYY-XXXX'
        ]);
        exit();
    }

    // Validate employee type
    $valid_employee_types = ['faculty', 'staff', 'admin'];
    if (!in_array($employee_type, $valid_employee_types)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid employee type'
        ]);
        exit();
    }

    // Check if email already exists
    $check_email_query = "SELECT id FROM employees WHERE email = ?";
    $check_email_stmt = mysqli_prepare($conn, $check_email_query);
    mysqli_stmt_bind_param($check_email_stmt, 's', $email);
    mysqli_stmt_execute($check_email_stmt);
    $check_email_result = mysqli_stmt_get_result($check_email_stmt);

    if (mysqli_num_rows($check_email_result) > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Email address already exists'
        ]);
        exit();
    }

    // Check if employee ID already exists in both employees and faculty tables
    $check_employee_id_query = "SELECT id FROM employees WHERE employee_id = ? 
                               UNION ALL 
                               SELECT id FROM faculty WHERE qrcode COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci";
    $check_employee_id_stmt = mysqli_prepare($conn, $check_employee_id_query);
    mysqli_stmt_bind_param($check_employee_id_stmt, 'ss', $employee_id, $employee_id);
    mysqli_stmt_execute($check_employee_id_stmt);
    $check_employee_id_result = mysqli_stmt_get_result($check_employee_id_stmt);

    if (mysqli_num_rows($check_employee_id_result) > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Employee ID already exists in the system'
        ]);
        exit();
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new employee
    $insert_query = "INSERT INTO employees (employee_id, first_name, last_name, email, password, position, department, employee_type, hire_date, phone, address, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $insert_stmt = mysqli_prepare($conn, $insert_query);
    if (!$insert_stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($insert_stmt, 'sssssssssssi', 
        $employee_id, 
        $first_name, 
        $last_name, 
        $email, 
        $hashed_password, 
        $position, 
        $department, 
        $employee_type, 
        $hire_date, 
        $phone, 
        $address, 
        $is_active
    );

    if (!mysqli_stmt_execute($insert_stmt)) {
        throw new Exception('Database execute error: ' . mysqli_stmt_error($insert_stmt));
    }

    $new_employee_id = mysqli_insert_id($conn);

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Employee added successfully',
        'employee_id' => $new_employee_id,
        'employee_data' => [
            'id' => $new_employee_id,
            'employee_id' => $employee_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'position' => $position,
            'department' => $department,
            'employee_type' => $employee_type,
            'is_active' => $is_active
        ]
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Error adding employee: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while adding the employee. Please try again.'
    ]);
}

mysqli_close($conn);
?>
