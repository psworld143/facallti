<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Get course ID from URL
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($course_id <= 0) {
    header('Location: index.php');
    exit();
}

// Fetch course details with college information
$course_query = "SELECT c.*, co.name as college_name, co.short_name as college_short_name, co.color_theme
                 FROM courses c
                 JOIN colleges co ON c.college_id = co.id
                 WHERE c.id = ? AND c.is_active = 1";
$stmt = mysqli_prepare($conn, $course_query);
mysqli_stmt_bind_param($stmt, "i", $course_id);
mysqli_stmt_execute($stmt);
$course_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($course_result) == 0) {
    header('Location: index.php');
    exit();
}

$course = mysqli_fetch_assoc($course_result);

// Fetch course requirements
$requirements_query = "SELECT * FROM course_requirements WHERE course_id = ? ORDER BY requirement_type, sort_order";
$stmt = mysqli_prepare($conn, $requirements_query);
mysqli_stmt_bind_param($stmt, "i", $course_id);
mysqli_stmt_execute($stmt);
$requirements_result = mysqli_stmt_get_result($stmt);

// Fetch course curriculum
$curriculum_query = "SELECT * FROM course_curriculum WHERE course_id = ? ORDER BY year_level, semester, sort_order";
$stmt = mysqli_prepare($conn, $curriculum_query);
mysqli_stmt_bind_param($stmt, "i", $course_id);
mysqli_stmt_execute($stmt);
$curriculum_result = mysqli_stmt_get_result($stmt);

// Organize curriculum by year level
$curriculum_by_year = [];
while ($subject = mysqli_fetch_assoc($curriculum_result)) {
    $year = $subject['year_level'];
    $semester = $subject['semester'];
    if (!isset($curriculum_by_year[$year])) {
        $curriculum_by_year[$year] = [];
    }
    if (!isset($curriculum_by_year[$year][$semester])) {
        $curriculum_by_year[$year][$semester] = [];
    }
    $curriculum_by_year[$year][$semester][] = $subject;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Details - SEAIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
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
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        /* Active navbar link styles */
        .navbar-link-active {
            color: #FF6B35 !important;
            font-weight: 600;
        }
        .navbar-link-active:hover {
            color: #FF6B35 !important;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Course Header -->
    <section class="bg-gradient-to-r from-seait-orange to-orange-600 text-white py-12 md:py-16">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center mb-4">
                <a href="index.php#academics" class="text-white hover:text-gray-200 transition mr-4">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Programs
                </a>
            </div>

            <div class="flex items-center mb-6">
                <div class="w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-2xl mr-6"
                     style="background-color: <?php echo htmlspecialchars($course['color_theme']); ?>">
                    <?php echo htmlspecialchars(substr($course['college_short_name'], 0, 2)); ?>
                </div>
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold mb-2"><?php echo htmlspecialchars($course['name']); ?></h1>
                    <p class="text-xl md:text-2xl opacity-90"><?php echo htmlspecialchars($course['college_name']); ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white bg-opacity-20 rounded-lg p-4">
                    <h3 class="font-semibold mb-2">Level</h3>
                    <p class="text-lg"><?php echo ucfirst(htmlspecialchars($course['level'])); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-lg p-4">
                    <h3 class="font-semibold mb-2">Duration</h3>
                    <p class="text-lg"><?php echo htmlspecialchars($course['duration']); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-lg p-4">
                    <h3 class="font-semibold mb-2">Credits</h3>
                    <p class="text-lg"><?php echo htmlspecialchars($course['credits']); ?> units</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Course Content -->
    <section class="py-12 md:py-16">
        <div class="max-w-7xl mx-auto px-4">
        <!-- Course Description -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-seait-dark mb-4">Course Description</h2>
            <p class="text-gray-700 text-lg leading-relaxed"><?php echo htmlspecialchars($course['description']); ?></p>
        </div>

        <!-- Requirements Section -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-seait-dark mb-6">Requirements</h2>

            <?php if (mysqli_num_rows($requirements_result) > 0): ?>
                <div class="space-y-6">
                    <?php
                    $current_type = '';
                    while($requirement = mysqli_fetch_assoc($requirements_result)):
                        if ($requirement['requirement_type'] !== $current_type):
                            if ($current_type !== '') echo '</div>';
                            $current_type = $requirement['requirement_type'];
                            $type_title = ucfirst(str_replace('_', ' ', $current_type));
                    ?>
                    <div>
                        <h3 class="text-xl font-semibold text-seait-dark mb-4"><?php echo $type_title; ?> Requirements</h3>
                        <div class="space-y-3">
                    <?php endif; ?>

                    <div class="flex items-start space-x-3 p-4 bg-gray-50 rounded-lg">
                        <div class="w-2 h-2 bg-seait-orange rounded-full mt-2 flex-shrink-0"></div>
                        <div>
                            <h4 class="font-semibold text-seait-dark"><?php echo htmlspecialchars($requirement['title']); ?></h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($requirement['description']); ?></p>
                        </div>
                    </div>

                    <?php endwhile; ?>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-gray-600">Requirements information will be available soon.</p>
            <?php endif; ?>
        </div>

        <!-- Curriculum Section -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold text-seait-dark mb-6">Curriculum</h2>

            <?php if (!empty($curriculum_by_year)): ?>
                <div class="space-y-8">
                    <?php
                    $year_titles = [
                        'first_year' => 'First Year',
                        'second_year' => 'Second Year',
                        'third_year' => 'Third Year',
                        'fourth_year' => 'Fourth Year'
                    ];
                    $semester_titles = [
                        'first_semester' => 'First Semester',
                        'second_semester' => 'Second Semester',
                        'summer' => 'Summer'
                    ];

                    foreach($curriculum_by_year as $year => $semesters):
                    ?>
                    <div>
                        <h3 class="text-xl font-semibold text-seait-dark mb-4"><?php echo $year_titles[$year]; ?></h3>

                        <?php foreach($semesters as $semester => $subjects): ?>
                        <div class="mb-6">
                            <h4 class="text-lg font-medium text-seait-dark mb-3"><?php echo $semester_titles[$semester]; ?></h4>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject Code</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject Title</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hours</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach($subjects as $subject): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-seait-dark">
                                                <?php echo htmlspecialchars($subject['subject_code']); ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                <?php echo htmlspecialchars($subject['subject_title']); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($subject['units']); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                <?php
                                                $hours = [];
                                                if ($subject['lecture_hours'] > 0) $hours[] = $subject['lecture_hours'] . 'L';
                                                if ($subject['laboratory_hours'] > 0) $hours[] = $subject['laboratory_hours'] . 'Lab';
                                                echo implode('/', $hours);
                                                ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600">
                                                <?php echo htmlspecialchars($subject['description']); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-600">Curriculum information will be available soon.</p>
            <?php endif; ?>
        </div>
    </div>
    </section>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Include FAB Inquiry System -->
    <?php include 'includes/fab-inquiry.php'; ?>

    <script>
        // Active navbar link functionality for course detail pages
        function updateActiveNavLink() {
            const navLinks = document.querySelectorAll('a[href^="index.php#"]');

            // Remove active class from all links
            navLinks.forEach(link => {
                link.classList.remove('navbar-link-active');
            });

            // Highlight Academics link for course detail pages
            const academicsLink = document.querySelector('a[href="index.php#academics"]');
            if (academicsLink) {
                academicsLink.classList.add('navbar-link-active');
            }
        }

        // Update active link on page load
        document.addEventListener('DOMContentLoaded', updateActiveNavLink);
    </script>
</body>
</html>