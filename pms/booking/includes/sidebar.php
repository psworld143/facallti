<?php
// Sidebar component for Hotel PMS
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_role = $_SESSION['user_role'];
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Define navigation items based on user role
$navigation_items = [
    'dashboard' => [
        'url' => '../index.php',
        'icon' => 'fas fa-tachometer-alt',
        'label' => 'Dashboard',
        'roles' => ['manager', 'front_desk', 'housekeeping']
    ],
    'front_desk' => [
        'url' => '../modules/front-desk/',
        'icon' => 'fas fa-concierge-bell',
        'label' => 'Front Desk',
        'roles' => ['manager', 'front_desk'],
        'submenu' => [
            'reservations' => ['url' => '../modules/front-desk/manage-reservations.php', 'label' => 'Reservations'],
            'check_in' => ['url' => '../modules/front-desk/check-in.php', 'label' => 'Check In'],
            'check_out' => ['url' => '../modules/front-desk/check-out.php', 'label' => 'Check Out'],
            'walk_ins' => ['url' => '../modules/front-desk/walk-ins.php', 'label' => 'Walk-ins'],
            'guest_services' => ['url' => '../modules/front-desk/guest-services.php', 'label' => 'Guest Services']
        ]
    ],
    'housekeeping' => [
        'url' => '../modules/housekeeping/',
        'icon' => 'fas fa-broom',
        'label' => 'Housekeeping',
        'roles' => ['manager', 'housekeeping'],
        'submenu' => [
            'room_status' => ['url' => '../modules/housekeeping/room-status.php', 'label' => 'Room Status'],
            'tasks' => ['url' => '../modules/housekeeping/tasks.php', 'label' => 'Tasks'],
            'maintenance' => ['url' => '../modules/housekeeping/maintenance.php', 'label' => 'Maintenance'],
            'inventory' => ['url' => '../modules/housekeeping/inventory.php', 'label' => 'Inventory']
        ]
    ],
    'guests' => [
        'url' => '../modules/guests/',
        'icon' => 'fas fa-users',
        'label' => 'Guest Management',
        'roles' => ['manager', 'front_desk'],
        'submenu' => [
            'profiles' => ['url' => '../modules/guests/profiles.php', 'label' => 'Guest Profiles'],
            'vip' => ['url' => '../modules/guests/vip-management.php', 'label' => 'VIP Guests'],
            'feedback' => ['url' => '../modules/guests/feedback.php', 'label' => 'Feedback'],
            'loyalty' => ['url' => '../modules/guests/loyalty.php', 'label' => 'Loyalty Program']
        ]
    ],
    'billing' => [
        'url' => '../modules/billing/',
        'icon' => 'fas fa-credit-card',
        'label' => 'Billing & Payments',
        'roles' => ['manager', 'front_desk'],
        'submenu' => [
            'invoices' => ['url' => '../modules/billing/invoices.php', 'label' => 'Invoices'],
            'payments' => ['url' => '../modules/billing/payments.php', 'label' => 'Payments'],
            'discounts' => ['url' => '../modules/billing/discounts.php', 'label' => 'Discounts'],
            'vouchers' => ['url' => '../modules/billing/vouchers.php', 'label' => 'Vouchers'],
            'reports' => ['url' => '../modules/billing/reports.php', 'label' => 'Reports']
        ]
    ],
    'management' => [
        'url' => '../modules/management/',
        'icon' => 'fas fa-chart-line',
        'label' => 'Management',
        'roles' => ['manager'],
        'submenu' => [
            'reports' => ['url' => '../modules/management/reports.php', 'label' => 'Reports'],
            'analytics' => ['url' => '../modules/management/analytics.php', 'label' => 'Analytics'],
            'staff' => ['url' => '../modules/management/staff.php', 'label' => 'Staff Management'],
            'settings' => ['url' => '../modules/management/settings.php', 'label' => 'Settings']
        ]
    ],
    'inventory' => [
        'url' => '../modules/inventory/',
        'icon' => 'fas fa-boxes',
        'label' => 'Inventory',
        'roles' => ['manager', 'housekeeping'],
        'submenu' => [
            'items' => ['url' => '../modules/inventory/items.php', 'label' => 'Items'],
            'categories' => ['url' => '../modules/inventory/categories.php', 'label' => 'Categories'],
            'transactions' => ['url' => '../modules/inventory/transactions.php', 'label' => 'Transactions'],
            'reports' => ['url' => '../modules/inventory/reports.php', 'label' => 'Reports']
        ]
    ],
    'training' => [
        'url' => '../modules/training/',
        'icon' => 'fas fa-graduation-cap',
        'label' => 'Training & Simulations',
        'roles' => ['manager', 'front_desk', 'housekeeping'],
        'submenu' => [
            'dashboard' => ['url' => '../modules/training/training-dashboard.php', 'label' => 'Training Dashboard'],
            'scenarios' => ['url' => '../modules/training/scenarios.php', 'label' => 'Scenarios'],
            'customer_service' => ['url' => '../modules/training/customer-service.php', 'label' => 'Customer Service'],
            'problem_solving' => ['url' => '../modules/training/problem-solving.php', 'label' => 'Problem Solving'],
            'progress' => ['url' => '../modules/training/progress.php', 'label' => 'My Progress'],
            'certificates' => ['url' => '../modules/training/certificates.php', 'label' => 'Certificates']
        ]
    ]
];

// Filter navigation items based on user role
$user_navigation = array_filter($navigation_items, function($item) use ($user_role) {
    return in_array($user_role, $item['roles']);
});
?>

<!-- Sidebar -->
<nav id="sidebar" class="fixed left-0 top-16 w-64 h-[calc(100vh-4rem)] bg-white shadow-lg overflow-y-auto z-40 transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
    <div class="p-4 border-b border-gray-200">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-user text-white text-sm"></i>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                <div class="text-xs text-gray-500"><?php echo ucfirst(str_replace('_', ' ', $user_role)); ?></div>
            </div>
        </div>
    </div>
    
    <ul class="py-4">
        <?php foreach ($user_navigation as $key => $item): ?>
            <li class="mb-1">
                <?php if (isset($item['submenu'])): ?>
                    <!-- Menu item with submenu -->
                    <button class="w-full flex items-center justify-between px-6 py-3 text-gray-600 hover:text-primary hover:bg-gray-50 border-l-4 border-transparent hover:border-primary transition-colors" 
                            onclick="toggleSubmenu('<?php echo $key; ?>')">
                        <div class="flex items-center">
                            <i class="<?php echo $item['icon']; ?> w-5 mr-3"></i>
                            <span><?php echo $item['label']; ?></span>
                        </div>
                        <i class="fas fa-chevron-down text-xs transition-transform" id="chevron-<?php echo $key; ?>"></i>
                    </button>
                    <ul id="submenu-<?php echo $key; ?>" class="hidden bg-gray-50">
                        <?php foreach ($item['submenu'] as $subkey => $subitem): ?>
                            <li>
                                <a href="<?php echo $subitem['url']; ?>" 
                                   class="flex items-center px-6 py-2 text-sm text-gray-600 hover:text-primary hover:bg-gray-100 pl-12">
                                    <i class="fas fa-circle text-xs mr-3"></i>
                                    <?php echo $subitem['label']; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <!-- Simple menu item -->
                    <a href="<?php echo $item['url']; ?>" 
                       class="flex items-center px-6 py-3 text-gray-600 hover:text-primary hover:bg-gray-50 border-l-4 border-transparent hover:border-primary transition-colors <?php echo ($current_page === $key || ($key === 'dashboard' && $current_page === 'index')) ? 'text-primary bg-blue-50 border-primary' : ''; ?>">
                        <i class="<?php echo $item['icon']; ?> w-5 mr-3"></i>
                        <?php echo $item['label']; ?>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    
    <!-- Quick Actions Section -->
    <div class="p-4 border-t border-gray-200">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Quick Actions</h3>
        <div class="space-y-2">
            <?php if (in_array($user_role, ['manager', 'front_desk'])): ?>
                <a href="../modules/front-desk/new-reservation.php" class="flex items-center px-3 py-2 text-sm text-gray-600 hover:text-primary hover:bg-gray-50 rounded transition-colors">
                    <i class="fas fa-plus text-xs mr-2"></i>
                    New Reservation
                </a>
            <?php endif; ?>
            
            <?php if (in_array($user_role, ['manager', 'housekeeping'])): ?>
                <a href="../modules/housekeeping/room-status.php" class="flex items-center px-3 py-2 text-sm text-gray-600 hover:text-primary hover:bg-gray-50 rounded transition-colors">
                    <i class="fas fa-clipboard-list text-xs mr-2"></i>
                    Room Status
                </a>
            <?php endif; ?>
            
            <a href="../modules/training/training-dashboard.php" class="flex items-center px-3 py-2 text-sm text-gray-600 hover:text-primary hover:bg-gray-50 rounded transition-colors">
                <i class="fas fa-play text-xs mr-2"></i>
                Start Training
            </a>
        </div>
    </div>
</nav>

<!-- Mobile overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden" onclick="closeSidebar()"></div>
