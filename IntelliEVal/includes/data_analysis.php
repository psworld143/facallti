<?php
/**
 * Comprehensive Data Analysis Class for IntelliEVal System
 * Handles normalization, sentiment analysis, missing values, and ML techniques
 */

class DataAnalysis {
    private $conn;
    private $vader_lexicon = [];
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadVaderLexicon();
    }
    
    /**
     * Load VADER sentiment lexicon
     */
    private function loadVaderLexicon() {
        $lexicon_file = __DIR__ . '/vader_lexicon.txt';
        if (file_exists($lexicon_file)) {
            $lines = file($lexicon_file, FILE_IGNORE_NEW_LINES);
            foreach ($lines as $line) {
                $parts = explode("\t", $line);
                if (count($parts) >= 2) {
                    $word = strtolower(trim($parts[0]));
                    $score = (float)trim($parts[1]);
                    $this->vader_lexicon[$word] = $score;
                }
            }
            error_log("Loaded " . count($this->vader_lexicon) . " VADER lexicon entries");
            
            // Debug: Check a few entries
            $sample_words = ['good', 'bad', 'excellent', 'terrible', 'love', 'hate'];
            foreach ($sample_words as $word) {
                if (isset($this->vader_lexicon[$word])) {
                    error_log("Sample word '$word' has score: " . $this->vader_lexicon[$word]);
                }
            }
        } else {
            error_log("VADER lexicon file not found: " . $lexicon_file);
        }
    }
    
    /**
     * Normalize data using Min-Max normalization
     */
    public function normalizeData($data, $min = null, $max = null) {
        if (empty($data)) return [];
        
        if ($min === null) $min = min($data);
        if ($max === null) $max = max($data);
        
        $range = $max - $min;
        if ($range == 0) return array_fill(0, count($data), 0.5);
        
        $normalized = [];
        foreach ($data as $value) {
            $normalized[] = ($value - $min) / $range;
        }
        
        return $normalized;
    }
    
    /**
     * Normalize data using Z-score standardization
     */
    public function standardizeData($data) {
        if (empty($data)) return [];
        
        $mean = array_sum($data) / count($data);
        $variance = 0;
        
        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= count($data);
        $std_dev = sqrt($variance);
        
        if ($std_dev == 0) return array_fill(0, count($data), 0);
        
        $standardized = [];
        foreach ($data as $value) {
            $standardized[] = ($value - $mean) / $std_dev;
        }
        
        return $standardized;
    }
    
    /**
     * Handle missing values using various strategies
     */
    public function handleMissingValues($data, $strategy = 'mean') {
        if (empty($data)) return [];
        
        switch ($strategy) {
            case 'mean':
                return $this->fillMissingWithMean($data);
            case 'median':
                return $this->fillMissingWithMedian($data);
            case 'mode':
                return $this->fillMissingWithMode($data);
            case 'interpolation':
                return $this->fillMissingWithInterpolation($data);
            case 'forward_fill':
                return $this->fillMissingWithForwardFill($data);
            default:
                return $data;
        }
    }
    
    private function fillMissingWithMean($data) {
        $valid_values = array_filter($data, function($val) {
            return $val !== null && $val !== '' && is_numeric($val);
        });
        
        if (empty($valid_values)) return $data;
        
        $mean = array_sum($valid_values) / count($valid_values);
        
        return array_map(function($val) use ($mean) {
            return ($val === null || $val === '' || !is_numeric($val)) ? $mean : $val;
        }, $data);
    }
    
    private function fillMissingWithMedian($data) {
        $valid_values = array_filter($data, function($val) {
            return $val !== null && $val !== '' && is_numeric($val);
        });
        
        if (empty($valid_values)) return $data;
        
        sort($valid_values);
        $count = count($valid_values);
        $median = $count % 2 == 0 
            ? ($valid_values[$count/2 - 1] + $valid_values[$count/2]) / 2
            : $valid_values[floor($count/2)];
        
        return array_map(function($val) use ($median) {
            return ($val === null || $val === '' || !is_numeric($val)) ? $median : $val;
        }, $data);
    }
    
    private function fillMissingWithMode($data) {
        $valid_values = array_filter($data, function($val) {
            return $val !== null && $val !== '';
        });
        
        if (empty($valid_values)) return $data;
        
        $frequency = array_count_values($valid_values);
        $mode = array_keys($frequency, max($frequency))[0];
        
        return array_map(function($val) use ($mode) {
            return ($val === null || $val === '') ? $mode : $val;
        }, $data);
    }
    
    private function fillMissingWithInterpolation($data) {
        $result = $data;
        $n = count($data);
        
        for ($i = 1; $i < $n - 1; $i++) {
            if ($result[$i] === null || $result[$i] === '' || !is_numeric($result[$i])) {
                $prev = $i - 1;
                $next = $i + 1;
                
                // Find previous valid value
                while ($prev >= 0 && ($result[$prev] === null || $result[$prev] === '' || !is_numeric($result[$prev]))) {
                    $prev--;
                }
                
                // Find next valid value
                while ($next < $n && ($result[$next] === null || $result[$next] === '' || !is_numeric($result[$next]))) {
                    $next++;
                }
                
                if ($prev >= 0 && $next < $n && is_numeric($result[$prev]) && is_numeric($result[$next])) {
                    $result[$i] = $result[$prev] + ($result[$next] - $result[$prev]) * ($i - $prev) / ($next - $prev);
                }
            }
        }
        
        return $result;
    }
    
    private function fillMissingWithForwardFill($data) {
        $result = $data;
        $last_valid = null;
        
        for ($i = 0; $i < count($result); $i++) {
            if ($result[$i] === null || $result[$i] === '' || !is_numeric($result[$i])) {
                if ($last_valid !== null) {
                    $result[$i] = $last_valid;
                }
            } else {
                $last_valid = $result[$i];
            }
        }
        
        return $result;
    }
    
    /**
     * VADER Sentiment Analysis
     */
    public function analyzeSentiment($text) {
        if (empty($text)) return ['compound' => 0, 'positive' => 0, 'negative' => 0, 'neutral' => 0];
        
        // Decode HTML entities to proper characters
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strtolower(trim($text));
        $words = preg_split('/\s+/', $text);
        
        error_log("Analyzing sentiment for text: " . substr($text, 0, 100) . "...");
        error_log("Words found: " . count($words));
        
        $positive_score = 0;
        $negative_score = 0;
        $neutral_score = 0;
        $vader_words_found = 0;
        foreach ($words as $word) {
            $word = preg_replace('/[^a-z]/', '', $word);
            if (isset($this->vader_lexicon[$word])) {
                $vader_words_found++;
                $score = $this->vader_lexicon[$word];
                if ($score > 0) {
                    $positive_score += $score;
                } elseif ($score < 0) {
                    $negative_score += abs($score);
                } else {
                    $neutral_score += 1;
                }
            }
        }
        error_log("VADER words found: " . $vader_words_found);
        
        // Normalize scores
        $total_words = count($words);
        if ($total_words > 0) {
            $positive_score /= $total_words;
            $negative_score /= $total_words;
            $neutral_score /= $total_words;
        }
        
        // Calculate compound score
        $compound_score = $positive_score - $negative_score;
        
        return [
            'compound' => $compound_score,
            'positive' => $positive_score,
            'negative' => $negative_score,
            'neutral' => $neutral_score,
            'sentiment' => $this->classifySentiment($compound_score)
        ];
    }
    
    private function classifySentiment($compound_score) {
        if ($compound_score >= 0.05) return 'positive';
        if ($compound_score <= -0.05) return 'negative';
        return 'neutral';
    }
    
    /**
     * K-Means Clustering
     */
    public function kMeansClustering($data, $k = 3, $max_iterations = 100) {
        if (empty($data)) {
            return ['clusters' => [], 'centroids' => [], 'iterations' => 0];
        }
        
        // Adjust k if we have fewer data points
        if (count($data) < $k) {
            $k = max(1, count($data));
        }
        
        // Initialize centroids randomly
        $centroids = $this->initializeCentroids($data, $k);
        $clusters = [];
        $iterations = 0;
        
        while ($iterations < $max_iterations) {
            $old_centroids = $centroids;
            
            // Assign points to clusters
            $clusters = $this->assignToClusters($data, $centroids);
            
            // Update centroids
            $centroids = $this->updateCentroids($data, $clusters, $k);
            
            // Check convergence
            if ($this->centroidsConverged($old_centroids, $centroids)) {
                break;
            }
            
            $iterations++;
        }
        
        return [
            'clusters' => $clusters,
            'centroids' => $centroids,
            'iterations' => $iterations,
            'silhouette_score' => $this->calculateSilhouetteScore($data, $clusters, $centroids)
        ];
    }
    
    private function initializeCentroids($data, $k) {
        $centroids = [];
        $data_size = count($data);
        
        for ($i = 0; $i < $k; $i++) {
            $random_index = rand(0, $data_size - 1);
            $centroids[] = $data[$random_index];
        }
        
        return $centroids;
    }
    
    private function assignToClusters($data, $centroids) {
        $clusters = array_fill(0, count($centroids), []);
        
        foreach ($data as $point_index => $point) {
            $min_distance = PHP_FLOAT_MAX;
            $closest_centroid = 0;
            
            foreach ($centroids as $centroid_index => $centroid) {
                $distance = $this->euclideanDistance($point, $centroid);
                if ($distance < $min_distance) {
                    $min_distance = $distance;
                    $closest_centroid = $centroid_index;
                }
            }
            
            $clusters[$closest_centroid][] = $point_index;
        }
        
        return $clusters;
    }
    
    private function updateCentroids($data, $clusters, $k) {
        $centroids = [];
        
        for ($i = 0; $i < $k; $i++) {
            if (empty($clusters[$i])) {
                // If cluster is empty, use a random point
                $centroids[] = $data[array_rand($data)];
                continue;
            }
            
            $cluster_points = array_map(function($index) use ($data) {
                return $data[$index];
            }, $clusters[$i]);
            
            $centroids[] = $this->calculateMean($cluster_points);
        }
        
        return $centroids;
    }
    
    private function centroidsConverged($old_centroids, $new_centroids, $tolerance = 0.001) {
        foreach ($old_centroids as $i => $old_centroid) {
            if ($this->euclideanDistance($old_centroid, $new_centroids[$i]) > $tolerance) {
                return false;
            }
        }
        return true;
    }
    
    private function euclideanDistance($point1, $point2) {
        if (is_array($point1) && is_array($point2)) {
            $sum = 0;
            foreach ($point1 as $i => $val1) {
                $sum += pow($val1 - $point2[$i], 2);
            }
            return sqrt($sum);
        }
        return abs($point1 - $point2);
    }
    
    private function calculateMean($points) {
        if (empty($points)) return 0;
        
        if (is_array($points[0])) {
            // Multi-dimensional points
            $dimensions = count($points[0]);
            $mean = array_fill(0, $dimensions, 0);
            
            foreach ($points as $point) {
                for ($i = 0; $i < $dimensions; $i++) {
                    $mean[$i] += $point[$i];
                }
            }
            
            for ($i = 0; $i < $dimensions; $i++) {
                $mean[$i] /= count($points);
            }
            
            return $mean;
        } else {
            // Single-dimensional points
            return array_sum($points) / count($points);
        }
    }
    
    private function calculateSilhouetteScore($data, $clusters, $centroids) {
        $total_silhouette = 0;
        $total_points = 0;
        
        foreach ($clusters as $cluster_id => $cluster_points) {
            foreach ($cluster_points as $point_index) {
                $point = $data[$point_index];
                
                // Calculate intra-cluster distance (a)
                $intra_distance = 0;
                $intra_count = 0;
                foreach ($cluster_points as $other_index) {
                    if ($other_index != $point_index) {
                        $intra_distance += $this->euclideanDistance($point, $data[$other_index]);
                        $intra_count++;
                    }
                }
                $intra_distance = $intra_count > 0 ? $intra_distance / $intra_count : 0;
                
                // Calculate nearest inter-cluster distance (b)
                $inter_distances = [];
                foreach ($clusters as $other_cluster_id => $other_cluster_points) {
                    if ($other_cluster_id != $cluster_id && !empty($other_cluster_points)) {
                        $distance = 0;
                        foreach ($other_cluster_points as $other_index) {
                            $distance += $this->euclideanDistance($point, $data[$other_index]);
                        }
                        $inter_distances[] = $distance / count($other_cluster_points);
                    }
                }
                $inter_distance = !empty($inter_distances) ? min($inter_distances) : 0;
                
                // Calculate silhouette score for this point
                if ($intra_distance == 0 && $inter_distance == 0) {
                    $silhouette = 0;
                } else {
                    $silhouette = ($inter_distance - $intra_distance) / max($intra_distance, $inter_distance);
                }
                
                $total_silhouette += $silhouette;
                $total_points++;
            }
        }
        
        return $total_points > 0 ? $total_silhouette / $total_points : 0;
    }
    
    /**
     * Decision Tree Classification
     */
    public function decisionTreeClassification($features, $labels, $test_features) {
        // Simple decision tree implementation
        $tree = $this->buildDecisionTree($features, $labels);
        return $this->predictWithTree($tree, $test_features);
    }
    
    private function buildDecisionTree($features, $labels, $max_depth = 5) {
        if (empty($features) || empty($labels)) return null;
        
        $unique_labels = array_unique($labels);
        if (count($unique_labels) == 1) {
            return ['type' => 'leaf', 'value' => $unique_labels[0]];
        }
        
        if (count($features) == 0 || count($features[0]) == 0) {
            return ['type' => 'leaf', 'value' => $this->getMostCommon($labels)];
        }
        
        $best_split = $this->findBestSplit($features, $labels);
        
        if ($best_split === null) {
            return ['type' => 'leaf', 'value' => $this->getMostCommon($labels)];
        }
        
        $left_features = [];
        $left_labels = [];
        $right_features = [];
        $right_labels = [];
        
        foreach ($features as $i => $feature) {
            if ($feature[$best_split['feature_index']] <= $best_split['threshold']) {
                $left_features[] = $feature;
                $left_labels[] = $labels[$i];
            } else {
                $right_features[] = $feature;
                $right_labels[] = $labels[$i];
            }
        }
        
        return [
            'type' => 'node',
            'feature_index' => $best_split['feature_index'],
            'threshold' => $best_split['threshold'],
            'left' => $this->buildDecisionTree($left_features, $left_labels, $max_depth - 1),
            'right' => $this->buildDecisionTree($right_features, $right_labels, $max_depth - 1)
        ];
    }
    
    private function findBestSplit($features, $labels) {
        $best_gain = -1;
        $best_split = null;
        
        $num_features = count($features[0]);
        
        for ($feature_index = 0; $feature_index < $num_features; $feature_index++) {
            $values = array_column($features, $feature_index);
            $unique_values = array_unique($values);
            
            foreach ($unique_values as $threshold) {
                $gain = $this->calculateInformationGain($features, $labels, $feature_index, $threshold);
                
                if ($gain > $best_gain) {
                    $best_gain = $gain;
                    $best_split = ['feature_index' => $feature_index, 'threshold' => $threshold];
                }
            }
        }
        
        return $best_split;
    }
    
    private function calculateInformationGain($features, $labels, $feature_index, $threshold) {
        $parent_entropy = $this->calculateEntropy($labels);
        
        $left_labels = [];
        $right_labels = [];
        
        foreach ($features as $i => $feature) {
            if ($feature[$feature_index] <= $threshold) {
                $left_labels[] = $labels[$i];
            } else {
                $right_labels[] = $labels[$i];
            }
        }
        
        $left_entropy = $this->calculateEntropy($left_labels);
        $right_entropy = $this->calculateEntropy($right_labels);
        
        $left_weight = count($left_labels) / count($labels);
        $right_weight = count($right_labels) / count($labels);
        
        return $parent_entropy - ($left_weight * $left_entropy + $right_weight * $right_entropy);
    }
    
    private function calculateEntropy($labels) {
        $counts = array_count_values($labels);
        $entropy = 0;
        $total = count($labels);
        
        foreach ($counts as $count) {
            $probability = $count / $total;
            $entropy -= $probability * log($probability, 2);
        }
        
        return $entropy;
    }
    
    private function getMostCommon($labels) {
        $counts = array_count_values($labels);
        return array_keys($counts, max($counts))[0];
    }
    
    private function predictWithTree($tree, $features) {
        if ($tree['type'] == 'leaf') {
            return $tree['value'];
        }
        
        if ($features[$tree['feature_index']] <= $tree['threshold']) {
            return $this->predictWithTree($tree['left'], $features);
        } else {
            return $this->predictWithTree($tree['right'], $features);
        }
    }
    
    /**
     * Naive Bayes Classification
     */
    public function naiveBayesClassification($features, $labels, $test_features) {
        $classes = array_unique($labels);
        $class_probabilities = [];
        $feature_probabilities = [];
        
        // Calculate class probabilities
        foreach ($classes as $class) {
            $class_count = array_count_values($labels)[$class];
            $class_probabilities[$class] = $class_count / count($labels);
        }
        
        // Calculate feature probabilities for each class
        foreach ($classes as $class) {
            $class_indices = array_keys($labels, $class);
            $class_features = array_map(function($index) use ($features) {
                return $features[$index];
            }, $class_indices);
            
            $feature_probabilities[$class] = [];
            $num_features = count($features[0]);
            
            for ($i = 0; $i < $num_features; $i++) {
                $feature_values = array_column($class_features, $i);
                $feature_probabilities[$class][$i] = $this->calculateFeatureProbability($feature_values, $test_features[$i]);
            }
        }
        
        // Calculate posterior probabilities
        $posteriors = [];
        foreach ($classes as $class) {
            $posterior = $class_probabilities[$class];
            for ($i = 0; $i < count($test_features); $i++) {
                $posterior *= $feature_probabilities[$class][$i];
            }
            $posteriors[$class] = $posterior;
        }
        
        // Return class with highest posterior probability
        return array_keys($posteriors, max($posteriors))[0];
    }
    
    private function calculateFeatureProbability($feature_values, $test_value) {
        // Simple Gaussian probability calculation
        $mean = array_sum($feature_values) / count($feature_values);
        $variance = 0;
        
        foreach ($feature_values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= count($feature_values);
        $std_dev = sqrt($variance);
        
        if ($std_dev == 0) return 1;
        
        $z_score = ($test_value - $mean) / $std_dev;
        return exp(-0.5 * pow($z_score, 2)) / ($std_dev * sqrt(2 * M_PI));
    }
    
    /**
     * Calculate descriptive statistics
     */
    public function calculateDescriptiveStatistics($data) {
        if (empty($data)) return [];
        
        $n = count($data);
        $mean = array_sum($data) / $n;
        
        $variance = 0;
        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= $n;
        $std_dev = sqrt($variance);
        
        sort($data);
        $median = $n % 2 == 0 
            ? ($data[$n/2 - 1] + $data[$n/2]) / 2
            : $data[floor($n/2)];
        
        $mode = $this->getMostCommon($data);
        
        $min = min($data);
        $max = max($data);
        $range = $max - $min;
        
        return [
            'count' => $n,
            'mean' => $mean,
            'median' => $median,
            'mode' => $mode,
            'std_dev' => $std_dev,
            'variance' => $variance,
            'min' => $min,
            'max' => $max,
            'range' => $range,
            'skewness' => $this->calculateSkewness($data, $mean, $std_dev),
            'kurtosis' => $this->calculateKurtosis($data, $mean, $std_dev)
        ];
    }
    
    private function calculateSkewness($data, $mean, $std_dev) {
        if ($std_dev == 0) return 0;
        
        $n = count($data);
        $sum = 0;
        
        foreach ($data as $value) {
            $sum += pow(($value - $mean) / $std_dev, 3);
        }
        
        return ($sum / $n) * sqrt($n * ($n - 1)) / ($n - 2);
    }
    
    private function calculateKurtosis($data, $mean, $std_dev) {
        if ($std_dev == 0) return 0;
        
        $n = count($data);
        $sum = 0;
        
        foreach ($data as $value) {
            $sum += pow(($value - $mean) / $std_dev, 4);
        }
        
        return ($sum / $n) - 3;
    }
    
    /**
     * Analyze text feedback with sentiment analysis
     */
    public function analyzeTextFeedback($feedback_data) {
        $results = [];
        
        foreach ($feedback_data as $feedback) {
            $sentiment = $this->analyzeSentiment($feedback['text']);
            $results[] = [
                'id' => $feedback['id'],
                'text' => $feedback['text'],
                'sentiment' => $sentiment,
                'rating' => $feedback['rating'] ?? null,
                'teacher_name' => $feedback['teacher_name'] ?? 'Unknown Teacher',
                'student_name' => $feedback['student_name'] ?? 'Anonymous Student',
                'date' => $feedback['date'] ?? null
            ];
        }
        
        return $results;
    }
    
    /**
     * Cluster faculty based on evaluation patterns
     */
    public function clusterFacultyByEvaluationPatterns($faculty_data, $k = 3) {
        $features = [];
        $faculty_ids = [];
        
        foreach ($faculty_data as $faculty) {
            $features[] = [
                $faculty['avg_rating'] ?? 0,
                $faculty['total_evaluations'] ?? 0,
                $faculty['positive_feedback_ratio'] ?? 0,
                $faculty['negative_feedback_ratio'] ?? 0
            ];
            $faculty_ids[] = $faculty['id'];
        }
        
        // Normalize features
        $normalized_features = [];
        $num_features = count($features[0]);
        
        for ($i = 0; $i < $num_features; $i++) {
            $feature_values = array_column($features, $i);
            $normalized_values = $this->normalizeData($feature_values);
            
            foreach ($normalized_values as $j => $value) {
                $normalized_features[$j][$i] = $value;
            }
        }
        
        // Perform clustering
        $clustering_result = $this->kMeansClustering($normalized_features, $k);
        
        // Map results back to faculty
        $faculty_clusters = [];
        foreach ($clustering_result['clusters'] as $cluster_id => $cluster_points) {
            foreach ($cluster_points as $point_index) {
                $faculty_clusters[] = [
                    'faculty_id' => $faculty_ids[$point_index],
                    'cluster' => $cluster_id,
                    'centroid_distance' => $this->euclideanDistance(
                        $normalized_features[$point_index], 
                        $clustering_result['centroids'][$cluster_id]
                    )
                ];
            }
        }
        
        return [
            'faculty_clusters' => $faculty_clusters,
            'clustering_metrics' => [
                'silhouette_score' => $clustering_result['silhouette_score'],
                'iterations' => $clustering_result['iterations']
            ],
            'centroids' => $clustering_result['centroids']
        ];
    }
    
    /**
     * Predict performance based on historical data
     */
    public function predictPerformance($historical_data, $current_features) {
        // Prepare training data
        $features = [];
        $labels = [];
        
        foreach ($historical_data as $record) {
            $features[] = [
                $record['avg_rating'],
                $record['total_evaluations'],
                $record['experience_years'],
                $record['positive_feedback_ratio']
            ];
            $labels[] = $record['performance_category'];
        }
        
        // Normalize features
        $normalized_features = [];
        $num_features = count($features[0]);
        
        for ($i = 0; $i < $num_features; $i++) {
            $feature_values = array_column($features, $i);
            $normalized_values = $this->normalizeData($feature_values);
            
            foreach ($normalized_values as $j => $value) {
                $normalized_features[$j][$i] = $value;
            }
        }
        
        // Normalize current features
        $normalized_current = [];
        for ($i = 0; $i < $num_features; $i++) {
            $feature_values = array_column($features, $i);
            $min = min($feature_values);
            $max = max($feature_values);
            $range = $max - $min;
            
            if ($range == 0) {
                $normalized_current[$i] = 0.5;
            } else {
                $normalized_current[$i] = ($current_features[$i] - $min) / $range;
            }
        }
        
        // Make predictions using different algorithms
        $decision_tree_prediction = $this->decisionTreeClassification($normalized_features, $labels, $normalized_current);
        $naive_bayes_prediction = $this->naiveBayesClassification($normalized_features, $labels, $normalized_current);
        
        return [
            'decision_tree_prediction' => $decision_tree_prediction,
            'naive_bayes_prediction' => $naive_bayes_prediction,
            'confidence_scores' => $this->calculatePredictionConfidence($normalized_features, $labels, $normalized_current)
        ];
    }
    
    private function calculatePredictionConfidence($features, $labels, $test_features) {
        // Simple confidence calculation based on nearest neighbors
        $distances = [];
        
        foreach ($features as $i => $feature) {
            $distance = $this->euclideanDistance($feature, $test_features);
            $distances[] = ['distance' => $distance, 'label' => $labels[$i]];
        }
        
        usort($distances, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });
        
        // Get top 5 nearest neighbors
        $k = min(5, count($distances));
        $nearest_labels = array_slice(array_column($distances, 'label'), 0, $k);
        
        $label_counts = array_count_values($nearest_labels);
        $max_count = max($label_counts);
        
        return $max_count / $k;
    }
}
?>
