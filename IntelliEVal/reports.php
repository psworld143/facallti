<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer or head role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['guidance_officer', 'head'])) {
    header('Location: ../index.php');
    exit();
}

// Get selected semester from form
$selected_semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';

// Pagination variables
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Initialize variables with default values
$teacher_insights = [];
$pattern_insights = [];
$department_insights = [];
$total_evaluations = 0;
$semester_evaluations = 0;
$total_teachers = 0;
$total_students = 0;
$total_subjects = 0;
$avg_rating = 0;

// Get available semesters for dropdown
$semesters_query = "SELECT id, name, academic_year FROM semesters WHERE status = 'active' ORDER BY start_date DESC";
$semesters_result = mysqli_query($conn, $semesters_query);

// Get available academic years
$years_query = "SELECT DISTINCT academic_year FROM semesters WHERE status = 'active' ORDER BY academic_year DESC";
$years_result = mysqli_query($conn, $years_query);

// Base date filters
$current_month = date('Y-m');
$current_year = date('Y');

// Semester-specific date range
$semester_start_date = null;
$semester_end_date = null;
if ($selected_semester > 0) {
    $semester_dates_query = "SELECT start_date, end_date FROM semesters WHERE id = ?";
    $stmt = mysqli_prepare($conn, $semester_dates_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $selected_semester);
        mysqli_stmt_execute($stmt);
        $semester_dates_result = mysqli_stmt_get_result($stmt);
        if ($semester_row = mysqli_fetch_assoc($semester_dates_result)) {
            $semester_start_date = $semester_row['start_date'];
            $semester_end_date = $semester_row['end_date'];
        }
    }
}

// Try to include clustering analysis with error handling
try {
    if (file_exists('clustering_analysis.php')) {
        require_once 'clustering_analysis.php';

        // Initialize clustering analysis
        $clustering = new ClusteringAnalysis($conn);

        // Get clustering data with error handling
        try {
            $teacher_clusters = $clustering->clusterTeacherPerformance($selected_semester, 3);
            if (!isset($teacher_clusters['error'])) {
                $teacher_insights = $clustering->getClusteringInsights($teacher_clusters, 'teacher');
            }
        } catch (Exception $e) {
            // Silently handle clustering errors
        }

        try {
            $pattern_clusters = $clustering->clusterEvaluationPatterns($selected_semester, 4);
            if (!isset($pattern_clusters['error'])) {
                $pattern_insights = $clustering->getClusteringInsights($pattern_clusters, 'pattern');
            }
        } catch (Exception $e) {
            // Silently handle clustering errors
        }

        try {
            $department_clusters = $clustering->clusterDepartmentPerformance($selected_semester, 3);
            if (!isset($department_clusters['error'])) {
                $department_insights = $clustering->getClusteringInsights($department_clusters, 'department');
            }
        } catch (Exception $e) {
            // Silently handle clustering errors
        }
    }
} catch (Exception $e) {
    // Silently handle clustering analysis errors
}

// Total evaluation statistics with error handling
try {
    $total_evaluations_query = "SELECT COUNT(*) as total FROM evaluation_sessions es";
    $total_evaluations_result = mysqli_query($conn, $total_evaluations_query);
    if ($total_evaluations_result) {
        $total_evaluations = mysqli_fetch_assoc($total_evaluations_result)['total'];
    }
} catch (Exception $e) {
    $total_evaluations = 0;
}

// Semester-specific evaluation statistics
try {
    if ($semester_start_date && $semester_end_date) {
        $semester_evaluations_query = "SELECT COUNT(*) as total FROM evaluation_sessions es
                                      WHERE es.evaluation_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $semester_evaluations_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $semester_start_date, $semester_end_date);
            mysqli_stmt_execute($stmt);
            $semester_evaluations_result = mysqli_stmt_get_result($stmt);
            $semester_evaluations = mysqli_fetch_assoc($semester_evaluations_result)['total'];
        }
    }
} catch (Exception $e) {
    $semester_evaluations = 0;
}

// Teacher statistics with error handling
try {
    $total_teachers_query = "SELECT COUNT(DISTINCT es.evaluatee_id) as total FROM evaluation_sessions es
                            WHERE es.evaluatee_type = 'teacher'";
    $total_teachers_result = mysqli_query($conn, $total_teachers_query);
    if ($total_teachers_result) {
        $total_teachers = mysqli_fetch_assoc($total_teachers_result)['total'];
    }
} catch (Exception $e) {
    $total_teachers = 0;
}

// Student statistics with error handling
try {
    $total_students_query = "SELECT COUNT(DISTINCT es.evaluator_id) as total FROM evaluation_sessions es
                            WHERE es.evaluator_type = 'student'";
    $total_students_result = mysqli_query($conn, $total_students_query);
    if ($total_students_result) {
        $total_students = mysqli_fetch_assoc($total_students_result)['total'];
    }
} catch (Exception $e) {
    $total_students = 0;
}

// Subject statistics with error handling
try {
    $total_subjects_query = "SELECT COUNT(DISTINCT es.subject_id) as total FROM evaluation_sessions es
                            WHERE es.subject_id IS NOT NULL";
    $total_subjects_result = mysqli_query($conn, $total_subjects_query);
    if ($total_subjects_result) {
        $total_subjects = mysqli_fetch_assoc($total_subjects_result)['total'];
    }
} catch (Exception $e) {
    $total_subjects = 0;
}

// Average rating statistics with error handling
try {
    $avg_rating_query = "SELECT AVG(er.rating_value) as avg_rating FROM evaluation_responses er
                         INNER JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
                         WHERE er.rating_value IS NOT NULL";
    $avg_rating_result = mysqli_query($conn, $avg_rating_query);
    if ($avg_rating_result) {
        $avg_rating = mysqli_fetch_assoc($avg_rating_result)['avg_rating'];
        if ($avg_rating === null) {
            $avg_rating = 0;
        }
    }
} catch (Exception $e) {
    $avg_rating = 0;
}

// Training needs analysis with error handling
try {
    $training_needs_query = "SELECT
        esc.name as subcategory_name,
        mec.name as category_name,
        AVG(er.rating_value) as avg_rating,
        COUNT(er.id) as total_responses,
        COUNT(CASE WHEN er.rating_value <= 3 THEN 1 END) as low_ratings
    FROM evaluation_responses er
    JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
    JOIN evaluation_questionnaires eq ON er.questionnaire_id = eq.id
    JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
    JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
    WHERE es.status = 'completed' AND er.rating_value IS NOT NULL";

    if ($selected_semester > 0) {
        $training_needs_query .= " AND es.semester_id = " . $selected_semester;
    }

    $training_needs_query .= " GROUP BY esc.id, esc.name, mec.name
                              HAVING COUNT(er.id) >= 5
                              ORDER BY (COUNT(CASE WHEN er.rating_value <= 3 THEN 1 END) / COUNT(er.id)) DESC, avg_rating ASC
                              LIMIT 10";

    $training_needs_result = mysqli_query($conn, $training_needs_query);
    if (!$training_needs_result) {
        $training_needs_result = false;
    }
} catch (Exception $e) {
    $training_needs_result = false;
}

// Top performing teachers with error handling
try {
    $top_teachers_query = "SELECT
        es.evaluatee_id,
        CONCAT(COALESCE(f.first_name, u.first_name), ' ', COALESCE(f.last_name, u.last_name)) as teacher_name,
        AVG(er.rating_value) as avg_rating,
        COUNT(er.id) as total_responses
    FROM evaluation_sessions es
    INNER JOIN evaluation_responses er ON es.id = er.evaluation_session_id
    LEFT JOIN faculty f ON es.evaluatee_id = f.id
    LEFT JOIN users u ON es.evaluatee_id = u.id
    WHERE es.evaluatee_type = 'teacher' AND er.rating_value IS NOT NULL";

    if ($selected_semester > 0) {
        $top_teachers_query .= " AND es.semester_id = " . $selected_semester;
    }

    $top_teachers_query .= " GROUP BY es.evaluatee_id, f.first_name, f.last_name, u.first_name, u.last_name
                            HAVING COUNT(er.id) >= 5
                            ORDER BY avg_rating DESC
                            LIMIT 10";

    $top_teachers_result = mysqli_query($conn, $top_teachers_query);
    if (!$top_teachers_result) {
        $top_teachers_result = false;
    }
} catch (Exception $e) {
    $top_teachers_result = false;
}

// Recent evaluations with error handling
try {
    $recent_evaluations_query = "SELECT
        es.id,
        es.evaluator_id,
        es.evaluator_type,
        es.evaluatee_id,
        es.evaluatee_type,
        es.subject_id,
        es.semester_id,
        es.status,
        es.created_at,
        s.name as semester_name,
        sub.name as subject_name,
        CASE
            WHEN es.evaluator_type = 'student' THEN
                CONCAT(st.first_name, ' ', st.last_name)
            WHEN es.evaluator_type = 'teacher' THEN
                CONCAT(evaluator_f.first_name, ' ', evaluator_f.last_name)
            WHEN es.evaluator_type = 'head' THEN
                CONCAT(evaluator_u.first_name, ' ', evaluator_u.last_name)
            ELSE 'Unknown Evaluator'
        END as evaluator_name,
        CASE
            WHEN es.evaluatee_type = 'teacher' THEN
                CONCAT(evaluatee_f.first_name, ' ', evaluatee_f.last_name)
            ELSE
                CONCAT(evaluatee_u.first_name, ' ', evaluatee_u.last_name)
        END as teacher_name,
        COALESCE(es.evaluation_date, es.created_at) as display_date,
        COUNT(er.id) as total_responses
    FROM evaluation_sessions es
    LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id
    LEFT JOIN semesters s ON es.semester_id = s.id
    LEFT JOIN subjects sub ON es.subject_id = sub.id
    LEFT JOIN students st ON es.evaluator_id = st.id AND es.evaluator_type = 'student'
    LEFT JOIN faculty evaluator_f ON es.evaluator_id = evaluator_f.id AND es.evaluator_type = 'teacher'
    LEFT JOIN users evaluator_u ON es.evaluator_id = evaluator_u.id AND es.evaluator_type = 'head'
    LEFT JOIN faculty evaluatee_f ON es.evaluatee_id = evaluatee_f.id AND es.evaluatee_type = 'teacher'
    LEFT JOIN users evaluatee_u ON es.evaluatee_id = evaluatee_u.id AND es.evaluatee_type != 'teacher'";

    if ($selected_semester > 0) {
        $recent_evaluations_query .= " WHERE es.semester_id = " . $selected_semester;
    }

    $recent_evaluations_query .= " GROUP BY es.id, es.evaluator_id, es.evaluator_type, es.evaluatee_id, es.evaluatee_type, es.subject_id, es.semester_id, es.status, es.created_at, s.name, sub.name, st.first_name, st.last_name, evaluator_f.first_name, evaluator_f.last_name, evaluator_u.first_name, evaluator_u.last_name, evaluatee_f.first_name, evaluatee_f.last_name, evaluatee_u.first_name, evaluatee_u.last_name, es.evaluation_date
    ORDER BY es.created_at DESC LIMIT $items_per_page OFFSET $offset";

    $recent_evaluations_result = mysqli_query($conn, $recent_evaluations_query);
    if (!$recent_evaluations_result) {
        $recent_evaluations_result = false;
    }
} catch (Exception $e) {
    $recent_evaluations_result = false;
}

// Get total count for pagination with error handling
try {
    $total_count_query = "SELECT COUNT(DISTINCT es.id) as total FROM evaluation_sessions es";
    if ($selected_semester > 0) {
        $total_count_query .= " WHERE es.semester_id = " . $selected_semester;
    }
    $total_count_result = mysqli_query($conn, $total_count_query);
    if ($total_count_result) {
        $total_count = mysqli_fetch_assoc($total_count_result)['total'];
        $total_pages = ceil($total_count / $items_per_page);
    } else {
        $total_count = 0;
        $total_pages = 1;
    }
} catch (Exception $e) {
    $total_count = 0;
    $total_pages = 1;
}

// Set page title
$page_title = 'IntelliEVal Reports & Analytics';

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">IntelliEVal Reports & Analytics</h1>
    <p class="text-sm sm:text-base text-gray-600">Comprehensive evaluation reports, clustering analysis, and performance insights</p>
</div>

<!-- Report Type Navigation -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <div class="flex flex-wrap gap-2">
        <a href="?report_type=overview&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
           class="px-4 py-2 rounded-md <?php echo $report_type === 'overview' ? 'bg-seait-orange text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-colors">
            <i class="fas fa-chart-pie mr-2"></i>Overview
        </a>
        <a href="?report_type=clustering&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
           class="px-4 py-2 rounded-md <?php echo $report_type === 'clustering' ? 'bg-seait-orange text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-colors">
            <i class="fas fa-brain mr-2"></i>Clustering Analysis
        </a>
        <a href="?report_type=performance&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
           class="px-4 py-2 rounded-md <?php echo $report_type === 'performance' ? 'bg-seait-orange text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-colors">
            <i class="fas fa-trophy mr-2"></i>Performance Metrics
        </a>
        <a href="?report_type=training&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
           class="px-4 py-2 rounded-md <?php echo $report_type === 'training' ? 'bg-seait-orange text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-colors">
            <i class="fas fa-graduation-cap mr-2"></i>Training Needs
        </a>
        <a href="?report_type=detailed&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
           class="px-4 py-2 rounded-md <?php echo $report_type === 'detailed' ? 'bg-seait-orange text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-colors">
            <i class="fas fa-list-alt mr-2"></i>Detailed Reports
        </a>
    </div>
</div>

<!-- Semester Filter Form -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter by Semester</h3>
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">
        <div>
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
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Academic Year</label>
            <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <?php if ($years_result): while ($year = mysqli_fetch_assoc($years_result)): ?>
                <option value="<?php echo $year['academic_year']; ?>" <?php echo $selected_year == $year['academic_year'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($year['academic_year']); ?>
                </option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition-colors">
                <i class="fas fa-filter mr-2"></i>Apply Filter
            </button>
        </div>
    </form>

    <?php if ($selected_semester > 0): ?>
    <div class="mt-4 p-3 bg-blue-50 rounded-md">
        <p class="text-sm text-blue-800">
            <i class="fas fa-info-circle mr-2"></i>
            Showing data for selected semester period.
            <?php if ($semester_start_date && $semester_end_date): ?>
            Period: <?php echo date('M d, Y', strtotime($semester_start_date)); ?> - <?php echo date('M d, Y', strtotime($semester_end_date)); ?>
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>
</div>

<?php if ($report_type === 'overview'): ?>
<!-- Overview Report -->
<?php if (file_exists('reports/overview_report.php')): ?>
<?php include 'reports/overview_report.php'; ?>
<?php else: ?>
<div class="bg-white rounded-lg shadow-md p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Overview Report</h3>
    <p class="text-gray-600">Overview report template not found.</p>
</div>
<?php endif; ?>

<?php elseif ($report_type === 'clustering'): ?>
<!-- Clustering Analysis Report -->
<?php if (file_exists('reports/clustering_report.php')): ?>
<?php include 'reports/clustering_report.php'; ?>
<?php else: ?>
<div class="bg-white rounded-lg shadow-md p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Clustering Analysis Report</h3>
    <p class="text-gray-600">Clustering report template not found.</p>
</div>
<?php endif; ?>

<?php elseif ($report_type === 'performance'): ?>
<!-- Performance Metrics Report -->
<?php if (file_exists('reports/performance_report.php')): ?>
<?php include 'reports/performance_report.php'; ?>
<?php else: ?>
<div class="bg-white rounded-lg shadow-md p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Metrics Report</h3>
    <p class="text-gray-600">Performance report template not found.</p>
</div>
<?php endif; ?>

<?php elseif ($report_type === 'training'): ?>
<!-- Training Needs Report -->
<?php if (file_exists('reports/training_report.php')): ?>
<?php include 'reports/training_report.php'; ?>
<?php else: ?>
<div class="bg-white rounded-lg shadow-md p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Training Needs Report</h3>
    <p class="text-gray-600">Training report template not found.</p>
</div>
<?php endif; ?>

<?php elseif ($report_type === 'detailed'): ?>
<!-- Detailed Reports -->
<?php if (file_exists('reports/detailed_report.php')): ?>
<?php include 'reports/detailed_report.php'; ?>
<?php else: ?>
<div class="bg-white rounded-lg shadow-md p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Detailed Reports</h3>
    <p class="text-gray-600">Detailed report template not found.</p>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Export Options -->
<div class="bg-white rounded-lg shadow-md p-6 mt-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Export Reports</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Overview Report -->
        <div class="border border-gray-300 rounded-lg p-4">
            <div class="flex items-center mb-3">
                <i class="fas fa-chart-pie text-blue-600 mr-3"></i>
                <span class="font-semibold">Overview Report</span>
            </div>
            <div class="flex gap-2">
                <a href="export_excel_reports.php?type=overview&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
                   class="w-full bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition text-center">
                    <i class="fas fa-file-excel mr-2"></i>Export Excel
                </a>
            </div>
        </div>

        <!-- Clustering Report -->
        <div class="border border-gray-300 rounded-lg p-4">
            <div class="flex items-center mb-3">
                <i class="fas fa-brain text-purple-600 mr-3"></i>
                <span class="font-semibold">Clustering Report</span>
            </div>
            <div class="flex gap-2">
                <a href="export_excel_reports.php?type=clustering&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
                   class="w-full bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition text-center">
                    <i class="fas fa-file-excel mr-2"></i>Export Excel
                </a>
            </div>
        </div>

        <!-- Performance Report -->
        <div class="border border-gray-300 rounded-lg p-4">
            <div class="flex items-center mb-3">
                <i class="fas fa-trophy text-green-600 mr-3"></i>
                <span class="font-semibold">Performance Report</span>
            </div>
            <div class="flex gap-2">
                <a href="export_excel_reports.php?type=performance&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
                   class="w-full bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition text-center">
                    <i class="fas fa-file-excel mr-2"></i>Export Excel
                </a>
            </div>
        </div>

        <!-- Training Report -->
        <div class="border border-gray-300 rounded-lg p-4">
            <div class="flex items-center mb-3">
                <i class="fas fa-graduation-cap text-orange-600 mr-3"></i>
                <span class="font-semibold">Training Report</span>
            </div>
            <div class="flex gap-2">
                <a href="export_excel_reports.php?type=training&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
                   class="w-full bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition text-center">
                    <i class="fas fa-file-excel mr-2"></i>Export Excel
                </a>
            </div>
        </div>

        <!-- Teacher Ratings Report -->
        <div class="border border-gray-300 rounded-lg p-4">
            <div class="flex items-center mb-3">
                <i class="fas fa-star text-yellow-600 mr-3"></i>
                <span class="font-semibold">Teacher Ratings Report</span>
            </div>
            <div class="flex gap-2">
                <a href="export_teacher_ratings.php?semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
                   class="w-full bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition text-center">
                    <i class="fas fa-file-excel mr-2"></i>Export Excel
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>