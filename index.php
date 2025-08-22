<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Fetch approved news, events and announcements
$news_query = "SELECT * FROM posts WHERE status = 'approved' AND (type = 'news' OR type = 'event') ORDER BY created_at DESC LIMIT 6";
$news_result = mysqli_query($conn, $news_query);

$announcements_query = "SELECT * FROM posts WHERE status = 'approved' AND type = 'announcement' ORDER BY created_at DESC LIMIT 3";
$announcements_result = mysqli_query($conn, $announcements_query);

// Fetch active carousel slides
$carousel_query = "SELECT * FROM carousel_slides WHERE status = 'approved' AND is_active = 1 ORDER BY sort_order ASC, created_at DESC LIMIT 10";
$carousel_result = mysqli_query($conn, $carousel_query);

// Fetch admission levels and their data
$admission_levels_query = "SELECT * FROM admission_levels WHERE is_active = 1 ORDER BY sort_order ASC, name ASC";
$admission_levels_result = mysqli_query($conn, $admission_levels_query);

// Fetch admission contacts
$admission_contacts_query = "SELECT * FROM admission_contacts WHERE is_active = 1 ORDER BY sort_order ASC, title ASC";
$admission_contacts_result = mysqli_query($conn, $admission_contacts_query);

// Fetch active colleges with their courses
$colleges_query = "SELECT c.*,
                          COUNT(co.id) as course_count
                   FROM colleges c
                   LEFT JOIN courses co ON c.id = co.college_id AND co.is_active = 1
                   WHERE c.is_active = 1
                   GROUP BY c.id
                   ORDER BY c.sort_order ASC, c.name ASC";
$colleges_result = mysqli_query($conn, $colleges_query);

// Fetch all active courses for display
$courses_query = "SELECT co.*, c.name as college_name, c.short_name as college_short_name, c.color_theme, c.logo_url
                  FROM courses co
                  JOIN colleges c ON co.college_id = c.id
                  WHERE co.is_active = 1 AND c.is_active = 1
                  ORDER BY c.sort_order ASC, co.sort_order ASC, co.name ASC";
$courses_result = mysqli_query($conn, $courses_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEAIT - South East Asian Institute of Technology, Inc.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="tailwind/tailwind.js"></script>
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

        .prose a:hover {
            color: #EA580C;
        }

        /* Admission Tab Styles */
        .admission-tab.active {
            background-color: #FF6B35;
            color: white;
        }
        .admission-tab:not(.active) {
            background-color: #E5E7EB;
            color: #374151;
        }
        .admission-tab:not(.active):hover {
            background-color: #D1D5DB;
        }

        /* Timeline Scrollbar Styles */
        .timeline-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .timeline-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .timeline-scroll::-webkit-scrollbar-thumb {
            background: #FF6B35;
            border-radius: 3px;
        }
        .timeline-scroll::-webkit-scrollbar-thumb:hover {
            background: #e55a2b;
        }

        /* Equal Height Sections */
        .history-grid {
            min-height: 400px;
        }
        .timeline-section {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .mission-vision-section {
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Smooth Scrolling and Navbar Offset */
        html {
            scroll-behavior: smooth;
        }
        section {
            scroll-margin-top: 60px; /* Reduced from 80px to 60px */
        }

        /* Perfect Circular Icons */
        .contact-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .contact-icon-large {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* College Icon Styles - Ensure Perfect Circles */
        .college-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }
        .college-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        .college-icon-fallback {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* Enhanced carousel transitions */
        .carousel-slide {
            transition: opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1), transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Ensure carousel content is visible when slide is active */
        .carousel-slide.opacity-100 .carousel-title,
        .carousel-slide.opacity-100 .carousel-subtitle,
        .carousel-slide.opacity-100 .carousel-description,
        .carousel-slide.opacity-100 .carousel-button {
            transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Override any conflicting styles for carousel content */
        .carousel-title,
        .carousel-subtitle,
        .carousel-description,
        .carousel-button {
            transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: opacity, transform;
        }

        /* Enhanced navigation styles */
        .carousel-dot {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .carousel-nav {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Active navbar link styles */
        .navbar-link-active {
            color: #FF6B35 !important;
            font-weight: 600;
        }
        .navbar-link-active:hover {
            color: #FF6B35 !important;
        }

        .carousel-title, .carousel-subtitle, .carousel-description {
            text-shadow: 0 2px 8px rgba(0,0,0,0.35), 0 1px 2px rgba(0,0,0,0.25);
        }

        /* Page Loader Styles */
        #page-loader {
            background: #fff;
            transition: opacity 0.5s;
        }
        .dark-mode #page-loader {
            background: #1e293b; /* Tailwind's gray-900 */
        }
        .loader-spinner {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        /* Hide carousel until images are loaded */
        .carousel-hidden {
            opacity: 0;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-gray-50 dark-mode" data-theme="light">
    <!-- Cookie Consent -->
    <div id="cookie-banner" class="fixed bottom-0 left-0 right-0 bg-seait-dark text-white p-4 z-50">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between">
            <div class="text-sm mb-4 md:mb-0">
                <p>This website uses cookies to enhance your experience.</p>
                <p class="text-xs mt-1">See our <a href="privacy.php" class="text-seait-orange underline">Privacy Policy</a></p>
            </div>
            <div class="flex space-x-2">
                <button onclick="acceptCookies()" class="bg-seait-orange text-white px-4 py-2 rounded text-sm">Accept</button>
                <button onclick="declineCookies()" class="bg-gray-600 text-white px-4 py-2 rounded text-sm">Decline</button>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section with Carousel -->
    <section id="home" class="relative bg-gradient-to-r from-seait-orange to-orange-600 text-white min-h-screen flex items-center">
        <div class="relative overflow-hidden w-full">
            <!-- Carousel Container -->
            <div id="carousel" class="relative h-screen min-h-[400px] sm:min-h-[500px] md:min-h-[600px] lg:min-h-[700px] xl:min-h-[800px] carousel-hidden">
                <?php
                // Reset the carousel result pointer to fetch all slides again
                mysqli_data_seek($carousel_result, 0);
                $slide_count = 0;
                while($slide = mysqli_fetch_assoc($carousel_result)):
                    $slide_count++;
                ?>
                <div class="carousel-slide absolute inset-0 transition-all duration-1000 ease-in-out <?php echo $slide_count === 1 ? 'opacity-100 scale-100 pointer-events-auto' : 'opacity-0 scale-105 pointer-events-none'; ?>" data-slide="<?php echo $slide_count; ?>">
                    <!-- Background Image with Overlay -->
                    <div class="absolute inset-0 bg-black bg-opacity-50"></div>
                    <div class="absolute inset-0 bg-cover bg-center bg-no-repeat" style="background-image: url('<?php echo htmlspecialchars($slide['image_url']); ?>'); filter: blur(1px);"></div>

                    <!-- Content Container -->
                    <div class="relative z-10 flex h-full">
                        <!-- Mobile: Center content, Desktop/Tablet: Lower left with reduced top margin -->
                        <div class="w-full flex items-center justify-center md:items-end md:justify-start md:pb-20 md:pt-8 md:pl-8 lg:pb-24 lg:pt-12 lg:pl-12">
                            <div class="max-w-7xl mx-auto md:mx-0 px-4 text-center md:text-left">
                                <div class="max-w-4xl md:max-w-2xl lg:max-w-3xl rounded-xl shadow-lg p-6 md:p-8">
                                    <!-- Title -->
                                    <h1 class="carousel-title text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold mb-2 md:mb-3 px-2 md:px-0 <?php echo $slide_count === 1 ? 'animate-fade-in' : 'opacity-0 transform translate-y-8'; ?>">
                                        <?php echo nl2br(htmlspecialchars($slide['title'])); ?>
                                    </h1>

                                    <!-- Subtitle -->
                                    <?php if (!empty($slide['subtitle'])): ?>
                                    <p class="carousel-subtitle text-2xl sm:text-3xl md:text-4xl mb-3 md:mb-4 px-2 md:px-0 <?php echo $slide_count === 1 ? 'animate-fade-in-delay' : 'opacity-0 transform translate-y-8'; ?>">
                                        <?php echo nl2br(htmlspecialchars($slide['subtitle'])); ?>
                                    </p>
                                    <?php endif; ?>

                                    <!-- Description -->
                                    <?php if (!empty($slide['description'])): ?>
                                    <p class="carousel-description text-lg sm:text-xl md:text-2xl mb-4 md:mb-6 max-w-3xl md:max-w-none mx-auto md:mx-0 px-2 md:px-0 <?php echo $slide_count === 1 ? 'animate-fade-in-delay-2' : 'opacity-0 transform translate-y-8'; ?>">
                                        <?php echo nl2br(htmlspecialchars($slide['description'])); ?>
                                    </p>
                                    <?php endif; ?>

                                    <!-- Button -->
                                    <?php if (!empty($slide['button_text']) && !empty($slide['button_link'])): ?>
                                    <div class="carousel-button <?php echo $slide_count === 1 ? 'animate-fade-in-delay-3' : 'opacity-0 transform translate-y-8'; ?>">
                                        <a href="<?php echo htmlspecialchars($slide['button_link']); ?>" class="bg-white text-seait-orange px-8 md:px-10 py-4 md:py-5 rounded-lg font-semibold text-lg md:text-2xl hover:bg-gray-100 transition-all duration-300 inline-block shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                                            <?php echo nl2br(htmlspecialchars($slide['button_text'])); ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>

                <?php if ($slide_count > 1): ?>
                <!-- Carousel Navigation Dots -->
                <div class="absolute top-6 md:top-8 lg:top-12 left-1/2 transform -translate-x-1/2 z-30">
                    <div class="flex space-x-3 md:space-x-4">
                        <?php for ($i = 1; $i <= $slide_count; $i++): ?>
                        <button class="carousel-dot w-4 h-4 md:w-5 md:h-5 rounded-full bg-white bg-opacity-90 hover:bg-opacity-100 transition-all duration-300 shadow-lg <?php echo $i === 1 ? 'bg-opacity-100 scale-110' : 'bg-opacity-60 hover:bg-opacity-80'; ?>" data-slide="<?php echo $i; ?>" title="Go to slide <?php echo $i; ?>"></button>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Previous/Next Navigation Buttons -->
                <button class="carousel-nav absolute left-4 md:left-6 lg:left-8 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-30 hover:bg-opacity-50 text-white p-3 md:p-4 rounded-full transition-all duration-300 z-30 hover:scale-110 hover:shadow-lg" onclick="previousSlide()" title="Previous slide">
                    <i class="fas fa-chevron-left text-lg md:text-xl"></i>
                </button>
                <button class="carousel-nav absolute right-4 md:right-6 lg:right-8 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-30 hover:bg-opacity-50 text-white p-3 md:p-4 rounded-full transition-all duration-300 z-30 hover:scale-110 hover:shadow-lg" onclick="nextSlide()" title="Next slide">
                    <i class="fas fa-chevron-right text-lg md:text-xl"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="min-h-screen bg-white flex items-center pt-8">
        <div class="max-w-7xl mx-auto px-4 py-8 md:py-16 w-full">
            <div class="text-center mb-8 md:mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-seait-dark mb-4">About SEAIT</h2>
                <p class="text-lg md:text-xl text-gray-600 max-w-3xl mx-auto px-4">Committed to academic excellence and innovation in technology education</p>
            </div>

            <!-- History Section -->
            <div class="mb-16">
                <div class="text-center mb-12">
                    <h3 class="text-2xl md:text-3xl font-bold text-seait-dark mb-4">Our History</h3>
                    <p class="text-gray-600 max-w-4xl mx-auto">A journey of excellence and innovation in education</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-stretch history-grid">
                    <!-- Timeline Events - Scrollable -->
                    <div class="bg-white rounded-lg shadow-sm p-6 timeline-scroll timeline-section">
                        <h4 class="text-xl font-semibold text-seait-dark mb-4">Timeline of Events</h4>
                        <div class="max-h-96 overflow-y-auto pr-2 space-y-4 flex-1">
                            <?php
                            // Fetch active timeline events - sorted by year descending (latest to oldest)
                            $timeline_query = "SELECT * FROM timeline_events WHERE is_active = 1 ORDER BY year DESC, sort_order ASC";
                            $timeline_result = mysqli_query($conn, $timeline_query);

                            while($event = mysqli_fetch_assoc($timeline_result)):
                            ?>
                            <div class="flex items-start space-x-4 border-l-4 border-seait-orange pl-4">
                                <div class="flex-shrink-0 w-12 h-12 bg-seait-orange rounded-full flex items-center justify-center text-white font-bold text-sm"><?php echo $event['year']; ?></div>
                                <div class="flex-1">
                                    <h5 class="text-lg font-semibold text-seait-dark mb-2"><?php echo htmlspecialchars($event['title']); ?></h5>
                                    <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($event['description']); ?></p>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Mission & Vision -->
                    <div class="bg-gray-100 p-8 rounded-lg mission-vision-section">
                        <?php
                        // Fetch mission and vision
                        $mission_vision_query = "SELECT * FROM mission_vision WHERE is_active = 1 ORDER BY type ASC";
                        $mission_vision_result = mysqli_query($conn, $mission_vision_query);

                        while($item = mysqli_fetch_assoc($mission_vision_result)):
                        ?>
                        <div class="mb-6">
                            <h4 class="text-xl font-semibold text-seait-dark mb-4"><?php echo htmlspecialchars($item['title']); ?></h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($item['content']); ?></p>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Core Values Section -->
            <div class="mb-16">
                <div class="text-center mb-12">
                    <h3 class="text-2xl md:text-3xl font-bold text-seait-dark mb-4">Our Core Values</h3>
                    <p class="text-lg md:text-xl text-gray-600 max-w-3xl mx-auto px-4">The foundation of our commitment to excellence</p>
                </div>

                <?php
                // Fetch all active core values into an array
                $core_values_query = "SELECT * FROM core_values WHERE is_active = 1 ORDER BY sort_order ASC, created_at ASC";
                $core_values_result = mysqli_query($conn, $core_values_query);
                $core_values = [];
                while($core_value = mysqli_fetch_assoc($core_values_result)) {
                    $core_values[] = $core_value;
                }

                $total_core_values = count($core_values);
                $first_row_limit = 3; // Max cards for the first row on large screens

                // Render the first row of core values (up to 3 cards)
                if ($total_core_values > 0) {
                    echo '<div class="flex justify-center mb-6">';
                    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8 max-w-6xl">';
                    for ($i = 0; $i < min($total_core_values, $first_row_limit); $i++) {
                        $core_value = $core_values[$i];
                        ?>
                        <div class="text-center p-6 bg-seait-light rounded-lg hover:shadow-lg transition duration-300 w-full max-w-sm">
                            <div class="w-16 h-16 bg-seait-orange rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="<?php echo htmlspecialchars($core_value['icon']); ?> text-white text-2xl"></i>
                            </div>
                            <h3 class="text-lg md:text-xl font-semibold mb-3 text-seait-dark"><?php echo htmlspecialchars($core_value['title']); ?></h3>
                            <p class="text-gray-600 text-sm md:text-base"><?php echo htmlspecialchars($core_value['description']); ?></p>
                        </div>
                        <?php
                    }
                    echo '</div>';
                    echo '</div>';
                }

                // Render the remaining core values in a centered row
                if ($total_core_values > $first_row_limit) {
                    echo '<div class="flex justify-center">';
                    echo '<div class="flex gap-6 md:gap-8 justify-center">';
                    for ($i = $first_row_limit; $i < $total_core_values; $i++) {
                        $core_value = $core_values[$i];
                        ?>
                        <div class="text-center p-6 bg-seait-light rounded-lg hover:shadow-lg transition duration-300 w-full max-w-sm">
                            <div class="w-16 h-16 bg-seait-orange rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="<?php echo htmlspecialchars($core_value['icon']); ?> text-white text-2xl"></i>
                            </div>
                            <h3 class="text-lg md:text-xl font-semibold mb-3 text-seait-dark"><?php echo htmlspecialchars($core_value['title']); ?></h3>
                            <p class="text-gray-600 text-sm md:text-base"><?php echo htmlspecialchars($core_value['description']); ?></p>
                        </div>
                        <?php
                    }
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>

            <!-- Board of Directors -->
            <div>
                <div class="text-center mb-12">
                    <h3 class="text-2xl md:text-3xl font-bold text-seait-dark mb-4">Board of Directors</h3>
                    <p class="text-gray-600 max-w-4xl mx-auto">Meet the visionary leaders guiding SEAIT towards excellence</p>
                </div>

                <div class="flex flex-wrap justify-center gap-6 md:gap-8">
                    <?php
                    // Fetch active board directors
                    $directors_query = "SELECT * FROM board_directors WHERE is_active = 1 ORDER BY sort_order ASC, name ASC";
                    $directors_result = mysqli_query($conn, $directors_query);

                    while($director = mysqli_fetch_assoc($directors_result)):
                    ?>
                    <div class="bg-white border border-gray-200 rounded-lg p-6 text-center hover:shadow-lg transition w-full max-w-sm md:max-w-xs lg:max-w-sm">
                        <?php if (!empty($director['photo_url'])): ?>
                        <img src="<?php echo htmlspecialchars($director['photo_url'] ?? ''); ?>" alt="<?php echo htmlspecialchars($director['name'] ?? ''); ?> Photo" class="w-24 h-24 rounded-full mx-auto mb-4 object-cover">
                        <?php else: ?>
                        <div class="w-24 h-24 bg-seait-orange rounded-full mx-auto mb-4 flex items-center justify-center">
                            <i class="fas fa-user-tie text-white text-2xl"></i>
                        </div>
                        <?php endif; ?>
                        <h4 class="text-lg font-semibold text-seait-dark mb-1"><?php echo htmlspecialchars($director['name'] ?? ''); ?></h4>
                        <p class="text-seait-orange font-medium mb-2"><?php echo htmlspecialchars($director['position'] ?? ''); ?></p>
                        <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($director['bio'] ?? ''); ?></p>
                        <?php if (!empty($director['email']) || !empty($director['phone']) || !empty($director['linkedin_url'])): ?>
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="flex justify-center space-x-3">
                                <?php if (!empty($director['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($director['email'] ?? ''); ?>" class="text-gray-400 hover:text-seait-orange transition" title="Email">
                                    <i class="fas fa-envelope"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($director['phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($director['phone'] ?? ''); ?>" class="text-gray-400 hover:text-seait-orange transition" title="Phone">
                                    <i class="fas fa-phone"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($director['linkedin_url'])): ?>
                                <a href="<?php echo htmlspecialchars($director['linkedin_url'] ?? ''); ?>" target="_blank" class="text-gray-400 hover:text-seait-orange transition" title="LinkedIn">
                                    <i class="fab fa-linkedin"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Academics Section -->
    <section id="academics" class="min-h-screen bg-gray-50 flex items-center pt-8">
        <div class="max-w-7xl mx-auto px-4 py-8 md:py-16 w-full">
            <div class="text-center mb-8 md:mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-seait-dark mb-4">Academic Programs</h2>
                <p class="text-lg md:text-xl text-gray-600 px-4">Explore our comprehensive range of academic offerings</p>
            </div>

            <!-- Colleges Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8 mb-12">
                <?php
                $current_college_id = null;
                $colleges_data = [];

                // First, organize courses by college
                while($course = mysqli_fetch_assoc($courses_result)) {
                    $college_id = $course['college_id'];
                    if (!isset($colleges_data[$college_id])) {
                        $colleges_data[$college_id] = [
                            'name' => $course['college_name'],
                            'short_name' => $course['college_short_name'],
                            'color_theme' => $course['color_theme'],
                            'logo_url' => $course['logo_url'],
                            'courses' => []
                        ];
                    }
                    $colleges_data[$college_id]['courses'][] = $course;
                }

                // Display each college with its courses
                foreach($colleges_data as $college_id => $college_data):
                ?>
                <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 border border-gray-100 relative">
                    <!-- Logo positioned at top center with 50% overlap -->
                    <div class="absolute -top-8 left-1/2 transform -translate-x-1/2">
                        <?php if (!empty($college_data['logo_url'])): ?>
                        <div class="w-20 h-20 rounded-full overflow-hidden shadow-xl border-4 border-white">
                            <img src="<?php echo htmlspecialchars($college_data['logo_url']); ?>" alt="<?php echo htmlspecialchars($college_data['name']); ?> Logo" class="w-full h-full object-cover">
                        </div>
                        <?php else: ?>
                        <div class="w-20 h-20 rounded-full flex items-center justify-center shadow-xl border-4 border-white"
                             style="background: linear-gradient(135deg, <?php echo htmlspecialchars($college_data['color_theme']); ?>, <?php echo htmlspecialchars($college_data['color_theme']); ?>dd);">
                            <span class="text-white font-bold text-2xl"><?php echo htmlspecialchars(substr($college_data['short_name'], 0, 2)); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-12">
                        <div class="mb-6">
                            <h3 class="text-lg md:text-xl font-bold text-seait-dark mb-1"><?php echo htmlspecialchars($college_data['name']); ?></h3>
                            <p class="text-sm text-gray-500 font-medium"><?php echo count($college_data['courses']); ?> Programs</p>
                        </div>

                        <div class="space-y-3">
                            <?php foreach($college_data['courses'] as $course): ?>
                            <div class="group">
                                <a href="course-detail.php?id=<?php echo $course['id']; ?>" class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition-all duration-200 hover:shadow-sm">
                                    <div class="w-2 h-2 bg-gradient-to-r from-seait-orange to-orange-400 rounded-full mr-3 flex-shrink-0 shadow-sm"></div>
                                    <span class="text-gray-700 group-hover:text-seait-orange font-medium transition-colors duration-200 flex-1">
                                        <?php echo htmlspecialchars($course['name']); ?>
                                    </span>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                        <i class="fas fa-arrow-right text-seait-orange text-sm"></i>
                                    </div>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Admission Section -->
    <section id="admissions" class="min-h-screen bg-white dark:bg-gray-900 flex items-center pt-8">
        <div class="max-w-7xl mx-auto px-4 py-8 md:py-16 w-full">
            <div class="text-center mb-8 md:mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-seait-dark dark:text-white mb-4">Admission Process</h2>
                <p class="text-lg md:text-xl text-gray-600 dark:text-gray-300 px-4">Your journey to academic excellence starts here</p>
            </div>

            <!-- Admission Tabs -->
            <div class="mb-8">
                <div class="flex flex-wrap justify-center gap-2 md:gap-4 mb-8">
                    <?php
                    $first_level = true;
                    while($level = mysqli_fetch_assoc($admission_levels_result)):
                    ?>
                    <button onclick="showAdmissionTab('<?php echo $level['id']; ?>')"
                            id="<?php echo $level['id']; ?>-tab"
                            class="admission-tab px-6 py-3 rounded-lg font-semibold transition <?php echo $first_level ? 'bg-seait-orange text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                        <i class="<?php echo htmlspecialchars($level['icon']); ?> mr-2"></i><?php echo htmlspecialchars($level['name']); ?>
                    </button>
                    <?php
                    $first_level = false;
                    endwhile;
                    ?>
                </div>
            </div>

            <?php
            // Reset the levels result for content display
            mysqli_data_seek($admission_levels_result, 0);
            $first_content = true;
            while($level = mysqli_fetch_assoc($admission_levels_result)):
            ?>
            <!-- Admission Content for <?php echo htmlspecialchars($level['name']); ?> -->
            <div id="<?php echo $level['id']; ?>-admission" class="admission-content <?php echo $first_content ? '' : 'hidden'; ?>">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center mb-8">
                    <div>
                        <h3 class="text-2xl md:text-3xl font-bold text-seait-dark dark:text-white mb-4"><?php echo htmlspecialchars($level['name']); ?></h3>
                        <p class="text-gray-600 dark:text-gray-300 mb-6"><?php echo htmlspecialchars($level['description']); ?></p>

                        <?php
                        // Fetch programs for this level
                        $programs_query = "SELECT * FROM admission_programs WHERE level_id = ? AND is_active = 1 ORDER BY name ASC";
                        $stmt = mysqli_prepare($conn, $programs_query);
                        mysqli_stmt_bind_param($stmt, "i", $level['id']);
                        mysqli_stmt_execute($stmt);
                        $programs_result = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($programs_result) > 0):
                        ?>
                        <div class="bg-seait-light dark:bg-gray-800 p-4 rounded-lg">
                            <h4 class="font-semibold text-seait-dark dark:text-white mb-2">Programs Offered:</h4>
                            <ul class="text-gray-700 dark:text-gray-300 space-y-1">
                                <?php while($program = mysqli_fetch_assoc($programs_result)): ?>
                                <li class="text-gray-700 dark:text-gray-300">• <?php echo htmlspecialchars($program['name']); ?></li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="bg-gray-50 dark:bg-white p-6 rounded-lg">
                        <h4 class="text-lg font-semibold text-seait-dark dark:text-black mb-4">Admission Requirements</h4>
                        <div class="space-y-3">
                            <?php
                            // Fetch requirements for this level
                            $requirements_query = "SELECT * FROM admission_requirements WHERE level_id = ? AND is_active = 1 ORDER BY step_number ASC";
                            $stmt = mysqli_prepare($conn, $requirements_query);
                            mysqli_stmt_bind_param($stmt, "i", $level['id']);
                            mysqli_stmt_execute($stmt);
                            $requirements_result = mysqli_stmt_get_result($stmt);

                            while($requirement = mysqli_fetch_assoc($requirements_result)):
                            ?>
                            <div class="flex items-start">
                                <div class="w-6 h-6 bg-seait-orange rounded-full flex items-center justify-center text-white text-sm font-bold mr-3 mt-0.5"><?php echo $requirement['step_number']; ?></div>
                                <div>
                                    <h5 class="font-medium text-seait-dark dark:text-black"><?php echo htmlspecialchars($requirement['title']); ?></h5>
                                    <p class="text-sm text-gray-600 dark:text-gray-700"><?php echo htmlspecialchars($requirement['description']); ?></p>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            $first_content = false;
            endwhile;
            ?>

            <!-- Contact Information -->
            <div class="bg-seait-light dark:bg-gray-800 rounded-lg p-6 md:p-8">
                <div class="text-center mb-6">
                    <h3 class="text-2xl font-bold text-seait-dark dark:text-white mb-2">Need Help with Admission?</h3>
                    <p class="text-gray-600 dark:text-gray-300">Our admission team is here to guide you through the process</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php while($contact = mysqli_fetch_assoc($admission_contacts_result)): ?>
                    <div class="text-center">
                        <div class="w-16 h-16 bg-seait-orange rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="<?php echo htmlspecialchars($contact['icon']); ?> text-white text-xl"></i>
                        </div>
                        <h4 class="font-semibold text-seait-dark dark:text-white mb-2"><?php echo htmlspecialchars($contact['title']); ?></h4>
                        <p class="text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($contact['contact_info']); ?></p>
                        <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($contact['additional_info']); ?></p>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Pre-registration Section -->
            <div class="bg-gradient-to-r from-seait-orange to-orange-600 rounded-lg p-8 md:p-12 mt-8">
                <div class="text-center text-white">
                    <h3 class="text-2xl md:text-3xl font-bold mb-4">Ready to Start Your Journey?</h3>
                    <p class="text-lg md:text-xl mb-6 opacity-90">Begin your application process with our pre-registration form</p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                        <a href="pre-registration.php" class="bg-white text-seait-orange px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-100 transition duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                            <i class="fas fa-user-plus mr-2"></i>Start Pre-registration
                        </a>
                        <a href="admission-guide.pdf" target="_blank" class="bg-transparent border-2 border-white text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-white hover:text-seait-orange transition duration-300">
                            <i class="fas fa-download mr-2"></i>Download Guide
                        </a>
                    </div>
                    <p class="text-sm opacity-75 mt-4">Complete the form to receive personalized guidance for your admission process</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Research & Publication Section -->
    <section id="research" class="min-h-screen bg-white dark:bg-gray-900 flex items-center pt-8">
        <div class="max-w-7xl mx-auto px-4 py-8 md:py-16 w-full">
            <div class="text-center mb-8 md:mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-seait-dark dark:text-white mb-4">Research & Publications</h2>
                <p class="text-lg md:text-xl text-gray-600 dark:text-gray-300 px-4">Exploring innovative research and scholarly contributions</p>
            </div>

            <!-- Research Categories -->
            <div class="mb-12">
                <h3 class="text-2xl font-bold text-seait-dark dark:text-white mb-6 text-center">Research Categories</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php
                    // Fetch active research categories
                    $categories_query = "SELECT * FROM research_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC";
                    $categories_result = mysqli_query($conn, $categories_query);

                    while($category = mysqli_fetch_assoc($categories_result)):
                    ?>
                    <div class="bg-gray-50 dark:bg-white rounded-lg p-6 hover:shadow-lg transition duration-300">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: <?php echo $category['color_theme']; ?>20; color: <?php echo $category['color_theme']; ?>">
                                <i class="fas fa-microscope text-xl" style="color: <?php echo $category['color_theme']; ?>"></i>
                            </div>
                            <h4 class="text-lg font-semibold text-seait-dark dark:text-black"><?php echo htmlspecialchars($category['name']); ?></h4>
                        </div>
                        <p class="text-gray-600 dark:text-gray-700 text-sm"><?php echo htmlspecialchars($category['description']); ?></p>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Featured Publications -->
            <div class="mb-12">
                <h3 class="text-2xl font-bold text-seait-dark dark:text-white mb-6 text-center">Featured Publications</h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <?php
                    // Fetch featured publications with their authors
                    $featured_query = "SELECT p.*, rc.name as category_name, rc.color_theme
                                     FROM publications p
                                     LEFT JOIN research_categories rc ON p.research_category_id = rc.id
                                     WHERE p.is_active = 1 AND p.featured = 1
                                     ORDER BY p.sort_order ASC, p.publication_date DESC
                                     LIMIT 4";
                    $featured_result = mysqli_query($conn, $featured_query);

                    while($publication = mysqli_fetch_assoc($featured_result)):
                    ?>
                    <div class="bg-white dark:bg-white border border-gray-200 dark:border-gray-300 rounded-lg p-6 hover:shadow-xl transition duration-300">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <h4 class="text-lg font-semibold text-seait-dark dark:text-black mb-2"><?php echo htmlspecialchars($publication['title']); ?></h4>
                                <div class="flex items-center space-x-4 text-sm text-gray-600 dark:text-gray-700 mb-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium" style="background-color: <?php echo $publication['color_theme']; ?>20; color: <?php echo $publication['color_theme']; ?>">
                                        <?php echo htmlspecialchars($publication['category_name']); ?>
                                    </span>
                                    <span class="px-2 py-1 bg-blue-100 dark:bg-blue-100 text-blue-800 dark:text-blue-800 rounded-full text-xs font-medium">
                                        <?php echo ucfirst(str_replace('_', ' ', $publication['status'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p class="text-gray-600 dark:text-gray-700 text-sm mb-4"><?php echo htmlspecialchars(substr($publication['abstract'], 0, 150)); ?>...</p>

                        <?php
                        // Fetch authors for this publication
                        $authors_query = "SELECT * FROM publication_authors WHERE publication_id = ? ORDER BY is_primary_author DESC, sort_order ASC LIMIT 5";
                        $stmt = mysqli_prepare($conn, $authors_query);
                        mysqli_stmt_bind_param($stmt, "i", $publication['id']);
                        mysqli_stmt_execute($stmt);
                        $authors_result = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($authors_result) > 0):
                        ?>
                        <div class="flex items-center space-x-2 mb-4">
                            <span class="text-sm text-gray-500 dark:text-gray-600">Authors:</span>
                            <div class="flex items-center space-x-2">
                                <?php while($author = mysqli_fetch_assoc($authors_result)): ?>
                                <div class="relative group">
                                    <?php if (!empty($author['author_photo_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($author['author_photo_url']); ?>"
                                         alt="<?php echo htmlspecialchars($author['author_name']); ?>"
                                         class="w-8 h-8 rounded-full object-cover border-2 <?php echo $author['is_primary_author'] ? 'border-seait-orange' : 'border-gray-300'; ?> cursor-help">
                                    <?php else: ?>
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium cursor-help border-2 <?php echo $author['is_primary_author'] ? 'border-seait-orange bg-seait-orange text-white' : 'border-gray-300 bg-gray-100 text-gray-700'; ?>">
                                        <?php
                                        $name_parts = explode(' ', $author['author_name']);
                                        $initials = '';
                                        foreach ($name_parts as $part) {
                                            if (!empty($part)) {
                                                $initials .= strtoupper(substr($part, 0, 1));
                                            }
                                        }
                                        echo substr($initials, 0, 2);
                                        ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Tooltip -->
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap z-10">
                                        <div class="font-semibold"><?php echo htmlspecialchars($author['author_name']); ?></div>
                                        <?php if ($author['is_primary_author']): ?>
                                        <div class="text-seait-orange text-xs">Main Author</div>
                                        <?php endif; ?>
                                        <?php if (!empty($author['author_title'])): ?>
                                        <div class="text-gray-300 text-xs"><?php echo htmlspecialchars($author['author_title']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($author['author_department'])): ?>
                                        <div class="text-gray-300 text-xs"><?php echo htmlspecialchars($author['author_department']); ?></div>
                                        <?php endif; ?>
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-500 dark:text-gray-600">
                                Published: <?php echo date('M Y', strtotime($publication['publication_date'])); ?>
                            </div>
                            <div class="flex space-x-2">
                                <?php if (!empty($publication['doi_link'])): ?>
                                <a href="<?php echo htmlspecialchars($publication['doi_link']); ?>" target="_blank" class="text-seait-orange hover:text-orange-600 text-sm">
                                    <i class="fas fa-external-link-alt mr-1"></i>DOI
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($publication['research_link'])): ?>
                                <a href="<?php echo htmlspecialchars($publication['research_link']); ?>" target="_blank" class="text-seait-orange hover:text-orange-600 text-sm">
                                    <i class="fas fa-file-alt mr-1"></i>View Research
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- All Publications -->
            <div>
                <h3 class="text-2xl font-bold text-seait-dark mb-6 text-center">All Publications</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php
                    // Fetch all active publications
                    $publications_query = "SELECT p.*, rc.name as category_name, rc.color_theme
                                         FROM publications p
                                         LEFT JOIN research_categories rc ON p.research_category_id = rc.id
                                         WHERE p.is_active = 1
                                         ORDER BY p.sort_order ASC, p.publication_date DESC
                                         LIMIT 9";
                    $publications_result = mysqli_query($conn, $publications_query);

                    while($publication = mysqli_fetch_assoc($publications_result)):
                    ?>
                    <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-lg transition duration-300">
                        <div class="mb-3">
                            <h4 class="text-base font-semibold text-seait-dark mb-2 line-clamp-2"><?php echo htmlspecialchars($publication['title']); ?></h4>
                            <div class="flex items-center space-x-2 mb-2">
                                <span class="px-2 py-1 rounded-full text-xs font-medium" style="background-color: <?php echo $publication['color_theme']; ?>20; color: <?php echo $publication['color_theme']; ?>">
                                    <?php echo htmlspecialchars($publication['category_name']); ?>
                                </span>
                                <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-medium">
                                    <?php echo ucfirst(str_replace('_', ' ', $publication['status'])); ?>
                                </span>
                            </div>
                        </div>

                        <p class="text-gray-600 text-xs mb-3 line-clamp-3"><?php echo htmlspecialchars(substr($publication['abstract'], 0, 100)); ?>...</p>

                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span><?php echo date('M Y', strtotime($publication['publication_date'])); ?></span>
                            <div class="flex space-x-2">
                                <?php if (!empty($publication['doi_link'])): ?>
                                <a href="<?php echo htmlspecialchars($publication['doi_link']); ?>" target="_blank" class="text-seait-orange hover:text-orange-600">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($publication['research_link'])): ?>
                                <a href="<?php echo htmlspecialchars($publication['research_link']); ?>" target="_blank" class="text-seait-orange hover:text-orange-600">
                                    <i class="fas fa-file-alt"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <div class="text-center mt-8">
                    <a href="research.php" class="bg-seait-orange text-white px-6 md:px-8 py-3 rounded-lg font-semibold hover:bg-orange-600 transition text-sm md:text-base">View All Research</a>
                </div>
            </div>
        </div>
    </section>

    <!-- News Section -->
    <section id="news" class="min-h-screen bg-white flex items-center pt-8">
        <div class="max-w-7xl mx-auto px-4 py-8 md:py-16 w-full">
            <div class="text-center mb-8 md:mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-seait-dark mb-4">Latest News & Events</h2>
                <p class="text-lg md:text-xl text-gray-600 px-4">Stay updated with the latest happenings and upcoming events at SEAIT</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                <?php
                $news_count = 0;
                while($news = mysqli_fetch_assoc($news_result)):
                    $news_count++;
                ?>
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition">
                    <div class="p-4 md:p-6">
                        <!-- Type Badge -->
                        <div class="flex justify-between items-start mb-3">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full <?php
                                echo $news['type'] === 'news' ? 'bg-blue-100 text-blue-800' :
                                    ($news['type'] === 'event' ? 'bg-purple-100 text-purple-800' :
                                    'bg-gray-100 text-gray-800');
                            ?>">
                                <?php echo ucfirst($news['type']); ?>
                            </span>
                            <span class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($news['created_at'])); ?></span>
                        </div>

                        <h3 class="text-lg md:text-xl font-semibold mb-2 text-seait-dark">
                            <a href="news-detail.php?id=<?php echo $news['id']; ?>" class="hover:text-seait-orange transition">
                                <?php echo htmlspecialchars($news['title']); ?>
                            </a>
                        </h3>
                        <div class="text-gray-600 mb-4 prose prose-sm max-w-none text-sm md:text-base">
                            <?php
                            // Display HTML content safely, but limit length for preview
                            $content = strip_tags($news['content']);
                            echo htmlspecialchars(substr($content, 0, 150)) . '...';
                            ?>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs md:text-sm text-gray-500">
                                <?php if ($news['type'] === 'event'): ?>
                                    <i class="fas fa-calendar-alt mr-1"></i>Event
                                <?php else: ?>
                                    <i class="fas fa-newspaper mr-1"></i>News
                                <?php endif; ?>
                            </span>
                            <a href="news-detail.php?id=<?php echo $news['id']; ?>" class="text-seait-orange hover:underline text-xs md:text-sm">Read More</a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>

                <?php
                // If we have less than 6 news items, add placeholder cards to maintain the 2-row layout
                while($news_count < 6):
                    $news_count++;
                ?>
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-lg opacity-50">
                    <div class="p-4 md:p-6">
                        <div class="animate-pulse">
                            <div class="h-6 bg-gray-200 rounded mb-2"></div>
                            <div class="h-4 bg-gray-200 rounded mb-2"></div>
                            <div class="h-4 bg-gray-200 rounded mb-2"></div>
                            <div class="h-4 bg-gray-200 rounded mb-4"></div>
                            <div class="flex justify-between items-center">
                                <div class="h-4 bg-gray-200 rounded w-20"></div>
                                <div class="h-4 bg-gray-200 rounded w-16"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <div class="text-center mt-8 md:mt-12">
                <a href="news.php" class="bg-seait-orange text-white px-6 md:px-8 py-3 rounded-lg font-semibold hover:bg-orange-600 transition text-sm md:text-base">View All News</a>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="min-h-screen bg-gray-50 flex items-center pt-8">
        <div class="max-w-7xl mx-auto px-4 py-8 md:py-16 w-full">
            <div class="text-center mb-8 md:mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-seait-dark mb-4">Contact Us</h2>
                <p class="text-lg md:text-xl text-gray-600 px-4">Get in touch with our departments for assistance</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                <?php
                // Fetch active departments with their contacts
                $departments_query = "SELECT d.*,
                                           COUNT(dc.id) as contact_count
                                    FROM departments d
                                    LEFT JOIN department_contacts dc ON d.id = dc.department_id AND dc.is_active = 1
                                    WHERE d.is_active = 1
                                    GROUP BY d.id
                                    ORDER BY d.sort_order ASC, d.name ASC";
                $departments_result = mysqli_query($conn, $departments_query);

                while($department = mysqli_fetch_assoc($departments_result)):
                ?>
                <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition duration-300">
                    <div class="flex items-center mb-4">
                        <div class="contact-icon mr-4" style="background-color: <?php echo $department['color_theme']; ?>">
                            <i class="<?php echo $department['icon']; ?> text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-seait-dark"><?php echo htmlspecialchars($department['name']); ?></h3>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($department['description']); ?></p>
                        </div>
                    </div>

                    <?php
                    // Fetch contacts for this department
                    $contacts_query = "SELECT * FROM department_contacts WHERE department_id = ? AND is_active = 1 ORDER BY sort_order ASC";
                    $stmt = mysqli_prepare($conn, $contacts_query);
                    mysqli_stmt_bind_param($stmt, "i", $department['id']);
                    mysqli_stmt_execute($stmt);
                    $contacts_result = mysqli_stmt_get_result($stmt);

                    if (mysqli_num_rows($contacts_result) > 0):
                    ?>
                    <div class="space-y-3">
                        <?php while($contact = mysqli_fetch_assoc($contacts_result)): ?>
                        <div class="flex items-start space-x-3">
                            <i class="<?php echo $contact['icon']; ?> text-seait-orange mt-1"></i>
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-seait-dark"><?php echo htmlspecialchars($contact['title']); ?></h4>
                                <?php if ($contact['contact_type'] === 'phone'): ?>
                                    <a href="tel:<?php echo htmlspecialchars($contact['contact_value']); ?>" class="text-sm text-gray-600 hover:text-seait-orange transition">
                                        <?php echo htmlspecialchars($contact['contact_value']); ?>
                                    </a>
                                <?php elseif ($contact['contact_type'] === 'email'): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($contact['contact_value']); ?>" class="text-sm text-gray-600 hover:text-seait-orange transition">
                                        <?php echo htmlspecialchars($contact['contact_value']); ?>
                                    </a>
                                <?php elseif ($contact['contact_type'] === 'website'): ?>
                                    <a href="<?php echo htmlspecialchars($contact['contact_value']); ?>" target="_blank" class="text-sm text-gray-600 hover:text-seait-orange transition">
                                        <?php echo htmlspecialchars($contact['contact_value']); ?>
                                    </a>
                                <?php else: ?>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($contact['contact_value']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-sm text-gray-500 italic">No contact information available</p>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Include FAB Inquiry System -->
    <?php include 'includes/fab-inquiry.php'; ?>

    <!-- Page Loader Overlay -->
    <div id="page-loader" class="fixed inset-0 z-[9999] flex items-center justify-center bg-white dark:bg-gray-900 transition-opacity duration-500">
        <div class="loader-spinner">
            <svg class="animate-spin h-16 w-16 text-seait-orange" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
        </div>
    </div>

    <script>
        // Mobile menu functionality is handled in navbar.php
        // No duplicate JavaScript needed here

        function acceptCookies() {
            document.getElementById('cookie-banner').style.display = 'none';
            localStorage.setItem('cookiesAccepted', 'true');
        }

        function declineCookies() {
            document.getElementById('cookie-banner').style.display = 'none';
            localStorage.setItem('cookiesAccepted', 'false');
        }

        if (localStorage.getItem('cookiesAccepted')) {
            document.getElementById('cookie-banner').style.display = 'none';
        }

        // Carousel functionality with 5-second auto-advance
        let currentSlide = 1;
        let slideInterval;
        let slides = [];
        let dots = [];
        let totalSlides = 0;
        let autoAdvanceInterval = 10000; // 10 seconds

        function showSlide(slideNumber) {
            console.log('Showing slide:', slideNumber);

            // Hide all slides with scale effect and pointer-events
            slides.forEach((slide, idx) => {
                if (idx === slideNumber - 1) {
                    slide.classList.add('opacity-100', 'scale-100', 'pointer-events-auto');
                    slide.classList.remove('opacity-0', 'scale-105', 'pointer-events-none');
                } else {
                    slide.classList.remove('opacity-100', 'scale-100', 'pointer-events-auto');
                    slide.classList.add('opacity-0', 'scale-105', 'pointer-events-none');
                }
            });

            // Remove active class from all dots
            dots.forEach(dot => {
                dot.classList.remove('bg-opacity-100', 'scale-125');
                dot.classList.add('bg-opacity-50', 'scale-100');
            });

            // Show current slide
            if (slides[slideNumber - 1]) {
                const currentSlideElement = slides[slideNumber - 1];

                // Make the slide visible with smooth transition
                currentSlideElement.classList.remove('opacity-0', 'scale-105');
                currentSlideElement.classList.add('opacity-100', 'scale-100');

                // Get all content elements
                const title = currentSlideElement.querySelector('.carousel-title');
                const subtitle = currentSlideElement.querySelector('.carousel-subtitle');
                const description = currentSlideElement.querySelector('.carousel-description');
                const button = currentSlideElement.querySelector('.carousel-button');

                // Reset all content elements to initial state
                [title, subtitle, description, button].forEach(element => {
                    if (element) {
                        // Remove all animation classes
                        element.classList.remove('animate-fade-in', 'animate-fade-in-delay', 'animate-fade-in-delay-2', 'animate-fade-in-delay-3');
                        // Set initial state
                        element.style.opacity = '0';
                        element.style.transform = 'translateY(20px)';
                    }
                });

                // Force reflow to ensure reset takes effect
                void currentSlideElement.offsetHeight;

                // Apply staggered animations with shorter, more consistent delays
                if (title) {
                    requestAnimationFrame(() => {
                        setTimeout(() => {
                            title.style.opacity = '1';
                            title.style.transform = 'translateY(0)';
                            title.classList.add('animate-fade-in');
                        }, 100);
                    });
                }

                if (subtitle) {
                    requestAnimationFrame(() => {
                        setTimeout(() => {
                            subtitle.style.opacity = '1';
                            subtitle.style.transform = 'translateY(0)';
                            subtitle.classList.add('animate-fade-in-delay');
                        }, 250);
                    });
                }

                if (description) {
                    requestAnimationFrame(() => {
                        setTimeout(() => {
                            description.style.opacity = '1';
                            description.style.transform = 'translateY(0)';
                            description.classList.add('animate-fade-in-delay-2');
                        }, 400);
                    });
                }

                if (button) {
                    requestAnimationFrame(() => {
                        setTimeout(() => {
                            button.style.opacity = '1';
                            button.style.transform = 'translateY(0)';
                            button.classList.add('animate-fade-in-delay-3');
                        }, 550);
                    });
                }
            }

            // Activate current dot
            if (dots[slideNumber - 1]) {
                dots[slideNumber - 1].classList.remove('bg-opacity-50', 'scale-100');
                dots[slideNumber - 1].classList.add('bg-opacity-100', 'scale-125');
            }

            currentSlide = slideNumber;
        }

        function nextSlide() {
            const next = currentSlide === totalSlides ? 1 : currentSlide + 1;
            console.log('Next slide:', next);
            showSlide(next);
        }

        function previousSlide() {
            const prev = currentSlide === 1 ? totalSlides : currentSlide - 1;
            console.log('Previous slide:', prev);
            showSlide(prev);
        }

        function startCarousel() {
            if (totalSlides > 1) {
                console.log('Starting carousel with', totalSlides, 'slides - Auto-advance every', autoAdvanceInterval/1000, 'seconds');
                slideInterval = setInterval(nextSlide, autoAdvanceInterval);
            } else {
                console.log('No carousel started - only', totalSlides, 'slide(s)');
            }
        }

        function stopCarousel() {
            if (slideInterval) {
                console.log('Stopping carousel');
                clearInterval(slideInterval);
            }
        }

        function restartCarousel() {
            stopCarousel();
            startCarousel();
        }

        document.addEventListener('DOMContentLoaded', function() {
            slides = document.querySelectorAll('.carousel-slide');
            dots = document.querySelectorAll('.carousel-dot');
            totalSlides = slides.length;

            console.log('Carousel initialization - Total slides:', totalSlides);

            // Initialize carousel
            if (totalSlides > 0) {
                // Ensure first slide content animates properly on page load
                setTimeout(() => {
                    const firstSlide = slides[0];
                    if (firstSlide) {
                        const title = firstSlide.querySelector('.carousel-title');
                        const subtitle = firstSlide.querySelector('.carousel-subtitle');
                        const description = firstSlide.querySelector('.carousel-description');
                        const button = firstSlide.querySelector('.carousel-button');

                        // Ensure first slide content is properly animated
                        [title, subtitle, description, button].forEach(element => {
                            if (element) {
                                element.style.opacity = '1';
                                element.style.transform = 'translateY(0)';
                            }
                        });
                    }
                }, 100);

                // Add click event listeners to dots
                dots.forEach((dot, index) => {
                    dot.addEventListener('click', () => {
                        showSlide(index + 1);
                        restartCarousel(); // Restart the interval
                    });
                });

                // Add keyboard navigation
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowLeft') {
                        previousSlide();
                        restartCarousel();
                    } else if (e.key === 'ArrowRight') {
                        nextSlide();
                        restartCarousel();
                    }
                });

                // Pause carousel on hover
                const carousel = document.getElementById('carousel');
                if (carousel) {
                    carousel.addEventListener('mouseenter', () => {
                        stopCarousel();
                    });
                    carousel.addEventListener('mouseleave', () => {
                        startCarousel();
                    });
                }

                // Start the carousel
                startCarousel();
            }

            // Carousel image preloading for smooth initial transition
            const carousel = document.getElementById('carousel');
            if (carousel) {
                const images = carousel.querySelectorAll('img');
                let loadedCount = 0;
                if (images.length === 0) {
                    carousel.classList.remove('carousel-hidden');
                } else {
                    images.forEach(img => {
                        if (img.complete) {
                            loadedCount++;
                            if (loadedCount === images.length) {
                                carousel.classList.remove('carousel-hidden');
                            }
                        } else {
                            img.addEventListener('load', () => {
                                loadedCount++;
                                if (loadedCount === images.length) {
                                    carousel.classList.remove('carousel-hidden');
                                }
                            });
                            img.addEventListener('error', () => {
                                loadedCount++;
                                if (loadedCount === images.length) {
                                    carousel.classList.remove('carousel-hidden');
                                }
                            });
                        }
                    });
                }
            }
        });

        // Admission Tab functionality
        function showAdmissionTab(levelId) {
            // Hide all admission content
            document.querySelectorAll('.admission-content').forEach(content => {
                content.classList.add('hidden');
            });
            // Remove 'active' class from all tab buttons
            document.querySelectorAll('.admission-tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show the selected content and add 'active' class to its button
            document.getElementById(levelId + '-admission').classList.remove('hidden');
            document.getElementById(levelId + '-tab').classList.add('active');
        }

        // Show the first tab on page load
        document.addEventListener('DOMContentLoaded', () => {
            // Get the first admission tab and show it
            const firstTab = document.querySelector('.admission-tab');
            if (firstTab) {
                const levelId = firstTab.id.replace('-tab', '');
                showAdmissionTab(levelId);
            }
        });

        // Services Dropdown functionality is handled in navbar.php
        // No duplicate JavaScript needed here

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            .animate-fade-in {
                animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            }
            .animate-fade-in-delay {
                animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.15s forwards;
            }
            .animate-fade-in-delay-2 {
                animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.3s forwards;
            }
            .animate-fade-in-delay-3 {
                animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.45s forwards;
            }
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Enhanced carousel transitions */
            .carousel-slide {
                transition: opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1), transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            }

            /* Ensure carousel content is visible when slide is active */
            .carousel-slide.opacity-100 .carousel-title,
            .carousel-slide.opacity-100 .carousel-subtitle,
            .carousel-slide.opacity-100 .carousel-description,
            .carousel-slide.opacity-100 .carousel-button {
                transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            }

            /* Override any conflicting styles for carousel content */
            .carousel-title,
            .carousel-subtitle,
            .carousel-description,
            .carousel-button {
                transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
                will-change: opacity, transform;
            }

            /* Enhanced navigation styles */
            .carousel-dot {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .carousel-nav {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            /* Active navbar link styles */
            .navbar-link-active {
                color: #FF6B35 !important;
                font-weight: 600;
            }
            .navbar-link-active:hover {
                color: #FF6B35 !important;
            }
        `;
        document.head.appendChild(style);

        // Active navbar link functionality
        function updateActiveNavLink() {
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('a[href^="index.php#"]');

            // Remove active class from all links
            navLinks.forEach(link => {
                link.classList.remove('navbar-link-active');
            });

            // Check if we're on a course detail page
            const isCourseDetailPage = window.location.pathname.includes('course-detail.php');

            if (isCourseDetailPage) {
                // Highlight Academics link for course detail pages
                const academicsLink = document.querySelector('a[href="index.php#academics"]');
                if (academicsLink) {
                    academicsLink.classList.add('navbar-link-active');
                }
                return;
            }

            // Find which section is currently in view
            let currentSection = '';
            const scrollPosition = window.scrollY + 100; // Offset for navbar height

            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.offsetHeight;

                if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                    currentSection = section.getAttribute('id');
                }
            });

            // Add active class to corresponding nav link
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                const targetId = href.split('#')[1];

                if (targetId === currentSection) {
                    link.classList.add('navbar-link-active');
                }
            });
        }

        // Update active link on scroll
        window.addEventListener('scroll', updateActiveNavLink);

        // Update active link on page load
        document.addEventListener('DOMContentLoaded', updateActiveNavLink);

        // Floating Action Button Inquiry System
        document.addEventListener('DOMContentLoaded', function() {
            const fabButton = document.getElementById('fab-button');
            const inquiryModal = document.getElementById('inquiry-modal');
            const closeModal = document.getElementById('close-modal');
            const inquiryForm = document.getElementById('inquiry-form');
            const inquiryInput = document.getElementById('inquiry-input');
            const chatContainer = document.getElementById('chat-container');

            // Open modal
            fabButton.addEventListener('click', function() {
                inquiryModal.classList.remove('hidden');
                inquiryInput.focus();
            });

            // Close modal
            closeModal.addEventListener('click', function() {
                inquiryModal.classList.add('hidden');
            });

            // Close modal when clicking outside
            inquiryModal.addEventListener('click', function(e) {
                if (e.target === inquiryModal) {
                    inquiryModal.classList.add('hidden');
                }
            });

            // Handle form submission
            inquiryForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const question = inquiryInput.value.trim();
                if (!question) return;

                // Add user message
                addMessage(question, 'user');
                inquiryInput.value = '';

                // Show typing indicator
                showTypingIndicator();

                // Get automatic response
                getAutomaticResponse(question);
            });

            function addMessage(message, sender) {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'flex items-start space-x-3';

                if (sender === 'user') {
                    messageDiv.innerHTML = `
                        <div class="flex-1"></div>
                        <div class="bg-seait-orange text-white rounded-lg p-3 max-w-xs">
                            <p class="text-sm">${message}</p>
                        </div>
                        <div class="w-8 h-8 bg-gray-400 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user text-white text-sm"></i>
                        </div>
                    `;
                } else {
                    messageDiv.innerHTML = `
                        <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-robot text-white text-sm"></i>
                        </div>
                        <div class="bg-gray-100 rounded-lg p-3 max-w-xs">
                            <p class="text-sm text-gray-800">${message}</p>
                        </div>
                    `;
                }

                chatContainer.appendChild(messageDiv);
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }

            function showTypingIndicator() {
                const typingDiv = document.createElement('div');
                typingDiv.id = 'typing-indicator';
                typingDiv.className = 'flex items-start space-x-3';
                typingDiv.innerHTML = `
                    <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-robot text-white text-sm"></i>
                    </div>
                    <div class="bg-gray-100 rounded-lg p-3">
                        <div class="flex space-x-1">
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                        </div>
                    </div>
                `;
                chatContainer.appendChild(typingDiv);
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }

            function hideTypingIndicator() {
                const typingIndicator = document.getElementById('typing-indicator');
                if (typingIndicator) {
                    typingIndicator.remove();
                }
            }

            function getAutomaticResponse(question) {
                // Show typing indicator
                showTypingIndicator();

                // Call API endpoint
                fetch('api/inquiry-handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        question: question
                    })
                })
                .then(response => response.json())
                .then(data => {
                    hideTypingIndicator();
                    if (data.success) {
                        addMessage(data.response, 'bot');
                    } else {
                        addMessage("I'm sorry, I couldn't process your question right now. Please try again or contact us directly.", 'bot');
                    }
                })
                .catch(error => {
                    hideTypingIndicator();
                    console.error('Error:', error);
                    // Fallback to local keyword matching if API fails
                    const response = getResponseByKeywords(question);
                    addMessage(response, 'bot');
                });
            }

            function getResponseByKeywords(question) {
                const lowerQuestion = question.toLowerCase();

                // Admission related questions
                if (lowerQuestion.includes('admission') || lowerQuestion.includes('apply') || lowerQuestion.includes('enroll')) {
                    return "For admission inquiries, you can visit our Admission Process section on this page, or contact our admission office. We offer various programs including undergraduate and graduate degrees. You can also start your application through our pre-registration form.";
                }

                // Program related questions
                if (lowerQuestion.includes('program') || lowerQuestion.includes('course') || lowerQuestion.includes('degree')) {
                    return "SEAIT offers various academic programs across different colleges. You can explore our Academic Programs section to see all available courses. Each program has detailed information about curriculum, requirements, and career opportunities.";
                }

                // Contact related questions
                if (lowerQuestion.includes('contact') || lowerQuestion.includes('phone') || lowerQuestion.includes('email')) {
                    return "You can find our contact information in the Contact Us section. We have different departments with specific contact details. For general inquiries, you can reach us through the contact form or call our main office.";
                }

                // Location related questions
                if (lowerQuestion.includes('location') || lowerQuestion.includes('address') || lowerQuestion.includes('where')) {
                    return "SEAIT is located in [City, Province]. You can find our exact address and directions in the Contact Us section. We also have virtual tours available for prospective students.";
                }

                // Fee related questions
                if (lowerQuestion.includes('fee') || lowerQuestion.includes('tuition') || lowerQuestion.includes('cost') || lowerQuestion.includes('price')) {
                    return "Tuition fees vary by program and level. For detailed information about fees and payment options, please contact our finance office or check our admission guide. We also offer scholarships and financial aid programs.";
                }

                // Schedule related questions
                if (lowerQuestion.includes('schedule') || lowerQuestion.includes('time') || lowerQuestion.includes('when')) {
                    return "Class schedules vary by program and semester. You can check our academic calendar for important dates. For specific class schedules, please contact your department or check the student portal.";
                }

                // General questions
                if (lowerQuestion.includes('hello') || lowerQuestion.includes('hi') || lowerQuestion.includes('help')) {
                    return "Hello! I'm here to help you with any questions about SEAIT. You can ask me about our programs, admission process, contact information, or any other general inquiries.";
                }

                // Default response
                return "Thank you for your question! For specific inquiries, I recommend contacting our relevant department directly. You can find contact information in the Contact Us section, or visit our main office during business hours.";
            }
        });

        // Hide page loader when fully loaded
        window.addEventListener('load', function() {
            const loader = document.getElementById('page-loader');
            if (loader) {
                loader.style.opacity = '0';
                setTimeout(() => loader.style.display = 'none', 500);
            }
        });
    </script>
</body>
</html>