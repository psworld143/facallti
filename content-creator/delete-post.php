<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_content_creator();

$message = '';
$error = '';

// Get post ID from URL
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$post_id) {
    header('Location: my-posts.php');
    exit();
}

// Verify the post belongs to the current user
$verify_query = "SELECT id, title FROM posts WHERE id = ? AND author_id = ?";
$stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($stmt, "ii", $post_id, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    header('Location: my-posts.php');
    exit();
}

$post = mysqli_fetch_assoc($result);

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $delete_query = "DELETE FROM posts WHERE id = ? AND author_id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, "ii", $post_id, $_SESSION['user_id']);

    if (mysqli_stmt_execute($stmt)) {
        $message = 'Post deleted successfully!';
        // Redirect after a short delay
        header('Refresh: 2; URL=my-posts.php');
    } else {
        $error = 'Failed to delete post.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Post - SEAIT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            <div class="p-4 lg:p-8">
                <div class="mb-8">
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Delete Post</h1>
                    <p class="text-gray-600">Confirm deletion of your post</p>
                </div>

                <!-- Information Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 lg:p-6 mb-6 lg:mb-8">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 mb-2">Post Deletion</h3>
                            <div class="text-sm text-blue-700 space-y-1">
                                <p><strong>Confirmation Required:</strong> This action requires explicit confirmation to prevent accidental deletions.</p>
                                <p><strong>Permanent Action:</strong> Once deleted, the post cannot be recovered. All content and data will be permanently removed.</p>
                                <p><strong>Impact:</strong> If the post was published, it will no longer appear on the website.</p>
                                <p><strong>Safety:</strong> You can cancel this action at any time before confirming the deletion.</p>
                            </div>
                        </div>
                    </div>
                </div>

            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $message; ?>
                    <p class="mt-2">Redirecting to My Posts...</p>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="max-w-md mx-auto">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="text-center mb-6">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Delete Post</h3>
                        <p class="text-sm text-gray-500">
                            Are you sure you want to delete the post "<strong><?php echo htmlspecialchars($post['title']); ?></strong>"?
                        </p>
                        <p class="text-sm text-gray-500 mt-2">
                            This action cannot be undone.
                        </p>
                    </div>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="confirm_delete" value="1">

                        <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3">
                            <button type="submit"
                                    class="bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition w-full sm:w-auto">
                                <i class="fas fa-trash mr-2"></i>Delete Post
                            </button>
                            <a href="my-posts.php"
                               class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 transition text-center w-full sm:w-auto">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>