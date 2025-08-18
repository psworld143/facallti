<!-- Training Needs Report -->
<div class="space-y-6">
    <!-- Training Overview -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-graduation-cap text-orange-600 mr-2"></i>
            Training Needs Analysis
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-gradient-to-br from-red-50 to-pink-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-red-900">Critical Areas</h4>
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
                <p class="text-2xl font-bold text-red-900">
                    <?php
                    $critical_count = 0;
                    mysqli_data_seek($training_needs_result, 0);
                    while ($row = mysqli_fetch_assoc($training_needs_result)) {
                        if ($row['avg_rating'] < 3.5) $critical_count++;
                    }
                    echo $critical_count;
                    ?>
                </p>
                <p class="text-xs text-red-700 mt-1">Categories needing immediate attention</p>
            </div>

            <div class="bg-gradient-to-br from-yellow-50 to-orange-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-yellow-900">Improvement Areas</h4>
                    <i class="fas fa-lightbulb text-yellow-600"></i>
                </div>
                <p class="text-2xl font-bold text-yellow-900">
                    <?php
                    $improvement_count = 0;
                    mysqli_data_seek($training_needs_result, 0);
                    while ($row = mysqli_fetch_assoc($training_needs_result)) {
                        if ($row['avg_rating'] >= 3.5 && $row['avg_rating'] < 4.0) $improvement_count++;
                    }
                    echo $improvement_count;
                    ?>
                </p>
                <p class="text-xs text-yellow-700 mt-1">Categories with room for improvement</p>
            </div>

            <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-green-900">Strong Areas</h4>
                    <i class="fas fa-check-circle text-green-600"></i>
                </div>
                <p class="text-2xl font-bold text-green-900">
                    <?php
                    $strong_count = 0;
                    mysqli_data_seek($training_needs_result, 0);
                    while ($row = mysqli_fetch_assoc($training_needs_result)) {
                        if ($row['avg_rating'] >= 4.0) $strong_count++;
                    }
                    echo $strong_count;
                    ?>
                </p>
                <p class="text-xs text-green-700 mt-1">Categories performing well</p>
            </div>
        </div>
    </div>

    <!-- Top Training Needs -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
            Priority Training Areas
            <?php if ($selected_semester > 0): ?>
            <span class="text-sm font-normal text-gray-500">(Semester Filtered)</span>
            <?php endif; ?>
        </h3>

        <?php if (mysqli_num_rows($training_needs_result) > 0): ?>
        <div class="space-y-4">
            <?php
            $rank = 1;
            mysqli_data_seek($training_needs_result, 0);
            while ($need = mysqli_fetch_assoc($training_needs_result)):
                $improvement_needed_pct = ($need['low_ratings'] / $need['total_responses']) * 100;
                $priority_class = $need['avg_rating'] < 3.5 ? 'border-red-200 bg-red-50' :
                                ($need['avg_rating'] < 4.0 ? 'border-yellow-200 bg-yellow-50' : 'border-green-200 bg-green-50');
                $priority_color = $need['avg_rating'] < 3.5 ? 'text-red-800 bg-red-100' :
                                ($need['avg_rating'] < 4.0 ? 'text-yellow-800 bg-yellow-100' : 'text-green-800 bg-green-100');
                $priority_text = $need['avg_rating'] < 3.5 ? 'Critical' :
                               ($need['avg_rating'] < 4.0 ? 'High' : 'Medium');
            ?>
            <div class="border rounded-lg p-4 <?php echo $priority_class; ?>">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center">
                        <span class="w-8 h-8 bg-red-100 text-red-800 text-sm font-bold rounded-full flex items-center justify-center mr-3">
                            <?php echo $rank; ?>
                        </span>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">
                                <?php echo htmlspecialchars($need['subcategory_name']); ?>
                            </h4>
                            <p class="text-xs text-gray-600">
                                <?php echo htmlspecialchars($need['category_name']); ?>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="px-3 py-1 <?php echo $priority_color; ?> text-xs rounded-full font-medium">
                            <?php echo $priority_text; ?> Priority
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-3">
                    <div class="text-center">
                        <p class="text-xs text-gray-600">Average Rating</p>
                        <p class="text-lg font-bold <?php echo $need['avg_rating'] < 3.5 ? 'text-red-600' : ($need['avg_rating'] < 4.0 ? 'text-yellow-600' : 'text-green-600'); ?>">
                            <?php echo number_format($need['avg_rating'], 2); ?>/5.0
                        </p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-600">Total Responses</p>
                        <p class="text-lg font-bold text-gray-900"><?php echo number_format($need['total_responses']); ?></p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-600">Low Ratings</p>
                        <p class="text-lg font-bold text-red-600"><?php echo number_format($need['low_ratings']); ?></p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-600">Improvement Needed</p>
                        <p class="text-lg font-bold text-red-600"><?php echo number_format($improvement_needed_pct, 1); ?>%</p>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                        <span>Performance Level</span>
                        <span><?php echo number_format($improvement_needed_pct, 1); ?>% need improvement</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-red-500 h-2 rounded-full" style="width: <?php echo $improvement_needed_pct; ?>%"></div>
                    </div>
                </div>

                <div class="bg-white rounded-lg p-3 border">
                    <h5 class="text-xs font-medium text-gray-900 mb-2">Recommended Training Focus:</h5>
                    <ul class="text-xs text-gray-700 space-y-1">
                        <?php if ($need['avg_rating'] < 3.5): ?>
                        <li class="flex items-start">
                            <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-2"></i>
                            <span>Immediate intervention required - schedule intensive training sessions</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-user-friends text-blue-500 mt-0.5 mr-2"></i>
                            <span>Implement peer mentoring programs for this category</span>
                        </li>
                        <?php elseif ($need['avg_rating'] < 4.0): ?>
                        <li class="flex items-start">
                            <i class="fas fa-lightbulb text-yellow-500 mt-0.5 mr-2"></i>
                            <span>Targeted professional development workshops</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-chart-line text-green-500 mt-0.5 mr-2"></i>
                            <span>Regular performance monitoring and feedback sessions</span>
                        </li>
                        <?php else: ?>
                        <li class="flex items-start">
                            <i class="fas fa-star text-green-500 mt-0.5 mr-2"></i>
                            <span>Maintain current performance with occasional refresher training</span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <?php
                $rank++;
            endwhile;
            ?>
        </div>
        <?php else: ?>
        <div class="text-center py-8">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-graduation-cap text-gray-400 text-2xl"></i>
            </div>
            <h4 class="text-lg font-medium text-gray-900 mb-2">No Training Data Available</h4>
            <p class="text-sm text-gray-600 mb-4">
                Insufficient evaluation data to determine training needs. Please ensure you have:
            </p>
            <ul class="text-sm text-gray-600 space-y-1 mb-6">
                <li>• At least 5 responses per evaluation category</li>
                <li>• Completed evaluation sessions</li>
                <li>• Active semester with evaluation data</li>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <!-- Training Recommendations -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-lightbulb text-yellow-600 mr-2"></i>
            Strategic Training Recommendations
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-blue-900 mb-3">Immediate Actions (Critical Areas)</h4>
                <ul class="text-sm text-blue-800 space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-clock text-blue-600 mt-1 mr-2"></i>
                        <span>Schedule intensive training sessions within 2 weeks</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-user-friends text-blue-600 mt-1 mr-2"></i>
                        <span>Assign experienced mentors to struggling teachers</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-chart-line text-blue-600 mt-1 mr-2"></i>
                        <span>Implement weekly progress monitoring</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-certificate text-blue-600 mt-1 mr-2"></i>
                        <span>Provide certification programs for skill development</span>
                    </li>
                </ul>
            </div>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-green-900 mb-3">Long-term Strategies</h4>
                <ul class="text-sm text-green-800 space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-calendar-alt text-green-600 mt-1 mr-2"></i>
                        <span>Establish quarterly training programs</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-users text-green-600 mt-1 mr-2"></i>
                        <span>Create peer learning communities</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-book text-green-600 mt-1 mr-2"></i>
                        <span>Develop resource libraries and best practices</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-trophy text-green-600 mt-1 mr-2"></i>
                        <span>Implement recognition programs for improvement</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Training Impact Metrics -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-bar text-purple-600 mr-2"></i>
            Training Impact Metrics
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-gradient-to-br from-purple-50 to-violet-50 border border-purple-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-purple-900">Expected Improvement</h4>
                    <i class="fas fa-arrow-up text-purple-600"></i>
                </div>
                <p class="text-2xl font-bold text-purple-900">15-25%</p>
                <p class="text-xs text-purple-700 mt-1">Average rating improvement after training</p>
            </div>
            <div class="bg-gradient-to-br from-indigo-50 to-blue-50 border border-indigo-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-indigo-900">Time to Impact</h4>
                    <i class="fas fa-clock text-indigo-600"></i>
                </div>
                <p class="text-2xl font-bold text-indigo-900">3-6 months</p>
                <p class="text-xs text-indigo-700 mt-1">Expected timeframe for measurable results</p>
            </div>
            <div class="bg-gradient-to-br from-teal-50 to-cyan-50 border border-teal-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-teal-900">Success Rate</h4>
                    <i class="fas fa-check-circle text-teal-600"></i>
                </div>
                <p class="text-2xl font-bold text-teal-900">85%</p>
                <p class="text-xs text-teal-700 mt-1">Teachers showing improvement after training</p>
            </div>
        </div>
    </div>

    <!-- Action Plan -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-tasks text-seait-orange mr-2"></i>
            Recommended Action Plan
        </h3>
        <div class="space-y-4">
            <div class="flex items-start p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="w-8 h-8 bg-red-100 text-red-800 text-sm font-bold rounded-full flex items-center justify-center mr-3 mt-1">
                    1
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-red-900 mb-1">Week 1-2: Immediate Assessment</h4>
                    <ul class="text-xs text-red-800 space-y-1">
                        <li>• Conduct detailed analysis of critical areas</li>
                        <li>• Identify specific training needs for each category</li>
                        <li>• Schedule initial training sessions</li>
                    </ul>
                </div>
            </div>

            <div class="flex items-start p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="w-8 h-8 bg-yellow-100 text-yellow-800 text-sm font-bold rounded-full flex items-center justify-center mr-3 mt-1">
                    2
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-yellow-900 mb-1">Week 3-8: Training Implementation</h4>
                    <ul class="text-xs text-yellow-800 space-y-1">
                        <li>• Begin targeted training programs</li>
                        <li>• Implement peer mentoring initiatives</li>
                        <li>• Establish regular feedback mechanisms</li>
                    </ul>
                </div>
            </div>

            <div class="flex items-start p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="w-8 h-8 bg-green-100 text-green-800 text-sm font-bold rounded-full flex items-center justify-center mr-3 mt-1">
                    3
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-green-900 mb-1">Month 3-6: Monitoring & Evaluation</h4>
                    <ul class="text-xs text-green-800 space-y-1">
                        <li>• Track progress through follow-up evaluations</li>
                        <li>• Adjust training programs based on results</li>
                        <li>• Celebrate improvements and recognize achievements</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>