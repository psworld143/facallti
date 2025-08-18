<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a content creator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'content_creator') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$error = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'categories';

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_category') {
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $color_theme = mysqli_real_escape_string($conn, $_POST['color_theme']);
            $sort_order = (int)$_POST['sort_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $query = "INSERT INTO research_categories (name, description, color_theme, sort_order, is_active, created_by)
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sssiis', $name, $description, $color_theme, $sort_order, $is_active, $_SESSION['user_id']);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Research category added successfully!';
            } else {
                $error = 'Failed to add research category.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'edit_category') {
            $id = (int)$_POST['category_id'];
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $color_theme = mysqli_real_escape_string($conn, $_POST['color_theme']);
            $sort_order = (int)$_POST['sort_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $query = "UPDATE research_categories SET name = ?, description = ?, color_theme = ?, sort_order = ?, is_active = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sssiis', $name, $description, $color_theme, $sort_order, $is_active, $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Research category updated successfully!';
            } else {
                $error = 'Failed to update research category.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'delete_category') {
            $id = (int)$_POST['category_id'];

            $query = "DELETE FROM research_categories WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Research category deleted successfully!';
            } else {
                $error = 'Failed to delete research category.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'add_publication') {
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            $abstract = mysqli_real_escape_string($conn, $_POST['abstract']);
            $research_category_id = (int)$_POST['research_category_id'];
            $publication_date = mysqli_real_escape_string($conn, $_POST['publication_date']);
            $journal_name = mysqli_real_escape_string($conn, $_POST['journal_name']);
            $doi_link = mysqli_real_escape_string($conn, $_POST['doi_link']);
            $research_link = mysqli_real_escape_string($conn, $_POST['research_link']);
            $keywords = mysqli_real_escape_string($conn, $_POST['keywords']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $featured = isset($_POST['featured']) ? 1 : 0;
            $sort_order = (int)$_POST['sort_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $query = "INSERT INTO publications (title, abstract, research_category_id, publication_date, journal_name, doi_link, research_link, keywords, status, featured, sort_order, is_active, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ssissssssiis', $title, $abstract, $research_category_id, $publication_date, $journal_name, $doi_link, $research_link, $keywords, $status, $featured, $sort_order, $is_active, $_SESSION['user_id']);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Publication added successfully!';
            } else {
                $error = 'Failed to add publication.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'edit_publication') {
            $id = (int)$_POST['publication_id'];
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            $abstract = mysqli_real_escape_string($conn, $_POST['abstract']);
            $research_category_id = (int)$_POST['research_category_id'];
            $publication_date = mysqli_real_escape_string($conn, $_POST['publication_date']);
            $journal_name = mysqli_real_escape_string($conn, $_POST['journal_name']);
            $doi_link = mysqli_real_escape_string($conn, $_POST['doi_link']);
            $research_link = mysqli_real_escape_string($conn, $_POST['research_link']);
            $keywords = mysqli_real_escape_string($conn, $_POST['keywords']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $featured = isset($_POST['featured']) ? 1 : 0;
            $sort_order = (int)$_POST['sort_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $query = "UPDATE publications SET title = ?, abstract = ?, research_category_id = ?, publication_date = ?, journal_name = ?, doi_link = ?, research_link = ?, keywords = ?, status = ?, featured = ?, sort_order = ?, is_active = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ssissssssiisi', $title, $abstract, $research_category_id, $publication_date, $journal_name, $doi_link, $research_link, $keywords, $status, $featured, $sort_order, $is_active, $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Publication updated successfully!';
            } else {
                $error = 'Failed to update publication.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'delete_publication') {
            $id = (int)$_POST['publication_id'];

            $query = "DELETE FROM publications WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Publication deleted successfully!';
            } else {
                $error = 'Failed to delete publication.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'add_author') {
            $publication_id = (int)$_POST['publication_id'];
            $author_name = mysqli_real_escape_string($conn, $_POST['author_name']);
            $author_title = mysqli_real_escape_string($conn, $_POST['author_title']);
            $author_department = mysqli_real_escape_string($conn, $_POST['author_department']);
            $author_photo_url = mysqli_real_escape_string($conn, $_POST['author_photo_url']);
            $author_email = mysqli_real_escape_string($conn, $_POST['author_email']);
            $author_bio = mysqli_real_escape_string($conn, $_POST['author_bio']);
            $is_primary_author = isset($_POST['is_primary_author']) ? 1 : 0;
            $sort_order = (int)$_POST['sort_order'];

            $query = "INSERT INTO publication_authors (publication_id, author_name, author_title, author_department, author_photo_url, author_email, author_bio, is_primary_author, sort_order, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'issssssiis', $publication_id, $author_name, $author_title, $author_department, $author_photo_url, $author_email, $author_bio, $is_primary_author, $sort_order, $_SESSION['user_id']);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Author added successfully!';
            } else {
                $error = 'Failed to add author.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'edit_author') {
            $id = (int)$_POST['author_id'];
            $publication_id = (int)$_POST['publication_id'];
            $author_name = mysqli_real_escape_string($conn, $_POST['author_name']);
            $author_title = mysqli_real_escape_string($conn, $_POST['author_title']);
            $author_department = mysqli_real_escape_string($conn, $_POST['author_department']);
            $author_photo_url = mysqli_real_escape_string($conn, $_POST['author_photo_url']);
            $author_email = mysqli_real_escape_string($conn, $_POST['author_email']);
            $author_bio = mysqli_real_escape_string($conn, $_POST['author_bio']);
            $is_primary_author = isset($_POST['is_primary_author']) ? 1 : 0;
            $sort_order = (int)$_POST['sort_order'];

            $query = "UPDATE publication_authors SET publication_id = ?, author_name = ?, author_title = ?, author_department = ?, author_photo_url = ?, author_email = ?, author_bio = ?, is_primary_author = ?, sort_order = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'issssssiis', $publication_id, $author_name, $author_title, $author_department, $author_photo_url, $author_email, $author_bio, $is_primary_author, $sort_order, $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Author updated successfully!';
            } else {
                $error = 'Failed to update author.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'delete_author') {
            $id = (int)$_POST['author_id'];

            $query = "DELETE FROM publication_authors WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Author deleted successfully!';
            } else {
                $error = 'Failed to delete author.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get total counts for pagination
$categories_count_query = "SELECT COUNT(*) as total FROM research_categories";
$categories_count_result = mysqli_query($conn, $categories_count_query);
$categories_total = mysqli_fetch_assoc($categories_count_result)['total'];
$categories_total_pages = ceil($categories_total / $items_per_page);

$publications_count_query = "SELECT COUNT(*) as total FROM publications";
$publications_count_result = mysqli_query($conn, $publications_count_query);
$publications_total = mysqli_fetch_assoc($publications_count_result)['total'];
$publications_total_pages = ceil($publications_total / $items_per_page);

$authors_count_query = "SELECT COUNT(*) as total FROM publication_authors";
$authors_count_result = mysqli_query($conn, $authors_count_query);
$authors_total = mysqli_fetch_assoc($authors_count_result)['total'];
$authors_total_pages = ceil($authors_total / $items_per_page);

// Get data for display with pagination
$categories_query = "SELECT * FROM research_categories ORDER BY sort_order ASC, name ASC LIMIT $items_per_page OFFSET $offset";
$categories_result = mysqli_query($conn, $categories_query);

$publications_query = "SELECT p.*, rc.name as category_name
                      FROM publications p
                      LEFT JOIN research_categories rc ON p.research_category_id = rc.id
                      ORDER BY p.sort_order ASC, p.publication_date DESC LIMIT $items_per_page OFFSET $offset";
$publications_result = mysqli_query($conn, $publications_query);

$authors_query = "SELECT pa.*, p.title as publication_title
                 FROM publication_authors pa
                 JOIN publications p ON pa.publication_id = p.id
                 ORDER BY pa.sort_order ASC, pa.author_name ASC LIMIT $items_per_page OFFSET $offset";
$authors_result = mysqli_query($conn, $authors_query);

// Helper function to generate pagination links
function generatePaginationLinks($current_page, $total_pages, $active_tab) {
    // Ensure parameters are integers
    $current_page = (int)$current_page;
    $total_pages = (int)$total_pages;

    $links = '';
    $base_url = "?tab=$active_tab&page=";

    if ($total_pages <= 1) {
        return $links;
    }

    // Previous button
    if ($current_page > 1) {
        $links .= '<a href="' . $base_url . ($current_page - 1) . '" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50">Previous</a>';
    } else {
        $links .= '<span class="px-3 py-2 text-sm font-medium text-gray-300 bg-white border border-gray-300 rounded-l-md cursor-not-allowed">Previous</span>';
    }

    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);

    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $links .= '<span class="px-3 py-2 text-sm font-medium text-white bg-seait-orange border border-seait-orange">' . $i . '</span>';
        } else {
            $links .= '<a href="' . $base_url . $i . '" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 hover:bg-gray-50">' . $i . '</a>';
        }
    }

    // Next button
    if ($current_page < $total_pages) {
        $links .= '<a href="' . $base_url . ($current_page + 1) . '" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50">Next</a>';
    } else {
        $links .= '<span class="px-3 py-2 text-sm font-medium text-gray-300 bg-white border border-gray-300 rounded-r-md cursor-not-allowed">Next</span>';
    }

    return $links;
}

// Helper function to generate initials from author name
function generateAuthorInitials($authorName) {
    $name = trim($authorName);
    if (empty($name)) {
        return '?';
    }

    $words = explode(' ', $name);
    $initials = '';

    // Get first letter of first name
    if (!empty($words[0])) {
        $initials .= strtoupper(substr($words[0], 0, 1));
    }

    // Get first letter of last name (if different from first name)
    if (count($words) > 1 && !empty($words[count($words) - 1])) {
        $lastName = $words[count($words) - 1];
        if ($lastName !== $words[0]) {
            $initials .= strtoupper(substr($lastName, 0, 1));
        }
    }

    return $initials ?: strtoupper(substr($name, 0, 1));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Research - SEAIT Content Creator</title>
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
                        'seait-dark': '#2C3E50'
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

        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .animate-bounce-in {
            animation: bounceIn 0.6s ease-out;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white fixed top-0 left-0 right-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-10 w-auto">
                    <div>
                        <h1 class="text-xl font-bold text-seait-dark">SEAIT Content Creator</h1>
                        <p class="text-sm text-gray-600">Welcome, <?php echo $_SESSION['first_name']; ?></p>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-seait-dark hover:text-seait-orange transition">
                        <i class="fas fa-home mr-2"></i><span class="hidden sm:inline">View Site</span>
                    </a>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">
                        <i class="fas fa-sign-out-alt mr-2"></i><span class="hidden sm:inline">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex min-h-screen pt-16">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 lg:ml-72 overflow-y-auto h-screen">
            <div class="p-6">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Manage Research & Publications</h1>
                    <p class="text-gray-600">Manage research categories, publications, and authors</p>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Information Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 lg:p-6 mb-6 lg:mb-8">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 mb-2">Research & Publications Management</h3>
                            <div class="text-sm text-blue-700 space-y-1">
                                <p><strong>Research Categories:</strong> Create and manage research categories to organize publications by field or topic.</p>
                                <p><strong>Publications:</strong> Add research publications with details including title, category, publication date, and featured status.</p>
                                <p><strong>Authors:</strong> Manage publication authors and their affiliations with the institution.</p>
                                <p><strong>Organization:</strong> Use the tabs to switch between different management sections. All data is organized for easy access and editing.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white rounded-lg shadow-sm mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="flex space-x-8 px-6">
                            <a href="?tab=categories" class="py-4 px-1 border-b-2 font-medium text-sm <?php echo $active_tab === 'categories' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                Research Categories
                            </a>
                            <a href="?tab=publications" class="py-4 px-1 border-b-2 font-medium text-sm <?php echo $active_tab === 'publications' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                Publications
                            </a>
                            <a href="?tab=authors" class="py-4 px-1 border-b-2 font-medium text-sm <?php echo $active_tab === 'authors' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                Authors
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Tab Content -->
                <?php if ($active_tab === 'categories'): ?>
                    <!-- Categories Tab -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-seait-dark">Research Categories</h2>
                            <button onclick="openModal('addCategoryModal')" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition">
                                <i class="fas fa-plus mr-2"></i>Add Category
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Color</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sort Order</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($row = mysqli_fetch_assoc($categories_result)): ?>
                                        <tr data-category-id="<?php echo $row['id']; ?>" data-category-name="<?php echo htmlspecialchars($row['name']); ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="w-6 h-6 rounded-full" style="background-color: <?php echo $row['color_theme']; ?>"></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo $row['name']; ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?php echo substr($row['description'], 0, 100); ?>...</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo $row['sort_order']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $row['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo $row['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="editCategory(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="text-seait-orange hover:text-orange-600 mr-3">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="deleteCategory(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination for Categories -->
                        <?php if ($categories_total_pages > 1): ?>
                        <div class="flex items-center justify-between mt-6">
                            <div class="text-sm text-gray-700">
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $items_per_page, $categories_total); ?> of <?php echo $categories_total; ?> results
                            </div>
                            <div class="flex items-center space-x-1">
                                <?php echo generatePaginationLinks($current_page, $categories_total_pages, 'categories'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($active_tab === 'publications'): ?>
                    <!-- Publications Tab -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-seait-dark">Publications</h2>
                            <button onclick="openModal('addPublicationModal')" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition">
                                <i class="fas fa-plus mr-2"></i>Add Publication
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Featured</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($row = mysqli_fetch_assoc($publications_result)): ?>
                                        <tr data-publication-id="<?php echo $row['id']; ?>" data-publication-title="<?php echo htmlspecialchars($row['title']); ?>">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars(substr($row['title'], 0, 80)); ?>...</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo $row['category_name']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    <?php echo $row['status'] === 'published' ? 'bg-green-100 text-green-800' :
                                                          ($row['status'] === 'in_progress' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo date('M Y', strtotime($row['publication_date'])); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $row['featured'] ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                    <?php echo $row['featured'] ? 'Featured' : 'Regular'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="editPublication(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="text-seait-orange hover:text-orange-600 mr-3">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="deletePublication(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination for Publications -->
                        <?php if ($publications_total_pages > 1): ?>
                        <div class="flex items-center justify-between mt-6">
                            <div class="text-sm text-gray-700">
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $items_per_page, $publications_total); ?> of <?php echo $publications_total; ?> results
                            </div>
                            <div class="flex items-center space-x-1">
                                <?php echo generatePaginationLinks($current_page, $publications_total_pages, 'publications'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($active_tab === 'authors'): ?>
                    <!-- Authors Tab -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-seait-dark">Authors</h2>
                            <button onclick="openModal('addAuthorModal')" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition">
                                <i class="fas fa-plus mr-2"></i>Add Author
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Publication</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Primary</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($row = mysqli_fetch_assoc($authors_result)): ?>
                                        <tr data-author-id="<?php echo $row['id']; ?>" data-author-name="<?php echo htmlspecialchars($row['author_name']); ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <?php if (!empty($row['author_photo_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($row['author_photo_url']); ?>"
                                                         alt="<?php echo htmlspecialchars($row['author_name']); ?>"
                                                         class="w-8 h-8 rounded-full object-cover mr-3 author-photo"
                                                         data-author-name="<?php echo htmlspecialchars($row['author_name']); ?>"
                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center mr-3 author-initials" style="display: none;">
                                                        <span class="text-white text-xs font-medium"><?php echo generateAuthorInitials($row['author_name']); ?></span>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center mr-3">
                                                        <span class="text-white text-xs font-medium"><?php echo generateAuthorInitials($row['author_name']); ?></span>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900"><?php echo $row['author_name']; ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo $row['author_email']; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars(substr($row['publication_title'], 0, 60)); ?>...</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo $row['author_title']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo $row['author_department']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $row['is_primary_author'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                    <?php echo $row['is_primary_author'] ? 'Primary' : 'Co-author'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="editAuthor(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="text-seait-orange hover:text-orange-600 mr-3">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="deleteAuthor(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination for Authors -->
                        <?php if ($authors_total_pages > 1): ?>
                        <div class="flex items-center justify-between mt-6">
                            <div class="text-sm text-gray-700">
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $items_per_page, $authors_total); ?> of <?php echo $authors_total; ?> results
                            </div>
                            <div class="flex items-center space-x-1">
                                <?php echo generatePaginationLinks($current_page, $authors_total_pages, 'authors'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Research Category</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_category">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category Name</label>
                        <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Color Theme</label>
                        <input type="color" name="color_theme" value="#FF6B35" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" checked class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('addCategoryModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Add Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Research Category</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="category_id" id="edit_category_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category Name</label>
                        <input type="text" name="name" id="edit_category_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" id="edit_category_description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Color Theme</label>
                        <input type="color" name="color_theme" id="edit_category_color_theme" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" id="edit_category_sort_order" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" id="edit_category_is_active" class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('editCategoryModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Publication Modal -->
    <div id="addPublicationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Publication</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_publication">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                        <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Abstract</label>
                        <textarea name="abstract" rows="4" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Research Category</label>
                        <select name="research_category_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Category</option>
                            <?php
                            mysqli_data_seek($categories_result, 0);
                            while($cat = mysqli_fetch_assoc($categories_result)):
                            ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Publication Date</label>
                        <input type="date" name="publication_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Journal Name</label>
                        <input type="text" name="journal_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">DOI Link</label>
                        <input type="url" name="doi_link" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Research Link</label>
                        <input type="url" name="research_link" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Keywords</label>
                        <input type="text" name="keywords" placeholder="keyword1, keyword2, keyword3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="published">Published</option>
                            <option value="in_progress">In Progress</option>
                            <option value="submitted">Submitted</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="featured" class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Featured Publication</span>
                        </label>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" checked class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('addPublicationModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Add Publication
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Publication Modal -->
    <div id="editPublicationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Publication</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="edit_publication">
                    <input type="hidden" name="publication_id" id="edit_publication_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                        <input type="text" name="title" id="edit_publication_title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Abstract</label>
                        <textarea name="abstract" id="edit_publication_abstract" rows="4" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Research Category</label>
                        <select name="research_category_id" id="edit_publication_category" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Category</option>
                            <?php
                            mysqli_data_seek($categories_result, 0);
                            while($cat = mysqli_fetch_assoc($categories_result)):
                            ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Publication Date</label>
                        <input type="date" name="publication_date" id="edit_publication_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Journal Name</label>
                        <input type="text" name="journal_name" id="edit_publication_journal" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">DOI Link</label>
                        <input type="url" name="doi_link" id="edit_publication_doi" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Research Link</label>
                        <input type="url" name="research_link" id="edit_publication_research_link" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Keywords</label>
                        <input type="text" name="keywords" id="edit_publication_keywords" placeholder="keyword1, keyword2, keyword3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" id="edit_publication_status" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="published">Published</option>
                            <option value="in_progress">In Progress</option>
                            <option value="submitted">Submitted</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="featured" id="edit_publication_featured" class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Featured Publication</span>
                        </label>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" id="edit_publication_sort_order" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" id="edit_publication_is_active" class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('editPublicationModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Update Publication
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Author Modal -->
    <div id="addAuthorModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Author</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_author">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Publication</label>
                        <select name="publication_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Publication</option>
                            <?php
                            mysqli_data_seek($publications_result, 0);
                            while($pub = mysqli_fetch_assoc($publications_result)):
                            ?>
                            <option value="<?php echo $pub['id']; ?>"><?php echo htmlspecialchars(substr($pub['title'], 0, 80)); ?>...</option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Author Name</label>
                        <input type="text" name="author_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title/Position</label>
                        <input type="text" name="author_title" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <input type="text" name="author_department" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Photo URL</label>
                        <input type="url" name="author_photo_url" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="author_email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bio</label>
                        <textarea name="author_bio" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_primary_author" class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Primary Author</span>
                        </label>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('addAuthorModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Add Author
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Author Modal -->
    <div id="editAuthorModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Author</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="edit_author">
                    <input type="hidden" name="author_id" id="edit_author_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Publication</label>
                        <select name="publication_id" id="edit_author_publication_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Publication</option>
                            <?php
                            mysqli_data_seek($publications_result, 0);
                            while($pub = mysqli_fetch_assoc($publications_result)):
                            ?>
                            <option value="<?php echo $pub['id']; ?>"><?php echo htmlspecialchars(substr($pub['title'], 0, 80)); ?>...</option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Author Name</label>
                        <input type="text" name="author_name" id="edit_author_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title/Position</label>
                        <input type="text" name="author_title" id="edit_author_title" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <input type="text" name="author_department" id="edit_author_department" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Photo URL</label>
                        <input type="url" name="author_photo_url" id="edit_author_photo_url" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="author_email" id="edit_author_email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bio</label>
                        <textarea name="author_bio" id="edit_author_bio" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_primary_author" id="edit_author_is_primary" class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Primary Author</span>
                        </label>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" id="edit_author_sort_order" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('editAuthorModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Update Author
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Category Confirmation Modal -->
    <div id="deleteCategoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Research Category</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete "<span id="deleteCategoryName" class="font-semibold"></span>"? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Category will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-link mr-2 text-red-500"></i>
                                    All associated publications will be affected
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    No longer visible on the website
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form id="deleteCategoryForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="category_id" id="deleteCategoryId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteCategoryModal()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-trash mr-2"></i>Delete Permanently
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Publication Confirmation Modal -->
    <div id="deletePublicationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Publication</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete "<span id="deletePublicationName" class="font-semibold"></span>"? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Publication will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-link mr-2 text-red-500"></i>
                                    All associated authors will be deleted
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    No longer visible on the website
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form id="deletePublicationForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_publication">
                        <input type="hidden" name="publication_id" id="deletePublicationId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeletePublicationModal()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-trash mr-2"></i>Delete Permanently
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Author Confirmation Modal -->
    <div id="deleteAuthorModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Author</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete "<span id="deleteAuthorName" class="font-semibold"></span>"? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Author will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-link mr-2 text-red-500"></i>
                                    No longer associated with publications
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    No longer visible on the website
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form id="deleteAuthorForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_author">
                        <input type="hidden" name="author_id" id="deleteAuthorId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteAuthorModal()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-trash mr-2"></i>Delete Permanently
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Category functions
        function editCategory(category) {
            document.getElementById('edit_category_id').value = category.id;
            document.getElementById('edit_category_name').value = category.name;
            document.getElementById('edit_category_description').value = category.description;
            document.getElementById('edit_category_color_theme').value = category.color_theme;
            document.getElementById('edit_category_sort_order').value = category.sort_order;
            document.getElementById('edit_category_is_active').checked = category.is_active == 1;
            openModal('editCategoryModal');
        }

        function deleteCategory(categoryId) {
            const categoryRow = document.querySelector(`tr[data-category-id="${categoryId}"]`);
            const categoryName = categoryRow.getAttribute('data-category-name');
            document.getElementById('deleteCategoryName').textContent = categoryName;
            document.getElementById('deleteCategoryId').value = categoryId;
            openModal('deleteCategoryModal');
        }

        function closeDeleteCategoryModal() {
            document.getElementById('deleteCategoryModal').classList.add('hidden');
        }

        // Publication functions
        function editPublication(publication) {
            document.getElementById('edit_publication_id').value = publication.id;
            document.getElementById('edit_publication_title').value = publication.title;
            document.getElementById('edit_publication_abstract').value = publication.abstract;
            document.getElementById('edit_publication_category').value = publication.research_category_id;
            document.getElementById('edit_publication_date').value = publication.publication_date;
            document.getElementById('edit_publication_journal').value = publication.journal_name;
            document.getElementById('edit_publication_doi').value = publication.doi_link;
            document.getElementById('edit_publication_research_link').value = publication.research_link;
            document.getElementById('edit_publication_keywords').value = publication.keywords;
            document.getElementById('edit_publication_status').value = publication.status;
            document.getElementById('edit_publication_featured').checked = publication.featured == 1;
            document.getElementById('edit_publication_sort_order').value = publication.sort_order;
            document.getElementById('edit_publication_is_active').checked = publication.is_active == 1;
            openModal('editPublicationModal');
        }

        function deletePublication(publicationId) {
            const publicationRow = document.querySelector(`tr[data-publication-id="${publicationId}"]`);
            const publicationTitle = publicationRow.getAttribute('data-publication-title');
            document.getElementById('deletePublicationName').textContent = publicationTitle;
            document.getElementById('deletePublicationId').value = publicationId;
            openModal('deletePublicationModal');
        }

        function closeDeletePublicationModal() {
            document.getElementById('deletePublicationModal').classList.add('hidden');
        }

        // Author functions
        function editAuthor(author) {
            document.getElementById('edit_author_id').value = author.id;
            document.getElementById('edit_author_publication_id').value = author.publication_id;
            document.getElementById('edit_author_name').value = author.author_name;
            document.getElementById('edit_author_title').value = author.author_title;
            document.getElementById('edit_author_department').value = author.author_department;
            document.getElementById('edit_author_photo_url').value = author.author_photo_url;
            document.getElementById('edit_author_email').value = author.author_email;
            document.getElementById('edit_author_bio').value = author.author_bio;
            document.getElementById('edit_author_is_primary').checked = author.is_primary_author == 1;
            document.getElementById('edit_author_sort_order').value = author.sort_order;
            openModal('editAuthorModal');
        }

        function deleteAuthor(authorId) {
            const authorRow = document.querySelector(`tr[data-author-id="${authorId}"]`);
            const authorName = authorRow.getAttribute('data-author-name');
            document.getElementById('deleteAuthorName').textContent = authorName;
            document.getElementById('deleteAuthorId').value = authorId;
            openModal('deleteAuthorModal');
        }

        function closeDeleteAuthorModal() {
            document.getElementById('deleteAuthorModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('[id$="Modal"]');
            modals.forEach(modal => {
                if (event.target === modal) {
                    closeModal(modal.id);
                }
            });
        }

        // Close specific delete modals when clicking outside
        const deleteCategoryModal = document.getElementById('deleteCategoryModal');
        const deletePublicationModal = document.getElementById('deletePublicationModal');
        const deleteAuthorModal = document.getElementById('deleteAuthorModal');

        if (deleteCategoryModal) {
            deleteCategoryModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteCategoryModal();
                }
            });
        }

        if (deletePublicationModal) {
            deletePublicationModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeletePublicationModal();
                }
            });
        }

        if (deleteAuthorModal) {
            deleteAuthorModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteAuthorModal();
                }
            });
        }

        // Handle author photo loading errors
        document.addEventListener('DOMContentLoaded', function() {
            const authorPhotos = document.querySelectorAll('.author-photo');
            authorPhotos.forEach(function(img) {
                img.addEventListener('error', function() {
                    // Hide the image
                    this.style.display = 'none';
                    // Show the initials div
                    const initialsDiv = this.nextElementSibling;
                    if (initialsDiv && initialsDiv.classList.contains('author-initials')) {
                        initialsDiv.style.display = 'flex';
                    }
                });
            });
        });
    </script>
</body>
</html>