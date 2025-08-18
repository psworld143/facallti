<?php
session_start();
require_once 'config/database.php';
require_once 'includes/services_crud.php';

// Get service ID from URL
$service_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$service_id) {
    header('Location: services.php');
    exit;
}

// Get service details
$service = get_service_by_id($conn, $service_id);
if (!$service) {
    header('Location: services.php');
    exit;
}

// Get service details (sub-services)
$service_details = get_service_details($conn, $service_id);

// Get related services from the same category
$related_services = get_services_by_category($conn, $service['category_id']);
$related_services = array_filter($related_services, function($s) use ($service_id) {
    return $s['id'] != $service_id;
});
$related_services = array_slice($related_services, 0, 3); // Limit to 3 related services
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($service['name']); ?> - SEAIT Services</title>
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

        .service-hero {
            background: linear-gradient(135deg, <?php echo $service['color_theme']; ?>20 0%, <?php echo $service['color_theme']; ?>10 100%);
        }

        .detail-card {
            transition: all 0.3s ease;
        }

        .detail-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="service-hero py-20">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-full mb-6"
                     style="background-color: <?php echo $service['color_theme']; ?>20;">
                    <i class="<?php echo htmlspecialchars($service['icon']); ?> text-4xl"
                       style="color: <?php echo $service['color_theme']; ?>"></i>
                </div>
                <h1 class="text-4xl md:text-6xl font-bold text-seait-dark dark:text-white mb-4">
                    <?php echo htmlspecialchars($service['name']); ?>
                </h1>
                <?php if (!empty($service['category_name'])): ?>
                <p class="text-xl text-gray-600 dark:text-gray-300 mb-8">
                    <?php echo htmlspecialchars($service['category_name']); ?>
                </p>
                <?php endif; ?>
                <p class="text-lg text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                    <?php echo htmlspecialchars($service['description']); ?>
                </p>
            </div>
        </div>
    </section>

    <!-- Service Details -->
    <?php if (!empty($service_details)): ?>
    <section class="py-16 bg-white dark:bg-gray-800">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-seait-dark dark:text-white mb-4">What We Offer</h2>
                <p class="text-lg text-gray-600 dark:text-gray-300">Comprehensive features and support for this service</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($service_details as $detail): ?>
                <div class="detail-card bg-white dark:bg-gray-700 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-600">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4"
                             style="background-color: <?php echo $service['color_theme']; ?>20;">
                            <i class="<?php echo htmlspecialchars($detail['icon']); ?> text-xl"
                               style="color: <?php echo $service['color_theme']; ?>"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-seait-dark dark:text-white">
                            <?php echo htmlspecialchars($detail['title']); ?>
                        </h3>
                    </div>
                    <p class="text-gray-600 dark:text-gray-300">
                        <?php echo htmlspecialchars($detail['content']); ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- How to Access -->
    <section class="py-16 bg-gray-50 dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-seait-dark dark:text-white mb-4">How to Access</h2>
                <p class="text-lg text-gray-600 dark:text-gray-300">Get started with this service in a few simple steps</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-seait-orange rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-user-plus text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-seait-dark dark:text-white mb-2">1. Contact Us</h3>
                    <p class="text-gray-600 dark:text-gray-300">Reach out to our support team to get started</p>
                </div>

                <div class="text-center">
                    <div class="w-16 h-16 bg-seait-orange rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-calendar-check text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-seait-dark dark:text-white mb-2">2. Schedule Appointment</h3>
                    <p class="text-gray-600 dark:text-gray-300">Book a convenient time for your consultation</p>
                </div>

                <div class="text-center">
                    <div class="w-16 h-16 bg-seait-orange rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check-circle text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-seait-dark dark:text-white mb-2">3. Get Started</h3>
                    <p class="text-gray-600 dark:text-gray-300">Begin using the service with our guidance</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Related Services -->
    <?php if (!empty($related_services)): ?>
    <section class="py-16 bg-white dark:bg-gray-800">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-seait-dark dark:text-white mb-4">Related Services</h2>
                <p class="text-lg text-gray-600 dark:text-gray-300">Explore other services in this category</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach ($related_services as $related_service): ?>
                <div class="detail-card bg-white dark:bg-gray-700 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-600">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4"
                             style="background-color: <?php echo $related_service['color_theme']; ?>20;">
                            <i class="<?php echo htmlspecialchars($related_service['icon']); ?> text-xl"
                               style="color: <?php echo $related_service['color_theme']; ?>"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-seait-dark dark:text-white">
                            <?php echo htmlspecialchars($related_service['name']); ?>
                        </h3>
                    </div>
                    <p class="text-gray-600 dark:text-gray-300 mb-4">
                        <?php echo htmlspecialchars(substr($related_service['description'], 0, 100)); ?>...
                    </p>
                    <a href="service-detail.php?id=<?php echo $related_service['id']; ?>"
                       class="text-seait-orange hover:text-orange-600 font-semibold transition">
                        Learn More <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Contact Section -->
    <section class="bg-gradient-to-r from-seait-orange to-orange-600 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-4">Ready to Get Started?</h2>
            <p class="text-xl mb-8 opacity-90">Contact our team to access this service</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="contact.php" class="bg-white text-seait-orange px-8 py-4 rounded-lg font-semibold hover:bg-gray-100 transition">
                    <i class="fas fa-envelope mr-2"></i>Contact Support
                </a>
                <a href="tel:+1234567890" class="bg-transparent border-2 border-white text-white px-8 py-4 rounded-lg font-semibold hover:bg-white hover:text-seait-orange transition">
                    <i class="fas fa-phone mr-2"></i>Call Now
                </a>
                <a href="services.php" class="bg-transparent border-2 border-white text-white px-8 py-4 rounded-lg font-semibold hover:bg-white hover:text-seait-orange transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Services
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Include FAB Inquiry System -->
    <?php include 'includes/fab-inquiry.php'; ?>

    <script>
</script>
</body>
</html>