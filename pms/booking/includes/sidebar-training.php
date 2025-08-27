<?php
// Training-specific sidebar component
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];

// Get current page for active state
$current_url = $_SERVER['REQUEST_URI'];

// Function to check if URL is active
function isActiveUrl($url, $current_url) {
    $current_path = parse_url($current_url, PHP_URL_PATH);
    
    // Handle relative paths in sidebar URLs
    if (strpos($url, '../') === 0) {
        // Convert relative path to absolute path for comparison
        $url = str_replace('../', '/seait/pms/booking/', $url);
    }
    
    // Extract just the filename from the URL for comparison
    $url_filename = basename($url);
    $current_filename = basename($current_path);
    
    return $url_filename === $current_filename;
}

// Function to check if submenu has active item
function hasActiveSubmenu($submenu, $current_url) {
    foreach ($submenu as $item) {
        if (isActiveUrl($item['url'], $current_url)) {
            return true;
        }
    }
    return false;
}



// Navigation items for training module
$navigation_items = [
    [
        'title' => 'Dashboard',
        'url' => '../modules/training/training-dashboard.php',
        'icon' => 'fas fa-tachometer-alt',
        'active' => isActiveUrl('training-dashboard.php', $current_url)
    ],
    [
        'title' => 'Training Scenarios',
        'icon' => 'fas fa-play-circle',
        'active' => isActiveUrl('scenarios.php', $current_url) || isActiveUrl('scenario-start.php', $current_url),
        'submenu' => [
            [
                'title' => 'All Scenarios',
                'url' => '../modules/training/scenarios.php',
                'active' => (isActiveUrl('scenarios.php', $current_url) && !isset($_GET['category'])) || 
                           (isActiveUrl('scenario-start.php', $current_url) && !isset($_GET['category']))
            ],
            [
                'title' => 'Front Desk',
                'url' => '../modules/training/scenarios.php?category=front_desk',
                'active' => (isActiveUrl('scenarios.php', $current_url) && isset($_GET['category']) && $_GET['category'] === 'front_desk') ||
                           (isActiveUrl('scenario-start.php', $current_url) && isset($_GET['id']))
            ],
            [
                'title' => 'Housekeeping',
                'url' => '../modules/training/scenarios.php?category=housekeeping',
                'active' => (isActiveUrl('scenarios.php', $current_url) && isset($_GET['category']) && $_GET['category'] === 'housekeeping') ||
                           (isActiveUrl('scenario-start.php', $current_url) && isset($_GET['id']))
            ],
            [
                'title' => 'Management',
                'url' => '../modules/training/scenarios.php?category=management',
                'active' => (isActiveUrl('scenarios.php', $current_url) && isset($_GET['category']) && $_GET['category'] === 'management') ||
                           (isActiveUrl('scenario-start.php', $current_url) && isset($_GET['id']))
            ]
        ]
    ],
    [
        'title' => 'Customer Service',
        'icon' => 'fas fa-headset',
        'active' => isActiveUrl('customer-service.php', $current_url) || isActiveUrl('customer-service-start.php', $current_url),
        'submenu' => [
            [
                'title' => 'Complaints',
                'url' => '../modules/training/customer-service.php?type=complaints',
                'active' => isActiveUrl('customer-service.php', $current_url) && isset($_GET['type']) && $_GET['type'] === 'complaints'
            ],
            [
                'title' => 'Requests',
                'url' => '../modules/training/customer-service.php?type=requests',
                'active' => isActiveUrl('customer-service.php', $current_url) && isset($_GET['type']) && $_GET['type'] === 'requests'
            ],
            [
                'title' => 'Emergencies',
                'url' => '../modules/training/customer-service.php?type=emergencies',
                'active' => isActiveUrl('customer-service.php', $current_url) && isset($_GET['type']) && $_GET['type'] === 'emergencies'
            ]
        ]
    ],
    [
        'title' => 'Problem Solving',
        'icon' => 'fas fa-puzzle-piece',
        'active' => isActiveUrl('problem-solving.php', $current_url) || isActiveUrl('problem-solving-start.php', $current_url),
        'submenu' => [
            [
                'title' => 'Low Priority',
                'url' => '../modules/training/problem-solving.php?severity=low',
                'active' => isActiveUrl('problem-solving.php', $current_url) && isset($_GET['severity']) && $_GET['severity'] === 'low'
            ],
            [
                'title' => 'Medium Priority',
                'url' => '../modules/training/problem-solving.php?severity=medium',
                'active' => isActiveUrl('problem-solving.php', $current_url) && isset($_GET['severity']) && $_GET['severity'] === 'medium'
            ],
            [
                'title' => 'High Priority',
                'url' => '../modules/training/problem-solving.php?severity=high',
                'active' => isActiveUrl('problem-solving.php', $current_url) && isset($_GET['severity']) && $_GET['severity'] === 'high'
            ],
            [
                'title' => 'Critical',
                'url' => '../modules/training/problem-solving.php?severity=critical',
                'active' => isActiveUrl('problem-solving.php', $current_url) && isset($_GET['severity']) && $_GET['severity'] === 'critical'
            ]
        ]
    ],
    [
        'title' => 'Progress Tracking',
        'icon' => 'fas fa-chart-line',
        'active' => isActiveUrl('progress.php', $current_url),
        'submenu' => [
            [
                'title' => 'My Progress',
                'url' => '../modules/training/progress.php',
                'active' => isActiveUrl('progress.php', $current_url) && !isset($_GET['view'])
            ],
            [
                'title' => 'Certificates',
                'url' => '../modules/training/certificates.php',
                'active' => isActiveUrl('certificates.php', $current_url)
            ],
            [
                'title' => 'Leaderboard',
                'url' => '../modules/training/leaderboard.php',
                'active' => isActiveUrl('leaderboard.php', $current_url)
            ]
        ]
    ],
    [
        'title' => 'Resources',
        'icon' => 'fas fa-book',
        'active' => isActiveUrl('resources.php', $current_url),
        'submenu' => [
            [
                'title' => 'Training Materials',
                'url' => '../modules/training/materials.php',
                'active' => isActiveUrl('materials.php', $current_url)
            ],
            [
                'title' => 'Best Practices',
                'url' => '../modules/training/best-practices.php',
                'active' => isActiveUrl('best-practices.php', $current_url)
            ],
            [
                'title' => 'FAQ',
                'url' => '../modules/training/faq.php',
                'active' => isActiveUrl('faq.php', $current_url)
            ]
        ]
    ]
];
?>



<!-- Sidebar -->
<div id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-gradient-to-b from-purple-800 to-indigo-900 text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out z-40">
    <!-- Sidebar Header -->
    <div class="flex items-center justify-between p-6 border-b border-purple-700">
        <div class="flex items-center">
            <i class="fas fa-graduation-cap text-yellow-400 text-2xl mr-3"></i>
            <div>
                <h2 class="text-lg font-semibold">Training Center</h2>
                <p class="text-xs text-purple-300">Hotel PMS</p>
            </div>
        </div>
        <button id="close-sidebar" class="lg:hidden text-white hover:text-gray-300">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>

    <!-- User Profile Section -->
    <div class="p-6 border-b border-purple-700">
        <div class="flex items-center">
            <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                <span class="text-white font-medium"><?php echo strtoupper(substr($user_name, 0, 1)); ?></span>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium"><?php echo htmlspecialchars($user_name); ?></p>
                <p class="text-xs text-purple-300">Training Participant</p>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 overflow-y-auto py-4">
        <ul class="space-y-2 px-4">
            <?php foreach ($navigation_items as $item): ?>
                <li>
                    <?php if (isset($item['submenu'])): ?>
                        <!-- Menu item with submenu -->
                        <div class="sidebar-nav-item <?php echo $item['active'] || hasActiveSubmenu($item['submenu'], $current_url) ? 'active' : ''; ?> rounded-lg">
                            <button onclick="toggleTrainingSubmenu('<?php echo strtolower(str_replace(' ', '-', $item['title'])); ?>')" class="w-full flex items-center justify-between p-3 text-left hover:bg-purple-700 rounded-lg transition-colors">
                                <div class="flex items-center">
                                    <i class="<?php echo $item['icon']; ?> w-5 h-5 mr-3"></i>
                                    <span class="text-sm font-medium"><?php echo $item['title']; ?></span>
                                </div>
                                <i id="chevron-<?php echo strtolower(str_replace(' ', '-', $item['title'])); ?>" class="fas fa-chevron-right text-xs transition-transform duration-200 <?php echo $item['active'] || hasActiveSubmenu($item['submenu'], $current_url) ? 'rotate-90' : ''; ?>"></i>
                            </button>
                            <ul id="submenu-<?php echo strtolower(str_replace(' ', '-', $item['title'])); ?>" class="submenu ml-8 mt-2 space-y-1 <?php echo $item['active'] || hasActiveSubmenu($item['submenu'], $current_url) ? 'block' : 'hidden'; ?>">
                                <?php foreach ($item['submenu'] as $subitem): ?>
                                    <li>
                                        <a href="<?php echo $subitem['url']; ?>" class="sidebar-submenu-item <?php echo $subitem['active'] ? 'active' : ''; ?> block p-2 text-sm rounded-lg hover:bg-purple-700 transition-colors">
                                            <?php echo $subitem['title']; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Simple menu item -->
                        <a href="<?php echo $item['url']; ?>" class="sidebar-nav-item <?php echo $item['active'] ? 'active' : ''; ?> flex items-center p-3 rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="<?php echo $item['icon']; ?> w-5 h-5 mr-3"></i>
                            <span class="text-sm font-medium"><?php echo $item['title']; ?></span>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Sidebar Footer -->
    <div class="p-4 border-t border-purple-700">
        <div class="text-center">
            <p class="text-xs text-purple-300">Training Progress</p>
            <div class="w-full bg-purple-700 rounded-full h-2 mt-2">
                <div class="bg-yellow-400 h-2 rounded-full" style="width: 65%"></div>
            </div>
            <p class="text-xs text-purple-300 mt-1">65% Complete</p>
        </div>
    </div>
</div>
