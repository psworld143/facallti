<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';
require_once 'includes/employee_id_generator.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Edit Faculty';

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

// Get colleges for dropdown
$colleges_query = "SELECT name FROM colleges WHERE is_active = 1 ORDER BY name";
$colleges_result = mysqli_query($conn, $colleges_query);
$colleges = [];
while ($row = mysqli_fetch_assoc($colleges_result)) {
    $colleges[] = $row['name'];
}

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Edit Faculty Member</h1>
            <p class="text-gray-600">Update faculty member information</p>
        </div>
        <a href="manage-faculty.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transform transition-all hover:scale-105 font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Faculty
        </a>
    </div>
</div>

<!-- Loading Spinner -->
<div id="loadingSpinner" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100">
                <i class="fas fa-spinner fa-spin text-blue-600 text-xl"></i>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Loading...</h3>
            <p class="text-sm text-gray-500 mt-2">Please wait while we load the faculty data.</p>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<div id="messageContainer" class="mb-6 hidden">
    <div id="successMessage" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        <i class="fas fa-check-circle mr-2"></i><span id="successText"></span>
    </div>
    <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <i class="fas fa-exclamation-circle mr-2"></i><span id="errorText"></span>
    </div>
</div>

<!-- Edit Faculty Form -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <form id="editFacultyForm" class="space-y-8">
        <!-- Basic Information Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-user text-blue-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Basic Information</h4>
                    <p class="text-gray-600 text-sm">Essential faculty member details</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" id="first_name" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter first name">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                    <input type="text" name="middle_name" id="middle_name"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter middle name">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" id="last_name" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter last name">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter email address">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                    <input type="tel" name="phone" id="phone"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter phone number">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Position/Title <span class="text-red-500">*</span></label>
                    <input type="text" name="position" id="position" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter position/title">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Department/College <span class="text-red-500">*</span></label>
                    <select name="department" id="department" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm">
                        <option value="">Select Department/College</option>
                        <?php foreach ($colleges as $college): ?>
                            <option value="<?php echo htmlspecialchars($college); ?>">
                                <?php echo htmlspecialchars($college); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
            </div>
        </div>

        <!-- Personal Information Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-user text-blue-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Personal Information</h4>
                    <p class="text-gray-600 text-sm">Personal details and background</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                    <input type="date" name="date_of_birth" id="date_of_birth"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                    <select name="gender" id="gender" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Civil Status</label>
                    <select name="civil_status" id="civil_status" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="">Select Civil Status</option>
                        <option value="Single">Single</option>
                        <option value="Married">Married</option>
                        <option value="Widowed">Widowed</option>
                        <option value="Divorced">Divorced</option>
                        <option value="Separated">Separated</option>
                    </select>
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nationality</label>
                    <input type="text" name="nationality" id="nationality"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter nationality">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
            </div>

            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Religion</label>
                <input type="text" name="religion" id="religion"
                       class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                       placeholder="Enter religion">
                <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
            </div>
        </div>

        <!-- Contact Information Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-phone text-green-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Contact Information</h4>
                    <p class="text-gray-600 text-sm">Contact details and emergency information</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" id="emergency_contact_name"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter emergency contact name">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Contact Number</label>
                    <input type="tel" name="emergency_contact_number" id="emergency_contact_number"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter emergency contact number">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
            </div>

            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Complete Address</label>
                <textarea name="address" id="address" rows="3"
                          class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                          placeholder="Enter complete address"></textarea>
                <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
            </div>
        </div>

        <!-- Employment Information Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-briefcase text-purple-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Employment Information</h4>
                    <p class="text-gray-600 text-sm">Job details and employment status</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Employee ID</label>
                    <div class="flex space-x-2">
                        <input type="text" name="employee_id" id="employee_id"
                               class="flex-1 px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                               placeholder="YYYY-XXXX (e.g., 2024-0001)" pattern="\d{4}-\d{4}">
                        <button type="button" onclick="generateEmployeeID()" 
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-magic mr-1"></i>Auto
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Format: YYYY-XXXX (Year-Series)</p>
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date of Hire</label>
                    <input type="date" name="date_of_hire" id="date_of_hire"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Employment Type</label>
                    <select name="employment_type" id="employment_type" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="">Select Employment Type</option>
                        <option value="Full-time">Full-time</option>
                        <option value="Part-time">Part-time</option>
                        <option value="Contract">Contract</option>
                        <option value="Temporary">Temporary</option>
                        <option value="Probationary">Probationary</option>
                    </select>
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pay Schedule</label>
                    <select name="pay_schedule" id="pay_schedule" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="">Select Pay Schedule</option>
                        <option value="Monthly">Monthly</option>
                        <option value="Bi-weekly">Bi-weekly</option>
                        <option value="Weekly">Weekly</option>
                    </select>
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
            </div>
        </div>

        <!-- Salary Information Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-money-bill-wave text-yellow-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Salary Information</h4>
                    <p class="text-gray-600 text-sm">Compensation and benefits details</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Basic Salary</label>
                    <input type="number" name="basic_salary" id="basic_salary" step="0.01" min="0"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter basic salary">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Salary Grade</label>
                    <input type="text" name="salary_grade" id="salary_grade"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter salary grade">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Allowances</label>
                    <input type="number" name="allowances" id="allowances" step="0.01" min="0"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter allowances">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
            </div>
        </div>

        <!-- Educational Background Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-graduation-cap text-indigo-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Educational Background</h4>
                    <p class="text-gray-600 text-sm">Academic qualifications and credentials</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Highest Educational Attainment</label>
                    <select name="highest_education" id="highest_education" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="">Select Highest Education</option>
                        <option value="High School">High School</option>
                        <option value="Associate Degree">Associate Degree</option>
                        <option value="Bachelor's Degree">Bachelor's Degree</option>
                        <option value="Master's Degree">Master's Degree</option>
                        <option value="Doctorate">Doctorate</option>
                        <option value="Post-Doctorate">Post-Doctorate</option>
                    </select>
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Field of Study</label>
                    <input type="text" name="field_of_study" id="field_of_study"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter field of study">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">School/University</label>
                    <input type="text" name="school_university" id="school_university"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter school/university">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Year Graduated</label>
                    <input type="number" name="year_graduated" id="year_graduated" min="1950" max="2030"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter year graduated">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
            </div>
        </div>

        <!-- Government Information Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-id-card text-red-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Government Information</h4>
                    <p class="text-gray-600 text-sm">Government-issued identification numbers</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">TIN Number</label>
                    <input type="text" name="tin_number" id="tin_number"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter TIN number">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">SSS Number</label>
                    <input type="text" name="sss_number" id="sss_number"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter SSS number">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">PhilHealth Number</label>
                    <input type="text" name="philhealth_number" id="philhealth_number"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter PhilHealth number">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">PAG-IBIG Number</label>
                    <input type="text" name="pagibig_number" id="pagibig_number"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter PAG-IBIG number">
                    <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
                </div>
            </div>
        </div>

        <!-- Status Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center">
                <input type="checkbox" name="is_active" id="is_active" value="1"
                       class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                <label for="is_active" class="ml-2 block text-sm text-gray-900">
                    Active Faculty Member
                </label>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="bg-white border-t border-gray-200 pt-6">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-2"></i>
                    All fields marked with * are required
                </div>
                <div class="flex space-x-4">
                    <a href="manage-faculty.php" 
                       class="px-8 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium border border-gray-300">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                    <button type="button" id="updateFacultyBtn"
                            class="px-8 py-3 bg-gradient-to-r from-seait-orange to-orange-500 text-white rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 font-medium shadow-lg">
                        <i class="fas fa-save mr-2"></i>Update Faculty Member
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Global variables
let facultyData = null;
const facultyId = '<?php echo $_GET['id']; ?>';

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    loadFacultyData();
    setupEventListeners();
});

// Load faculty data via AJAX
async function loadFacultyData() {
    showLoading(true);
    
    try {
        const response = await fetch(`get-faculty-data.php?id=${facultyId}`);
        const data = await response.json();
        
        if (data.success) {
            facultyData = data.faculty;
            populateForm(facultyData);
        } else {
            showMessage('error', data.message || 'Failed to load faculty data');
        }
    } catch (error) {
        console.error('Error loading faculty data:', error);
        showMessage('error', 'Network error. Please try again.');
    } finally {
        showLoading(false);
    }
}

// Populate form with faculty data
function populateForm(data) {
    // Basic Information
    document.getElementById('first_name').value = data.first_name || '';
    document.getElementById('middle_name').value = data.middle_name || '';
    document.getElementById('last_name').value = data.last_name || '';
    document.getElementById('email').value = data.email || '';
    document.getElementById('phone').value = data.phone || '';
    document.getElementById('position').value = data.position || '';
    document.getElementById('department').value = data.department || '';
    
    // Personal Information
    document.getElementById('date_of_birth').value = data.date_of_birth || '';
    document.getElementById('gender').value = data.gender || '';
    document.getElementById('civil_status').value = data.civil_status || '';
    document.getElementById('nationality').value = data.nationality || '';
    document.getElementById('religion').value = data.religion || '';
    
    // Contact Information
    document.getElementById('emergency_contact_name').value = data.emergency_contact_name || '';
    document.getElementById('emergency_contact_number').value = data.emergency_contact_number || '';
    document.getElementById('address').value = data.address || '';
    
    // Employment Information
    document.getElementById('employee_id').value = data.employee_id || '';
    document.getElementById('date_of_hire').value = data.date_of_hire || '';
    document.getElementById('employment_type').value = data.employment_type || '';
    document.getElementById('pay_schedule').value = data.pay_schedule || '';
    
    // Salary Information
    document.getElementById('basic_salary').value = data.basic_salary || '';
    document.getElementById('salary_grade').value = data.salary_grade || '';
    document.getElementById('allowances').value = data.allowances || '';
    
    // Educational Background
    document.getElementById('highest_education').value = data.highest_education || '';
    document.getElementById('field_of_study').value = data.field_of_study || '';
    document.getElementById('school_university').value = data.school_university || '';
    document.getElementById('year_graduated').value = data.year_graduated || '';
    
    // Government Information
    document.getElementById('tin_number').value = data.tin_number || '';
    document.getElementById('sss_number').value = data.sss_number || '';
    document.getElementById('philhealth_number').value = data.philhealth_number || '';
    document.getElementById('pagibig_number').value = data.pagibig_number || '';
    
    // Status
    document.getElementById('is_active').checked = data.is_active == 1;
}

// Setup event listeners
function setupEventListeners() {
    // Update button
    document.getElementById('updateFacultyBtn').addEventListener('click', handleUpdate);
    
    // Real-time validation
    const requiredFields = ['first_name', 'last_name', 'email', 'position', 'department'];
    requiredFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (field) {
            field.addEventListener('blur', () => validateField(field));
            field.addEventListener('input', () => clearFieldError(field));
        }
    });
}

// Handle form update
async function handleUpdate() {
    if (!validateForm()) {
        return;
    }
    
    const updateBtn = document.getElementById('updateFacultyBtn');
    const originalText = updateBtn.innerHTML;
    
    // Show loading state
    updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
    updateBtn.disabled = true;
    
    try {
        const formData = new FormData(document.getElementById('editFacultyForm'));
        formData.append('faculty_id', facultyId);
        
        const response = await fetch('update-faculty-ajax.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('success', data.message);
            // Update local data
            facultyData = { ...facultyData, ...data.data };
        } else {
            showMessage('error', data.message);
        }
    } catch (error) {
        console.error('Update error:', error);
        showMessage('error', 'Network error. Please try again.');
    } finally {
        // Reset button state
        updateBtn.innerHTML = originalText;
        updateBtn.disabled = false;
    }
}

// Form validation
function validateForm() {
    let isValid = true;
    clearAllErrors();
    
    const requiredFields = ['first_name', 'last_name', 'email', 'position', 'department'];
    
    requiredFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (field && !field.value.trim()) {
            showFieldError(field, `${fieldName.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())} is required`);
            isValid = false;
        }
    });
    
    // Email validation
    const emailField = document.getElementById('email');
    if (emailField && emailField.value && !isValidEmail(emailField.value)) {
        showFieldError(emailField, 'Please enter a valid email address');
        isValid = false;
    }
    
    return isValid;
}

// Field validation
function validateField(field) {
    const value = field.value.trim();
    
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, `${field.name.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())} is required`);
        return false;
    }
    
    if (field.type === 'email' && value && !isValidEmail(value)) {
        showFieldError(field, 'Please enter a valid email address');
        return false;
    }
    
    clearFieldError(field);
    return true;
}

// Utility functions
function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function showFieldError(field, message) {
    field.classList.add('border-red-500');
    const errorDiv = field.parentNode.querySelector('.error-message');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.classList.remove('hidden');
    }
}

function clearFieldError(field) {
    field.classList.remove('border-red-500');
    const errorDiv = field.parentNode.querySelector('.error-message');
    if (errorDiv) {
        errorDiv.classList.add('hidden');
    }
}

function clearAllErrors() {
    document.querySelectorAll('.error-message').forEach(div => {
        div.classList.add('hidden');
    });
    document.querySelectorAll('input, select, textarea').forEach(field => {
        field.classList.remove('border-red-500');
    });
}

function showMessage(type, message) {
    const container = document.getElementById('messageContainer');
    const successDiv = document.getElementById('successMessage');
    const errorDiv = document.getElementById('errorMessage');
    const successText = document.getElementById('successText');
    const errorText = document.getElementById('errorText');
    
    // Hide all messages
    successDiv.classList.add('hidden');
    errorDiv.classList.add('hidden');
    
    // Show appropriate message
    if (type === 'success') {
        successText.textContent = message;
        successDiv.classList.remove('hidden');
    } else {
        errorText.textContent = message;
        errorDiv.classList.remove('hidden');
    }
    
    container.classList.remove('hidden');
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        container.classList.add('hidden');
    }, 5000);
}

function showLoading(show) {
    const spinner = document.getElementById('loadingSpinner');
    if (show) {
        spinner.classList.remove('hidden');
    } else {
        spinner.classList.add('hidden');
    }
}

// Employee ID generation
async function generateEmployeeID() {
    try {
        const response = await fetch('get-next-employee-id.php');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('employee_id').value = data.employee_id;
            showMessage('success', `Employee ID generated: ${data.employee_id}`);
        } else {
            showMessage('error', data.message || 'Error generating employee ID');
        }
    } catch (error) {
        console.error('Error generating employee ID:', error);
        showMessage('error', 'Network error. Please try again.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
