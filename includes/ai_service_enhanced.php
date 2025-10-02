<?php
/**
 * Enhanced AI Service - Database-Driven Multi-Provider AI Integration
 * 
 * This service provides a unified interface for multiple AI providers
 * with intelligent fallback, health checking, and automatic provider switching.
 * All providers are loaded dynamically from the database.
 */

require_once __DIR__ . '/ai_checker.php';

class AIServiceEnhanced {
    private $conn;
    private $ai_checker;
    private $request_timeout = 120; // Increased to 2 minutes for complex AI analysis
    private $max_retries = 3;
    
    public function __construct($database_connection = null) {
        if (!$database_connection) {
            throw new Exception("Database connection is required for Enhanced AI Service");
        }
        
        $this->conn = $database_connection;
        $this->ai_checker = new AIChecker($database_connection);
        
        // Verify we have providers available
        if (!$this->ai_checker->hasProviders()) {
            error_log("Enhanced AI Service: No providers available from database");
        }
    }
    
    /**
     * Generate questions using AI with intelligent provider selection and fallback
     */
    public function generateQuestions($lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions) {
        // Refresh providers from database to get latest configuration
        $this->ai_checker->refreshProviders();
        
        if (!$this->ai_checker->hasProviders()) {
            error_log("Enhanced AI Service: No AI providers available, falling back to simulation");
            return [];
        }
        
        $fallback_chain = $this->ai_checker->getFallbackChain();
        $failed_providers = [];
        $attempted_providers = [];
        $last_error = null;
        
        // Try each provider in the fallback chain
        foreach ($fallback_chain as $provider_name) {
            $provider = $this->ai_checker->getProvider($provider_name);
            
            if (!$provider || !$provider['enabled'] || empty($provider['api_key'])) {
                error_log("Enhanced AI Service: Provider $provider_name is not available, skipping");
                $failed_providers[] = $provider_name . " (disabled/missing key)";
                continue;
            }
            
            $attempted_providers[] = $provider_name;
            
            try {
                $start_time = microtime(true);
                error_log("Enhanced AI Service: Attempting to generate questions with provider: $provider_name");
                
                $questions = $this->callProvider($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
                $response_time = (microtime(true) - $start_time) * 1000;
                
                if (!empty($questions)) {
                    // Log successful usage
                    $this->logProviderUsage($provider, $questions, $response_time, true);
                    error_log("✅ Enhanced AI Service: Successfully generated " . count($questions) . " questions using provider: $provider_name (Response time: {$response_time}ms)");
                    return $questions;
                } else {
                    error_log("❌ Enhanced AI Service: Provider $provider_name returned empty questions, trying next provider");
                    $failed_providers[] = $provider_name . " (empty response)";
                }
            } catch (Exception $e) {
                // Try to auto-fix the error
                $fixed = $this->autoFixProviderError($provider_name, $e->getMessage());
                if ($fixed) {
                    error_log("🔧 Enhanced AI Service: Auto-fixed error for $provider_name, retrying...");
                    // Refresh providers to get updated configuration
                    $this->ai_checker->refreshProviders();
                    
                    // Retry with the same provider
                    try {
                        $start_time = microtime(true);
                        $updated_provider = $this->ai_checker->getProvider($provider_name);
                        
                        if ($updated_provider) {
                            error_log("Enhanced AI Service: Retrying with auto-fixed provider: $provider_name");
                            
                            $questions = $this->callProvider($updated_provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
                            $response_time = (microtime(true) - $start_time) * 1000;
                            
                            if (!empty($questions)) {
                                // Log successful usage
                                $this->logProviderUsage($updated_provider, $questions, $response_time, true);
                                error_log("✅ Enhanced AI Service: Successfully generated " . count($questions) . " questions using auto-fixed provider: $provider_name (Response time: {$response_time}ms)");
                                return $questions;
                            }
                        }
                    } catch (Exception $retry_e) {
                        error_log("❌ Enhanced AI Service: Auto-fixed provider $provider_name still failed: " . $retry_e->getMessage());
                    }
                }
                
                // Log failed usage
                $this->logProviderUsage($provider, [], 0, false, $e->getMessage());
                $failed_providers[] = $provider_name . " (" . $this->getShortErrorMessage($e->getMessage()) . ")";
                $last_error = $e->getMessage();
                error_log("❌ Enhanced AI Service: Provider $provider_name failed: " . $e->getMessage() . " - Trying next provider");
                continue;
            }
        }
        
        // If all providers fail, log the failure and return empty array (fallback to simulation)
        $failed_list = implode(', ', $failed_providers);
        $attempted_list = implode(', ', $attempted_providers);
        error_log("❌ Enhanced AI Service: All AI providers failed. Attempted: $attempted_list. Failures: $failed_list. Last error: $last_error. Falling back to simulation.");
        return [];
    }
    
    /**
     * Generate assignment evaluation using AI providers with fallback
     */
    public function generateAssignmentEvaluation($prompt) {
        // Refresh providers from database to get latest configuration
        $this->ai_checker->refreshProviders();
        
        if (!$this->ai_checker->hasProviders()) {
            error_log("Enhanced AI Service: No AI providers available for assignment evaluation");
            return null;
        }
        
        $fallback_chain = $this->ai_checker->getFallbackChain();
        $failed_providers = [];
        $attempted_providers = [];
        $last_error = null;
        
        // Try each provider in the fallback chain
        $total_providers = count($fallback_chain);
        $current_provider_index = 0;
        
        foreach ($fallback_chain as $provider_name) {
            $current_provider_index++;
            $provider = $this->ai_checker->getProvider($provider_name);
            
            if (!$provider || !$provider['enabled'] || empty($provider['api_key'])) {
                error_log("Enhanced AI Service: Provider $provider_name ($current_provider_index/$total_providers) is not available for assignment evaluation, skipping");
                $failed_providers[] = $provider_name . " (disabled/missing key)";
                continue;
            }
            
            $attempted_providers[] = $provider_name;
            error_log("Enhanced AI Service: Trying provider $provider_name ($current_provider_index/$total_providers) for assignment evaluation");
            
            try {
                $start_time = microtime(true);
                error_log("Enhanced AI Service: Attempting to generate assignment evaluation with provider: $provider_name");
                
                $content = $this->callAssignmentProvider($provider, $prompt);
                $response_time = (microtime(true) - $start_time) * 1000;
                
                if (!empty($content)) {
                    // Log successful usage
                    $this->logProviderUsage($provider, [], $response_time, true);
                    error_log("✅ Enhanced AI Service: Successfully generated assignment evaluation using provider: $provider_name (Response time: {$response_time}ms)");
                    return $content;
                } else {
                    error_log("❌ Enhanced AI Service: Provider $provider_name returned empty content, trying next provider");
                    $failed_providers[] = $provider_name . " (empty response)";
                }
            } catch (Exception $e) {
                // Check if it's a timeout error
                if (strpos($e->getMessage(), 'timeout') !== false || strpos($e->getMessage(), 'Timeout') !== false) {
                    error_log("⏰ Enhanced AI Service: Timeout for $provider_name, trying next provider");
                    $failed_providers[] = $provider_name . " (timeout)";
                    continue;
                }
                
                // Check if it's a rate limit error
                if (strpos($e->getMessage(), 'Rate limit') !== false || strpos($e->getMessage(), '429') !== false) {
                    error_log("⏳ Enhanced AI Service: Rate limit hit for $provider_name, trying next provider immediately");
                    $failed_providers[] = $provider_name . " (rate limit)";
                    continue;
                }
                
                // Try to auto-fix the error
                $fixed = $this->autoFixProviderError($provider_name, $e->getMessage());
                if ($fixed) {
                    error_log("🔧 Enhanced AI Service: Auto-fixed error for $provider_name, retrying...");
                    // Refresh providers to get updated configuration
                    $this->ai_checker->refreshProviders();
                    
                    // Retry with the same provider
                    try {
                        $start_time = microtime(true);
                        $updated_provider = $this->ai_checker->getProvider($provider_name);
                        
                        if ($updated_provider) {
                            error_log("Enhanced AI Service: Retrying assignment evaluation with auto-fixed provider: $provider_name");
                            
                            $content = $this->callAssignmentProvider($updated_provider, $prompt);
                            $response_time = (microtime(true) - $start_time) * 1000;
                            
                            if (!empty($content)) {
                                // Log successful usage
                                $this->logProviderUsage($updated_provider, [], $response_time, true);
                                error_log("✅ Enhanced AI Service: Successfully generated assignment evaluation using auto-fixed provider: $provider_name (Response time: {$response_time}ms)");
                                return $content;
                            }
                        }
                    } catch (Exception $retry_e) {
                        error_log("❌ Enhanced AI Service: Auto-fixed provider $provider_name still failed: " . $retry_e->getMessage());
                    }
                }
                
                // Log failed usage
                $this->logProviderUsage($provider, [], 0, false, $e->getMessage());
                $failed_providers[] = $provider_name . " (" . $this->getShortErrorMessage($e->getMessage()) . ")";
                $last_error = $e->getMessage();
                error_log("❌ Enhanced AI Service: Provider $provider_name failed: " . $e->getMessage() . " - Trying next provider");
                
                // Add a small delay before trying the next provider to avoid overwhelming services
                if (count($attempted_providers) < count($fallback_chain)) {
                    error_log("⏳ Enhanced AI Service: Waiting 2 seconds before trying next provider...");
                    sleep(2);
                }
                continue;
            }
        }
        
        // If all providers fail, log the failure and return null
        $failed_list = implode(', ', $failed_providers);
        $attempted_list = implode(', ', $attempted_providers);
        error_log("❌ Enhanced AI Service: All AI providers failed for assignment evaluation. Attempted: $attempted_list. Failures: $failed_list. Last error: $last_error.");
        return null;
    }

    /**
     * Generate syllabus content using AI with intelligent provider selection
     */
    public function generateSyllabusContent($prompt) {
        // Refresh providers from database to get latest configuration
        $this->ai_checker->refreshProviders();
        
        if (!$this->ai_checker->hasProviders()) {
            error_log("Enhanced AI Service: No AI providers available for syllabus generation");
            return null;
        }
        
        $fallback_chain = $this->ai_checker->getFallbackChain();
        $failed_providers = [];
        $attempted_providers = [];
        $last_error = null;
        
        // Try each provider in the fallback chain
        $total_providers = count($fallback_chain);
        $current_provider_index = 0;
        
        foreach ($fallback_chain as $provider_name) {
            $current_provider_index++;
            $provider = $this->ai_checker->getProvider($provider_name);
            
            if (!$provider || !$provider['enabled'] || empty($provider['api_key'])) {
                error_log("Enhanced AI Service: Provider $provider_name ($current_provider_index/$total_providers) is not available for syllabus generation, skipping");
                $failed_providers[] = $provider_name . " (disabled/missing key)";
                continue;
            }
            
            $attempted_providers[] = $provider_name;
            error_log("Enhanced AI Service: Trying provider $provider_name ($current_provider_index/$total_providers) for syllabus generation");
            
            try {
                $start_time = microtime(true);
                error_log("Enhanced AI Service: Attempting to generate syllabus content with provider: $provider_name");
                
                $content = $this->callSyllabusProvider($provider, $prompt);
                $response_time = (microtime(true) - $start_time) * 1000;
                
                if (!empty($content)) {
                    // Log successful usage
                    $this->logProviderUsage($provider, [], $response_time, true);
                    error_log("✅ Enhanced AI Service: Successfully generated syllabus content using provider: $provider_name (Response time: {$response_time}ms)");
                    return $content;
                } else {
                    error_log("❌ Enhanced AI Service: Provider $provider_name returned empty content, trying next provider");
                    $failed_providers[] = $provider_name . " (empty response)";
                }
            } catch (Exception $e) {
                // Check if it's a timeout error
                if (strpos($e->getMessage(), 'timeout') !== false || strpos($e->getMessage(), 'Timeout') !== false) {
                    error_log("⏰ Enhanced AI Service: Timeout for $provider_name, trying next provider");
                    $failed_providers[] = $provider_name . " (timeout)";
                    continue;
                }
                
                // Check if it's a rate limit error
                if (strpos($e->getMessage(), 'Rate limit') !== false || strpos($e->getMessage(), '429') !== false) {
                    error_log("⏳ Enhanced AI Service: Rate limit hit for $provider_name, trying next provider immediately");
                    $failed_providers[] = $provider_name . " (rate limit)";
                    continue;
                }
                
                // Try to auto-fix the error
                $fixed = $this->autoFixProviderError($provider_name, $e->getMessage());
                if ($fixed) {
                    error_log("🔧 Enhanced AI Service: Auto-fixed error for $provider_name, retrying...");
                    // Refresh providers to get updated configuration
                    $this->ai_checker->refreshProviders();
                    
                    // Retry with the same provider
                    try {
                        $start_time = microtime(true);
                        $updated_provider = $this->ai_checker->getProvider($provider_name);
                        
                        if ($updated_provider) {
                            error_log("Enhanced AI Service: Retrying syllabus generation with auto-fixed provider: $provider_name");
                            
                            $content = $this->callSyllabusProvider($updated_provider, $prompt);
                            $response_time = (microtime(true) - $start_time) * 1000;
                            
                            if (!empty($content)) {
                                // Log successful usage
                                $this->logProviderUsage($updated_provider, [], $response_time, true);
                                error_log("✅ Enhanced AI Service: Successfully generated syllabus content using auto-fixed provider: $provider_name (Response time: {$response_time}ms)");
                                return $content;
                            }
                        }
                    } catch (Exception $retry_e) {
                        error_log("❌ Enhanced AI Service: Auto-fixed provider $provider_name still failed: " . $retry_e->getMessage());
                    }
                }
                
                // Log failed usage
                $this->logProviderUsage($provider, [], 0, false, $e->getMessage());
                $failed_providers[] = $provider_name . " (" . $this->getShortErrorMessage($e->getMessage()) . ")";
                $last_error = $e->getMessage();
                error_log("❌ Enhanced AI Service: Provider $provider_name failed: " . $e->getMessage() . " - Trying next provider");
                
                // Add a small delay before trying the next provider to avoid overwhelming services
                if (count($attempted_providers) < count($fallback_chain)) {
                    error_log("⏳ Enhanced AI Service: Waiting 2 seconds before trying next provider...");
                    sleep(2);
                }
                continue;
            }
        }
        
        // If all providers fail, log the failure and return null
        $failed_list = implode(', ', $failed_providers);
        $attempted_list = implode(', ', $attempted_providers);
        error_log("❌ Enhanced AI Service: All AI providers failed for syllabus generation. Attempted: $attempted_list. Failures: $failed_list. Last error: $last_error.");
        return null;
    }
    
    /**
     * Call a specific AI provider for question generation
     */
    private function callProvider($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions) {
        $provider_type = $provider['provider_type'];
        
        switch ($provider_type) {
            case 'openai':
            case 'cursor':
                return $this->callOpenAI($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
            case 'anthropic':
                return $this->callAnthropic($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
            case 'google':
                return $this->callGoogle($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
            case 'azure':
                return $this->callAzure($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
            default:
                throw new Exception("Unknown provider type: $provider_type (Provider: " . $provider['name'] . ")");
        }
    }
    
    /**
     * Call a specific AI provider for syllabus generation
     */
    private function callSyllabusProvider($provider, $prompt) {
        $provider_type = $provider['provider_type'];
        
        switch ($provider_type) {
            case 'openai':
            case 'cursor':
                return $this->callOpenAISyllabus($provider, $prompt);
            case 'anthropic':
                return $this->callAnthropicSyllabus($provider, $prompt);
            case 'google':
                return $this->callGoogleSyllabus($provider, $prompt);
            case 'azure':
                return $this->callAzureSyllabus($provider, $prompt);
            default:
                throw new Exception("Unknown provider type: $provider_type (Provider: " . $provider['name'] . ")");
        }
    }
    
    /**
     * Call a specific AI provider for assignment evaluation
     */
    private function callAssignmentProvider($provider, $prompt) {
        $provider_type = $provider['provider_type'];
        
        switch ($provider_type) {
            case 'openai':
            case 'cursor':
                return $this->callOpenAIAssignment($provider, $prompt);
            case 'anthropic':
                return $this->callAnthropicAssignment($provider, $prompt);
            case 'google':
                return $this->callGoogleAssignment($provider, $prompt);
            case 'azure':
                return $this->callAzureAssignment($provider, $prompt);
            default:
                throw new Exception("Unknown provider type: $provider_type (Provider: " . $provider['name'] . ")");
        }
    }
    
    /**
     * Call OpenAI API for question generation
     */
    private function callOpenAI($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions) {
        if (empty($provider['api_key'])) {
            throw new Exception("OpenAI API key is not configured for provider: " . $provider['name']);
        }
        
        $combined_content = $this->combineLessonContent($lesson_contents);
        $prompt = $this->createPrompt($combined_content, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
        
        $data = [
            'model' => $provider['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $provider['instructions'] ?: 'You are an expert educational content creator specializing in creating high-quality quiz questions based on educational materials.'
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
     * Call Anthropic Claude API for question generation
     */
    private function callAnthropic($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions) {
        if (empty($provider['api_key'])) {
            throw new Exception("Anthropic API key is not configured for provider: " . $provider['name']);
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
     * Call Google Gemini API for question generation
     */
    private function callGoogle($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions) {
        if (empty($provider['api_key'])) {
            throw new Exception("Google API key is not configured for provider: " . $provider['name']);
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
     * Call Azure OpenAI API for question generation
     */
    private function callAzure($provider, $lesson_contents, $question_count, $difficulty, $question_types, $case_sensitive, $instructions) {
        if (empty($provider['api_key'])) {
            throw new Exception("Azure API key is not configured for provider: " . $provider['name']);
        }
        if (empty($provider['api_url'])) {
            throw new Exception("Azure API URL is not configured for provider: " . $provider['name']);
        }
        
        $combined_content = $this->combineLessonContent($lesson_contents);
        $prompt = $this->createPrompt($combined_content, $question_count, $difficulty, $question_types, $case_sensitive, $instructions);
        
        $data = [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $provider['instructions'] ?: 'You are an expert educational content creator specializing in creating high-quality quiz questions based on educational materials.'
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
     * Call OpenAI API for syllabus generation
     */
    private function callOpenAISyllabus($provider, $prompt) {
        if (empty($provider['api_key'])) {
            throw new Exception("OpenAI API key is not configured for provider: " . $provider['name']);
        }
        
        $data = [
            'model' => $provider['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $provider['instructions'] ?: 'You are an expert academic curriculum designer specializing in Philippine higher education standards and CHED (Commission on Higher Education) guidelines.'
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
            throw new Exception("Anthropic API key is not configured for provider: " . $provider['name']);
        }
        
        $enhanced_prompt = ($provider['instructions'] ?: 'You are an expert academic curriculum designer specializing in Philippine higher education standards and CHED (Commission on Higher Education) guidelines. ') . $prompt;
        
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
            throw new Exception("Google API key is not configured for provider: " . $provider['name']);
        }
        
        $enhanced_prompt = ($provider['instructions'] ?: 'You are an expert academic curriculum designer specializing in Philippine higher education standards and CHED (Commission on Higher Education) guidelines. ') . $prompt;
        
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
            throw new Exception("Azure API key is not configured for provider: " . $provider['name']);
        }
        if (empty($provider['api_url'])) {
            throw new Exception("Azure API URL is not configured for provider: " . $provider['name']);
        }
        
        $data = [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $provider['instructions'] ?: 'You are an expert academic curriculum designer specializing in Philippine higher education standards and CHED (Commission on Higher Education) guidelines.'
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
     * Make HTTP request with enhanced error handling
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
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->request_timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); // Increased connection timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("Enhanced AI Service cURL error: $curl_error");
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
            error_log("Enhanced AI Service HTTP error: $error_message");
            throw new Exception($error_message);
        }
        
        return $response;
    }
    
    /**
     * Combine lesson content for AI processing
     */
    private function combineLessonContent($lesson_contents) {
        if (is_string($lesson_contents)) {
            return $lesson_contents;
        }
        
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
     * Create AI prompt for question generation
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
     * Call OpenAI API for assignment evaluation
     */
    private function callOpenAIAssignment($provider, $prompt) {
        if (empty($provider['api_key'])) {
            throw new Exception("OpenAI API key is not configured for provider: " . $provider['name']);
        }
        
        $data = [
            'model' => $provider['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $provider['instructions'] ?: 'You are an expert academic evaluator specializing in comprehensive assignment assessment, plagiarism detection, and AI content analysis for Philippine higher education standards.'
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
        
        return $this->parseOpenAIAssignmentResponse($response);
    }
    
    /**
     * Call Anthropic Claude API for assignment evaluation
     */
    private function callAnthropicAssignment($provider, $prompt) {
        if (empty($provider['api_key'])) {
            throw new Exception("Anthropic API key is not configured for provider: " . $provider['name']);
        }
        
        $enhanced_prompt = ($provider['instructions'] ?: 'You are an expert academic evaluator specializing in comprehensive assignment assessment, plagiarism detection, and AI content analysis for Philippine higher education standards. ') . $prompt;
        
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
        
        return $this->parseAnthropicAssignmentResponse($response);
    }
    
    /**
     * Call Google Gemini API for assignment evaluation
     */
    private function callGoogleAssignment($provider, $prompt) {
        if (empty($provider['api_key'])) {
            throw new Exception("Google API key is not configured for provider: " . $provider['name']);
        }
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => ($provider['instructions'] ?: 'You are an expert academic evaluator specializing in comprehensive assignment assessment, plagiarism detection, and AI content analysis for Philippine higher education standards. ') . $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $provider['temperature'],
                'maxOutputTokens' => $provider['max_tokens']
            ]
        ];
        
        $response = $this->makeHttpRequest($provider['api_url'], $data, [
            'x-goog-api-key: ' . $provider['api_key']
        ]);
        
        return $this->parseGoogleAssignmentResponse($response);
    }
    
    /**
     * Call Azure OpenAI API for assignment evaluation
     */
    private function callAzureAssignment($provider, $prompt) {
        if (empty($provider['api_key'])) {
            throw new Exception("Azure API key is not configured for provider: " . $provider['name']);
        }
        
        $data = [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $provider['instructions'] ?: 'You are an expert academic evaluator specializing in comprehensive assignment assessment, plagiarism detection, and AI content analysis for Philippine higher education standards.'
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
        
        return $this->parseAzureAssignmentResponse($response);
    }
    
    /**
     * Parse OpenAI response for assignment evaluation
     */
    private function parseOpenAIAssignmentResponse($response) {
        $result = json_decode($response, true);
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("Invalid OpenAI response format");
        }
        
        $ai_content = $result['choices'][0]['message']['content'];
        return $this->extractAssignmentFromContent($ai_content);
    }
    
    /**
     * Parse Anthropic response for assignment evaluation
     */
    private function parseAnthropicAssignmentResponse($response) {
        $result = json_decode($response, true);
        if (!isset($result['content'][0]['text'])) {
            throw new Exception("Invalid Anthropic response format");
        }
        
        $ai_content = $result['content'][0]['text'];
        return $this->extractAssignmentFromContent($ai_content);
    }
    
    /**
     * Parse Google response for assignment evaluation
     */
    private function parseGoogleAssignmentResponse($response) {
        $result = json_decode($response, true);
        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid Google response format");
        }
        
        $ai_content = $result['candidates'][0]['content']['parts'][0]['text'];
        return $this->extractAssignmentFromContent($ai_content);
    }
    
    /**
     * Parse Azure response for assignment evaluation
     */
    private function parseAzureAssignmentResponse($response) {
        $result = json_decode($response, true);
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("Invalid Azure response format");
        }
        
        $ai_content = $result['choices'][0]['message']['content'];
        return $this->extractAssignmentFromContent($ai_content);
    }
    
    /**
     * Extract assignment evaluation data from AI content
     */
    private function extractAssignmentFromContent($ai_content) {
        // Check if the content is JSON first
        $json_start = strpos($ai_content, '{');
        $json_end = strrpos($ai_content, '}') + 1;
        
        if ($json_start !== false && $json_end !== false) {
            // Try to parse as JSON
            $json_content = substr($ai_content, $json_start, $json_end - $json_start);
            $assignment_data = json_decode($json_content, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $assignment_data;
            }
        }
        
        // If not JSON or JSON parsing failed, return the content as-is (HTML)
        return $ai_content;
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
                // Handle lesson_id assignment
                if (is_array($lesson_contents) && !empty($lesson_contents)) {
                    $question['lesson_id'] = $lesson_contents[0]['id'];
                } else {
                    $question['lesson_id'] = null;
                }
                // Calculate and assign points based on question complexity
                $question['points'] = $this->calculateQuestionPoints($question);
                $validated_questions[] = $question;
            }
        }
        return $validated_questions;
    }
    
    /**
     * Extract syllabus data from AI content
     */
    private function extractSyllabusFromContent($ai_content) {
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
        return $ai_content;
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
                $type_multiplier = 0.8;
                break;
            case 'multiple_choice':
                $type_multiplier = 1;
                break;
            case 'fill_blank':
                $type_multiplier = 1.2;
                break;
            case 'short_answer':
                $type_multiplier = 1.5;
                break;
            case 'essay':
                $type_multiplier = 2;
                break;
            default:
                $type_multiplier = 1;
        }
        
        // Complexity bonus based on question length and content
        $question_length = strlen($question['question']);
        if ($question_length > 200) {
            $complexity_bonus += 1;
        }
        if ($question_length > 400) {
            $complexity_bonus += 1;
        }
        
        // Check for complex keywords that indicate higher-order thinking
        $complex_keywords = ['explain', 'analyze', 'compare', 'contrast', 'evaluate', 'synthesize', 'justify', 'demonstrate', 'apply', 'create'];
        $question_lower = strtolower($question['question']);
        foreach ($complex_keywords as $keyword) {
            if (strpos($question_lower, $keyword) !== false) {
                $complexity_bonus += 1;
                break;
            }
        }
        
        // Calculate final points
        $points = round($base_points * $difficulty_multiplier * $type_multiplier + $complexity_bonus);
        
        // Ensure minimum of 1 point and maximum of 10 points
        return max(1, min(10, $points));
    }
    
    /**
     * Log provider usage
     */
    private function logProviderUsage($provider, $questions, $response_time, $success, $error_message = null) {
        if (!$this->conn) {
            return;
        }
        
        // Skip logging for faculty users since they're not in the users table
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher') {
            return;
        }
        
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
            $success_int = $success ? 1 : 0;
            $error_message = $error_message ?: null;
            $response_time_int = (int)round($response_time);
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
     * Auto-fix provider errors using the existing AI service auto-fix system
     */
    private function autoFixProviderError($provider_name, $error_message) {
        // Handle auto-fix logic directly here
        error_log("Enhanced AI Service: Attempting to auto-fix error for provider: $provider_name");
        
        // For rate limit errors, we'll just log and return false to try next provider
        if (strpos($error_message, 'Rate limit') !== false || strpos($error_message, '429') !== false) {
            error_log("Enhanced AI Service: Rate limit detected for $provider_name, will try next provider");
            return false;
        }
        
        // For other errors, we'll also return false to try next provider
        error_log("Enhanced AI Service: Auto-fix not available for this error type, will try next provider");
        return false;
    }
    
    /**
     * Get provider status
     */
    public function getProviderStatus() {
        return $this->ai_checker->getProviderStatus();
    }
    
    /**
     * Check all providers health
     */
    public function checkAllProvidersHealth($force_refresh = false) {
        return $this->ai_checker->checkAllProvidersHealth($force_refresh);
    }
    
    /**
     * Get provider statistics
     */
    public function getProviderStatistics() {
        return $this->ai_checker->getProviderStatistics();
    }
    
    /**
     * Get best available provider
     */
    public function getBestProvider() {
        return $this->ai_checker->getBestProvider();
    }
    
    /**
     * Refresh providers from database
     */
    public function refreshProviders() {
        return $this->ai_checker->refreshProviders();
    }
    
    /**
     * Check if any providers are available
     */
    public function hasProviders() {
        return $this->ai_checker->hasProviders();
    }
}
?>
