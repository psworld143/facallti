<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    // For debugging, let's see what's in the session
    echo "Session Debug:<br>";
    echo "user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "<br>";
    echo "role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'NOT SET') . "<br>";
    echo "Redirecting to login...<br>";
    header('Location: ../index.php');
    exit();
}

// Get filter parameters
$selected_faculty = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : 0;
$selected_priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$selected_status = isset($_GET['status']) ? $_GET['status'] : '';

// Build the query with filters
$where_conditions = ["f.is_active = 1"];
$params = [];
$param_types = "";

if ($selected_faculty > 0) {
    $where_conditions[] = "f.id = ?";
    $params[] = $selected_faculty;
    $param_types .= "i";
}

if ($selected_priority !== '') {
    $where_conditions[] = "ts.priority_level = ?";
    $params[] = $selected_priority;
    $param_types .= "s";
}

if ($selected_status !== '') {
    $where_conditions[] = "ts.status = ?";
    $params[] = $selected_status;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get training suggestions data with proper evaluator information
$suggestions_query = "SELECT
                       f.id as faculty_id,
                       f.first_name,
                       f.last_name,
                       f.email,
                       f.department,
                       ts.id as suggestion_id,
                       ts2.title as training_title,
                       ts2.type as training_type,
                       ts2.start_date,
                       ts2.end_date,
                       ts2.venue,
                       ts2.duration_hours,
                       ts2.cost,
                       esc.name as evaluation_category,
                       ts.evaluation_score,
                       ts.priority_level,
                       ts.status,
                       ts.suggestion_reason,
                       ts.suggestion_date,
                       u.first_name as suggested_by_first,
                       u.last_name as suggested_by_last,
                       mec.evaluation_type,
                       CASE 
                           WHEN mec.evaluation_type = 'student_to_teacher' THEN 'Student'
                           WHEN mec.evaluation_type = 'peer_to_peer' THEN 'Faculty'
                           WHEN mec.evaluation_type = 'head_to_teacher' THEN 'Head'
                           ELSE 'Unknown'
                       END as evaluator_type
                     FROM faculty f
                     JOIN training_suggestions ts ON f.id = ts.user_id
                     JOIN trainings_seminars ts2 ON ts.training_id = ts2.id
                     LEFT JOIN evaluation_sub_categories esc ON ts.evaluation_category_id = esc.id
                     LEFT JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
                     LEFT JOIN users u ON ts.suggested_by = u.id
                     WHERE $where_clause
                     ORDER BY f.last_name, f.first_name, ts.priority_level DESC, ts.suggestion_date DESC";

$suggestions_stmt = mysqli_prepare($conn, $suggestions_query);
if (!$suggestions_stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

if (!empty($params)) {
    mysqli_stmt_bind_param($suggestions_stmt, $param_types, ...$params);
}

if (!mysqli_stmt_execute($suggestions_stmt)) {
    die("Execute failed: " . mysqli_stmt_error($suggestions_stmt));
}

$suggestions_result = mysqli_stmt_get_result($suggestions_stmt);
if (!$suggestions_result) {
    die("Get result failed: " . mysqli_stmt_error($suggestions_stmt));
}

// Check if we have data
$row_count = mysqli_num_rows($suggestions_result);
if ($row_count == 0) {
    die("No training suggestions found with the current filters.");
}

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator('IntelliEVal System')
    ->setLastModifiedBy('Guidance Officer')
    ->setTitle('Training Suggestions Report')
    ->setSubject('Faculty Training Suggestions Export')
    ->setDescription('Export of training suggestions for faculty members with scores below 4.0')
    ->setKeywords('training suggestions faculty evaluation')
    ->setCategory('Reports');

// Set default column widths
$sheet->getColumnDimension('A')->setWidth(15); // Faculty ID
$sheet->getColumnDimension('B')->setWidth(20); // First Name
$sheet->getColumnDimension('C')->setWidth(20); // Last Name
$sheet->getColumnDimension('D')->setWidth(30); // Email
$sheet->getColumnDimension('E')->setWidth(25); // Department
$sheet->getColumnDimension('F')->setWidth(15); // Suggestion ID
$sheet->getColumnDimension('G')->setWidth(35); // Training Title
$sheet->getColumnDimension('H')->setWidth(15); // Training Type
$sheet->getColumnDimension('I')->setWidth(15); // Start Date
$sheet->getColumnDimension('J')->setWidth(15); // End Date
$sheet->getColumnDimension('K')->setWidth(25); // Venue
$sheet->getColumnDimension('L')->setWidth(15); // Duration
$sheet->getColumnDimension('M')->setWidth(15); // Cost
$sheet->getColumnDimension('N')->setWidth(25); // Evaluation Category
$sheet->getColumnDimension('O')->setWidth(15); // Evaluation Score
$sheet->getColumnDimension('P')->setWidth(15); // Priority Level
$sheet->getColumnDimension('Q')->setWidth(15); // Status
$sheet->getColumnDimension('R')->setWidth(50); // Suggestion Reason
$sheet->getColumnDimension('S')->setWidth(15); // Suggestion Date
$sheet->getColumnDimension('T')->setWidth(20); // Suggested By
$sheet->getColumnDimension('U')->setWidth(20); // Evaluation Type
$sheet->getColumnDimension('V')->setWidth(15); // Evaluator Type

// Create header row
$headers = [
    'Faculty ID', 'First Name', 'Last Name', 'Email', 'Department',
    'Suggestion ID', 'Training Title', 'Training Type', 'Start Date', 'End Date',
    'Venue', 'Duration (hrs)', 'Cost', 'Evaluation Category', 'Evaluation Score',
    'Priority Level', 'Status', 'Suggestion Reason', 'Suggestion Date', 'Suggested By',
    'Evaluation Type', 'Evaluator Type'
];

$headerRow = 1;
foreach ($headers as $colIndex => $header) {
    $column = chr(65 + $colIndex); // A, B, C, etc.
    $sheet->setCellValue($column . $headerRow, $header);
    
    // Style header
    $headerStyle = $sheet->getStyle($column . $headerRow);
    $headerStyle->getFont()->setBold(true)->setColor(new Color('FFFFFF'));
    $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('2C3E50'));
    $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $headerStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('34495E'));
}

// Add data rows
$row = 2;
$priorityColors = [
    'critical' => 'FF6B6B',
    'high' => 'FFA500',
    'medium' => 'FFD700',
    'low' => '90EE90'
];

$statusColors = [
    'pending' => 'FFF3CD',
    'accepted' => 'D1E7DD',
    'declined' => 'F8D7DA',
    'completed' => 'D1ECF1'
];

while ($suggestion = mysqli_fetch_assoc($suggestions_result)) {
    $sheet->setCellValue('A' . $row, $suggestion['faculty_id']);
    $sheet->setCellValue('B' . $row, $suggestion['first_name']);
    $sheet->setCellValue('C' . $row, $suggestion['last_name']);
    $sheet->setCellValue('D' . $row, $suggestion['email']);
    $sheet->setCellValue('E' . $row, $suggestion['department']);
    $sheet->setCellValue('F' . $row, $suggestion['suggestion_id']);
    $sheet->setCellValue('G' . $row, $suggestion['training_title']);
    $sheet->setCellValue('H' . $row, ucfirst($suggestion['training_type']));
    $sheet->setCellValue('I' . $row, $suggestion['start_date'] ? date('M d, Y', strtotime($suggestion['start_date'])) : 'N/A');
    $sheet->setCellValue('J' . $row, $suggestion['end_date'] ? date('M d, Y', strtotime($suggestion['end_date'])) : 'N/A');
    $sheet->setCellValue('K' . $row, $suggestion['venue'] ?: 'N/A');
    $sheet->setCellValue('L' . $row, $suggestion['duration_hours'] ?: 'N/A');
    $sheet->setCellValue('M' . $row, $suggestion['cost'] ? '₱' . number_format($suggestion['cost'], 2) : 'N/A');
    $sheet->setCellValue('N' . $row, $suggestion['evaluation_category'] ?: 'N/A');
    $sheet->setCellValue('O' . $row, $suggestion['evaluation_score'] ? round($suggestion['evaluation_score'], 2) : 'N/A');
    $sheet->setCellValue('P' . $row, ucfirst($suggestion['priority_level']));
    $sheet->setCellValue('Q' . $row, ucfirst($suggestion['status']));
    $sheet->setCellValue('R' . $row, $suggestion['suggestion_reason']);
    $sheet->setCellValue('S' . $row, date('M d, Y', strtotime($suggestion['suggestion_date'])));
    $sheet->setCellValue('T' . $row, $suggestion['suggested_by_first'] . ' ' . $suggestion['suggested_by_last']);
    $sheet->setCellValue('U' . $row, ucwords(str_replace('_', ' ', $suggestion['evaluation_type'])));
    $sheet->setCellValue('V' . $row, $suggestion['evaluator_type']);

    // Apply conditional formatting for priority levels
    if (isset($priorityColors[$suggestion['priority_level']])) {
        $sheet->getStyle('P' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new Color($priorityColors[$suggestion['priority_level']]));
    }

    // Apply conditional formatting for status
    if (isset($statusColors[$suggestion['status']])) {
        $sheet->getStyle('Q' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new Color($statusColors[$suggestion['status']]));
    }

    // Apply borders to all cells
    $dataStyle = $sheet->getStyle('A' . $row . ':V' . $row);
    $dataStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('BDC3C7'));

    $row++;
}

// Add summary information
$summaryRow = $row + 2;
$sheet->setCellValue('A' . $summaryRow, 'Export Summary');
$summaryHeaderStyle = $sheet->getStyle('A' . $summaryRow);
$summaryHeaderStyle->getFont()->setBold(true)->setSize(14);
$summaryHeaderStyle->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('ECF0F1'));

$summaryRow++;
$sheet->setCellValue('A' . $summaryRow, 'Total Suggestions:');
$sheet->setCellValue('B' . $summaryRow, $row - 2);

$summaryRow++;
$sheet->setCellValue('A' . $summaryRow, 'Export Date:');
$sheet->setCellValue('B' . $summaryRow, date('F d, Y \a\t g:i A'));

$summaryRow++;
$sheet->setCellValue('A' . $summaryRow, 'Exported By:');
$sheet->setCellValue('B' . $summaryRow, $_SESSION['first_name'] . ' ' . $_SESSION['last_name']);

// Add filters applied
if ($selected_faculty > 0 || $selected_priority !== '' || $selected_status !== '') {
    $summaryRow++;
    $sheet->setCellValue('A' . $summaryRow, 'Filters Applied:');
    
    $filters = [];
    if ($selected_faculty > 0) {
        $filters[] = 'Specific Faculty Member';
    }
    if ($selected_priority !== '') {
        $filters[] = 'Priority: ' . ucfirst($selected_priority);
    }
    if ($selected_status !== '') {
        $filters[] = 'Status: ' . ucfirst($selected_status);
    }
    
    $sheet->setCellValue('B' . $summaryRow, implode(', ', $filters));
}

// Set the header to download the file
$filename = 'training_suggestions_' . date('Y-m-d_H-i-s') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Create Excel file
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

exit();
?>
