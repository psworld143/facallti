<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../login.php');
    exit();
}

// Get department head information
$head_query = "SELECT h.*, u.first_name, u.last_name, u.email 
               FROM heads h 
               JOIN users u ON h.user_id = u.id 
               WHERE h.user_id = ? AND h.status = 'active'";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);
$head_data = mysqli_fetch_assoc($head_result);

if (!$head_data) {
    header('Location: ../login.php');
    exit();
}

$department = $head_data['department'];

// Set page title
$page_title = 'Grading System Configuration';

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Set JSON header for AJAX requests
    if ($action === 'save_config') {
        // Clear any previous output
        if (ob_get_level()) {
            ob_clean();
        }
        header('Content-Type: application/json');
    }
    
    switch ($action) {
        case 'save_config':
            try {
                // Debug mode - uncomment to see POST data
                if (isset($_GET['debug'])) {
                    echo "<pre>POST Data: ";
                    print_r($_POST);
                    echo "</pre>";
                }
                
            $config_name = trim($_POST['config_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $grading_scale = $_POST['grading_scale'] ?? 'percentage';
            $passing_grade = (float)($_POST['passing_grade'] ?? 75);
            $max_grade = (float)($_POST['max_grade'] ?? 100);
            $min_grade = (float)($_POST['min_grade'] ?? 0);
            $decimal_places = (int)($_POST['decimal_places'] ?? 2);
            $grade_rounding = $_POST['grade_rounding'] ?? 'round_nearest';
                $class_activities_percentage = (float)($_POST['class_activities_percentage'] ?? 60);
                $examination_percentage = (float)($_POST['examination_percentage'] ?? 40);
            
            // Validate that percentages add up to 100%
            if (abs(($class_activities_percentage + $examination_percentage) - 100) > 0.01) {
                $error_message = "Class Activities and Examination percentages must add up to exactly 100%. " .
                               "Current total: " . ($class_activities_percentage + $examination_percentage) . "%";
                
                // Return JSON error response for AJAX
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $error_message
                ]);
                exit();
            }
            
            if ($config_name) {
                $config_id = (int)($_POST['config_id'] ?? 0);
                
                // If we have a config_id, we're editing an existing config
                if ($config_id > 0) {
                    $check_query = "SELECT id FROM department_grading_configs WHERE id = ?";
                    $check_stmt = mysqli_prepare($conn, $check_query);
                    mysqli_stmt_bind_param($check_stmt, "i", $config_id);
                    mysqli_stmt_execute($check_stmt);
                    $existing_config = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));
                } else {
                    // Check if config already exists by name and department
                $check_query = "SELECT id FROM department_grading_configs WHERE department = ? AND config_name = ?";
                $check_stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($check_stmt, "ss", $department, $config_name);
                mysqli_stmt_execute($check_stmt);
                $existing_config = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));
                }
                
                if ($existing_config) {
                    // Update existing config (preserve current status)
                    $update_query = "UPDATE department_grading_configs SET 
                                    description = ?, grading_scale = ?, passing_grade = ?, max_grade = ?, 
                                    min_grade = ?, decimal_places = ?, grade_rounding = ?, 
                                    class_activities_percentage = ?, examination_percentage = ?,
                                    updated_at = NOW() 
                                    WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($update_stmt, "ssdddisddi", $description, $grading_scale, $passing_grade, 
                                         $max_grade, $min_grade, $decimal_places, $grade_rounding, 
                                         $class_activities_percentage, $examination_percentage, $config_id);
                    $result = mysqli_stmt_execute($update_stmt);
                } else {
                    // Insert new config
                    $insert_query = "INSERT INTO department_grading_configs 
                                    (department, config_name, description, grading_scale, passing_grade, 
                                     max_grade, min_grade, decimal_places, grade_rounding, class_activities_percentage, 
                                     examination_percentage, created_by) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "ssssdddisddi", $department, $config_name, $description, 
                                         $grading_scale, $passing_grade, $max_grade, $min_grade, $decimal_places, 
                                         $grade_rounding, $class_activities_percentage, $examination_percentage, $_SESSION['user_id']);
                    $result = mysqli_stmt_execute($insert_stmt);
                    $config_id = mysqli_insert_id($conn);
                }
                
                if ($result) {
                    // Get the updated configuration data
                    $updated_config_query = "SELECT * FROM department_grading_configs WHERE id = ?";
                    $updated_config_stmt = mysqli_prepare($conn, $updated_config_query);
                    mysqli_stmt_bind_param($updated_config_stmt, "i", $config_id);
                    mysqli_stmt_execute($updated_config_stmt);
                    $updated_config = mysqli_fetch_assoc(mysqli_stmt_get_result($updated_config_stmt));
                    
                    // Return JSON response for AJAX
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Grading configuration saved successfully!',
                        'config' => $updated_config
                    ]);
                    exit();
                } else {
                    $error_msg = "Error saving configuration: " . mysqli_error($conn);
                    error_log("Grading System Error: " . $error_msg);
                    
                    // Return JSON error response for AJAX
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => $error_msg
                    ]);
                    exit();
                }
            } else {
                // Return JSON error response for AJAX
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Please provide a configuration name.'
                ]);
                exit();
            }
            } catch (Exception $e) {
                // Return JSON error response for any PHP errors
                echo json_encode([
                    'success' => false,
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]);
                exit();
            }
            break;
            
        case 'save_grade_scale':
            $config_id = (int)($_POST['config_id'] ?? 0);
            $grade_scales = $_POST['grade_scales'] ?? [];
            
            
            if ($config_id) {
                // Get existing grade scales to compare
                $existing_query = "SELECT id, sort_order FROM grade_scale_definitions WHERE config_id = ? ORDER BY sort_order";
                $existing_stmt = mysqli_prepare($conn, $existing_query);
                mysqli_stmt_bind_param($existing_stmt, "i", $config_id);
                mysqli_stmt_execute($existing_stmt);
                $existing_result = mysqli_stmt_get_result($existing_stmt);
                $existing_scales = [];
                while ($row = mysqli_fetch_assoc($existing_result)) {
                    $existing_scales[$row['sort_order']] = $row['id'];
                }
                
                
                $success_count = 0;
                $processed_orders = [];
                
                // Process submitted grade scales
                if (!empty($grade_scales)) {
                    foreach ($grade_scales as $index => $scale) {
                        if (!empty($scale['grade_value']) && !empty($scale['min_percentage']) && !empty($scale['max_percentage'])) {
                            $sort_order = $index + 1;
                            $processed_orders[] = $sort_order;
                            $is_passing = isset($scale['is_passing']) ? 1 : 0;
                            
                            // Check if this sort_order already exists
                            if (isset($existing_scales[$sort_order])) {
                                // Update existing record
                                $update_query = "UPDATE grade_scale_definitions SET 
                                                grade_value = ?, min_percentage = ?, max_percentage = ?, 
                                                description = ?, is_passing = ? 
                                                WHERE id = ?";
                                $update_stmt = mysqli_prepare($conn, $update_query);
                                mysqli_stmt_bind_param($update_stmt, "sddssi", $scale['grade_value'], 
                                                     $scale['min_percentage'], $scale['max_percentage'], 
                                                     $scale['description'], $is_passing, $existing_scales[$sort_order]);
                                
                                if (mysqli_stmt_execute($update_stmt)) {
                                    $success_count++;
                                }
                            } else {
                                // Insert new record
                                $insert_query = "INSERT INTO grade_scale_definitions 
                                                (config_id, grade_value, min_percentage, max_percentage, description, is_passing, sort_order) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                                $insert_stmt = mysqli_prepare($conn, $insert_query);
                                mysqli_stmt_bind_param($insert_stmt, "isddssi", $config_id, $scale['grade_value'], 
                                                     $scale['min_percentage'], $scale['max_percentage'], 
                                                     $scale['description'], $is_passing, $sort_order);
                                if (mysqli_stmt_execute($insert_stmt)) {
                                    $success_count++;
                                }
                            }
                        }
                    }
                }
                
                // Delete any existing records that weren't in the submitted data
                foreach ($existing_scales as $sort_order => $id) {
                    if (!in_array($sort_order, $processed_orders)) {
                        $delete_query = "DELETE FROM grade_scale_definitions WHERE id = ?";
                        $delete_stmt = mysqli_prepare($conn, $delete_query);
                        mysqli_stmt_bind_param($delete_stmt, "i", $id);
                        mysqli_stmt_execute($delete_stmt);
                    }
                }
                
                if ($success_count > 0 || !empty($grade_scales)) {
                    $message = "Grade scales updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "No changes made to grade scales.";
                    $message_type = "info";
                }
            } else {
                $message = "Invalid configuration ID.";
                $message_type = "error";
            }
            break;
            
        case 'save_categories':
            $config_id = (int)($_POST['config_id'] ?? 0);
            $categories = $_POST['categories'] ?? [];
            
            if ($config_id) {
                // Get existing categories to compare
                $existing_query = "SELECT id, sort_order FROM grade_category_templates WHERE config_id = ? ORDER BY sort_order";
                $existing_stmt = mysqli_prepare($conn, $existing_query);
                mysqli_stmt_bind_param($existing_stmt, "i", $config_id);
                mysqli_stmt_execute($existing_stmt);
                $existing_result = mysqli_stmt_get_result($existing_stmt);
                $existing_categories = [];
                while ($row = mysqli_fetch_assoc($existing_result)) {
                    $existing_categories[$row['sort_order']] = $row['id'];
                }
                
                $success_count = 0;
                $processed_orders = [];
                
                // Process submitted categories
                if (!empty($categories)) {
                    foreach ($categories as $index => $category) {
                        if (!empty($category['category_name']) && !empty($category['weight_percentage'])) {
                            $sort_order = $index + 1;
                            $processed_orders[] = $sort_order;
                            $is_required = isset($category['is_required']) ? 1 : 0;
                            $grade_type = $category['grade_type'] ?? 'both';
                            
                            // Check if this sort_order already exists
                            if (isset($existing_categories[$sort_order])) {
                                // Update existing record
                                $update_query = "UPDATE grade_category_templates SET 
                                                category_name = ?, description = ?, weight_percentage = ?, 
                                                color = ?, is_required = ?, grade_type = ? 
                                                WHERE id = ?";
                                $update_stmt = mysqli_prepare($conn, $update_query);
                                mysqli_stmt_bind_param($update_stmt, "ssdsis", $category['category_name'], 
                                                     $category['description'], $category['weight_percentage'], 
                                                     $category['color'], $is_required, $grade_type, $existing_categories[$sort_order]);
                                if (mysqli_stmt_execute($update_stmt)) {
                                    $success_count++;
                                }
                            } else {
                                // Insert new record
                                $insert_query = "INSERT INTO grade_category_templates 
                                                (config_id, category_name, description, weight_percentage, color, is_required, sort_order, grade_type) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                                $insert_stmt = mysqli_prepare($conn, $insert_query);
                                mysqli_stmt_bind_param($insert_stmt, "issdsiis", $config_id, $category['category_name'], 
                                                     $category['description'], $category['weight_percentage'], 
                                                     $category['color'], $is_required, $sort_order, $grade_type);
                                if (mysqli_stmt_execute($insert_stmt)) {
                                    $success_count++;
                                }
                            }
                        }
                    }
                }
                
                // Delete any existing records that weren't in the submitted data
                foreach ($existing_categories as $sort_order => $id) {
                    if (!in_array($sort_order, $processed_orders)) {
                        $delete_query = "DELETE FROM grade_category_templates WHERE id = ?";
                        $delete_stmt = mysqli_prepare($conn, $delete_query);
                        mysqli_stmt_bind_param($delete_stmt, "i", $id);
                        mysqli_stmt_execute($delete_stmt);
                    }
                }
                
                if ($success_count > 0 || !empty($categories)) {
                    $message = "Categories updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "No changes made to categories.";
                    $message_type = "info";
                }
            } else {
                $message = "Invalid configuration ID.";
                $message_type = "error";
            }
            break;
            
        case 'activate_config':
            // Set JSON header for AJAX requests
            header('Content-Type: application/json');
            
            $config_id = (int)($_POST['config_id'] ?? 0);
            
            if ($config_id) {
                // Deactivate all other configs for this department
                $deactivate_query = "UPDATE department_grading_configs SET status = 'inactive' WHERE department = ?";
                $deactivate_stmt = mysqli_prepare($conn, $deactivate_query);
                mysqli_stmt_bind_param($deactivate_stmt, "s", $department);
                mysqli_stmt_execute($deactivate_stmt);
                
                // Activate selected config
                $activate_query = "UPDATE department_grading_configs SET status = 'active' WHERE id = ?";
                $activate_stmt = mysqli_prepare($conn, $activate_query);
                mysqli_stmt_bind_param($activate_stmt, "i", $config_id);
                
                if (mysqli_stmt_execute($activate_stmt)) {
                    // Fetch the activated configuration data
                    $updated_config_query = "SELECT * FROM department_grading_configs WHERE id = ?";
                    $updated_config_stmt = mysqli_prepare($conn, $updated_config_query);
                    mysqli_stmt_bind_param($updated_config_stmt, "i", $config_id);
                    mysqli_stmt_execute($updated_config_stmt);
                    $updated_config = mysqli_fetch_assoc(mysqli_stmt_get_result($updated_config_stmt));
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Grading configuration activated successfully!',
                        'config' => $updated_config
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error activating configuration.'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid configuration ID.'
                ]);
            }
            exit();
            break;
            
        case 'deactivate_config':
            // Set JSON header for AJAX requests
            header('Content-Type: application/json');
            
            $config_id = (int)($_POST['config_id'] ?? 0);
            
            if ($config_id) {
                // Deactivate the selected config
                $deactivate_query = "UPDATE department_grading_configs SET status = 'inactive' WHERE id = ?";
                $deactivate_stmt = mysqli_prepare($conn, $deactivate_query);
                mysqli_stmt_bind_param($deactivate_stmt, "i", $config_id);
                
                if (mysqli_stmt_execute($deactivate_stmt)) {
                    // Fetch the deactivated configuration data
                    $updated_config_query = "SELECT * FROM department_grading_configs WHERE id = ?";
                    $updated_config_stmt = mysqli_prepare($conn, $updated_config_query);
                    mysqli_stmt_bind_param($updated_config_stmt, "i", $config_id);
                    mysqli_stmt_execute($updated_config_stmt);
                    $updated_config = mysqli_fetch_assoc(mysqli_stmt_get_result($updated_config_stmt));
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Grading configuration deactivated successfully!',
                        'config' => $updated_config
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error deactivating configuration.'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid configuration ID.'
                ]);
            }
            exit();
            break;
    }
}

// Get current grading configurations
$configs_query = "SELECT * FROM department_grading_configs WHERE department = ? ORDER BY created_at DESC";
$configs_stmt = mysqli_prepare($conn, $configs_query);
mysqli_stmt_bind_param($configs_stmt, "s", $department);
mysqli_stmt_execute($configs_stmt);
$configs_result = mysqli_stmt_get_result($configs_stmt);
$configs = mysqli_fetch_all($configs_result, MYSQLI_ASSOC);

// Get active configuration
$active_config = null;
foreach ($configs as $config) {
    if ($config['status'] === 'active') {
        $active_config = $config;
        break;
    }
}

// Get grade scales for active config
$grade_scales = [];
if ($active_config) {
    $scales_query = "SELECT * FROM grade_scale_definitions WHERE config_id = ? ORDER BY sort_order";
    $scales_stmt = mysqli_prepare($conn, $scales_query);
    mysqli_stmt_bind_param($scales_stmt, "i", $active_config['id']);
    mysqli_stmt_execute($scales_stmt);
    $scales_result = mysqli_stmt_get_result($scales_stmt);
    $grade_scales = mysqli_fetch_all($scales_result, MYSQLI_ASSOC);
}

// Get grade categories for active config
$grade_categories = [];
if ($active_config) {
    $categories_query = "SELECT * FROM grade_category_templates WHERE config_id = ? ORDER BY sort_order";
    $categories_stmt = mysqli_prepare($conn, $categories_query);
    mysqli_stmt_bind_param($categories_stmt, "i", $active_config['id']);
    mysqli_stmt_execute($categories_stmt);
    $categories_result = mysqli_stmt_get_result($categories_stmt);
    $grade_categories = mysqli_fetch_all($categories_result, MYSQLI_ASSOC);
}

include 'includes/header.php';
?>

<style>
/* Beautiful Modal Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes slideOut {
    from {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    to {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
}

/* Modal backdrop animation */
#confirmationModal {
    animation: fadeIn 0.3s ease-out;
}

/* Modal content animation */
#confirmationModalContent {
    animation: slideIn 0.3s ease-out;
}

/* Hover effects for buttons */
#confirmationAction:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Focus states for accessibility */
#confirmationAction:focus {
    outline: none;
    ring: 2px;
    ring-offset: 2px;
}

/* Smooth transitions for all interactive elements */
button, input, select, textarea {
    transition: all 0.2s ease-in-out;
}

/* Enhanced button hover effects */
.bg-green-500:hover, .bg-red-500:hover, .bg-blue-500:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Auto-calculated weight input styles */
.auto-calculated {
    background-color: #f0fdf4 !important;
    border-color: #22c55e !important;
    color: #15803d !important;
    font-weight: 500;
}

.auto-calculated:focus {
    background-color: #dcfce7 !important;
    border-color: #16a34a !important;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1) !important;
}

/* Magic icon animation */
.fa-magic {
    animation: sparkle 2s ease-in-out infinite;
}

@keyframes sparkle {
    0%, 100% {
        opacity: 0.7;
        transform: scale(1);
    }
    50% {
        opacity: 1;
        transform: scale(1.1);
    }
}

/* Tooltip styles for auto-calculated fields */
.auto-calculated:hover::after {
    content: "Auto-calculated weight - Total must equal configured percentage";
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background-color: #1f2937;
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Weight input container for better positioning */
.relative {
    position: relative;
}
</style>

<div class="px-0 sm:px-0">
    <!-- Header Section -->
    <div class="mb-8">
        <div class="bg-white rounded-xl shadow-sm p-8 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold mb-2 text-gray-900">Grading System Configuration</h1>
                    <p class="text-gray-600 text-lg"><?php echo htmlspecialchars($department); ?> Department</p>
                    <p class="text-gray-500 mt-2">Configure grading policies and categories for your department</p>
                </div>
                <div class="hidden md:block">
                    <div class="w-20 h-20 bg-seait-orange rounded-full flex items-center justify-center">
                        <i class="fas fa-calculator text-4xl text-white"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <button onclick="openConfigModal()" class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-all duration-200 transform hover:scale-105 border-l-4 border-green-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-plus text-2xl text-green-500"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-900">New Configuration</h3>
                        <p class="text-xs text-gray-500">Create grading system</p>
                    </div>
                </div>
            </button>

            <a href="dashboard.php" class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-all duration-200 transform hover:scale-105 border-l-4 border-green-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-arrow-left text-2xl text-green-500"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-900">Back to Dashboard</h3>
                        <p class="text-xs text-gray-500">Return to main dashboard</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

        <!-- Message -->
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-gray-100 border-gray-300 text-gray-700'; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

    <!-- Current Configuration -->
    <?php if ($active_config): ?>
    <div class="mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Active Configuration</h2>
        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-green-500">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Configuration</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($active_config['config_name']); ?></p>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($active_config['description']); ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Grading Scale</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900"><?php echo ucfirst($active_config['grading_scale']); ?></p>
                    <p class="text-sm text-gray-600">Passing: <?php echo $active_config['passing_grade']; ?>%</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Grade Range</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900"><?php echo $active_config['min_grade']; ?> - <?php echo $active_config['max_grade']; ?></p>
                    <p class="text-sm text-gray-600"><?php echo $active_config['decimal_places']; ?> decimal places</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Configuration Tabs -->
    <div class="mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Configuration Management</h2>
        <div class="bg-white rounded-lg shadow-sm">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex flex-wrap space-x-2 sm:space-x-8 px-3 sm:px-6" aria-label="Tabs">
                    <button onclick="showTab('configurations')" id="tab-configurations" class="tab-button active py-3 sm:py-4 px-1 border-b-2 border-green-500 font-medium text-xs sm:text-sm text-green-600 whitespace-nowrap">
                        Configurations
                    </button>
                    <button onclick="showTab('grade-scales')" id="tab-grade-scales" class="tab-button py-3 sm:py-4 px-1 border-b-2 border-transparent font-medium text-xs sm:text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                        Grade Scales
                    </button>
                    <button onclick="showTab('categories')" id="tab-categories" class="tab-button py-3 sm:py-4 px-1 border-b-2 border-transparent font-medium text-xs sm:text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                        Grade Categories
                    </button>
                </nav>
            </div>

            <!-- Configurations Tab -->
            <div id="content-configurations" class="tab-content">
                <div class="p-3 sm:p-6">
                    <div class="grid grid-cols-1 gap-6">
                        <?php foreach ($configs as $config): ?>
                        <div class="border border-gray-200 rounded-lg p-4" data-config-id="<?php echo $config['id']; ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($config['config_name']); ?></h3>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($config['description']); ?></p>
                                    <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                        <span>Scale: <?php echo ucfirst($config['grading_scale']); ?></span>
                                        <span>Passing: <?php echo $config['passing_grade']; ?>%</span>
                                        <span>Range: <?php echo $config['min_grade']; ?>-<?php echo $config['max_grade']; ?></span>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <?php if ($config['status'] === 'active'): ?>
                                    <div class="flex items-center space-x-2">
                                        <span class="status-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Active
                                        </span>
                                        <button onclick="deactivateConfig(<?php echo $config['id']; ?>)" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                                            Deactivate
                                        </button>
                                    </div>
                                    <?php else: ?>
                                    <button onclick="activateConfig(<?php echo $config['id']; ?>)" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">
                                        Activate
                                    </button>
                                    <?php endif; ?>
                                    <button onclick="editConfigFromData(this)" data-config="<?php echo htmlspecialchars(json_encode($config)); ?>" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">
                                        Edit
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Grade Scales Tab -->
            <div id="content-grade-scales" class="tab-content hidden">
                <div class="p-3 sm:p-6">
                    <?php if ($active_config): ?>
                    <form id="gradeScalesForm" method="POST">
                        <input type="hidden" name="action" value="save_grade_scale">
                        <input type="hidden" name="config_id" value="<?php echo $active_config['id']; ?>">
                        
                        <div class="mb-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Grade Scale Definitions</h3>
                            <p class="text-sm text-gray-600">Define the grade values and their corresponding percentage ranges.</p>
                        </div>
                        
                        <div id="gradeScalesContainer">
                            <?php foreach ($grade_scales as $index => $scale): ?>
                            <div class="grade-scale-item border border-gray-200 rounded-lg p-4 mb-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Grade Value</label>
                                        <input type="text" name="grade_scales[<?php echo $index; ?>][grade_value]" 
                                               value="<?php echo htmlspecialchars($scale['grade_value']); ?>"
                                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm"
                                               required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Min %</label>
                                        <input type="number" name="grade_scales[<?php echo $index; ?>][min_percentage]" 
                                               value="<?php echo $scale['min_percentage']; ?>"
                                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm"
                                               step="0.01" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Max %</label>
                                        <input type="number" name="grade_scales[<?php echo $index; ?>][max_percentage]" 
                                               value="<?php echo $scale['max_percentage']; ?>"
                                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm"
                                               step="0.01" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Description</label>
                                        <input type="text" name="grade_scales[<?php echo $index; ?>][description]" 
                                               value="<?php echo htmlspecialchars($scale['description']); ?>"
                                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
                                    </div>
                                    <div class="flex items-end">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="grade_scales[<?php echo $index; ?>][is_passing]" 
                                                   <?php echo $scale['is_passing'] ? 'checked' : ''; ?>
                                                   class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                            <span class="ml-2 text-sm text-gray-700">Passing</span>
                                        </label>
                                        <button type="button" onclick="removeGradeScale(this)" class="ml-2 text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row sm:justify-between space-y-3 sm:space-y-0">
                            <button type="button" onclick="addGradeScale()" class="bg-green-500 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-green-600 text-sm">
                                <i class="fas fa-plus mr-1 sm:mr-2"></i>Add Grade Scale
                            </button>
                            <button type="button" onclick="confirmSaveGradeScales()" class="bg-green-500 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-green-600 text-sm">
                                <i class="fas fa-save mr-1 sm:mr-2"></i>Save Grade Scales
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-gray-500">Please create and activate a grading configuration first.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Categories Tab -->
            <div id="content-categories" class="tab-content hidden">
                <div class="p-3 sm:p-6">
                    <?php if ($active_config): ?>
                    <form id="categoriesForm" method="POST">
                        <input type="hidden" name="action" value="save_categories">
                        <input type="hidden" name="config_id" value="<?php echo $active_config['id']; ?>">
                        
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Grade Categories Configuration</h3>
                            <p class="text-sm text-gray-600">Configure grade categories for Midterm and Final grades according to the specified grading system.</p>
                            <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-start">
                                    <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-2"></i>
                                    <div class="text-sm text-blue-800">
                                        <strong>Auto-Weight Calculation:</strong> Weights are automatically calculated to total the configured percentage for each grade type. 
                                        Use the "Auto-Calculate Weights" button to toggle between automatic and manual weight entry.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Midterm Grade Categories -->
                        <div class="mb-8">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                                <h4 class="text-lg font-semibold text-blue-900 mb-2">
                                    <i class="fas fa-chart-line mr-2"></i>Midterm Grade Categories (Total: 100%)
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-blue-800">
                                    <div>
                                        <strong>Class Activities (<span class="class-activities-percentage"><?php echo $active_config['class_activities_percentage'] ?? 60; ?></span>% - Configurable):</strong>
                                        <p class="text-xs text-blue-600 mt-1">Department Head can add/modify categories below</p>
                                    </div>
                                    <div>
                                        <strong>Examination (<span class="examination-percentage"><?php echo $active_config['examination_percentage'] ?? 40; ?></span>% - Fixed):</strong>
                                        <ul class="ml-4 mt-1">
                                            <li>• Midterm Examination (<?php echo $active_config['examination_percentage'] ?? 40; ?>%)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h5 class="text-md font-medium text-gray-800">Midterm Categories (<?php echo $active_config['class_activities_percentage'] ?? 60; ?>% - Configurable)</h5>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm text-gray-600">Total:</span>
                                        <span id="midtermTotal" class="text-sm font-semibold text-blue-600"><?php echo $active_config['class_activities_percentage'] ?? 60; ?>.00%</span>
                                    </div>
                                </div>
                                <div id="midtermCategoriesContainer">
                                    <?php 
                                    // Filter out examination categories for midterm (examinations are fixed)
                                    $midterm_categories = array_filter($grade_categories, function($cat) { 
                                        return $cat['grade_type'] === 'midterm' && 
                                               stripos($cat['category_name'], 'examination') === false && 
                                               stripos($cat['category_name'], 'exam') === false; 
                                    });
                                    $index = 0;
                                    foreach ($midterm_categories as $category): ?>
                                    <div class="category-item border border-gray-200 rounded-lg p-4 mb-4">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Category Name</label>
                                                <input type="text" name="categories[<?php echo $index; ?>][category_name]" 
                                                       value="<?php echo htmlspecialchars($category['category_name']); ?>"
                                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm"
                                                       required>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Weight %</label>
                                                <input type="number" name="categories[<?php echo $index; ?>][weight_percentage]" 
                                                       value="<?php echo $category['weight_percentage']; ?>"
                                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm"
                                                       step="0.01" required>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Color</label>
                                                <input type="color" name="categories[<?php echo $index; ?>][color]" 
                                                       value="<?php echo htmlspecialchars($category['color']); ?>"
                                                       class="mt-1 block w-full h-10 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                                <input type="text" name="categories[<?php echo $index; ?>][description]" 
                                                       value="<?php echo htmlspecialchars($category['description']); ?>"
                                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Grade Type</label>
                                                <select name="categories[<?php echo $index; ?>][grade_type]" 
                                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
                                                    <option value="midterm" <?php echo $category['grade_type'] === 'midterm' ? 'selected' : ''; ?>>Midterm</option>
                                                    <option value="final" <?php echo $category['grade_type'] === 'final' ? 'selected' : ''; ?>>Final</option>
                                                    <option value="both" <?php echo $category['grade_type'] === 'both' ? 'selected' : ''; ?>>Both</option>
                                                </select>
                                            </div>
                                            <div class="flex items-end justify-between">
                                                <label class="flex items-center">
                                                    <input type="checkbox" name="categories[<?php echo $index; ?>][is_required]" 
                                                           <?php echo $category['is_required'] ? 'checked' : ''; ?>
                                                           class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                                    <span class="ml-1 text-xs text-gray-700">Required</span>
                                                </label>
                                                <button type="button" onclick="removeCategory(this)" class="text-red-600 hover:text-red-800 p-1">
                                                    <i class="fas fa-trash text-sm"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php $index++; endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Final Grade Categories -->
                        <div class="mb-8">
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                                <h4 class="text-lg font-semibold text-green-900 mb-2">
                                    <i class="fas fa-graduation-cap mr-2"></i>Final Grade Categories (Total: 100%)
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-green-800">
                                    <div>
                                        <strong>Class Activities (<span class="class-activities-percentage"><?php echo $active_config['class_activities_percentage'] ?? 60; ?></span>% - Configurable):</strong>
                                        <p class="text-xs text-green-600 mt-1">Department Head can add/modify categories below</p>
                                    </div>
                                    <div>
                                        <strong>Examination (<span class="examination-percentage"><?php echo $active_config['examination_percentage'] ?? 40; ?></span>% - Fixed):</strong>
                                        <ul class="ml-4 mt-1">
                                            <li>• Final Examination (<?php echo $active_config['examination_percentage'] ?? 40; ?>%)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h5 class="text-md font-medium text-gray-800">Final Categories (<?php echo $active_config['class_activities_percentage'] ?? 60; ?>% - Configurable)</h5>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm text-gray-600">Total:</span>
                                        <span id="finalTotal" class="text-sm font-semibold text-green-600"><?php echo $active_config['class_activities_percentage'] ?? 60; ?>.00%</span>
                                    </div>
                                </div>
                                <div id="finalCategoriesContainer">
                                    <?php 
                                    // Filter out examination categories for final (examinations are fixed)
                                    $final_categories = array_filter($grade_categories, function($cat) { 
                                        return $cat['grade_type'] === 'final' && 
                                               stripos($cat['category_name'], 'examination') === false && 
                                               stripos($cat['category_name'], 'exam') === false; 
                                    });
                                    foreach ($final_categories as $category): ?>
                                    <div class="category-item border border-gray-200 rounded-lg p-4 mb-4">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Category Name</label>
                                                <input type="text" name="categories[<?php echo $index; ?>][category_name]" 
                                                       value="<?php echo htmlspecialchars($category['category_name']); ?>"
                                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm"
                                                       required>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Weight %</label>
                                                <input type="number" name="categories[<?php echo $index; ?>][weight_percentage]" 
                                                       value="<?php echo $category['weight_percentage']; ?>"
                                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm"
                                                       step="0.01" required>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Color</label>
                                                <input type="color" name="categories[<?php echo $index; ?>][color]" 
                                                       value="<?php echo htmlspecialchars($category['color']); ?>"
                                                       class="mt-1 block w-full h-10 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                                <input type="text" name="categories[<?php echo $index; ?>][description]" 
                                                       value="<?php echo htmlspecialchars($category['description']); ?>"
                                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Grade Type</label>
                                                <select name="categories[<?php echo $index; ?>][grade_type]" 
                                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
                                                    <option value="midterm" <?php echo $category['grade_type'] === 'midterm' ? 'selected' : ''; ?>>Midterm</option>
                                                    <option value="final" <?php echo $category['grade_type'] === 'final' ? 'selected' : ''; ?>>Final</option>
                                                    <option value="both" <?php echo $category['grade_type'] === 'both' ? 'selected' : ''; ?>>Both</option>
                                                </select>
                                            </div>
                                            <div class="flex items-end justify-between">
                                                <label class="flex items-center">
                                                    <input type="checkbox" name="categories[<?php echo $index; ?>][is_required]" 
                                                           <?php echo $category['is_required'] ? 'checked' : ''; ?>
                                                           class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                                    <span class="ml-1 text-xs text-gray-700">Required</span>
                                                </label>
                                                <button type="button" onclick="removeCategory(this)" class="text-red-600 hover:text-red-800 p-1">
                                                    <i class="fas fa-trash text-sm"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php $index++; endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Fixed Midterm Examination Section -->
                        <div class="mb-4">
                            <div class="bg-gray-50 border border-gray-300 rounded-lg p-4">
                                <h5 class="text-md font-medium text-gray-800 mb-3">Midterm Examination (<span class="midterm-examination-percentage"><?php echo $active_config['examination_percentage'] ?? 40; ?></span>% - Fixed)</h5>
                                <?php 
                                // Get midterm examination category
                                $midterm_exam = array_filter($grade_categories, function($cat) { 
                                    return $cat['grade_type'] === 'midterm' && 
                                           (stripos($cat['category_name'], 'examination') !== false || 
                                            stripos($cat['category_name'], 'exam') !== false); 
                                });
                                if (!empty($midterm_exam)) {
                                    $exam = reset($midterm_exam);
                                ?>
                                <div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg">
                                    <div class="flex items-center">
                                        <i class="fas fa-lock text-gray-400 mr-3"></i>
                                        <span class="font-medium text-gray-800"><?php echo htmlspecialchars($exam['category_name']); ?></span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-lg font-bold text-gray-600"><?php echo $active_config['examination_percentage'] ?? 40; ?>%</span>
                                        <span class="ml-2 text-sm text-gray-500">(Fixed)</span>
                                    </div>
                                </div>
                                <?php } else { ?>
                                <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <p class="text-yellow-800 text-sm">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        Midterm Examination category not found in database.
                                    </p>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                        
                        <!-- Fixed Final Examination Section -->
                        <div class="mb-4">
                            <div class="bg-gray-50 border border-gray-300 rounded-lg p-4">
                                <h5 class="text-md font-medium text-gray-800 mb-3">Final Examination (<span class="final-examination-percentage"><?php echo $active_config['examination_percentage'] ?? 40; ?></span>% - Fixed)</h5>
                                <?php 
                                // Get final examination category
                                $final_exam = array_filter($grade_categories, function($cat) { 
                                    return $cat['grade_type'] === 'final' && 
                                           (stripos($cat['category_name'], 'examination') !== false || 
                                            stripos($cat['category_name'], 'exam') !== false); 
                                });
                                if (!empty($final_exam)) {
                                    $exam = reset($final_exam);
                                ?>
                                <div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg">
                                    <div class="flex items-center">
                                        <i class="fas fa-lock text-gray-400 mr-3"></i>
                                        <span class="font-medium text-gray-800"><?php echo htmlspecialchars($exam['category_name']); ?></span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-lg font-bold text-gray-600"><?php echo $active_config['examination_percentage'] ?? 40; ?>%</span>
                                        <span class="ml-2 text-sm text-gray-500">(Fixed)</span>
                                    </div>
                                </div>
                                <?php } else { ?>
                                <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <p class="text-yellow-800 text-sm">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        Final Examination category not found in database.
                                    </p>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row sm:justify-between space-y-3 sm:space-y-0">
                            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                                <button type="button" onclick="addCategory('midterm')" class="bg-green-500 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-green-600 text-sm">
                                    <i class="fas fa-plus mr-1 sm:mr-2"></i>Add Midterm Category
                                </button>
                                <button type="button" onclick="addCategory('final')" class="bg-green-500 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-green-600 text-sm">
                                    <i class="fas fa-plus mr-1 sm:mr-2"></i>Add Final Category
                                </button>
                                <button type="button" onclick="toggleAutoCalculation()" id="autoCalcToggle" class="bg-green-500 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-green-600 text-sm">
                                    <i class="fas fa-magic mr-1 sm:mr-2"></i>Auto-Calculate Weights
                                </button>
                            </div>
                            <button type="button" onclick="saveCategories()" class="bg-green-500 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-green-600 text-sm">
                                <i class="fas fa-save mr-1 sm:mr-2"></i>Save Categories
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-gray-500">Please create and activate a grading configuration first.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Configuration Modal -->
<div id="configModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900" id="modalTitle">New Grading Configuration</h3>
                <button onclick="closeConfigModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="configForm" method="POST">
                <input type="hidden" name="action" value="save_config">
                <input type="hidden" name="config_id" id="configId">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Configuration Name</label>
                        <input type="text" name="config_name" id="configName" required
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" id="description" rows="3"
                                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Grading Scale</label>
                            <select name="grading_scale" id="gradingScale"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
                                <option value="percentage">Percentage</option>
                                <option value="letter">Letter Grade</option>
                                <option value="numerical">Numerical</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Grade Rounding</label>
                            <select name="grade_rounding" id="gradeRounding"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
                                <option value="round_nearest">Round to Nearest</option>
                                <option value="round_up">Round Up</option>
                                <option value="round_down">Round Down</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Passing Grade</label>
                            <input type="number" name="passing_grade" id="passingGrade" value="75" step="0.01"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Minimum Grade</label>
                            <input type="number" name="min_grade" id="minGrade" value="0" step="0.01"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Maximum Grade</label>
                            <input type="number" name="max_grade" id="maxGrade" value="100" step="0.01"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Decimal Places</label>
                        <input type="number" name="decimal_places" id="decimalPlaces" value="2" min="0" max="4"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
                    </div>
                    
                    <!-- Percentage Configuration Section -->
                    <div class="border-t border-gray-200 pt-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Grade Distribution Configuration</h3>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-2"></i>
                                <div class="text-sm text-blue-800">
                                    <strong>Grade Distribution:</strong> Configure how the total grade is distributed between class activities and examinations. The percentages must add up to exactly 100%.
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Class Activities Percentage</label>
                                <div class="relative">
                                    <input type="number" name="class_activities_percentage" id="classActivitiesPercentage" 
                                           value="60" step="0.01" min="0" max="100"
                                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm"
                                           onchange="updateExaminationPercentage()">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 text-sm">%</span>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-600 mt-1">Includes: Quizzes, Seat Works, Laboratory Activities, Research Works, Project, Class Participation</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Examination Percentage</label>
                                <div class="relative">
                                    <input type="number" name="examination_percentage" id="examinationPercentage" 
                                           value="40" step="0.01" min="0" max="100"
                                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm"
                                           onchange="updateClassActivitiesPercentage()">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 text-sm">%</span>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-600 mt-1">Includes: Midterm Examination, Final Examination</p>
                            </div>
                        </div>
                        
                        <div class="mt-4 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700">Total Percentage:</span>
                                <span id="totalPercentage" class="text-lg font-bold text-gray-900">100%</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeConfigModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="button" onclick="saveConfig()" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">
                        Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Beautiful Confirmation Modal -->
<div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95" id="confirmationModalContent">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div id="confirmationIcon" class="w-12 h-12 rounded-full flex items-center justify-center mr-4">
                        <i id="confirmationIconSymbol" class="text-2xl"></i>
                    </div>
                    <h3 id="confirmationTitle" class="text-xl font-semibold text-gray-900"></h3>
                </div>
                <button onclick="closeConfirmationModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <p id="confirmationMessage" class="text-gray-700 mb-6 leading-relaxed"></p>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeConfirmationModal()" 
                        class="px-6 py-3 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all duration-200 font-medium">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
                <button type="button" id="confirmationAction" 
                        class="px-6 py-3 text-white rounded-lg transition-all duration-200 transform hover:scale-105 font-medium">
                    <i id="confirmationActionIcon" class="mr-2"></i>
                    <span id="confirmationActionText"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let gradeScaleIndex = <?php echo count($grade_scales); ?>;
let categoryIndex = <?php echo count($grade_categories); ?>;

function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active', 'border-green-500', 'text-green-600');
        button.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Show selected tab content
    document.getElementById('content-' + tabName).classList.remove('hidden');
    
    // Add active class to selected tab button
    const activeButton = document.getElementById('tab-' + tabName);
    activeButton.classList.add('active', 'border-green-500', 'text-green-600');
    activeButton.classList.remove('border-transparent', 'text-gray-500');
    
    // Update URL parameter to preserve tab state
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.replaceState({}, '', url);
}

function getCurrentActiveTab() {
    const activeButton = document.querySelector('.tab-button.active');
    if (activeButton) {
        return activeButton.id.replace('tab-', '');
    }
    return 'configurations'; // default tab
}

function initializeTabs() {
    // Check for tab parameter in URL
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    
    if (tabParam && ['configurations', 'grade-scales', 'categories'].includes(tabParam)) {
        showTab(tabParam);
    } else {
        showTab('configurations'); // default tab
    }
}

function activateConfig(configId) {
    // Find the config name for display
    const configElement = document.querySelector(`button[onclick="activateConfig(${configId})"]`);
    const configName = configElement ? configElement.closest('.border').querySelector('h3').textContent : 'this configuration';
    
    showConfirmationModal({
        type: 'activate',
        title: 'Activate Grading Configuration',
        message: `Are you sure you want to activate "<strong>${configName}</strong>"?<br><br>This will deactivate the current active configuration and make this one the default for your department.`,
        actionText: 'Activate Configuration',
        onConfirm: function() {
            const formData = new FormData();
            formData.append('action', 'activate_config');
            formData.append('config_id', configId);
            
            fetch('grading_system.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is ok
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Response is not JSON');
                }
                
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Reload the page immediately to show updated data, preserving the current tab
                    const currentTab = getCurrentActiveTab();
                    window.location.href = 'grading_system.php?tab=' + currentTab;
                } else {
                    // Show error modal
                    showErrorModal('Activation Failed', 
                        data.message || 'An error occurred while activating the configuration. Please try again.',
                        'Try Again');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('Network Error', 
                    'An error occurred while activating the configuration. Please check your connection and try again.',
                    'Try Again');
            });
        }
    });
}

function deactivateConfig(configId) {
    // Find the config name for display
    const configElement = document.querySelector(`button[onclick="deactivateConfig(${configId})"]`);
    const configName = configElement ? configElement.closest('.border').querySelector('h3').textContent : 'this configuration';
    
    showConfirmationModal({
        type: 'delete',
        title: 'Deactivate Grading Configuration',
        message: `Are you sure you want to deactivate "<strong>${configName}</strong>"?<br><br>
                 <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mt-3">
                     <div class="flex items-center">
                         <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                         <span class="text-yellow-800 font-medium">This will make the configuration inactive</span>
                     </div>
                 </div>
                 <p class="text-sm text-gray-600 mt-3">You can reactivate it later if needed.</p>`,
        actionText: 'Deactivate Configuration',
        onConfirm: function() {
            const formData = new FormData();
            formData.append('action', 'deactivate_config');
            formData.append('config_id', configId);
            
            fetch('grading_system.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is ok
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Response is not JSON');
                }
                
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Reload the page immediately to show updated data, preserving the current tab
                    const currentTab = getCurrentActiveTab();
                    window.location.href = 'grading_system.php?tab=' + currentTab;
                } else {
                    // Show error modal
                    showErrorModal('Deactivation Failed', 
                        data.message || 'An error occurred while deactivating the configuration. Please try again.',
                        'Try Again');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('Network Error', 
                    'An error occurred while deactivating the configuration. Please check your connection and try again.',
                    'Try Again');
            });
        }
    });
}

function saveConfig() {
    const form = document.getElementById('configForm');
    const formData = new FormData(form);
    
    // Debug: Log form data
    console.log('Form data being sent:');
    for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
    }
    
    // Show loading state
    const saveButton = document.querySelector('button[onclick="saveConfig()"]');
    const originalText = saveButton.innerHTML;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    saveButton.disabled = true;
    
    fetch('grading_system.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Response is not JSON');
        }
        
        return response.json();
    })
    .then(data => {
        // Debug: Log server response
        console.log('Server response:', data);
        
        // Reset button state
        saveButton.innerHTML = originalText;
        saveButton.disabled = false;
        
        if (data.success) {
            // Close modal
            closeConfigModal();
            
            // Show success modal
            showSuccessModal('Configuration Saved Successfully!', 
                `Your grading configuration has been saved successfully.<br><br>
                 <div class="bg-green-50 border border-green-200 rounded-lg p-3 mt-3">
                     <div class="flex items-center">
                         <i class="fas fa-check-circle text-green-500 mr-2"></i>
                         <span class="text-green-800 font-medium">Configuration updated</span>
                     </div>
                 </div>
                 <p class="text-sm text-gray-600 mt-3">The configuration has been updated and the UI has been refreshed.</p>`,
                'Continue',
                function() {
                    // Update the UI without reloading the page
                    updateConfigurationList(data.config);
                    
                    // Update the active configuration if this was the active one
                    if (data.config && data.config.status === 'active') {
                        updateActiveConfiguration(data.config);
                    }
                });
        } else {
            // Show error modal
            showErrorModal('Configuration Save Failed', 
                data.message || 'Error saving configuration. Please check the form and try again.',
                'Try Again');
            console.error('Server response:', data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        
        // Reset button state
        saveButton.innerHTML = originalText;
        saveButton.disabled = false;
        
        // Show error modal
        showErrorModal('Network Error', 
            'An error occurred while saving the configuration. Please check your connection and try again.',
            'Try Again');
    });
}

function openConfigModal() {
    document.getElementById('modalTitle').textContent = 'New Grading Configuration';
    document.getElementById('configForm').reset();
    document.getElementById('configId').value = '';
    document.getElementById('configModal').classList.remove('hidden');
}

function closeConfigModal() {
    document.getElementById('configModal').classList.add('hidden');
}

function editConfig(config) {
            document.getElementById('modalTitle').textContent = 'Edit Grading Configuration';
            document.getElementById('configId').value = config.id;
            document.getElementById('configName').value = config.config_name;
            document.getElementById('description').value = config.description || '';
            document.getElementById('gradingScale').value = config.grading_scale;
            document.getElementById('gradeRounding').value = config.grade_rounding;
            document.getElementById('passingGrade').value = config.passing_grade;
            document.getElementById('minGrade').value = config.min_grade;
            document.getElementById('maxGrade').value = config.max_grade;
            document.getElementById('decimalPlaces').value = config.decimal_places;
    
    // Set percentage values
    document.getElementById('classActivitiesPercentage').value = config.class_activities_percentage || 60;
    document.getElementById('examinationPercentage').value = config.examination_percentage || 40;
    
    updateTotalPercentage();
    
            document.getElementById('configModal').classList.remove('hidden');
}

function addGradeScale() {
    const container = document.getElementById('gradeScalesContainer');
    const newScale = document.createElement('div');
    newScale.className = 'grade-scale-item border border-gray-200 rounded-lg p-4 mb-4';
    newScale.innerHTML = `
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Grade Value</label>
                <input type="text" name="grade_scales[${gradeScaleIndex}][grade_value]" 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm"
                       required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Min %</label>
                <input type="number" name="grade_scales[${gradeScaleIndex}][min_percentage]" 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm"
                       step="0.01" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Max %</label>
                <input type="number" name="grade_scales[${gradeScaleIndex}][max_percentage]" 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm"
                       step="0.01" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <input type="text" name="grade_scales[${gradeScaleIndex}][description]" 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
            </div>
            <div class="flex items-end">
                <label class="flex items-center">
                    <input type="checkbox" name="grade_scales[${gradeScaleIndex}][is_passing]" 
                           class="rounded border-gray-300 text-green-600 focus:ring-green-500" checked>
                    <span class="ml-2 text-sm text-gray-700">Passing</span>
                </label>
                <button type="button" onclick="removeGradeScale(this)" class="ml-2 text-red-600 hover:text-red-800">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(newScale);
    gradeScaleIndex++;
}

function removeGradeScale(button) {
    const gradeScaleItem = button.closest('.grade-scale-item');
    const gradeValue = gradeScaleItem.querySelector('input[name*="[grade_value]"]').value;
    
    // Check if confirmation modal function exists
    if (typeof showConfirmationModal === 'function') {
        showConfirmationModal({
            type: 'delete',
            title: 'Delete Grade Scale',
            message: `Are you sure you want to delete the grade scale "<strong>${gradeValue}</strong>"?<br><br>This action cannot be undone.`,
            actionText: 'Delete Grade Scale',
            onConfirm: function() {
                gradeScaleItem.remove();
                showMessage('Grade scale deleted successfully!', 'success');
            }
        });
    } else {
        // Fallback to simple confirmation
        if (confirm(`Are you sure you want to delete the grade scale "${gradeValue}"?`)) {
            gradeScaleItem.remove();
            showMessage('Grade scale deleted successfully!', 'success');
        }
    }
}

function addCategory(gradeType = 'both') {
    const container = gradeType === 'midterm' ? 
        document.getElementById('midtermCategoriesContainer') : 
        document.getElementById('finalCategoriesContainer');
    
    const newCategory = document.createElement('div');
    newCategory.className = 'category-item border border-gray-200 rounded-lg p-4 mb-4';
    newCategory.innerHTML = `
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Category Name</label>
                <input type="text" name="categories[${categoryIndex}][category_name]" 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm"
                       required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Weight %</label>
                <div class="relative">
                    <input type="number" name="categories[${categoryIndex}][weight_percentage]" 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm ${autoCalculationEnabled ? 'auto-calculated' : ''}"
                           step="0.01" required ${autoCalculationEnabled ? 'readonly' : ''}>
                    ${autoCalculationEnabled ? '<div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none"><i class="fas fa-magic text-green-500 text-xs" title="Auto-calculated"></i></div>' : ''}
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Color</label>
                <input type="color" name="categories[${categoryIndex}][color]" 
                       value="#8B5CF6"
                       class="mt-1 block w-full h-10 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <input type="text" name="categories[${categoryIndex}][description]" 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Grade Type</label>
                <select name="categories[${categoryIndex}][grade_type]" 
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 text-sm">
                    <option value="midterm" ${gradeType === 'midterm' ? 'selected' : ''}>Midterm</option>
                    <option value="final" ${gradeType === 'final' ? 'selected' : ''}>Final</option>
                    <option value="both" ${gradeType === 'both' ? 'selected' : ''}>Both</option>
                </select>
            </div>
            <div class="flex items-end justify-between">
                <label class="flex items-center">
                    <input type="checkbox" name="categories[${categoryIndex}][is_required]" 
                           class="rounded border-gray-300 text-green-600 focus:ring-green-500" checked>
                    <span class="ml-1 text-xs text-gray-700">Required</span>
                </label>
                <button type="button" onclick="removeCategory(this)" class="text-red-600 hover:text-red-800 p-1">
                    <i class="fas fa-trash text-sm"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(newCategory);
    
    // Add event listener to the new weight input
    const newWeightInput = newCategory.querySelector('input[name*="[weight_percentage]"]');
    newWeightInput.addEventListener('input', calculateTotals);
    
    categoryIndex++;
    
    // Auto-calculate weights for the specific grade type if enabled
    if (autoCalculationEnabled) {
        autoCalculateWeights(gradeType);
    }
}

function removeCategory(button) {
    const categoryItem = button.closest('.category-item');
    const categoryName = categoryItem.querySelector('input[name*="[category_name]"]').value;
    const gradeType = categoryItem.querySelector('select[name*="[grade_type]"]').value;
    
    // Check if confirmation modal function exists
    if (typeof showConfirmationModal === 'function') {
        showConfirmationModal({
            type: 'delete',
            title: 'Delete Grade Category',
            message: `Are you sure you want to delete the "${gradeType}" category "<strong>${categoryName}</strong>"?<br><br>This action cannot be undone and will affect the total percentage calculation.`,
            actionText: 'Delete Category',
        onConfirm: function() {
            // Determine grade type before removing
            const gradeType = categoryItem.querySelector('select[name*="[grade_type]"]').value;
            categoryItem.remove();
            
            // Redistribute weights after removal
            redistributeWeights(gradeType);
            showMessage('Grade category deleted successfully!', 'success');
        }
        });
    } else {
        // Fallback to simple confirmation
        if (confirm(`Are you sure you want to delete the "${gradeType}" category "${categoryName}"?`)) {
            categoryItem.remove();
            // Redistribute weights after removal
            redistributeWeights(gradeType);
            showMessage('Grade category deleted successfully!', 'success');
        }
    }
}

function calculateTotals() {
    // Calculate midterm total
    let midtermTotal = 0;
    document.querySelectorAll('#midtermCategoriesContainer input[name*="[weight_percentage]"]').forEach(input => {
        const value = parseFloat(input.value) || 0;
        midtermTotal += value;
    });
    
    // Calculate final total
    let finalTotal = 0;
    document.querySelectorAll('#finalCategoriesContainer input[name*="[weight_percentage]"]').forEach(input => {
        const value = parseFloat(input.value) || 0;
        finalTotal += value;
    });
    
    // Update display
    document.getElementById('midtermTotal').textContent = midtermTotal.toFixed(2) + '%';
    document.getElementById('finalTotal').textContent = finalTotal.toFixed(2) + '%';
    
    // Get the expected percentage from the configuration
    const expectedClassActivitiesPercentage = <?php echo $active_config['class_activities_percentage'] ?? 60; ?>;
    
    // Color coding and validation feedback
    const midtermElement = document.getElementById('midtermTotal');
    const finalElement = document.getElementById('finalTotal');
    
    if (Math.abs(midtermTotal - expectedClassActivitiesPercentage) < 0.01) {
        midtermElement.className = 'text-sm font-semibold text-green-600';
    } else {
        midtermElement.className = 'text-sm font-semibold text-red-600';
        // Show warning if significantly off and not in auto-calc mode
        if (!autoCalculationEnabled && Math.abs(midtermTotal - expectedClassActivitiesPercentage) > 5) {
            showValidationWarning('midterm', midtermTotal);
        }
    }
    
    if (Math.abs(finalTotal - expectedClassActivitiesPercentage) < 0.01) {
        finalElement.className = 'text-sm font-semibold text-green-600';
    } else {
        finalElement.className = 'text-sm font-semibold text-red-600';
        // Show warning if significantly off and not in auto-calc mode
        if (!autoCalculationEnabled && Math.abs(finalTotal - expectedClassActivitiesPercentage) > 5) {
            showValidationWarning('final', finalTotal);
        }
    }
}

// Function to show validation warning for significant deviations
function showValidationWarning(gradeType, currentTotal) {
    // Only show warning once per session to avoid spam
    const warningKey = `validation_warning_${gradeType}`;
    if (sessionStorage.getItem(warningKey)) {
        return;
    }
    
    const typeLabel = gradeType === 'midterm' ? 'Midterm' : 'Final';
    const expectedClassActivitiesPercentage = <?php echo $active_config['class_activities_percentage'] ?? 60; ?>;
    const deviation = Math.abs(currentTotal - expectedClassActivitiesPercentage);
    
    showValidationModal(
        `${typeLabel} Categories Weight Warning`,
        `The ${typeLabel.toLowerCase()} categories total is significantly different from the required ${expectedClassActivitiesPercentage}%.<br><br>
         <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mt-3">
             <div class="flex items-center">
                 <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                 <span class="text-yellow-800 font-medium">Current total: ${currentTotal.toFixed(2)}% (${deviation.toFixed(2)}% deviation)</span>
             </div>
         </div>
         <p class="text-sm text-gray-600 mt-3">Consider using the "Auto-Calculate Weights" button to automatically distribute weights equally.</p>`,
        'Auto-Calculate Weights',
        function() {
            autoCalculateWeights(gradeType);
            // Mark warning as shown
            sessionStorage.setItem(warningKey, 'true');
        }
    );
}

// Function to automatically calculate and distribute weights
function autoCalculateWeights(gradeType) {
    const container = gradeType === 'midterm' ? 
        document.getElementById('midtermCategoriesContainer') : 
        document.getElementById('finalCategoriesContainer');
    
    const weightInputs = container.querySelectorAll('input[name*="[weight_percentage]"]');
    const totalCategories = weightInputs.length;
    
    if (totalCategories === 0) return;
    
    // Get the expected percentage from the configuration
    const expectedClassActivitiesPercentage = <?php echo $active_config['class_activities_percentage'] ?? 60; ?>;
    
    // Calculate equal distribution for the configured percentage total
    const equalWeight = expectedClassActivitiesPercentage / totalCategories;
    const roundedWeight = Math.round(equalWeight * 100) / 100; // Round to 2 decimal places
    
    // Distribute weights equally
    weightInputs.forEach((input, index) => {
        // For the last item, ensure total is exactly the configured percentage
        if (index === totalCategories - 1) {
            let currentTotal = 0;
            weightInputs.forEach((inp, i) => {
                if (i < totalCategories - 1) {
                    currentTotal += parseFloat(inp.value) || 0;
                }
            });
            const remainingWeight = expectedClassActivitiesPercentage - currentTotal;
            input.value = Math.round(remainingWeight * 100) / 100;
        } else {
            input.value = roundedWeight;
        }
        
        // Add visual indicator that this was auto-calculated
        input.classList.add('auto-calculated');
        input.title = 'Auto-calculated weight';
    });
    
    calculateTotals();
}

// Function to redistribute weights when a category is removed
function redistributeWeights(gradeType) {
    const container = gradeType === 'midterm' ? 
        document.getElementById('midtermCategoriesContainer') : 
        document.getElementById('finalCategoriesContainer');
    
    const weightInputs = container.querySelectorAll('input[name*="[weight_percentage]"]');
    const totalCategories = weightInputs.length;
    
    if (totalCategories === 0) return;
    
    // Calculate current total
    let currentTotal = 0;
    weightInputs.forEach(input => {
        currentTotal += parseFloat(input.value) || 0;
    });
    
    // Get the expected percentage from the configuration
    const expectedClassActivitiesPercentage = <?php echo $active_config['class_activities_percentage'] ?? 60; ?>;
    
    // If total is not the expected percentage, redistribute
    if (Math.abs(currentTotal - expectedClassActivitiesPercentage) > 0.01) {
        const equalWeight = expectedClassActivitiesPercentage / totalCategories;
        const roundedWeight = Math.round(equalWeight * 100) / 100;
        
        weightInputs.forEach((input, index) => {
            if (index === totalCategories - 1) {
                let newTotal = 0;
                weightInputs.forEach((inp, i) => {
                    if (i < totalCategories - 1) {
                        newTotal += roundedWeight;
                    }
                });
                const remainingWeight = expectedClassActivitiesPercentage - newTotal;
                input.value = Math.round(remainingWeight * 100) / 100;
            } else {
                input.value = roundedWeight;
            }
            
            input.classList.add('auto-calculated');
            input.title = 'Auto-calculated weight';
        });
    }
    
    calculateTotals();
}

// Global variable to track auto-calculation state
let autoCalculationEnabled = true;

// Function to toggle auto-calculation mode
function toggleAutoCalculation() {
    autoCalculationEnabled = !autoCalculationEnabled;
    const toggleButton = document.getElementById('autoCalcToggle');
    
    if (autoCalculationEnabled) {
        toggleButton.innerHTML = '<i class="fas fa-magic mr-1 sm:mr-2"></i>Auto-Calculate Weights';
        toggleButton.className = 'bg-green-500 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-green-600 text-sm';
        
        // Re-enable auto-calculation for all weight inputs
        document.querySelectorAll('input[name*="[weight_percentage]"]').forEach(input => {
            input.classList.add('auto-calculated');
            input.readOnly = true;
            input.title = 'Auto-calculated weight';
        });
        
        // Recalculate all weights
        autoCalculateWeights('midterm');
        autoCalculateWeights('final');
        
        // Clear any validation warnings
        sessionStorage.removeItem('validation_warning_midterm');
        sessionStorage.removeItem('validation_warning_final');
        
        showMessage('Auto-calculation enabled! Weights will be automatically distributed.', 'success');
    } else {
        toggleButton.innerHTML = '<i class="fas fa-edit mr-1 sm:mr-2"></i>Manual Weight Entry';
        toggleButton.className = 'bg-green-500 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-green-600 text-sm';
        
        // Disable auto-calculation for all weight inputs
        document.querySelectorAll('input[name*="[weight_percentage]"]').forEach(input => {
            input.classList.remove('auto-calculated');
            input.readOnly = false;
            input.title = 'Manual weight entry - ensure total equals 60%';
        });
        
        showMessage('Manual weight entry enabled! You can now edit weights manually.', 'info');
    }
}

// Add event listeners for weight percentage changes
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to existing weight inputs
    document.querySelectorAll('input[name*="[weight_percentage]"]').forEach(input => {
        input.addEventListener('input', calculateTotals);
    });
    
    // Calculate initial totals
    calculateTotals();
    
    // Prevent default form submission since we're using AJAX
    const gradeScalesForm = document.getElementById('gradeScalesForm');
    if (gradeScalesForm) {
        gradeScalesForm.addEventListener('submit', function(e) {
            e.preventDefault();
        });
    }
    
    const categoriesForm = document.getElementById('categoriesForm');
    if (categoriesForm) {
        categoriesForm.addEventListener('submit', function(e) {
            e.preventDefault();
        });
    }
    
    const configForm = document.getElementById('configForm');
    if (configForm) {
        configForm.addEventListener('submit', function(e) {
            e.preventDefault();
        });
    }
});

function validateCategories() {
    // Calculate midterm total (excluding examination categories)
    let midtermTotal = 0;
    document.querySelectorAll('#midtermCategoriesContainer input[name*="[weight_percentage]"]').forEach(input => {
        const value = parseFloat(input.value) || 0;
        midtermTotal += value;
    });
    
    // Calculate final total (excluding examination categories)
    let finalTotal = 0;
    document.querySelectorAll('#finalCategoriesContainer input[name*="[weight_percentage]"]').forEach(input => {
        const value = parseFloat(input.value) || 0;
        finalTotal += value;
    });
    
    // Get the expected percentage from the configuration
    const expectedClassActivitiesPercentage = <?php echo $active_config['class_activities_percentage'] ?? 60; ?>;
    
    // Check if totals are correct (allow some tolerance)
    if (Math.abs(midtermTotal - expectedClassActivitiesPercentage) > 0.1) {
        showValidationModal('Midterm Categories Validation Error', 
            `Midterm categories must total exactly <strong>${expectedClassActivitiesPercentage}%</strong>.<br><br>
             <div class="bg-red-50 border border-red-200 rounded-lg p-3 mt-3">
                 <div class="flex items-center">
                     <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                     <span class="text-red-800 font-medium">Current total: ${midtermTotal.toFixed(2)}%</span>
                 </div>
             </div>
             <p class="text-sm text-gray-600 mt-3">Please adjust the weights or use the "Auto-Calculate Weights" button to automatically distribute the weights equally.</p>`,
            'Fix Weights',
            function() {
                // Auto-calculate midterm weights
                autoCalculateWeights('midterm');
            });
        return false;
    }
    
    if (Math.abs(finalTotal - expectedClassActivitiesPercentage) > 0.1) {
        showValidationModal('Final Categories Validation Error', 
            `Final categories must total exactly <strong>${expectedClassActivitiesPercentage}%</strong>.<br><br>
             <div class="bg-red-50 border border-red-200 rounded-lg p-3 mt-3">
                 <div class="flex items-center">
                     <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                     <span class="text-red-800 font-medium">Current total: ${finalTotal.toFixed(2)}%</span>
                 </div>
             </div>
             <p class="text-sm text-gray-600 mt-3">Please adjust the weights or use the "Auto-Calculate Weights" button to automatically distribute the weights equally.</p>`,
            'Fix Weights',
            function() {
                // Auto-calculate final weights
                autoCalculateWeights('final');
            });
        return false;
    }
    
    return true;
}

// Function to show confirmation modal before saving grade scales
function confirmSaveGradeScales() {
    // Count the number of grade scales
    const gradeScaleItems = document.querySelectorAll('.grade-scale-item');
    const scaleCount = gradeScaleItems.length;
    
    showConfirmationModal({
        type: 'save',
        title: 'Save Grade Scales',
        message: `Are you sure you want to save the grade scales configuration?<br><br>
                 <div class="bg-green-50 border border-green-200 rounded-lg p-3 mt-3">
                     <div class="flex items-center">
                         <i class="fas fa-chart-line text-green-500 mr-2"></i>
                         <span class="text-green-800 font-medium">${scaleCount} grade scale${scaleCount !== 1 ? 's' : ''} will be saved</span>
                     </div>
                 </div>
                 <p class="text-sm text-gray-600 mt-3">This will update the grading scale definitions for the active configuration.</p>`,
        actionText: 'Save Grade Scales',
        onConfirm: function() {
            saveGradeScales();
        }
    });
}

// AJAX function to save grade scales
function saveGradeScales() {
    const form = document.getElementById('gradeScalesForm');
    const formData = new FormData(form);
    
    // Show loading state
    const saveButton = document.querySelector('button[onclick="confirmSaveGradeScales()"]');
    const originalText = saveButton.innerHTML;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1 sm:mr-2"></i>Saving...';
    saveButton.disabled = true;
    
    fetch('grading_system.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Reset button state
        saveButton.innerHTML = originalText;
        saveButton.disabled = false;
        
        // Show success modal
        showSuccessModal('Grade Scales Saved Successfully!', 
            `Your grade scales have been saved successfully.<br><br>
             <div class="bg-green-50 border border-green-200 rounded-lg p-3 mt-3">
                 <div class="flex items-center">
                     <i class="fas fa-check-circle text-green-500 mr-2"></i>
                     <span class="text-green-800 font-medium">Grade scale definitions updated</span>
                 </div>
             </div>
             <p class="text-sm text-gray-600 mt-3">The page will reload to show the updated configuration.</p>`,
            'Continue',
            function() {
                // Reload the page to show updated data, preserving the current tab
                const currentTab = getCurrentActiveTab();
                window.location.href = 'grading_system.php?tab=' + currentTab;
            });
    })
    .catch(error => {
        console.error('Error:', error);
        
        // Reset button state
        saveButton.innerHTML = originalText;
        saveButton.disabled = false;
        
        // Show error message
        showMessage('Error saving grade scales. Please try again.', 'error');
    });
}

// AJAX function to save categories
function saveCategories() {
    // Calculate totals for validation
    let midtermTotal = 0;
    document.querySelectorAll('#midtermCategoriesContainer input[name*="[weight_percentage]"]').forEach(input => {
        const value = parseFloat(input.value) || 0;
        midtermTotal += value;
    });
    
    let finalTotal = 0;
    document.querySelectorAll('#finalCategoriesContainer input[name*="[weight_percentage]"]').forEach(input => {
        const value = parseFloat(input.value) || 0;
        finalTotal += value;
    });
    
    // Get the expected percentage from the configuration
    const expectedClassActivitiesPercentage = <?php echo $active_config['class_activities_percentage'] ?? 60; ?>;
    
    // Check if both totals are correct
    const midtermValid = Math.abs(midtermTotal - expectedClassActivitiesPercentage) < 0.1;
    const finalValid = Math.abs(finalTotal - expectedClassActivitiesPercentage) < 0.1;
    
    if (!midtermValid || !finalValid) {
        // Show comprehensive validation modal
        let errorMessage = 'The following issues need to be resolved before saving:<br><br>';
        let hasErrors = false;
        
        if (!midtermValid) {
            errorMessage += `<div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-3">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                    <span class="text-red-800 font-medium">Midterm categories: ${midtermTotal.toFixed(2)}% (must be 60%)</span>
                </div>
            </div>`;
            hasErrors = true;
        }
        
        if (!finalValid) {
            errorMessage += `<div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-3">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                    <span class="text-red-800 font-medium">Final categories: ${finalTotal.toFixed(2)}% (must be 60%)</span>
                </div>
            </div>`;
            hasErrors = true;
        }
        
        if (hasErrors) {
            errorMessage += `<p class="text-sm text-gray-600 mt-3">Click "Auto-Fix All" to automatically distribute weights equally, or manually adjust the weights.</p>`;
            
            showValidationModal('Cannot Save Categories', 
                errorMessage,
                'Auto-Fix All',
                function() {
                    // Auto-calculate both types
                    autoCalculateWeights('midterm');
                    autoCalculateWeights('final');
                    showMessage('Weights have been automatically adjusted to total 60% each.', 'success');
                });
            return;
        }
    }
    
    const form = document.getElementById('categoriesForm');
    const formData = new FormData(form);
    
    // Show loading state
    const saveButton = event.target;
    const originalText = saveButton.innerHTML;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1 sm:mr-2"></i>Saving...';
    saveButton.disabled = true;
    
    fetch('grading_system.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Reset button state
        saveButton.innerHTML = originalText;
        saveButton.disabled = false;
        
        // Show success modal
        showSuccessModal('Categories Saved Successfully!', 
            `Your grade categories have been saved successfully.<br><br>
             <div class="bg-green-50 border border-green-200 rounded-lg p-3 mt-3">
                 <div class="flex items-center">
                     <i class="fas fa-check-circle text-green-500 mr-2"></i>
                     <span class="text-green-800 font-medium">All category weights total exactly 60%</span>
                 </div>
             </div>
             <p class="text-sm text-gray-600 mt-3">The page will reload to show the updated configuration.</p>`,
            'Continue',
            function() {
                // Reload the page to show updated data, preserving the current tab
                const currentTab = getCurrentActiveTab();
                window.location.href = 'grading_system.php?tab=' + currentTab;
            });
    })
    .catch(error => {
        console.error('Error:', error);
        
        // Reset button state
        saveButton.innerHTML = originalText;
        saveButton.disabled = false;
        
        // Show error message
        showMessage('Error saving categories. Please try again.', 'error');
    });
}

// Function to show messages
function showMessage(message, type) {
    // Remove existing messages
    const existingMessage = document.querySelector('.message-container');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    // Create message element
    const messageDiv = document.createElement('div');
    messageDiv.className = `message-container mb-6 p-4 rounded-lg ${type === 'success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700'}`;
    messageDiv.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
            ${message}
        </div>
    `;
    
    // Insert message at the top of the content
    const content = document.querySelector('.px-0.sm\\:px-0');
    if (content) {
        content.insertBefore(messageDiv, content.firstChild);
        
        // Auto-remove message after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }
}

// Beautiful confirmation modal functions
function showConfirmationModal(options) {
    const modal = document.getElementById('confirmationModal');
    const modalContent = document.getElementById('confirmationModalContent');
    const title = document.getElementById('confirmationTitle');
    const message = document.getElementById('confirmationMessage');
    const icon = document.getElementById('confirmationIcon');
    const iconSymbol = document.getElementById('confirmationIconSymbol');
    const actionBtn = document.getElementById('confirmationAction');
    const actionIcon = document.getElementById('confirmationActionIcon');
    const actionText = document.getElementById('confirmationActionText');
    
    // Set modal content
    title.textContent = options.title;
    message.innerHTML = options.message;
    
    // Set icon and colors based on type
    let iconClass, iconColor, actionColor, actionIconClass;
    
    switch(options.type) {
        case 'delete':
            iconClass = 'fas fa-trash-alt';
            iconColor = 'bg-red-100 text-red-600';
            actionColor = 'bg-red-500 hover:bg-red-600';
            actionIconClass = 'fas fa-trash-alt';
            break;
        case 'activate':
            iconClass = 'fas fa-check-circle';
            iconColor = 'bg-green-100 text-green-600';
            actionColor = 'bg-green-500 hover:bg-green-600';
            actionIconClass = 'fas fa-check';
            break;
        case 'update':
            iconClass = 'fas fa-edit';
            iconColor = 'bg-blue-100 text-blue-600';
            actionColor = 'bg-blue-500 hover:bg-blue-600';
            actionIconClass = 'fas fa-save';
            break;
        case 'save':
            iconClass = 'fas fa-save';
            iconColor = 'bg-green-100 text-green-600';
            actionColor = 'bg-green-500 hover:bg-green-600';
            actionIconClass = 'fas fa-save';
            break;
        default:
            iconClass = 'fas fa-question-circle';
            iconColor = 'bg-yellow-100 text-yellow-600';
            actionColor = 'bg-green-500 hover:bg-green-600';
            actionIconClass = 'fas fa-check';
    }
    
    // Apply styles
    icon.className = `w-12 h-12 rounded-full flex items-center justify-center mr-4 ${iconColor}`;
    iconSymbol.className = `text-2xl ${iconClass}`;
    
    // Set action button
    actionBtn.className = `px-6 py-3 text-white rounded-lg transition-all duration-200 transform hover:scale-105 font-medium ${actionColor}`;
    actionIcon.className = `mr-2 ${actionIconClass}`;
    actionText.textContent = options.actionText || 'Confirm';
    
    // Set action button click handler
    actionBtn.onclick = function() {
        options.onConfirm();
        closeConfirmationModal();
    };
    
    // Show modal with animation
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Trigger animation
    setTimeout(() => {
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
    }, 10);
}

function closeConfirmationModal() {
    const modal = document.getElementById('confirmationModal');
    const modalContent = document.getElementById('confirmationModalContent');
    
    // Trigger close animation
    modalContent.classList.remove('scale-100');
    modalContent.classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }, 200);
}

// Beautiful validation modal function
function showValidationModal(title, message, actionText, onAction) {
    const modal = document.getElementById('confirmationModal');
    const modalContent = document.getElementById('confirmationModalContent');
    const titleElement = document.getElementById('confirmationTitle');
    const messageElement = document.getElementById('confirmationMessage');
    const icon = document.getElementById('confirmationIcon');
    const iconSymbol = document.getElementById('confirmationIconSymbol');
    const actionBtn = document.getElementById('confirmationAction');
    const actionIcon = document.getElementById('confirmationActionIcon');
    const actionTextElement = document.getElementById('confirmationActionText');
    
    // Set modal content
    titleElement.textContent = title;
    messageElement.innerHTML = message;
    
    // Set validation-specific styling
    icon.className = 'w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-red-100 text-red-600';
    iconSymbol.className = 'text-2xl fas fa-exclamation-triangle';
    
    // Set action button
    actionBtn.className = 'px-6 py-3 text-white rounded-lg transition-all duration-200 transform hover:scale-105 font-medium bg-red-500 hover:bg-red-600';
    actionIcon.className = 'mr-2 fas fa-wrench';
    actionTextElement.textContent = actionText || 'Fix Issue';
    
    // Set action button click handler
    actionBtn.onclick = function() {
        if (onAction && typeof onAction === 'function') {
            onAction();
        }
        closeConfirmationModal();
    };
    
    // Show modal with animation
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Trigger animation
    setTimeout(() => {
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
    }, 10);
}

// Beautiful success modal function
function showSuccessModal(title, message, actionText, onAction) {
    const modal = document.getElementById('confirmationModal');
    const modalContent = document.getElementById('confirmationModalContent');
    const titleElement = document.getElementById('confirmationTitle');
    const messageElement = document.getElementById('confirmationMessage');
    const icon = document.getElementById('confirmationIcon');
    const iconSymbol = document.getElementById('confirmationIconSymbol');
    const actionBtn = document.getElementById('confirmationAction');
    const actionIcon = document.getElementById('confirmationActionIcon');
    const actionTextElement = document.getElementById('confirmationActionText');
    
    // Set modal content
    titleElement.textContent = title;
    messageElement.innerHTML = message;
    
    // Set success-specific styling
    icon.className = 'w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-green-100 text-green-600';
    iconSymbol.className = 'text-2xl fas fa-check-circle';
    
    // Set action button
    actionBtn.className = 'px-6 py-3 text-white rounded-lg transition-all duration-200 transform hover:scale-105 font-medium bg-green-500 hover:bg-green-600';
    actionIcon.className = 'mr-2 fas fa-arrow-right';
    actionTextElement.textContent = actionText || 'Continue';
    
    // Set action button click handler
    actionBtn.onclick = function() {
        if (onAction && typeof onAction === 'function') {
            onAction();
        }
        closeConfirmationModal();
    };
    
    // Show modal with animation
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Trigger animation
    setTimeout(() => {
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
    }, 10);
}

// Initialize tabs when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeTabs();
    updateTotalPercentage(); // Initialize percentage display
});

// Function to update examination percentage when class activities percentage changes
function updateExaminationPercentage() {
    const classActivitiesInput = document.getElementById('classActivitiesPercentage');
    const examinationInput = document.getElementById('examinationPercentage');
    
    if (classActivitiesInput && examinationInput) {
        const classActivitiesValue = parseFloat(classActivitiesInput.value) || 0;
        const examinationValue = 100 - classActivitiesValue;
        examinationInput.value = examinationValue.toFixed(2);
        updateTotalPercentage();
    }
}

// Function to update class activities percentage when examination percentage changes
function updateClassActivitiesPercentage() {
    const classActivitiesInput = document.getElementById('classActivitiesPercentage');
    const examinationInput = document.getElementById('examinationPercentage');
    
    if (classActivitiesInput && examinationInput) {
        const examinationValue = parseFloat(examinationInput.value) || 0;
        const classActivitiesValue = 100 - examinationValue;
        classActivitiesInput.value = classActivitiesValue.toFixed(2);
        updateTotalPercentage();
    }
}

// Function to update the total percentage display
function updateTotalPercentage() {
    const classActivitiesInput = document.getElementById('classActivitiesPercentage');
    const examinationInput = document.getElementById('examinationPercentage');
    const totalDisplay = document.getElementById('totalPercentage');
    
    if (classActivitiesInput && examinationInput && totalDisplay) {
        const classActivitiesValue = parseFloat(classActivitiesInput.value) || 0;
        const examinationValue = parseFloat(examinationInput.value) || 0;
        const total = classActivitiesValue + examinationValue;
        
        totalDisplay.textContent = total.toFixed(2) + '%';
        
        // Color coding for total
        if (Math.abs(total - 100) < 0.01) {
            totalDisplay.className = 'text-lg font-bold text-green-600';
        } else {
            totalDisplay.className = 'text-lg font-bold text-red-600';
        }
    }
}

// Function to update the configuration list in the UI
function updateConfigurationList(updatedConfig) {
    if (!updatedConfig) return;
    
    // Find the configuration card in the UI
    const configCard = document.querySelector(`[data-config-id="${updatedConfig.id}"]`);
    if (configCard) {
        // Update the configuration name if it changed
        const nameElement = configCard.querySelector('h3');
        if (nameElement) {
            nameElement.textContent = updatedConfig.config_name;
        }
        
        // Update the description if it changed
        const descElement = configCard.querySelector('.text-gray-600');
        if (descElement && updatedConfig.description) {
            descElement.textContent = updatedConfig.description;
        }
        
        // Update the status badge
        const statusElement = configCard.querySelector('.status-badge');
        if (statusElement) {
            statusElement.className = `status-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                updatedConfig.status === 'active' 
                    ? 'bg-green-100 text-green-800' 
                    : 'bg-gray-100 text-gray-800'
            }`;
            statusElement.textContent = updatedConfig.status.charAt(0).toUpperCase() + updatedConfig.status.slice(1);
        }
        
        // Update the edit button with new data
        const editButton = configCard.querySelector('button[onclick*="editConfig"]');
        if (editButton) {
            // Store the config data in a data attribute instead of onclick
            editButton.setAttribute('data-config', JSON.stringify(updatedConfig));
            // Keep the onclick but use a generic function
            editButton.setAttribute('onclick', 'editConfigFromData(this)');
        }
    }
}

// Beautiful error modal function
function showErrorModal(title, message, actionText) {
    const modal = document.getElementById('confirmationModal');
    const modalContent = document.getElementById('confirmationModalContent');
    const titleElement = document.getElementById('confirmationTitle');
    const messageElement = document.getElementById('confirmationMessage');
    const icon = document.getElementById('confirmationIcon');
    const iconSymbol = document.getElementById('confirmationIconSymbol');
    const actionBtn = document.getElementById('confirmationAction');
    const actionIcon = document.getElementById('confirmationActionIcon');
    const actionTextElement = document.getElementById('confirmationActionText');
    
    // Set modal content
    titleElement.textContent = title;
    messageElement.innerHTML = message;
    
    // Set error-specific styling
    icon.className = 'w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-red-100 text-red-600';
    iconSymbol.className = 'text-2xl fas fa-exclamation-circle';
    
    // Set action button
    actionBtn.className = 'px-6 py-3 text-white rounded-lg transition-all duration-200 transform hover:scale-105 font-medium bg-red-500 hover:bg-red-600';
    actionIcon.className = 'mr-2 fas fa-times';
    actionTextElement.textContent = actionText || 'Close';
    
    // Set action button click handler
    actionBtn.onclick = function() {
        closeConfirmationModal();
    };
    
    // Show modal with animation
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Trigger animation
    setTimeout(() => {
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
    }, 10);
}

// Function to edit config from data attribute
function editConfigFromData(button) {
    const configData = button.getAttribute('data-config');
    if (configData) {
        try {
            const config = JSON.parse(configData);
            editConfig(config);
        } catch (e) {
            console.error('Error parsing config data:', e);
        }
    }
}

// Function to update the active configuration display
function updateActiveConfiguration(activeConfig) {
    if (!activeConfig) return;
    
    // Update the percentage displays in the categories section
    const classActivitiesElements = document.querySelectorAll('.class-activities-percentage');
    const examinationElements = document.querySelectorAll('.examination-percentage');
    
    classActivitiesElements.forEach(element => {
        element.textContent = activeConfig.class_activities_percentage + '%';
    });
    
    examinationElements.forEach(element => {
        element.textContent = activeConfig.examination_percentage + '%';
    });
    
    // Update the section headers
    const midtermHeader = document.querySelector('#midtermCategoriesContainer').closest('.mb-4').querySelector('h5');
    const finalHeader = document.querySelector('#finalCategoriesContainer').closest('.mb-4').querySelector('h5');
    
    if (midtermHeader) {
        midtermHeader.textContent = `Midterm Categories (${activeConfig.class_activities_percentage}% - Configurable)`;
    }
    
    if (finalHeader) {
        finalHeader.textContent = `Final Categories (${activeConfig.class_activities_percentage}% - Configurable)`;
    }
    
    // Update the total displays
    const midtermTotal = document.getElementById('midtermTotal');
    const finalTotal = document.getElementById('finalTotal');
    
    if (midtermTotal) {
        midtermTotal.textContent = activeConfig.class_activities_percentage + '.00%';
    }
    
    if (finalTotal) {
        finalTotal.textContent = activeConfig.class_activities_percentage + '.00%';
    }
    
    // Update the fixed examination sections
    const midtermExamPercentage = document.querySelector('.midterm-examination-percentage');
    const finalExamPercentage = document.querySelector('.final-examination-percentage');
    
    if (midtermExamPercentage) {
        midtermExamPercentage.textContent = activeConfig.examination_percentage + '%';
    }
    
    if (finalExamPercentage) {
        finalExamPercentage.textContent = activeConfig.examination_percentage + '%';
    }
}

</script>

