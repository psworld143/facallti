<?php
/**
 * IntelliEVal - Clustering Analysis Module
 * Provides advanced data analysis using clustering algorithms
 * for teacher performance, student patterns, and department insights
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

class ClusteringAnalysis {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * K-means clustering for teacher performance analysis
     * Groups teachers based on their evaluation scores across different categories
     */
    public function clusterTeacherPerformance($semester_id = null, $k = 3) {
        // If no semester_id provided, try to get active semester
        if ($semester_id === null) {
            $active_semester_query = "SELECT id FROM semesters WHERE status = 'active' ORDER BY created_at DESC LIMIT 1";
            $active_semester_result = mysqli_query($this->conn, $active_semester_query);
            $active_semester = mysqli_fetch_assoc($active_semester_result);
            if ($active_semester) {
                $semester_id = $active_semester['id'];
            } else {
                return ['error' => 'No active semester found. Please set up an active semester first.'];
            }
        }

        // First, try to get data with semester filter
        $query = "SELECT
                    es.evaluatee_id,
                    u.first_name,
                    u.last_name,
                    AVG(er.rating_value) as avg_rating,
                    COUNT(er.id) as total_evaluations,
                    COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
                    COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
                    COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
                    COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
                    COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count,
                    COUNT(CASE WHEN er.text_response IS NOT NULL AND er.text_response != '' THEN 1 END) as text_responses_count
                  FROM evaluation_sessions es
                  JOIN users u ON es.evaluatee_id = u.id
                  JOIN evaluation_responses er ON es.id = er.evaluation_session_id
                  WHERE es.status = 'completed'
                  AND er.rating_value IS NOT NULL
                  AND u.role = 'teacher'
                  AND es.semester_id = ?";

        $query .= " GROUP BY es.evaluatee_id, u.first_name, u.last_name
                    HAVING total_evaluations >= 2
                    ORDER BY avg_rating DESC";

        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $semester_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $teachers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $teachers[] = [
                'id' => $row['evaluatee_id'],
                'name' => $row['first_name'] . ' ' . $row['last_name'],
                'avg_rating' => (float)$row['avg_rating'],
                'total_evaluations' => (int)$row['total_evaluations'],
                'excellent_percentage' => $row['total_evaluations'] > 0 ? ($row['excellent_count'] / $row['total_evaluations']) * 100 : 0,
                'very_satisfactory_percentage' => $row['total_evaluations'] > 0 ? ($row['very_satisfactory_count'] / $row['total_evaluations']) * 100 : 0,
                'satisfactory_percentage' => $row['total_evaluations'] > 0 ? ($row['satisfactory_count'] / $row['total_evaluations']) * 100 : 0,
                'good_percentage' => $row['total_evaluations'] > 0 ? ($row['good_count'] / $row['total_evaluations']) * 100 : 0,
                'poor_percentage' => $row['total_evaluations'] > 0 ? ($row['poor_count'] / $row['total_evaluations']) * 100 : 0,
                'text_responses_ratio' => $row['total_evaluations'] > 0 ? ($row['text_responses_count'] / $row['total_evaluations']) * 100 : 0
            ];
        }

        // If insufficient data with semester filter, try without semester filter
        if (count($teachers) < $k) {
            $fallback_query = "SELECT
                                es.evaluatee_id,
                                u.first_name,
                                u.last_name,
                                AVG(er.rating_value) as avg_rating,
                                COUNT(er.id) as total_evaluations,
                                COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
                                COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
                                COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
                                COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
                                COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count,
                                COUNT(CASE WHEN er.text_response IS NOT NULL AND er.text_response != '' THEN 1 END) as text_responses_count
                              FROM evaluation_sessions es
                              JOIN users u ON es.evaluatee_id = u.id
                              JOIN evaluation_responses er ON es.id = er.evaluation_session_id
                              WHERE es.status = 'completed'
                              AND er.rating_value IS NOT NULL
                              AND u.role = 'teacher'";

            $fallback_query .= " GROUP BY es.evaluatee_id, u.first_name, u.last_name
                                HAVING total_evaluations >= 1
                                ORDER BY avg_rating DESC
                                LIMIT " . max($k * 3, 10);

            $fallback_result = mysqli_query($this->conn, $fallback_query);
            $teachers = [];
            while ($row = mysqli_fetch_assoc($fallback_result)) {
                $teachers[] = [
                    'id' => $row['evaluatee_id'],
                    'name' => $row['first_name'] . ' ' . $row['last_name'],
                    'avg_rating' => (float)$row['avg_rating'],
                    'total_evaluations' => (int)$row['total_evaluations'],
                    'excellent_percentage' => $row['total_evaluations'] > 0 ? ($row['excellent_count'] / $row['total_evaluations']) * 100 : 0,
                    'very_satisfactory_percentage' => $row['total_evaluations'] > 0 ? ($row['very_satisfactory_count'] / $row['total_evaluations']) * 100 : 0,
                    'satisfactory_percentage' => $row['total_evaluations'] > 0 ? ($row['satisfactory_count'] / $row['total_evaluations']) * 100 : 0,
                    'good_percentage' => $row['total_evaluations'] > 0 ? ($row['good_count'] / $row['total_evaluations']) * 100 : 0,
                    'poor_percentage' => $row['total_evaluations'] > 0 ? ($row['poor_count'] / $row['total_evaluations']) * 100 : 0,
                    'text_responses_ratio' => $row['total_evaluations'] > 0 ? ($row['text_responses_count'] / $row['total_evaluations']) * 100 : 0
                ];
            }
        }

        // Only proceed if we have real data
        if (count($teachers) == 0) {
            return ['error' => 'No evaluation data found in the database. Please add evaluation data to perform clustering analysis.'];
        }

        if (count($teachers) < $k) {
            return ['error' => 'Insufficient evaluation data for clustering. Found ' . count($teachers) . ' teachers, but need at least ' . $k . ' teachers with evaluations.'];
        }

        $clusters = $this->kMeansClustering($teachers, $k, ['avg_rating', 'excellent_percentage', 'very_satisfactory_percentage', 'text_responses_ratio']);

        // Add metadata about data source
        $clusters['metadata'] = [
            'using_real_data' => true,
            'data_source' => 'database',
            'semester_id' => $semester_id,
            'total_teachers' => count($teachers)
        ];

        return $clusters;
    }

    /**
     * Cluster evaluation patterns by category
     */
    public function clusterEvaluationPatterns($semester_id = null, $k = 4) {
        // If no semester_id provided, try to get active semester
        if ($semester_id === null) {
            $active_semester_query = "SELECT id FROM semesters WHERE status = 'active' ORDER BY created_at DESC LIMIT 1";
            $active_semester_result = mysqli_query($this->conn, $active_semester_query);
            $active_semester = mysqli_fetch_assoc($active_semester_result);
            if ($active_semester) {
                $semester_id = $active_semester['id'];
            } else {
                return ['error' => 'No active semester found. Please set up an active semester first.'];
            }
        }

        // First, try to get data with semester filter
        $query = "SELECT
                    esc.name as subcategory_name,
                    mec.name as category_name,
                    mec.evaluation_type,
                    AVG(er.rating_value) as avg_rating,
                    COUNT(er.id) as total_responses,
                    COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
                    COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
                    COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
                    COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
                    COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count,
                    COUNT(CASE WHEN er.text_response IS NOT NULL AND er.text_response != '' THEN 1 END) as text_responses_count
                  FROM evaluation_responses er
                  JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
                  JOIN evaluation_questionnaires eq ON er.questionnaire_id = eq.id
                  JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
                  JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
                  WHERE es.status = 'completed'
                  AND er.rating_value IS NOT NULL
                  AND es.semester_id = ?";

        $query .= " GROUP BY esc.id, esc.name, mec.name, mec.evaluation_type
                    HAVING COUNT(er.id) >= 5
                    ORDER BY avg_rating DESC";

        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $semester_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $patterns = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $patterns[] = [
                'subcategory' => $row['subcategory_name'],
                'category' => $row['category_name'],
                'evaluation_type' => $row['evaluation_type'],
                'avg_rating' => (float)$row['avg_rating'],
                'total_responses' => (int)$row['total_responses'],
                'excellent_percentage' => $row['total_responses'] > 0 ? ($row['excellent_count'] / $row['total_responses']) * 100 : 0,
                'very_satisfactory_percentage' => $row['total_responses'] > 0 ? ($row['very_satisfactory_count'] / $row['total_responses']) * 100 : 0,
                'satisfactory_percentage' => $row['total_responses'] > 0 ? ($row['satisfactory_count'] / $row['total_responses']) * 100 : 0,
                'good_percentage' => $row['total_responses'] > 0 ? ($row['good_count'] / $row['total_responses']) * 100 : 0,
                'poor_percentage' => $row['total_responses'] > 0 ? ($row['poor_count'] / $row['total_responses']) * 100 : 0,
                'text_responses_ratio' => $row['total_responses'] > 0 ? ($row['text_responses_count'] / $row['total_responses']) * 100 : 0
            ];
        }

        // If insufficient data with semester filter, try without semester filter
        if (count($patterns) < $k) {
            $fallback_query = "SELECT
                                esc.name as subcategory_name,
                                mec.name as category_name,
                                mec.evaluation_type,
                                AVG(er.rating_value) as avg_rating,
                                COUNT(er.id) as total_responses,
                                COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
                                COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
                                COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
                                COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
                                COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count,
                                COUNT(CASE WHEN er.text_response IS NOT NULL AND er.text_response != '' THEN 1 END) as text_responses_count
                              FROM evaluation_responses er
                              JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
                              JOIN evaluation_questionnaires eq ON er.questionnaire_id = eq.id
                              JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
                              JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
                              WHERE es.status = 'completed'
                              AND er.rating_value IS NOT NULL";

            $fallback_query .= " GROUP BY esc.id, esc.name, mec.name, mec.evaluation_type
                                HAVING COUNT(er.id) >= 3
                                ORDER BY avg_rating DESC
                                LIMIT " . max($k * 2, 8);

            $fallback_result = mysqli_query($this->conn, $fallback_query);
            $patterns = [];
            while ($row = mysqli_fetch_assoc($fallback_result)) {
                $patterns[] = [
                    'subcategory' => $row['subcategory_name'],
                    'category' => $row['category_name'],
                    'evaluation_type' => $row['evaluation_type'],
                    'avg_rating' => (float)$row['avg_rating'],
                    'total_responses' => (int)$row['total_responses'],
                    'excellent_percentage' => $row['total_responses'] > 0 ? ($row['excellent_count'] / $row['total_responses']) * 100 : 0,
                    'very_satisfactory_percentage' => $row['total_responses'] > 0 ? ($row['very_satisfactory_count'] / $row['total_responses']) * 100 : 0,
                    'satisfactory_percentage' => $row['total_responses'] > 0 ? ($row['satisfactory_count'] / $row['total_responses']) * 100 : 0,
                    'good_percentage' => $row['total_responses'] > 0 ? ($row['good_count'] / $row['total_responses']) * 100 : 0,
                    'poor_percentage' => $row['total_responses'] > 0 ? ($row['poor_count'] / $row['total_responses']) * 100 : 0,
                    'text_responses_ratio' => $row['total_responses'] > 0 ? ($row['text_responses_count'] / $row['total_responses']) * 100 : 0
                ];
            }
        }

        // Only proceed if we have real data
        if (count($patterns) == 0) {
            return ['error' => 'No evaluation data found in the database. Please add evaluation data to perform clustering analysis.'];
        }

        if (count($patterns) < $k) {
            return ['error' => 'Insufficient evaluation data for clustering. Found ' . count($patterns) . ' categories with responses, but need at least ' . $k . ' categories with responses.'];
        }

        $clusters = $this->kMeansClustering($patterns, $k, ['avg_rating', 'excellent_percentage', 'very_satisfactory_percentage', 'text_responses_ratio']);

        // Add metadata about data source
        $clusters['metadata'] = [
            'using_real_data' => true,
            'data_source' => 'database',
            'semester_id' => $semester_id,
            'total_categories' => count($patterns)
        ];

        return $clusters;
    }

    /**
     * Cluster departments/subjects by performance
     */
    public function clusterDepartmentPerformance($semester_id = null, $k = 3) {
        // If no semester_id provided, try to get active semester
        if ($semester_id === null) {
            $active_semester_query = "SELECT id FROM semesters WHERE status = 'active' ORDER BY created_at DESC LIMIT 1";
            $active_semester_result = mysqli_query($this->conn, $active_semester_query);
            $active_semester = mysqli_fetch_assoc($active_semester_result);
            if ($active_semester) {
                $semester_id = $active_semester['id'];
            } else {
                return ['error' => 'No active semester found. Please set up an active semester first.'];
            }
        }

        // First, try to get data with semester filter
        $query = "SELECT
                    s.name as subject_name,
                    s.code as subject_code,
                    AVG(er.rating_value) as avg_rating,
                    COUNT(er.id) as total_responses,
                    COUNT(DISTINCT es.evaluatee_id) as unique_teachers,
                    COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
                    COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
                    COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
                    COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
                    COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count
                  FROM evaluation_responses er
                  JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
                  LEFT JOIN subjects s ON es.subject_id = s.id
                  WHERE es.status = 'completed'
                  AND er.rating_value IS NOT NULL
                  AND es.subject_id IS NOT NULL
                  AND es.semester_id = ?";

        $query .= " GROUP BY s.id, s.name, s.code
                    HAVING COUNT(er.id) >= 10
                    ORDER BY avg_rating DESC";

        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $semester_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $departments = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $departments[] = [
                'subject_name' => $row['subject_name'],
                'subject_code' => $row['subject_code'],
                'avg_rating' => (float)$row['avg_rating'],
                'total_responses' => (int)$row['total_responses'],
                'unique_teachers' => (int)$row['unique_teachers'],
                'excellent_percentage' => $row['total_responses'] > 0 ? ($row['excellent_count'] / $row['total_responses']) * 100 : 0,
                'very_satisfactory_percentage' => $row['total_responses'] > 0 ? ($row['very_satisfactory_count'] / $row['total_responses']) * 100 : 0,
                'satisfactory_percentage' => $row['total_responses'] > 0 ? ($row['satisfactory_count'] / $row['total_responses']) * 100 : 0,
                'good_percentage' => $row['total_responses'] > 0 ? ($row['good_count'] / $row['total_responses']) * 100 : 0,
                'poor_percentage' => $row['total_responses'] > 0 ? ($row['poor_count'] / $row['total_responses']) * 100 : 0,
                'responses_per_teacher' => $row['unique_teachers'] > 0 ? $row['total_responses'] / $row['unique_teachers'] : 0
            ];
        }

        // If insufficient data with semester filter, try without semester filter
        if (count($departments) < $k) {
            $fallback_query = "SELECT
                                s.name as subject_name,
                                s.code as subject_code,
                                AVG(er.rating_value) as avg_rating,
                                COUNT(er.id) as total_responses,
                                COUNT(DISTINCT es.evaluatee_id) as unique_teachers,
                                COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
                                COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
                                COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
                                COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
                                COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count
                              FROM evaluation_responses er
                              JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
                              LEFT JOIN subjects s ON es.subject_id = s.id
                              WHERE es.status = 'completed'
                              AND er.rating_value IS NOT NULL
                              AND es.subject_id IS NOT NULL";

            $fallback_query .= " GROUP BY s.id, s.name, s.code
                                HAVING COUNT(er.id) >= 5
                                ORDER BY avg_rating DESC
                                LIMIT " . max($k * 2, 6);

            $fallback_result = mysqli_query($this->conn, $fallback_query);
            $departments = [];
            while ($row = mysqli_fetch_assoc($fallback_result)) {
                $departments[] = [
                    'subject_name' => $row['subject_name'],
                    'subject_code' => $row['subject_code'],
                    'avg_rating' => (float)$row['avg_rating'],
                    'total_responses' => (int)$row['total_responses'],
                    'unique_teachers' => (int)$row['unique_teachers'],
                    'excellent_percentage' => $row['total_responses'] > 0 ? ($row['excellent_count'] / $row['total_responses']) * 100 : 0,
                    'very_satisfactory_percentage' => $row['total_responses'] > 0 ? ($row['very_satisfactory_count'] / $row['total_responses']) * 100 : 0,
                    'satisfactory_percentage' => $row['total_responses'] > 0 ? ($row['satisfactory_count'] / $row['total_responses']) * 100 : 0,
                    'good_percentage' => $row['total_responses'] > 0 ? ($row['good_count'] / $row['total_responses']) * 100 : 0,
                    'poor_percentage' => $row['total_responses'] > 0 ? ($row['poor_count'] / $row['total_responses']) * 100 : 0,
                    'responses_per_teacher' => $row['unique_teachers'] > 0 ? $row['total_responses'] / $row['unique_teachers'] : 0
                ];
            }
        }

        // Only proceed if we have real data
        if (count($departments) == 0) {
            return ['error' => 'No evaluation data found in the database. Please add evaluation data to perform clustering analysis.'];
        }

        if (count($departments) < $k) {
            return ['error' => 'Insufficient evaluation data for clustering. Found ' . count($departments) . ' subjects with responses, but need at least ' . $k . ' subjects with responses.'];
        }

        $clusters = $this->kMeansClustering($departments, $k, ['avg_rating', 'excellent_percentage', 'very_satisfactory_percentage', 'responses_per_teacher']);

        // Add metadata about data source
        $clusters['metadata'] = [
            'using_real_data' => true,
            'data_source' => 'database',
            'semester_id' => $semester_id,
            'total_subjects' => count($departments)
        ];

        return $clusters;
    }

    /**
     * K-means clustering algorithm implementation
     */
    private function kMeansClustering($data, $k, $features) {
        if (count($data) < $k) {
            return ['error' => 'Insufficient data points for clustering'];
        }

        // Initialize centroids randomly
        $centroids = [];
        for ($i = 0; $i < $k; $i++) {
            $centroids[$i] = [];
            foreach ($features as $feature) {
                $values = array_column($data, $feature);
                $min = min($values);
                $max = max($values);
                $centroids[$i][$feature] = $min + (($max - $min) * rand(0, 100) / 100);
            }
        }

        $iterations = 0;
        $max_iterations = 100;
        $converged = false;

        while (!$converged && $iterations < $max_iterations) {
            $iterations++;

            // Assign points to clusters
            $clusters = array_fill(0, $k, []);
            foreach ($data as $index => $point) {
                $min_distance = PHP_FLOAT_MAX;
                $closest_cluster = 0;

                foreach ($centroids as $cluster_id => $centroid) {
                    $distance = $this->calculateEuclideanDistance($point, $centroid, $features);
                    if ($distance < $min_distance) {
                        $min_distance = $distance;
                        $closest_cluster = $cluster_id;
                    }
                }

                $clusters[$closest_cluster][] = $index;
            }

            // Update centroids
            $new_centroids = [];
            $converged = true;

            for ($i = 0; $i < $k; $i++) {
                if (empty($clusters[$i])) {
                    // If cluster is empty, keep old centroid
                    $new_centroids[$i] = $centroids[$i];
                    continue;
                }

                $new_centroids[$i] = [];
                foreach ($features as $feature) {
                    $sum = 0;
                    foreach ($clusters[$i] as $point_index) {
                        $sum += $data[$point_index][$feature];
                    }
                    $new_centroids[$i][$feature] = $sum / count($clusters[$i]);
                }

                // Check convergence
                foreach ($features as $feature) {
                    if (abs($new_centroids[$i][$feature] - $centroids[$i][$feature]) > 0.001) {
                        $converged = false;
                    }
                }
            }

            $centroids = $new_centroids;
        }

        // Prepare results
        $results = [];
        for ($i = 0; $i < $k; $i++) {
            $cluster_data = [];
            foreach ($clusters[$i] as $point_index) {
                $cluster_data[] = $data[$point_index];
            }

            $results[$i] = [
                'cluster_id' => $i,
                'centroid' => $centroids[$i],
                'size' => count($clusters[$i]),
                'data' => $cluster_data,
                'iterations' => $iterations
            ];
        }

        return $results;
    }

    /**
     * Calculate Euclidean distance between two points
     */
    private function calculateEuclideanDistance($point1, $point2, $features) {
        $sum = 0;
        foreach ($features as $feature) {
            $diff = $point1[$feature] - $point2[$feature];
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }

    /**
     * Get clustering insights and recommendations
     */
    public function getClusteringInsights($clusters, $type = 'teacher') {
        $insights = [];

        // Check if clusters is an error string or empty
        if (!is_array($clusters) || isset($clusters['error']) || empty($clusters)) {
            return $insights;
        }

        foreach ($clusters as $cluster_id => $cluster) {
            // Skip metadata key
            if ($cluster_id === 'metadata') {
                continue;
            }

            // Check if cluster has required keys
            if (!isset($cluster['size']) || !isset($cluster['centroid']) || !isset($cluster['data'])) {
                continue;
            }

            if ($cluster['size'] == 0) continue;

            $centroid = $cluster['centroid'];
            $size = $cluster['size'];

            switch ($type) {
                case 'teacher':
                    $insights[$cluster_id] = $this->getTeacherClusterInsights($centroid, $size, $cluster['data']);
                    break;
                case 'pattern':
                    $insights[$cluster_id] = $this->getPatternClusterInsights($centroid, $size, $cluster['data']);
                    break;
                case 'department':
                    $insights[$cluster_id] = $this->getDepartmentClusterInsights($centroid, $size, $cluster['data']);
                    break;
            }
        }

        return $insights;
    }

    private function getTeacherClusterInsights($centroid, $size, $data) {
        $avg_rating = $centroid['avg_rating'];
        $excellent_pct = $centroid['excellent_percentage'];

        if ($avg_rating >= 4.5 && $excellent_pct >= 60) {
            return [
                'label' => 'High Performers',
                'description' => 'Teachers with exceptional performance across all evaluation categories',
                'recommendations' => [
                    'Consider for leadership roles',
                    'Use as mentors for other teachers',
                    'Recognize achievements publicly'
                ],
                'size' => $size,
                'avg_rating' => round($avg_rating, 2)
            ];
        } elseif ($avg_rating >= 3.5 && $excellent_pct >= 30) {
            return [
                'label' => 'Good Performers',
                'description' => 'Teachers with solid performance and room for improvement',
                'recommendations' => [
                    'Provide targeted professional development',
                    'Encourage peer mentoring',
                    'Set specific improvement goals'
                ],
                'size' => $size,
                'avg_rating' => round($avg_rating, 2)
            ];
        } else {
            return [
                'label' => 'Needs Support',
                'description' => 'Teachers requiring additional support and development',
                'recommendations' => [
                    'Implement improvement plans',
                    'Provide intensive mentoring',
                    'Regular performance check-ins'
                ],
                'size' => $size,
                'avg_rating' => round($avg_rating, 2)
            ];
        }
    }

    private function getPatternClusterInsights($centroid, $size, $data) {
        $avg_rating = $centroid['avg_rating'];
        $text_ratio = $centroid['text_responses_ratio'];

        // Create more specific labels based on multiple factors
        if ($avg_rating >= 4.5) {
            return [
                'label' => 'Excellent Categories',
                'description' => 'Evaluation categories with outstanding performance and high ratings',
                'recommendations' => [
                    'Maintain current standards',
                    'Share best practices',
                    'Document successful approaches'
                ],
                'size' => $size,
                'avg_rating' => round($avg_rating, 2)
            ];
        } elseif ($avg_rating >= 4.0) {
            return [
                'label' => 'High-Rated Categories',
                'description' => 'Categories with consistently good performance and ratings',
                'recommendations' => [
                    'Continue current practices',
                    'Identify areas for excellence',
                    'Encourage peer learning'
                ],
                'size' => $size,
                'avg_rating' => round($avg_rating, 2)
            ];
        } elseif ($avg_rating >= 3.5) {
            return [
                'label' => 'Good Categories',
                'description' => 'Categories with satisfactory performance with room for improvement',
                'recommendations' => [
                    'Target specific improvement areas',
                    'Provide focused training',
                    'Set performance goals'
                ],
                'size' => $size,
                'avg_rating' => round($avg_rating, 2)
            ];
        } elseif ($avg_rating >= 3.0) {
            return [
                'label' => 'Average Categories',
                'description' => 'Categories with moderate performance levels requiring attention',
                'recommendations' => [
                    'Develop improvement plans',
                    'Provide targeted training',
                    'Monitor progress closely'
                ],
                'size' => $size,
                'avg_rating' => round($avg_rating, 2)
            ];
        } else {
            return [
                'label' => 'Needs Attention',
                'description' => 'Categories requiring immediate attention and improvement',
                'recommendations' => [
                    'Develop action plans',
                    'Allocate additional resources',
                    'Regular progress reviews'
                ],
                'size' => $size,
                'avg_rating' => round($avg_rating, 2)
            ];
        }
    }

    private function getDepartmentClusterInsights($centroid, $size, $data) {
        $avg_rating = $centroid['avg_rating'];
        $responses_per_teacher = $centroid['responses_per_teacher'];

        if ($avg_rating >= 4.0 && $responses_per_teacher >= 10) {
            return [
                'label' => 'High-Performing Departments',
                'description' => 'Departments with excellent performance and good evaluation coverage',
                'recommendations' => [
                    'Maintain current standards',
                    'Share best practices with other departments',
                    'Consider for additional resources'
                ],
                'size' => $size,
                'avg_rating' => round($avg_rating, 2)
            ];
        } elseif ($avg_rating >= 3.0) {
            return [
                'label' => 'Standard Departments',
                'description' => 'Departments with acceptable performance levels',
                'recommendations' => [
                    'Identify improvement opportunities',
                    'Enhance evaluation participation',
                    'Provide department-specific training'
                ],
                'size' => $size,
                'avg_rating' => round($avg_rating, 2)
            ];
        } else {
            return [
                'label' => 'Departments Needing Support',
                'description' => 'Departments requiring immediate attention and support',
                'recommendations' => [
                    'Develop comprehensive improvement plans',
                    'Increase evaluation participation',
                    'Provide intensive department support'
                ],
                'size' => $size,
                'avg_rating' => round($avg_rating, 2)
            ];
        }
    }
}

// Note: This file is designed to be included by other files, not accessed directly.
// For API access, use clustering_api.php instead.
?>