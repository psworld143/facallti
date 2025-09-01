<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'View Faculty';

// Check if faculty ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage-faculty.php');
    exit();
}

// Decrypt the faculty ID
$faculty_id = safe_decrypt_id($_GET['id']);
if ($faculty_id <= 0) {
    header('Location: manage-faculty.php');
    exit();
}

// Get faculty details with comprehensive HR information using JOIN
$query = "SELECT 
    f.*,
    fd.middle_name,
    fd.date_of_birth,
    fd.gender,
    fd.civil_status,
    fd.nationality,
    fd.religion,
    fd.phone,
    fd.emergency_contact_name,
    fd.emergency_contact_number,
    fd.address,
    f.qrcode as employee_id,
    fd.date_of_hire,
    fd.employment_type,
    fd.basic_salary,
    fd.salary_grade,
    fd.allowances,
    fd.pay_schedule,
    fd.highest_education,
    fd.field_of_study,
    fd.school_university,
    fd.year_graduated,
    fd.tin_number,
    fd.sss_number,
    fd.philhealth_number,
    fd.pagibig_number,
    fd.created_at as details_created_at,
    fd.updated_at as details_updated_at
FROM faculty f
LEFT JOIN faculty_details fd ON f.id = fd.faculty_id
WHERE f.id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $faculty_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$faculty = mysqli_fetch_assoc($result)) {
    header('Location: manage-faculty.php');
    exit();
}

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">View Faculty Member</h1>
            <p class="text-gray-600">Comprehensive faculty member details</p>
        </div>
        <a href="manage-faculty.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transform transition-all hover:scale-105 font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Faculty
        </a>
    </div>
</div>

<!-- Faculty Details -->
<div class="space-y-6">
    <!-- Basic Information Card -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center mb-6">
            <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-xl mr-4">
                <?php echo strtoupper(substr($faculty['first_name'], 0, 1) . substr($faculty['last_name'], 0, 1)); ?>
            </div>
            <div>
                <h2 class="text-2xl font-bold text-gray-900">
                    <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>
                    <?php if ($faculty['middle_name']): ?>
                        <?php echo htmlspecialchars($faculty['middle_name']); ?>
                    <?php endif; ?>
                </h2>
                <p class="text-gray-600"><?php echo htmlspecialchars($faculty['position']); ?></p>
                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($faculty['department']); ?></p>
            </div>
            <div class="ml-auto">
                <span class="px-4 py-2 text-sm rounded-full font-semibold <?php echo $faculty['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo $faculty['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['email']); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['phone'] ?? 'Not provided'); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Employee ID</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['employee_id'] ?? 'Not assigned'); ?></p>
            </div>
        </div>
    </div>

    <!-- Personal Information Card -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center mb-6">
            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                <i class="fas fa-user text-blue-600 text-lg"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900">Personal Information</h3>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                <p class="text-gray-900"><?php echo $faculty['date_of_birth'] ? date('F j, Y', strtotime($faculty['date_of_birth'])) : 'Not provided'; ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['gender'] ?? 'Not provided'); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Civil Status</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['civil_status'] ?? 'Not provided'); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nationality</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['nationality'] ?? 'Not provided'); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Religion</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['religion'] ?? 'Not provided'); ?></p>
            </div>
        </div>
    </div>

    <!-- Contact Information Card -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center mb-6">
            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-4">
                <i class="fas fa-phone text-green-600 text-lg"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900">Contact Information</h3>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Complete Address</label>
                <p class="text-gray-900"><?php echo nl2br(htmlspecialchars($faculty['address'] ?? 'Not provided')); ?></p>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Name</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($faculty['emergency_contact_name'] ?? 'Not provided'); ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Number</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($faculty['emergency_contact_number'] ?? 'Not provided'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Employment Information Card -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center mb-6">
            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                <i class="fas fa-briefcase text-purple-600 text-lg"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900">Employment Information</h3>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date of Hire</label>
                <p class="text-gray-900"><?php echo $faculty['date_of_hire'] ? date('F j, Y', strtotime($faculty['date_of_hire'])) : 'Not provided'; ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Employment Type</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['employment_type'] ?? 'Not specified'); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Pay Schedule</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['pay_schedule'] ?? 'Not specified'); ?></p>
            </div>
        </div>
    </div>

    <!-- Salary Information Card -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center mb-6">
            <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center mr-4">
                <i class="fas fa-money-bill-wave text-yellow-600 text-lg"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900">Salary Information</h3>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Basic Salary</label>
                <p class="text-gray-900"><?php echo $faculty['basic_salary'] ? '₱' . number_format($faculty['basic_salary'], 2) : 'Not specified'; ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Salary Grade</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['salary_grade'] ?? 'Not specified'); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Allowances</label>
                <p class="text-gray-900"><?php echo $faculty['allowances'] ? '₱' . number_format($faculty['allowances'], 2) : 'Not specified'; ?></p>
            </div>
        </div>
    </div>

    <!-- Educational Background Card -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center mb-6">
            <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center mr-4">
                <i class="fas fa-graduation-cap text-indigo-600 text-lg"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900">Educational Background</h3>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Highest Education</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['highest_education'] ?? 'Not provided'); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Field of Study</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['field_of_study'] ?? 'Not provided'); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">School/University</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['school_university'] ?? 'Not provided'); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Year Graduated</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['year_graduated'] ?? 'Not provided'); ?></p>
            </div>
        </div>
    </div>

    <!-- Government Information Card -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center mb-6">
            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-4">
                <i class="fas fa-id-card text-red-600 text-lg"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900">Government Information</h3>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">TIN Number</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['tin_number'] ?? 'Not provided'); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">SSS Number</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['sss_number'] ?? 'Not provided'); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">PhilHealth Number</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['philhealth_number'] ?? 'Not provided'); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">PAG-IBIG Number</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($faculty['pagibig_number'] ?? 'Not provided'); ?></p>
            </div>
        </div>
    </div>

    <!-- System Information Card -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center mb-6">
            <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center mr-4">
                <i class="fas fa-cog text-gray-600 text-lg"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900">System Information</h3>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Created Date</label>
                <p class="text-gray-900"><?php echo date('F j, Y g:i A', strtotime($faculty['created_at'])); ?></p>
            </div>
            <?php if ($faculty['details_updated_at']): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Last Updated</label>
                <p class="text-gray-900"><?php echo date('F j, Y g:i A', strtotime($faculty['details_updated_at'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="mt-8 flex justify-end space-x-3">
    <a href="manage-faculty.php" 
       class="px-6 py-3 text-gray-600 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors font-medium">
        <i class="fas fa-arrow-left mr-2"></i>Back to List
    </a>
    <a href="edit-faculty.php?id=<?php echo encrypt_id($faculty['id']); ?>" 
       class="px-6 py-3 bg-gradient-to-r from-seait-orange to-orange-500 text-white rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 font-medium">
        <i class="fas fa-edit mr-2"></i>Edit Faculty
    </a>
</div>

<?php include 'includes/footer.php'; ?>
