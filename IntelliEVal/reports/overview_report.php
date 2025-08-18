<!-- Overview Report -->
<div class="space-y-6">
    <!-- Key Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-full">
                    <i class="fas fa-chart-bar text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Evaluations</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_evaluations ?? 0); ?></p>
                    <?php if ($selected_semester > 0): ?>
                    <p class="text-xs text-blue-600"><?php echo number_format($semester_evaluations ?? 0); ?> in selected semester</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-full">
                    <i class="fas fa-user-tie text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Teachers Evaluated</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_teachers ?? 0); ?></p>
                    <?php if ($selected_semester > 0): ?>
                    <p class="text-xs text-green-600">Active in selected semester</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 bg-purple-100 rounded-full">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Students Participated</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_students ?? 0); ?></p>
                    <?php if ($selected_semester > 0): ?>
                    <p class="text-xs text-purple-600">Active in selected semester</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 bg-orange-100 rounded-full">
                    <i class="fas fa-star text-orange-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Average Rating</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($avg_rating ?? 0, 2); ?>/5.0</p>
                    <?php if ($selected_semester > 0): ?>
                    <p class="text-xs text-orange-600">Semester average</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Clustering Summary -->
    <?php if (!empty($teacher_insights) || !empty($pattern_insights) || !empty($department_insights)): ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-brain text-seait-orange mr-2"></i>
            AI-Powered Clustering Summary
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php if (!empty($teacher_insights)): ?>
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-blue-900 mb-3">Teacher Performance Clusters</h4>
                <div class="space-y-2">
                    <?php foreach ($teacher_insights as $insight): ?>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-blue-700"><?php echo htmlspecialchars($insight['label'] ?? 'Unknown'); ?></span>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                            <?php echo $insight['size'] ?? 0; ?> teachers
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($pattern_insights)): ?>
            <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-green-900 mb-3">Evaluation Patterns</h4>
                <div class="space-y-2">
                    <?php foreach ($pattern_insights as $insight): ?>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-green-700"><?php echo htmlspecialchars($insight['label'] ?? 'Unknown'); ?></span>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                            <?php echo $insight['size'] ?? 0; ?> categories
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($department_insights)): ?>
            <div class="bg-gradient-to-br from-purple-50 to-violet-50 border border-purple-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-purple-900 mb-3">Department Performance</h4>
                <div class="space-y-2">
                    <?php foreach ($department_insights as $insight): ?>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-purple-700"><?php echo htmlspecialchars($insight['label'] ?? 'Unknown'); ?></span>
                        <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">
                            <?php echo $insight['size'] ?? 0; ?> departments
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Performing Teachers -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-trophy text-yellow-600 mr-2"></i>
            Top Performing Teachers
            <?php if ($selected_semester > 0): ?>
            <span class="text-sm font-normal text-gray-500">(Semester Filtered)</span>
            <?php endif; ?>
        </h3>
        <?php if ($top_teachers_result && mysqli_num_rows($top_teachers_result) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Rating</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Responses</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $rank = 1;
                    while($teacher = mysqli_fetch_assoc($top_teachers_result)):
                        $avg_rating = $teacher['avg_rating'] ?? 0;
                        $performance_class = $avg_rating >= 4.5 ? 'bg-green-100 text-green-800' :
                                           ($avg_rating >= 4.0 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                        $performance_text = $avg_rating >= 4.5 ? 'Excellent' :
                                          ($avg_rating >= 4.0 ? 'Good' : 'Needs Improvement');
                    ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            #<?php echo $rank; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($teacher['teacher_name'] ?? 'Unknown Teacher'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <span class="font-semibold"><?php echo number_format($avg_rating, 2); ?>/5.0</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo number_format($teacher['total_responses'] ?? 0); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full font-medium <?php echo $performance_class; ?>">
                                <?php echo $performance_text; ?>
                            </span>
                        </td>
                    </tr>
                    <?php
                        $rank++;
                    endwhile;
                    ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-8">
            <i class="fas fa-info-circle text-gray-400 text-4xl mb-4"></i>
            <p class="text-gray-600">No teacher performance data available for the selected criteria.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-clock text-blue-600 mr-2"></i>
            Recent Evaluation Activity
            <?php if ($selected_semester > 0): ?>
            <span class="text-sm font-normal text-gray-500">(Semester Filtered)</span>
            <?php endif; ?>
        </h3>
        <?php if ($recent_evaluations_result && mysqli_num_rows($recent_evaluations_result) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluator</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while($evaluation = mysqli_fetch_assoc($recent_evaluations_result)): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($evaluation['evaluator_name'] ?? 'Unknown Evaluator'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($evaluation['teacher_name'] ?? 'Unknown Teacher'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($evaluation['subject_name'] ?? 'Subject not specified'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded <?php
                                $evaluator_type = $evaluation['evaluator_type'] ?? '';
                                echo $evaluator_type == 'student' ? 'bg-blue-100 text-blue-800' :
                                    ($evaluator_type == 'teacher' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800');
                            ?>">
                                <?php echo ucwords($evaluator_type ?: 'Unknown'); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php
                            $display_date = $evaluation['display_date'] ?? $evaluation['evaluation_date'] ?? $evaluation['created_at'];
                            echo $display_date ? date('M d, Y', strtotime($display_date)) : 'Date not available';
                            ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if (isset($total_pages) && $total_pages > 1): ?>
        <div class="mt-6 flex items-center justify-between">
            <div class="text-sm text-gray-700">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_count ?? 0); ?> of <?php echo $total_count ?? 0; ?> evaluations
            </div>
            <div class="flex items-center space-x-2">
                <?php if ($page > 1): ?>
                <a href="?report_type=overview&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>&page=<?php echo $page - 1; ?>"
                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    <i class="fas fa-chevron-left mr-1"></i>Previous
                </a>
                <?php endif; ?>

                <div class="flex items-center space-x-1">
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1): ?>
                    <a href="?report_type=overview&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>&page=1"
                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">1</a>
                    <?php if ($start_page > 2): ?>
                    <span class="px-2 py-2 text-sm text-gray-500">...</span>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?report_type=overview&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>&page=<?php echo $i; ?>"
                       class="px-3 py-2 text-sm font-medium <?php echo $i == $page ? 'text-white bg-seait-orange border border-seait-orange' : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-md">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                    <span class="px-2 py-2 text-sm text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?report_type=overview&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>&page=<?php echo $total_pages; ?>"
                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50"><?php echo $total_pages; ?></a>
                    <?php endif; ?>
                </div>

                <?php if ($page < $total_pages): ?>
                <a href="?report_type=overview&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>&page=<?php echo $page + 1; ?>"
                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Next<i class="fas fa-chevron-right ml-1"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="text-center py-8">
            <i class="fas fa-info-circle text-gray-400 text-4xl mb-4"></i>
            <p class="text-gray-600">No recent evaluation activity found for the selected criteria.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-bolt text-seait-orange mr-2"></i>
            Quick Actions
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="?report_type=clustering&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
               class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                <i class="fas fa-brain text-blue-600 mr-3 text-xl"></i>
                <div>
                    <p class="font-medium text-gray-900">Clustering Analysis</p>
                    <p class="text-sm text-gray-600">AI-powered insights</p>
                </div>
            </a>
            <a href="?report_type=performance&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
               class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                <i class="fas fa-trophy text-green-600 mr-3 text-xl"></i>
                <div>
                    <p class="font-medium text-gray-900">Performance Metrics</p>
                    <p class="text-sm text-gray-600">Detailed analytics</p>
                </div>
            </a>
            <a href="?report_type=training&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
               class="flex items-center p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors">
                <i class="fas fa-graduation-cap text-orange-600 mr-3 text-xl"></i>
                <div>
                    <p class="font-medium text-gray-900">Training Needs</p>
                    <p class="text-sm text-gray-600">Development areas</p>
                </div>
            </a>
            <a href="clustering_visualization.php"
               class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                <i class="fas fa-chart-line text-purple-600 mr-3 text-xl"></i>
                <div>
                    <p class="font-medium text-gray-900">Visualization</p>
                    <p class="text-sm text-gray-600">Interactive charts</p>
                </div>
            </a>
        </div>
    </div>
</div>