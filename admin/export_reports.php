<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_admin();

// Get parameters
$type = isset($_GET['type']) ? $_GET['type'] : '';
$semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $type . '_report_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Get semester date range if specified
$semester_start_date = null;
$semester_end_date = null;
if ($semester > 0) {
    $semester_dates_query = "SELECT start_date, end_date, name, academic_year FROM semesters WHERE id = ?";
    $stmt = mysqli_prepare($conn, $semester_dates_query);
    mysqli_stmt_bind_param($stmt, "i", $semester);
    mysqli_stmt_execute($stmt);
    $semester_dates_result = mysqli_stmt_get_result($stmt);
    if ($semester_row = mysqli_fetch_assoc($semester_dates_result)) {
        $semester_start_date = $semester_row['start_date'];
        $semester_end_date = $semester_row['end_date'];
        $semester_name = $semester_row['name'];
        $academic_year = $semester_row['academic_year'];
    }
}

switch ($type) {
    case 'users':
        // Export users report
        fputcsv($output, ['User Report - ' . ($semester > 0 ? $semester_name . ' (' . $academic_year . ')' : 'All Time')]);
        fputcsv($output, ['ID', 'Username', 'First Name', 'Last Name', 'Email', 'Role', 'Status', 'Created At']);

        $users_where = "";
        $users_params = [];
        if ($semester_start_date && $semester_end_date) {
            $users_where = "WHERE created_at BETWEEN ? AND ?";
            $users_params = [$semester_start_date, $semester_end_date];
        }

        $users_query = "SELECT id, username, first_name, last_name, email, role, status, created_at FROM users $users_where ORDER BY created_at DESC";

        if (!empty($users_params)) {
            $stmt = mysqli_prepare($conn, $users_query);
            mysqli_stmt_bind_param($stmt, "ss", $users_params[0], $users_params[1]);
            mysqli_stmt_execute($stmt);
            $users_result = mysqli_stmt_get_result($stmt);
        } else {
            $users_result = mysqli_query($conn, $users_query);
        }

        while ($user = mysqli_fetch_assoc($users_result)) {
            fputcsv($output, [
                $user['id'],
                $user['username'],
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['role'],
                $user['status'],
                $user['created_at']
            ]);
        }
        break;

    case 'posts':
        // Export posts report
        fputcsv($output, ['Posts Report - ' . ($semester > 0 ? $semester_name . ' (' . $academic_year . ')' : 'All Time')]);
        fputcsv($output, ['ID', 'Title', 'Type', 'Status', 'Author', 'Created At', 'Updated At']);

        $posts_where = "";
        $posts_params = [];
        if ($semester_start_date && $semester_end_date) {
            $posts_where = "WHERE p.created_at BETWEEN ? AND ?";
            $posts_params = [$semester_start_date, $semester_end_date];
        }

        $posts_query = "SELECT p.id, p.title, p.type, p.status, p.created_at, p.updated_at,
                               CONCAT(u.first_name, ' ', u.last_name) as author
                        FROM posts p
                        LEFT JOIN users u ON p.author_id = u.id
                        $posts_where
                        ORDER BY p.created_at DESC";

        if (!empty($posts_params)) {
            $stmt = mysqli_prepare($conn, $posts_query);
            mysqli_stmt_bind_param($stmt, "ss", $posts_params[0], $posts_params[1]);
            mysqli_stmt_execute($stmt);
            $posts_result = mysqli_stmt_get_result($stmt);
        } else {
            $posts_result = mysqli_query($conn, $posts_query);
        }

        while ($post = mysqli_fetch_assoc($posts_result)) {
            fputcsv($output, [
                $post['id'],
                $post['title'],
                $post['type'],
                $post['status'],
                $post['author'],
                $post['created_at'],
                $post['updated_at']
            ]);
        }
        break;

    case 'inquiries':
        // Export inquiries report
        fputcsv($output, ['Inquiries Report - ' . ($semester > 0 ? $semester_name . ' (' . $academic_year . ')' : 'All Time')]);
        fputcsv($output, ['ID', 'Name', 'Email', 'Subject', 'Message', 'Status', 'Created At', 'Resolved At']);

        $inquiries_where = "";
        $inquiries_params = [];
        if ($semester_start_date && $semester_end_date) {
            $inquiries_where = "WHERE created_at BETWEEN ? AND ?";
            $inquiries_params = [$semester_start_date, $semester_end_date];
        }

        $inquiries_query = "SELECT id, name, email, subject, message, is_resolved, created_at, resolved_at FROM user_inquiries $inquiries_where ORDER BY created_at DESC";

        if (!empty($inquiries_params)) {
            $stmt = mysqli_prepare($conn, $inquiries_query);
            mysqli_stmt_bind_param($stmt, "ss", $inquiries_params[0], $inquiries_params[1]);
            mysqli_stmt_execute($stmt);
            $inquiries_result = mysqli_stmt_get_result($stmt);
        } else {
            $inquiries_result = mysqli_query($conn, $inquiries_query);
        }

        while ($inquiry = mysqli_fetch_assoc($inquiries_result)) {
            fputcsv($output, [
                $inquiry['id'],
                $inquiry['name'],
                $inquiry['email'],
                $inquiry['subject'],
                $inquiry['message'],
                $inquiry['is_resolved'] ? 'Resolved' : 'Unresolved',
                $inquiry['created_at'],
                $inquiry['resolved_at']
            ]);
        }
        break;

    case 'students':
        // Export students report
        fputcsv($output, ['Students Report - ' . ($semester > 0 ? $semester_name . ' (' . $academic_year . ')' : 'All Time')]);
        fputcsv($output, ['ID', 'Student ID', 'First Name', 'Last Name', 'Email', 'Status', 'Created At']);

        $students_where = "WHERE status = 'active'";
        $students_params = [];
        if ($semester_start_date && $semester_end_date) {
            $students_where .= " AND created_at BETWEEN ? AND ?";
            $students_params = [$semester_start_date, $semester_end_date];
        }

        $students_query = "SELECT id, student_id, first_name, last_name, email, status, created_at FROM students $students_where ORDER BY created_at DESC";

        if (!empty($students_params)) {
            $stmt = mysqli_prepare($conn, $students_query);
            mysqli_stmt_bind_param($stmt, "ss", $students_params[0], $students_params[1]);
            mysqli_stmt_execute($stmt);
            $students_result = mysqli_stmt_get_result($stmt);
        } else {
            $students_result = mysqli_query($conn, $students_query);
        }

        while ($student = mysqli_fetch_assoc($students_result)) {
            fputcsv($output, [
                $student['id'],
                $student['student_id'],
                $student['first_name'],
                $student['last_name'],
                $student['email'],
                $student['status'],
                $student['created_at']
            ]);
        }
        break;

    case 'evaluations':
        // Export evaluations report (if IntelliEVal system exists)
        if (mysqli_query($conn, "SHOW TABLES LIKE 'evaluation_sessions'")) {
            fputcsv($output, ['Evaluations Report - ' . ($semester > 0 ? $semester_name . ' (' . $academic_year . ')' : 'All Time')]);
            fputcsv($output, ['ID', 'Evaluator Type', 'Evaluator ID', 'Evaluatee ID', 'Subject ID', 'Semester', 'Created At']);

            $evaluations_where = "";
            $evaluations_params = [];
            if ($semester_start_date && $semester_end_date) {
                $evaluations_where = "WHERE es.created_at BETWEEN ? AND ?";
                $evaluations_params = [$semester_start_date, $semester_end_date];
            }

            $evaluations_query = "SELECT es.id, es.evaluator_type, es.evaluator_id, es.evaluatee_id, es.subject_id,
                                        s.name as semester_name, es.created_at
                                 FROM evaluation_sessions es
                                 LEFT JOIN semesters s ON es.semester_id = s.id
                                 $evaluations_where
                                 ORDER BY es.created_at DESC";

            if (!empty($evaluations_params)) {
                $stmt = mysqli_prepare($conn, $evaluations_query);
                mysqli_stmt_bind_param($stmt, "ss", $evaluations_params[0], $evaluations_params[1]);
                mysqli_stmt_execute($stmt);
                $evaluations_result = mysqli_stmt_get_result($stmt);
            } else {
                $evaluations_result = mysqli_query($conn, $evaluations_query);
            }

            while ($evaluation = mysqli_fetch_assoc($evaluations_result)) {
                fputcsv($output, [
                    $evaluation['id'],
                    $evaluation['evaluator_type'],
                    $evaluation['evaluator_id'],
                    $evaluation['evaluatee_id'],
                    $evaluation['subject_id'],
                    $evaluation['semester_name'],
                    $evaluation['created_at']
                ]);
            }
        } else {
            fputcsv($output, ['Evaluations Report']);
            fputcsv($output, ['No evaluation data available']);
        }
        break;

    default:
        fputcsv($output, ['Invalid report type']);
        break;
}

fclose($output);
exit;
?>