<!-- Clustering Analysis Report -->
<div class="space-y-6">
    <!-- Clustering Overview -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-brain text-seait-orange mr-2"></i>
            AI-Powered Clustering Analysis
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-blue-900">Teacher Performance</h4>
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                        <?php echo !empty($teacher_insights) ? count($teacher_insights) : 0; ?> clusters
                    </span>
                </div>
                <p class="text-xs text-blue-700 mb-3">K-means clustering based on performance metrics</p>
                <div class="space-y-2">
                    <?php if (!empty($teacher_insights)): ?>
                        <?php foreach ($teacher_insights as $insight): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-blue-700"><?php echo htmlspecialchars($insight['label']); ?></span>
                            <span class="text-xs font-medium text-blue-900"><?php echo $insight['size']; ?> teachers</span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-xs text-blue-600">No clustering data available</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-green-900">Evaluation Patterns</h4>
                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                        <?php echo !empty($pattern_insights) ? count($pattern_insights) : 0; ?> clusters
                    </span>
                </div>
                <p class="text-xs text-green-700 mb-3">Category-based evaluation analysis</p>
                <div class="space-y-2">
                    <?php if (!empty($pattern_insights)): ?>
                        <?php foreach ($pattern_insights as $insight): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-green-700"><?php echo htmlspecialchars($insight['label']); ?></span>
                            <span class="text-xs font-medium text-green-900"><?php echo $insight['size']; ?> categories</span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-xs text-green-600">No clustering data available</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-gradient-to-br from-purple-50 to-violet-50 border border-purple-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-purple-900">Department Performance</h4>
                    <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">
                        <?php echo !empty($department_insights) ? count($department_insights) : 0; ?> clusters
                    </span>
                </div>
                <p class="text-xs text-purple-700 mb-3">Subject/department performance analysis</p>
                <div class="space-y-2">
                    <?php if (!empty($department_insights)): ?>
                        <?php foreach ($department_insights as $insight): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-purple-700"><?php echo htmlspecialchars($insight['label']); ?></span>
                            <span class="text-xs font-medium text-purple-900"><?php echo $insight['size']; ?> departments</span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-xs text-purple-600">No clustering data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Clustering Algorithm Info -->
        <div class="bg-gray-50 rounded-lg p-4">
            <h5 class="text-sm font-medium text-gray-900 mb-2">Clustering Algorithm Details</h5>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs text-gray-600">
                <div>
                    <span class="font-medium">Algorithm:</span> K-means Clustering
                </div>
                <div>
                    <span class="font-medium">Distance Metric:</span> Euclidean Distance
                </div>
                <div>
                    <span class="font-medium">Initialization:</span> Random Centroids
                </div>
                <div>
                    <span class="font-medium">Max Iterations:</span> 100
                </div>
            </div>
        </div>
    </div>

    <!-- Teacher Performance Clusters -->
    <?php if (!empty($teacher_insights)): ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-user-tie text-blue-600 mr-2"></i>
            Teacher Performance Clusters
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach ($teacher_insights as $cluster_id => $insight): ?>
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-blue-900"><?php echo htmlspecialchars($insight['label']); ?></h4>
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                        <?php echo $insight['size']; ?> teachers
                    </span>
                </div>
                <p class="text-xs text-blue-700 mb-3"><?php echo htmlspecialchars($insight['description']); ?></p>
                <div class="mb-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-600">Avg Rating:</span>
                        <span class="font-semibold text-blue-900"><?php echo number_format($insight['avg_rating'], 2); ?>/5.0</span>
                    </div>
                </div>

                <!-- Show actual teachers in this cluster -->
                <?php if (isset($teacher_clusters[$cluster_id]['data']) && !empty($teacher_clusters[$cluster_id]['data'])): ?>
                <div class="mb-3">
                    <p class="text-xs font-medium text-blue-800 mb-2">Teachers in this cluster:</p>
                    <div class="space-y-1 max-h-24 overflow-y-auto">
                        <?php foreach ($teacher_clusters[$cluster_id]['data'] as $teacher): ?>
                        <div class="text-xs text-blue-700 bg-blue-100 px-2 py-1 rounded">
                            <div class="font-medium"><?php echo htmlspecialchars($teacher['name']); ?></div>
                            <div class="text-blue-600"><?php echo number_format($teacher['avg_rating'], 2); ?>/5.0</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="space-y-2">
                    <p class="text-xs font-medium text-blue-800">Recommendations:</p>
                    <ul class="text-xs text-blue-700 space-y-1">
                        <?php foreach (array_slice($insight['recommendations'], 0, 3) as $recommendation): ?>
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
    <?php if (!empty($pattern_insights)): ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-line text-green-600 mr-2"></i>
            Evaluation Category Patterns
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($pattern_insights as $cluster_id => $insight): ?>
            <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-green-900"><?php echo htmlspecialchars($insight['label']); ?></h4>
                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                        <?php echo $insight['size']; ?> categories
                    </span>
                </div>
                <p class="text-xs text-green-700 mb-3"><?php echo htmlspecialchars($insight['description']); ?></p>
                <div class="mb-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-600">Avg Rating:</span>
                        <span class="font-semibold text-green-900"><?php echo number_format($insight['avg_rating'], 2); ?>/5.0</span>
                    </div>
                </div>

                <!-- Show actual categories in this cluster -->
                <?php if (isset($pattern_clusters[$cluster_id]['data']) && !empty($pattern_clusters[$cluster_id]['data'])): ?>
                <div class="mb-3">
                    <p class="text-xs font-medium text-green-800 mb-2">Categories in this cluster:</p>
                    <div class="space-y-1 max-h-24 overflow-y-auto">
                        <?php foreach ($pattern_clusters[$cluster_id]['data'] as $category): ?>
                        <div class="text-xs text-green-700 bg-green-100 px-2 py-1 rounded">
                            <div class="font-medium"><?php echo htmlspecialchars($category['category']); ?></div>
                            <div class="text-green-600"><?php echo htmlspecialchars($category['subcategory']); ?></div>
                            <div class="text-green-500"><?php echo number_format($category['avg_rating'], 2); ?>/5.0</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

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
    <?php if (!empty($department_insights)): ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-building text-purple-600 mr-2"></i>
            Department Performance Analysis
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach ($department_insights as $cluster_id => $insight): ?>
            <div class="bg-gradient-to-br from-purple-50 to-violet-50 border border-purple-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-purple-900"><?php echo htmlspecialchars($insight['label']); ?></h4>
                    <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">
                        <?php echo $insight['size']; ?> departments
                    </span>
                </div>
                <p class="text-xs text-purple-700 mb-3"><?php echo htmlspecialchars($insight['description']); ?></p>
                <div class="mb-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-600">Avg Rating:</span>
                        <span class="font-semibold text-purple-900"><?php echo number_format($insight['avg_rating'], 2); ?>/5.0</span>
                    </div>
                </div>

                <!-- Show actual departments/subjects in this cluster -->
                <?php if (isset($department_clusters[$cluster_id]['data']) && !empty($department_clusters[$cluster_id]['data'])): ?>
                <div class="mb-3">
                    <p class="text-xs font-medium text-purple-800 mb-2">Departments/Subjects in this cluster:</p>
                    <div class="space-y-1 max-h-24 overflow-y-auto">
                        <?php foreach ($department_clusters[$cluster_id]['data'] as $department): ?>
                        <div class="text-xs text-purple-700 bg-purple-100 px-2 py-1 rounded">
                            <div class="font-medium"><?php echo htmlspecialchars($department['subject_name']); ?></div>
                            <div class="text-purple-600"><?php echo htmlspecialchars($department['subject_code']); ?></div>
                            <div class="text-purple-500"><?php echo number_format($department['avg_rating'], 2); ?>/5.0</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

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

    <!-- Clustering Insights Summary -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-lightbulb text-yellow-600 mr-2"></i>
            Clustering Insights Summary
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-yellow-900 mb-3">Key Findings</h4>
                <ul class="text-sm text-yellow-800 space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-yellow-600 mt-1 mr-2"></i>
                        <span>Performance patterns identified across <?php echo count($teacher_insights) + count($pattern_insights) + count($department_insights); ?> clusters</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-yellow-600 mt-1 mr-2"></i>
                        <span>Data-driven insights for targeted improvement strategies</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-yellow-600 mt-1 mr-2"></i>
                        <span>Automated recommendations based on cluster characteristics</span>
                    </li>
                </ul>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-blue-900 mb-3">Next Steps</h4>
                <ul class="text-sm text-blue-800 space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right text-blue-600 mt-1 mr-2"></i>
                        <span>Review detailed cluster analysis for specific insights</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right text-blue-600 mt-1 mr-2"></i>
                        <span>Implement recommended actions for each cluster</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right text-blue-600 mt-1 mr-2"></i>
                        <span>Monitor progress and adjust strategies accordingly</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-cogs text-seait-orange mr-2"></i>
            Detailed Analysis Options
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php if (!empty($teacher_insights)): ?>
            <a href="clustering_visualization.php?action=teacher_clusters&semester_id=<?php echo $selected_semester; ?>"
               class="flex items-center justify-center p-4 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">
                <i class="fas fa-users text-blue-600 mr-3"></i>
                <span>Detailed Teacher Analysis</span>
            </a>
            <?php endif; ?>

            <?php if (!empty($pattern_insights)): ?>
            <a href="clustering_visualization.php?action=pattern_clusters&semester_id=<?php echo $selected_semester; ?>"
               class="flex items-center justify-center p-4 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors">
                <i class="fas fa-chart-line text-green-600 mr-3"></i>
                <span>Pattern Analysis</span>
            </a>
            <?php endif; ?>

            <?php if (!empty($department_insights)): ?>
            <a href="clustering_visualization.php?action=department_clusters&semester_id=<?php echo $selected_semester; ?>"
               class="flex items-center justify-center p-4 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-colors">
                <i class="fas fa-building text-purple-600 mr-3"></i>
                <span>Department Analysis</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>