<?php
require_once dirname(__FILE__) . '/../config/api_keys.php';

/**
 * Sentiment Analysis Class
 * Supports multiple sentiment analysis methods with fallback options
 */
class SentimentAnalysis {
    private $api_keys;
    private $enabled_methods;

    public function __construct() {
        global $api_keys, $sentiment_methods;
        $this->api_keys = $api_keys;
        $this->enabled_methods = $sentiment_methods['enabled_methods'];
    }

    /**
     * Analyze sentiment of text using multiple methods
     */
    public function analyzeSentiment($text, $method = null) {
        if (empty($text)) {
            return $this->getDefaultResult();
        }

        // Clean and prepare text
        $text = $this->cleanText($text);

        // If specific method requested, try that first
        if ($method && in_array($method, $this->enabled_methods)) {
            $result = $this->analyzeWithMethod($text, $method);
            if ($result) {
                return $result;
            }
        }

        // Try enabled methods in order
        foreach ($this->enabled_methods as $method) {
            $result = $this->analyzeWithMethod($text, $method);
            if ($result) {
                return $result;
            }
        }

        // Fallback to keyword-based analysis
        return $this->keywordBasedAnalysis($text);
    }

    /**
     * Analyze sentiment using specific method
     */
    private function analyzeWithMethod($text, $method) {
        switch ($method) {
            case 'vader':
                return $this->vaderSentiment($text);
            case 'textblob':
                return $this->textBlobSentiment($text);
            case 'huggingface':
                return $this->huggingFaceSentiment($text);
            case 'openai':
                return $this->openAISentiment($text);
            case 'watson':
                return $this->watsonSentiment($text);
            case 'google':
                return $this->googleSentiment($text);
            case 'azure':
                return $this->azureSentiment($text);
            default:
                return null;
        }
    }

    /**
     * VADER Sentiment Analysis (Local Implementation)
     */
    private function vaderSentiment($text) {
        // VADER lexicon for sentiment analysis with education-specific terms
        $positive_words = [
            'love', 'enjoy', 'excellent', 'great', 'good', 'wonderful', 'amazing', 'fantastic',
            'perfect', 'outstanding', 'brilliant', 'superb', 'terrific', 'awesome', 'fabulous',
            'confident', 'satisfied', 'happy', 'pleased', 'delighted', 'thrilled', 'excited',
            'best', 'superior', 'exceptional', 'marvelous', 'splendid', 'engaging', 'motivating',
            'inspiring', 'helpful', 'supportive', 'encouraging', 'clear', 'organized', 'structured',
            'interactive', 'interesting', 'fun', 'enjoyable', 'satisfying', 'rewarding', 'valuable',
            'knowledgeable', 'skilled', 'effective', 'successful', 'productive', 'beneficial'
        ];

        $negative_words = [
            'hate', 'terrible', 'awful', 'horrible', 'bad', 'worst', 'disgusting', 'disappointing',
            'frustrated', 'angry', 'annoyed', 'irritated', 'upset', 'sad', 'depressed', 'confused',
            'boring', 'stupid', 'ridiculous', 'disaster', 'nightmare', 'useless', 'pointless',
            'worst', 'dreadful', 'atrocious', 'abysmal', 'chaotic', 'disorganized', 'unclear',
            'confusing', 'frustrating', 'annoying', 'boring', 'uninteresting', 'unmotivating',
            'discouraging', 'unsupportive', 'unhelpful', 'waste', 'useless', 'ineffective',
            'struggles', 'problems', 'issues', 'concerns', 'worries', 'difficult', 'hard'
        ];

        $intensifiers = [
            'very', 'really', 'extremely', 'absolutely', 'completely', 'totally', 'incredibly',
            'amazingly', 'exceptionally', 'particularly', 'especially', 'notably', 'so', 'too',
            'quite', 'rather', 'pretty', 'fairly', 'somewhat', 'slightly'
        ];

        $negations = [
            'not', 'no', 'never', 'none', 'nobody', 'nothing', 'neither', 'nowhere', 'hardly',
            'barely', 'scarcely', 'doesn\'t', 'isn\'t', 'wasn\'t', 'shouldn\'t', 'wouldn\'t',
            'couldn\'t', 'won\'t', 'can\'t', 'don\'t', 'didn\'t', 'hasn\'t', 'haven\'t', 'hadn\'t',
            'without', 'lack', 'missing', 'absent'
        ];

        $text_lower = strtolower($text);
        $words = preg_split('/\s+/', $text_lower);

        $positive_score = 0;
        $negative_score = 0;
        $intensifier_multiplier = 1;
        $negation_multiplier = 1;
        $word_count = 0;

        foreach ($words as $word) {
            $word = trim($word, '.,!?;:()[]{}"\'-');
            if (strlen($word) > 0) {
                $word_count++;
            }

            if (in_array($word, $positive_words)) {
                $positive_score += $intensifier_multiplier * $negation_multiplier;
            } elseif (in_array($word, $negative_words)) {
                $negative_score += $intensifier_multiplier * $negation_multiplier;
            } elseif (in_array($word, $intensifiers)) {
                $intensifier_multiplier = 1.5;
            } elseif (in_array($word, $negations)) {
                $negation_multiplier = -1;
            } else {
                $intensifier_multiplier = 1;
                $negation_multiplier = 1;
            }
        }

        // Calculate compound score with better normalization for educational feedback
        if ($word_count > 0) {
            $compound_score = ($positive_score - $negative_score) / $word_count;
        } else {
            $compound_score = 0;
        }

        // Apply adjusted normalization for educational context
        $compound_score = max(-1, min(1, $compound_score * 4));

        // Additional boost for strong positive/negative indicators
        if ($positive_score > 0 && $negative_score == 0) {
            $compound_score = max(0.2, $compound_score);
        }
        if ($negative_score > 0 && $positive_score == 0) {
            $compound_score = min(-0.2, $compound_score);
        }

        return $this->normalizeResult($compound_score, 'vader');
    }

    /**
     * TextBlob-style Sentiment Analysis (Local Implementation)
     */
    private function textBlobSentiment($text) {
        // Simple polarity calculation based on positive/negative word ratios
        $positive_words = [
            'good', 'great', 'excellent', 'amazing', 'wonderful', 'fantastic', 'perfect',
            'love', 'enjoy', 'happy', 'pleased', 'satisfied', 'confident', 'excited'
        ];

        $negative_words = [
            'bad', 'terrible', 'awful', 'horrible', 'worst', 'hate', 'dislike', 'angry',
            'sad', 'frustrated', 'confused', 'boring', 'annoyed', 'disappointed'
        ];

        $text_lower = strtolower($text);
        $words = preg_split('/\s+/', $text_lower);

        $positive_count = 0;
        $negative_count = 0;

        foreach ($words as $word) {
            $word = trim($word, '.,!?;:()[]{}"\'-');
            if (in_array($word, $positive_words)) {
                $positive_count++;
            } elseif (in_array($word, $negative_words)) {
                $negative_count++;
            }
        }

        $total_words = count($words);
        if ($total_words == 0) {
            return $this->getDefaultResult();
        }

        $polarity = ($positive_count - $negative_count) / $total_words;
        $polarity = max(-1, min(1, $polarity));

        return $this->normalizeResult($polarity, 'textblob');
    }

    /**
     * Hugging Face Sentiment Analysis
     */
    private function huggingFaceSentiment($text) {
        if (empty($this->api_keys['huggingface']['api_key'])) {
            return null;
        }

        $url = $this->api_keys['huggingface']['base_url'] . '/' . $this->api_keys['huggingface']['model'];
        $headers = [
            'Authorization: Bearer ' . $this->api_keys['huggingface']['api_key'],
            'Content-Type: application/json'
        ];

        $data = json_encode(['inputs' => $text]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200 && $response) {
            $result = json_decode($response, true);
            if (is_array($result) && !empty($result)) {
                return $this->processHuggingFaceResult($result[0]);
            }
        }

        return null;
    }

    /**
     * OpenAI GPT Sentiment Analysis
     */
    private function openAISentiment($text) {
        if (empty($this->api_keys['openai']['api_key'])) {
            return null;
        }

        $url = $this->api_keys['openai']['base_url'] . '/chat/completions';
        $headers = [
            'Authorization: Bearer ' . $this->api_keys['openai']['api_key'],
            'Content-Type: application/json'
        ];

        $prompt = "Analyze the sentiment of this text and return only a JSON response with 'sentiment' (positive/negative/neutral), 'score' (0-1), and 'emotion' (happy/sad/angry/neutral/confused/frustrated/excited/concerned): " . $text;

        $data = json_encode([
            'model' => $this->api_keys['openai']['model'],
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 100,
            'temperature' => 0.3
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                $content = $result['choices'][0]['message']['content'];
                $sentiment_data = json_decode($content, true);
                if ($sentiment_data) {
                    return $this->processOpenAIResult($sentiment_data);
                }
            }
        }

        return null;
    }

    /**
     * Keyword-based fallback analysis
     */
    private function keywordBasedAnalysis($text) {
        $text_lower = strtolower($text);

        // Emotion detection patterns
        $emotions = [
            'happy' => ['love', 'enjoy', 'excellent', 'confident', 'satisfied', 'excited', 'wonderful', 'amazing'],
            'sad' => ['sad', 'stupid', 'concerned', 'frustrated', 'confused', 'disappointed', 'upset'],
            'angry' => ['boring', 'annoyed', 'terrible', 'disaster', 'ridiculous', 'hate', 'angry'],
            'neutral' => ['okay', 'fine', 'normal', 'average', 'standard', 'typical']
        ];

        $detected_emotion = 'neutral';
        $max_matches = 0;

        foreach ($emotions as $emotion => $keywords) {
            $matches = 0;
            foreach ($keywords as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    $matches++;
                }
            }
            if ($matches > $max_matches) {
                $max_matches = $matches;
                $detected_emotion = $emotion;
            }
        }

        // Calculate sentiment score based on emotion
        $sentiment_scores = [
            'happy' => 0.8,
            'sad' => -0.6,
            'angry' => -0.8,
            'neutral' => 0.0
        ];

        $score = $sentiment_scores[$detected_emotion];

        return [
            'sentiment' => $score > 0.3 ? 'positive' : ($score < -0.3 ? 'negative' : 'neutral'),
            'score' => abs($score),
            'emotion' => $detected_emotion,
            'method' => 'keyword',
            'confidence' => 0.7,
            'label' => $detected_emotion,
            'color' => $this->getEmotionColor($score > 0.3 ? 'positive' : ($score < -0.3 ? 'negative' : 'neutral'), $score),
            'bg' => $this->getEmotionBackground($score > 0.3 ? 'positive' : ($score < -0.3 ? 'negative' : 'neutral'), $score),
            'border' => $this->getEmotionBorder($score > 0.3 ? 'positive' : ($score < -0.3 ? 'negative' : 'neutral'), $score),
            'icon' => $this->getEmotionIcon($score > 0.3 ? 'positive' : ($score < -0.3 ? 'negative' : 'neutral'), $score)
        ];
    }

    /**
     * Process Hugging Face API result
     */
    private function processHuggingFaceResult($result) {
        if (!isset($result['label']) || !isset($result['score'])) {
            return null;
        }

        $label = strtolower($result['label']);
        $score = $result['score'];

        $sentiment_map = [
            'positive' => 'positive',
            'negative' => 'negative',
            'neutral' => 'neutral',
            'label_0' => 'negative',
            'label_1' => 'neutral',
            'label_2' => 'positive'
        ];

        $sentiment = $sentiment_map[$label] ?? 'neutral';

        return [
            'sentiment' => $sentiment,
            'score' => $score,
            'emotion' => $this->mapSentimentToEmotion($sentiment, $score),
            'method' => 'huggingface',
            'confidence' => $score,
            'label' => $this->mapSentimentToEmotion($sentiment, $score),
            'color' => $this->getEmotionColor($sentiment, $score),
            'bg' => $this->getEmotionBackground($sentiment, $score),
            'border' => $this->getEmotionBorder($sentiment, $score),
            'icon' => $this->getEmotionIcon($sentiment, $score)
        ];
    }

    /**
     * Process OpenAI API result
     */
    private function processOpenAIResult($data) {
        return [
            'sentiment' => $data['sentiment'] ?? 'neutral',
            'score' => $data['score'] ?? 0.5,
            'emotion' => $data['emotion'] ?? 'neutral',
            'method' => 'openai',
            'confidence' => 0.9,
            'label' => $this->mapSentimentToEmotion($data['sentiment'] ?? 'neutral', $data['score'] ?? 0.5),
            'color' => $this->getEmotionColor($data['sentiment'] ?? 'neutral', $data['score'] ?? 0.5),
            'bg' => $this->getEmotionBackground($data['sentiment'] ?? 'neutral', $data['score'] ?? 0.5),
            'border' => $this->getEmotionBorder($data['sentiment'] ?? 'neutral', $data['score'] ?? 0.5),
            'icon' => $this->getEmotionIcon($data['sentiment'] ?? 'neutral', $data['score'] ?? 0.5)
        ];
    }

    /**
     * Normalize result from different methods
     */
    private function normalizeResult($score, $method) {
        // Adjust thresholds for better sentiment detection in educational context
        $sentiment = $score > 0.1 ? 'positive' : ($score < -0.1 ? 'negative' : 'neutral');

        return [
            'sentiment' => $sentiment,
            'score' => abs($score),
            'emotion' => $this->mapSentimentToEmotion($sentiment, $score),
            'method' => $method,
            'confidence' => 0.8,
            'label' => $this->mapSentimentToEmotion($sentiment, $score),
            'color' => $this->getEmotionColor($sentiment, $score),
            'bg' => $this->getEmotionBackground($sentiment, $score),
            'border' => $this->getEmotionBorder($sentiment, $score),
            'icon' => $this->getEmotionIcon($sentiment, $score)
        ];
    }

    /**
     * Get emotion color for styling
     */
    private function getEmotionColor($sentiment, $score) {
        if ($sentiment === 'positive') {
            if ($score > 0.7) return 'green';
            return 'green';
        } elseif ($sentiment === 'negative') {
            if ($score > 0.7) return 'red';
            if ($score > 0.5) return 'orange';
            return 'blue';
        } else {
            return 'gray';
        }
    }

    /**
     * Get emotion background for styling
     */
    private function getEmotionBackground($sentiment, $score) {
        if ($sentiment === 'positive') {
            if ($score > 0.7) return 'bg-green-50';
            return 'bg-green-50';
        } elseif ($sentiment === 'negative') {
            if ($score > 0.7) return 'bg-red-50';
            if ($score > 0.5) return 'bg-orange-50';
            return 'bg-blue-50';
        } else {
            return 'bg-gray-50';
        }
    }

    /**
     * Get emotion border for styling
     */
    private function getEmotionBorder($sentiment, $score) {
        if ($sentiment === 'positive') {
            if ($score > 0.7) return 'border-green-200';
            return 'border-green-200';
        } elseif ($sentiment === 'negative') {
            if ($score > 0.7) return 'border-red-200';
            if ($score > 0.5) return 'border-orange-200';
            return 'border-blue-200';
        } else {
            return 'border-gray-200';
        }
    }

    /**
     * Get emotion icon for styling
     */
    private function getEmotionIcon($sentiment, $score) {
        if ($sentiment === 'positive') {
            if ($score > 0.7) return 'fa-star';
            if ($score > 0.5) return 'fa-thumbs-up';
            return 'fa-check-circle';
        } elseif ($sentiment === 'negative') {
            if ($score > 0.7) return 'fa-exclamation-triangle';
            if ($score > 0.5) return 'fa-times-circle';
            return 'fa-question-circle';
        } else {
            return 'fa-comment';
        }
    }

    /**
     * Map sentiment to emotion
     */
    private function mapSentimentToEmotion($sentiment, $score) {
        if ($sentiment === 'positive') {
            if ($score > 0.7) return 'excited';
            if ($score > 0.5) return 'happy';
            return 'satisfied';
        } elseif ($sentiment === 'negative') {
            if ($score > 0.7) return 'angry';
            if ($score > 0.5) return 'frustrated';
            return 'concerned';
        } else {
            return 'neutral';
        }
    }

    /**
     * Clean text for analysis
     */
    private function cleanText($text) {
        // Remove extra whitespace and normalize
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Remove special characters but keep basic punctuation
        $text = preg_replace('/[^\w\s\.\,\!\?\;\:\-\(\)]/', '', $text);

        return $text;
    }

    /**
     * Get default result
     */
    private function getDefaultResult() {
        return [
            'sentiment' => 'neutral',
            'score' => 0.0,
            'emotion' => 'neutral',
            'method' => 'default',
            'confidence' => 0.0,
            'label' => 'neutral',
            'color' => 'gray',
            'bg' => 'bg-gray-50',
            'border' => 'border-gray-200',
            'icon' => 'fa-comment'
        ];
    }

    /**
     * Get available methods
     */
    public function getAvailableMethods() {
        return $this->enabled_methods;
    }

    /**
     * Test all available methods
     */
    public function testMethods($text) {
        $results = [];

        foreach ($this->enabled_methods as $method) {
            $start_time = microtime(true);
            $result = $this->analyzeWithMethod($text, $method);
            $end_time = microtime(true);

            if ($result) {
                $result['response_time'] = round(($end_time - $start_time) * 1000, 2); // ms
                $results[$method] = $result;
            }
        }

        return $results;
    }
}

// Watson, Google, and Azure implementations (commented out for brevity)
/*
private function watsonSentiment($text) {
    // IBM Watson implementation
    return null;
}

private function googleSentiment($text) {
    // Google Cloud Natural Language implementation
    return null;
}

private function azureSentiment($text) {
    // Azure Text Analytics implementation
    return null;
}
*/
?>