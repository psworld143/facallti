<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Get filter parameters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$year_filter = isset($_GET['year']) ? (int)$_GET['year'] : 0;

// Build the publications query with filters
$publications_query = "SELECT p.*, rc.name as category_name, rc.color_theme, rc.id as category_id
                      FROM publications p
                      LEFT JOIN research_categories rc ON p.research_category_id = rc.id
                      WHERE p.is_active = 1";

$query_params = [];
$query_types = "";

if ($category_filter > 0) {
    $publications_query .= " AND p.research_category_id = ?";
    $query_params[] = $category_filter;
    $query_types .= "i";
}

if (!empty($status_filter)) {
    $publications_query .= " AND p.status = ?";
    $query_params[] = $status_filter;
    $query_types .= "s";
}

if (!empty($search_query)) {
    $publications_query .= " AND (p.title LIKE ? OR p.abstract LIKE ? OR p.keywords LIKE ?)";
    $search_param = "%$search_query%";
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $query_types .= "sss";
}

if ($year_filter > 0) {
    $publications_query .= " AND YEAR(p.publication_date) = ?";
    $query_params[] = $year_filter;
    $query_types .= "i";
}

$publications_query .= " ORDER BY p.publication_date DESC, p.sort_order ASC";

// Execute the query with parameters
$stmt = mysqli_prepare($conn, $publications_query);
if (!empty($query_params)) {
    mysqli_stmt_bind_param($stmt, $query_types, ...$query_params);
}
mysqli_stmt_execute($stmt);
$publications_result = mysqli_stmt_get_result($stmt);

// Fetch research categories for filter
$categories_query = "SELECT * FROM research_categories WHERE is_active = 1 ORDER BY name ASC";
$categories_result = mysqli_query($conn, $categories_query);

// Fetch years for filter
$years_query = "SELECT DISTINCT YEAR(publication_date) as year FROM publications WHERE is_active = 1 ORDER BY year DESC";
$years_result = mysqli_query($conn, $years_query);

// Fetch featured publications
$featured_query = "SELECT p.*, rc.name as category_name, rc.color_theme
                   FROM publications p
                   LEFT JOIN research_categories rc ON p.research_category_id = rc.id
                   WHERE p.is_active = 1 AND p.featured = 1
                   ORDER BY p.publication_date DESC LIMIT 6";
$featured_result = mysqli_query($conn, $featured_query);

// Fetch research statistics
$stats_query = "SELECT
                    COUNT(*) as total_publications,
                    COUNT(DISTINCT research_category_id) as total_categories,
                    COUNT(CASE WHEN featured = 1 THEN 1 END) as featured_count,
                    COUNT(CASE WHEN status = 'published' THEN 1 END) as published_count,
                    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count
                FROM publications WHERE is_active = 1";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research & Publications - SEAIT</title>
    <!-- Favicon Configuration -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="icon" type="image/png" href="assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="shortcut icon" type="image/png" href="assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="assets/images/seait-logo.png">
    <link rel="apple-touch-icon-precomposed" type="image/png" href="assets/images/seait-logo.png">
    <meta name="msapplication-TileImage" content="assets/images/seait-logo.png">
    <meta name="msapplication-TileColor" content="#FF6B35">



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

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .filter-active {
            background-color: #FF6B35;
            color: white;
        }

        .publication-card {
            transition: all 0.3s ease;
        }

        .publication-card:hover {
            transform: translateY(-2px);
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
<body class="bg-gray-50 dark-mode" data-theme="light">
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-seait-orange to-orange-600 text-white py-16 md:py-24">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">Research & Publications</h1>
            <p class="text-xl md:text-2xl mb-8 max-w-4xl mx-auto">Exploring innovative research and scholarly contributions that advance knowledge and technology</p>

            <!-- Research Statistics -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8 mt-12">
                <div class="bg-white bg-opacity-20 rounded-lg p-4">
                    <div class="text-2xl md:text-3xl font-bold"><?php echo $stats['total_publications']; ?></div>
                    <div class="text-sm md:text-base opacity-90">Publications</div>
                </div>
                <div class="bg-white bg-opacity-20 rounded-lg p-4">
                    <div class="text-2xl md:text-3xl font-bold"><?php echo $stats['total_categories']; ?></div>
                    <div class="text-sm md:text-base opacity-90">Categories</div>
                </div>
                <div class="bg-white bg-opacity-20 rounded-lg p-4">
                    <div class="text-2xl md:text-3xl font-bold"><?php echo $stats['featured_count']; ?></div>
                    <div class="text-sm md:text-base opacity-90">Featured</div>
                </div>
                <div class="bg-white bg-opacity-20 rounded-lg p-4">
                    <div class="text-2xl md:text-3xl font-bold"><?php echo $stats['published_count']; ?></div>
                    <div class="text-sm md:text-base opacity-90">Published</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Filters Section -->
    <section class="bg-white py-8 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                               placeholder="Search publications..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                    </div>

                    <!-- Category Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                            <option value="0">All Categories</option>
                            <?php while($category = mysqli_fetch_assoc($categories_result)): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                            <option value="">All Status</option>
                            <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="submitted" <?php echo $status_filter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                            <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                        </select>
                    </div>

                    <!-- Year Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                        <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                            <option value="0">All Years</option>
                            <?php while($year = mysqli_fetch_assoc($years_result)): ?>
                            <option value="<?php echo $year['year']; ?>" <?php echo $year_filter == $year['year'] ? 'selected' : ''; ?>>
                                <?php echo $year['year']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <button type="submit" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition">
                        <i class="fas fa-search mr-2"></i>Apply Filters
                    </button>

                    <?php if ($category_filter > 0 || !empty($status_filter) || !empty($search_query) || $year_filter > 0): ?>
                    <a href="research.php" class="text-gray-600 hover:text-seait-orange transition">
                        <i class="fas fa-times mr-2"></i>Clear Filters
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>

    <!-- Featured Publications -->
    <?php if (mysqli_num_rows($featured_result) > 0): ?>
    <section class="py-12 bg-seait-light">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-seait-dark mb-4">Featured Publications</h2>
                <p class="text-lg text-gray-600">Highlighted research that showcases our commitment to academic excellence</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                <?php while($publication = mysqli_fetch_assoc($featured_result)): ?>
                <div class="bg-white rounded-lg shadow-lg p-6 publication-card">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-seait-dark mb-2 line-clamp-2">
                                <?php echo htmlspecialchars($publication['title']); ?>
                            </h3>
                            <div class="flex items-center space-x-2 mb-3">
                                <span class="px-2 py-1 rounded-full text-xs font-medium"
                                      style="background-color: <?php echo $publication['color_theme']; ?>20; color: <?php echo $publication['color_theme']; ?>">
                                    <?php echo htmlspecialchars($publication['category_name']); ?>
                                </span>
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                                    <?php echo ucfirst(str_replace('_', ' ', $publication['status'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <p class="text-gray-600 text-sm mb-4 line-clamp-3">
                        <?php echo htmlspecialchars(substr($publication['abstract'], 0, 150)); ?>...
                    </p>

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
                        <span class="text-sm text-gray-500">Authors:</span>
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
                        <div class="text-sm text-gray-500">
                            <?php echo date('M Y', strtotime($publication['publication_date'])); ?>
                        </div>
                        <div class="flex space-x-2">
                            <?php if (!empty($publication['doi_link'])): ?>
                            <a href="<?php echo htmlspecialchars($publication['doi_link']); ?>" target="_blank"
                               class="text-seait-orange hover:text-orange-600 text-sm">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($publication['research_link'])): ?>
                            <a href="<?php echo htmlspecialchars($publication['research_link']); ?>" target="_blank"
                               class="text-seait-orange hover:text-orange-600 text-sm">
                                <i class="fas fa-file-alt"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- All Publications -->
    <section class="py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h2 class="text-3xl md:text-4xl font-bold text-seait-dark mb-2">All Publications</h2>
                    <p class="text-gray-600">
                        <?php echo mysqli_num_rows($publications_result); ?> publication<?php echo mysqli_num_rows($publications_result) != 1 ? 's' : ''; ?> found
                    </p>
                </div>
            </div>

            <?php if (mysqli_num_rows($publications_result) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                <?php while($publication = mysqli_fetch_assoc($publications_result)): ?>
                <div class="bg-white border border-gray-200 rounded-lg p-6 publication-card">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-seait-dark mb-2 line-clamp-2">
                            <?php echo htmlspecialchars($publication['title']); ?>
                        </h3>
                        <div class="flex items-center space-x-2 mb-3">
                            <span class="px-2 py-1 rounded-full text-xs font-medium"
                                  style="background-color: <?php echo $publication['color_theme']; ?>20; color: <?php echo $publication['color_theme']; ?>">
                                <?php echo htmlspecialchars($publication['category_name']); ?>
                            </span>
                            <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-medium">
                                <?php echo ucfirst(str_replace('_', ' ', $publication['status'])); ?>
                            </span>
                        </div>
                    </div>

                    <p class="text-gray-600 text-sm mb-4 line-clamp-3">
                        <?php echo htmlspecialchars(substr($publication['abstract'], 0, 120)); ?>...
                    </p>

                    <?php
                    // Fetch authors for this publication
                    $authors_query = "SELECT * FROM publication_authors WHERE publication_id = ? ORDER BY is_primary_author DESC, sort_order ASC LIMIT 4";
                    $stmt = mysqli_prepare($conn, $authors_query);
                    mysqli_stmt_bind_param($stmt, "i", $publication['id']);
                    mysqli_stmt_execute($stmt);
                    $authors_result = mysqli_stmt_get_result($stmt);

                    if (mysqli_num_rows($authors_result) > 0):
                    ?>
                    <div class="flex items-center space-x-2 mb-4">
                        <span class="text-xs text-gray-500">Authors:</span>
                        <div class="flex items-center space-x-2">
                            <?php while($author = mysqli_fetch_assoc($authors_result)): ?>
                            <div class="relative group">
                                <?php if (!empty($author['author_photo_url'])): ?>
                                <img src="<?php echo htmlspecialchars($author['author_photo_url']); ?>"
                                     alt="<?php echo htmlspecialchars($author['author_name']); ?>"
                                     class="w-6 h-6 rounded-full object-cover border-2 <?php echo $author['is_primary_author'] ? 'border-seait-orange' : 'border-gray-300'; ?> cursor-help">
                                <?php else: ?>
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium cursor-help border-2 <?php echo $author['is_primary_author'] ? 'border-seait-orange bg-seait-orange text-white' : 'border-gray-300 bg-gray-100 text-gray-700'; ?>">
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
                        <div class="text-xs text-gray-500">
                            <?php echo date('M Y', strtotime($publication['publication_date'])); ?>
                        </div>
                        <div class="flex space-x-2">
                            <?php if (!empty($publication['doi_link'])): ?>
                            <a href="<?php echo htmlspecialchars($publication['doi_link']); ?>" target="_blank"
                               class="text-seait-orange hover:text-orange-600 text-xs">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($publication['research_link'])): ?>
                            <a href="<?php echo htmlspecialchars($publication['research_link']); ?>" target="_blank"
                               class="text-seait-orange hover:text-orange-600 text-xs">
                                <i class="fas fa-file-alt"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
                <div class="text-gray-400 mb-4">
                    <i class="fas fa-search text-6xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">No Publications Found</h3>
                <p class="text-gray-500">Try adjusting your search criteria or filters</p>
                <a href="research.php" class="inline-block mt-4 bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition">
                    Clear All Filters
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Research Categories Section -->
    <section class="py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-seait-dark mb-4">Research Categories</h2>
                <p class="text-lg text-gray-600">Explore our diverse research areas and specializations</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                <?php
                // Reset categories result for display
                mysqli_data_seek($categories_result, 0);
                while($category = mysqli_fetch_assoc($categories_result)):
                ?>
                <div class="bg-gray-50 rounded-lg p-6 hover:shadow-lg transition duration-300">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4"
                             style="background-color: <?php echo $category['color_theme']; ?>20; color: <?php echo $category['color_theme']; ?>">
                            <i class="<?php echo htmlspecialchars($category['icon'] ?? 'fas fa-microscope'); ?> text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-seait-dark"><?php echo htmlspecialchars($category['name']); ?></h3>
                    </div>
                    <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($category['description']); ?></p>

                    <?php
                    // Count publications in this category
                    $count_query = "SELECT COUNT(*) as count FROM publications WHERE research_category_id = ? AND is_active = 1";
                    $stmt = mysqli_prepare($conn, $count_query);
                    mysqli_stmt_bind_param($stmt, "i", $category['id']);
                    mysqli_stmt_execute($stmt);
                    $count_result = mysqli_stmt_get_result($stmt);
                    $count = mysqli_fetch_assoc($count_result);
                    ?>

                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500"><?php echo $count['count']; ?> publications</span>
                        <a href="research.php?category=<?php echo $category['id']; ?>"
                           class="text-seait-orange hover:text-orange-600 text-sm font-medium">
                            View Publications <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Include FAB Inquiry System -->
    <?php include 'includes/fab-inquiry.php'; ?>

    <script>
        // Auto-submit form when filters change
        document.querySelectorAll('select[name="category"], select[name="status"], select[name="year"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Add loading state to search
        const searchInput = document.querySelector('input[name="search"]');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Active navbar link functionality for research page
        function updateActiveNavLink() {
            const navLinks = document.querySelectorAll('a[href^="index.php#"]');

            // Remove active class from all links
            navLinks.forEach(link => {
                link.classList.remove('navbar-link-active');
            });

            // Highlight Research & Publication link for research page
            const researchLink = document.querySelector('a[href="index.php#research"]');
            if (researchLink) {
                researchLink.classList.add('navbar-link-active');
            }
        }

        // Update active link on page load
        document.addEventListener('DOMContentLoaded', updateActiveNavLink);
    </script>
</body>
</html>