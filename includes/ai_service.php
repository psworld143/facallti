<?php
/**
 * AI Service - Multi-Provider AI Integration
 * 
 * This service provides a unified interface for multiple AI providers
 * with automatic fallback and provider switching.
 */

class AIService {
    private $conn;
    private $providers;
    private $fallback_chain;
    
    public function __construct($database_connection = null) {
        if ($database_connection) {
            $this->conn = $database_connection;
            // Load providers from database when database connection is available
            $this->loadProvidersFromDatabase();
        } else {
            // Fallback to config file if no database connection
            require_once __DIR__ . '/../config/ai_providers.php';
            global $ai_providers, $fallback_chain;
            $this->providers = $ai_providers;
            $this->fallback_chain = $fallback_chain;
        }
    }
    
    /**
     * Load providers from database
     */
    private function loadProvidersFromDatabase() {
        if (!$this->conn) {
            return false;
        }
        
        try {
            $query = "SELECT * FROM ai_providers WHERE enabled = 1 AND api_key IS NOT NULL AND api_key != '' ORDER BY priority ASC";
            $result = mysqli_query($this->conn, $query);
            
            if (!$result) {
                error_log("Error loading AI providers from database: " . mysqli_error($this->conn));
                return false;
            }
            
            $this->providers = [];
            $this->fallback_chain = [];
            
            while ($provider = mysqli_fetch_assoc($result)) {
                $this->providers[$provider['name']] = [
                    'id' => $provider['id'],
                    'name' => $provider['name'],
                    'display_name' => $provider['display_name'],
                    'provider_type' => $provider['provider_type'],
                    'api_url' => $provider['api_url'],
                    'model' => $provider['model'],
                    'api_key' => $provider['api_key'],
                    'max_tokens' => (int)$provider['max_tokens'], // Ensure integer type
                    'temperature' => (float)$provider['temperature'], // Ensure float type
                    'enabled' => $provider['enabled'],
                    'priority' => $provider['priority'],
                    'cost_per_token' => $provider['cost_per_token'],
                    'description' => $provider['description'],
                    'instructions' => $provider['instructions']
                ];
                $this->fallback_chain[] = $provider['name'];
            }
            
            error_log("Loaded " . count($this->providers) . " AI providers from database: " . implode(', ', $this->fallback_chain));
            return true;
        } catch (Exception $e) {
            error_log("Exception loading AI providers: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Refresh providers from database (force reload)
     */
    public function refreshProviders() {
        if ($this->conn) {
            $this->loadProvidersFromDatabase();
            return true;
        }
        return false;
    }
    
    /**
     * Get status of all providers
     */
    public function getProviderStatus() {
        $status = [];
        foreach ($this->fallback_chain as $provider_name) {
            $status[$provider_name] = [
                'enabled' => $this->isProviderEnabled($provider_name),
                'has_api_key' => isset($this->providers[$provider_name]) && !empty($this->providers[$provider_name]['api_key']),
                'priority' => isset($this->providers[$provider_name]) ? $this->providers[$provider_name]['priority'] : null,
                'display_name' => isset($this->providers[$provider_name]) ? $this->providers[$provider_name]['display_name'] : null
            ];
        }
        return $status;
    }
    
    /**
     * Generate questions using AI with automatic provider fallback
     */
    public function generateQuestions($lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions) {
        // Always load fresh providers from database if available
        if ($this->conn) {
            $this->loadProvidersFromDatabase();
        }
        
        // If no providers are available, return empty array to fall back to simulation
        if (empty($this->providers) || empty($this->fallback_chain)) {
            error_log("No AI providers available, falling back to simulation");
            return [];
        }
        
        // Try each provider in the fallback chain
        $failed_providers = [];
        $last_error = null;
        $attempted_providers = [];
        
        foreach ($this->fallback_chain as $provider_name) {
            if (!$this->isProviderEnabled($provider_name)) {
                error_log("Provider $provider_name is not enabled or missing API key, skipping");
                $failed_providers[] = $provider_name . " (disabled/missing key)";
                continue;
            }
            
            $attempted_providers[] = $provider_name;
            
            try {
                $start_time = microtime(true);
                error_log("Attempting to generate questions with provider: $provider_name");
                
                $questions = $this->callProvider($provider_name, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
                $response_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
                
                if (!empty($questions)) {
                    // Log successful usage
                    $this->logProviderUsage($provider_name, $questions, $response_time, true);
                    error_log("✅ Successfully generated " . count($questions) . " questions using provider: $provider_name (Response time: {$response_time}ms)");
                    return $questions;
                } else {
                    error_log("❌ Provider $provider_name returned empty questions, trying next provider");
                    $failed_providers[] = $provider_name . " (empty response)";
                }
            } catch (Exception $e) {
                // Try to auto-fix the error
                $fixed = $this->autoFixProviderError($provider_name, $e->getMessage());
                if ($fixed) {
                    error_log("🔧 Auto-fixed error for $provider_name, retrying...");
                    // Reload providers to get updated configuration
                    $this->refreshProviders();
                    // Retry with the same provider
                    try {
                        $start_time = microtime(true);
                        error_log("Retrying with auto-fixed provider: $provider_name");
                        
                        $questions = $this->callProvider($provider_name, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
                        
                        if (!empty($questions)) {
                            $end_time = microtime(true);
                            $response_time = ($end_time - $start_time) * 1000;
                            error_log("✅ Successfully generated " . count($questions) . " questions using auto-fixed provider: $provider_name (Response time: {$response_time}ms)");
                            
                            // Log successful usage
                            $this->logProviderUsage($provider_name, $questions, $response_time, true);
                            return $questions;
                        }
                    } catch (Exception $retry_e) {
                        error_log("❌ Auto-fixed provider $provider_name still failed: " . $retry_e->getMessage());
                    }
                }
                
                // Log failed usage
                $this->logProviderUsage($provider_name, [], 0, false, $e->getMessage());
                $failed_providers[] = $provider_name . " (" . $this->getShortErrorMessage($e->getMessage()) . ")";
                $last_error = $e->getMessage();
                error_log("❌ Provider $provider_name failed: " . $e->getMessage() . " - Trying next provider");
                continue;
            }
        }
        
        // If all providers fail, log the failure and return empty array (fallback to simulation)
        $failed_list = implode(', ', $failed_providers);
        $attempted_list = implode(', ', $attempted_providers);
        error_log("❌ All AI providers failed. Attempted: $attempted_list. Failures: $failed_list. Last error: $last_error. Falling back to simulation.");
        return [];
    }
    
    /**
     * Check if a provider is enabled and has API key
     */
    private function isProviderEnabled($provider_name) {
        if (!isset($this->providers[$provider_name])) {
            return false;
        }
        
        $provider = $this->providers[$provider_name];
        return $provider['enabled'] && !empty($provider['api_key']);
    }
    
    /**
     * Get a short error message for logging
     */
    private function getShortErrorMessage($error_message) {
        if (strpos($error_message, 'quota') !== false || strpos($error_message, 'insufficient_quota') !== false) {
            return 'quota exceeded';
        } elseif (strpos($error_message, 'credit') !== false || strpos($error_message, 'balance') !== false) {
            return 'low credits';
        } elseif (strpos($error_message, 'rate limit') !== false || strpos($error_message, '429') !== false) {
            return 'rate limited';
        } elseif (strpos($error_message, '401') !== false || strpos($error_message, 'unauthorized') !== false) {
            return 'auth failed';
        } elseif (strpos($error_message, 'timeout') !== false) {
            return 'timeout';
        } elseif (strpos($error_message, 'connection') !== false) {
            return 'connection error';
        } else {
            return 'api error';
        }
    }
    
    /**
     * Call a specific AI provider
     */
    private function callProvider($provider_name, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions) {
        $provider = $this->providers[$provider_name];
        $provider_type = $provider['provider_type'];
        
        // Use provider_type instead of provider_name to determine the API format
        switch ($provider_type) {
            case 'openai':
            case 'cursor': // Cursor AI uses OpenAI API format
                return $this->callOpenAI($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
            case 'anthropic':
                return $this->callAnthropic($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
            case 'google':
                return $this->callGoogle($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
            case 'azure':
                return $this->callAzure($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
            default:
                throw new Exception("Unknown provider type: $provider_type (Provider: $provider_name)");
        }
    }
    
    /**
     * Call OpenAI API
     */
    private function callOpenAI($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions) {
        if (empty($provider['api_key'])) {
            throw new Exception("OpenAI API key is not configured");
        }
        $combined_content = $this->combineLessonContent($lesson_contents);
        $prompt = $this->createPrompt($combined_content, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
        
        $data = [
            'model' => $provider['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert educational content creator specializing in creating high-quality quiz questions based on educational materials.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $provider['max_tokens'],
            'temperature' => $provider['temperature']
        ];
        
        $response = $this->makeHttpRequest($provider['api_url'], $data, [
            'Authorization: Bearer ' . $provider['api_key']
        ]);
        
        return $this->parseOpenAIResponse($response, $lesson_contents);
    }
    
    /**
     * Call Anthropic Claude API
     */
    private function callAnthropic($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions) {
        if (empty($provider['api_key'])) {
            throw new Exception("Anthropic API key is not configured");
        }
        $combined_content = $this->combineLessonContent($lesson_contents);
        $prompt = $this->createPrompt($combined_content, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
        
        $data = [
            'model' => $provider['model'],
            'max_tokens' => $provider['max_tokens'],
            'temperature' => $provider['temperature'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $response = $this->makeHttpRequest($provider['api_url'], $data, [
            'x-api-key: ' . $provider['api_key'],
            'anthropic-version: 2023-06-01'
        ]);
        
        return $this->parseAnthropicResponse($response, $lesson_contents);
    }
    
    /**
     * Call Google Gemini API
     */
    private function callGoogle($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions) {
        if (empty($provider['api_key'])) {
            throw new Exception("Google API key is not configured");
        }
        $combined_content = $this->combineLessonContent($lesson_contents);
        $prompt = $this->createPrompt($combined_content, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $provider['temperature'],
                'maxOutputTokens' => $provider['max_tokens']
            ]
        ];
        
        $url = $provider['api_url'] . '?key=' . $provider['api_key'];
        $response = $this->makeHttpRequest($url, $data, [
            'Content-Type: application/json'
        ]);
        
        return $this->parseGoogleResponse($response, $lesson_contents);
    }
    
    /**
     * Call Azure OpenAI API
     */
    private function callAzure($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions) {
        if (empty($provider['api_key'])) {
            throw new Exception("Azure API key is not configured");
        }
        if (empty($provider['api_url'])) {
            throw new Exception("Azure API URL is not configured");
        }
        $combined_content = $this->combineLessonContent($lesson_contents);
        $prompt = $this->createPrompt($combined_content, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
        
        $data = [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert educational content creator specializing in creating high-quality quiz questions based on educational materials.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $provider['max_tokens'],
            'temperature' => $provider['temperature']
        ];
        
        $response = $this->makeHttpRequest($provider['api_url'], $data, [
            'api-key: ' . $provider['api_key']
        ]);
        
        return $this->parseAzureResponse($response, $lesson_contents);
    }
    
    /**
     * Make HTTP request to AI provider
     */
    private function makeHttpRequest($url, $data, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
            'Content-Type: application/json'
        ], $headers));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        
        if ($curl_error) {
            error_log("cURL error: $curl_error");
            throw new Exception("cURL Error: $curl_error");
        }
        
        if ($http_code !== 200) {
            $error_message = "HTTP Error $http_code";
            if ($http_code === 401) {
                $error_message .= " - Unauthorized (check API key)";
            } elseif ($http_code === 429) {
                $error_message .= " - Rate limit exceeded";
            } elseif ($http_code === 500) {
                $error_message .= " - Server error";
            } elseif ($http_code === 503) {
                $error_message .= " - Service unavailable";
            }
            $error_message .= ": $response";
            error_log("HTTP error: $error_message");
            throw new Exception($error_message);
        }
        
        return $response;
    }
    
    /**
     * Combine lesson content for AI processing
     */
    private function combineLessonContent($lesson_contents) {
        // If lesson_contents is already a string, return it
        if (is_string($lesson_contents)) {
            return $lesson_contents;
        }
        
        // If it's an array, combine the content
        if (is_array($lesson_contents)) {
            $combined_content = '';
            foreach ($lesson_contents as $lesson) {
                $combined_content .= "Topic: " . $lesson['title'] . "\n";
                $combined_content .= "Description: " . $lesson['description'] . "\n";
                $combined_content .= "Content: " . strip_tags($lesson['content']) . "\n\n";
            }
            return $combined_content;
        }
        
        return '';
    }
    
    /**
     * Create AI prompt
     */
    private function createPrompt($content, $question_count, $difficulty, $question_types, $case_sensitive, $instructions) {
        $question_types_text = implode(', ', $question_types);
        $case_sensitive_text = $case_sensitive ? "Case sensitive answers required." : "Case insensitive answers acceptable.";
        
        return "You are an expert educational content creator. Based on the following lesson content, generate exactly $question_count high-quality quiz questions.

REQUIREMENTS:
- Difficulty Level: $difficulty
- Question Types: $question_types_text
- $case_sensitive_text
- Questions must be directly based on the provided content
- Each question should test understanding, not just memorization
- Provide clear, unambiguous correct answers
- Include brief explanations for answers

LESSON CONTENT:
$content

ADDITIONAL INSTRUCTIONS: $instructions

Please respond with a valid JSON array in this exact format:
[
  {
    \"type\": \"multiple_choice\",
    \"question\": \"Question text here\",
    \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],
    \"correct_answer\": \"Option A\",
    \"explanation\": \"Why this answer is correct\",
    \"topic\": \"Topic name\",
    \"difficulty\": \"$difficulty\",
    \"case_sensitive\": " . ($case_sensitive ? 'true' : 'false') . "
  }
]

For multiple choice questions, provide 4 options. For true/false, use \"true\" or \"false\" as correct_answer. For fill in the blank questions, provide the expected answer text. For short answer, provide the expected answer text.";
    }
    
    /**
     * Calculate points based on question complexity
     */
    private function calculateQuestionPoints($question) {
        $base_points = 1;
        $difficulty_multiplier = 1;
        $type_multiplier = 1;
        $complexity_bonus = 0;
        
        // Difficulty-based points
        switch (strtolower($question['difficulty'])) {
            case 'easy':
                $difficulty_multiplier = 1;
                break;
            case 'medium':
                $difficulty_multiplier = 1.5;
                break;
            case 'hard':
                $difficulty_multiplier = 2;
                break;
            default:
                $difficulty_multiplier = 1;
        }
        
        // Question type-based points
        switch ($question['type']) {
            case 'true_false':
                $type_multiplier = 0.8; // Easier to answer
                break;
            case 'multiple_choice':
                $type_multiplier = 1; // Standard
                break;
            case 'fill_blank':
                $type_multiplier = 1.2; // Requires recall
                break;
            case 'short_answer':
                $type_multiplier = 1.5; // Requires explanation
                break;
            case 'essay':
                $type_multiplier = 2; // Most complex
                break;
            default:
                $type_multiplier = 1;
        }
        
        // Complexity bonus based on question length and content
        $question_length = strlen($question['question']);
        if ($question_length > 200) {
            $complexity_bonus += 1; // Longer questions are more complex
        }
        if ($question_length > 400) {
            $complexity_bonus += 1; // Very long questions get extra points
        }
        
        // Check for complex keywords that indicate higher-order thinking
        $complex_keywords = ['explain', 'analyze', 'compare', 'contrast', 'evaluate', 'synthesize', 'justify', 'demonstrate', 'apply', 'create'];
        $question_lower = strtolower($question['question']);
        foreach ($complex_keywords as $keyword) {
            if (strpos($question_lower, $keyword) !== false) {
                $complexity_bonus += 1;
                break; // Only count once per question
            }
        }
        
        // Calculate final points
        $points = round($base_points * $difficulty_multiplier * $type_multiplier + $complexity_bonus);
        
        // Ensure minimum of 1 point and maximum of 10 points
        return max(1, min(10, $points));
    }
    
    /**
     * Parse OpenAI response
     */
    private function parseOpenAIResponse($response, $lesson_contents) {
        $result = json_decode($response, true);
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("Invalid OpenAI response format");
        }
        
        $ai_content = $result['choices'][0]['message']['content'];
        return $this->extractQuestionsFromContent($ai_content, $lesson_contents);
    }
    
    /**
     * Parse Anthropic response
     */
    private function parseAnthropicResponse($response, $lesson_contents) {
        $result = json_decode($response, true);
        if (!isset($result['content'][0]['text'])) {
            throw new Exception("Invalid Anthropic response format");
        }
        
        $ai_content = $result['content'][0]['text'];
        return $this->extractQuestionsFromContent($ai_content, $lesson_contents);
    }
    
    /**
     * Parse Google response
     */
    private function parseGoogleResponse($response, $lesson_contents) {
        $result = json_decode($response, true);
        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid Google response format");
        }
        
        $ai_content = $result['candidates'][0]['content']['parts'][0]['text'];
        return $this->extractQuestionsFromContent($ai_content, $lesson_contents);
    }
    
    /**
     * Parse Azure response
     */
    private function parseAzureResponse($response, $lesson_contents) {
        $result = json_decode($response, true);
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("Invalid Azure response format");
        }
        
        $ai_content = $result['choices'][0]['message']['content'];
        return $this->extractQuestionsFromContent($ai_content, $lesson_contents);
    }
    
    /**
     * Extract questions from AI content
     */
    private function extractQuestionsFromContent($ai_content, $lesson_contents) {
        $json_start = strpos($ai_content, '[');
        $json_end = strrpos($ai_content, ']') + 1;
        
        if ($json_start === false || $json_end === false) {
            throw new Exception("No valid JSON array found in AI response");
        }
        
        $json_content = substr($ai_content, $json_start, $json_end - $json_start);
        $questions = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON decode error: " . json_last_error_msg());
        }
        
        // Validate and clean up questions
        $validated_questions = [];
        foreach ($questions as $question) {
            if ($this->validateQuestion($question)) {
                // Handle lesson_id assignment - if lesson_contents is an array, use first lesson ID
                if (is_array($lesson_contents) && !empty($lesson_contents)) {
                    $question['lesson_id'] = $lesson_contents[0]['id'];
                } else {
                    $question['lesson_id'] = null; // Set to null if no lesson content available
                }
                // Calculate and assign points based on question complexity
                $question['points'] = $this->calculateQuestionPoints($question);
                $validated_questions[] = $question;
            }
        }
        return $validated_questions;
    }
    
    /**
     * Validate question structure
     */
    private function validateQuestion($question) {
        $required_fields = ['type', 'question', 'correct_answer', 'explanation', 'topic', 'difficulty'];
        
        foreach ($required_fields as $field) {
            if (!isset($question[$field]) || empty($question[$field])) {
                return false;
            }
        }
        
        $valid_types = ['multiple_choice', 'true_false', 'fill_blank', 'short_answer'];
        if (!in_array($question['type'], $valid_types)) {
            return false;
        }
        
        if ($question['type'] === 'multiple_choice') {
            if (!isset($question['options']) || !is_array($question['options']) || count($question['options']) !== 4) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Log provider usage
     */
    private function logProviderUsage($provider_name, $questions, $response_time, $success, $error_message = null) {
        if (!$this->conn || !isset($this->providers[$provider_name])) {
            return;
        }
        
        // Skip logging for faculty users since they're not in the users table
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher') {
            return;
        }
        
        $provider = $this->providers[$provider_name];
        $tokens_used = $this->estimateTokens($questions);
        $cost = $tokens_used * $provider['cost_per_token'];
        
        // Only log for users that exist in the users table
        $user_id = null;
        if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
            // Check if user exists in users table
            $user_query = "SELECT id FROM users WHERE id = ? AND status = 'active'";
            $user_stmt = mysqli_prepare($this->conn, $user_query);
            mysqli_stmt_bind_param($user_stmt, "i", $_SESSION['user_id']);
            mysqli_stmt_execute($user_stmt);
            $user_result = mysqli_stmt_get_result($user_stmt);
            if ($user_row = mysqli_fetch_assoc($user_result)) {
                $user_id = $user_row['id'];
            }
        }
        
        // Only log if we have a valid user_id that exists in users table
        if ($user_id) {
            $query = "INSERT INTO ai_provider_usage (provider_id, user_id, tokens_used, cost, success, error_message, response_time_ms) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($this->conn, $query);
            $success_int = $success ? 1 : 0; // Convert boolean to integer
            $error_message = $error_message ?: null; // Ensure null instead of empty string
            $response_time_int = (int)round($response_time); // Convert float to integer
            mysqli_stmt_bind_param($stmt, "iiidisi", $provider['id'], $user_id, $tokens_used, $cost, $success_int, $error_message, $response_time_int);
            mysqli_stmt_execute($stmt);
        }
    }
    
    /**
     * Estimate tokens used (rough estimation)
     */
    private function estimateTokens($questions) {
        $total_chars = 0;
        foreach ($questions as $question) {
            $total_chars += strlen($question['question']);
            if (isset($question['options'])) {
                foreach ($question['options'] as $option) {
                    $total_chars += strlen($option);
                }
            }
            $total_chars += strlen($question['correct_answer']);
            $total_chars += strlen($question['explanation']);
        }
        
        // Rough estimation: 1 token ≈ 4 characters
        return ceil($total_chars / 4);
    }
    
    
    /**
     * Generate syllabus content using AI with automatic provider fallback
     */
    public function generateSyllabusContent($prompt) {
        // Always load fresh providers from database if available
        if ($this->conn) {
            $this->loadProvidersFromDatabase();
        }
        
        // If no providers are available, return null
        if (empty($this->providers) || empty($this->fallback_chain)) {
            error_log("No AI providers available for syllabus generation");
            return null;
        }
        
        // Try each provider in the fallback chain
        $failed_providers = [];
        $last_error = null;
        $attempted_providers = [];
        
        foreach ($this->fallback_chain as $provider_name) {
            if (!$this->isProviderEnabled($provider_name)) {
                error_log("Provider $provider_name is not enabled or missing API key, skipping");
                $failed_providers[] = $provider_name . " (disabled/missing key)";
                continue;
            }
            
            $attempted_providers[] = $provider_name;
            
            try {
                $start_time = microtime(true);
                error_log("Attempting to generate syllabus content with provider: $provider_name");
                
                $content = $this->callSyllabusProvider($provider_name, $prompt);
                $response_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
                
                if (!empty($content)) {
                    // Log successful usage
                    $this->logProviderUsage($provider_name, [], $response_time, true);
                    error_log("✅ Successfully generated syllabus content using provider: $provider_name (Response time: {$response_time}ms)");
                    return $content;
                } else {
                    error_log("❌ Provider $provider_name returned empty content, trying next provider");
                    $failed_providers[] = $provider_name . " (empty response)";
                }
            } catch (Exception $e) {
                // Try to auto-fix the error
                $fixed = $this->autoFixProviderError($provider_name, $e->getMessage());
                if ($fixed) {
                    error_log("🔧 Auto-fixed error for $provider_name, retrying...");
                    // Reload providers to get updated configuration
                    $this->refreshProviders();
                    // Retry with the same provider
                    try {
                        $start_time = microtime(true);
                        error_log("Retrying syllabus generation with auto-fixed provider: $provider_name");
                        
                        $content = $this->callSyllabusProvider($provider_name, $prompt);
                        $response_time = (microtime(true) - $start_time) * 1000;
                        
                        if (!empty($content)) {
                            // Log successful usage
                            $this->logProviderUsage($provider_name, [], $response_time, true);
                            error_log("✅ Successfully generated syllabus content using auto-fixed provider: $provider_name (Response time: {$response_time}ms)");
                            return $content;
                        }
                    } catch (Exception $retry_e) {
                        error_log("❌ Auto-fixed provider $provider_name still failed: " . $retry_e->getMessage());
                    }
                }
                
                // Log failed usage
                $this->logProviderUsage($provider_name, [], 0, false, $e->getMessage());
                $failed_providers[] = $provider_name . " (" . $this->getShortErrorMessage($e->getMessage()) . ")";
                $last_error = $e->getMessage();
                error_log("❌ Provider $provider_name failed: " . $e->getMessage() . " - Trying next provider");
                continue;
            }
        }
        
        // If all providers fail, log the failure and return null
        $failed_list = implode(', ', $failed_providers);
        $attempted_list = implode(', ', $attempted_providers);
        error_log("❌ All AI providers failed for syllabus generation. Attempted: $attempted_list. Failures: $failed_list. Last error: $last_error.");
        return null;
    }
    
    /**
     * Call a specific AI provider for syllabus generation
     */
    private function callSyllabusProvider($provider_name, $prompt) {
        $provider = $this->providers[$provider_name];
        $provider_type = $provider['provider_type'];
        
        // Use provider_type instead of provider_name to determine the API format
        switch ($provider_type) {
            case 'openai':
            case 'cursor': // Cursor AI uses OpenAI API format
                return $this->callOpenAISyllabus($provider, $prompt);
            case 'anthropic':
                return $this->callAnthropicSyllabus($provider, $prompt);
            case 'google':
                return $this->callGoogleSyllabus($provider, $prompt);
            case 'azure':
                return $this->callAzureSyllabus($provider, $prompt);
            default:
                throw new Exception("Unknown provider type: $provider_type (Provider: $provider_name)");
        }
    }
    
    /**
     * Call OpenAI API for syllabus generation
     */
    private function callOpenAISyllabus($provider, $prompt) {
        if (empty($provider['api_key'])) {
            throw new Exception("OpenAI API key is not configured");
        }
        
        $data = [
            'model' => $provider['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert academic curriculum designer specializing in Philippine higher education standards and CHED (Commission on Higher Education) guidelines. You create comprehensive, detailed university course syllabi that follow Philippine academic standards, grading systems, and educational policies. You ensure all fields are filled with substantial, relevant content that prepares students for Philippine industry needs and professional standards.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $provider['max_tokens'],
            'temperature' => $provider['temperature']
        ];
        
        $response = $this->makeHttpRequest($provider['api_url'], $data, [
            'Authorization: Bearer ' . $provider['api_key']
        ]);
        
        return $this->parseOpenAISyllabusResponse($response);
    }
    
    /**
     * Call Anthropic Claude API for syllabus generation
     */
    private function callAnthropicSyllabus($provider, $prompt) {
        if (empty($provider['api_key'])) {
            throw new Exception("Anthropic API key is not configured");
        }
        
        // Add Philippine context to the prompt for Anthropic
        $enhanced_prompt = "You are an expert academic curriculum designer specializing in Philippine higher education standards and CHED (Commission on Higher Education) guidelines. " . $prompt;
        
        $data = [
            'model' => $provider['model'],
            'max_tokens' => $provider['max_tokens'],
            'temperature' => $provider['temperature'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $enhanced_prompt
                ]
            ]
        ];
        
        $response = $this->makeHttpRequest($provider['api_url'], $data, [
            'x-api-key: ' . $provider['api_key'],
            'anthropic-version: 2023-06-01'
        ]);
        
        return $this->parseAnthropicSyllabusResponse($response);
    }
    
    /**
     * Call Google Gemini API for syllabus generation
     */
    private function callGoogleSyllabus($provider, $prompt) {
        if (empty($provider['api_key'])) {
            throw new Exception("Google API key is not configured");
        }
        
        // Add Philippine context to the prompt for Google
        $enhanced_prompt = "You are an expert academic curriculum designer specializing in Philippine higher education standards and CHED (Commission on Higher Education) guidelines. " . $prompt;
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $enhanced_prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $provider['temperature'],
                'maxOutputTokens' => $provider['max_tokens']
            ]
        ];
        
        $url = $provider['api_url'] . '?key=' . $provider['api_key'];
        $response = $this->makeHttpRequest($url, $data, [
            'Content-Type: application/json'
        ]);
        
        return $this->parseGoogleSyllabusResponse($response);
    }
    
    /**
     * Call Azure OpenAI API for syllabus generation
     */
    private function callAzureSyllabus($provider, $prompt) {
        if (empty($provider['api_key'])) {
            throw new Exception("Azure API key is not configured");
        }
        if (empty($provider['api_url'])) {
            throw new Exception("Azure API URL is not configured");
        }
        
        $data = [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert academic curriculum designer specializing in Philippine higher education standards and CHED (Commission on Higher Education) guidelines. You create comprehensive, detailed university course syllabi that follow Philippine academic standards, grading systems, and educational policies. You ensure all fields are filled with substantial, relevant content that prepares students for Philippine industry needs and professional standards.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $provider['max_tokens'],
            'temperature' => $provider['temperature']
        ];
        
        $response = $this->makeHttpRequest($provider['api_url'], $data, [
            'api-key: ' . $provider['api_key']
        ]);
        
        return $this->parseAzureSyllabusResponse($response);
    }
    
    /**
     * Parse OpenAI response for syllabus
     */
    private function parseOpenAISyllabusResponse($response) {
        $result = json_decode($response, true);
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("Invalid OpenAI response format");
        }
        
        $ai_content = $result['choices'][0]['message']['content'];
        return $this->extractSyllabusFromContent($ai_content);
    }
    
    /**
     * Parse Anthropic response for syllabus
     */
    private function parseAnthropicSyllabusResponse($response) {
        $result = json_decode($response, true);
        if (!isset($result['content'][0]['text'])) {
            throw new Exception("Invalid Anthropic response format");
        }
        
        $ai_content = $result['content'][0]['text'];
        return $this->extractSyllabusFromContent($ai_content);
    }
    
    /**
     * Parse Google response for syllabus
     */
    private function parseGoogleSyllabusResponse($response) {
        $result = json_decode($response, true);
        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid Google response format");
        }
        
        $ai_content = $result['candidates'][0]['content']['parts'][0]['text'];
        return $this->extractSyllabusFromContent($ai_content);
    }
    
    /**
     * Parse Azure response for syllabus
     */
    private function parseAzureSyllabusResponse($response) {
        $result = json_decode($response, true);
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("Invalid Azure response format");
        }
        
        $ai_content = $result['choices'][0]['message']['content'];
        return $this->extractSyllabusFromContent($ai_content);
    }
    
    /**
     * Extract syllabus data from AI content
     */
    private function extractSyllabusFromContent($ai_content) {
        // For lesson content generation, we expect HTML content, not JSON
        // Check if the content is JSON first
        $json_start = strpos($ai_content, '{');
        $json_end = strrpos($ai_content, '}') + 1;
        
        if ($json_start !== false && $json_end !== false) {
            // Try to parse as JSON
            $json_content = substr($ai_content, $json_start, $json_end - $json_start);
            $syllabus_data = json_decode($json_content, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $syllabus_data;
            }
        }
        
        // If not JSON or JSON parsing failed, return the content as-is (HTML)
        // This is expected for lesson content generation
        return $ai_content;
    }
    
    /**
     * Get fallback chain information (hidden from external view)
     */
    public function getFallbackChain() {
        // Always return empty array to hide external providers
        return [];
    }
    
    /**
     * Get providers (for debugging)
     */
    public function getProviders(): array {
        return $this->providers ?? [];
    }
    
    /**
     * Check if any providers are available
     */
    public function hasProviders() {
        return !empty($this->providers) && !empty($this->fallback_chain);
    }
    
    /**
     * Comprehensive auto-fix system for all types of API errors
     */
    public function autoFixProviderError($provider_name, $error_message) {
        if (!$this->conn) {
            error_log("Cannot auto-fix provider error: No database connection");
            return false;
        }
        
        // 1. Max Tokens Errors
        if ($this->isMaxTokensError($error_message)) {
            return $this->fixMaxTokensError($provider_name, $error_message);
        }
        
        // 2. Insufficient Balance Errors
        if ($this->isInsufficientBalanceError($error_message)) {
            return $this->fixInsufficientBalanceError($provider_name, $error_message);
        }
        
        // 3. Invalid API Key Errors
        if ($this->isInvalidApiKeyError($error_message)) {
            return $this->fixInvalidApiKeyError($provider_name, $error_message);
        }
        
        // 4. Rate Limit Errors
        if ($this->isRateLimitError($error_message)) {
            return $this->fixRateLimitError($provider_name, $error_message);
        }
        
        // 5. Model Not Found Errors
        if ($this->isModelNotFoundError($error_message)) {
            return $this->fixModelNotFoundError($provider_name, $error_message);
        }
        
        // 6. Temperature/Parameter Errors
        if ($this->isParameterError($error_message)) {
            return $this->fixParameterError($provider_name, $error_message);
        }
        
        // 7. Network/Connection Errors
        if ($this->isNetworkError($error_message)) {
            return $this->fixNetworkError($provider_name, $error_message);
        }
        
        // 8. Non-fixable Errors (move to least priority)
        if ($this->isNonFixableError($error_message)) {
            return $this->fixNonFixableError($provider_name, $error_message);
        }
        
        error_log("No auto-fix available for error: $error_message");
        return false;
    }
    
    /**
     * Check if an error message indicates a max_tokens issue
     */
    public function isMaxTokensError($error_message) {
        $max_token_patterns = [
            'Invalid max_tokens value',
            'max_tokens must be less than or equal to',
            'maximum value for max_tokens is less than',
            'max_tokens.*must be.*less than',
            'max_tokens.*exceeded',
            'token limit exceeded',
            'context length exceeded',
            '`max_tokens` must be less than or equal to',
            'HTTP Error.*max_tokens'
        ];
        
        foreach ($max_token_patterns as $pattern) {
            if (stripos($error_message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if an error message indicates insufficient balance
     */
    public function isInsufficientBalanceError($error_message) {
        $balance_patterns = [
            'Insufficient Balance',
            'insufficient funds',
            'credit limit exceeded',
            'payment required',
            'billing limit'
        ];
        
        foreach ($balance_patterns as $pattern) {
            if (stripos($error_message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if an error message indicates invalid API key
     */
    public function isInvalidApiKeyError($error_message) {
        $api_key_patterns = [
            'Invalid API key',
            'unauthorized',
            'authentication failed',
            'invalid credentials',
            'API key not found',
            'access denied'
        ];
        
        foreach ($api_key_patterns as $pattern) {
            if (stripos($error_message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if an error message indicates rate limiting
     */
    public function isRateLimitError($error_message) {
        $rate_limit_patterns = [
            'rate limit',
            'too many requests',
            'quota exceeded',
            'throttled',
            'request limit',
            'requests per minute',
            'requests per hour',
            'rate exceeded',
            'limit exceeded'
        ];
        
        foreach ($rate_limit_patterns as $pattern) {
            if (stripos($error_message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if an error message indicates model not found
     */
    public function isModelNotFoundError($error_message) {
        $model_patterns = [
            'model not found',
            'invalid model',
            'model does not exist',
            'unsupported model'
        ];
        
        foreach ($model_patterns as $pattern) {
            if (stripos($error_message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if an error message indicates parameter issues
     */
    public function isParameterError($error_message) {
        $parameter_patterns = [
            'invalid parameter',
            'temperature.*must be',
            'invalid.*value',
            'parameter.*out of range',
            'temperature must be between',
            'temperature.*between.*and',
            'parameter.*must be',
            'invalid.*parameter',
            'out of range',
            'must be between',
            'must be.*between',
            'invalid.*range',
            'parameter.*invalid',
            'value.*invalid',
            'invalid.*temperature',
            'invalid.*max_tokens',
            'invalid.*model',
            'unsupported.*parameter'
        ];
        
        foreach ($parameter_patterns as $pattern) {
            if (stripos($error_message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if an error message indicates network issues
     */
    public function isNetworkError($error_message) {
        $network_patterns = [
            'connection timeout',
            'network error',
            'connection refused',
            'timeout',
            'connection failed'
        ];
        
        foreach ($network_patterns as $pattern) {
            if (stripos($error_message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if an error message indicates non-fixable issues
     */
    public function isNonFixableError($error_message) {
        $non_fixable_patterns = [
            'internal server error',
            'server error',
            'service unavailable',
            'maintenance',
            'unknown error',
            'unexpected error',
            'system error',
            'database error',
            'configuration error',
            'permission denied',
            'forbidden',
            'not implemented',
            'method not allowed',
            'bad gateway',
            'service temporarily unavailable'
        ];
        
        foreach ($non_fixable_patterns as $pattern) {
            if (stripos($error_message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Fix max tokens error
     */
    private function fixMaxTokensError($provider_name, $error_message) {
        $new_limit = $this->extractTokenLimitFromError($error_message, $provider_name);
        if (!$new_limit) {
            error_log("Could not extract token limit from error message: $error_message");
            return false;
        }
        
        $update_query = "UPDATE ai_providers SET max_tokens = ? WHERE name = ? AND enabled = 1";
        $stmt = mysqli_prepare($this->conn, $update_query);
        
        if (!$stmt) {
            error_log("Failed to prepare update statement: " . mysqli_error($this->conn));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "is", $new_limit, $provider_name);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($result) {
            error_log("🔧 Auto-fixed max_tokens for $provider_name: Updated to $new_limit tokens");
            return true;
        } else {
            error_log("Failed to update max_tokens for $provider_name: " . mysqli_error($this->conn));
            return false;
        }
    }
    
    /**
     * Fix insufficient balance error by disabling the provider
     */
    private function fixInsufficientBalanceError($provider_name, $error_message) {
        $update_query = "UPDATE ai_providers SET enabled = 0 WHERE name = ?";
        $stmt = mysqli_prepare($this->conn, $update_query);
        
        if (!$stmt) {
            error_log("Failed to prepare update statement: " . mysqli_error($this->conn));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "s", $provider_name);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($result) {
            error_log("🔧 Auto-disabled $provider_name due to insufficient balance");
            return true;
        } else {
            error_log("Failed to disable $provider_name: " . mysqli_error($this->conn));
            return false;
        }
    }
    
    /**
     * Fix invalid API key error by disabling the provider
     */
    private function fixInvalidApiKeyError($provider_name, $error_message) {
        $update_query = "UPDATE ai_providers SET enabled = 0 WHERE name = ?";
        $stmt = mysqli_prepare($this->conn, $update_query);
        
        if (!$stmt) {
            error_log("Failed to prepare update statement: " . mysqli_error($this->conn));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "s", $provider_name);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($result) {
            error_log("🔧 Auto-disabled $provider_name due to invalid API key");
            return true;
        } else {
            error_log("Failed to disable $provider_name: " . mysqli_error($this->conn));
            return false;
        }
    }
    
    /**
     * Fix rate limit error by adding delay and reducing priority
     */
    private function fixRateLimitError($provider_name, $error_message) {
        // Reduce priority to try this provider later
        $update_query = "UPDATE ai_providers SET priority = priority + 10 WHERE name = ?";
        $stmt = mysqli_prepare($this->conn, $update_query);
        
        if (!$stmt) {
            error_log("Failed to prepare update statement: " . mysqli_error($this->conn));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "s", $provider_name);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($result) {
            error_log("🔧 Auto-reduced priority for $provider_name due to rate limiting");
            return true;
        } else {
            error_log("Failed to update priority for $provider_name: " . mysqli_error($this->conn));
            return false;
        }
    }
    
    /**
     * Fix model not found error by updating to a default model
     */
    private function fixModelNotFoundError($provider_name, $error_message) {
        if (!isset($this->providers[$provider_name])) {
            return false;
        }
        
        $provider_type = $this->providers[$provider_name]['provider_type'];
        $default_model = $this->getDefaultModelForProviderType($provider_type);
        
        if (!$default_model) {
            error_log("No default model available for provider type: $provider_type");
            return false;
        }
        
        $update_query = "UPDATE ai_providers SET model = ? WHERE name = ?";
        $stmt = mysqli_prepare($this->conn, $update_query);
        
        if (!$stmt) {
            error_log("Failed to prepare update statement: " . mysqli_error($this->conn));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "ss", $default_model, $provider_name);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($result) {
            error_log("🔧 Auto-updated model for $provider_name to $default_model");
            return true;
        } else {
            error_log("Failed to update model for $provider_name: " . mysqli_error($this->conn));
            return false;
        }
    }
    
    /**
     * Fix parameter error by setting safe defaults
     */
    private function fixParameterError($provider_name, $error_message) {
        // Set safe default temperature
        $update_query = "UPDATE ai_providers SET temperature = 0.7 WHERE name = ?";
        $stmt = mysqli_prepare($this->conn, $update_query);
        
        if (!$stmt) {
            error_log("Failed to prepare update statement: " . mysqli_error($this->conn));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "s", $provider_name);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($result) {
            error_log("🔧 Auto-fixed parameters for $provider_name: Set temperature to 0.7");
            return true;
        } else {
            error_log("Failed to update parameters for $provider_name: " . mysqli_error($this->conn));
            return false;
        }
    }
    
    /**
     * Fix network error by temporarily disabling the provider
     */
    private function fixNetworkError($provider_name, $error_message) {
        // Temporarily disable the provider (can be re-enabled later)
        $update_query = "UPDATE ai_providers SET enabled = 0 WHERE name = ?";
        $stmt = mysqli_prepare($this->conn, $update_query);
        
        if (!$stmt) {
            error_log("Failed to prepare update statement: " . mysqli_error($this->conn));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "s", $provider_name);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($result) {
            error_log("🔧 Auto-disabled $provider_name due to network issues");
            return true;
        } else {
            error_log("Failed to disable $provider_name: " . mysqli_error($this->conn));
            return false;
        }
    }
    
    /**
     * Get default model for provider type
     */
    private function getDefaultModelForProviderType($provider_type) {
        $default_models = [
            'openai' => 'gpt-3.5-turbo',
            'anthropic' => 'claude-3-haiku-20240307',
            'google' => 'gemini-pro',
            'azure' => 'gpt-35-turbo'
        ];
        
        return $default_models[$provider_type] ?? null;
    }
    
    /**
     * Fix non-fixable error by moving provider to least priority
     */
    private function fixNonFixableError($provider_name, $error_message) {
        // Get the highest priority number to move this provider to the end
        $max_priority_query = "SELECT MAX(priority) as max_priority FROM ai_providers WHERE enabled = 1";
        $result = mysqli_query($this->conn, $max_priority_query);
        
        if (!$result) {
            error_log("Failed to get max priority: " . mysqli_error($this->conn));
            return false;
        }
        
        $row = mysqli_fetch_assoc($result);
        $new_priority = ($row['max_priority'] ?? 100) + 50; // Move to end with buffer
        
        // Update the provider's priority to move it to the end
        $update_query = "UPDATE ai_providers SET priority = ? WHERE name = ?";
        $stmt = mysqli_prepare($this->conn, $update_query);
        
        if (!$stmt) {
            error_log("Failed to prepare update statement: " . mysqli_error($this->conn));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "is", $new_priority, $provider_name);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($result) {
            error_log("🔧 Auto-moved $provider_name to least priority ($new_priority) due to non-fixable error");
            return true;
        } else {
            error_log("Failed to update priority for $provider_name: " . mysqli_error($this->conn));
            return false;
        }
    }
    
    /**
     * Extract the correct token limit from an error message
     */
    public function extractTokenLimitFromError($error_message, $provider_name = null) {
        // Pattern 1: "max_tokens must be less than or equal to `8192`" (HTTP error format)
        if (preg_match('/`max_tokens` must be less than or equal to `(\d+)`/', $error_message, $matches)) {
            return (int)$matches[1];
        }
        
        // Pattern 2: "max_tokens must be less than or equal to `8192`" (direct format)
        if (preg_match('/max_tokens must be less than or equal to `(\d+)`/', $error_message, $matches)) {
            return (int)$matches[1];
        }
        
        // Pattern 3: "Invalid max_tokens value, the valid range of max_tokens is [1, 8192]"
        if (preg_match('/valid range of max_tokens is \[1, (\d+)\]/', $error_message, $matches)) {
            return (int)$matches[1];
        }
        
        // Pattern 4: "maximum value for max_tokens is less than the context_window for this model"
        if (stripos($error_message, 'maximum value for max_tokens is less than') !== false) {
            if (isset($this->providers[$provider_name])) {
                $provider_type = $this->providers[$provider_name]['provider_type'];
                switch ($provider_type) {
                    case 'openai':
                        return 4000;
                    case 'anthropic':
                        return 200000;
                    case 'google':
                        return 1000000;
                    default:
                        return 4000;
                }
            }
        }
        
        // Pattern 5: Look for any number in the error message that could be a limit
        if (preg_match('/(\d+)/', $error_message, $matches)) {
            $potential_limit = (int)$matches[1];
            if ($potential_limit >= 1000 && $potential_limit <= 2000000) {
                return $potential_limit;
            }
        }
        
        return null;
    }
}
?>
