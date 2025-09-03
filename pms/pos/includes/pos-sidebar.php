<?php
// POS-specific sidebar component that matches the exact booking system design
// This file should not contain session checks or redirects

$user_role = $_SESSION['pos_user_role'] ?? 'pos_user';
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Define POS navigation items based on user role
$navigation_items = [
    'dashboard' => [
        'url' => '/seait/pms/pos/index.php',
        'icon' => 'fas fa-tachometer-alt',
        'label' => 'POS Dashboard',
        'roles' => ['manager', 'front_desk', 'pos_user', 'student']
    ],
    'restaurant' => [
        'url' => '/seait/pms/pos/restaurant/',
        'icon' => 'fas fa-utensils',
        'label' => 'Restaurant POS',
        'roles' => ['manager', 'front_desk', 'pos_user', 'student'],
        'submenu' => [
            'menu' => ['url' => '/seait/pms/pos/restaurant/menu.php', 'label' => 'Menu Management'],
            'orders' => ['url' => '/seait/pms/pos/restaurant/orders.php', 'label' => 'Active Orders'],
            'tables' => ['url' => '/seait/pms/pos/restaurant/tables.php', 'label' => 'Table Management'],
            'reports' => ['url' => '/seait/pms/pos/restaurant/reports.php', 'label' => 'Restaurant Reports']
        ]
    ],
    'room_service' => [
        'url' => '/seait/pms/pos/room-service/',
        'icon' => 'fas fa-bed',
        'label' => 'Room Service',
        'roles' => ['manager', 'front_desk', 'pos_user', 'student'],
        'submenu' => [
            'orders' => ['url' => '/seait/pms/pos/room-service/orders.php', 'label' => 'Room Orders'],
            'delivery' => ['url' => '/seait/pms/pos/room-service/delivery.php', 'label' => 'Delivery Status'],
            'menu' => ['url' => '/seait/pms/pos/room-service/menu.php', 'label' => 'Room Service Menu'],
            'reports' => ['url' => '/seait/pms/pos/room-service/reports.php', 'label' => 'Room Service Reports']
        ]
    ],
    'spa' => [
        'url' => '/seait/pms/pos/spa/',
        'icon' => 'fas fa-spa',
        'label' => 'Spa & Wellness',
        'roles' => ['manager', 'front_desk', 'pos_user', 'student'],
        'submenu' => [
            'services' => ['url' => '/seait/pms/pos/spa/services.php', 'label' => 'Spa Services'],
            'appointments' => ['url' => '/seait/pms/pos/spa/appointments.php', 'label' => 'Appointments'],
            'therapists' => ['url' => '/seait/pms/pos/spa/therapists.php', 'label' => 'Therapists'],
            'reports' => ['url' => '/seait/pms/pos/spa/reports.php', 'label' => 'Spa Reports']
        ]
    ],
    'gift_shop' => [
        'url' => '/seait/pms/pos/gift-shop/',
        'icon' => 'fas fa-gift',
        'label' => 'Gift Shop',
        'roles' => ['manager', 'front_desk', 'pos_user', 'student'],
        'submenu' => [
            'inventory' => ['url' => '/seait/pms/pos/gift-shop/inventory.php', 'label' => 'Inventory'],
            'sales' => ['url' => '/seait/pms/pos/gift-shop/sales.php', 'label' => 'Sales'],
            'products' => ['url' => '/seait/pms/pos/gift-shop/products.php', 'label' => 'Products'],
            'reports' => ['url' => '/seait/pms/pos/gift-shop/reports.php', 'label' => 'Gift Shop Reports']
        ]
    ],
    'events' => [
        'url' => '/seait/pms/pos/events/',
        'icon' => 'fas fa-calendar-alt',
        'label' => 'Event Services',
        'roles' => ['manager', 'front_desk', 'pos_user', 'student'],
        'submenu' => [
            'bookings' => ['url' => '/seait/pms/pos/events/bookings.php', 'label' => 'Event Bookings'],
            'services' => ['url' => '/seait/pms/pos/events/services.php', 'label' => 'Event Services'],
            'venues' => ['url' => '/seait/pms/pos/events/venues.php', 'label' => 'Venues'],
            'reports' => ['url' => '/seait/pms/pos/events/reports.php', 'label' => 'Event Reports']
        ]
    ],
    'quick_sales' => [
        'url' => '/seait/pms/pos/quick-sales/',
        'icon' => 'fas fa-bolt',
        'label' => 'Quick Sales',
        'roles' => ['manager', 'front_desk', 'pos_user', 'student'],
        'submenu' => [
            'transactions' => ['url' => '/seait/pms/pos/quick-sales/transactions.php', 'label' => 'Transactions'],
            'items' => ['url' => '/seait/pms/pos/quick-sales/items.php', 'label' => 'Quick Items'],
            'history' => ['url' => '/seait/pms/pos/quick-sales/history.php', 'label' => 'Sales History'],
            'reports' => ['url' => '/seait/pms/pos/quick-sales/reports.php', 'label' => 'Quick Sales Reports']
        ]
    ],
    'reports' => [
        'url' => '/seait/pms/pos/reports/',
        'icon' => 'fas fa-chart-bar',
        'label' => 'Reports & Analytics',
        'roles' => ['manager', 'pos_user'],
        'submenu' => [
            'sales' => ['url' => '/seait/pms/pos/reports/sales.php', 'label' => 'Sales Reports'],
            'inventory' => ['url' => '/seait/pms/pos/reports/inventory.php', 'label' => 'Inventory Reports'],
            'performance' => ['url' => '/seait/pms/pos/reports/performance.php', 'label' => 'Performance Reports'],
            'analytics' => ['url' => '/seait/pms/pos/reports/analytics.php', 'label' => 'Analytics Dashboard']
        ]
    ]
];

// Filter navigation items based on user role
$user_navigation = array_filter($navigation_items, function($item) use ($user_role) {
    return in_array($user_role, $item['roles']);
});
?>

<!-- Sidebar - Matching booking system exactly -->
<nav id="sidebar" class="fixed left-0 top-16 w-64 h-[calc(100vh-4rem)] bg-white shadow-lg overflow-y-auto z-40 transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
    <div class="p-4 border-b border-gray-200">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-cash-register text-white text-sm"></i>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['pos_user_name'] ?? 'POS User'); ?></div>
                <div class="text-xs text-gray-500">
                    <?php if (isset($_SESSION['pos_demo_mode']) && $_SESSION['pos_demo_mode']): ?>
                        <i class="fas fa-graduation-cap mr-1"></i>Student Trainee
                    <?php else: ?>
                        <?php echo ucfirst(str_replace('_', ' ', $user_role)); ?>
                    <?php endif; ?>
                </div>
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
            <a href="/seait/pms/pos/quick-sales/" class="flex items-center px-3 py-2 text-sm text-gray-600 hover:text-primary hover:bg-gray-50 rounded transition-colors">
                <i class="fas fa-plus text-xs mr-2"></i>
                New Transaction
            </a>
            
            <a href="/seait/pms/pos/restaurant/" class="flex items-center px-3 py-2 text-sm text-gray-600 hover:text-primary hover:bg-gray-50 rounded transition-colors">
                <i class="fas fa-utensils text-xs mr-2"></i>
                Restaurant Orders
            </a>
            
            <a href="/seait/pms/pos/room-service/" class="flex items-center px-3 py-2 text-sm text-gray-600 hover:text-primary hover:bg-gray-50 rounded transition-colors">
                <i class="fas fa-bed text-xs mr-2"></i>
                Room Service
            </a>
            
            <?php if (isset($_SESSION['pos_demo_mode']) && $_SESSION['pos_demo_mode']): ?>
                <a href="../../booking/modules/training/training-dashboard.php" class="flex items-center px-3 py-2 text-sm text-gray-600 hover:text-primary hover:bg-gray-50 rounded transition-colors">
                    <i class="fas fa-play text-xs mr-2"></i>
                    Training Dashboard
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Mobile overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden" onclick="closeSidebar()"></div>
