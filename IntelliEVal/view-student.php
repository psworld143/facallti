<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'View Student';

// Get student ID from URL
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$student_id) {
    $_SESSION['message'] = 'Invalid student ID provided.';
    $_SESSION['message_type'] = 'error';
    header('Location: students.php');
    exit();
}

// Get student information
$student_query = "SELECT s.*, sp.phone, sp.address, sp.city, sp.state, sp.zip_code, sp.country,
                         sp.date_of_birth, sp.gender, sp.emergency_contact_name, sp.emergency_contact_phone,
                         sp.emergency_contact_relationship, sai.program_id, sai.year_level, sai.section,
                         sai.enrollment_date, sai.expected_graduation, sai.gpa, sai.units_completed,
                         sai.units_remaining, sai.academic_status
                  FROM students s
                  LEFT JOIN student_profiles sp ON s.id = sp.student_id
                  LEFT JOIN student_academic_info sai ON s.id = sai.student_id
                  WHERE s.id = ?";

$student_stmt = mysqli_prepare($conn, $student_query);
mysqli_stmt_bind_param($student_stmt, "i", $student_id);
mysqli_stmt_execute($student_stmt);
$student_result = mysqli_stmt_get_result($student_stmt);
$student = mysqli_fetch_assoc($student_result);

if (!$student) {
    $_SESSION['message'] = 'Student not found.';
    $_SESSION['message_type'] = 'error';
    header('Location: students.php');
    exit();
}

// Include the shared header
include 'includes/header.php';
?>

<!-- Custom CSS for view-student page -->
<link rel="stylesheet" href="assets/css/view-student.css">

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Student Profile</h1>
            <p class="text-sm sm:text-base text-gray-600">View detailed student information and history</p>
        </div>
        <div class="flex space-x-2">
            <a href="edit-student.php?id=<?php echo $student['id']; ?>" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition text-sm">
                <i class="fas fa-edit mr-2"></i>Edit Student
            </a>
            <a href="students.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition text-sm">
                <i class="fas fa-arrow-left mr-2"></i>Back to Students
            </a>
        </div>
    </div>
</div>

<!-- Student Profile Header -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <div class="flex flex-col lg:flex-row items-start lg:items-center space-y-4 lg:space-y-0 lg:space-x-6">
        <div class="h-20 w-20 rounded-full bg-seait-orange flex items-center justify-center">
            <span class="text-white text-2xl font-bold"><?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?></span>
        </div>
        <div class="flex-1">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2">
                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="text-gray-500">Student ID:</span>
                    <span class="font-medium text-gray-900 ml-2"><?php echo htmlspecialchars($student['student_id']); ?></span>
                </div>
                <div>
                    <span class="text-gray-500">Email:</span>
                    <span class="font-medium text-gray-900 ml-2"><?php echo htmlspecialchars($student['email']); ?></span>
                </div>
                <div>
                    <span class="text-gray-500">Status:</span>
                    <span class="px-2 py-1 text-xs rounded-full ml-2 <?php
                        echo $student['status'] === 'active' ? 'bg-green-100 text-green-800' :
                            ($student['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                    ?>">
                        <?php echo ucfirst($student['status']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column - Personal Information -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Personal Information -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Personal Information</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Full Name</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Date of Birth</label>
                            <p class="text-gray-900"><?php echo $student['date_of_birth'] ? date('F d, Y', strtotime($student['date_of_birth'])) : 'Not specified'; ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Gender</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($student['gender'] ?? 'Not specified'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Phone</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($student['phone'] ?? 'Not specified'); ?></p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Address</label>
                            <p class="text-gray-900">
                                <?php
                                $address_parts = array_filter([
                                    $student['address'],
                                    $student['city'],
                                    $student['state'],
                                    $student['zip_code'],
                                    $student['country']
                                ]);
                                echo !empty($address_parts) ? htmlspecialchars(implode(', ', $address_parts)) : 'Not specified';
                                ?>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Emergency Contact</label>
                            <p class="text-gray-900">
                                <?php if ($student['emergency_contact_name']): ?>
                                    <?php echo htmlspecialchars($student['emergency_contact_name']); ?>
                                    (<?php echo htmlspecialchars($student['emergency_contact_relationship'] ?? ''); ?>)<br>
                                    <span class="text-sm text-gray-500"><?php echo htmlspecialchars($student['emergency_contact_phone'] ?? ''); ?></span>
                                <?php else: ?>
                                    Not specified
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Information -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Academic Information</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Course/Program</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($student['course'] ?? 'Not specified'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Year Level</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($student['year_level'] ?? 'Not specified'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Section</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($student['section'] ?? 'Not specified'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Enrollment Date</label>
                            <p class="text-gray-900"><?php echo $student['enrollment_date'] ? date('F d, Y', strtotime($student['enrollment_date'])) : 'Not specified'; ?></p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">GPA</label>
                            <p class="text-gray-900"><?php echo $student['gpa'] ? number_format($student['gpa'], 2) : 'Not specified'; ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Units Completed</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($student['units_completed'] ?? 'Not specified'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Units Remaining</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($student['units_remaining'] ?? 'Not specified'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Expected Graduation</label>
                            <p class="text-gray-900"><?php echo $student['expected_graduation'] ? date('F Y', strtotime($student['expected_graduation'])) : 'Not specified'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column - Quick Stats & Actions -->
    <div class="space-y-6">
        <!-- Quick Stats -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Quick Stats</h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Member Since</span>
                        <span class="font-medium text-gray-900"><?php echo date('M Y', strtotime($student['created_at'])); ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Last Updated</span>
                        <span class="font-medium text-gray-900"><?php echo date('M d, Y', strtotime($student['updated_at'] ?? $student['created_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Quick Actions</h3>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <a href="conduct-evaluation.php?student_id=<?php echo $student['id']; ?>" class="w-full bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition text-sm text-center block">
                        <i class="fas fa-clipboard-check mr-2"></i>Conduct Evaluation
                    </a>
                    <a href="reports.php?student_id=<?php echo $student['id']; ?>" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm text-center block">
                        <i class="fas fa-chart-bar mr-2"></i>Generate Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>