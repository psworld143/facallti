<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Get parameters
$type = isset($_GET['type']) ? $_GET['type'] : '';
$semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="intellieval_' . $type . '_report_' . date('Y-m-d') . '.csv"');

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
    case 'evaluations':
        // Export evaluations report
        fputcsv($output, ['IntelliEVal Evaluation Report - ' . ($semester > 0 ? $semester_name . ' (' . $academic_year . ')' : 'All Time')]);
        fputcsv($output, ['ID', 'Evaluator Type', 'Evaluator Name', 'Teacher Name', 'Subject', 'Semester', 'Evaluation Date', 'Status']);

        $evaluations_where = "";
        $evaluations_params = [];
        if ($semester_start_date && $semester_end_date) {
            $evaluations_where = "WHERE es.evaluation_date BETWEEN ? AND ?";
            $evaluations_params = [$semester_start_date, $semester_end_date];
        }

        $evaluations_query = "SELECT es.id, es.evaluator_type, es.evaluation_date, es.status,
                                    CONCAT(COALESCE(evaluator_f.first_name, evaluator_u.first_name), ' ', COALESCE(evaluator_f.last_name, evaluator_u.last_name)) as evaluator_name,
                                    CONCAT(COALESCE(evaluatee_f.first_name, evaluatee_u.first_name), ' ', COALESCE(evaluatee_f.last_name, evaluatee_u.last_name)) as teacher_name,
                                    s.name as subject_name,
                                    sem.name as semester_name
                             FROM evaluation_sessions es
                             LEFT JOIN faculty evaluator_f ON es.evaluator_id = evaluator_f.id
                             LEFT JOIN users evaluator_u ON es.evaluator_id = evaluator_u.id
                             LEFT JOIN faculty evaluatee_f ON es.evaluatee_id = evaluatee_f.id
                             LEFT JOIN users evaluatee_u ON es.evaluatee_id = evaluatee_u.id
                             LEFT JOIN subjects s ON es.subject_id = s.id
                             LEFT JOIN semesters sem ON es.semester_id = sem.id
                             $evaluations_where
                             ORDER BY es.evaluation_date DESC";

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
                $evaluation['id'] ?? '',
                ucwords($evaluation['evaluator_type'] ?? 'Unknown'),
                $evaluation['evaluator_name'] ?? 'Unknown Evaluator',
                $evaluation['teacher_name'] ?? 'Unknown Teacher',
                $evaluation['subject_name'] ?? 'Subject not specified',
                $evaluation['semester_name'] ?? 'Semester not specified',
                $evaluation['evaluation_date'] ?? 'Date not available',
                ucwords($evaluation['status'] ?? 'Unknown')
            ]);
        }
        break;

    case 'teachers':
        // Export teacher evaluation report
        fputcsv($output, ['IntelliEVal Teacher Evaluation Report - ' . ($semester > 0 ? $semester_name . ' (' . $academic_year . ')' : 'All Time')]);
        fputcsv($output, ['Teacher ID', 'Teacher Name', 'Total Evaluations', 'Student Evaluations', 'Teacher Evaluations', 'Head Evaluations', 'Subjects Taught']);

        $teachers_where = "WHERE es.evaluatee_type = 'teacher'";
        $teachers_params = [];
        if ($semester_start_date && $semester_end_date) {
            $teachers_where .= " AND es.evaluation_date BETWEEN ? AND ?";
            $teachers_params = [$semester_start_date, $semester_end_date];
        }

        $teachers_query = "SELECT
            es.evaluatee_id,
            CONCAT(COALESCE(evaluatee_f.first_name, evaluatee_u.first_name), ' ', COALESCE(evaluatee_f.last_name, evaluatee_u.last_name)) as teacher_name,
            COUNT(*) as total_evaluations,
            SUM(CASE WHEN es.evaluator_type = 'student' THEN 1 ELSE 0 END) as student_evaluations,
            SUM(CASE WHEN es.evaluator_type = 'teacher' THEN 1 ELSE 0 END) as teacher_evaluations,
            SUM(CASE WHEN es.evaluator_type = 'head' THEN 1 ELSE 0 END) as head_evaluations,
            GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as subjects_taught
            FROM evaluation_sessions es
            LEFT JOIN faculty evaluatee_f ON es.evaluatee_id = evaluatee_f.id
            LEFT JOIN users evaluatee_u ON es.evaluatee_id = evaluatee_u.id
            LEFT JOIN subjects s ON es.subject_id = s.id
            $teachers_where
            GROUP BY es.evaluatee_id, evaluatee_f.first_name, evaluatee_f.last_name, evaluatee_u.first_name, evaluatee_u.last_name
            ORDER BY total_evaluations DESC";

        if (!empty($teachers_params)) {
            $stmt = mysqli_prepare($conn, $teachers_query);
            mysqli_stmt_bind_param($stmt, "ss", $teachers_params[0], $teachers_params[1]);
            mysqli_stmt_execute($stmt);
            $teachers_result = mysqli_stmt_get_result($stmt);
        } else {
            $teachers_result = mysqli_query($conn, $teachers_query);
        }

        while ($teacher = mysqli_fetch_assoc($teachers_result)) {
            fputcsv($output, [
                $teacher['evaluatee_id'] ?? '',
                $teacher['teacher_name'] ?? 'Unknown Teacher',
                $teacher['total_evaluations'] ?? 0,
                $teacher['student_evaluations'] ?? 0,
                $teacher['teacher_evaluations'] ?? 0,
                $teacher['head_evaluations'] ?? 0,
                $teacher['subjects_taught'] ?? 'No subjects specified'
            ]);
        }
        break;

    case 'subjects':
        // Export subject evaluation report
        fputcsv($output, ['IntelliEVal Subject Evaluation Report - ' . ($semester > 0 ? $semester_name . ' (' . $academic_year . ')' : 'All Time')]);
        fputcsv($output, ['Subject ID', 'Subject Name', 'Total Evaluations', 'Student Evaluations', 'Teacher Evaluations', 'Head Evaluations', 'Teachers Teaching']);

        $subjects_where = "WHERE es.subject_id IS NOT NULL";
        $subjects_params = [];
        if ($semester_start_date && $semester_end_date) {
            $subjects_where .= " AND es.evaluation_date BETWEEN ? AND ?";
            $subjects_params = [$semester_start_date, $semester_end_date];
        }

        $subjects_query = "SELECT
            es.subject_id,
            s.name as subject_name,
            COUNT(*) as total_evaluations,
            SUM(CASE WHEN es.evaluator_type = 'student' THEN 1 ELSE 0 END) as student_evaluations,
            SUM(CASE WHEN es.evaluator_type = 'teacher' THEN 1 ELSE 0 END) as teacher_evaluations,
            SUM(CASE WHEN es.evaluator_type = 'head' THEN 1 ELSE 0 END) as head_evaluations,
            GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as teachers_teaching
            FROM evaluation_sessions es
            LEFT JOIN subjects s ON es.subject_id = s.id
            LEFT JOIN users u ON es.evaluatee_id = u.id
            $subjects_where
            GROUP BY es.subject_id, s.name
            ORDER BY total_evaluations DESC";

        if (!empty($subjects_params)) {
            $stmt = mysqli_prepare($conn, $subjects_query);
            mysqli_stmt_bind_param($stmt, "ss", $subjects_params[0], $subjects_params[1]);
            mysqli_stmt_execute($stmt);
            $subjects_result = mysqli_stmt_get_result($stmt);
        } else {
            $subjects_result = mysqli_query($conn, $subjects_query);
        }

        while ($subject = mysqli_fetch_assoc($subjects_result)) {
            fputcsv($output, [
                $subject['subject_id'] ?? '',
                $subject['subject_name'] ?? 'Unknown Subject',
                $subject['total_evaluations'] ?? 0,
                $subject['student_evaluations'] ?? 0,
                $subject['teacher_evaluations'] ?? 0,
                $subject['head_evaluations'] ?? 0,
                $subject['teachers_teaching'] ?? 'No teachers specified'
            ]);
        }
        break;

    case 'students':
        // Export student participation report
        fputcsv($output, ['IntelliEVal Student Participation Report - ' . ($semester > 0 ? $semester_name . ' (' . $academic_year . ')' : 'All Time')]);
        fputcsv($output, ['Student ID', 'Student Name', 'Total Evaluations', 'Teachers Evaluated', 'Subjects Evaluated', 'Last Evaluation Date']);

        $students_where = "WHERE es.evaluator_type = 'student'";
        $students_params = [];
        if ($semester_start_date && $semester_end_date) {
            $students_where .= " AND es.evaluation_date BETWEEN ? AND ?";
            $students_params = [$semester_start_date, $semester_end_date];
        }

        $students_query = "SELECT
            es.evaluator_id,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            COUNT(*) as total_evaluations,
            COUNT(DISTINCT es.evaluatee_id) as teachers_evaluated,
            COUNT(DISTINCT es.subject_id) as subjects_evaluated,
            MAX(es.evaluation_date) as last_evaluation_date
            FROM evaluation_sessions es
            LEFT JOIN users u ON es.evaluator_id = u.id
            $students_where
            GROUP BY es.evaluator_id, u.first_name, u.last_name
            ORDER BY total_evaluations DESC";

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
                $student['evaluator_id'] ?? '',
                $student['student_name'] ?? 'Unknown Student',
                $student['total_evaluations'] ?? 0,
                $student['teachers_evaluated'] ?? 0,
                $student['subjects_evaluated'] ?? 0,
                $student['last_evaluation_date'] ?? 'No evaluations yet'
            ]);
        }
        break;

    case 'monthly':
        // Export monthly evaluation activity report
        fputcsv($output, ['IntelliEVal Monthly Activity Report - ' . ($semester > 0 ? $semester_name . ' (' . $academic_year . ')' : 'All Time')]);
        fputcsv($output, ['Month', 'Total Evaluations', 'Student Evaluations', 'Teacher Evaluations', 'Head Evaluations']);

        $monthly_where = "";
        $monthly_params = [];
        if ($semester_start_date && $semester_end_date) {
            $monthly_where = "WHERE evaluation_date BETWEEN ? AND ?";
            $monthly_params = [$semester_start_date, $semester_end_date];
        }

        $monthly_query = "SELECT
            DATE_FORMAT(evaluation_date, '%Y-%m') as month,
            COUNT(*) as total_evaluations,
            SUM(CASE WHEN evaluator_type = 'student' THEN 1 ELSE 0 END) as student_evaluations,
            SUM(CASE WHEN evaluator_type = 'teacher' THEN 1 ELSE 0 END) as teacher_evaluations,
            SUM(CASE WHEN evaluator_type = 'head' THEN 1 ELSE 0 END) as head_evaluations
            FROM evaluation_sessions
            $monthly_where
            GROUP BY DATE_FORMAT(evaluation_date, '%Y-%m')
            ORDER BY month";

        if (!empty($monthly_params)) {
            $stmt = mysqli_prepare($conn, $monthly_query);
            mysqli_stmt_bind_param($stmt, "ss", $monthly_params[0], $monthly_params[1]);
            mysqli_stmt_execute($stmt);
            $monthly_result = mysqli_stmt_get_result($stmt);
        } else {
            $monthly_result = mysqli_query($conn, $monthly_query);
        }

        while ($month = mysqli_fetch_assoc($monthly_result)) {
            fputcsv($output, [
                date('F Y', strtotime($month['month'] . '-01')),
                $month['total_evaluations'] ?? 0,
                $month['student_evaluations'] ?? 0,
                $month['teacher_evaluations'] ?? 0,
                $month['head_evaluations'] ?? 0
            ]);
        }
        break;

    default:
        fputcsv($output, ['Invalid report type']);
        break;
}

fclose($output);
exit;
?>