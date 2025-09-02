<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';
require_once '../includes/error_handler.php';

// Check database connection
if (!checkDatabaseConnection($conn)) {
    // If we can't redirect due to headers already sent, show a user-friendly error
    if (headers_sent()) {
        echo '<div style="background: #fee; border: 1px solid #fcc; padding: 20px; margin: 20px; border-radius: 5px; color: #c33;">
                <h2>Database Connection Error</h2>
                <p>Unable to connect to the database. Please try refreshing the page or contact support if the problem persists.</p>
              </div>';
        exit();
    }
}

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php');
    exit();
}

// Get and validate employee ID
$encrypted_id = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($encrypted_id)) {
    header('Location: admin-employee.php');
    exit();
}

// Decrypt the employee ID
$employee_id = decrypt_id($encrypted_id);
if (!$employee_id) {
    header('Location: admin-employee.php');
    exit();
}

// Get employee details with department information
$employee_query = "SELECT e.*, d.name as department_name, d.icon as department_icon, d.color_theme as department_color, d.description as department_description
                   FROM employees e 
                   LEFT JOIN departments d ON e.department = d.name 
                   WHERE e.id = ?";

$employee_stmt = mysqli_prepare($conn, $employee_query);
if ($employee_stmt) {
    mysqli_stmt_bind_param($employee_stmt, "i", $employee_id);
    if (!checkDatabaseStatement($employee_stmt, "employee_details")) {
        // If we can't redirect due to headers already sent, show a user-friendly error
        if (headers_sent()) {
            echo '<div style="background: #fee; border: 1px solid #fcc; padding: 20px; margin: 20px; border-radius: 5px; color: #c33;">
                    <h2>Database Error</h2>
                    <p>Unable to retrieve employee information. Please try refreshing the page or contact support if the problem persists.</p>
                  </div>';
            exit();
        }
    }
    $employee_result = mysqli_stmt_get_result($employee_stmt);
    
    if ($employee_result && $employee = mysqli_fetch_assoc($employee_result)) {
        // Employee found
        $page_title = 'View Employee - ' . $employee['first_name'] . ' ' . $employee['last_name'];
    } else {
        // Employee not found
        header('Location: admin-employee.php');
        exit();
    }
    mysqli_stmt_close($employee_stmt);
} else {
    header('Location: admin-employee.php');
    exit();
}

// Get employee activity logs (if table exists)
$activity_logs = [];
$logs_query = "SHOW TABLES LIKE 'employee_activity_logs'";
$logs_table_exists = mysqli_query($conn, $logs_query);

if ($logs_table_exists && mysqli_num_rows($logs_table_exists) > 0) {
    $activity_query = "SELECT * FROM employee_activity_logs WHERE employee_id = ? ORDER BY created_at DESC LIMIT 10";
    $activity_stmt = mysqli_prepare($conn, $activity_query);
    if ($activity_stmt) {
        mysqli_stmt_bind_param($activity_stmt, "i", $employee_id);
        if (mysqli_stmt_execute($activity_stmt)) {
            $activity_result = mysqli_stmt_get_result($activity_stmt);
            while ($log = mysqli_fetch_assoc($activity_result)) {
                $activity_logs[] = $log;
            }
        }
        mysqli_stmt_close($activity_stmt);
    }
}

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Employee Details</h1>
            <p class="text-gray-600">View comprehensive information about <?php echo htmlspecialchars(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')); ?></p>
        </div>
        <div class="flex space-x-3">
            <a href="admin-employee.php" class="bg-seait-dark text-white px-4 py-2 rounded-lg hover:bg-gray-800 transform transition-all hover:scale-105 font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Back to Employees
            </a>
            <a href="edit-employee.php?id=<?php echo $encrypted_id; ?>" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transform transition-all hover:scale-105 font-medium">
                <i class="fas fa-edit mr-2"></i>Edit Employee
            </a>
        </div>
    </div>
</div>

<!-- Employee Profile Card -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
    <div class="bg-gradient-to-r from-seait-orange to-orange-500 p-6 text-white">
        <div class="flex items-center space-x-6">
            <div class="flex-shrink-0">
                <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center text-white text-3xl font-bold backdrop-blur-sm">
                    <?php echo strtoupper(substr($employee['first_name'] ?? '', 0, 1) . substr($employee['last_name'] ?? '', 0, 1)); ?>
                </div>
            </div>
            <div class="flex-1">
                <h2 class="text-3xl font-bold mb-2"><?php echo htmlspecialchars(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')); ?></h2>
                <p class="text-xl opacity-90 mb-1"><?php echo htmlspecialchars($employee['position'] ?? ''); ?></p>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <?php if ($employee['department_icon'] && $employee['department_color']): ?>
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm mr-2" 
                                 style="background-color: <?php echo $employee['department_color']; ?>">
                                <i class="<?php echo $employee['department_icon']; ?>"></i>
                            </div>
                        <?php endif; ?>
                        <span class="text-lg"><?php echo htmlspecialchars(($employee['department_name'] ?: $employee['department']) ?? ''); ?></span>
                    </div>
                    <span class="px-3 py-1 text-sm rounded-full font-semibold <?php echo $employee['is_active'] ? 'bg-green-500/20 text-green-100' : 'bg-red-500/20 text-red-100'; ?>">
                        <?php echo $employee['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm opacity-75">Employee ID</p>
                <p class="text-xl font-mono font-bold"><?php echo htmlspecialchars($employee['employee_id'] ?? ''); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Employee Information Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Personal Information -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center mb-6">
            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                <i class="fas fa-user text-blue-600 text-lg"></i>
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-900">Personal Information</h3>
                <p class="text-gray-600 text-sm">Basic personal details</p>
            </div>
        </div>
        
        <div class="space-y-4">
            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                <span class="text-gray-600 font-medium">Full Name</span>
                <span class="text-gray-900 font-semibold"><?php echo htmlspecialchars(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')); ?></span>
            </div>
            
            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                <span class="text-gray-600 font-medium">Email Address</span>
                <span class="text-gray-900 font-semibold"><?php echo htmlspecialchars($employee['email'] ?? ''); ?></span>
            </div>
            
            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                <span class="text-gray-600 font-medium">Phone Number</span>
                <span class="text-gray-900 font-semibold"><?php echo htmlspecialchars($employee['phone'] ?? ''); ?></span>
            </div>
            
            <div class="flex justify-between items-start py-3 border-b border-gray-100">
                <span class="text-gray-600 font-medium">Address</span>
                <span class="text-gray-900 font-semibold text-right max-w-xs"><?php echo nl2br(htmlspecialchars($employee['address'] ?? '')); ?></span>
            </div>
        </div>
    </div>

    <!-- Employment Information -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center mb-6">
            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                <i class="fas fa-briefcase text-purple-600 text-lg"></i>
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-900">Employment Information</h3>
                <p class="text-gray-600 text-sm">Job and employment details</p>
            </div>
        </div>
        
        <div class="space-y-4">
            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                <span class="text-gray-600 font-medium">Employee ID</span>
                <span class="text-gray-900 font-semibold font-mono"><?php echo htmlspecialchars($employee['employee_id'] ?? ''); ?></span>
            </div>
            
            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                <span class="text-gray-600 font-medium">Position</span>
                <span class="text-gray-900 font-semibold"><?php echo htmlspecialchars($employee['position'] ?? ''); ?></span>
            </div>
            
            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                <span class="text-gray-600 font-medium">Employee Type</span>
                <span class="px-3 py-1 text-sm rounded-full font-semibold bg-blue-100 text-blue-800">
                    <?php echo ucfirst(htmlspecialchars($employee['employee_type'] ?? '')); ?>
                </span>
            </div>
            
            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                <span class="text-gray-600 font-medium">Date of Hire</span>
                <span class="text-gray-900 font-semibold"><?php echo $employee['hire_date'] ? date('F j, Y', strtotime($employee['hire_date'])) : 'Not set'; ?></span>
            </div>
            
            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                <span class="text-gray-600 font-medium">Employment Status</span>
                <span class="px-3 py-1 text-sm rounded-full font-semibold <?php echo $employee['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo $employee['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Department Information -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex items-center mb-6">
        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-4">
            <i class="fas fa-building text-green-600 text-lg"></i>
        </div>
        <div>
            <h3 class="text-xl font-bold text-gray-900">Department Information</h3>
            <p class="text-gray-600 text-sm">Department and organizational details</p>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="flex items-center space-x-4 p-4 bg-gray-50 rounded-lg">
            <?php if ($employee['department_icon'] && $employee['department_color']): ?>
                <div class="w-16 h-16 rounded-xl flex items-center justify-center text-white text-2xl" 
                     style="background-color: <?php echo $employee['department_color']; ?>">
                    <i class="<?php echo $employee['department_icon']; ?>"></i>
                </div>
            <?php else: ?>
                <div class="w-16 h-16 bg-seait-orange rounded-xl flex items-center justify-center text-white text-2xl">
                    <i class="fas fa-building"></i>
                </div>
            <?php endif; ?>
            <div>
                <h4 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars(($employee['department_name'] ?: $employee['department']) ?? ''); ?></h4>
                <?php if ($employee['department_description']): ?>
                    <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($employee['department_description'] ?? ''); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="space-y-3">
            <div class="flex justify-between items-center py-2">
                <span class="text-gray-600 font-medium">Department Name</span>
                <span class="text-gray-900 font-semibold"><?php echo htmlspecialchars(($employee['department_name'] ?: $employee['department']) ?? ''); ?></span>
            </div>
            
            <?php if ($employee['department_description']): ?>
            <div class="flex justify-between items-start py-2">
                <span class="text-gray-600 font-medium">Description</span>
                <span class="text-gray-900 font-semibold text-right max-w-xs"><?php echo htmlspecialchars($employee['department_description'] ?? ''); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="flex justify-between items-center py-2">
                <span class="text-gray-600 font-medium">Color Theme</span>
                <div class="flex items-center space-x-2">
                    <div class="w-6 h-6 rounded-full border-2 border-gray-300" 
                         style="background-color: <?php echo $employee['department_color'] ?: '#FF6B35'; ?>"></div>
                    <span class="text-gray-900 font-semibold font-mono"><?php echo $employee['department_color'] ?: '#FF6B35'; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Information -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex items-center mb-6">
        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center mr-4">
            <i class="fas fa-cog text-gray-600 text-lg"></i>
        </div>
        <div>
            <h3 class="text-xl font-bold text-gray-900">System Information</h3>
            <p class="text-gray-600 text-sm">Account and system details</p>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="space-y-3">
            <div class="flex justify-between items-center py-2">
                <span class="text-gray-600 font-medium">Record ID</span>
                <span class="text-gray-900 font-semibold font-mono"><?php echo $employee['id']; ?></span>
            </div>
            
            <div class="flex justify-between items-center py-2">
                <span class="text-gray-600 font-medium">Created</span>
                <span class="text-gray-900 font-semibold"><?php echo $employee['created_at'] ? date('M j, Y g:i A', strtotime($employee['created_at'])) : 'Not set'; ?></span>
            </div>
        </div>
        
        <div class="space-y-3">
            <div class="flex justify-between items-center py-2">
                <span class="text-gray-600 font-medium">Last Updated</span>
                <span class="text-gray-900 font-semibold">
                    <?php echo $employee['updated_at'] ? date('M j, Y g:i A', strtotime($employee['updated_at'])) : 'Never'; ?>
                </span>
            </div>
            
            <div class="flex justify-between items-center py-2">
                <span class="text-gray-600 font-medium">Account Status</span>
                <span class="px-3 py-1 text-sm rounded-full font-semibold <?php echo $employee['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo $employee['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
        </div>
        
        <div class="space-y-3">
            <div class="flex justify-between items-center py-2">
                <span class="text-gray-600 font-medium">Tenure</span>
                <span class="text-gray-900 font-semibold">
                    <?php 
                    if ($employee['hire_date']) {
                        $hire_date = new DateTime($employee['hire_date']);
                        $now = new DateTime();
                        $tenure = $hire_date->diff($now);
                        echo $tenure->y . ' years, ' . $tenure->m . ' months';
                    } else {
                        echo 'Not available';
                    }
                    ?>
                </span>
            </div>
            
            <div class="flex justify-between items-center py-2">
                <span class="text-gray-600 font-medium">Days Employed</span>
                <span class="text-gray-900 font-semibold">
                    <?php 
                    if ($employee['hire_date']) {
                        $hire_date = new DateTime($employee['hire_date']);
                        $now = new DateTime();
                        $tenure = $hire_date->diff($now);
                        echo $tenure->days;
                    } else {
                        echo '0';
                    }
                    ?> days
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Activity Logs (if available) -->
<?php if (!empty($activity_logs)): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex items-center mb-6">
        <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center mr-4">
            <i class="fas fa-history text-yellow-600 text-lg"></i>
        </div>
        <div>
            <h3 class="text-xl font-bold text-gray-900">Recent Activity</h3>
            <p class="text-gray-600 text-sm">Latest employee activities</p>
        </div>
    </div>
    
    <div class="space-y-4">
        <?php foreach ($activity_logs as $log): ?>
        <div class="flex items-center space-x-4 p-4 bg-gray-50 rounded-lg">
            <div class="w-10 h-10 bg-seait-orange rounded-full flex items-center justify-center text-white">
                <i class="fas fa-<?php echo $log['action_type'] === 'login' ? 'sign-in-alt' : 'edit'; ?>"></i>
            </div>
            <div class="flex-1">
                <p class="text-gray-900 font-semibold"><?php echo htmlspecialchars($log['action']); ?></p>
                <p class="text-gray-600 text-sm"><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></p>
            </div>
            <div class="text-right">
                <span class="px-3 py-1 text-sm rounded-full font-semibold bg-blue-100 text-blue-800">
                    <?php echo ucfirst(htmlspecialchars($log['action_type'])); ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Action Buttons -->
<div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
    <a href="admin-employee.php" 
       class="w-full sm:w-auto bg-gray-500 text-white px-8 py-3 rounded-lg hover:bg-gray-600 transform transition-all hover:scale-105 font-medium text-center">
        <i class="fas fa-arrow-left mr-2"></i>Back to Employees
    </a>
    
    <a href="edit-employee.php?id=<?php echo $encrypted_id; ?>" 
       class="w-full sm:w-auto bg-seait-orange text-white px-8 py-3 rounded-lg hover:bg-orange-600 transform transition-all hover:scale-105 font-medium text-center">
        <i class="fas fa-edit mr-2"></i>Edit Employee
    </a>
    
    <button onclick="printEmployeeDetails()" 
            class="w-full sm:w-auto bg-seait-dark text-white px-8 py-3 rounded-lg hover:bg-gray-800 transform transition-all hover:scale-105 font-medium">
        <i class="fas fa-print mr-2"></i>Print Details
    </button>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Print employee details function
function printEmployeeDetails() {
    window.print();
}

// Add some interactive features
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to information cards
    const cards = document.querySelectorAll('.bg-white.rounded-xl.shadow-lg');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
        });
    });
    
    // Add copy functionality for email
    const emailElement = document.querySelector('.text-gray-900.font-semibold');
    if (emailElement && emailElement.textContent.includes('@')) {
        emailElement.style.cursor = 'pointer';
        emailElement.title = 'Click to copy email';
        emailElement.addEventListener('click', function() {
            navigator.clipboard.writeText(this.textContent).then(() => {
                // Show a temporary notification
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                notification.textContent = 'Email copied to clipboard!';
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 2000);
            });
        });
    }
});

// Add print styles
const printStyles = `
    @media print {
        .bg-gradient-to-r, .bg-seait-orange, .bg-seait-dark, .bg-gray-500 {
            -webkit-print-color-adjust: exact !important;
            color-adjust: exact !important;
        }
        
        .shadow-lg {
            box-shadow: none !important;
        }
        
        .transform, .hover\\:scale-105 {
            transform: none !important;
        }
        
        .flex.space-x-3, .flex.flex-col.sm\\:flex-row.gap-4 {
            display: none !important;
        }
    }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = printStyles;
document.head.appendChild(styleSheet);
</script>
