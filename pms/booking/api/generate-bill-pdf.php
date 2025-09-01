<?php
session_start();
require_once '../../includes/error_handler.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has front desk access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['front_desk', 'manager'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if bill ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Bill ID is required']);
    exit();
}

$bill_id = (int)$_GET['id'];

try {
    // Get bill details
    $bill = getBillDetails($bill_id);
    if (!$bill) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Bill not found']);
        exit();
    }

    // Get reservation details
    $reservation = getReservationDetails($bill['reservation_id']);
    if (!$reservation) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Reservation not found']);
        exit();
    }

    // Get guest details
    $guest = getGuestDetails($reservation['guest_id']);
    if (!$guest) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Guest not found']);
        exit();
    }

    // Get room details
    $room = getRoomDetails($reservation['room_id']);
    if (!$room) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Room not found']);
        exit();
    }

    // Get billing details
    $billing_details = getBillingDetails($bill['reservation_id']);
    
    // Get additional services
    $additional_services = getAdditionalServicesForReservation($bill['reservation_id']);

    // Calculate nights
    $nights = (strtotime($reservation['check_out_date']) - strtotime($reservation['check_in_date'])) / (60 * 60 * 24);

    // Include TCPDF library
    require_once '../../../vendor/tecnickcom/tcpdf/tcpdf.php';

    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('Hotel PMS');
    $pdf->SetAuthor('Hotel Management System');
    $pdf->SetTitle('Bill #' . $bill['bill_number']);
    $pdf->SetSubject('Hotel Bill');

    // Set default header data
    $pdf->SetHeaderData('', 0, 'HOTEL BILL', 'Generated on ' . date('F d, Y g:i A'));

    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Hotel Information
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'HOTEL MANAGEMENT SYSTEM', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 6, '123 Hotel Street, City, Philippines', 0, 1, 'C');
    $pdf->Cell(0, 6, 'Phone: +63 123 456 7890 | Email: info@hotel.com', 0, 1, 'C');
    $pdf->Ln(10);

    // Bill Header
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'BILL / INVOICE', 0, 1, 'C');
    $pdf->Ln(5);

    // Bill Information
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Bill Information', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(40, 6, 'Bill Number:', 0, 0);
    $pdf->Cell(0, 6, $bill['bill_number'], 0, 1);
    
    $pdf->Cell(40, 6, 'Bill Date:', 0, 0);
    $pdf->Cell(0, 6, date('F d, Y', strtotime($bill['bill_date'])), 0, 1);
    
    $pdf->Cell(40, 6, 'Due Date:', 0, 0);
    $pdf->Cell(0, 6, date('F d, Y', strtotime($bill['due_date'])), 0, 1);
    
    $pdf->Cell(40, 6, 'Status:', 0, 0);
    $pdf->Cell(0, 6, ucfirst($bill['status']), 0, 1);
    $pdf->Ln(5);

    // Guest Information
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Guest Information', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(40, 6, 'Guest Name:', 0, 0);
    $pdf->Cell(0, 6, $guest['first_name'] . ' ' . $guest['last_name'], 0, 1);
    
    $pdf->Cell(40, 6, 'Email:', 0, 0);
    $pdf->Cell(0, 6, $guest['email'] ?? 'Not provided', 0, 1);
    
    $pdf->Cell(40, 6, 'Phone:', 0, 0);
    $pdf->Cell(0, 6, $guest['phone'], 0, 1);
    
    if ($guest['is_vip']) {
        $pdf->Cell(40, 6, 'VIP Status:', 0, 0);
        $pdf->Cell(0, 6, 'VIP Guest', 0, 1);
    }
    $pdf->Ln(5);

    // Reservation Information
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Reservation Information', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(40, 6, 'Reservation #:', 0, 0);
    $pdf->Cell(0, 6, $reservation['reservation_number'], 0, 1);
    
    $pdf->Cell(40, 6, 'Room Number:', 0, 0);
    $pdf->Cell(0, 6, $room['room_number'], 0, 1);
    
    $pdf->Cell(40, 6, 'Room Type:', 0, 0);
    $pdf->Cell(0, 6, ucfirst(str_replace('_', ' ', $room['room_type'])), 0, 1);
    
    $pdf->Cell(40, 6, 'Check-in:', 0, 0);
    $pdf->Cell(0, 6, date('F d, Y', strtotime($reservation['check_in_date'])), 0, 1);
    
    $pdf->Cell(40, 6, 'Check-out:', 0, 0);
    $pdf->Cell(0, 6, date('F d, Y', strtotime($reservation['check_out_date'])), 0, 1);
    
    $pdf->Cell(40, 6, 'Nights:', 0, 0);
    $pdf->Cell(0, 6, $nights . ' night(s)', 0, 1);
    
    $pdf->Cell(40, 6, 'Guests:', 0, 0);
    $pdf->Cell(0, 6, $reservation['adults'] . ' adult(s), ' . $reservation['children'] . ' child(ren)', 0, 1);
    $pdf->Ln(5);

    // Bill Items Table
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Bill Items', 0, 1, 'L');
    $pdf->Ln(2);

    // Table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(80, 8, 'Description', 1, 0, 'L', true);
    $pdf->Cell(30, 8, 'Quantity', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Rate', 1, 0, 'R', true);
    $pdf->Cell(40, 8, 'Amount', 1, 1, 'R', true);

    // Room charges
    $pdf->SetFont('helvetica', '', 10);
    $room_rate = getRoomTypes()[$room['room_type']]['rate'];
    $room_total = $room_rate * $nights;
    
    $pdf->Cell(80, 8, 'Room Charges (' . ucfirst(str_replace('_', ' ', $room['room_type'])) . ')', 1, 0, 'L');
    $pdf->Cell(30, 8, $nights . ' night(s)', 1, 0, 'C');
    $pdf->Cell(40, 8, '₱' . number_format($room_rate, 2), 1, 0, 'R');
    $pdf->Cell(40, 8, '₱' . number_format($room_total, 2), 1, 1, 'R');

    // Additional services
    if (!empty($additional_services)) {
        foreach ($additional_services as $service) {
            $service_name = $service['notes'] ?? 'Additional Service';
            $pdf->Cell(80, 8, $service_name, 1, 0, 'L');
            $pdf->Cell(30, 8, $service['quantity'], 1, 0, 'C');
            $pdf->Cell(40, 8, '₱' . number_format($service['unit_price'], 2), 1, 0, 'R');
            $pdf->Cell(40, 8, '₱' . number_format($service['total_price'], 2), 1, 1, 'R');
        }
    }

    // Subtotal
    $subtotal = $room_total + ($billing_details['services_total'] ?? 0);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(150, 8, 'Subtotal:', 1, 0, 'R');
    $pdf->Cell(40, 8, '₱' . number_format($subtotal, 2), 1, 1, 'R');

    // Tax
    $tax = $billing_details['taxes'] ?? 0;
    $tax_percentage = $subtotal > 0 ? round(($tax / $subtotal) * 100, 0) : 10;
    $pdf->Cell(150, 8, 'Tax (' . $tax_percentage . '%):', 1, 0, 'R');
    $pdf->Cell(40, 8, '₱' . number_format($tax, 2), 1, 1, 'R');

    // Discount
    if ($bill['discount_amount'] > 0) {
        $pdf->Cell(150, 8, 'Discount:', 1, 0, 'R');
        $pdf->Cell(40, 8, '-₱' . number_format($bill['discount_amount'], 2), 1, 1, 'R');
    }

    // Total
    $total = $subtotal + $tax - $bill['discount_amount'];
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell(150, 10, 'TOTAL:', 1, 0, 'R', true);
    $pdf->Cell(40, 10, '₱' . number_format($total, 2), 1, 1, 'R', true);
    $pdf->Ln(10);

    // Payment Information
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Payment Information', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(40, 6, 'Payment Due:', 0, 0);
    $pdf->Cell(0, 6, '₱' . number_format($total, 2), 0, 1);
    
    $pdf->Cell(40, 6, 'Due Date:', 0, 0);
    $pdf->Cell(0, 6, date('F d, Y', strtotime($bill['due_date'])), 0, 1);
    
    $pdf->Cell(40, 6, 'Payment Status:', 0, 0);
    $pdf->Cell(0, 6, ucfirst($bill['status']), 0, 1);
    $pdf->Ln(10);

    // Terms and Conditions
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Terms and Conditions', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    
    $terms = [
        'Payment is due upon receipt of this bill.',
        'Late payments may incur additional charges.',
        'All rates are subject to applicable taxes.',
        'Cancellation policies apply as per hotel terms.',
        'For questions, please contact the front desk.'
    ];
    
    foreach ($terms as $term) {
        $pdf->Cell(0, 5, '• ' . $term, 0, 1);
    }
    $pdf->Ln(5);

    // Footer
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 6, 'Thank you for choosing our hotel!', 0, 1, 'C');
    $pdf->Cell(0, 6, 'Generated on ' . date('F d, Y g:i A'), 0, 1, 'C');

    // Output PDF
    $filename = 'Bill_' . $bill['bill_number'] . '_' . date('Y-m-d') . '.pdf';
    $pdf->Output($filename, 'D'); // 'D' for download

} catch (Exception $e) {
    error_log("Error generating bill PDF: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error generating PDF: ' . $e->getMessage()
    ]);
}
?>
