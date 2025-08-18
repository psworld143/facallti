<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/functions.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['question']) || empty(trim($input['question']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Question is required']);
    exit();
}

$question = trim($input['question']);
$user_email = isset($input['email']) ? trim($input['email']) : '';
$user_name = isset($input['name']) ? trim($input['name']) : '';

// Get user information
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Search for matching FAQ
$response = searchFAQResponse($conn, $question);

// If no exact match found, use keyword-based response
if (!$response) {
    $response = getKeywordBasedResponse($question);
}

// Log the inquiry
logInquiry($conn, $question, $response, $user_email, $user_name, $ip_address, $user_agent);

// Return response
echo json_encode([
    'success' => true,
    'response' => $response,
    'timestamp' => date('Y-m-d H:i:s')
]);

/**
 * Search for FAQ response based on question similarity
 */
function searchFAQResponse($conn, $question) {
    $lowerQuestion = strtolower($question);

    // First, try to find exact keyword matches
    $keywords = explode(' ', $lowerQuestion);
    $keywordConditions = [];
    $params = [];
    $types = '';

    foreach ($keywords as $keyword) {
        if (strlen($keyword) > 2) { // Only consider keywords longer than 2 characters
            $keywordConditions[] = "LOWER(keywords) LIKE ?";
            $params[] = "%$keyword%";
            $types .= 's';
        }
    }

    if (!empty($keywordConditions)) {
        $query = "SELECT answer, keywords FROM faqs
                  WHERE is_active = 1 AND (" . implode(' OR ', $keywordConditions) . ")
                  ORDER BY sort_order ASC, LENGTH(keywords) DESC
                  LIMIT 1";

        $stmt = mysqli_prepare($conn, $query);
        if ($types) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            return $row['answer'];
        }
    }

    // If no keyword match, try category-based matching
    $categories = [
        'admission' => ['admission', 'apply', 'enroll', 'application', 'requirements'],
        'academics' => ['program', 'course', 'degree', 'curriculum', 'study'],
        'contact' => ['contact', 'phone', 'email', 'reach', 'call'],
        'location' => ['location', 'address', 'where', 'directions', 'place'],
        'fees' => ['fee', 'tuition', 'cost', 'price', 'payment', 'scholarship'],
        'schedule' => ['schedule', 'time', 'when', 'class', 'calendar']
    ];

    foreach ($categories as $category => $categoryKeywords) {
        foreach ($categoryKeywords as $keyword) {
            if (strpos($lowerQuestion, $keyword) !== false) {
                $query = "SELECT answer FROM faqs
                          WHERE is_active = 1 AND category = ?
                          ORDER BY sort_order ASC
                          LIMIT 1";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 's', $category);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if ($row = mysqli_fetch_assoc($result)) {
                    return $row['answer'];
                }
                break;
            }
        }
    }

    return null;
}

/**
 * Get response based on keywords if no FAQ match found
 */
function getKeywordBasedResponse($question) {
    $lowerQuestion = strtolower($question);

    // Admission related questions
    if (strpos($lowerQuestion, 'admission') !== false || strpos($lowerQuestion, 'apply') !== false || strpos($lowerQuestion, 'enroll') !== false) {
        return "For admission inquiries, you can visit our Admission Process section on this page, or contact our admission office. We offer various programs including undergraduate and graduate degrees. You can also start your application through our pre-registration form.";
    }

    // Program related questions
    if (strpos($lowerQuestion, 'program') !== false || strpos($lowerQuestion, 'course') !== false || strpos($lowerQuestion, 'degree') !== false) {
        return "SEAIT offers various academic programs across different colleges. You can explore our Academic Programs section to see all available courses. Each program has detailed information about curriculum, requirements, and career opportunities.";
    }

    // Contact related questions
    if (strpos($lowerQuestion, 'contact') !== false || strpos($lowerQuestion, 'phone') !== false || strpos($lowerQuestion, 'email') !== false) {
        return "You can find our contact information in the Contact Us section. We have different departments with specific contact details. For general inquiries, you can reach us through the contact form or call our main office.";
    }

    // Location related questions
    if (strpos($lowerQuestion, 'location') !== false || strpos($lowerQuestion, 'address') !== false || strpos($lowerQuestion, 'where') !== false) {
        return "SEAIT is located in [City, Province]. You can find our exact address and directions in the Contact Us section. We also have virtual tours available for prospective students.";
    }

    // Fee related questions
    if (strpos($lowerQuestion, 'fee') !== false || strpos($lowerQuestion, 'tuition') !== false || strpos($lowerQuestion, 'cost') !== false || strpos($lowerQuestion, 'price') !== false) {
        return "Tuition fees vary by program and level. For detailed information about fees and payment options, please contact our finance office or check our admission guide. We also offer scholarships and financial aid programs.";
    }

    // Schedule related questions
    if (strpos($lowerQuestion, 'schedule') !== false || strpos($lowerQuestion, 'time') !== false || strpos($lowerQuestion, 'when') !== false) {
        return "Class schedules vary by program and semester. You can check our academic calendar for important dates. For specific class schedules, please contact your department or check the student portal.";
    }

    // General questions
    if (strpos($lowerQuestion, 'hello') !== false || strpos($lowerQuestion, 'hi') !== false || strpos($lowerQuestion, 'help') !== false) {
        return "Hello! I'm here to help you with any questions about SEAIT. You can ask me about our programs, admission process, contact information, or any other general inquiries.";
    }

    // Default response
    return "Thank you for your question! For specific inquiries, I recommend contacting our relevant department directly. You can find contact information in the Contact Us section, or visit our main office during business hours.";
}

/**
 * Log user inquiry to database
 */
function logInquiry($conn, $question, $response, $user_email, $user_name, $ip_address, $user_agent) {
    $query = "INSERT INTO user_inquiries (user_question, bot_response, user_email, user_name, ip_address, user_agent)
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ssssss', $question, $response, $user_email, $user_name, $ip_address, $user_agent);
    mysqli_stmt_execute($stmt);
}
?>