<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_social_media_manager();

// Get comprehensive statistics
function get_analytics_data($conn) {
    $stats = [];

    // Total posts by status
    $status_query = "SELECT status, COUNT(*) as count FROM posts GROUP BY status";
    $status_result = mysqli_query($conn, $status_query);
    $stats['by_status'] = [];
    while ($row = mysqli_fetch_assoc($status_result)) {
        $stats['by_status'][$row['status']] = $row['count'];
    }

    // Posts by type
    $type_query = "SELECT type, COUNT(*) as count FROM posts GROUP BY type";
    $type_result = mysqli_query($conn, $type_query);
    $stats['by_type'] = [];
    while ($row = mysqli_fetch_assoc($type_result)) {
        $stats['by_type'][$row['type']] = $row['count'];
    }

    // Recent activity (last 30 days)
    $recent_query = "SELECT
                        DATE(created_at) as date,
                        COUNT(*) as count,
                        status
                     FROM posts
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     GROUP BY DATE(created_at), status
                     ORDER BY date DESC";
    $recent_result = mysqli_query($conn, $recent_query);
    $stats['recent_activity'] = [];
    while ($row = mysqli_fetch_assoc($recent_result)) {
        $stats['recent_activity'][] = $row;
    }

    // Top content creators
    $creators_query = "SELECT
                         u.first_name, u.last_name,
                         COUNT(p.id) as post_count,
                         SUM(CASE WHEN p.status = 'approved' THEN 1 ELSE 0 END) as approved_count
                       FROM users u
                       LEFT JOIN posts p ON u.id = p.author_id
                       WHERE u.role = 'content_creator'
                       GROUP BY u.id
                       ORDER BY post_count DESC
                       LIMIT 10";
    $creators_result = mysqli_query($conn, $creators_query);
    $stats['top_creators'] = [];
    while ($row = mysqli_fetch_assoc($creators_result)) {
        $stats['top_creators'][] = $row;
    }

    // Approval statistics
    $approval_query = "SELECT
                         COUNT(*) as total_pending,
                         COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_pending,
                         AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_approval_time
                       FROM posts
                       WHERE status IN ('pending', 'approved')";
    $approval_result = mysqli_query($conn, $approval_query);
    $stats['approval_stats'] = mysqli_fetch_assoc($approval_result);

    return $stats;
}

$analytics = get_analytics_data($conn);
?>

<?php
$page_title = 'Analytics';
include 'includes/header.php';
?>
        <div class="p-3 sm:p-4 lg:p-8">
            <div class="mb-6 sm:mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Content Analytics</h1>
                <p class="text-gray-600">Comprehensive insights into content performance and management</p>
            </div>

            <!-- Information Section -->
            <?php include 'includes/info-section.php'; ?>

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Posts</p>
                            <p class="text-2xl font-bold text-gray-900">
                                <?php echo array_sum($analytics['by_status']); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-full">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending</p>
                            <p class="text-2xl font-bold text-gray-900">
                                <?php echo $analytics['by_status']['pending'] ?? 0; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-full">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Approved</p>
                            <p class="text-2xl font-bold text-gray-900">
                                <?php echo $analytics['by_status']['approved'] ?? 0; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-full">
                            <i class="fas fa-clock text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Avg Approval Time</p>
                            <p class="text-2xl font-bold text-gray-900">
                                <?php echo round($analytics['approval_stats']['avg_approval_time'] ?? 0, 1); ?>h
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-8">
                <!-- Posts by Status -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-4 sm:p-6">
                        <h3 class="text-lg font-semibold text-seait-dark mb-4">Posts by Status</h3>
                        <canvas id="statusChart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Posts by Type -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-4 sm:p-6">
                        <h3 class="text-lg font-semibold text-seait-dark mb-4">Posts by Type</h3>
                        <canvas id="typeChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Content Creators -->
            <div class="bg-white rounded-lg shadow-md mb-8">
                <div class="p-4 sm:p-6">
                    <h3 class="text-lg font-semibold text-seait-dark mb-4">Top Content Creators</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Creator
                                    </th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total Posts
                                    </th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Approved Posts
                                    </th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Approval Rate
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($analytics['top_creators'] as $creator): ?>
                                <tr>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center">
                                                    <span class="text-white font-medium">
                                                        <?php echo strtoupper(substr($creator['first_name'], 0, 1) . substr($creator['last_name'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($creator['first_name'] . ' ' . $creator['last_name']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $creator['post_count']; ?>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $creator['approved_count']; ?>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php
                                        $rate = $creator['post_count'] > 0 ? ($creator['approved_count'] / $creator['post_count']) * 100 : 0;
                                        echo round($rate, 1) . '%';
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-4 sm:p-6">
                    <h3 class="text-lg font-semibold text-seait-dark mb-4">Recent Activity (Last 30 Days)</h3>
                    <div class="space-y-4">
                        <?php
                        $grouped_activity = [];
                        foreach ($analytics['recent_activity'] as $activity) {
                            $date = $activity['date'];
                            if (!isset($grouped_activity[$date])) {
                                $grouped_activity[$date] = [];
                            }
                            $grouped_activity[$date][$activity['status']] = $activity['count'];
                        }

                        $count = 0;
                        foreach ($grouped_activity as $date => $statuses):
                            if ($count >= 10) break; // Show only last 10 days
                        ?>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-4 mb-2 sm:mb-0">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo date('M d, Y', strtotime($date)); ?>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($statuses as $status => $count): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php
                                    switch($status) {
                                        case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'approved': echo 'bg-green-100 text-green-800'; break;
                                        case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo ucfirst($status); ?>: <?php echo $count; ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php
                        $count++;
                        endforeach;
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

                </div>
            </div>
        </div>
    </main>
</div>
</div>

                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>
                    // Status Chart
                    const statusCtx = document.getElementById('statusChart').getContext('2d');
                    new Chart(statusCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Pending', 'Approved', 'Rejected', 'Draft'],
                            datasets: [{
                                data: [
                                    <?php echo $analytics['by_status']['pending'] ?? 0; ?>,
                                    <?php echo $analytics['by_status']['approved'] ?? 0; ?>,
                                    <?php echo $analytics['by_status']['rejected'] ?? 0; ?>,
                                    <?php echo $analytics['by_status']['draft'] ?? 0; ?>
                                ],
                                backgroundColor: [
                                    '#FCD34D', // Yellow for pending
                                    '#10B981', // Green for approved
                                    '#EF4444', // Red for rejected
                                    '#6B7280'  // Gray for draft
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });

                    // Type Chart
                    const typeCtx = document.getElementById('typeChart').getContext('2d');
                    new Chart(typeCtx, {
                        type: 'bar',
                        data: {
                            labels: ['News', 'Announcement', 'Hiring', 'Event', 'Article'],
                            datasets: [{
                                label: 'Posts',
                                data: [
                                    <?php echo $analytics['by_type']['news'] ?? 0; ?>,
                                    <?php echo $analytics['by_type']['announcement'] ?? 0; ?>,
                                    <?php echo $analytics['by_type']['hiring'] ?? 0; ?>,
                                    <?php echo $analytics['by_type']['event'] ?? 0; ?>,
                                    <?php echo $analytics['by_type']['article'] ?? 0; ?>
                                ],
                                backgroundColor: '#FF6B35',
                                borderColor: '#FF6B35',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                </script>
</body>
</html>
