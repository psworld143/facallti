<?php
// Manager-specific sidebar component
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ../login.php');
    exit();
}

$user_role = $_SESSION['user_role'];
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Manager navigation items
$navigation_items = [
    'dashboard' => [
        'url' => '../index.php',
        'icon' => 'fas fa-tachometer-alt',
        'label' => 'Dashboard',
        'active' => ($current_page === 'index')
    ],
    'management' => [
        'icon' => 'fas fa-chart-line',
        'label' => 'Management',
        'submenu' => [
            'reports' => ['url' => '../modules/management/reports.php', 'label' => 'Reports & Analytics'],
            'analytics' => ['url' => '../modules/management/analytics.php', 'label' => 'Business Analytics'],
            'staff' => ['url' => '../modules/management/staff.php', 'label' => 'Staff Management'],
            'settings' => ['url' => '../modules/management/settings.php', 'label' => 'System Settings'],
            'audit_log' => ['url' => '../modules/management/audit-log.php', 'label' => 'Audit Log']
        ]
    ],
    'front_desk' => [
        'icon' => 'fas fa-concierge-bell',
        'label' => 'Front Desk',
        'submenu' => [
            'reservations' => ['url' => '../modules/front-desk/manage-reservations.php', 'label' => 'Reservations'],
            'check_in' => ['url' => '../modules/front-desk/check-in.php', 'label' => 'Check In'],
            'check_out' => ['url' => '../modules/front-desk/check-out.php', 'label' => 'Check Out'],
            'walk_ins' => ['url' => '../modules/front-desk/walk-ins.php', 'label' => 'Walk-ins'],
            'guest_services' => ['url' => '../modules/front-desk/guest-services.php', 'label' => 'Guest Services']
        ]
    ],
    'housekeeping' => [
        'icon' => 'fas fa-broom',
        'label' => 'Housekeeping',
        'submenu' => [
            'room_status' => ['url' => '../modules/housekeeping/room-status.php', 'label' => 'Room Status'],
            'tasks' => ['url' => '../modules/housekeeping/tasks.php', 'label' => 'Task Management'],
            'maintenance' => ['url' => '../modules/housekeeping/maintenance.php', 'label' => 'Maintenance'],
            'inventory' => ['url' => '../modules/housekeeping/inventory.php', 'label' => 'Inventory']
        ]
    ],
    'guests' => [
        'icon' => 'fas fa-users',
        'label' => 'Guest Management',
        'submenu' => [
            'profiles' => ['url' => '../modules/guests/profiles.php', 'label' => 'Guest Profiles'],
            'vip' => ['url' => '../modules/guests/vip-management.php', 'label' => 'VIP Guests'],
            'feedback' => ['url' => '../modules/guests/feedback.php', 'label' => 'Feedback'],
            'loyalty' => ['url' => '../modules/guests/loyalty.php', 'label' => 'Loyalty Program']
        ]
    ],
    'billing' => [
        'icon' => 'fas fa-credit-card',
        'label' => 'Billing & Payments',
        'submenu' => [
            'invoices' => ['url' => '../modules/billing/invoices.php', 'label' => 'Invoices'],
            'payments' => ['url' => '../modules/billing/payments.php', 'label' => 'Payments'],
            'discounts' => ['url' => '../modules/billing/discounts.php', 'label' => 'Discounts'],
            'vouchers' => ['url' => '../modules/billing/vouchers.php', 'label' => 'Vouchers'],
            'reports' => ['url' => '../modules/billing/reports.php', 'label' => 'Revenue Reports']
        ]
    ],
    'inventory' => [
        'icon' => 'fas fa-boxes',
        'label' => 'Inventory',
        'submenu' => [
            'items' => ['url' => '../modules/inventory/items.php', 'label' => 'Items'],
            'categories' => ['url' => '../modules/inventory/categories.php', 'label' => 'Categories'],
            'transactions' => ['url' => '../modules/inventory/transactions.php', 'label' => 'Transactions'],
            'reports' => ['url' => '../modules/inventory/reports.php', 'label' => 'Reports']
        ]
    ],
    'training' => [
        'icon' => 'fas fa-graduation-cap',
        'label' => 'Training & Simulations',
        'submenu' => [
            'dashboard' => ['url' => '../modules/training/training-dashboard.php', 'label' => 'Training Dashboard'],
            'scenarios' => ['url' => '../modules/training/scenarios.php', 'label' => 'Scenarios'],
            'customer_service' => ['url' => '../modules/training/customer-service.php', 'label' => 'Customer Service'],
            'problem_solving' => ['url' => '../modules/training/problem-solving.php', 'label' => 'Problem Solving'],
            'progress' => ['url' => '../modules/training/progress.php', 'label' => 'Staff Progress'],
            'certificates' => ['url' => '../modules/training/certificates.php', 'label' => 'Certificates']
        ]
    ]
];
?>

<!-- Manager Sidebar -->
<nav id="sidebar" class="fixed left-0 top-16 w-64 h-[calc(100vh-4rem)] bg-white shadow-lg overflow-y-auto z-40 transition-all duration-300" data-collapsed="false">
    <div class="p-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <div class="flex items-center sidebar-content">
                <div class="w-8 h-8 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-crown text-white text-sm"></i>
                </div>
                <div class="sidebar-text">
                    <div class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                    <div class="text-xs text-gray-500">System Manager</div>
                </div>
            </div>

        </div>
    </div>
    
    <ul class="py-4">
        <?php foreach ($navigation_items as $key => $item): ?>
            <li class="mb-1">
                <?php if (isset($item['submenu'])): ?>
                    <!-- Menu item with submenu -->
                    <button class="w-full flex items-center justify-between px-6 py-3 text-gray-600 hover:text-purple-600 hover:bg-purple-50 border-l-4 border-transparent hover:border-purple-600 transition-colors" 
                            onclick="toggleSubmenu('<?php echo $key; ?>')">
                        <div class="flex items-center">
                            <i class="<?php echo $item['icon']; ?> w-5 mr-3 sidebar-icon"></i>
                            <span class="sidebar-text"><?php echo $item['label']; ?></span>
                        </div>
                        <i class="fas fa-chevron-down text-xs transition-transform sidebar-text" id="chevron-<?php echo $key; ?>"></i>
                    </button>
                    <ul id="submenu-<?php echo $key; ?>" class="hidden bg-gray-50">
                        <?php foreach ($item['submenu'] as $subkey => $subitem): ?>
                            <li>
                                <a href="<?php echo $subitem['url']; ?>" 
                                   class="flex items-center px-6 py-2 text-sm text-gray-600 hover:text-purple-600 hover:bg-purple-100 pl-12">
                                    <i class="fas fa-circle text-xs mr-3"></i>
                                    <span class="sidebar-text"><?php echo $subitem['label']; ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <!-- Simple menu item -->
                    <a href="<?php echo $item['url']; ?>" 
                       class="flex items-center px-6 py-3 text-gray-600 hover:text-purple-600 hover:bg-purple-50 border-l-4 border-transparent hover:border-purple-600 transition-colors <?php echo $item['active'] ? 'text-purple-600 bg-purple-50 border-purple-600' : ''; ?>">
                        <i class="<?php echo $item['icon']; ?> w-5 mr-3 sidebar-icon"></i>
                        <span class="sidebar-text"><?php echo $item['label']; ?></span>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    
    <!-- Management Quick Actions -->
    <div class="p-4 border-t border-gray-200">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 sidebar-text">Management Actions</h3>
        <div class="space-y-2">
            <a href="../modules/management/reports.php" class="flex items-center px-3 py-2 text-sm text-gray-600 hover:text-purple-600 hover:bg-purple-50 rounded transition-colors">
                <i class="fas fa-chart-bar text-xs mr-2 sidebar-icon"></i>
                <span class="sidebar-text">View Reports</span>
            </a>
            <a href="../modules/management/staff.php" class="flex items-center px-3 py-2 text-sm text-gray-600 hover:text-purple-600 hover:bg-purple-50 rounded transition-colors">
                <i class="fas fa-users-cog text-xs mr-2 sidebar-icon"></i>
                <span class="sidebar-text">Staff Management</span>
            </a>
            <a href="../modules/management/settings.php" class="flex items-center px-3 py-2 text-sm text-gray-600 hover:text-purple-600 hover:bg-purple-50 rounded transition-colors">
                <i class="fas fa-cog text-xs mr-2 sidebar-icon"></i>
                <span class="sidebar-text">System Settings</span>
            </a>
            <a href="../modules/management/audit-log.php" class="flex items-center px-3 py-2 text-sm text-gray-600 hover:text-purple-600 hover:bg-purple-50 rounded transition-colors">
                <i class="fas fa-history text-xs mr-2 sidebar-icon"></i>
                <span class="sidebar-text">Audit Log</span>
            </a>
        </div>
    </div>
</nav>

<!-- Mobile overlay -->
