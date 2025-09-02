<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/id_encryption.php';

// Get filter parameters
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 9;

// Build query with filters
$where_conditions = ["status = 'approved'"];
$params = [];
$param_types = "";

if ($type_filter) {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
    $param_types .= "s";
}

if ($search_query) {
    $where_conditions[] = "(title LIKE ? OR content LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $param_types .= "ss";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM posts WHERE $where_clause";
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $count_query);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $count_result = mysqli_stmt_get_result($stmt);
} else {
    $count_result = mysqli_query($conn, $count_query);
}
$total = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total / $items_per_page);

// Calculate offset
$offset = ($page - 1) * $items_per_page;

// Fetch posts with pagination
$query = "SELECT p.*, u.first_name, u.last_name
          FROM posts p
          JOIN users u ON p.author_id = u.id
          WHERE $where_clause
          ORDER BY p.created_at DESC
          LIMIT ? OFFSET ?";

$params[] = $items_per_page;
$params[] = $offset;
$param_types .= "ii";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $param_types, ...$params);
mysqli_stmt_execute($stmt);
$posts_result = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News - SEAIT</title>
    <link rel="icon" type="image/png" href="assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="assets/images/seait-logo.png">
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
    </style>
</head>
<body class="bg-gray-50 dark-mode" data-theme="light">
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-seait-orange to-orange-600 text-white py-12 md:py-16">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-4">News & Updates</h1>
            <p class="text-lg md:text-xl max-w-3xl mx-auto">Stay informed about the latest happenings, announcements, and developments at SEAIT</p>
        </div>
    </section>

    <!-- Filters and Search -->
    <section class="bg-white py-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <form method="GET" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                               placeholder="Search news and updates..."
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>
                <div class="flex gap-2">
                    <select name="type" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <option value="">All Types</option>
                        <option value="news" <?php echo $type_filter === 'news' ? 'selected' : ''; ?>>News</option>
                        <option value="announcement" <?php echo $type_filter === 'announcement' ? 'selected' : ''; ?>>Announcements</option>
                        <option value="hiring" <?php echo $type_filter === 'hiring' ? 'selected' : ''; ?>>Hiring</option>
                        <option value="event" <?php echo $type_filter === 'event' ? 'selected' : ''; ?>>Events</option>
                        <option value="article" <?php echo $type_filter === 'article' ? 'selected' : ''; ?>>Articles</option>
                    </select>
                    <button type="submit" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="news.php" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 transition">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </section>

    <!-- News Grid -->
    <section class="py-12 md:py-16">
        <div class="max-w-7xl mx-auto px-4">
            <?php if (mysqli_num_rows($posts_result) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                    <?php while($post = mysqli_fetch_assoc($posts_result)): ?>
                    <article class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-3">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full <?php
                                    echo $post['type'] === 'news' ? 'bg-blue-100 text-blue-800' :
                                        ($post['type'] === 'announcement' ? 'bg-yellow-100 text-yellow-800' :
                                        ($post['type'] === 'hiring' ? 'bg-green-100 text-green-800' :
                                        ($post['type'] === 'event' ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800')));
                                ?>">
                                    <?php echo ucfirst($post['type']); ?>
                                </span>
                                <span class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
                            </div>

                            <h3 class="text-lg md:text-xl font-semibold mb-3 text-seait-dark">
                                <a href="news-detail.php?id=<?php echo encrypt_id($post['id']); ?>" class="hover:text-seait-orange transition">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                            </h3>

                            <div class="text-gray-600 mb-4 prose prose-sm max-w-none text-sm md:text-base">
                                <?php
                                // Display HTML content safely, but limit length for preview
                                $content = strip_tags($post['content']);
                                echo htmlspecialchars(substr($content, 0, 150)) . (strlen($content) > 150 ? '...' : '');
                                ?>
                            </div>

                            <div class="flex justify-between items-center">
                                <div class="text-xs text-gray-500">
                                    <i class="fas fa-user mr-1"></i>
                                    <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>
                                </div>
                                <a href="news-detail.php?id=<?php echo encrypt_id($post['id']); ?>" class="text-seait-orange hover:underline text-sm font-medium">
                                    Read More <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                    <?php endwhile; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="mt-12 flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0">
                    <div class="text-sm text-gray-700">
                        Showing <?php echo (($page - 1) * $items_per_page) + 1; ?> to <?php echo min($page * $items_per_page, $total); ?> of <?php echo $total; ?> posts
                    </div>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                           class="px-4 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">
                            <i class="fas fa-chevron-left mr-1"></i>Previous
                        </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                           class="px-4 py-2 text-sm rounded transition <?php echo $i == $page ? 'bg-seait-orange text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                           class="px-4 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">
                            Next<i class="fas fa-chevron-right ml-1"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-newspaper text-gray-400 text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No posts found</h3>
                    <p class="text-gray-500 mb-6">
                        <?php if ($search_query || $type_filter): ?>
                            No posts match your current filters. Try adjusting your search criteria.
                        <?php else: ?>
                            No posts are available at the moment. Please check back later.
                        <?php endif; ?>
                    </p>
                    <?php if ($search_query || $type_filter): ?>
                    <a href="news.php" class="bg-seait-orange text-white px-6 py-3 rounded-lg hover:bg-orange-600 transition">
                        <i class="fas fa-times mr-2"></i>Clear Filters
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Include FAB Inquiry System -->
    <?php include 'includes/fab-inquiry.php'; ?>

    <script>
        // Mobile menu functionality
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });

        // Close mobile menu when clicking on a link
        const mobileMenuLinks = mobileMenu.querySelectorAll('a');
        mobileMenuLinks.forEach(link => {
            link.addEventListener('click', function() {
                mobileMenu.classList.add('hidden');
            });
        });
    </script>
</body>
</html>