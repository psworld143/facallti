<?php
require_once 'config/database.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Get teacher ID from URL parameter
$teacher_id = $_GET['id'] ?? null;

if (!$teacher_id) {
    echo "<h1>Teacher QR Code Generator</h1>";
    echo "<p>Please provide a teacher ID in the URL: ?id=TEACHER_ID</p>";
    exit();
}

// Validate teacher ID
if (!is_numeric($teacher_id)) {
    echo "<h1>Error</h1>";
    echo "<p>Invalid teacher ID format. Please provide a numeric ID.</p>";
    exit();
}

// Get teacher details
$query = "SELECT id, first_name, last_name, department, position, qrcode FROM faculty WHERE id = ? AND is_active = 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    echo "<h1>Error</h1>";
    echo "<p>Teacher not found or inactive.</p>";
    exit();
}

$teacher = mysqli_fetch_assoc($result);

// Generate QR code if not exists
if (empty($teacher['qrcode'])) {
    $currentYear = date('Y');
    $qrCode = $currentYear . '-' . str_pad($teacher['id'], 4, '0', STR_PAD_LEFT);
    
    $updateQuery = "UPDATE faculty SET qrcode = ? WHERE id = ?";
    $updateStmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($updateStmt, "si", $qrCode, $teacher_id);
    mysqli_stmt_execute($updateStmt);
    
    $teacher['qrcode'] = $qrCode;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher QR Code - <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-6">
            <div class="text-center">
                <div class="mb-6">
                    <i class="fas fa-qrcode text-4xl text-orange-600 mb-4"></i>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Teacher QR Code</h1>
                    <p class="text-gray-600">Scan this QR code to confirm availability</p>
                </div>
                
                <div class="mb-6">
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <h2 class="text-lg font-semibold text-gray-800 mb-2">
                            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                        </h2>
                        <p class="text-gray-600 text-sm">
                            <strong>ID:</strong> <?php echo $teacher['id']; ?><br>
                            <strong>QR Code:</strong> <?php echo htmlspecialchars($teacher['qrcode']); ?><br>
                            <strong>Department:</strong> <?php echo htmlspecialchars($teacher['department']); ?><br>
                            <strong>Position:</strong> <?php echo htmlspecialchars($teacher['position']); ?>
                        </p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <div id="qrcode" class="flex justify-center"></div>
                </div>
                
                <div class="mb-6">
                    <p class="text-sm text-gray-500 mb-2">QR Code for scanning:</p>
                    <div class="bg-gray-100 rounded p-2 font-mono text-lg text-center">
                        <?php echo htmlspecialchars($teacher['qrcode']); ?>
                    </div>
                </div>
                
                <div class="text-xs text-gray-400">
                    <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
                    <p>Use this QR code at the teacher screen to confirm availability</p>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-6">
            <a href="consultation/teacher-screen.php" class="text-orange-600 hover:text-orange-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i>Back to Teacher Screen
            </a>
        </div>
    </div>

    <script>
        // Generate QR code
        const qrCode = '<?php echo htmlspecialchars($teacher['qrcode']); ?>';
        const qrcodeElement = document.getElementById('qrcode');
        
        QRCode.toCanvas(qrcodeElement, qrCode, {
            width: 200,
            margin: 2,
            color: {
                dark: '#000000',
                light: '#FFFFFF'
            }
        }, function (error) {
            if (error) {
                console.error('Error generating QR code:', error);
                qrcodeElement.innerHTML = '<p class="text-red-500">Error generating QR code</p>';
            }
        });
        
        // Add click to copy functionality
        const qrCodeElement = document.querySelector('.font-mono');
        qrCodeElement.addEventListener('click', function() {
            navigator.clipboard.writeText(qrCode).then(function() {
                // Show temporary success message
                const originalText = qrCodeElement.textContent;
                qrCodeElement.textContent = 'Copied!';
                qrCodeElement.classList.add('text-green-600');
                
                setTimeout(function() {
                    qrCodeElement.textContent = originalText;
                    qrCodeElement.classList.remove('text-green-600');
                }, 1000);
            });
        });
        
        qrCodeElement.style.cursor = 'pointer';
        qrCodeElement.title = 'Click to copy';
    </script>
</body>
</html>
