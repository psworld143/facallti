<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_admin();

$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'mark_resolved') {
        $id = (int)$_POST['id'];
        $query = "UPDATE user_inquiries SET is_resolved = 1 WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {
            $message = display_message('Inquiry marked as resolved!', 'success');
        } else {
            $message = display_message('Error updating inquiry. Please try again.', 'error');
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 20;

// Build query with filters
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if ($status_filter !== '') {
    $where_conditions[] = "is_resolved = ?";
    $params[] = $status_filter === 'resolved' ? 1 : 0;
    $param_types .= "i";
}

if ($search_query) {
    $where_conditions[] = "(user_question LIKE ? OR bot_response LIKE ? OR user_email LIKE ? OR user_name LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM user_inquiries WHERE $where_clause";
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

// Fetch inquiries with pagination
$query = "SELECT * FROM user_inquiries WHERE $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";

$params[] = $items_per_page;
$params[] = $offset;
$param_types .= "ii";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$inquiries_result = mysqli_stmt_get_result($stmt);

// Debug information
$total_rows = mysqli_num_rows($inquiries_result);
echo "<!-- Debug: Total rows found: $total_rows -->";
echo "<!-- Debug: Total count: $total -->";
echo "<!-- Debug: Page: $page, Items per page: $items_per_page -->";
echo "<!-- Debug: Where clause: $where_clause -->";
echo "<!-- Debug: Params: " . json_encode($params) . " -->";

// Test query to verify data exists
$test_query = "SELECT COUNT(*) as test_count FROM user_inquiries";
$test_result = mysqli_query($conn, $test_query);
$test_data = mysqli_fetch_assoc($test_result);
echo "<!-- Debug: Test count: " . $test_data['test_count'] . " -->";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Inquiries - Admin Dashboard</title>
    <link rel="icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="../assets/images/seait-logo.png">
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
    <?php include 'includes/admin-header.php'; ?>


            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-seait-dark">User Inquiries</h1>
                <div class="text-sm text-gray-600">
                    Total: <?php echo $total; ?> inquiries
                </div>
            </div>

            <?php echo $message; ?>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <form method="GET" class="flex flex-wrap gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">All</option>
                            <option value="unresolved" <?php echo $status_filter === 'unresolved' ? 'selected' : ''; ?>>Unresolved</option>
                            <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                               placeholder="Search questions, responses, or user info..."
                               class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Inquiries List -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User Question</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bot Response</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User Info</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            $row_count = 0;
                            while($inquiry = mysqli_fetch_assoc($inquiries_result)):
                                $row_count++;
                            ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-xs"><?php echo htmlspecialchars($inquiry['user_question']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-600 max-w-xs"><?php echo htmlspecialchars(substr($inquiry['bot_response'], 0, 100)); ?>...</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-500">
                                        <?php if ($inquiry['user_name']): ?>
                                            <div><?php echo htmlspecialchars($inquiry['user_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($inquiry['user_email']): ?>
                                            <div><?php echo htmlspecialchars($inquiry['user_email']); ?></div>
                                        <?php endif; ?>
                                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($inquiry['ip_address']); ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $inquiry['is_resolved'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo $inquiry['is_resolved'] ? 'Resolved' : 'Unresolved'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo date('M d, Y H:i', strtotime($inquiry['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium">
                                    <?php if (!$inquiry['is_resolved']): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="mark_resolved">
                                        <input type="hidden" name="id" value="<?php echo $inquiry['id']; ?>">
                                        <button type="submit" class="text-green-600 hover:text-green-800" title="Mark as resolved">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <button onclick="viewInquiry(<?php echo htmlspecialchars(json_encode($inquiry)); ?>)" class="text-seait-orange hover:text-orange-600 ml-2">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>

                            <?php if ($row_count === 0): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    No inquiries found matching your criteria.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-6">
                <div class="flex space-x-2">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_query); ?>"
                       class="px-3 py-2 rounded-lg <?php echo $i === $page ? 'bg-seait-orange text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
      

    <!-- View Inquiry Modal -->
    <div id="inquiry-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-seait-dark">Inquiry Details</h3>
                        <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <div id="inquiry-details" class="space-y-4">
                        <!-- Details will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewInquiry(inquiry) {
            const details = document.getElementById('inquiry-details');
            details.innerHTML = `
                <div class="space-y-4">
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-2">User Question</h4>
                        <p class="text-gray-700 bg-gray-50 p-3 rounded-lg">${inquiry.user_question}</p>
                    </div>

                    <div>
                        <h4 class="font-semibold text-gray-900 mb-2">Bot Response</h4>
                        <p class="text-gray-700 bg-gray-50 p-3 rounded-lg">${inquiry.bot_response}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">User Information</h4>
                            <div class="text-sm text-gray-600">
                                ${inquiry.user_name ? `<div><strong>Name:</strong> ${inquiry.user_name}</div>` : ''}
                                ${inquiry.user_email ? `<div><strong>Email:</strong> ${inquiry.user_email}</div>` : ''}
                                <div><strong>IP Address:</strong> ${inquiry.ip_address}</div>
                                <div><strong>User Agent:</strong> ${inquiry.user_agent}</div>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">Inquiry Information</h4>
                            <div class="text-sm text-gray-600">
                                <div><strong>Status:</strong> <span class="px-2 py-1 text-xs font-medium rounded-full ${inquiry.is_resolved ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">${inquiry.is_resolved ? 'Resolved' : 'Unresolved'}</span></div>
                                <div><strong>Date:</strong> ${new Date(inquiry.created_at).toLocaleString()}</div>
                                <div><strong>ID:</strong> ${inquiry.id}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('inquiry-modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('inquiry-modal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('inquiry-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>