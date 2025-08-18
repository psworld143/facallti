<?php
/**
 * Student Registration Functions
 * Handles all student-related operations including registration and Excel import
 */

/**
 * Register a new student manually
 */
function registerStudent($conn, $data) {
    // Validate required fields
    $required_fields = ['student_id', 'first_name', 'last_name', 'email'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required.'];
        }
    }

    // Sanitize input data
    $student_id = mysqli_real_escape_string($conn, trim($data['student_id']));
    $first_name = mysqli_real_escape_string($conn, trim($data['first_name']));
    $middle_name = mysqli_real_escape_string($conn, trim($data['middle_name'] ?? ''));
    $last_name = mysqli_real_escape_string($conn, trim($data['last_name']));
    $email = mysqli_real_escape_string($conn, trim($data['email']));

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format.'];
    }

    // Check if student ID already exists
    $check_query = "SELECT id FROM students WHERE student_id = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "s", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        return ['success' => false, 'message' => 'Student ID already exists.'];
    }

    // Check if email already exists
    $check_email_query = "SELECT id FROM students WHERE email = ?";
    $stmt = mysqli_prepare($conn, $check_email_query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        return ['success' => false, 'message' => 'Email address already exists.'];
    }

    // Generate default password hash
    $default_password = 'Seait123';
    $password_hash = password_hash($default_password, PASSWORD_DEFAULT);

    // Insert new student
    $insert_query = "INSERT INTO students (student_id, first_name, middle_name, last_name, email, password_hash, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())";
    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt, "ssssss", $student_id, $first_name, $middle_name, $last_name, $email, $password_hash);

    if (mysqli_stmt_execute($stmt)) {
        $student_id_inserted = mysqli_insert_id($conn);

        // Log the registration
        logStudentRegistration($conn, $student_id_inserted, 'manual');

        return [
            'success' => true,
            'message' => 'Student registered successfully with default password: ' . $default_password
        ];
    } else {
        return ['success' => false, 'message' => 'Error registering student: ' . mysqli_error($conn)];
    }
}

/**
 * Import students from Excel file
 */
function importStudentsFromExcel($conn, $files) {
    if (!isset($files['excel_file']) || $files['excel_file']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Please select a valid Excel file.'];
    }

    $file = $files['excel_file'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Validate file extension
    if (!in_array($file_extension, ['xlsx', 'xls'])) {
        return ['success' => false, 'message' => 'Only Excel files (.xlsx, .xls) are allowed.'];
    }

    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File size must be less than 5MB.'];
    }

    // Include PhpSpreadsheet library
    require_once '../vendor/autoload.php';

    try {
        // Load the Excel file
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Remove header row if exists
        if (count($rows) > 0) {
            $header = array_map('strtolower', $rows[0]);
            if (in_array('student id', $header) || in_array('student_id', $header)) {
                array_shift($rows);
            }
        }

        if (count($rows) === 0) {
            return ['success' => false, 'message' => 'No data found in the Excel file.'];
        }

        $success_count = 0;
        $error_count = 0;
        $errors = [];

        // Begin transaction
        mysqli_begin_transaction($conn);

        foreach ($rows as $row_index => $row) {
            $row_number = $row_index + 2; // +2 because we removed header and array is 0-indexed

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Validate required columns
            if (count($row) < 4) {
                $errors[] = "Row $row_number: Insufficient data columns.";
                $error_count++;
                continue;
            }

            // Extract data from columns
            $student_id = trim($row[0] ?? '');
            $first_name = trim($row[1] ?? '');
            $middle_name = trim($row[2] ?? '');
            $last_name = trim($row[3] ?? '');
            $email = trim($row[4] ?? '');

            // Validate required fields
            if (empty($student_id) || empty($first_name) || empty($last_name) || empty($email)) {
                $errors[] = "Row $row_number: Missing required fields (Student ID, First Name, Last Name, or Email).";
                $error_count++;
                continue;
            }

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row $row_number: Invalid email format ($email).";
                $error_count++;
                continue;
            }

            // Check for duplicate student ID
            $check_query = "SELECT id FROM students WHERE student_id = ?";
            $stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($stmt, "s", $student_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) > 0) {
                $errors[] = "Row $row_number: Student ID '$student_id' already exists.";
                $error_count++;
                continue;
            }

            // Check for duplicate email
            $check_email_query = "SELECT id FROM students WHERE email = ?";
            $stmt = mysqli_prepare($conn, $check_email_query);
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) > 0) {
                $errors[] = "Row $row_number: Email '$email' already exists.";
                $error_count++;
                continue;
            }

            // Generate default password hash
            $default_password = 'Seait123';
            $password_hash = password_hash($default_password, PASSWORD_DEFAULT);

            // Insert student
            $insert_query = "INSERT INTO students (student_id, first_name, middle_name, last_name, email, password_hash, status, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "ssssss", $student_id, $first_name, $middle_name, $last_name, $email, $password_hash);

            if (mysqli_stmt_execute($stmt)) {
                $student_id_inserted = mysqli_insert_id($conn);
                logStudentRegistration($conn, $student_id_inserted, 'excel_import');
                $success_count++;
            } else {
                $errors[] = "Row $row_number: Database error - " . mysqli_error($conn);
                $error_count++;
            }
        }

        // Commit or rollback transaction
        if ($error_count === 0) {
            mysqli_commit($conn);
            $message = "Successfully imported $success_count students.";
        } else {
            mysqli_rollback($conn);
            $message = "Import completed with errors. $success_count students imported, $error_count errors encountered.";
            if (count($errors) > 0) {
                $message .= " First few errors: " . implode('; ', array_slice($errors, 0, 3));
            }
        }

        return [
            'success' => $success_count > 0,
            'message' => $message,
            'imported' => $success_count,
            'errors' => $error_count,
            'error_details' => $errors
        ];

    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => 'Error processing Excel file: ' . $e->getMessage()];
    }
}

/**
 * Log student registration activity
 */
function logStudentRegistration($conn, $student_id, $method) {
    $admin_id = $_SESSION['user_id'] ?? 0;
    $method = mysqli_real_escape_string($conn, $method);

    $log_query = "INSERT INTO student_registration_logs (student_id, admin_id, registration_method, created_at)
                  VALUES (?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $log_query);
    mysqli_stmt_bind_param($stmt, "iis", $student_id, $admin_id, $method);
    mysqli_stmt_execute($stmt);
}

/**
 * Get student by ID
 */
function getStudentById($conn, $id) {
    $query = "SELECT * FROM students WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result);
}

/**
 * Update student information
 */
function updateStudent($conn, $id, $data) {
    // Validate required fields
    $required_fields = ['student_id', 'first_name', 'last_name', 'email'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required.'];
        }
    }

    // Sanitize input data
    $student_id = mysqli_real_escape_string($conn, trim($data['student_id']));
    $first_name = mysqli_real_escape_string($conn, trim($data['first_name']));
    $middle_name = mysqli_real_escape_string($conn, trim($data['middle_name'] ?? ''));
    $last_name = mysqli_real_escape_string($conn, trim($data['last_name']));
    $email = mysqli_real_escape_string($conn, trim($data['email']));
    $status = mysqli_real_escape_string($conn, trim($data['status'] ?? 'active'));

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format.'];
    }

    // Check if student ID already exists (excluding current student)
    $check_query = "SELECT id FROM students WHERE student_id = ? AND id != ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "si", $student_id, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        return ['success' => false, 'message' => 'Student ID already exists.'];
    }

    // Check if email already exists (excluding current student)
    $check_email_query = "SELECT id FROM students WHERE email = ? AND id != ?";
    $stmt = mysqli_prepare($conn, $check_email_query);
    mysqli_stmt_bind_param($stmt, "si", $email, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        return ['success' => false, 'message' => 'Email address already exists.'];
    }

    // Update student
    $update_query = "UPDATE students SET student_id = ?, first_name = ?, middle_name = ?, last_name = ?,
                     email = ?, status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "ssssssi", $student_id, $first_name, $middle_name, $last_name, $email, $status, $id);

    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Student updated successfully.'];
    } else {
        return ['success' => false, 'message' => 'Error updating student: ' . mysqli_error($conn)];
    }
}

/**
 * Delete student
 */
function deleteStudent($conn, $id) {
    // Check if student exists
    $student = getStudentById($conn, $id);
    if (!$student) {
        return ['success' => false, 'message' => 'Student not found.'];
    }

    // Soft delete by updating status
    $delete_query = "UPDATE students SET status = 'deleted', deleted_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Student deleted successfully.'];
    } else {
        return ['success' => false, 'message' => 'Error deleting student: ' . mysqli_error($conn)];
    }
}

/**
 * Reset student password to default
 */
function resetStudentPassword($conn, $id) {
    $default_password = 'Seait123';
    $password_hash = password_hash($default_password, PASSWORD_DEFAULT);

    $reset_query = "UPDATE students SET password_hash = ?, updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $reset_query);
    mysqli_stmt_bind_param($stmt, "si", $password_hash, $id);

    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Password reset successfully to: ' . $default_password];
    } else {
        return ['success' => false, 'message' => 'Error resetting password: ' . mysqli_error($conn)];
    }
}

/**
 * Search students
 */
function searchStudents($conn, $search_term, $status = null, $limit = 50) {
    $search_term = mysqli_real_escape_string($conn, $search_term);
    $status_filter = '';

    if ($status) {
        $status = mysqli_real_escape_string($conn, $status);
        $status_filter = " AND status = '$status'";
    }

    $query = "SELECT * FROM students
              WHERE (student_id LIKE '%$search_term%'
                     OR first_name LIKE '%$search_term%'
                     OR last_name LIKE '%$search_term%'
                     OR email LIKE '%$search_term%')
              $status_filter
              ORDER BY created_at DESC
              LIMIT $limit";

    $result = mysqli_query($conn, $query);
    $students = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }

    return $students;
}

/**
 * Get student statistics
 */
function getStudentStatistics($conn) {
    $stats = [];

    // Total students
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM students WHERE status != 'deleted'");
    $stats['total'] = mysqli_fetch_assoc($result)['total'];

    // Active students
    $result = mysqli_query($conn, "SELECT COUNT(*) as active FROM students WHERE status = 'active'");
    $stats['active'] = mysqli_fetch_assoc($result)['active'];

    // Pending students
    $result = mysqli_query($conn, "SELECT COUNT(*) as pending FROM students WHERE status = 'pending'");
    $stats['pending'] = mysqli_fetch_assoc($result)['pending'];

    // Today's registrations
    $result = mysqli_query($conn, "SELECT COUNT(*) as today FROM students WHERE DATE(created_at) = CURDATE()");
    $stats['today'] = mysqli_fetch_assoc($result)['today'];

    // This month's registrations
    $result = mysqli_query($conn, "SELECT COUNT(*) as this_month FROM students WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stats['this_month'] = mysqli_fetch_assoc($result)['this_month'];

    return $stats;
}
?>