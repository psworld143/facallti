<?php
/**
 * IntelliEVal - Clustering Analysis API
 * Provides JSON API access to clustering analysis results
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'clustering_analysis.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get parameters
$action = $_GET['action'] ?? '';
$semester_id = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : null;

// Initialize clustering analysis
$clustering = new ClusteringAnalysis($conn);

// Get clustering data
$clusters = [];
$insights = [];
$analysis_type = '';

switch ($action) {
    case 'teacher_clusters':
        $clusters = $clustering->clusterTeacherPerformance($semester_id, 3);
        $insights = $clustering->getClusteringInsights($clusters, 'teacher');
        $analysis_type = 'Teacher Performance';
        break;

    case 'pattern_clusters':
        $clusters = $clustering->clusterEvaluationPatterns($semester_id, 4);
        $insights = $clustering->getClusteringInsights($clusters, 'pattern');
        $analysis_type = 'Evaluation Patterns';
        break;

    case 'department_clusters':
        $clusters = $clustering->clusterDepartmentPerformance($semester_id, 3);
        $insights = $clustering->getClusteringInsights($clusters, 'department');
        $analysis_type = 'Department Performance';
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action parameter']);
        exit();
}

// Return JSON response
echo json_encode([
    'clusters' => $clusters,
    'insights' => $insights,
    'analysis_type' => $analysis_type,
    'semester_id' => $semester_id,
    'generated_at' => date('Y-m-d H:i:s')
]);
?>