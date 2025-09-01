<?php
// QR Code generation script for student IDs
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Include QR code library
require_once '../phpqrcode/qrlib.php';

// Get student ID from request
$studentId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($studentId)) {
    http_response_code(400);
    echo "Student ID is required";
    exit;
}

// Validate student ID (alphanumeric and common characters)
if (!preg_match('/^[A-Za-z0-9\-_\.]+$/', $studentId)) {
    http_response_code(400);
    echo "Invalid Student ID format";
    exit;
}

// Set content type to image
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400'); // Cache for 24 hours

// Generate QR code directly to output
// Parameters: data, filename (false for output), error correction level, pixel size, margin
QRcode::png($studentId, false, QR_ECLEVEL_L, 8, 1);
?>
