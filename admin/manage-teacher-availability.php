<?php
session_start();
require_once '../config/database.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$page_title = 'Manage Teacher Availability';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $teacher_id = $_POST['teacher_id'] ?? '';
    
    if ($action && $teacher_id) {
        switch ($action) {
            case 'mark_available':
                $notes = $_POST['notes'] ?? 'Admin marked available';
                $query = "INSERT INTO teacher_availability (teacher_id, availability_date, status, notes, last_activity)
                          VALUES (?, CURDATE(), 'available', ?, NOW())
                          ON DUPLICATE KEY UPDATE
                              status = 'available',
                              scan_time = NOW(),
                              last_activity = NOW(),
                              notes = ?,
                              updated_at = NOW()";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "iss", $teacher_id, $notes, $notes);
                mysqli_stmt_execute($stmt);
                $success_message = "Teacher marked as available successfully.";
                break;
                
            case 'mark_unavailable':
                $notes = $_POST['notes'] ?? 'Admin marked unavailable';
                $query = "UPDATE teacher_availability 
                          SET status = 'unavailable',
                              last_activity = NOW(),
                              notes = ?,
                              updated_at = NOW()
                          WHERE teacher_id = ? AND availability_date = CURDATE()";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "si", $notes, $teacher_id);
                mysqli_stmt_execute($stmt);
                $success_message = "Teacher marked as unavailable successfully.";
                break;
        }
    }
}

// Get all faculty members with their availability status
$query = "SELECT 
            f.id,
            f.first_name,
            f.last_name,
            f.department,
            f.position,
            f.email,
            ta.status,
            ta.scan_time,
            ta.last_activity,
            ta.notes
          FROM faculty f
          LEFT JOIN teacher_availability ta ON f.id = ta.teacher_id AND ta.availability_date = CURDATE()
          WHERE f.is_active = 1
          ORDER BY f.department, f.last_name, f.first_name";

$result = mysqli_query($conn, $query);
$faculty_members = [];
while ($row = mysqli_fetch_assoc($result)) {
    $faculty_members[] = $row;
}

// Get statistics
$stats_query = "SELECT 
                  COUNT(*) as total_teachers,
                  SUM(CASE WHEN ta.status = 'available' THEN 1 ELSE 0 END) as available_teachers,
                  SUM(CASE WHEN ta.status = 'unavailable' THEN 1 ELSE 0 END) as unavailable_teachers,
                  SUM(CASE WHEN ta.status IS NULL THEN 1 ELSE 0 END) as not_scanned_teachers
                FROM faculty f
                LEFT JOIN teacher_availability ta ON f.id = ta.teacher_id AND ta.availability_date = CURDATE()
                WHERE f.is_active = 1";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SEAIT Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-bold text-gray-900"><?php echo $page_title; ?></h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="dashboard.php" class="text-gray-600 hover:text-gray-900">
                            <i class="fas fa-arrow-left mr-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Success Message -->
            <?php if (isset($success_message)): ?>
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Teachers</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_teachers']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Available</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['available_teachers']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100 text-red-600">
                            <i class="fas fa-times-circle text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Unavailable</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['unavailable_teachers']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Not Scanned</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['not_scanned_teachers']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Teacher List -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Teacher Availability Status</h2>
                    <p class="text-sm text-gray-600">Current date: <?php echo date('l, F j, Y'); ?></p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Activity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($faculty_members as $teacher): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                    <i class="fas fa-user text-gray-600"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($teacher['position']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($teacher['department']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($teacher['status'] === 'available'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>Available
                                            </span>
                                        <?php elseif ($teacher['status'] === 'unavailable'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-times-circle mr-1"></i>Unavailable
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-clock mr-1"></i>Not Scanned
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($teacher['last_activity']): ?>
                                            <?php echo date('M j, Y g:i A', strtotime($teacher['last_activity'])); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <?php if ($teacher['status'] !== 'available'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="mark_available">
                                                    <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                                    <input type="hidden" name="notes" value="Admin marked available">
                                                    <button type="submit" class="text-green-600 hover:text-green-900">
                                                        <i class="fas fa-check mr-1"></i>Mark Available
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($teacher['status'] !== 'unavailable'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="mark_unavailable">
                                                    <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                                    <input type="hidden" name="notes" value="Admin marked unavailable">
                                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                                        <i class="fas fa-times mr-1"></i>Mark Unavailable
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <a href="../generate-teacher-qr.php?id=<?php echo $teacher['id']; ?>" 
                                               target="_blank" 
                                               class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-qrcode mr-1"></i>Generate QR
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh page every 30 seconds to show updated status
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
