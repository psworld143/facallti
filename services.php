<?php
session_start();
require_once 'config/database.php';
require_once 'includes/services_crud.php';

// Get all service categories
$service_categories = get_service_categories($conn);

// Get all services
$services = get_services($conn);

// Get services statistics
$stats = get_services_statistics($conn);

// Handle search
$search_results = [];
$search_term = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = $_GET['search'];
    $search_results = search_services($conn, $search_term);
}

// Handle category filter
$filtered_services = [];
$selected_category = '';
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $selected_category = $_GET['category'];
    $filtered_services = get_services_by_category($conn, $selected_category);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - SEAIT</title>
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

        .service-card {
            transition: all 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-5px);
        }

        .category-tab.active {
            background-color: #FF6B35;
            color: white;
        }

        .category-tab:not(.active) {
            background-color: #E5E7EB;
            color: #374151;
        }

        .category-tab:not(.active):hover {
            background-color: #D1D5DB;
        }

        /* Dark mode category tab styles */
        .dark .category-tab:not(.active) {
            background-color: #374151;
            color: #D1D5DB;
        }

        .dark .category-tab:not(.active):hover {
            background-color: #4B5563;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-seait-orange to-orange-600 text-white py-20">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center">
                <h1 class="text-4xl md:text-6xl font-bold mb-6">Our Services</h1>
                <p class="text-xl md:text-2xl mb-8 opacity-90">Comprehensive support services to enhance your academic journey</p>

                <!-- Search Bar -->
                <div class="max-w-2xl mx-auto">
                    <form method="GET" class="flex">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>"
                               placeholder="Search services..."
                               class="flex-1 px-6 py-4 rounded-l-lg text-gray-900 dark:text-gray-100 dark:bg-gray-800 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-white dark:focus:ring-seait-orange">
                        <button type="submit" class="bg-white dark:bg-gray-800 text-seait-orange dark:text-white px-6 py-4 rounded-r-lg font-semibold hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-12 bg-white dark:bg-gray-800">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center p-6 bg-seait-light dark:bg-gray-700 rounded-lg">
                    <div class="text-3xl font-bold text-seait-orange mb-2"><?php echo $stats['total_services']; ?></div>
                    <div class="text-gray-600 dark:text-gray-300">Total Services</div>
                </div>
                <div class="text-center p-6 bg-seait-light dark:bg-gray-700 rounded-lg">
                    <div class="text-3xl font-bold text-seait-orange mb-2"><?php echo $stats['total_categories']; ?></div>
                    <div class="text-gray-600 dark:text-gray-300">Service Categories</div>
                </div>
                <div class="text-center p-6 bg-seait-light dark:bg-gray-700 rounded-lg">
                    <div class="text-3xl font-bold text-seait-orange mb-2">24/7</div>
                    <div class="text-gray-600 dark:text-gray-300">Support Available</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="py-16 bg-gray-50 dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4">
            <!-- Category Tabs -->
            <div class="mb-12">
                <div class="flex flex-wrap justify-center gap-4">
                    <a href="services.php" class="category-tab px-6 py-3 rounded-lg font-semibold transition <?php echo empty($selected_category) ? 'active' : ''; ?>">
                        All Services
                    </a>
                    <?php foreach ($service_categories as $category): ?>
                    <a href="services.php?category=<?php echo $category['id']; ?>"
                       class="category-tab px-6 py-3 rounded-lg font-semibold transition <?php echo $selected_category == $category['id'] ? 'active' : ''; ?>">
                        <i class="<?php echo htmlspecialchars($category['icon']); ?> mr-2"></i>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Services Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php
                $services_to_display = !empty($search_results) ? $search_results :
                                     (!empty($filtered_services) ? $filtered_services : $services);

                if (empty($services_to_display)):
                ?>
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-search text-6xl text-gray-400 dark:text-gray-500 mb-4"></i>
                    <h3 class="text-2xl font-semibold text-gray-600 dark:text-gray-300 mb-2">No Services Found</h3>
                    <p class="text-gray-500 dark:text-gray-400">Try adjusting your search criteria or browse all services.</p>
                    <a href="services.php" class="inline-block mt-4 bg-seait-orange text-white px-6 py-3 rounded-lg hover:bg-orange-600 transition">
                        View All Services
                    </a>
                </div>
                <?php else: ?>
                    <?php foreach ($services_to_display as $service): ?>
                    <div class="service-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 hover:shadow-xl border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center mb-4">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center mr-4"
                                 style="background-color: <?php echo $service['color_theme']; ?>20; color: <?php echo $service['color_theme']; ?>">
                                <i class="<?php echo htmlspecialchars($service['icon']); ?> text-2xl"
                                   style="color: <?php echo $service['color_theme']; ?>"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold text-seait-dark dark:text-white mb-1">
                                    <?php echo htmlspecialchars($service['name']); ?>
                                </h3>
                                <?php if (!empty($service['category_name'])): ?>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($service['category_name']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <p class="text-gray-600 dark:text-gray-300 mb-6">
                            <?php echo htmlspecialchars($service['description']); ?>
                        </p>

                        <div class="flex justify-between items-center">
                            <a href="service-detail.php?id=<?php echo $service['id']; ?>"
                               class="text-seait-orange hover:text-orange-600 font-semibold transition">
                                Learn More <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                            <div class="flex space-x-2">
                                <a href="service-detail.php?id=<?php echo $service['id']; ?>"
                                   class="bg-seait-orange text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-600 transition">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="bg-gradient-to-r from-seait-orange to-orange-600 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-4">Need Help?</h2>
            <p class="text-xl mb-8 opacity-90">Our support team is here to assist you with any questions or concerns</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="contact.php" class="bg-white text-seait-orange px-8 py-4 rounded-lg font-semibold hover:bg-gray-100 transition">
                    <i class="fas fa-envelope mr-2"></i>Contact Support
                </a>
                <a href="tel:+1234567890" class="bg-transparent border-2 border-white text-white px-8 py-4 rounded-lg font-semibold hover:bg-white hover:text-seait-orange transition">
                    <i class="fas fa-phone mr-2"></i>Call Now
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Include FAB Inquiry System -->
    <?php include 'includes/fab-inquiry.php'; ?>

    <script>
        // Category tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const categoryTabs = document.querySelectorAll('.category-tab');

            categoryTabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    // Remove active class from all tabs
                    categoryTabs.forEach(t => t.classList.remove('active'));
                    // Add active class to clicked tab
                    this.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>