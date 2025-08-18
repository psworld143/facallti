<!-- Detailed Reports -->
<div class="space-y-6">
    <!-- Detailed Analytics Overview -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-line text-seait-orange mr-2"></i>
            Detailed Analytics Overview
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-blue-900">Evaluation Sessions</h4>
                    <i class="fas fa-clipboard-check text-blue-600"></i>
                </div>
                <p class="text-2xl font-bold text-blue-900"><?php echo number_format($total_evaluations); ?></p>
                <p class="text-xs text-blue-700 mt-1">Total completed sessions</p>
            </div>

            <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-green-900">Subjects Evaluated</h4>
                    <i class="fas fa-book text-green-600"></i>
                </div>
                <p class="text-2xl font-bold text-green-900"><?php echo number_format($total_subjects); ?></p>
                <p class="text-xs text-green-700 mt-1">Unique subjects covered</p>
            </div>

            <div class="bg-gradient-to-br from-purple-50 to-violet-50 border border-purple-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-purple-900">Response Rate</h4>
                    <i class="fas fa-percentage text-purple-600"></i>
                </div>
                <p class="text-2xl font-bold text-purple-900">
                    <?php
                    $response_rate = $total_evaluations > 0 ? ($total_evaluations / ($total_teachers * 10)) * 100 : 0;
                    echo number_format(min($response_rate, 100), 1);
                    ?>%
                </p>
                <p class="text-xs text-purple-700 mt-1">Average participation rate</p>
            </div>

            <div class="bg-gradient-to-br from-orange-50 to-red-50 border border-orange-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-orange-900">Data Quality</h4>
                    <i class="fas fa-database text-orange-600"></i>
                </div>
                <p class="text-2xl font-bold text-orange-900">
                    <?php
                    $data_quality = $avg_rating > 0 ? min(100, ($avg_rating / 5) * 100) : 0;
                    echo number_format($data_quality, 1);
                    ?>%
                </p>
                <p class="text-xs text-orange-700 mt-1">Overall data completeness</p>
            </div>
        </div>
    </div>

    <!-- Detailed Teacher Performance -->
    <?php
    // Get detailed teacher performance data
    $detailed_teachers_query = "SELECT
        es.evaluatee_id,
        CONCAT(COALESCE(f.first_name, u.first_name), ' ', COALESCE(f.last_name, u.last_name)) as teacher_name,
        AVG(er.rating_value) as avg_rating,
        COUNT(er.id) as total_responses,
        COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
        COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
        COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
        COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
        COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count,
        COUNT(CASE WHEN er.text_response IS NOT NULL AND er.text_response != '' THEN 1 END) as text_responses
    FROM evaluation_sessions es
    INNER JOIN evaluation_responses er ON es.id = er.evaluation_session_id
    LEFT JOIN faculty f ON es.evaluatee_id = f.id
    LEFT JOIN users u ON es.evaluatee_id = u.id
    WHERE es.evaluatee_type = 'teacher' AND er.rating_value IS NOT NULL";

    if ($selected_semester > 0) {
        $detailed_teachers_query .= " AND es.semester_id = " . $selected_semester;
    }

    $detailed_teachers_query .= " GROUP BY es.evaluatee_id, f.first_name, f.last_name, u.first_name, u.last_name
                                 HAVING COUNT(er.id) >= 5
                                 ORDER BY avg_rating DESC";
    $detailed_teachers_result = mysqli_query($conn, $detailed_teachers_query);
    ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-user-tie text-blue-600 mr-2"></i>
            Detailed Teacher Performance Analysis
            <?php if ($selected_semester > 0): ?>
            <span class="text-sm font-normal text-gray-500">(Semester Filtered)</span>
            <?php endif; ?>
        </h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Rating</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Responses</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating Distribution</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Text Responses</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance Level</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while($teacher = mysqli_fetch_assoc($detailed_teachers_result)):
                        $excellent_pct = ($teacher['excellent_count'] / $teacher['total_responses']) * 100;
                        $very_satisfactory_pct = ($teacher['very_satisfactory_count'] / $teacher['total_responses']) * 100;
                        $satisfactory_pct = ($teacher['satisfactory_count'] / $teacher['total_responses']) * 100;
                        $good_pct = ($teacher['good_count'] / $teacher['total_responses']) * 100;
                        $poor_pct = ($teacher['poor_count'] / $teacher['total_responses']) * 100;
                        $text_pct = ($teacher['text_responses'] / $teacher['total_responses']) * 100;

                        $performance_class = $teacher['avg_rating'] >= 4.5 ? 'bg-green-100 text-green-800' :
                                           ($teacher['avg_rating'] >= 4.0 ? 'bg-yellow-100 text-yellow-800' :
                                           ($teacher['avg_rating'] >= 3.5 ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800'));
                        $performance_text = $teacher['avg_rating'] >= 4.5 ? 'Excellent' :
                                          ($teacher['avg_rating'] >= 4.0 ? 'Good' :
                                          ($teacher['avg_rating'] >= 3.5 ? 'Satisfactory' : 'Needs Improvement'));
                    ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($teacher['teacher_name'] ?? 'Unknown Teacher'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <span class="text-yellow-500 text-sm mr-2">
                                    <?php echo str_repeat('★', round($teacher['avg_rating'])) . str_repeat('☆', 5 - round($teacher['avg_rating'])); ?>
                                </span>
                                <span class="font-semibold text-gray-900"><?php echo number_format($teacher['avg_rating'], 2); ?>/5.0</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo number_format($teacher['total_responses']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-1">
                                <div class="w-8 h-2 bg-green-500 rounded" title="Excellent (<?php echo number_format($excellent_pct, 1); ?>%)"></div>
                                <div class="w-8 h-2 bg-blue-500 rounded" title="Very Satisfactory (<?php echo number_format($very_satisfactory_pct, 1); ?>%)"></div>
                                <div class="w-8 h-2 bg-yellow-500 rounded" title="Satisfactory (<?php echo number_format($satisfactory_pct, 1); ?>%)"></div>
                                <div class="w-8 h-2 bg-orange-500 rounded" title="Good (<?php echo number_format($good_pct, 1); ?>%)"></div>
                                <div class="w-8 h-2 bg-red-500 rounded" title="Poor (<?php echo number_format($poor_pct, 1); ?>%)"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                <?php echo number_format($excellent_pct, 1); ?>% | <?php echo number_format($very_satisfactory_pct, 1); ?>% | <?php echo number_format($satisfactory_pct, 1); ?>% | <?php echo number_format($good_pct, 1); ?>% | <?php echo number_format($poor_pct, 1); ?>%
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <div class="flex items-center">
                                <span class="font-medium"><?php echo number_format($teacher['text_responses']); ?></span>
                                <span class="text-gray-500 ml-1">(<?php echo number_format($text_pct, 1); ?>%)</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full font-medium <?php echo $performance_class; ?>">
                                <?php echo $performance_text; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Category Performance Analysis -->
    <?php
    // Get category performance data
    $category_performance_query = "SELECT
        mec.name as category_name,
        esc.name as subcategory_name,
        AVG(er.rating_value) as avg_rating,
        COUNT(er.id) as total_responses,
        COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
        COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
        COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
        COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
        COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count
    FROM evaluation_responses er
    JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
    JOIN evaluation_questionnaires eq ON er.questionnaire_id = eq.id
    JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
    JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
    WHERE er.rating_value IS NOT NULL";

    if ($selected_semester > 0) {
        $category_performance_query .= " AND es.semester_id = " . $selected_semester;
    }

    $category_performance_query .= " GROUP BY mec.id, mec.name, esc.id, esc.name
                                    HAVING COUNT(er.id) >= 5
                                    ORDER BY avg_rating DESC";
    $category_performance_result = mysqli_query($conn, $category_performance_query);
    ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-list-alt text-green-600 mr-2"></i>
            Category Performance Analysis
            <?php if ($selected_semester > 0): ?>
            <span class="text-sm font-normal text-gray-500">(Semester Filtered)</span>
            <?php endif; ?>
        </h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subcategory</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Rating</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Responses</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while($category = mysqli_fetch_assoc($category_performance_result)):
                        $excellent_pct = ($category['excellent_count'] / $category['total_responses']) * 100;
                        $very_satisfactory_pct = ($category['very_satisfactory_count'] / $category['total_responses']) * 100;
                        $satisfactory_pct = ($category['satisfactory_count'] / $category['total_responses']) * 100;
                        $good_pct = ($category['good_count'] / $category['total_responses']) * 100;
                        $poor_pct = ($category['poor_count'] / $category['total_responses']) * 100;

                        $performance_class = $category['avg_rating'] >= 4.5 ? 'bg-green-100 text-green-800' :
                                           ($category['avg_rating'] >= 4.0 ? 'bg-yellow-100 text-yellow-800' :
                                           ($category['avg_rating'] >= 3.5 ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800'));
                        $performance_text = $category['avg_rating'] >= 4.5 ? 'Excellent' :
                                          ($category['avg_rating'] >= 4.0 ? 'Good' :
                                          ($category['avg_rating'] >= 3.5 ? 'Satisfactory' : 'Needs Improvement'));
                    ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($category['subcategory_name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <span class="text-yellow-500 text-sm mr-2">
                                    <?php echo str_repeat('★', round($category['avg_rating'])) . str_repeat('☆', 5 - round($category['avg_rating'])); ?>
                                </span>
                                <span class="font-semibold text-gray-900"><?php echo number_format($category['avg_rating'], 2); ?>/5.0</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo number_format($category['total_responses']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-1">
                                <div class="w-6 h-2 bg-green-500 rounded" title="Excellent (<?php echo number_format($excellent_pct, 1); ?>%)"></div>
                                <div class="w-6 h-2 bg-blue-500 rounded" title="Very Satisfactory (<?php echo number_format($very_satisfactory_pct, 1); ?>%)"></div>
                                <div class="w-6 h-2 bg-yellow-500 rounded" title="Satisfactory (<?php echo number_format($satisfactory_pct, 1); ?>%)"></div>
                                <div class="w-6 h-2 bg-orange-500 rounded" title="Good (<?php echo number_format($good_pct, 1); ?>%)"></div>
                                <div class="w-6 h-2 bg-red-500 rounded" title="Poor (<?php echo number_format($poor_pct, 1); ?>%)"></div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full font-medium <?php echo $performance_class; ?>">
                                <?php echo $performance_text; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Activity Timeline -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-clock text-purple-600 mr-2"></i>
            Recent Evaluation Activity Timeline
            <?php if ($selected_semester > 0): ?>
            <span class="text-sm font-normal text-gray-500">(Semester Filtered)</span>
            <?php endif; ?>
        </h3>
        <div class="space-y-4">
            <?php
            mysqli_data_seek($recent_evaluations_result, 0);
            while($evaluation = mysqli_fetch_assoc($recent_evaluations_result)):
            ?>
            <div class="flex items-start space-x-4 p-4 bg-gray-50 rounded-lg">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-white text-sm"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-900">
                            <span class="font-semibold"><?php echo htmlspecialchars($evaluation['evaluator_name'] ?? 'Unknown Evaluator'); ?></span>
                            evaluated
                            <span class="font-semibold"><?php echo htmlspecialchars($evaluation['teacher_name'] ?? 'Unknown Teacher'); ?></span>
                        </p>
                        <span class="text-xs text-gray-500">
                            <?php
                            $display_date = $evaluation['display_date'] ?? $evaluation['evaluation_date'] ?? $evaluation['created_at'];
                            echo $display_date ? date('M d, Y', strtotime($display_date)) : 'Date not available';
                            ?>
                        </span>
                    </div>
                    <div class="mt-1 flex items-center space-x-4 text-xs text-gray-600">
                        <span>
                            <i class="fas fa-book mr-1"></i>
                            <?php echo htmlspecialchars($evaluation['subject_name'] ?? 'Subject not specified'); ?>
                        </span>
                        <span>
                            <i class="fas fa-user mr-1"></i>
                            <?php echo ucwords($evaluation['evaluator_type'] ?? 'Unknown'); ?>
                        </span>
                        <span>
                            <i class="fas fa-calendar mr-1"></i>
                            <?php echo htmlspecialchars($evaluation['semester_name'] ?? 'Semester not specified'); ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Data Quality Metrics -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-database text-indigo-600 mr-2"></i>
            Data Quality Metrics
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-blue-900 mb-2">Completeness</h4>
                <div class="flex items-center justify-between">
                    <span class="text-2xl font-bold text-blue-900">
                        <?php
                        $completeness = $total_evaluations > 0 ? min(100, ($total_evaluations / ($total_teachers * 5)) * 100) : 0;
                        echo number_format($completeness, 1);
                        ?>%
                    </span>
                    <i class="fas fa-check-circle text-blue-600"></i>
                </div>
                <p class="text-xs text-blue-700 mt-1">Data completeness rate</p>
            </div>

            <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-green-900 mb-2">Consistency</h4>
                <div class="flex items-center justify-between">
                    <span class="text-2xl font-bold text-green-900">
                        <?php
                        $consistency = $avg_rating > 0 ? min(100, (1 - abs($avg_rating - 4.0) / 4.0) * 100) : 0;
                        echo number_format($consistency, 1);
                        ?>%
                    </span>
                    <i class="fas fa-balance-scale text-green-600"></i>
                </div>
                <p class="text-xs text-green-700 mt-1">Rating consistency</p>
            </div>

            <div class="bg-gradient-to-br from-purple-50 to-violet-50 border border-purple-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-purple-900 mb-2">Reliability</h4>
                <div class="flex items-center justify-between">
                    <span class="text-2xl font-bold text-purple-900">
                        <?php
                        $reliability = $total_evaluations > 0 ? min(100, ($total_evaluations / 100) * 100) : 0;
                        echo number_format($reliability, 1);
                        ?>%
                    </span>
                    <i class="fas fa-shield-alt text-purple-600"></i>
                </div>
                <p class="text-xs text-purple-700 mt-1">Data reliability score</p>
            </div>

            <div class="bg-gradient-to-br from-orange-50 to-red-50 border border-orange-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-orange-900 mb-2">Validity</h4>
                <div class="flex items-center justify-between">
                    <span class="text-2xl font-bold text-orange-900">
                        <?php
                        $validity = $avg_rating > 0 ? min(100, ($avg_rating / 5) * 100) : 0;
                        echo number_format($validity, 1);
                        ?>%
                    </span>
                    <i class="fas fa-certificate text-orange-600"></i>
                </div>
                <p class="text-xs text-orange-700 mt-1">Data validity score</p>
            </div>
        </div>
    </div>
</div>