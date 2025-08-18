<!-- Performance Metrics Report -->
<div class="space-y-6">
    <!-- Performance Overview -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-trophy text-yellow-600 mr-2"></i>
            Performance Metrics Overview
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-green-900">Overall Rating</h4>
                    <i class="fas fa-star text-green-600"></i>
                </div>
                <p class="text-2xl font-bold text-green-900"><?php echo number_format($avg_rating, 2); ?>/5.0</p>
                <p class="text-xs text-green-700 mt-1">
                    <?php
                    if ($avg_rating >= 4.5) echo "Excellent Performance";
                    elseif ($avg_rating >= 4.0) echo "Good Performance";
                    elseif ($avg_rating >= 3.5) echo "Satisfactory Performance";
                    else echo "Needs Improvement";
                    ?>
                </p>
            </div>

            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-blue-900">Total Evaluations</h4>
                    <i class="fas fa-chart-bar text-blue-600"></i>
                </div>
                <p class="text-2xl font-bold text-blue-900"><?php echo number_format($total_evaluations); ?></p>
                <p class="text-xs text-blue-700 mt-1">
                    <?php if ($selected_semester > 0): ?>
                    <?php echo number_format($semester_evaluations); ?> in selected semester
                    <?php else: ?>
                    Across all semesters
                    <?php endif; ?>
                </p>
            </div>

            <div class="bg-gradient-to-br from-purple-50 to-violet-50 border border-purple-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-purple-900">Active Teachers</h4>
                    <i class="fas fa-user-tie text-purple-600"></i>
                </div>
                <p class="text-2xl font-bold text-purple-900"><?php echo number_format($total_teachers); ?></p>
                <p class="text-xs text-purple-700 mt-1">
                    <?php if ($selected_semester > 0): ?>
                    Evaluated this semester
                    <?php else: ?>
                    Total evaluated
                    <?php endif; ?>
                </p>
            </div>

            <div class="bg-gradient-to-br from-orange-50 to-red-50 border border-orange-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-orange-900">Student Participation</h4>
                    <i class="fas fa-users text-orange-600"></i>
                </div>
                <p class="text-2xl font-bold text-orange-900"><?php echo number_format($total_students); ?></p>
                <p class="text-xs text-orange-700 mt-1">
                    <?php if ($selected_semester > 0): ?>
                    Active this semester
                    <?php else: ?>
                    Total participants
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Rating Distribution -->
    <?php
    // Get rating distribution
    $rating_distribution_query = "SELECT
        er.rating_value,
        COUNT(*) as count,
        (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM evaluation_responses WHERE rating_value IS NOT NULL)) as percentage
    FROM evaluation_responses er
    INNER JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
    WHERE er.rating_value IS NOT NULL";

    if ($selected_semester > 0) {
        $rating_distribution_query .= " AND es.semester_id = " . $selected_semester;
    }

    $rating_distribution_query .= " GROUP BY er.rating_value ORDER BY er.rating_value DESC";
    $rating_distribution_result = mysqli_query($conn, $rating_distribution_query);
    ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-pie text-blue-600 mr-2"></i>
            Rating Distribution
            <?php if ($selected_semester > 0): ?>
            <span class="text-sm font-normal text-gray-500">(Semester Filtered)</span>
            <?php endif; ?>
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <div class="space-y-3">
                    <?php
                    $rating_labels = [5 => 'Excellent', 4 => 'Very Satisfactory', 3 => 'Satisfactory', 2 => 'Good', 1 => 'Poor'];
                    $rating_colors = [5 => 'bg-green-500', 4 => 'bg-blue-500', 3 => 'bg-yellow-500', 2 => 'bg-orange-500', 1 => 'bg-red-500'];
                    $rating_data = [];

                    while ($row = mysqli_fetch_assoc($rating_distribution_result)) {
                        $rating_data[$row['rating_value']] = $row;
                    }

                    foreach ($rating_labels as $rating => $label):
                        $data = $rating_data[$rating] ?? ['count' => 0, 'percentage' => 0];
                    ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-4 h-4 <?php echo $rating_colors[$rating]; ?> rounded-full mr-3"></div>
                            <span class="text-sm font-medium text-gray-900"><?php echo $label; ?> (<?php echo $rating; ?>)</span>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900"><?php echo number_format($data['count']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo number_format($data['percentage'], 1); ?>%</p>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="<?php echo $rating_colors[$rating]; ?> h-2 rounded-full" style="width: <?php echo $data['percentage']; ?>%"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex items-center justify-center">
                <div class="w-48 h-48">
                    <canvas id="ratingDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performing Teachers -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-medal text-yellow-600 mr-2"></i>
            Top Performing Teachers
            <?php if ($selected_semester > 0): ?>
            <span class="text-sm font-normal text-gray-500">(Semester Filtered)</span>
            <?php endif; ?>
        </h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Rating</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Responses</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance Level</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $rank = 1;
                    mysqli_data_seek($top_teachers_result, 0);
                    while($teacher = mysqli_fetch_assoc($top_teachers_result)):
                        $performance_class = $teacher['avg_rating'] >= 4.5 ? 'bg-green-100 text-green-800' :
                                           ($teacher['avg_rating'] >= 4.0 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                        $performance_text = $teacher['avg_rating'] >= 4.5 ? 'Excellent' :
                                          ($teacher['avg_rating'] >= 4.0 ? 'Good' : 'Needs Improvement');
                        $star_rating = str_repeat('★', round($teacher['avg_rating'])) . str_repeat('☆', 5 - round($teacher['avg_rating']));
                    ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <div class="flex items-center">
                                <?php if ($rank <= 3): ?>
                                <i class="fas fa-medal text-yellow-500 mr-2"></i>
                                <?php endif; ?>
                                #<?php echo $rank; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($teacher['teacher_name'] ?? 'Unknown Teacher'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <span class="text-yellow-500 text-sm mr-2"><?php echo $star_rating; ?></span>
                                <span class="font-semibold text-gray-900"><?php echo number_format($teacher['avg_rating'], 2); ?>/5.0</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo number_format($teacher['total_responses']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full font-medium <?php echo $performance_class; ?>">
                                <?php echo $performance_text; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <a href="teacher_details.php?id=<?php echo $teacher['evaluatee_id']; ?>"
                               class="text-seait-orange hover:text-orange-600">
                                <i class="fas fa-eye mr-1"></i>View Details
                            </a>
                        </td>
                    </tr>
                    <?php
                        $rank++;
                    endwhile;
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Performance Trends -->
    <?php
    // Get monthly performance trends
    $monthly_trends_query = "SELECT
        DATE_FORMAT(es.evaluation_date, '%Y-%m') as month,
        AVG(er.rating_value) as avg_rating,
        COUNT(er.id) as total_responses
    FROM evaluation_sessions es
    INNER JOIN evaluation_responses er ON es.id = er.evaluation_session_id
    WHERE er.rating_value IS NOT NULL";

    if ($selected_semester > 0) {
        $monthly_trends_query .= " AND es.semester_id = " . $selected_semester;
    }

    $monthly_trends_query .= " GROUP BY DATE_FORMAT(es.evaluation_date, '%Y-%m')
                              ORDER BY month DESC LIMIT 12";
    $monthly_trends_result = mysqli_query($conn, $monthly_trends_query);
    ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-line text-green-600 mr-2"></i>
            Performance Trends
            <?php if ($selected_semester > 0): ?>
            <span class="text-sm font-normal text-gray-500">(Semester Filtered)</span>
            <?php endif; ?>
        </h3>
        <div class="h-64">
            <canvas id="performanceTrendsChart"></canvas>
        </div>
    </div>

    <!-- Performance Insights -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-lightbulb text-yellow-600 mr-2"></i>
            Performance Insights
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-green-900 mb-3">Strengths</h4>
                <ul class="text-sm text-green-800 space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-600 mt-1 mr-2"></i>
                        <span>Overall average rating of <?php echo number_format($avg_rating, 2); ?>/5.0 indicates good performance</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-600 mt-1 mr-2"></i>
                        <span><?php echo number_format($total_evaluations); ?> evaluations show active participation</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-600 mt-1 mr-2"></i>
                        <span>High student engagement with <?php echo number_format($total_students); ?> participants</span>
                    </li>
                </ul>
            </div>
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-orange-900 mb-3">Areas for Improvement</h4>
                <ul class="text-sm text-orange-800 space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-orange-600 mt-1 mr-2"></i>
                        <span>Focus on teachers with ratings below 4.0</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-orange-600 mt-1 mr-2"></i>
                        <span>Increase evaluation frequency for better data quality</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-orange-600 mt-1 mr-2"></i>
                        <span>Implement targeted training programs</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Rating Distribution Chart
    const ratingCtx = document.getElementById('ratingDistributionChart');
    if (ratingCtx) {
        const ratingData = {
            labels: ['Excellent', 'Very Satisfactory', 'Satisfactory', 'Good', 'Poor'],
            datasets: [{
                data: [
                    <?php
                    $rating_counts = [];
                    foreach ($rating_labels as $rating => $label) {
                        $data = $rating_data[$rating] ?? ['count' => 0];
                        $rating_counts[] = $data['count'];
                    }
                    echo implode(', ', $rating_counts);
                    ?>
                ],
                backgroundColor: [
                    '#10B981', // green
                    '#3B82F6', // blue
                    '#F59E0B', // yellow
                    '#F97316', // orange
                    '#EF4444'  // red
                ]
            }]
        };

        new Chart(ratingCtx.getContext('2d'), {
            type: 'doughnut',
            data: ratingData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
    }

    // Performance Trends Chart
    const trendsCtx = document.getElementById('performanceTrendsChart');
    if (trendsCtx) {
        const trendsData = {
            labels: [
                <?php
                $months = [];
                $ratings = [];
                $responses = [];
                mysqli_data_seek($monthly_trends_result, 0);
                while ($row = mysqli_fetch_assoc($monthly_trends_result)) {
                    $months[] = "'" . date('M Y', strtotime($row['month'] . '-01')) . "'";
                    $ratings[] = $row['avg_rating'];
                    $responses[] = $row['total_responses'];
                }
                echo implode(', ', array_reverse($months));
                ?>
            ],
            datasets: [{
                label: 'Average Rating',
                data: [<?php echo implode(', ', array_reverse($ratings)); ?>],
                borderColor: '#FF6B35',
                backgroundColor: 'rgba(255, 107, 53, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                yAxisID: 'y'
            }, {
                label: 'Total Responses',
                data: [<?php echo implode(', ', array_reverse($responses)); ?>],
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                fill: false,
                tension: 0.4,
                yAxisID: 'y1'
            }]
        };

        new Chart(trendsCtx.getContext('2d'), {
            type: 'line',
            data: trendsData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        min: 0,
                        max: 5,
                        title: {
                            display: true,
                            text: 'Average Rating'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        min: 0,
                        title: {
                            display: true,
                            text: 'Total Responses'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    }
});
</script>