<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
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
        ->setCreator('SEAIT IntelliEVal System')
        ->setLastModifiedBy('SEAIT IntelliEVal System')
        ->setTitle('Student Import Template - Guidance Officer')
        ->setSubject('Student Import Template')
        ->setDescription('Template for importing students into the system')
        ->setKeywords('student import template excel guidance')
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
        ['Student Import Template Instructions - Guidance Officer'],
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
        ['• Existing students will be skipped'],
        ['• Duplicate student IDs will be prevented'],
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
        ['• Students will be created with active status'],
        ['• The template includes sample data - replace with actual student data'],
        ['• Supports 500+ records with progress tracking'],
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
    $filename = 'student_import_template_guidance_' . date('Y-m-d') . '.xlsx';
    
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
    header('Location: import_students.php?error=template_generation_failed');
    exit();
}

exit();
?>
