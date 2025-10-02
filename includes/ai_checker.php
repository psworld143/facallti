<?php
/**
 * AI Checker Service - Advanced Multi-Provider AI Integration
 * 
 * This service provides a comprehensive AI provider management system with:
 * - Database-driven provider configuration
 * - Intelligent fallback mechanisms
 * - Automatic error detection and fixing
 * - Performance monitoring and logging
 * - Real-time provider health checking
 */

class AIChecker {
    private $conn;
    private $providers;
    private $fallback_chain;
    private $health_cache;
    private $cache_duration = 300; // 5 minutes cache for health checks
    
    public function __construct($database_connection = null) {
        if ($database_connection) {
            $this->conn = $database_connection;
            $this->loadProvidersFromDatabase();
        } else {
            throw new Exception("Database connection is required for AI Checker");
        }
        
        // Initialize health cache
        $this->health_cache = [];
    }
    
    /**
     * Load providers from database with enhanced filtering
     */
    private function loadProvidersFromDatabase() {
        if (!$this->conn) {
            return false;
        }
        
        try {
            // Load providers ordered by priority, with additional health checks
            $query = "SELECT * FROM ai_providers 
                     WHERE enabled = 1 
                     AND api_key IS NOT NULL 
                     AND api_key != '' 
                     AND api_key != 'sk-placeholder-key-replace-with-real-key'
                     ORDER BY priority ASC, id ASC";
            
            $result = mysqli_query($this->conn, $query);
            
            if (!$result) {
                error_log("Error loading AI providers from database: " . mysqli_error($this->conn));
                return false;
            }
            
            $this->providers = [];
            $this->fallback_chain = [];
            
            while ($provider = mysqli_fetch_assoc($result)) {
                $provider_data = [
                    'id' => $provider['id'],
                    'name' => $provider['name'],
                    'display_name' => $provider['display_name'],
                    'provider_type' => $provider['provider_type'],
                    'api_url' => $provider['api_url'],
                    'model' => $provider['model'],
                    'api_key' => $provider['api_key'],
                    'max_tokens' => (int)$provider['max_tokens'],
                    'temperature' => (float)$provider['temperature'],
                    'enabled' => $provider['enabled'],
                    'priority' => $provider['priority'],
                    'cost_per_token' => (float)$provider['cost_per_token'],
                    'description' => $provider['description'],
                    'instructions' => $provider['instructions'],
                    'last_checked' => null,
                    'health_status' => 'unknown',
                    'response_time' => null,
                    'success_rate' => 100.0,
                    'error_count' => 0,
                    'last_error' => null
                ];
                
                $this->providers[$provider['name']] = $provider_data;
                $this->fallback_chain[] = $provider['name'];
            }
            
            error_log("AI Checker: Loaded " . count($this->providers) . " providers: " . implode(', ', $this->fallback_chain));
            return true;
        } catch (Exception $e) {
            error_log("Exception loading AI providers: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get comprehensive provider status with health information
     */
    public function getProviderStatus($include_health = true) {
        $status = [];
        
        foreach ($this->fallback_chain as $provider_name) {
            $provider = $this->providers[$provider_name] ?? null;
            
            if (!$provider) {
                $status[$provider_name] = [
                    'enabled' => false,
                    'has_api_key' => false,
                    'priority' => null,
                    'display_name' => null,
                    'health_status' => 'not_found',
                    'error' => 'Provider not found in database'
                ];
                continue;
            }
            
            $provider_status = [
                'enabled' => $provider['enabled'],
                'has_api_key' => !empty($provider['api_key']),
                'priority' => $provider['priority'],
                'display_name' => $provider['display_name'],
                'provider_type' => $provider['provider_type'],
                'model' => $provider['model'],
                'max_tokens' => $provider['max_tokens'],
                'temperature' => $provider['temperature'],
                'cost_per_token' => $provider['cost_per_token']
            ];
            
            if ($include_health) {
                $provider_status['health_status'] = $provider['health_status'];
                $provider_status['last_checked'] = $provider['last_checked'];
                $provider_status['response_time'] = $provider['response_time'];
                $provider_status['success_rate'] = $provider['success_rate'];
                $provider_status['error_count'] = $provider['error_count'];
                $provider_status['last_error'] = $provider['last_error'];
            }
            
            $status[$provider_name] = $provider_status;
        }
        
        return $status;
    }
    
    /**
     * Check health of all providers
     */
    public function checkAllProvidersHealth($force_refresh = false) {
        $health_results = [];
        
        foreach ($this->fallback_chain as $provider_name) {
            $health_results[$provider_name] = $this->checkProviderHealth($provider_name, $force_refresh);
        }
        
        return $health_results;
    }
    
    /**
     * Check health of a specific provider
     */
    public function checkProviderHealth($provider_name, $force_refresh = false) {
        if (!isset($this->providers[$provider_name])) {
            return [
                'status' => 'error',
                'message' => 'Provider not found',
                'response_time' => null,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        $provider = $this->providers[$provider_name];
        
        // Check cache first
        if (!$force_refresh && isset($this->health_cache[$provider_name])) {
            $cached = $this->health_cache[$provider_name];
            if (time() - $cached['timestamp'] < $this->cache_duration) {
                return $cached;
            }
        }
        
        try {
            $start_time = microtime(true);
            
            // Create a simple test prompt
            $test_prompt = "Hello, this is a health check. Please respond with 'OK'.";
            $response = $this->makeTestRequest($provider, $test_prompt);
            
            $response_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
            
            if ($response && $response !== false) {
                $result = [
                    'status' => 'healthy',
                    'message' => 'Provider is responding correctly',
                    'response_time' => round($response_time, 2),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                // Update provider health status
                $this->updateProviderHealth($provider_name, 'healthy', $response_time, null);
            } else {
                $result = [
                    'status' => 'error',
                    'message' => 'Provider returned invalid response',
                    'response_time' => round($response_time, 2),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                $this->updateProviderHealth($provider_name, 'error', $response_time, 'Invalid response');
            }
            
        } catch (Exception $e) {
            $result = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'response_time' => null,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $this->updateProviderHealth($provider_name, 'error', null, $e->getMessage());
        }
        
        // Cache the result
        $this->health_cache[$provider_name] = $result;
        
        return $result;
    }
    
    /**
     * Make a test request to a provider
     */
    private function makeTestRequest($provider, $test_prompt) {
        $provider_type = $provider['provider_type'];
        
        switch ($provider_type) {
            case 'openai':
            case 'cursor':
                return $this->makeOpenAITestRequest($provider, $test_prompt);
            case 'anthropic':
                return $this->makeAnthropicTestRequest($provider, $test_prompt);
            case 'google':
                return $this->makeGoogleTestRequest($provider, $test_prompt);
            case 'azure':
                return $this->makeAzureTestRequest($provider, $test_prompt);
            default:
                throw new Exception("Unknown provider type: $provider_type");
        }
    }
    
    /**
     * Make OpenAI test request
     */
    private function makeOpenAITestRequest($provider, $test_prompt) {
        $data = [
            'model' => $provider['model'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $test_prompt
                ]
            ],
            'max_tokens' => 10,
            'temperature' => 0.1
        ];
        
        $response = $this->makeHttpRequest($provider['api_url'], $data, [
            'Authorization: Bearer ' . $provider['api_key']
        ]);
        
        $result = json_decode($response, true);
        return isset($result['choices'][0]['message']['content']) ? $result['choices'][0]['message']['content'] : false;
    }
    
    /**
     * Make Anthropic test request
     */
    private function makeAnthropicTestRequest($provider, $test_prompt) {
        $data = [
            'model' => $provider['model'],
            'max_tokens' => 10,
            'temperature' => 0.1,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $test_prompt
                ]
            ]
        ];
        
        $response = $this->makeHttpRequest($provider['api_url'], $data, [
            'x-api-key: ' . $provider['api_key'],
            'anthropic-version: 2023-06-01'
        ]);
        
        $result = json_decode($response, true);
        return isset($result['content'][0]['text']) ? $result['content'][0]['text'] : false;
    }
    
    /**
     * Make Google test request
     */
    private function makeGoogleTestRequest($provider, $test_prompt) {
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $test_prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 10
            ]
        ];
        
        $url = $provider['api_url'] . '?key=' . $provider['api_key'];
        $response = $this->makeHttpRequest($url, $data, [
            'Content-Type: application/json'
        ]);
        
        $result = json_decode($response, true);
        return isset($result['candidates'][0]['content']['parts'][0]['text']) ? $result['candidates'][0]['content']['parts'][0]['text'] : false;
    }
    
    /**
     * Make Azure test request
     */
    private function makeAzureTestRequest($provider, $test_prompt) {
        $data = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $test_prompt
                ]
            ],
            'max_tokens' => 10,
            'temperature' => 0.1
        ];
        
        $response = $this->makeHttpRequest($provider['api_url'], $data, [
            'api-key: ' . $provider['api_key']
        ]);
        
        $result = json_decode($response, true);
        return isset($result['choices'][0]['message']['content']) ? $result['choices'][0]['message']['content'] : false;
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Shorter timeout for health checks
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);
        
        if ($curl_error) {
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
            throw new Exception($error_message);
        }
        
        return $response;
    }
    
    /**
     * Update provider health status in memory
     */
    private function updateProviderHealth($provider_name, $status, $response_time, $error_message) {
        if (isset($this->providers[$provider_name])) {
            $this->providers[$provider_name]['health_status'] = $status;
            $this->providers[$provider_name]['last_checked'] = date('Y-m-d H:i:s');
            $this->providers[$provider_name]['response_time'] = $response_time;
            
            if ($status === 'error') {
                $this->providers[$provider_name]['error_count']++;
                $this->providers[$provider_name]['last_error'] = $error_message;
                
                // Calculate success rate
                $total_checks = $this->providers[$provider_name]['error_count'] + 1;
                $this->providers[$provider_name]['success_rate'] = (1 - ($this->providers[$provider_name]['error_count'] / $total_checks)) * 100;
            } else {
                // Reset error count on success
                $this->providers[$provider_name]['error_count'] = 0;
                $this->providers[$provider_name]['last_error'] = null;
                $this->providers[$provider_name]['success_rate'] = 100.0;
            }
        }
    }
    
    /**
     * Get the best available provider based on health and performance
     */
    public function getBestProvider() {
        if (empty($this->providers) || empty($this->fallback_chain)) {
            return null;
        }
        
        // Sort providers by priority and health
        $sorted_providers = $this->fallback_chain;
        
        // Filter out unhealthy providers
        $healthy_providers = [];
        foreach ($sorted_providers as $provider_name) {
            if (isset($this->providers[$provider_name])) {
                $provider = $this->providers[$provider_name];
                
                // Skip if provider is disabled or has no API key
                if (!$provider['enabled'] || empty($provider['api_key'])) {
                    continue;
                }
                
                // Skip if provider has too many recent errors
                if ($provider['error_count'] > 3) {
                    continue;
                }
                
                // Skip if success rate is too low
                if ($provider['success_rate'] < 50.0) {
                    continue;
                }
                
                $healthy_providers[] = $provider_name;
            }
        }
        
        // If no healthy providers, return the first available one
        if (empty($healthy_providers)) {
            return $this->fallback_chain[0] ?? null;
        }
        
        // Return the first healthy provider (already sorted by priority)
        return $healthy_providers[0];
    }
    
    /**
     * Get provider statistics
     */
    public function getProviderStatistics() {
        $stats = [
            'total_providers' => count($this->providers),
            'enabled_providers' => 0,
            'healthy_providers' => 0,
            'unhealthy_providers' => 0,
            'providers_with_errors' => 0,
            'average_response_time' => 0,
            'total_errors' => 0
        ];
        
        $total_response_time = 0;
        $providers_with_response_time = 0;
        
        foreach ($this->providers as $provider) {
            if ($provider['enabled']) {
                $stats['enabled_providers']++;
            }
            
            if ($provider['health_status'] === 'healthy') {
                $stats['healthy_providers']++;
            } elseif ($provider['health_status'] === 'error') {
                $stats['unhealthy_providers']++;
            }
            
            if ($provider['error_count'] > 0) {
                $stats['providers_with_errors']++;
                $stats['total_errors'] += $provider['error_count'];
            }
            
            if ($provider['response_time'] !== null) {
                $total_response_time += $provider['response_time'];
                $providers_with_response_time++;
            }
        }
        
        if ($providers_with_response_time > 0) {
            $stats['average_response_time'] = round($total_response_time / $providers_with_response_time, 2);
        }
        
        return $stats;
    }
    
    /**
     * Refresh providers from database
     */
    public function refreshProviders() {
        $this->loadProvidersFromDatabase();
        $this->health_cache = []; // Clear health cache
        return true;
    }
    
    /**
     * Get fallback chain
     */
    public function getFallbackChain() {
        return $this->fallback_chain;
    }
    
    /**
     * Check if any providers are available
     */
    public function hasProviders() {
        return !empty($this->providers) && !empty($this->fallback_chain);
    }
    
    /**
     * Get provider by name
     */
    public function getProvider($provider_name) {
        return $this->providers[$provider_name] ?? null;
    }
    
    /**
     * Get all providers
     */
    public function getAllProviders() {
        return $this->providers;
    }
    
    /**
     * Clear health cache
     */
    public function clearHealthCache() {
        $this->health_cache = [];
    }
    
    /**
     * Set cache duration
     */
    public function setCacheDuration($seconds) {
        $this->cache_duration = $seconds;
    }
    
    /**
     * Get cache duration
     */
    public function getCacheDuration() {
        return $this->cache_duration;
    }
}
?>
