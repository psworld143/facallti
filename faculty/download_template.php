<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Get class_id from URL
$class_id = safe_decrypt_id($_GET['class_id']);

if (!$class_id) {
    header('Location: class-management.php');
    exit();
}

// Verify the class belongs to the logged-in teacher
$class_query = "SELECT tc.*, cc.subject_title, cc.subject_code, cc.units, cc.description as subject_description
                FROM teacher_classes tc
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                JOIN faculty f ON tc.teacher_id = f.id
                WHERE tc.id = ? AND f.email = ? AND f.is_active = 1";
$class_stmt = mysqli_prepare($conn, $class_query);
mysqli_stmt_bind_param($class_stmt, "is", $class_id, $_SESSION['username']);
mysqli_stmt_execute($class_stmt);
$class_result = mysqli_stmt_get_result($class_stmt);
$class_data = mysqli_fetch_assoc($class_result);

if (!$class_data) {
    header('Location: class-management.php');
    exit();
}

// Require PhpSpreadsheet
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;

try {
    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('SEAIT Faculty Portal')
        ->setLastModifiedBy('SEAIT Faculty Portal')
        ->setTitle('Student Import Template - ' . $class_data['subject_title'])
        ->setSubject('Student Import Template')
        ->setDescription('Template for importing students into ' . $class_data['subject_title'])
        ->setKeywords('student import template excel')
        ->setCategory('Student Management');
    
    // Set headers
    $headers = ['ID Number', 'Firstname', 'Lastname'];
    $sheet->fromArray($headers, NULL, 'A1');
    
    // Style the header row
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FF6B35'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];
    
    $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);
    
    // Add sample data
    $sampleData = [
        ['2021-0001', 'John', 'Doe'],
        ['2021-0002', 'Jane', 'Smith'],
        ['2021-0003', 'Mike', 'Johnson'],
        ['2021-0004', 'Sarah', 'Williams'],
        ['2021-0005', 'David', 'Brown'],
    ];
    
    $sheet->fromArray($sampleData, NULL, 'A2');
    
    // Style sample data
    $sampleStyle = [
        'font' => [
            'color' => ['rgb' => '666666'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F8F9FA'],
        ],
    ];
    
    $sheet->getStyle('A2:C6')->applyFromArray($sampleStyle);
    
    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(15);
    
    // Add instructions sheet
    $instructionsSheet = $spreadsheet->createSheet();
    $instructionsSheet->setTitle('Instructions');
    
    $instructions = [
        ['Student Import Template Instructions'],
        [''],
        ['File Format Requirements:'],
        ['• Excel format (.xlsx or .xls)'],
        ['• Use the provided template'],
        ['• Columns must be in order: ID Number, Firstname, Lastname'],
        ['• No empty rows or missing data'],
        [''],
        ['Student Creation Process:'],
        ['• New students will be created automatically'],
        ['• Default password: Seait123'],
        ['• Email format: firstname.lastname@seait.edu.ph'],
        ['• If email exists, a number will be added (e.g., john.doe1@seait.edu.ph)'],
        ['• Existing students will only be enrolled in the class'],
        ['• Duplicate enrollments will be prevented'],
        [''],
        ['Sample Data Format:'],
        ['ID Number', 'Firstname', 'Lastname'],
        ['2021-0001', 'John', 'Doe'],
        ['2021-0002', 'Jane', 'Smith'],
        ['2021-0003', 'Mike', 'Johnson'],
        [''],
        ['Important Notes:'],
        ['• ID Number must be unique'],
        ['• Firstname and Lastname are required'],
        ['• Students will be automatically enrolled in the selected class'],
        ['• The template includes sample data - replace with actual student data'],
    ];
    
    $instructionsSheet->fromArray($instructions, NULL, 'A1');
    
    // Style instructions
    $instructionsSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $instructionsSheet->getStyle('A3')->getFont()->setBold(true);
    $instructionsSheet->getStyle('A9')->getFont()->setBold(true);
    $instructionsSheet->getStyle('A16')->getFont()->setBold(true);
    $instructionsSheet->getStyle('A23')->getFont()->setBold(true);
    
    // Set column width for instructions
    $instructionsSheet->getColumnDimension('A')->setWidth(50);
    
    // Set the first sheet as active
    $spreadsheet->setActiveSheetIndex(0);
    
    // Create the Excel file
    $writer = new Xlsx($spreadsheet);
    
    // Set headers for download
    $filename = 'student_import_template_' . $class_data['subject_code'] . '_' . date('Y-m-d') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');
    
    // Output the file
    $writer->save('php://output');
    
} catch (Exception $e) {
    // If there's an error, redirect back with error message
    header('Location: import_students.php?class_id=' . $class_id . '&error=template_generation_failed');
    exit();
}

exit();
?>
