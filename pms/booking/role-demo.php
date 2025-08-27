<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Set page information
$page_title = 'Role-Specific Navigation Demo';
$page_subtitle = 'This page demonstrates the different navigation systems for each user role';

// Include the unified navigation system
include 'includes/header-unified.php';
include 'includes/sidebar-unified.php';
?>

<!-- Main Content -->
<main class="ml-0 lg:ml-64 mt-16 p-6 flex-1">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-semibold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
            <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($page_subtitle); ?></p>
        </div>
        <div class="text-right">
            <div id="current-date" class="text-sm text-gray-600"></div>
            <div id="current-time" class="text-sm text-gray-600"></div>
        </div>
    </div>

    <!-- Role Information -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex items-center mb-4">
            <?php
            $role_icon = '';
            $role_color = '';
            $role_title = '';
            
            switch ($_SESSION['user_role']) {
                case 'manager':
                    $role_icon = 'fas fa-crown';
                    $role_color = 'from-purple-600 to-indigo-600';
                    $role_title = 'System Manager';
                    break;
                case 'front_desk':
                    $role_icon = 'fas fa-concierge-bell';
                    $role_color = 'from-blue-600 to-cyan-600';
                    $role_title = 'Front Desk Staff';
                    break;
                case 'housekeeping':
                    $role_icon = 'fas fa-broom';
                    $role_color = 'from-green-600 to-emerald-600';
                    $role_title = 'Housekeeping Staff';
                    break;
            }
            ?>
            <div class="w-12 h-12 bg-gradient-to-r <?php echo $role_color; ?> rounded-full flex items-center justify-center mr-4">
                <i class="<?php echo $role_icon; ?> text-white text-xl"></i>
            </div>
            <div>
                <h2 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
                <p class="text-gray-600"><?php echo $role_title; ?></p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-semibold text-gray-800 mb-2">Current Role</h3>
                <p class="text-gray-600"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['user_role'])); ?></p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-semibold text-gray-800 mb-2">Navigation Theme</h3>
                <p class="text-gray-600">
                    <?php
                    switch ($_SESSION['user_role']) {
                        case 'manager':
                            echo 'Purple/Indigo (Management)';
                            break;
                        case 'front_desk':
                            echo 'Blue/Cyan (Front Desk)';
                            break;
                        case 'housekeeping':
                            echo 'Green/Emerald (Housekeeping)';
                            break;
                    }
                    ?>
                </p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-semibold text-gray-800 mb-2">Access Level</h3>
                <p class="text-gray-600">
                    <?php
                    switch ($_SESSION['user_role']) {
                        case 'manager':
                            echo 'Full System Access';
                            break;
                        case 'front_desk':
                            echo 'Front Desk Operations';
                            break;
                        case 'housekeeping':
                            echo 'Housekeeping Operations';
                            break;
                    }
                    ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Navigation Features -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Navbar Features -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Navbar Features</h3>
            <ul class="space-y-3">
                <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-3"></i>
                    <span class="text-gray-700">Role-specific color scheme</span>
                </li>
                <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-3"></i>
                    <span class="text-gray-700">Role-specific quick actions</span>
                </li>
                <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-3"></i>
                    <span class="text-gray-700">Role-specific notifications</span>
                </li>
                <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-3"></i>
                    <span class="text-gray-700">Role-specific user menu</span>
                </li>
                <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-3"></i>
                    <span class="text-gray-700">Mobile-responsive design</span>
                </li>
            </ul>
        </div>

        <!-- Sidebar Features -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Sidebar Features</h3>
            <ul class="space-y-3">
                <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-3"></i>
                    <span class="text-gray-700">Role-specific navigation items</span>
                </li>
                <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-3"></i>
                    <span class="text-gray-700">Collapsible submenus</span>
                </li>
                <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-3"></i>
                    <span class="text-gray-700">Role-specific quick actions</span>
                </li>
                <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-3"></i>
                    <span class="text-gray-700">Active page highlighting</span>
                </li>
                <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-3"></i>
                    <span class="text-gray-700">User profile information</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Role-Specific Information -->
    <div class="bg-white rounded-lg shadow-md p-6 mt-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Role-Specific Information</h3>
        
        <?php if ($_SESSION['user_role'] === 'manager'): ?>
            <div class="bg-purple-50 border-l-4 border-purple-400 p-4">
                <h4 class="font-semibold text-purple-800 mb-2">Manager Access</h4>
                <p class="text-purple-700">As a manager, you have access to all system modules including management reports, staff management, system settings, and audit logs. Your navigation is themed with purple/indigo colors to reflect your administrative role.</p>
            </div>
        <?php elseif ($_SESSION['user_role'] === 'front_desk'): ?>
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                <h4 class="font-semibold text-blue-800 mb-2">Front Desk Access</h4>
                <p class="text-blue-700">As front desk staff, you have access to guest-facing operations including reservations, check-ins, check-outs, guest services, and billing. Your navigation is themed with blue/cyan colors to reflect your customer service role.</p>
            </div>
        <?php elseif ($_SESSION['user_role'] === 'housekeeping'): ?>
            <div class="bg-green-50 border-l-4 border-green-400 p-4">
                <h4 class="font-semibold text-green-800 mb-2">Housekeeping Access</h4>
                <p class="text-green-700">As housekeeping staff, you have access to room management, maintenance, inventory, and cleaning operations. Your navigation is themed with green/emerald colors to reflect your operational role.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
