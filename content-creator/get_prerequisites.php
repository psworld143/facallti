<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a content creator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'content_creator') {
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

// Get parameters
$course_id = (int)($_POST['course_id'] ?? 0);
$search_term = trim($_POST['search_term'] ?? '');
$load_all = isset($_POST['load_all']) && $_POST['load_all'] == '1';

if ($course_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
    exit();
}

try {
    if ($load_all) {
        // Load all subjects for the course (for dropdown population)
        $query = "SELECT id, subject_code, subject_title, year_level, semester
                  FROM course_curriculum
                  WHERE course_id = ?
                  ORDER BY year_level ASC, semester ASC, subject_code ASC";

        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $course_id);
    } else {
        // Search functionality (if search term is provided)
        if (empty($search_term)) {
            echo json_encode(['success' => false, 'message' => 'Search term is required']);
            exit();
        }

        $query = "SELECT id, subject_code, subject_title, year_level, semester
                  FROM course_curriculum
                  WHERE course_id = ?
                  AND (subject_code LIKE ? OR subject_title LIKE ?)
                  ORDER BY year_level ASC, semester ASC, subject_code ASC
                  LIMIT 10";

        $stmt = mysqli_prepare($conn, $query);
        $search_pattern = "%$search_term%";
        mysqli_stmt_bind_param($stmt, 'iss', $course_id, $search_pattern, $search_pattern);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $prerequisites = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $prerequisites[] = [
            'id' => $row['id'],
            'subject_code' => $row['subject_code'],
            'subject_title' => $row['subject_title'],
            'year_level' => $row['year_level'],
            'semester' => $row['semester']
        ];
    }

    mysqli_stmt_close($stmt);

    echo json_encode([
        'success' => true,
        'prerequisites' => $prerequisites
    ]);

} catch (Exception $e) {
    error_log('Error in get_prerequisites.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>