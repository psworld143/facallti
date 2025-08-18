<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Fetch active courses for the dropdown
$courses_query = "SELECT co.*, c.name as college_name
                  FROM courses co
                  JOIN colleges c ON co.college_id = c.id
                  WHERE co.is_active = 1 AND c.is_active = 1
                  ORDER BY c.sort_order ASC, co.sort_order ASC, co.name ASC";
$courses_result = mysqli_query($conn, $courses_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form submission here
    // You can add database insertion logic later
    $success_message = "Pre-registration form submitted successfully! We will contact you soon.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-Registration - SEAIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0'
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dark-mode.css">
    <script src="assets/js/dark-mode.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .form-section {
            background: linear-gradient(135deg, #FF6B35 0%, #FF8C42 100%);
        }

        /* Active navbar link styles */
        .navbar-link-active {
            color: #FF6B35 !important;
            font-weight: 600;
        }
        .navbar-link-active:hover {
            color: #FF6B35 !important;
        }
    </style>
</head>
<body class="bg-gray-50 dark-mode" data-theme="light">
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Header Section -->
    <div class="form-section text-white py-8">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h1 class="text-3xl md:text-4xl font-bold mb-4">Online Pre-Registration</h1>
            <p class="text-xl mb-2">1st Semester S.Y. 2025-2026</p>
            <p class="text-lg opacity-90">(for incoming Grade 11, New College Student, Returnee, Transferee and Former Student)</p>
        </div>
    </div>

    <!-- Instructions Section -->
    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 rounded-lg mb-8">
            <h2 class="text-xl font-bold text-yellow-800 mb-4">INSTRUCTIONS</h2>
            <div class="space-y-2 text-yellow-700">
                <p><strong>A.</strong> Read all instructions very carefully</p>
                <p><strong>B.</strong> Fill out this form carefully and type all information requested. Only applicable forms correctly and completely filled out will be accepted. Write N/A if the information is not applicable to you. Omissions can delay the processing of your application.</p>
                <p><strong>C.</strong> Incomplete Application Form will not be processed.</p>
                <p><strong>D.</strong> Submit this form (Click the submit button at the bottom of this form).</p>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
        <div class="bg-green-50 border-l-4 border-green-400 p-6 rounded-lg mb-8">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-green-700"><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pre-registration Form -->
        <form method="POST" class="bg-white rounded-lg shadow-lg p-6 md:p-8 lg:p-12">
            <!-- Personal Information Section -->
            <div class="mb-8">
                <h3 class="text-2xl font-bold text-seait-dark mb-6 border-b-2 border-seait-orange pb-2">Personal Information</h3>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
                    <!-- Applicant Category -->
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Applicant Category *</label>
                        <select name="applicant_category" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Category</option>
                            <option value="incoming_grade11">Incoming Grade 11</option>
                            <option value="new_college">New College Student</option>
                            <option value="returnee">Returnee</option>
                            <option value="transferee">Transferee</option>
                            <option value="former_student">Former Student</option>
                        </select>
                    </div>

                    <!-- Preferred Course -->
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Preferred Course *</label>
                        <select name="preferred_course" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Course</option>
                            <?php
                            if ($courses_result && mysqli_num_rows($courses_result) > 0) {
                                while ($course = mysqli_fetch_assoc($courses_result)) {
                                    echo "<option value='" . htmlspecialchars($course['id']) . "'>" . htmlspecialchars($course['name']) . " - " . htmlspecialchars($course['college_name']) . "</option>";
                                }
                            } else {
                                echo "<option value=''>No active courses available.</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Name Fields -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                        <input type="text" name="last_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                        <input type="text" name="first_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                        <input type="text" name="middle_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Extension Name (Jr, II, III)</label>
                        <input type="text" name="extension_name" placeholder="Jr, II, III" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <!-- Sex and Date of Birth -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sex *</label>
                        <select name="sex" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Sex</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth *</label>
                        <input type="date" name="date_of_birth" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <!-- Place of Birth and Civil Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Place of Birth *</label>
                        <input type="text" name="place_of_birth" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Civil Status *</label>
                        <select name="civil_status" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Civil Status</option>
                            <option value="single">Single</option>
                            <option value="married">Married</option>
                            <option value="widowed">Widowed</option>
                            <option value="divorced">Divorced</option>
                        </select>
                    </div>

                    <!-- Contact Information -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Telephone Number</label>
                        <input type="tel" name="telephone_number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mobile Number *</label>
                        <input type="tel" name="mobile_number" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                        <input type="email" name="email_address" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Citizenship *</label>
                        <select name="citizenship" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Citizenship</option>
                            <option value="filipino">Filipino</option>
                            <option value="dual_citizen">Dual Citizen</option>
                            <option value="foreign">Foreign</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Permanent/Home Address Section -->
            <div class="mb-8">
                <h4 class="text-xl font-bold text-seait-dark mb-4">Permanent/Home Address</h4>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Region *</label>
                        <input type="text" name="permanent_region" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Province *</label>
                        <input type="text" name="permanent_province" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">City/Municipality *</label>
                        <input type="text" name="permanent_city" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Barangay *</label>
                        <input type="text" name="permanent_barangay" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Street</label>
                        <input type="text" name="permanent_street" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">House/Block/Lot No</label>
                        <input type="text" name="permanent_house_number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                </div>
            </div>

            <!-- Current Address Section -->
            <div class="mb-8">
                <h4 class="text-xl font-bold text-seait-dark mb-4">Current Address</h4>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Region *</label>
                        <input type="text" name="current_region" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Province *</label>
                        <input type="text" name="current_province" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">City/Municipality *</label>
                        <input type="text" name="current_city" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Barangay *</label>
                        <input type="text" name="current_barangay" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Street</label>
                        <input type="text" name="current_street" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">House/Block/Lot No</label>
                        <input type="text" name="current_house_number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                </div>
            </div>

            <!-- Family Information Section -->
            <div class="mb-8">
                <h3 class="text-2xl font-bold text-seait-dark mb-6 border-b-2 border-seait-orange pb-2">Family Information</h3>

                <!-- Father's Information -->
                <div class="mb-6">
                    <h4 class="text-xl font-bold text-seait-dark mb-4">Father</h4>
                    <div class="flex items-center space-x-4 mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="father_deceased" class="mr-2">
                            <span class="text-sm text-gray-700">Deceased</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="father_not_applicable" class="mr-2">
                            <span class="text-sm text-gray-700">Not Applicable</span>
                        </label>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                            <input type="text" name="father_last_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                            <input type="text" name="father_first_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                            <input type="text" name="father_middle_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Telephone Number</label>
                            <input type="tel" name="father_telephone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Mobile Number</label>
                            <input type="tel" name="father_mobile" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">School</label>
                            <input type="text" name="father_school" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Year and Degree Graduated</label>
                            <input type="text" name="father_degree" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company/Employer (if employed)</label>
                            <input type="text" name="father_employer" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>
                </div>

                <!-- Mother's Information -->
                <div class="mb-6">
                    <h4 class="text-xl font-bold text-seait-dark mb-4">Mother (Maiden Name)</h4>
                    <div class="flex items-center space-x-4 mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="mother_deceased" class="mr-2">
                            <span class="text-sm text-gray-700">Deceased</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="mother_not_applicable" class="mr-2">
                            <span class="text-sm text-gray-700">Not Applicable</span>
                        </label>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                            <input type="text" name="mother_last_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                            <input type="text" name="mother_first_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                            <input type="text" name="mother_middle_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Telephone Number</label>
                            <input type="tel" name="mother_telephone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Mobile Number</label>
                            <input type="tel" name="mother_mobile" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">School</label>
                            <input type="text" name="mother_school" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Year and Degree Graduated</label>
                            <input type="text" name="mother_degree" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company/Employer (if employed)</label>
                            <input type="text" name="mother_employer" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>
                </div>

                <!-- Spouse's Information -->
                <div class="mb-6">
                    <h4 class="text-xl font-bold text-seait-dark mb-4">Spouse (If Married)</h4>
                    <div class="flex items-center space-x-4 mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="spouse_deceased" class="mr-2">
                            <span class="text-sm text-gray-700">Deceased</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="spouse_not_applicable" class="mr-2">
                            <span class="text-sm text-gray-700">Not Applicable</span>
                        </label>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                            <input type="text" name="spouse_last_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                            <input type="text" name="spouse_first_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                            <input type="text" name="spouse_middle_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Telephone Number</label>
                            <input type="tel" name="spouse_telephone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Mobile Number</label>
                            <input type="tel" name="spouse_mobile" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">School</label>
                            <input type="text" name="spouse_school" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Year and Degree Graduated</label>
                            <input type="text" name="spouse_degree" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company/Employer (if employed)</label>
                            <input type="text" name="spouse_employer" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Academic Information Section -->
            <div class="mb-8">
                <h3 class="text-2xl font-bold text-seait-dark mb-6 border-b-2 border-seait-orange pb-2">Academic Information</h3>
                <p class="text-gray-600 mb-4">School Attended (list all schools attended beginning from elementary)</p>

                <div class="space-y-6">
                    <!-- Elementary -->
                    <div>
                        <h4 class="text-lg font-semibold text-seait-dark mb-4">Elementary</h4>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Elementary School</label>
                                <input type="text" name="elementary_school" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Year Graduated (20__ to 20__)</label>
                                <input type="text" name="elementary_year" placeholder="e.g., 2015 to 2021" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>
                        </div>
                    </div>

                    <!-- Junior High School -->
                    <div>
                        <h4 class="text-lg font-semibold text-seait-dark mb-4">Junior High School</h4>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Junior High School</label>
                                <input type="text" name="junior_high_school" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Year Graduated (20__ to 20__)</label>
                                <input type="text" name="junior_high_year" placeholder="e.g., 2021 to 2025" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>
                        </div>
                    </div>

                    <!-- Senior High School -->
                    <div>
                        <h4 class="text-lg font-semibold text-seait-dark mb-4">Senior High School</h4>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Senior High School</label>
                                <input type="text" name="senior_high_school" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Year Graduated (20__ to 20__)</label>
                                <input type="text" name="senior_high_year" placeholder="e.g., 2025 to 2027" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Emergency Contact Section -->
            <div class="mb-8">
                <h3 class="text-2xl font-bold text-seait-dark mb-6 border-b-2 border-seait-orange pb-2">Emergency Contact</h3>
                <p class="text-gray-600 mb-4">Please contact in case of emergency:</p>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Complete Name *</label>
                        <input type="text" name="emergency_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number *</label>
                        <input type="tel" name="emergency_contact" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Relationship *</label>
                        <input type="text" name="emergency_relationship" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Complete Address *</label>
                        <textarea name="emergency_address" required rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="text-center pt-8">
                <button type="submit" class="bg-seait-orange text-white px-8 md:px-12 py-3 md:py-4 rounded-lg font-semibold text-base md:text-lg hover:bg-orange-600 transition duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                    <i class="fas fa-paper-plane mr-2"></i>Submit Pre-registration Form
                </button>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Include FAB Inquiry System -->
    <?php include 'includes/fab-inquiry.php'; ?>

    <script>
        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            // Add form validation here if needed
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                // Add any additional validation logic here
                console.log('Form submitted');
            });
        });

        // Active navbar link functionality for pre-registration page
        function updateActiveNavLink() {
            const navLinks = document.querySelectorAll('a[href^="index.php#"]');

            // Remove active class from all links
            navLinks.forEach(link => {
                link.classList.remove('navbar-link-active');
            });

            // Highlight Admissions link for pre-registration page
            const admissionsLink = document.querySelector('a[href="index.php#admissions"]');
            if (admissionsLink) {
                admissionsLink.classList.add('navbar-link-active');
            }
        }

        // Update active link on page load
        document.addEventListener('DOMContentLoaded', updateActiveNavLink);
    </script>
</body>
</html>