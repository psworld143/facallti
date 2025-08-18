<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer or head role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['guidance_officer', 'head'])) {
    header('Location: ../index.php');
    exit();
}

// Function to map evaluation types between main_categories and evaluation_schedules tables
function mapEvaluationType($mainCategoryType) {
    $mapping = [
        'student_to_teacher' => 'student_to_teacher',
        'peer_to_peer' => 'teacher_to_teacher',
        'head_to_teacher' => 'head_to_teacher'
    ];

    return $mapping[$mainCategoryType] ?? $mainCategoryType;
}

// Function to normalize department names for comparison
function normalizeDepartment($department) {
    if (empty($department)) return '';
    
    $dept = strtolower(trim($department));
    
    // Remove common prefixes and suffixes
    $dept = preg_replace('/^(department of|college of|school of)\s+/i', '', $dept);
    $dept = preg_replace('/\s+(department|college|school)$/i', '', $dept);
    
    // Normalize common variations
    $normalizations = [
        'computer science' => 'computer science',
        'information technology' => 'information technology',
        'information and communication technology' => 'information technology',
        'ict' => 'information technology',
        'business' => 'business',
        'business and good governance' => 'business',
        'engineering' => 'engineering',
        'electronics engineering' => 'electronics engineering',
        'information systems' => 'information systems',
        'mathematics' => 'mathematics',
        'math' => 'mathematics',
        'english' => 'english',
        'history' => 'history',
        'science' => 'science'
    ];
    
    return $normalizations[$dept] ?? $dept;
}

// Function to calculate actual evaluation sessions for display
function calculateActualSessions($evaluators, $evaluatees, $evaluation_type) {
    $total_sessions = 0;

    foreach ($evaluators as $evaluator) {
        foreach ($evaluatees as $evaluatee) {
            // Skip self-evaluations
            if ($evaluator['id'] == $evaluatee['id']) {
                continue;
            }

            // For Peer to Peer evaluations, check department matching
            if ($evaluation_type === 'peer_to_peer') {
                $evaluator_department = normalizeDepartment($evaluator['department'] ?? '');
                $evaluatee_department = normalizeDepartment($evaluatee['department'] ?? '');

                if (empty($evaluator_department) || empty($evaluatee_department) ||
                    $evaluator_department !== $evaluatee_department) {
                    continue;
                }
            }

            $total_sessions++;
        }
    }

    return $total_sessions;
}

// Set page title
$page_title = 'Conduct Evaluation';

$message = '';
$message_type = '';

// Get main_category_id from URL
$main_category_id = isset($_GET['main_category_id']) ? (int)$_GET['main_category_id'] : null;

if (!$main_category_id) {
    header('Location: evaluations.php');
    exit();
}

// Get main category details
$category_query = "SELECT * FROM main_evaluation_categories WHERE id = ? AND status = 'active'";
$category_stmt = mysqli_prepare($conn, $category_query);
mysqli_stmt_bind_param($category_stmt, "i", $main_category_id);
mysqli_stmt_execute($category_stmt);
$category_result = mysqli_stmt_get_result($category_stmt);
$main_category = mysqli_fetch_assoc($category_result);

if (!$main_category) {
    $message = "Main evaluation category not found or inactive.";
    $message_type = "error";
} else {
    // Get sub-categories for this main category
    $sub_categories_query = "SELECT * FROM evaluation_sub_categories
                            WHERE main_category_id = ? AND status = 'active'
                            ORDER BY order_number ASC";
    $sub_categories_stmt = mysqli_prepare($conn, $sub_categories_query);
    if (!$sub_categories_stmt) {
        $message = "Database error: " . mysqli_error($conn);
        $message_type = "error";
    } else {
        mysqli_stmt_bind_param($sub_categories_stmt, "i", $main_category_id);
        mysqli_stmt_execute($sub_categories_stmt);
        $sub_categories_result = mysqli_stmt_get_result($sub_categories_stmt);
        $sub_categories = [];
        while ($row = mysqli_fetch_assoc($sub_categories_result)) {
            $sub_categories[] = $row;
        }
    }

    // Get questionnaires for each sub-category
    $questionnaires = [];
    if (empty($message)) { // Only proceed if no previous errors
        foreach ($sub_categories as $sub_category) {
            $questionnaires_query = "SELECT * FROM evaluation_questionnaires
                                    WHERE sub_category_id = ? AND status = 'active'
                                    ORDER BY order_number ASC";
            $questionnaires_stmt = mysqli_prepare($conn, $questionnaires_query);
            if (!$questionnaires_stmt) {
                $message = "Database error: " . mysqli_error($conn);
                $message_type = "error";
                break;
            }
            mysqli_stmt_bind_param($questionnaires_stmt, "i", $sub_category['id']);
            mysqli_stmt_execute($questionnaires_stmt);
            $questionnaires_result = mysqli_stmt_get_result($questionnaires_stmt);

            $sub_category_questionnaires = [];
            while ($row = mysqli_fetch_assoc($questionnaires_result)) {
                $sub_category_questionnaires[] = $row;
            }
            $questionnaires[$sub_category['id']] = $sub_category_questionnaires;
        }
    }

    // Get available evaluators based on evaluation type
    $evaluators = [];
    if (empty($message)) { // Only proceed if no previous errors
        switch ($main_category['evaluation_type']) {
            case 'student_to_teacher':
                // Students from students table evaluate teachers
                $evaluators_query = "SELECT s.id, s.first_name, s.last_name, s.email, 'student' as role FROM students s WHERE s.status = 'active' ORDER BY s.last_name, s.first_name";
                break;
            case 'peer_to_peer':
                // Teachers from faculty table evaluate other teachers
                $evaluators_query = "SELECT f.id, f.first_name, f.last_name, f.email, f.department, 'teacher' as role
                                    FROM faculty f
                                    WHERE f.is_active = 1
                                    ORDER BY f.last_name, f.first_name";
                break;
            case 'head_to_teacher':
                // Heads from users table evaluate teachers - include department from heads table
                $evaluators_query = "SELECT u.id, u.first_name, u.last_name, u.email, u.role, h.department
                                    FROM users u
                                    LEFT JOIN heads h ON u.id = h.user_id
                                    WHERE u.role = 'head' AND u.status = 'active'
                                    ORDER BY u.last_name, u.first_name";
                break;
            default:
                $evaluators_query = "SELECT id, first_name, last_name, email, role FROM users ORDER BY last_name, first_name";
        }

        $evaluators_result = mysqli_query($conn, $evaluators_query);
        if (!$evaluators_result) {
            $message = "Database error: " . mysqli_error($conn);
            $message_type = "error";
        } else {
            while ($row = mysqli_fetch_assoc($evaluators_result)) {
                $evaluators[] = $row;
            }
        }

        // Debug logging for evaluators
        if ($main_category['evaluation_type'] === 'head_to_teacher') {
            error_log("Loaded " . count($evaluators) . " heads as evaluators:");
            foreach ($evaluators as $evaluator) {
                $normalized_dept = normalizeDepartment($evaluator['department'] ?? '');
                error_log("  - {$evaluator['first_name']} {$evaluator['last_name']} (Original Dept: '{$evaluator['department']}', Normalized: '{$normalized_dept}')");
            }
        }
    }

    // Get available evaluatees based on evaluation type
    $evaluatees = [];
    if (empty($message)) { // Only proceed if no previous errors
        switch ($main_category['evaluation_type']) {
            case 'student_to_teacher':
                // Students evaluate teachers from faculty table
                $evaluatees_query = "SELECT f.id, f.first_name, f.last_name, f.email, 'teacher' as role
                                    FROM faculty f
                                    WHERE f.is_active = 1
                                    ORDER BY f.last_name, f.first_name";
                break;
            case 'head_to_teacher':
                // Heads evaluate teachers from faculty table
                $evaluatees_query = "SELECT f.id, f.first_name, f.last_name, f.email, f.department, 'teacher' as role
                                    FROM faculty f
                                    WHERE f.is_active = 1
                                    ORDER BY f.last_name, f.first_name";
                break;
            case 'peer_to_peer':
                // Teachers evaluate other teachers from faculty table - same department only
                $evaluatees_query = "SELECT f.id, f.first_name, f.last_name, f.email, f.department, 'teacher' as role
                                    FROM faculty f
                                    WHERE f.is_active = 1
                                    ORDER BY f.last_name, f.first_name";
                break;
            default:
                $evaluatees_query = "SELECT f.id, f.first_name, f.last_name, f.email, 'teacher' as role
                                    FROM faculty f
                                    WHERE f.is_active = 1
                                    ORDER BY f.last_name, f.first_name";
        }

        $evaluatees_result = mysqli_query($conn, $evaluatees_query);
        if (!$evaluatees_result) {
            $message = "Database error: " . mysqli_error($conn);
            $message_type = "error";
        } else {
            while ($row = mysqli_fetch_assoc($evaluatees_result)) {
                $evaluatees[] = $row;
            }
        }

        // Debug logging for evaluatees
        if ($main_category['evaluation_type'] === 'head_to_teacher') {
            error_log("Loaded " . count($evaluatees) . " teachers as evaluatees:");
            foreach ($evaluatees as $evaluatee) {
                $normalized_dept = normalizeDepartment($evaluatee['department'] ?? '');
                error_log("  - {$evaluatee['first_name']} {$evaluatee['last_name']} (Original Dept: '{$evaluatee['department']}', Normalized: '{$normalized_dept}')");
            }
        }
    }

    // Get available semesters
    $semesters = [];
    if (empty($message)) { // Only proceed if no previous errors
        $semesters_query = "SELECT * FROM semesters WHERE status = 'active' ORDER BY start_date DESC";
        $semesters_result = mysqli_query($conn, $semesters_query);
        if (!$semesters_result) {
            $message = "Database error: " . mysqli_error($conn);
            $message_type = "error";
        } else {
            while ($row = mysqli_fetch_assoc($semesters_result)) {
                $semesters[] = $row;
            }
        }
    }

    // Get current semester based on current date
    $current_semester = null;
    if (empty($message)) {
        $current_date = date('Y-m-d');
        $current_semester_query = "SELECT * FROM semesters
                                  WHERE status = 'active'
                                  AND ? BETWEEN start_date AND end_date
                                  ORDER BY start_date DESC
                                  LIMIT 1";
        $current_semester_stmt = mysqli_prepare($conn, $current_semester_query);
        mysqli_stmt_bind_param($current_semester_stmt, "s", $current_date);
        mysqli_stmt_execute($current_semester_stmt);
        $current_semester_result = mysqli_stmt_get_result($current_semester_stmt);
        $current_semester = mysqli_fetch_assoc($current_semester_result);

        if (!$current_semester) {
            $message = "No active semester found for the current date. Please ensure there's an active semester that includes today's date.";
            $message_type = "error";
        }
    }

    // Handle form submission for creating evaluation session
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

        if ($_POST['action'] === 'create_session') {
            $start_datetime = $_POST['start_datetime'];
            $end_datetime = $_POST['end_datetime'];
            $notes = sanitize_input($_POST['notes'] ?? '');

            // Validate datetime inputs
            if (empty($start_datetime) || empty($end_datetime)) {
                $message = "Start and end date/time are required.";
                $message_type = "error";
            } elseif (strtotime($end_datetime) <= strtotime($start_datetime)) {
                $message = "End date/time must be after start date/time.";
                $message_type = "error";
            } elseif (!$current_semester) {
                $message = "No active semester found for the current date. Please ensure there's an active semester that includes today's date.";
                $message_type = "error";
            } else {
                // Start transaction
                mysqli_begin_transaction($conn);

                try {
                    $sessions_created = 0;
                    $errors = [];

                    // Use current date for evaluation_date
                    $evaluation_date = date('Y-m-d');

                    // First, create or activate an evaluation schedule for this evaluation type
                    $mapped_evaluation_type = mapEvaluationType($main_category['evaluation_type']);
                    $schedule_check = "SELECT id FROM evaluation_schedules
                                      WHERE evaluation_type = ? AND semester_id = ?";
                    $schedule_stmt = mysqli_prepare($conn, $schedule_check);
                    mysqli_stmt_bind_param($schedule_stmt, "si", $mapped_evaluation_type, $current_semester['id']);
                    mysqli_stmt_execute($schedule_stmt);
                    $schedule_result = mysqli_stmt_get_result($schedule_stmt);

                    if (mysqli_num_rows($schedule_result) > 0) {
                        // Update existing schedule to active
                        $schedule = mysqli_fetch_assoc($schedule_result);
                        $update_schedule = "UPDATE evaluation_schedules
                                           SET status = 'active', start_date = ?, end_date = ?, updated_at = NOW()
                                           WHERE id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_schedule);
                        mysqli_stmt_bind_param($update_stmt, "ssi", $start_datetime, $end_datetime, $schedule['id']);
                        mysqli_stmt_execute($update_stmt);
                    } else {
                        // Create new active schedule
                        $create_schedule = "INSERT INTO evaluation_schedules
                                           (semester_id, evaluation_type, start_date, end_date, status, created_by)
                                           VALUES (?, ?, ?, ?, 'active', ?)";
                        $create_schedule_stmt = mysqli_prepare($conn, $create_schedule);
                        mysqli_stmt_bind_param($create_schedule_stmt, "isssi", $current_semester['id'], $mapped_evaluation_type, $start_datetime, $end_datetime, $_SESSION['user_id']);
                        mysqli_stmt_execute($create_schedule_stmt);
                    }

                    // Create evaluation sessions for all eligible evaluator-evaluatee combinations
                    $total_combinations = 0;
                    $skipped_combinations = 0;

                    // Validate required variables
                    if (!isset($evaluation_date) || empty($evaluation_date)) {
                        $evaluation_date = date('Y-m-d');
                        error_log("evaluation_date was not set, using current date: " . $evaluation_date);
                    }

                    if (!isset($notes)) {
                        $notes = '';
                        error_log("notes was not set, using empty string");
                    }

                    error_log("Starting evaluation session creation with evaluation_date: " . $evaluation_date . ", notes: " . $notes);

                    // First, delete all existing evaluation sessions for this category and semester
                    // Also delete related evaluation responses to prevent orphaned data

                    // Get session IDs to delete responses first
                    $get_session_ids = "SELECT id FROM evaluation_sessions
                                       WHERE main_category_id = ? AND semester_id = ?";
                    $get_session_stmt = mysqli_prepare($conn, $get_session_ids);
                    mysqli_stmt_bind_param($get_session_stmt, "ii", $main_category_id, $current_semester['id']);
                    mysqli_stmt_execute($get_session_stmt);
                    $session_ids_result = mysqli_stmt_get_result($get_session_stmt);

                    $deleted_responses = 0;
                    while ($session = mysqli_fetch_assoc($session_ids_result)) {
                        // Delete related evaluation responses first (due to foreign key constraints)
                        $delete_responses = "DELETE FROM evaluation_responses WHERE evaluation_session_id = ?";
                        $delete_responses_stmt = mysqli_prepare($conn, $delete_responses);
                        mysqli_stmt_bind_param($delete_responses_stmt, "i", $session['id']);
                        mysqli_stmt_execute($delete_responses_stmt);
                        $deleted_responses += mysqli_affected_rows($conn);
                    }

                    // Now delete the evaluation sessions
                    $delete_existing_query = "DELETE FROM evaluation_sessions
                                             WHERE main_category_id = ? AND semester_id = ?";
                    $delete_stmt = mysqli_prepare($conn, $delete_existing_query);
                    mysqli_stmt_bind_param($delete_stmt, "ii", $main_category_id, $current_semester['id']);

                    if (mysqli_stmt_execute($delete_stmt)) {
                        $deleted_count = mysqli_stmt_affected_rows($delete_stmt);
                        error_log("Deleted {$deleted_count} existing evaluation sessions and {$deleted_responses} related responses for category {$main_category_id} and semester {$current_semester['id']}");
                    } else {
                        $errors[] = "Error deleting existing sessions: " . mysqli_error($conn);
                        error_log("Error deleting existing sessions: " . mysqli_error($conn));
                    }

                    foreach ($evaluators as $evaluator) {
                        foreach ($evaluatees as $evaluatee) {
                            $total_combinations++;

                            // Skip if evaluator and evaluatee are the same person
                            if ($evaluator['id'] == $evaluatee['id']) {
                                $skipped_combinations++;
                                continue;
                            }

                            // Debug logging for head to teacher evaluations
                            if ($main_category['evaluation_type'] === 'head_to_teacher') {
                                $normalized_evaluator_dept = normalizeDepartment($evaluator['department'] ?? '');
                                $normalized_evaluatee_dept = normalizeDepartment($evaluatee['department'] ?? '');
                                error_log("Checking combination: Head {$evaluator['first_name']} {$evaluator['last_name']} (ID: {$evaluator['id']}, Original Dept: '{$evaluator['department']}', Normalized: '{$normalized_evaluator_dept}') evaluating Teacher {$evaluatee['first_name']} {$evaluatee['last_name']} (ID: {$evaluatee['id']}, Original Dept: '{$evaluatee['department']}', Normalized: '{$normalized_evaluatee_dept}')");
                            }

                            // For Peer to Peer evaluations, check if both are from the same department
                            if ($main_category['evaluation_type'] === 'peer_to_peer') {
                                // Since we now include department in the queries, we can check directly
                                $evaluator_department = normalizeDepartment($evaluator['department'] ?? '');
                                $evaluatee_department = normalizeDepartment($evaluatee['department'] ?? '');

                                // Skip if departments don't match or if either doesn't have a department
                                if (empty($evaluator_department) || empty($evaluatee_department) ||
                                    $evaluator_department !== $evaluatee_department) {
                                    $skipped_combinations++;
                                    continue;
                                }
                            }

                            // For Student to Teacher evaluations, check if student is enrolled in teacher's class
                            if ($main_category['evaluation_type'] === 'student_to_teacher') {
                                // Since we're getting students directly from students table, we can use their ID directly
                                $student_id = $evaluator['id'];

                                // Check if student is enrolled in any class taught by this teacher
                                $enrollment_check = "SELECT ce.id FROM class_enrollments ce
                                                    JOIN teacher_classes tc ON ce.class_id = tc.id
                                                    WHERE ce.student_id = ? AND tc.teacher_id = ? AND ce.status = 'enrolled'";
                                $enrollment_stmt = mysqli_prepare($conn, $enrollment_check);
                                mysqli_stmt_bind_param($enrollment_stmt, "ii", $student_id, $evaluatee['id']);
                                mysqli_stmt_execute($enrollment_stmt);
                                $enrollment_result = mysqli_stmt_get_result($enrollment_stmt);

                                if (mysqli_num_rows($enrollment_result) == 0) {
                                    $skipped_combinations++;
                                    error_log("Student {$evaluator['email']} is not enrolled in any class taught by teacher {$evaluatee['id']}");
                                    continue;
                                }
                            }

                            // For Head to Teacher evaluations, check if head's department matches evaluatee's department
                            if ($main_category['evaluation_type'] === 'head_to_teacher') {
                                $evaluator_department = normalizeDepartment($evaluator['department'] ?? '');
                                $evaluatee_department = normalizeDepartment($evaluatee['department'] ?? '');

                                error_log("Head to Teacher department check - Head: {$evaluator['first_name']} {$evaluator['last_name']} (Original Dept: '{$evaluator['department']}', Normalized: '{$evaluator_department}') evaluating Teacher: {$evaluatee['first_name']} {$evaluatee['last_name']} (Original Dept: '{$evaluatee['department']}', Normalized: '{$evaluatee_department}')");

                                if (empty($evaluator_department) || empty($evaluatee_department) ||
                                    $evaluator_department !== $evaluatee_department) {
                                    $skipped_combinations++;
                                    error_log("Skipped: Department mismatch or empty - Head dept: '{$evaluator_department}', Teacher dept: '{$evaluatee_department}'");
                                    continue;
                                }
                                
                                error_log("Department match found: '{$evaluator_department}'");
                            }

                            // Create new evaluation session (no need to check for existing ones since we deleted them)
                            error_log("Creating new session for evaluator {$evaluator['id']} evaluating {$evaluatee['id']}");

                            $create_session_query = "INSERT INTO evaluation_sessions
                                                    (evaluator_id, evaluator_type, evaluatee_id, evaluatee_type,
                                                     main_category_id, semester_id, subject_id, evaluation_date, notes, status)
                                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')";
                            $create_session_stmt = mysqli_prepare($conn, $create_session_query);

                            if (!$create_session_stmt) {
                                $error_msg = "Failed to prepare statement: " . mysqli_error($conn);
                                $errors[] = $error_msg;
                                error_log($error_msg);
                                continue;
                            }

                            // Create variables for null values to pass by reference
                            $subject_id = null;

                            // Ensure role values match enum constraints exactly
                            $evaluator_type = $evaluator['role'];
                            $evaluatee_type = $evaluatee['role'];

                            // Debug logging
                            error_log("Creating session - Evaluator ID: {$evaluator['id']}, Type: {$evaluator_type}, Evaluatee ID: {$evaluatee['id']}, Type: {$evaluatee_type}");

                            $bind_result = mysqli_stmt_bind_param($create_session_stmt, "isssiiiss",
                                $evaluator['id'], $evaluator_type, $evaluatee['id'], $evaluatee_type,
                                $main_category_id, $current_semester['id'], $subject_id, $evaluation_date, $notes);

                            if (!$bind_result) {
                                $error_msg = "Failed to bind parameters: " . mysqli_stmt_error($create_session_stmt);
                                $errors[] = $error_msg;
                                error_log($error_msg);
                                continue;
                            }

                            if (mysqli_stmt_execute($create_session_stmt)) {
                                $sessions_created++;
                                error_log("Successfully created session #{$sessions_created}");
                            } else {
                                $error_msg = "Error creating session for " . $evaluator['first_name'] . " " . $evaluator['last_name'] .
                                           " evaluating " . $evaluatee['first_name'] . " " . $evaluatee['last_name'] . ": " . mysqli_error($conn);
                                $errors[] = $error_msg;
                                error_log($error_msg);
                            }
                        }
                    }

                    // error_log("Total combinations checked: {$total_combinations}, Sessions created: {$sessions_created}, Skipped: {$skipped_combinations}");

                    if ($sessions_created > 0) {
                        mysqli_commit($conn);

                        $message = "Successfully created " . $sessions_created . " evaluation session(s) and activated evaluation period from " . date('M d, Y H:i', strtotime($start_datetime)) . " to " . date('M d, Y H:i', strtotime($end_datetime)) . "!";

                        if (isset($deleted_count) && $deleted_count > 0) {
                            $message .= " (Deleted " . $deleted_count . " existing session(s) and " . $deleted_responses . " related response(s) before creating new ones)";
                        }

                        if (!empty($errors)) {
                            $message .= " Some errors occurred: " . implode(", ", $errors);
                        }
                        $message_type = "success";

                        // Redirect back to evaluations page to show the updated status
                        $_SESSION['message'] = $message;
                        $_SESSION['message_type'] = $message_type;
                        header('Location: evaluations.php');
                        exit();
                    } else {
                        mysqli_rollback($conn);
                        $message = "No new evaluation sessions were created. ";
                        if ($skipped_combinations > 0) {
                            if ($main_category['evaluation_type'] === 'student_to_teacher') {
                                $message .= "All " . $skipped_combinations . " combinations were skipped because students are not enrolled in the teachers' classes. Students can only evaluate teachers they are enrolled with.";
                            } elseif ($main_category['evaluation_type'] === 'head_to_teacher') {
                                $message .= "All " . $skipped_combinations . " combinations were skipped because heads can only evaluate teachers from the same department. Department heads must match the teachers' departments.";
                            } else {
                                $message .= "All " . $skipped_combinations . " eligible combinations were skipped (self-evaluations and department mismatches for peer evaluations).";
                            }
                        } else {
                            if ($main_category['evaluation_type'] === 'student_to_teacher') {
                                $message .= "No eligible evaluator-evaluatee combinations found. Students can only evaluate teachers they are enrolled with.";
                            } elseif ($main_category['evaluation_type'] === 'head_to_teacher') {
                                $message .= "No eligible evaluator-evaluatee combinations found. Heads can only evaluate teachers from the same department.";
                            } else {
                                $message .= "No eligible evaluator-evaluatee combinations found.";
                            }
                        }
                        $message_type = "warning";
                    }

                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $message = "Error creating evaluation sessions: " . $e->getMessage();
                    $message_type = "error";
                }
            }
        }
    }

    // Get existing evaluation sessions for this category
    $existing_sessions = [];
    if (empty($message)) { // Only proceed if no previous errors
        $existing_sessions_query = "SELECT es.*,
                                   CASE
                                       WHEN es.evaluator_type = 'student' THEN evaluator_s.first_name
                                       WHEN es.evaluator_type = 'teacher' THEN evaluator_f.first_name
                                       ELSE evaluator_u.first_name
                                   END as evaluator_first_name,
                                   CASE
                                       WHEN es.evaluator_type = 'student' THEN evaluator_s.last_name
                                       WHEN es.evaluator_type = 'teacher' THEN evaluator_f.last_name
                                       ELSE evaluator_u.last_name
                                   END as evaluator_last_name,
                                   CASE
                                       WHEN es.evaluatee_type = 'teacher' THEN evaluatee_f.first_name
                                       ELSE evaluatee_u.first_name
                                   END as evaluatee_first_name,
                                   CASE
                                       WHEN es.evaluatee_type = 'teacher' THEN evaluatee_f.last_name
                                       ELSE evaluatee_u.last_name
                                   END as evaluatee_last_name,
                                   s.name as semester_name
                                   FROM evaluation_sessions es
                                   LEFT JOIN students evaluator_s ON es.evaluator_id = evaluator_s.id AND es.evaluator_type = 'student'
                                   LEFT JOIN faculty evaluator_f ON es.evaluator_id = evaluator_f.id AND es.evaluator_type = 'teacher'
                                   LEFT JOIN users evaluator_u ON es.evaluator_id = evaluator_u.id AND es.evaluator_type = 'head'
                                   LEFT JOIN faculty evaluatee_f ON es.evaluatee_id = evaluatee_f.id AND es.evaluatee_type = 'teacher'
                                   LEFT JOIN users evaluatee_u ON es.evaluatee_id = evaluatee_u.id AND es.evaluatee_type != 'teacher'
                                   LEFT JOIN semesters s ON es.semester_id = s.id
                                   WHERE es.main_category_id = ? AND es.semester_id = ?
                                   ORDER BY es.created_at DESC";
        $existing_sessions_stmt = mysqli_prepare($conn, $existing_sessions_query);
        mysqli_stmt_bind_param($existing_sessions_stmt, "ii", $main_category_id, $current_semester['id']);
        mysqli_stmt_execute($existing_sessions_stmt);
        $existing_sessions_result = mysqli_stmt_get_result($existing_sessions_stmt);

        while ($row = mysqli_fetch_assoc($existing_sessions_result)) {
            $existing_sessions[] = $row;
        }
    }
}

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Conduct Evaluation</h1>
            <p class="text-sm sm:text-base text-gray-600">
                <?php echo $main_category ? htmlspecialchars($main_category['name']) : 'Category Not Found'; ?>
            </p>
        </div>
        <a href="evaluations.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Evaluations
        </a>
    </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($main_category): ?>
    <!-- Category Information -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Category Details</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Category Name</h3>
                    <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($main_category['name']); ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Evaluation Type</h3>
                    <p class="text-lg font-semibold text-gray-900">
                        <?php echo ucwords(str_replace('_', ' ', $main_category['evaluation_type'])); ?>
                    </p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Description</h3>
                    <p class="text-gray-900"><?php echo htmlspecialchars($main_category['description'] ?? 'No description available'); ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Sub-Categories</h3>
                    <p class="text-lg font-semibold text-gray-900"><?php echo count($sub_categories); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Create New Evaluation Session -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Start Evaluation Period</h2>
        </div>
        <div class="p-6">
            <!-- Evaluation Summary -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h3 class="text-lg font-medium text-blue-900 mb-2">Evaluation Summary</h3>
                <p class="text-blue-700 text-sm">
                    <i class="fas fa-info-circle mr-1"></i>
                    This will delete all existing evaluation sessions for this category and semester, then create new evaluation sessions for all eligible evaluator-evaluatee combinations and activate the evaluation period.
                </p>
            </div>

            <!-- Detailed Breakdown -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-3">Evaluation Statistics</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white p-4 rounded-lg border">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-users text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total Evaluators</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo count($evaluators); ?></p>
                                <p class="text-xs text-gray-500"><?php echo ucfirst($main_category['evaluation_type'] === 'student_to_teacher' ? 'students' : ($main_category['evaluation_type'] === 'peer_to_peer' ? 'teachers' : 'heads')); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg border">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-user-tie text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total Evaluatees</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo count($evaluatees); ?></p>
                                <p class="text-xs text-gray-500">teachers</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg border">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-clipboard-list text-orange-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total Sessions</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php
                                    if ($main_category['evaluation_type'] === 'peer_to_peer') {
                                        echo calculateActualSessions($evaluators, $evaluatees, $main_category['evaluation_type']);
                                    } else {
                                        echo count($evaluators) * count($evaluatees);
                                    }
                                    ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?php if ($main_category['evaluation_type'] === 'peer_to_peer'): ?>
                                        (same department only)
                                    <?php else: ?>
                                        evaluation sessions
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Department Breakdown for Peer to Peer -->
                <?php if ($main_category['evaluation_type'] === 'peer_to_peer'): ?>
                <div class="mt-4 p-4 bg-purple-50 border border-purple-200 rounded-lg">
                    <h3 class="text-lg font-medium text-purple-900 mb-3">Department Breakdown</h3>
                    <?php
                    $department_counts = [];
                    foreach ($evaluators as $evaluator) {
                        $dept = isset($evaluator['department']) ? trim($evaluator['department']) : 'Unknown';
                        if (!isset($department_counts[$dept])) {
                            $department_counts[$dept] = 0;
                        }
                        $department_counts[$dept]++;
                    }
                    ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php foreach ($department_counts as $dept => $count): ?>
                        <div class="bg-white p-3 rounded-lg border border-purple-200">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-purple-800"><?php echo htmlspecialchars($dept); ?></span>
                                <span class="text-lg font-bold text-purple-900"><?php echo $count; ?></span>
                            </div>
                            <p class="text-xs text-purple-600 mt-1">
                                <?php
                                $dept_sessions = 0;
                                foreach ($evaluators as $evaluator) {
                                    if (isset($evaluator['department']) && trim($evaluator['department']) === $dept) {
                                        foreach ($evaluatees as $evaluatee) {
                                            if (isset($evaluatee['department']) && trim($evaluatee['department']) === $dept && $evaluator['id'] != $evaluatee['id']) {
                                                $dept_sessions++;
                                            }
                                        }
                                    }
                                }
                                echo $dept_sessions . ' sessions';
                                ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-sm text-purple-700 mt-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        Teachers can only evaluate other teachers within their own department.
                    </p>
                </div>
                <?php endif; ?>

                <!-- Department Breakdown for Head to Teacher -->
                <?php if ($main_category['evaluation_type'] === 'head_to_teacher'): ?>
                <div class="mt-4 p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                    <h3 class="text-lg font-medium text-indigo-900 mb-3">Department Breakdown</h3>
                    <?php
                    $department_counts = [];
                    foreach ($evaluators as $evaluator) {
                        $dept = isset($evaluator['department']) ? trim($evaluator['department']) : 'Unknown';
                        if (!isset($department_counts[$dept])) {
                            $department_counts[$dept] = 0;
                        }
                        $department_counts[$dept]++;
                    }
                    ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php foreach ($department_counts as $dept => $count): ?>
                        <div class="bg-white p-3 rounded-lg border border-indigo-200">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-indigo-800"><?php echo htmlspecialchars($dept); ?></span>
                                <span class="text-lg font-bold text-indigo-900"><?php echo $count; ?></span>
                            </div>
                            <p class="text-xs text-indigo-600 mt-1">
                                <?php
                                $dept_sessions = 0;
                                foreach ($evaluators as $evaluator) {
                                    if (isset($evaluator['department']) && trim($evaluator['department']) === $dept) {
                                        foreach ($evaluatees as $evaluatee) {
                                            if (isset($evaluatee['department']) && trim($evaluatee['department']) === $dept) {
                                                $dept_sessions++;
                                            }
                                        }
                                    }
                                }
                                echo $dept_sessions . ' sessions';
                                ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-sm text-indigo-700 mt-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        Department heads can only evaluate teachers within their own department.
                    </p>
                </div>
                <?php endif; ?>

                <!-- Existing Sessions Info -->
                <?php if (!empty($existing_sessions)): ?>
                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-2"></i>
                        <div class="text-sm text-yellow-700">
                            <p class="font-medium mb-1">Warning: Existing Sessions Found</p>
                            <p>There are already <?php echo count($existing_sessions); ?> evaluation session(s) for this category and semester.
                            <strong>All existing sessions will be deleted and replaced with new ones.</strong> This action cannot be undone.</p>
                            <?php if ($main_category['evaluation_type'] === 'peer_to_peer'): ?>
                            <p class="mt-2 text-yellow-600">
                                <i class="fas fa-info-circle mr-1"></i>
                                New sessions will only be created for teachers within the same department.
                            </p>
                            <?php endif; ?>
                            <?php if ($main_category['evaluation_type'] === 'head_to_teacher'): ?>
                            <p class="mt-2 text-yellow-600">
                                <i class="fas fa-info-circle mr-1"></i>
                                New sessions will only be created for department heads evaluating teachers from their own department.
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-500 mt-1 mr-2"></i>
                        <div class="text-sm text-blue-700">
                            <p class="font-medium mb-1">Evaluation Process:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>Each evaluator will evaluate each evaluatee</li>
                                <li>Self-evaluations are automatically excluded</li>
                                <?php if ($main_category['evaluation_type'] === 'peer_to_peer'): ?>
                                <li>For Peer to Peer evaluations, only teachers from the same department can evaluate each other</li>
                                <?php endif; ?>
                                <?php if ($main_category['evaluation_type'] === 'head_to_teacher'): ?>
                                <li>For Head to Teacher evaluations, only department heads can evaluate teachers from their own department</li>
                                <?php endif; ?>
                                <li>Evaluation sessions will be created in draft status</li>
                                <li>Participants can complete evaluations during the active period</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <form id="evaluationForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?main_category_id=' . $main_category_id); ?>" class="space-y-6">
                <input type="hidden" name="action" value="create_session">

                <!-- Current Semester Information -->
                <?php if ($current_semester): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h3 class="text-lg font-medium text-green-900 mb-2">Current Semester</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="font-medium text-green-800">Semester:</span>
                            <span class="text-green-700"><?php echo htmlspecialchars($current_semester['name']); ?></span>
                        </div>
                        <div>
                            <span class="font-medium text-green-800">Period:</span>
                            <span class="text-green-700">
                                <?php echo date('M d, Y', strtotime($current_semester['start_date'])); ?> -
                                <?php echo date('M d, Y', strtotime($current_semester['end_date'])); ?>
                            </span>
                        </div>
                        <div>
                            <span class="font-medium text-green-800">Status:</span>
                            <span class="text-green-700">Active</span>
                        </div>
                    </div>
                    <p class="text-green-700 text-sm mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        The evaluation will be automatically associated with this semester based on the current date.
                    </p>
                </div>
                <?php endif; ?>

                <!-- Evaluation Time Frame -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="text-lg font-medium text-blue-900 mb-3">Evaluation Time Frame</h3>
                    <p class="text-blue-700 text-sm mb-4">
                        <i class="fas fa-info-circle mr-1"></i>
                        Set the start and end dates for the evaluation period. The evaluation will automatically be disabled when the end time is reached.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date & Time</label>
                            <input type="datetime-local" name="start_datetime" required
                                   value="<?php echo date('Y-m-d\TH:i'); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <p class="text-xs text-gray-500 mt-1">When the evaluation period begins</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Date & Time</label>
                            <input type="datetime-local" name="end_datetime" required
                                   value="<?php echo date('Y-m-d\TH:i', strtotime('+7 days')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <p class="text-xs text-gray-500 mt-1">When the evaluation period ends (auto-disabled)</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                    <textarea name="notes" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                              placeholder="Add any additional notes for all evaluation sessions..."></textarea>
                </div>

                <div class="flex justify-end">
                    <button type="button" onclick="showStartEvaluationModal('<?php echo htmlspecialchars($main_category['name']); ?>')"
                            class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition">
                        <i class="fas <?php echo !empty($existing_sessions) ? 'fa-redo' : 'fa-play'; ?> mr-2"></i><?php echo !empty($existing_sessions) ? 'Restart Evaluation' : 'Start Evaluation Period'; ?>
                    </button>
                    <!-- Hidden submit button for JavaScript fallback -->
                    <button type="submit" id="hiddenSubmitBtn" style="display: none;">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Evaluation Structure Preview -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Evaluation Structure</h2>
        </div>
        <div class="p-6">
            <?php if (empty($sub_categories)): ?>
                <p class="text-gray-500 text-center py-4">No sub-categories defined for this category yet.</p>
            <?php else: ?>
                <!-- Tab Navigation -->
                <div class="border-b border-gray-200 mb-6">
                    <nav class="-mb-px flex space-x-8 overflow-x-auto" aria-label="Tabs">
                        <?php foreach ($sub_categories as $index => $sub_category): ?>
                            <button onclick="switchTab(<?php echo $index; ?>)"
                                    class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200 <?php echo $index === 0 ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>"
                                    id="tab-<?php echo $index; ?>">
                                <?php echo htmlspecialchars($sub_category['name']); ?>
                                <?php
                                $questionnaire_count = isset($questionnaires[$sub_category['id']]) ? count($questionnaires[$sub_category['id']]) : 0;
                                if ($questionnaire_count > 0):
                                ?>
                                    <span class="ml-2 bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs">
                                        <?php echo $questionnaire_count; ?> <?php echo $questionnaire_count === 1 ? 'question' : 'questions'; ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="tab-content-container">
                    <?php foreach ($sub_categories as $index => $sub_category): ?>
                        <div class="tab-content <?php echo $index === 0 ? 'block' : 'hidden'; ?>" id="content-<?php echo $index; ?>">
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                                <h3 class="text-lg font-medium text-gray-900 mb-2">
                                    <i class="fas fa-folder-open text-seait-orange mr-2"></i>
                                    <?php echo htmlspecialchars($sub_category['name']); ?>
                                </h3>
                                <?php if ($sub_category['description']): ?>
                                    <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($sub_category['description']); ?></p>
                                <?php endif; ?>

                                <?php
                                $sub_questionnaires = $questionnaires[$sub_category['id']] ?? [];
                                if (empty($sub_questionnaires)): ?>
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                                            <span class="text-yellow-700">No questionnaires defined for this sub-category.</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <h4 class="text-md font-medium text-gray-900">Questionnaires</h4>
                                            <span class="text-sm text-gray-500"><?php echo count($sub_questionnaires); ?> total</span>
                                        </div>

                                        <div class="space-y-3">
                                            <?php foreach ($sub_questionnaires as $q_index => $questionnaire): ?>
                                                <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-200">
                                                    <div class="flex items-start space-x-3">
                                                        <div class="flex-shrink-0 w-8 h-8 bg-seait-orange text-white rounded-full flex items-center justify-center text-sm font-medium">
                                                            <?php echo $q_index + 1; ?>
                                                        </div>
                                                        <div class="flex-1">
                                                            <p class="text-sm font-medium text-gray-900 mb-2">
                                                                <?php echo htmlspecialchars($questionnaire['question_text']); ?>
                                                            </p>
                                                            <div class="flex items-center space-x-4 text-xs">
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                    <i class="fas fa-tag mr-1"></i>
                                                                    <?php echo ucwords(str_replace('_', ' ', $questionnaire['question_type'])); ?>
                                                                </span>
                                                                <?php if ($questionnaire['required']): ?>
                                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                        <i class="fas fa-asterisk mr-1"></i>
                                                                        Required
                                                                    </span>
                                                                <?php endif; ?>
                                                                <span class="text-gray-500">
                                                                    Order: <?php echo $questionnaire['order_number']; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Existing Evaluation Sessions -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Existing Evaluation Sessions</h2>
        </div>
        <div class="p-6">
            <?php if (empty($existing_sessions)): ?>
                <p class="text-gray-500 text-center py-4">No evaluation sessions created for this category yet.</p>
            <?php else: ?>
                <!-- Session Statistics -->
                <?php
                $session_stats = [
                    'draft' => 0,
                    'completed' => 0,
                    'archived' => 0
                ];
                foreach ($existing_sessions as $session) {
                    $session_stats[$session['status']]++;
                }
                ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-edit text-yellow-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-yellow-800">Draft</p>
                                <p class="text-2xl font-bold text-yellow-900"><?php echo $session_stats['draft']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-check text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-green-800">Completed</p>
                                <p class="text-2xl font-bold text-green-900"><?php echo $session_stats['completed']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-archive text-gray-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Archived</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $session_stats['archived']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluator</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluatee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($existing_sessions as $session): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        #<?php echo $session['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($session['evaluator_first_name'] . ' ' . $session['evaluator_last_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($session['evaluatee_first_name'] . ' ' . $session['evaluatee_last_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('M d, Y', strtotime($session['evaluation_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full <?php
                                            echo $session['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                                ($session['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                                        ?>">
                                            <?php echo ucfirst($session['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <span class="text-gray-500">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </span>
                                        <?php if ($session['status'] === 'draft'): ?>
                                            <span class="text-gray-500 ml-3">
                                                <i class="fas fa-edit mr-1"></i>Edit
                                            </span>
                                        <?php endif; ?>
                                        <span class="text-xs text-gray-400 ml-2">(Files not yet implemented)</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- Category Not Found -->
    <div class="bg-red-50 rounded-lg p-6 text-center">
        <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
        <h3 class="text-lg font-medium text-red-900 mb-2">Category Not Found</h3>
        <p class="text-red-700">The specified evaluation category could not be found or is inactive.</p>
        <a href="evaluations.php" class="inline-block mt-4 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
            Return to Evaluations
        </a>
    </div>
<?php endif; ?>

<!-- Start Evaluation Confirmation Modal -->
<div id="startEvaluationModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 modal-backdrop">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all duration-300 scale-95 opacity-0 modal-content-start" id="modalContent">
            <div class="p-6 text-center">
                <div class="mb-4">
                    <div class="p-4 rounded-full bg-blue-100 text-blue-600 inline-block mb-4">
                        <i class="fas <?php echo !empty($existing_sessions) ? 'fa-redo' : 'fa-play'; ?> text-3xl icon-pulse"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2"><?php echo !empty($existing_sessions) ? 'Restart Evaluation' : 'Start Evaluation'; ?></h3>
                    <p class="text-gray-600 mb-4">Are you sure you want to <?php echo !empty($existing_sessions) ? 'restart' : 'start'; ?> the evaluation for "<span id="evaluationCategoryName" class="font-semibold"></span>"?</p>

                    <?php if (!empty($existing_sessions)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                        <div class="flex items-center text-red-800">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span class="text-sm font-medium">Warning: Existing Sessions Will Be Deleted</span>
                        </div>
                        <p class="text-sm text-red-700 mt-1">
                            This action will permanently delete <?php echo count($existing_sessions); ?> existing evaluation session(s) and create new ones. This cannot be undone.
                        </p>
                    </div>
                    <?php endif; ?>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                        <div class="flex items-center text-blue-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span class="text-sm font-medium">What will happen:</span>
                        </div>
                        <ul class="text-sm text-blue-700 mt-2 text-left space-y-1">
                            <li class="flex items-center">
                                <i class="fas fa-trash mr-2 text-blue-500"></i>
                                All existing evaluation sessions will be deleted
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-users mr-2 text-blue-500"></i>
                                New evaluation sessions will be created for all eligible participants
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-calendar mr-2 text-blue-500"></i>
                                The evaluation period will be activated with your specified dates
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-clipboard-check mr-2 text-blue-500"></i>
                                Participants can then begin their evaluations
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="flex justify-center space-x-3">
                    <button type="button" onclick="closeStartEvaluationModal()"
                            class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 btn-hover-scale">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="button" onclick="confirmStartEvaluation()"
                            class="px-6 py-3 bg-gradient-to-r from-seait-orange to-orange-600 text-white rounded-lg hover:from-orange-600 hover:to-orange-700 transition-all duration-200 font-semibold btn-hover-scale">
                        <i class="fas <?php echo !empty($existing_sessions) ? 'fa-redo' : 'fa-play'; ?> mr-2"></i><?php echo !empty($existing_sessions) ? 'Restart Evaluation' : 'Start Evaluation'; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .modal-show {
        transform: scale(1);
        opacity: 1;
    }

    /* Enhanced modal animations */
    @keyframes modalFadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes modalSlideUp {
        from {
            transform: translateY(20px) scale(0.95);
            opacity: 0;
        }
        to {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
    }

    @keyframes modalBounceIn {
        0% {
            transform: scale(0.3) translateY(-50px);
            opacity: 0;
        }
        50% {
            transform: scale(1.05) translateY(0);
            opacity: 1;
        }
        70% {
            transform: scale(0.9) translateY(0);
        }
        100% {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
    }

    @keyframes modalShake {
        0%, 100% {
            transform: translateX(0);
        }
        10%, 30%, 50%, 70%, 90% {
            transform: translateX(-2px);
        }
        20%, 40%, 60%, 80% {
            transform: translateX(2px);
        }
    }

    /* Modal backdrop animation */
    .modal-backdrop {
        animation: modalFadeIn 0.3s ease-out;
    }

    /* Modal content animations */
    .modal-content-start {
        animation: modalBounceIn 0.5s ease-out;
    }

    /* Button hover animations */
    .btn-hover-scale {
        transition: all 0.2s ease-in-out;
    }

    .btn-hover-scale:hover {
        transform: scale(1.05);
    }

    .btn-hover-scale:active {
        transform: scale(0.95);
    }

    /* Icon animations */
    .icon-pulse {
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
        100% {
            transform: scale(1);
        }
    }
</style>

<script>
    let currentEvaluationData = {};

    function showStartEvaluationModal(categoryName) {
        document.getElementById('evaluationCategoryName').textContent = categoryName;
        const modal = document.getElementById('startEvaluationModal');
        const modalContent = document.getElementById('modalContent');

        modal.classList.remove('hidden');
        // Trigger animation after a small delay
        setTimeout(() => {
            modalContent.classList.add('modal-show');
            // Add bounce effect
            modalContent.style.animation = 'modalBounceIn 0.5s ease-out';
        }, 10);
    }

    function closeStartEvaluationModal() {
        const modal = document.getElementById('startEvaluationModal');
        const modalContent = document.getElementById('modalContent');

        // Add exit animation
        modalContent.style.animation = 'modalSlideUp 0.3s ease-in reverse';
        modalContent.classList.remove('modal-show');

        // Wait for animation to complete before hiding
        setTimeout(() => {
            modal.classList.add('hidden');
            modalContent.style.animation = '';
        }, 300);

        currentEvaluationData = {};
    }

    function confirmStartEvaluation() {
        // Add button click animation
        const button = event.target.closest('button');
        if (button) {
            button.style.transform = 'scale(0.95)';
            setTimeout(() => {
                button.style.transform = '';
            }, 150);
        }

        // Small delay for animation before submitting form
        setTimeout(() => {
            closeStartEvaluationModal();
            // Submit the form - use the form ID
            const form = document.getElementById('evaluationForm');
            if (form) {

                // Ensure all required fields are filled
                const requiredFields = form.querySelectorAll('input[required], textarea[required]');
                let isValid = true;
                let firstInvalidField = null;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        if (!firstInvalidField) {
                            firstInvalidField = field;
                        }
                        field.classList.add('border-red-500');
                    } else {
                        field.classList.remove('border-red-500');
                    }
                });

                if (isValid) {

                    // Show loading state
                    const submitButton = form.querySelector('button[type="button"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Starting Evaluation...';
                    }

                    // Try to submit the form using the hidden submit button
                    const hiddenSubmitBtn = document.getElementById('hiddenSubmitBtn');
                    if (hiddenSubmitBtn) {
                        hiddenSubmitBtn.click();
                    } else {
                        // Fallback to direct form submission
                        form.submit();
                    }
                } else {
                    // Show error message if validation fails
                    alert('Please fill in all required fields before starting the evaluation.');

                    // Focus on the first invalid field
                    if (firstInvalidField) {
                        firstInvalidField.focus();
                    }

                    // Reopen modal
                    setTimeout(() => {
                        showStartEvaluationModal(document.getElementById('evaluationCategoryName').textContent);
                    }, 100);
                }
            } else {
                alert('Error: Evaluation form not found. Please refresh the page and try again.');
            }
        }, 200);
    }

    // Close modal when clicking outside
    const startEvaluationModal = document.getElementById('startEvaluationModal');

    if (startEvaluationModal) {
        startEvaluationModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeStartEvaluationModal();
            }
        });
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeStartEvaluationModal();
        }
    });

    // Add hover effects to action buttons
    document.addEventListener('DOMContentLoaded', function() {
        const actionButtons = document.querySelectorAll('.btn-hover-scale');
        actionButtons.forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
            });

            button.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });

            button.addEventListener('mousedown', function() {
                this.style.transform = 'scale(0.95)';
            });

            button.addEventListener('mouseup', function() {
                this.style.transform = 'scale(1.05)';
            });
        });
    });

    // Tab switching functionality
    function switchTab(tabIndex) {
        // Hide all tab contents
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => {
            content.classList.add('hidden');
            content.classList.remove('block');
        });

        // Remove active state from all tab buttons
        const tabButtons = document.querySelectorAll('.tab-button');
        tabButtons.forEach(button => {
            button.classList.remove('border-seait-orange', 'text-seait-orange');
            button.classList.add('border-transparent', 'text-gray-500');
        });

        // Show selected tab content
        const selectedContent = document.getElementById('content-' + tabIndex);
        if (selectedContent) {
            selectedContent.classList.remove('hidden');
            selectedContent.classList.add('block');
        }

        // Add active state to selected tab button
        const selectedButton = document.getElementById('tab-' + tabIndex);
        if (selectedButton) {
            selectedButton.classList.remove('border-transparent', 'text-gray-500');
            selectedButton.classList.add('border-seait-orange', 'text-seait-orange');
        }
    }
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>