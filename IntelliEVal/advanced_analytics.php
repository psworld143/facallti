<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'includes/data_analysis.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Initialize data analysis
$dataAnalysis = new DataAnalysis($conn);

// Get selected semester and analysis type
$selected_semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$analysis_type = isset($_GET['analysis_type']) ? $_GET['analysis_type'] : 'sentiment';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10; // Number of records per page
$offset = ($page - 1) * $per_page;

// Set page title
$page_title = 'Advanced Analytics';

// Get available semesters
$semesters_query = "SELECT id, name, academic_year FROM semesters WHERE status = 'active' ORDER BY start_date DESC";
$semesters_result = mysqli_query($conn, $semesters_query);

// Get text feedback data for sentiment analysis
$feedback_data = [];
$total_feedback_count = 0;
if ($analysis_type === 'sentiment') {
    // First, get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM evaluation_responses er
                    JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
                    LEFT JOIN faculty f ON es.evaluatee_id = f.id AND es.evaluatee_type = 'teacher'
                    LEFT JOIN users u ON es.evaluatee_id = u.id AND es.evaluatee_type = 'teacher'
                    LEFT JOIN students st ON es.evaluator_id = st.id
                    WHERE er.text_response IS NOT NULL AND er.text_response != ''
                    AND es.evaluatee_type = 'teacher'";
    
    if ($selected_semester > 0) {
        $count_query .= " AND es.semester_id = " . $selected_semester;
    }
    
    $count_result = mysqli_query($conn, $count_query);
    if ($count_result) {
        $total_feedback_count = mysqli_fetch_assoc($count_result)['total'];
    }
    
    // Calculate total pages
    $total_pages = ceil($total_feedback_count / $per_page);
    
    // Ensure page is within valid range
    if ($page < 1) $page = 1;
    if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
    
    $feedback_query = "SELECT 
        er.id,
        er.text_response,
        er.rating_value,
        es.evaluation_date,
        COALESCE(
            CONCAT(f.first_name, ' ', f.last_name),
            CONCAT(u.first_name, ' ', u.last_name),
            'Unknown Teacher'
        ) as teacher_name,
        CASE 
            WHEN st.first_name IS NOT NULL AND st.last_name IS NOT NULL 
            THEN CONCAT(st.first_name, ' ', st.last_name)
            ELSE 'Anonymous Student'
        END as student_name
    FROM evaluation_responses er
    JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
    LEFT JOIN faculty f ON es.evaluatee_id = f.id AND es.evaluatee_type = 'teacher'
    LEFT JOIN users u ON es.evaluatee_id = u.id AND es.evaluatee_type = 'teacher'
    LEFT JOIN students st ON es.evaluator_id = st.id
    WHERE er.text_response IS NOT NULL AND er.text_response != ''
    AND es.evaluatee_type = 'teacher'";
    
    if ($selected_semester > 0) {
        $feedback_query .= " AND es.semester_id = " . $selected_semester;
    }
    
    $feedback_query .= " ORDER BY es.evaluation_date DESC LIMIT $per_page OFFSET $offset";
    
    $feedback_result = mysqli_query($conn, $feedback_query);
    if ($feedback_result) {
        while ($row = mysqli_fetch_assoc($feedback_result)) {
            $feedback_data[] = [
                'id' => $row['id'],
                'text' => $row['text_response'],
                'rating' => $row['rating_value'],
                'date' => $row['evaluation_date'],
                'teacher_name' => $row['teacher_name'],
                'student_name' => $row['student_name']
            ];
        }
    } else {
        error_log("Error in feedback query: " . mysqli_error($conn));
    }
    
    // Debug: Log feedback data count
    error_log("Found " . count($feedback_data) . " feedback records for sentiment analysis");
    
    // Debug: Log sample teacher names
    if (!empty($feedback_data)) {
        $sample_teachers = array_slice(array_column($feedback_data, 'teacher_name'), 0, 5);
        error_log("Sample teacher names: " . implode(', ', $sample_teachers));
    }
    
    // Debug: Check if faculty table has data
    $faculty_check_query = "SELECT COUNT(*) as count FROM faculty";
    $faculty_check_result = mysqli_query($conn, $faculty_check_query);
    if ($faculty_check_result) {
        $faculty_count = mysqli_fetch_assoc($faculty_check_result)['count'];
        error_log("Total faculty records in database: " . $faculty_count);
    }
    
    // Debug: Check evaluation_sessions data
    $sessions_check_query = "SELECT COUNT(*) as count FROM evaluation_sessions WHERE evaluatee_type = 'teacher'";
    $sessions_check_result = mysqli_query($conn, $sessions_check_query);
    if ($sessions_check_result) {
        $sessions_count = mysqli_fetch_assoc($sessions_check_result)['count'];
        error_log("Total teacher evaluation sessions: " . $sessions_count);
    }
    
    // Debug: Check what evaluatee_ids are being used
    $evaluatee_ids_query = "SELECT DISTINCT evaluatee_id FROM evaluation_sessions WHERE evaluatee_type = 'teacher' LIMIT 10";
    $evaluatee_ids_result = mysqli_query($conn, $evaluatee_ids_query);
    if ($evaluatee_ids_result) {
        $evaluatee_ids = [];
        while ($row = mysqli_fetch_assoc($evaluatee_ids_result)) {
            $evaluatee_ids[] = $row['evaluatee_id'];
        }
        error_log("Sample evaluatee_ids: " . implode(', ', $evaluatee_ids));
    }
    
    // Debug: Check if these IDs exist in faculty table
    if (!empty($evaluatee_ids)) {
        $faculty_check_query = "SELECT id, first_name, last_name FROM faculty WHERE id IN (" . implode(',', $evaluatee_ids) . ")";
        $faculty_check_result = mysqli_query($conn, $faculty_check_query);
        if ($faculty_check_result) {
            $faculty_found = [];
            while ($row = mysqli_fetch_assoc($faculty_check_result)) {
                $faculty_found[] = $row['id'] . ':' . $row['first_name'] . ' ' . $row['last_name'];
            }
            error_log("Faculty found for evaluatee_ids: " . implode(', ', $faculty_found));
        }
    }
    
    // Debug: Check if these IDs exist in users table
    if (!empty($evaluatee_ids)) {
        $users_check_query = "SELECT id, first_name, last_name FROM users WHERE id IN (" . implode(',', $evaluatee_ids) . ")";
        $users_check_result = mysqli_query($conn, $users_check_query);
        if ($users_check_result) {
            $users_found = [];
            while ($row = mysqli_fetch_assoc($users_check_result)) {
                $users_found[] = $row['id'] . ':' . $row['first_name'] . ' ' . $row['last_name'];
            }
            error_log("Users found for evaluatee_ids: " . implode(', ', $users_found));
        }
    }
}

// Get faculty data for clustering and prediction
$faculty_data = [];
if ($analysis_type === 'clustering' || $analysis_type === 'prediction') {
    // Debug: Check what evaluatee_ids exist in evaluation_sessions
    $debug_query = "SELECT DISTINCT evaluatee_id, evaluatee_type FROM evaluation_sessions WHERE evaluatee_type = 'teacher' LIMIT 10";
    $debug_result = mysqli_query($conn, $debug_query);
    if ($debug_result) {
        $debug_ids = [];
        while ($row = mysqli_fetch_assoc($debug_result)) {
            $debug_ids[] = $row['evaluatee_id'] . ' (' . $row['evaluatee_type'] . ')';
        }
        error_log("Debug - evaluatee_ids in evaluation_sessions: " . implode(', ', $debug_ids));
    }
    
    // Debug: Check if these IDs exist in faculty table
    $faculty_check_query = "SELECT id, first_name, last_name FROM faculty LIMIT 5";
    $faculty_check_result = mysqli_query($conn, $faculty_check_query);
    if ($faculty_check_result) {
        $faculty_sample = [];
        while ($row = mysqli_fetch_assoc($faculty_check_result)) {
            $faculty_sample[] = $row['id'] . ':' . $row['first_name'] . ' ' . $row['last_name'];
        }
        error_log("Debug - Sample faculty records: " . implode(', ', $faculty_sample));
    }
    
    // Debug: Check if these IDs exist in users table with teacher role
    $users_check_query = "SELECT id, first_name, last_name, role FROM users WHERE role = 'teacher' LIMIT 5";
    $users_check_result = mysqli_query($conn, $users_check_query);
    if ($users_check_result) {
        $users_sample = [];
        while ($row = mysqli_fetch_assoc($users_check_result)) {
            $users_sample[] = $row['id'] . ':' . $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['role'] . ')';
        }
        error_log("Debug - Sample teacher users: " . implode(', ', $users_sample));
    }
    
    $faculty_query = "SELECT 
        COALESCE(f.id, u.id) as id,
        COALESCE(
            CONCAT(f.first_name, ' ', f.last_name),
            CONCAT(u.first_name, ' ', u.last_name),
            'Unknown Faculty'
        ) as name,
        AVG(er.rating_value) as avg_rating,
        COUNT(DISTINCT es.id) as total_evaluations,
        COUNT(CASE WHEN er.rating_value >= 4 THEN 1 END) / COUNT(er.rating_value) as positive_feedback_ratio,
        COUNT(CASE WHEN er.rating_value <= 2 THEN 1 END) / COUNT(er.rating_value) as negative_feedback_ratio
    FROM evaluation_sessions es
    INNER JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
    LEFT JOIN faculty f ON es.evaluatee_id = f.id AND es.evaluatee_type = 'teacher'
    LEFT JOIN users u ON es.evaluatee_id = u.id AND es.evaluatee_type = 'teacher' AND u.role = 'teacher'
    WHERE es.evaluatee_type = 'teacher'";
    
    if ($selected_semester > 0) {
        $faculty_query .= " AND es.semester_id = " . $selected_semester;
    }
    
    $faculty_query .= " GROUP BY COALESCE(f.id, u.id), COALESCE(f.first_name, u.first_name), COALESCE(f.last_name, u.last_name)
                       HAVING COUNT(er.rating_value) >= 3
                       ORDER BY avg_rating DESC";
    
    error_log("Debug - Faculty query: " . $faculty_query);
    
    $faculty_result = mysqli_query($conn, $faculty_query);
    if ($faculty_result) {
        while ($row = mysqli_fetch_assoc($faculty_result)) {
            $faculty_data[] = $row;
        }
    } else {
        error_log("Debug - Faculty query error: " . mysqli_error($conn));
    }
    
    // Debug: Log faculty data
    error_log("Found " . count($faculty_data) . " faculty members for clustering/prediction");
    foreach ($faculty_data as $faculty) {
        error_log("Faculty: " . $faculty['name'] . " - Avg Rating: " . $faculty['avg_rating'] . " - Evaluations: " . $faculty['total_evaluations']);
    }
    
    // Debug: Log sample faculty names
    if (!empty($faculty_data)) {
        $sample_faculty = array_slice(array_column($faculty_data, 'name'), 0, 5);
        error_log("Sample faculty names: " . implode(', ', $sample_faculty));
    } else {
        error_log("No faculty data found - this might be why prediction is not working");
    }
}

// Process analysis based on type
$analysis_results = [];
if ($analysis_type === 'sentiment' && !empty($feedback_data)) {
    $analysis_results = $dataAnalysis->analyzeTextFeedback($feedback_data);
    
    // Debug: Check if we have results
    if (empty($analysis_results)) {
        error_log("No sentiment analysis results generated");
    } else {
        error_log("Generated " . count($analysis_results) . " sentiment analysis results");
    }
} elseif ($analysis_type === 'clustering' && !empty($faculty_data)) {
    $k = min(3, count($faculty_data)); // Use smaller k if fewer faculty
    $analysis_results = $dataAnalysis->clusterFacultyByEvaluationPatterns($faculty_data, $k);
    
    // Debug: Check if we have results
    if (empty($analysis_results)) {
        error_log("No clustering results generated");
    } else {
        error_log("Generated clustering results with " . count($analysis_results['faculty_clusters']) . " faculty clusters");
    }
} elseif ($analysis_type === 'prediction' && !empty($faculty_data)) {
    error_log("Starting prediction analysis with " . count($faculty_data) . " faculty members");
    
    // Prepare historical data for prediction
    $historical_data = [];
    foreach ($faculty_data as $faculty) {
        // Calculate experience years (simplified - could be enhanced with actual hire dates)
        $experience_years = min(10, max(1, round($faculty['total_evaluations'] / 5)));
        
        // Determine performance category based on average rating
        $performance_category = 'average';
        if ($faculty['avg_rating'] >= 4.5) {
            $performance_category = 'excellent';
        } elseif ($faculty['avg_rating'] >= 4.0) {
            $performance_category = 'good';
        } elseif ($faculty['avg_rating'] <= 3.0) {
            $performance_category = 'needs_improvement';
        }
        
        $historical_data[] = [
            'faculty_id' => $faculty['id'],
            'faculty_name' => $faculty['name'],
            'avg_rating' => $faculty['avg_rating'],
            'total_evaluations' => $faculty['total_evaluations'],
            'experience_years' => $experience_years,
            'positive_feedback_ratio' => $faculty['positive_feedback_ratio'],
            'performance_category' => $performance_category
        ];
        
        error_log("Historical data for " . $faculty['name'] . ": rating=" . $faculty['avg_rating'] . ", evaluations=" . $faculty['total_evaluations'] . ", category=" . $performance_category);
    }
    
    error_log("Prepared " . count($historical_data) . " historical records for prediction");
    
    // Generate predictions for each faculty member
    $prediction_results = [];
    foreach ($historical_data as $faculty) {
        $current_features = [
            $faculty['avg_rating'],
            $faculty['total_evaluations'],
            $faculty['experience_years'],
            $faculty['positive_feedback_ratio']
        ];
        
        error_log("Predicting for " . $faculty['faculty_name'] . " with features: " . implode(', ', $current_features));
        
        try {
            $prediction = $dataAnalysis->predictPerformance($historical_data, $current_features);
            
            $prediction_results[] = [
                'faculty_id' => $faculty['faculty_id'],
                'faculty_name' => $faculty['faculty_name'],
                'current_rating' => $faculty['avg_rating'],
                'total_evaluations' => $faculty['total_evaluations'],
                'experience_years' => $faculty['experience_years'],
                'positive_feedback_ratio' => round($faculty['positive_feedback_ratio'] * 100, 1),
                'decision_tree_prediction' => $prediction['decision_tree_prediction'],
                'naive_bayes_prediction' => $prediction['naive_bayes_prediction'],
                'confidence_score' => round($prediction['confidence_scores'] * 100, 1)
            ];
            
            error_log("Prediction for " . $faculty['faculty_name'] . ": DT=" . $prediction['decision_tree_prediction'] . ", NB=" . $prediction['naive_bayes_prediction'] . ", Confidence=" . $prediction['confidence_scores']);
        } catch (Exception $e) {
            error_log("Error predicting for " . $faculty['faculty_name'] . ": " . $e->getMessage());
        }
    }
    
    $analysis_results = $prediction_results;
    
    // Debug: Check if we have results
    if (empty($analysis_results)) {
        error_log("No prediction results generated");
    } else {
        error_log("Generated prediction results for " . count($analysis_results) . " faculty members");
    }
} elseif ($analysis_type === 'prediction') {
    error_log("Prediction analysis requested but no faculty data available");
}

// Include the header
include 'includes/header.php';
?>

<div class="mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark">Advanced Analytics</h1>
            <p class="text-gray-600 mt-1">Comprehensive data analysis with machine learning insights</p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-2">
            <a href="reports.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Reports
            </a>
        </div>
    </div>
</div>

<!-- Analysis Type Navigation -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <div class="flex flex-wrap gap-2">
        <a href="?analysis_type=sentiment&semester=<?php echo $selected_semester; ?>"
           class="px-4 py-2 rounded-md <?php echo $analysis_type === 'sentiment' ? 'bg-seait-orange text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-colors">
            <i class="fas fa-comments mr-2"></i>Sentiment Analysis
        </a>
        <a href="?analysis_type=clustering&semester=<?php echo $selected_semester; ?>"
           class="px-4 py-2 rounded-md <?php echo $analysis_type === 'clustering' ? 'bg-seait-orange text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-colors">
            <i class="fas fa-brain mr-2"></i>Faculty Clustering
        </a>
        <a href="?analysis_type=prediction&semester=<?php echo $selected_semester; ?>"
           class="px-4 py-2 rounded-md <?php echo $analysis_type === 'prediction' ? 'bg-seait-orange text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-colors">
            <i class="fas fa-chart-line mr-2"></i>Performance Prediction
        </a>
    </div>
</div>

<!-- Semester Filter -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter by Semester</h3>
    <form method="GET" class="flex gap-4">
        <input type="hidden" name="analysis_type" value="<?php echo $analysis_type; ?>">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
            <select name="semester" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Semesters</option>
                <?php if ($semesters_result): while ($semester = mysqli_fetch_assoc($semesters_result)): ?>
                <option value="<?php echo $semester['id']; ?>" <?php echo $selected_semester == $semester['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($semester['name'] . ' (' . $semester['academic_year'] . ')'); ?>
                </option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition-colors">
                <i class="fas fa-filter mr-2"></i>Apply Filter
            </button>
        </div>
    </form>
</div>

<?php if ($analysis_type === 'sentiment'): ?>
<!-- Sentiment Analysis Results -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">
        <i class="fas fa-comments mr-2"></i>Sentiment Analysis Results
    </h2>
    
    <?php if (!empty($analysis_results)): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <?php
        $sentiment_counts = [];
        $total_feedback = count($analysis_results);
        
        foreach ($analysis_results as $result) {
            $sentiment = $result['sentiment']['sentiment'];
            if (!isset($sentiment_counts[$sentiment])) {
                $sentiment_counts[$sentiment] = 0;
            }
            $sentiment_counts[$sentiment]++;
        }
        ?>
        
        <div class="bg-green-50 p-4 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-smile text-green-600 text-2xl mr-3"></i>
                <div>
                    <div class="text-2xl font-bold text-green-900"><?php echo $sentiment_counts['positive'] ?? 0; ?></div>
                    <div class="text-sm text-green-700">Positive Feedback</div>
                    <div class="text-xs text-green-600"><?php echo round(($sentiment_counts['positive'] ?? 0) / $total_feedback * 100, 1); ?>%</div>
                </div>
            </div>
        </div>
        
        <div class="bg-yellow-50 p-4 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-meh text-yellow-600 text-2xl mr-3"></i>
                <div>
                    <div class="text-2xl font-bold text-yellow-900"><?php echo $sentiment_counts['neutral'] ?? 0; ?></div>
                    <div class="text-sm text-yellow-700">Neutral Feedback</div>
                    <div class="text-xs text-yellow-600"><?php echo round(($sentiment_counts['neutral'] ?? 0) / $total_feedback * 100, 1); ?>%</div>
                </div>
            </div>
        </div>
        
        <div class="bg-red-50 p-4 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-frown text-red-600 text-2xl mr-3"></i>
                <div>
                    <div class="text-2xl font-bold text-red-900"><?php echo $sentiment_counts['negative'] ?? 0; ?></div>
                    <div class="text-sm text-red-700">Negative Feedback</div>
                    <div class="text-xs text-red-600"><?php echo round(($sentiment_counts['negative'] ?? 0) / $total_feedback * 100, 1); ?>%</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Feedback</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sentiment</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($analysis_results as $result): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php 
                        $teacher_name = trim($result['teacher_name'] ?? '');
                        echo htmlspecialchars($teacher_name ?: 'Unknown Teacher'); 
                        ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <div class="max-w-md">
                            <?php 
                            // Clean and decode the text properly
                            $clean_text = trim($result['text']);
                            $decoded_text = html_entity_decode($clean_text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $display_text = htmlspecialchars($decoded_text, ENT_QUOTES, 'UTF-8');
                            
                            // Display full text with proper wrapping
                            echo '<div class="whitespace-pre-wrap break-words leading-relaxed">' . $display_text . '</div>';
                            ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        $sentiment = $result['sentiment']['sentiment'];
                        $color_class = $sentiment === 'positive' ? 'text-green-600' : ($sentiment === 'negative' ? 'text-red-600' : 'text-yellow-600');
                        $icon = $sentiment === 'positive' ? 'fa-smile' : ($sentiment === 'negative' ? 'fa-frown' : 'fa-meh');
                        ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $color_class; ?>">
                            <i class="fas <?php echo $icon; ?> mr-1"></i><?php echo ucfirst($sentiment); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $result['rating'] ?? 'N/A'; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($result['date'] ?? '')); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
    <div class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-700">
            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $per_page, $total_feedback_count); ?> of <?php echo $total_feedback_count; ?> results
        </div>
        <div class="flex items-center space-x-2">
            <?php if ($page > 1): ?>
            <a href="?analysis_type=sentiment&semester=<?php echo $selected_semester; ?>&page=<?php echo ($page - 1); ?>" 
               class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                <i class="fas fa-chevron-left mr-1"></i>Previous
            </a>
            <?php endif; ?>
            
            <div class="flex items-center space-x-1">
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                <a href="?analysis_type=sentiment&semester=<?php echo $selected_semester; ?>&page=1" 
                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">1</a>
                <?php if ($start_page > 2): ?>
                <span class="px-3 py-2 text-sm text-gray-500">...</span>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?analysis_type=sentiment&semester=<?php echo $selected_semester; ?>&page=<?php echo $i; ?>" 
                   class="px-3 py-2 text-sm font-medium <?php echo $i == $page ? 'text-white bg-seait-orange border-seait-orange' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50'; ?> border rounded-md">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                <span class="px-3 py-2 text-sm text-gray-500">...</span>
                <?php endif; ?>
                <a href="?analysis_type=sentiment&semester=<?php echo $selected_semester; ?>&page=<?php echo $total_pages; ?>" 
                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50"><?php echo $total_pages; ?></a>
                <?php endif; ?>
            </div>
            
            <?php if ($page < $total_pages): ?>
            <a href="?analysis_type=sentiment&semester=<?php echo $selected_semester; ?>&page=<?php echo ($page + 1); ?>" 
               class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                Next<i class="fas fa-chevron-right ml-1"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <div class="text-center py-8">
        <i class="fas fa-comments text-gray-400 text-4xl mb-4"></i>
        <p class="text-gray-600">No text feedback data available for analysis.</p>
        <?php if (empty($feedback_data)): ?>
        <p class="text-sm text-gray-500 mt-2">No text responses found in the selected semester. Try selecting a different semester or check if evaluations have been completed.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($analysis_type === 'clustering'): ?>
<!-- Faculty Clustering Results -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">
        <i class="fas fa-brain mr-2"></i>Faculty Clustering Analysis
    </h2>
    
    <?php if (!empty($analysis_results['faculty_clusters'])): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-blue-50 p-4 rounded-lg">
            <h3 class="font-semibold text-blue-900 mb-2">Clustering Metrics</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span>Silhouette Score:</span>
                    <span class="font-medium"><?php echo round($analysis_results['clustering_metrics']['silhouette_score'], 3); ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Iterations:</span>
                    <span class="font-medium"><?php echo $analysis_results['clustering_metrics']['iterations']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Total Faculty:</span>
                    <span class="font-medium"><?php echo count($analysis_results['faculty_clusters']); ?></span>
                </div>
            </div>
        </div>
        
        <div class="bg-green-50 p-4 rounded-lg">
            <h3 class="font-semibold text-green-900 mb-2">Cluster Distribution</h3>
            <?php
            $cluster_counts = array_count_values(array_column($analysis_results['faculty_clusters'], 'cluster'));
            foreach ($cluster_counts as $cluster_id => $count):
            ?>
            <div class="flex justify-between text-sm mb-1">
                <span>Cluster <?php echo $cluster_id + 1; ?>:</span>
                <span class="font-medium"><?php echo $count; ?> faculty</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faculty Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cluster</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Rating</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Evaluations</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Centroid Distance</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($analysis_results['faculty_clusters'] as $cluster): ?>
                <?php
                $faculty_info = array_filter($faculty_data, function($f) use ($cluster) {
                    return $f['id'] == $cluster['faculty_id'];
                });
                $faculty_info = reset($faculty_info);
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php 
                        $faculty_name = trim($faculty_info['name'] ?? '');
                        echo htmlspecialchars($faculty_name ?: 'Unknown Faculty'); 
                        ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            Cluster <?php echo $cluster['cluster'] + 1; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo round($faculty_info['avg_rating'] ?? 0, 2); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $faculty_info['total_evaluations'] ?? 0; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo round($cluster['centroid_distance'], 3); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="text-center py-8">
        <i class="fas fa-brain text-gray-400 text-4xl mb-4"></i>
        <p class="text-gray-600">No faculty data available for clustering analysis.</p>
        <?php if (empty($faculty_data)): ?>
        <p class="text-sm text-gray-500 mt-2">Faculty need at least 3 evaluations to be included in clustering.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($analysis_type === 'prediction'): ?>
<!-- Performance Prediction Results -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">
        <i class="fas fa-chart-line mr-2"></i>Performance Prediction Analysis
    </h2>
    
    <?php if (!empty($analysis_results)): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <?php
        $prediction_counts = [];
        $total_predictions = count($analysis_results);
        
        foreach ($analysis_results as $result) {
            $prediction = $result['decision_tree_prediction'];
            if (!isset($prediction_counts[$prediction])) {
                $prediction_counts[$prediction] = 0;
            }
            $prediction_counts[$prediction]++;
        }
        ?>
        
        <div class="bg-green-50 p-4 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-star text-green-600 text-2xl mr-3"></i>
                <div>
                    <div class="text-2xl font-bold text-green-900"><?php echo $prediction_counts['excellent'] ?? 0; ?></div>
                    <div class="text-sm text-green-700">Excellent Performance</div>
                    <div class="text-xs text-green-600"><?php echo round(($prediction_counts['excellent'] ?? 0) / $total_predictions * 100, 1); ?>%</div>
                </div>
            </div>
        </div>
        
        <div class="bg-blue-50 p-4 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-thumbs-up text-blue-600 text-2xl mr-3"></i>
                <div>
                    <div class="text-2xl font-bold text-blue-900"><?php echo $prediction_counts['good'] ?? 0; ?></div>
                    <div class="text-sm text-blue-700">Good Performance</div>
                    <div class="text-xs text-blue-600"><?php echo round(($prediction_counts['good'] ?? 0) / $total_predictions * 100, 1); ?>%</div>
                </div>
            </div>
        </div>
        
        <div class="bg-yellow-50 p-4 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl mr-3"></i>
                <div>
                    <div class="text-2xl font-bold text-yellow-900"><?php echo ($prediction_counts['needs_improvement'] ?? 0) + ($prediction_counts['average'] ?? 0); ?></div>
                    <div class="text-sm text-yellow-700">Needs Improvement</div>
                    <div class="text-xs text-yellow-600"><?php echo round((($prediction_counts['needs_improvement'] ?? 0) + ($prediction_counts['average'] ?? 0)) / $total_predictions * 100, 1); ?>%</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faculty Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Rating</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Experience</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Decision Tree Prediction</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Naive Bayes Prediction</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Confidence</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($analysis_results as $result): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($result['faculty_name']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div class="flex items-center">
                            <span class="font-medium"><?php echo number_format($result['current_rating'], 2); ?></span>
                            <span class="text-xs text-gray-500 ml-1">/5.0</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            <?php echo $result['total_evaluations']; ?> evaluations
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div><?php echo $result['experience_years']; ?> years</div>
                        <div class="text-xs text-gray-500">
                            <?php echo $result['positive_feedback_ratio']; ?>% positive
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        $prediction = $result['decision_tree_prediction'];
                        $color_class = $prediction === 'excellent' ? 'text-green-600 bg-green-100' : 
                                     ($prediction === 'good' ? 'text-blue-600 bg-blue-100' : 
                                     ($prediction === 'needs_improvement' ? 'text-red-600 bg-red-100' : 'text-yellow-600 bg-yellow-100'));
                        $icon = $prediction === 'excellent' ? 'fa-star' : 
                               ($prediction === 'good' ? 'fa-thumbs-up' : 
                               ($prediction === 'needs_improvement' ? 'fa-exclamation-triangle' : 'fa-minus'));
                        ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $color_class; ?>">
                            <i class="fas <?php echo $icon; ?> mr-1"></i><?php echo ucwords(str_replace('_', ' ', $prediction)); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        $prediction = $result['naive_bayes_prediction'];
                        $color_class = $prediction === 'excellent' ? 'text-green-600 bg-green-100' : 
                                     ($prediction === 'good' ? 'text-blue-600 bg-blue-100' : 
                                     ($prediction === 'needs_improvement' ? 'text-red-600 bg-red-100' : 'text-yellow-600 bg-yellow-100'));
                        $icon = $prediction === 'excellent' ? 'fa-star' : 
                               ($prediction === 'good' ? 'fa-thumbs-up' : 
                               ($prediction === 'needs_improvement' ? 'fa-exclamation-triangle' : 'fa-minus'));
                        ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $color_class; ?>">
                            <i class="fas <?php echo $icon; ?> mr-1"></i><?php echo ucwords(str_replace('_', ' ', $prediction)); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                <div class="bg-seait-orange h-2 rounded-full" style="width: <?php echo $result['confidence_score']; ?>%"></div>
                            </div>
                            <span class="text-xs font-medium"><?php echo $result['confidence_score']; ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="mt-6 bg-blue-50 p-4 rounded-lg">
        <h3 class="font-semibold text-blue-900 mb-2">
            <i class="fas fa-info-circle mr-2"></i>Prediction Methodology
        </h3>
        <div class="text-sm text-blue-800 space-y-1">
            <p><strong>Decision Tree:</strong> Uses historical performance patterns to predict future performance categories.</p>
            <p><strong>Naive Bayes:</strong> Applies probabilistic classification based on feature distributions.</p>
            <p><strong>Confidence Score:</strong> Indicates prediction reliability based on nearest neighbor analysis.</p>
            <p><strong>Features Used:</strong> Average rating, total evaluations, experience years, and positive feedback ratio.</p>
        </div>
    </div>
    
    <?php else: ?>
    <div class="text-center py-8">
        <i class="fas fa-chart-line text-gray-400 text-4xl mb-4"></i>
        <p class="text-gray-600">No faculty data available for performance prediction.</p>
        <?php if (empty($faculty_data)): ?>
        <p class="text-sm text-gray-500 mt-2">Faculty need at least 3 evaluations to be included in prediction analysis.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
