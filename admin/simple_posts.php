<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Simple admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Please log in as admin.");
}

// Simple query to get posts
$query = "SELECT p.*, u.first_name, u.last_name, u.username
          FROM posts p
          LEFT JOIN users u ON p.author_id = u.id
          ORDER BY p.created_at DESC";

$result = mysqli_query($conn, $query);
$total_posts = mysqli_num_rows($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Posts Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Simple Posts Test</h1>

        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">Debug Info</h2>
            <p><strong>Database Connected:</strong> <?php echo $conn ? 'Yes' : 'No'; ?></p>
            <p><strong>User ID:</strong> <?php echo $_SESSION['user_id'] ?? 'Not set'; ?></p>
            <p><strong>User Role:</strong> <?php echo $_SESSION['role'] ?? 'Not set'; ?></p>
            <p><strong>Total Posts Found:</strong> <?php echo $total_posts; ?></p>
            <p><strong>Query:</strong> <?php echo htmlspecialchars($query); ?></p>
        </div>

        <?php if ($total_posts > 0): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold">Posts (<?php echo $total_posts; ?>)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Author</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($post = mysqli_fetch_assoc($result)): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo $post['id']; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($post['title']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo substr(strip_tags($post['content']), 0, 100) . '...'; ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                    <?php echo ucfirst($post['type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                    switch($post['status']) {
                                        case 'approved': echo 'bg-green-100 text-green-800'; break;
                                        case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                        case 'draft': echo 'bg-gray-100 text-gray-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo ucfirst($post['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>
                                <div class="text-xs text-gray-500">@<?php echo htmlspecialchars($post['username']); ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <p class="text-center text-gray-500">No posts found.</p>
        </div>
        <?php endif; ?>

        <div class="mt-6">
            <a href="posts.php" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition">
                Back to Full Posts Page
            </a>
        </div>
    </div>
</body>
</html>