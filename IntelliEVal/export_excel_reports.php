<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'guidance_officer', 'head', 'teacher'])) {
    header("Location: " . get_login_path());
    exit();
}

$type = isset($_GET['type']) ? $_GET['type'] : 'performance';
$semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

// Get faculty ID if user is a teacher
$faculty_id = null;
if ($_SESSION['role'] === 'teacher') {
    $faculty_id = $_SESSION['user_id'];
}

// Set headers based on format
if ($format === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="faculty_report_' . date('Y-m-d') . '.pdf"');
} else {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="faculty_report_' . date('Y-m-d') . '.xlsx"');
}

// Include required libraries
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator('SEAIT LMS')
    ->setLastModifiedBy('SEAIT LMS')
    ->setTitle('Faculty Performance Report')
    ->setSubject('Faculty Performance Report')
    ->setDescription('Faculty performance report generated from SEAIT LMS');

// Set up the report based on type
switch ($type) {
    case 'performance':
        generatePerformanceReport($sheet, $faculty_id, $semester, $year);
        break;
    case 'evaluations':
        generateEvaluationsReport($sheet, $faculty_id, $semester, $year);
        break;
    default:
        generatePerformanceReport($sheet, $faculty_id, $semester, $year);
}

// Auto-size columns
foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Create the writer
if ($format === 'pdf') {
    // For PDF, we'll use HTML to PDF conversion
    // This is a simplified approach - in production you might want to use a proper PDF library
    $html = generateHTMLReport($sheet);
    echo $html; // This will be converted to PDF by the browser or a PDF service
} else {
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
}

function generatePerformanceReport($sheet, $faculty_id, $semester, $year) {
    // Set title
    $sheet->setCellValue('A1', 'SEAIT LMS - Faculty Performance Report');
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Set subtitle
    $sheet->setCellValue('A2', 'Generated on: ' . date('F j, Y g:i A'));
    $sheet->mergeCells('A2:F2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Add faculty filter info
    if ($faculty_id) {
        $faculty_query = "SELECT first_name, last_name, email FROM faculty WHERE id = ?";
        $stmt = mysqli_prepare($GLOBALS['conn'], $faculty_query);
        mysqli_stmt_bind_param($stmt, "i", $faculty_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $faculty = mysqli_fetch_assoc($result);

        $sheet->setCellValue('A3', 'Faculty: ' . $faculty['first_name'] . ' ' . $faculty['last_name'] . ' (' . $faculty['email'] . ')');
        $sheet->mergeCells('A3:F3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // Set headers
    $headers = ['Metric', 'Count', 'Percentage', 'Details', 'Last Updated', 'Notes'];
    $col = 'A';
    $row = 5;

    foreach ($headers as $header) {
        $sheet->setCellValue($col . $row, $header);
        $sheet->getStyle($col . $row)->getFont()->setBold(true);
        $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $col++;
    }

    // Get data
    $data = getPerformanceData($faculty_id, $semester, $year);

    // Add data rows
    $row = 6;
    foreach ($data as $metric) {
        $sheet->setCellValue('A' . $row, $metric['name']);
        $sheet->setCellValue('B' . $row, $metric['count']);
        $sheet->setCellValue('C' . $row, $metric['percentage'] . '%');
        $sheet->setCellValue('D' . $row, $metric['details']);
        $sheet->setCellValue('E' . $row, $metric['last_updated']);
        $sheet->setCellValue('F' . $row, $metric['notes']);
        $row++;
    }

    // Add borders
    $sheet->getStyle('A5:F' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

function generateEvaluationsReport($sheet, $faculty_id, $semester, $year) {
    // Similar structure to performance report but for evaluations
    $sheet->setCellValue('A1', 'SEAIT LMS - Faculty Evaluations Report');
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Add evaluation data
    $headers = ['Teacher', 'Subject', 'Semester', 'Average Rating', 'Total Evaluations', 'Last Evaluation'];
    $col = 'A';
    $row = 3;

    foreach ($headers as $header) {
        $sheet->setCellValue($col . $row, $header);
        $sheet->getStyle($col . $row)->getFont()->setBold(true);
        $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $col++;
    }

    // Get evaluation data
    $eval_data = getEvaluationData($faculty_id, $semester, $year);

    $row = 4;
    foreach ($eval_data as $eval) {
        $sheet->setCellValue('A' . $row, $eval['teacher_name']);
        $sheet->setCellValue('B' . $row, $eval['subject_name']);
        $sheet->setCellValue('C' . $row, $eval['semester']);
        $sheet->setCellValue('D' . $row, $eval['average_rating']);
        $sheet->setCellValue('E' . $row, $eval['total_evaluations']);
        $sheet->setCellValue('F' . $row, $eval['last_evaluation']);
        $row++;
    }

    $sheet->getStyle('A3:F' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

function getPerformanceData($faculty_id, $semester, $year) {
    $data = [];

    // Build WHERE clause
    $where_clause = "WHERE 1=1";
    $params = [];
    $types = "";

    if ($faculty_id) {
        $where_clause .= " AND tc.faculty_id = ?";
        $params[] = $faculty_id;
        $types .= "i";
    }

    if ($semester > 0) {
        $where_clause .= " AND ts.semester_id = ?";
        $params[] = $semester;
        $types .= "i";
    }

    if ($year > 0) {
        $where_clause .= " AND YEAR(tc.created_at) = ?";
        $params[] = $year;
        $types .= "i";
    }

    // Get class statistics
    $class_query = "SELECT COUNT(*) as count FROM teacher_classes tc $where_clause";
    $stmt = mysqli_prepare($GLOBALS['conn'], $class_query);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $class_count = mysqli_fetch_assoc($result)['count'];

    $data[] = [
        'name' => 'Total Classes Created',
        'count' => $class_count,
        'percentage' => 100,
        'details' => 'Classes created in the specified period',
        'last_updated' => date('Y-m-d H:i:s'),
        'notes' => 'Active and inactive classes included'
    ];

    // Get student enrollment statistics
    $student_query = "SELECT COUNT(DISTINCT ce.student_id) as count
                     FROM teacher_classes tc
                     LEFT JOIN class_enrollments ce ON tc.id = ce.class_id
                     $where_clause";
    $stmt = mysqli_prepare($GLOBALS['conn'], $student_query);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student_count = mysqli_fetch_assoc($result)['count'];

    $data[] = [
        'name' => 'Total Students Enrolled',
        'count' => $student_count,
        'percentage' => $class_count > 0 ? round(($student_count / $class_count) * 100, 2) : 0,
        'details' => 'Unique students enrolled in classes',
        'last_updated' => date('Y-m-d H:i:s'),
        'notes' => 'Based on class enrollments'
    ];

    // Get material statistics
    $material_query = "SELECT COUNT(*) as count FROM teacher_classes tc
                      LEFT JOIN class_materials cm ON tc.id = cm.class_id
                      $where_clause";
    $stmt = mysqli_prepare($GLOBALS['conn'], $material_query);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $material_count = mysqli_fetch_assoc($result)['count'];

    $data[] = [
        'name' => 'Total Materials Uploaded',
        'count' => $material_count,
        'percentage' => $class_count > 0 ? round(($material_count / $class_count) * 100, 2) : 0,
        'details' => 'Learning materials uploaded to classes',
        'last_updated' => date('Y-m-d H:i:s'),
        'notes' => 'Includes all file types'
    ];

    return $data;
}

function getEvaluationData($faculty_id, $semester, $year) {
    $data = [];

    $query = "SELECT
                CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                s.name as subject_name,
                sem.name as semester,
                AVG(er.rating) as average_rating,
                COUNT(DISTINCT es.id) as total_evaluations,
                MAX(es.created_at) as last_evaluation
              FROM evaluation_sessions es
              JOIN teachers t ON es.evaluatee_id = t.id
              JOIN teacher_subjects ts ON t.id = ts.teacher_id
              JOIN subjects s ON ts.subject_id = s.id
              JOIN semesters sem ON ts.semester_id = sem.id
              LEFT JOIN evaluation_responses er ON es.id = er.session_id
              WHERE es.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
              GROUP BY t.id, s.id, sem.id
              ORDER BY average_rating DESC";

    $result = mysqli_query($GLOBALS['conn'], $query);

    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'teacher_name' => $row['teacher_name'],
            'subject_name' => $row['subject_name'],
            'semester' => $row['semester'],
            'average_rating' => round($row['average_rating'], 2),
            'total_evaluations' => $row['total_evaluations'],
            'last_evaluation' => date('M j, Y', strtotime($row['last_evaluation']))
        ];
    }

    return $data;
}

function generateHTMLReport($sheet) {
    // Convert spreadsheet data to HTML for PDF generation
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Faculty Performance Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin-bottom: 20px; }
            .title { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
            .subtitle { font-size: 14px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="title">SEAIT LMS - Faculty Performance Report</div>
            <div class="subtitle">Generated on: ' . date('F j, Y g:i A') . '</div>
        </div>
        <table>
            <thead>
                <tr>';

    // Add headers
    $highestColumn = $sheet->getHighestColumn();
    $highestRow = $sheet->getHighestRow();

    for ($col = 'A'; $col <= $highestColumn; $col++) {
        $cellValue = $sheet->getCell($col . '5')->getValue();
        $html .= '<th>' . htmlspecialchars($cellValue) . '</th>';
    }

    $html .= '</tr></thead><tbody>';

    // Add data rows
    for ($row = 6; $row <= $highestRow; $row++) {
        $html .= '<tr>';
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $cellValue = $sheet->getCell($col . $row)->getValue();
            $html .= '<td>' . htmlspecialchars($cellValue) . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table></body></html>';

    return $html;
}
?>