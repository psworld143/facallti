<?php
/**
 * IntelliEVal - Dashboard Clustering Widget
 * Displays clustering analysis results in the dashboard
 */

require_once 'clustering_analysis.php';

// Initialize clustering analysis
$clustering = new ClusteringAnalysis($conn);

// Get current active semester for filtering
$current_semester_query = "SELECT id, name, academic_year FROM semesters WHERE status = 'active' ORDER BY created_at DESC LIMIT 1";
$current_semester_result = mysqli_query($conn, $current_semester_query);
$current_semester = mysqli_fetch_assoc($current_semester_result);

// Check if a semester filter is applied
$semester_id = null;
$semester_name = 'No Active Semester';

if (isset($_GET['semester_filter']) && !empty($_GET['semester_filter'])) {
    // Use the selected semester filter
    $semester_id = (int)$_GET['semester_filter'];
    $filtered_semester_query = "SELECT id, name, academic_year FROM semesters WHERE id = ?";
    $filtered_semester_stmt = mysqli_prepare($conn, $filtered_semester_query);
    mysqli_stmt_bind_param($filtered_semester_stmt, "i", $semester_id);
    mysqli_stmt_execute($filtered_semester_stmt);
    $filtered_semester_result = mysqli_stmt_get_result($filtered_semester_stmt);
    $filtered_semester = mysqli_fetch_assoc($filtered_semester_result);
    if ($filtered_semester) {
        $semester_name = $filtered_semester['name'] . ' (' . $filtered_semester['academic_year'] . ') - Filtered';
    }
} elseif ($current_semester) {
    // Use the active semester if no filter is applied
    $semester_id = $current_semester['id'];
    $semester_name = $current_semester['name'] . ' (' . $current_semester['academic_year'] . ')';
}

// Get clustering data with error handling
$teacher_clusters = $clustering->clusterTeacherPerformance($semester_id, 3);
$pattern_clusters = $clustering->clusterEvaluationPatterns($semester_id, 4);
$department_clusters = $clustering->clusterDepartmentPerformance($semester_id, 3);

// Get insights with error handling
$teacher_insights = [];
$pattern_insights = [];
$department_insights = [];

// Track data source information
$data_sources = [];
$total_real_data_items = 0;

if (!isset($teacher_clusters['error'])) {
    $teacher_insights = $clustering->getClusteringInsights($teacher_clusters, 'teacher');
    if (isset($teacher_clusters['metadata'])) {
        $data_sources['teacher'] = $teacher_clusters['metadata'];
        $total_real_data_items += $teacher_clusters['metadata']['total_teachers'];
    }
}

if (!isset($pattern_clusters['error'])) {
    $pattern_insights = $clustering->getClusteringInsights($pattern_clusters, 'pattern');
    if (isset($pattern_clusters['metadata'])) {
        $data_sources['pattern'] = $pattern_clusters['metadata'];
        $total_real_data_items += $pattern_clusters['metadata']['total_categories'];
    }
}

if (!isset($department_clusters['error'])) {
    $department_insights = $clustering->getClusteringInsights($department_clusters, 'department');
    if (isset($department_clusters['metadata'])) {
        $data_sources['department'] = $department_clusters['metadata'];
        $total_real_data_items += $department_clusters['metadata']['total_subjects'];
    }
}

// Check if we have any data to display
$has_teacher_data = !empty($teacher_insights);
$has_pattern_data = !empty($pattern_insights);
$has_department_data = !empty($department_insights);
$has_any_data = $has_teacher_data || $has_pattern_data || $has_department_data;
?>

<!-- Clustering Analysis Section -->
<div class="bg-white shadow rounded-lg mb-6 sm:mb-8">
    <?php if (isset($_GET['semester_filter']) && !empty($_GET['semester_filter'])): ?>
    <!-- Filter Notice -->
    <div class="px-4 sm:px-6 py-3 bg-blue-50 border-b border-blue-200">
        <div class="flex items-center">
            <i class="fas fa-filter text-blue-600 mr-2"></i>
            <p class="text-sm text-blue-800">
                <strong>Filtered Analysis:</strong> This clustering analysis is based on the semester selected in the filter above.
                Change the filter to analyze different semesters.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base sm:text-lg font-medium text-gray-900">
                    <i class="fas fa-chart-pie text-seait-orange mr-2"></i>
                    AI-Powered Performance Clusters
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    Advanced clustering analysis based on
                    <span class="font-semibold text-seait-orange"><?php echo htmlspecialchars($semester_name); ?></span>
                    <?php if (isset($_GET['semester_filter']) && !empty($_GET['semester_filter'])): ?>
                    <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                        <i class="fas fa-filter mr-1"></i>Filtered
                    </span>
                    <?php endif; ?>
                </p>
                <?php if ($total_real_data_items > 0): ?>
                <p class="text-xs text-green-600 mt-1">
                    <i class="fas fa-database mr-1"></i>
                    Using real evaluation data from database (<?php echo $total_real_data_items; ?> items analyzed)
                </p>
                <?php else: ?>
                <p class="text-xs text-yellow-600 mt-1">
                    <i class="fas fa-info-circle mr-1"></i>
                    No evaluation data available for this semester. Please ensure you have:
                </p>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li>• At least 2 completed evaluations per teacher</li>
                    <li>• At least 5 responses per evaluation category</li>
                    <li>• At least 10 responses per subject/department</li>
                    <li>• Active semester with evaluation data</li>
                </ul>
                <?php endif; ?>
                <?php if (isset($_GET['semester_filter']) && !empty($_GET['semester_filter'])): ?>
                <p class="text-xs text-blue-600 mt-1">
                    <i class="fas fa-info-circle mr-1"></i>
                    Filtered by semester selection from the filter button above
                </p>
                <?php endif; ?>
            </div>
            <div class="text-right">
                <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">
                    Machine Learning
                </span>
                <?php if (isset($_GET['semester_filter']) && !empty($_GET['semester_filter'])): ?>
                <div class="mt-1">
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                        <i class="fas fa-filter mr-1"></i>Filtered
                    </span>
                </div>
                <?php elseif ($current_semester): ?>
                <div class="mt-1">
                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                        Active Semester
                    </span>
                </div>
                <?php endif; ?>
                <?php if ($total_real_data_items > 0): ?>
                <div class="mt-1">
                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                        <i class="fas fa-database mr-1"></i>Real Data
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="p-4 sm:p-6">
        <?php if (!$has_any_data): ?>
        <!-- No Data Available -->
        <div class="text-center py-8">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-chart-bar text-gray-400 text-2xl"></i>
            </div>
            <h4 class="text-lg font-medium text-gray-900 mb-2">No Clustering Data Available</h4>
            <p class="text-sm text-gray-600 mb-4">
                <?php if (!$current_semester): ?>
                Clustering analysis requires an active semester. Please set up an active semester first.
                <?php else: ?>
                Clustering analysis requires sufficient evaluation data. Please ensure you have:
                <?php endif; ?>
            </p>
            <?php if ($current_semester): ?>
            <ul class="text-sm text-gray-600 space-y-1 mb-6">
                <li>• At least 5 completed evaluations per teacher</li>
                <li>• At least 10 responses per evaluation category</li>
                <li>• At least 20 responses per subject/department</li>
                <li>• Active semester with evaluation data</li>
            </ul>
            <?php endif; ?>
            <div class="flex justify-center space-x-4">
                <?php if (!$current_semester): ?>
                <a href="semesters.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-seait-orange hover:bg-orange-600 transition">
                    <i class="fas fa-calendar-plus mr-2"></i>
                    Set Up Active Semester
                </a>
                <?php else: ?>
                <a href="evaluations.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-seait-orange hover:bg-orange-600 transition">
                    <i class="fas fa-clipboard-check mr-2"></i>
                    View Evaluations
                </a>
                <?php endif; ?>
                <a href="reports.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                    <i class="fas fa-chart-line mr-2"></i>
                    View Reports
                </a>
            </div>
        </div>
        <?php else: ?>

        <!-- Teacher Performance Clusters -->
        <?php if ($has_teacher_data): ?>
        <div class="mb-6">
            <h4 class="text-sm sm:text-base font-medium text-gray-900 mb-3">Teacher Performance Clusters</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($teacher_insights as $cluster_id => $insight): ?>
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h5 class="text-sm font-semibold text-blue-900"><?php echo htmlspecialchars($insight['label']); ?></h5>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                            <?php echo $insight['size']; ?> teachers
                        </span>
                    </div>
                    <p class="text-xs text-blue-700 mb-3"><?php echo htmlspecialchars($insight['description']); ?></p>
                    <div class="mb-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-600">Avg Rating:</span>
                            <span class="font-semibold text-blue-900"><?php echo $insight['avg_rating']; ?>/5.0</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <p class="text-xs font-medium text-blue-800">Recommendations:</p>
                        <ul class="text-xs text-blue-700 space-y-1">
                            <?php foreach (array_slice($insight['recommendations'], 0, 2) as $recommendation): ?>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-0.5 mr-2 text-xs"></i>
                                <?php echo htmlspecialchars($recommendation); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Evaluation Pattern Clusters -->
        <?php if ($has_pattern_data): ?>
        <div class="mb-6">
            <h4 class="text-sm sm:text-base font-medium text-gray-900 mb-3">Evaluation Category Patterns</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($pattern_insights as $cluster_id => $insight): ?>
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h5 class="text-sm font-semibold text-green-900"><?php echo htmlspecialchars($insight['label']); ?></h5>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                            <?php echo $insight['size']; ?> categories
                        </span>
                    </div>
                    <p class="text-xs text-green-700 mb-3"><?php echo htmlspecialchars($insight['description']); ?></p>
                    <div class="mb-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-600">Avg Rating:</span>
                            <span class="font-semibold text-green-900"><?php echo $insight['avg_rating']; ?>/5.0</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <p class="text-xs font-medium text-green-800">Actions:</p>
                        <ul class="text-xs text-green-700 space-y-1">
                            <?php foreach (array_slice($insight['recommendations'], 0, 2) as $recommendation): ?>
                            <li class="flex items-start">
                                <i class="fas fa-lightbulb text-yellow-500 mt-0.5 mr-2 text-xs"></i>
                                <?php echo htmlspecialchars($recommendation); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Department Performance Clusters -->
        <?php if ($has_department_data): ?>
        <div class="mb-6">
            <h4 class="text-sm sm:text-base font-medium text-gray-900 mb-3">Department Performance Analysis</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($department_insights as $cluster_id => $insight): ?>
                <div class="bg-gradient-to-br from-purple-50 to-violet-50 border border-purple-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h5 class="text-sm font-semibold text-purple-900"><?php echo htmlspecialchars($insight['label']); ?></h5>
                        <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">
                            <?php echo $insight['size']; ?> departments
                        </span>
                    </div>
                    <p class="text-xs text-purple-700 mb-3"><?php echo htmlspecialchars($insight['description']); ?></p>
                    <div class="mb-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-600">Avg Rating:</span>
                            <span class="font-semibold text-purple-900"><?php echo $insight['avg_rating']; ?>/5.0</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <p class="text-xs font-medium text-purple-800">Strategic Actions:</p>
                        <ul class="text-xs text-purple-700 space-y-1">
                            <?php foreach (array_slice($insight['recommendations'], 0, 2) as $recommendation): ?>
                            <li class="flex items-start">
                                <i class="fas fa-rocket text-purple-500 mt-0.5 mr-2 text-xs"></i>
                                <?php echo htmlspecialchars($recommendation); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Clustering Summary -->
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h5 class="text-sm font-medium text-gray-900">Clustering Analysis Summary</h5>
                    <p class="text-xs text-gray-600 mt-1">
                        <?php
                        $total_clusters = count($teacher_insights) + count($pattern_insights) + count($department_insights);
                        $total_items = 0;
                        foreach ($teacher_insights as $insight) $total_items += $insight['size'];
                        foreach ($pattern_insights as $insight) $total_items += $insight['size'];
                        foreach ($department_insights as $insight) $total_items += $insight['size'];
                        echo "Analyzed {$total_items} data points across {$total_clusters} performance clusters";
                        ?>
                        <?php if (isset($_GET['semester_filter']) && !empty($_GET['semester_filter'])): ?>
                        <span class="text-blue-600 font-medium"> (Filtered by selected semester)</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="text-right">
                    <div class="flex items-center space-x-2">
                        <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                        <span class="text-xs text-gray-600">High Performers</span>
                    </div>
                    <div class="flex items-center space-x-2 mt-1">
                        <div class="w-2 h-2 bg-yellow-500 rounded-full"></div>
                        <span class="text-xs text-gray-600">Needs Support</span>
                    </div>
                    <?php if (isset($_GET['semester_filter']) && !empty($_GET['semester_filter'])): ?>
                    <div class="flex items-center space-x-2 mt-1">
                        <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                        <span class="text-xs text-blue-600">Filtered Data</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-4 flex flex-wrap gap-2">
            <?php if ($has_teacher_data): ?>
            <a href="clustering_visualization.php?action=teacher_clusters&semester_id=<?php echo $semester_id; ?>"
               class="inline-flex items-center px-3 py-2 border border-transparent text-xs font-medium rounded-md text-white bg-seait-orange hover:bg-orange-600 transition">
                <i class="fas fa-users mr-1"></i>
                Detailed Teacher Analysis
            </a>
            <?php endif; ?>

            <?php if ($has_pattern_data): ?>
            <a href="clustering_visualization.php?action=pattern_clusters&semester_id=<?php echo $semester_id; ?>"
               class="inline-flex items-center px-3 py-2 border border-transparent text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition">
                <i class="fas fa-chart-line mr-1"></i>
                Pattern Analysis
            </a>
            <?php endif; ?>

            <?php if ($has_department_data): ?>
            <a href="clustering_visualization.php?action=department_clusters&semester_id=<?php echo $semester_id; ?>"
               class="inline-flex items-center px-3 py-2 border border-transparent text-xs font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 transition">
                <i class="fas fa-building mr-1"></i>
                Department Analysis
            </a>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </div>
</div>

<script>
// JavaScript for interactive clustering visualization
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to cluster cards
    const clusterCards = document.querySelectorAll('.bg-gradient-to-br');
    clusterCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.1)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });

    // Add click handlers for detailed analysis
    const actionButtons = document.querySelectorAll('a[href*="clustering_analysis.php"]');
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Add loading state
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Loading...';
            this.disabled = true;

            // Reset after a delay (in real implementation, this would be AJAX)
            setTimeout(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            }, 2000);
        });
    });
});
</script>