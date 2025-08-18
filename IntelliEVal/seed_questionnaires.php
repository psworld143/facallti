<?php
/**
 * IntelliEVal System - Questionnaire Seeding Script
 * This script automatically seeds sample questionnaires for all sub-categories
 */

session_start();
require_once '../config/database.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'seed_questionnaires') {

        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            $questions_created = 0;
            $errors = [];

            // Standard rating labels for 1-5 scale
            $rating_labels = '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]';

            // Sample questionnaires data
            $questionnaires_data = [
                // STUDENT TO TEACHER EVALUATION
                [
                    'sub_category_name' => 'Classroom Management',
                    'questions' => [
                        ['How well does the teacher maintain classroom discipline?', 'rating_1_5', $rating_labels, 1, 1],
                        ['Does the teacher start and end classes on time?', 'rating_1_5', $rating_labels, 1, 2],
                        ['How organized is the teacher\'s classroom setup?', 'rating_1_5', $rating_labels, 1, 3],
                        ['Does the teacher handle disruptive behavior effectively?', 'rating_1_5', $rating_labels, 1, 4],
                        ['How well does the teacher manage classroom activities and transitions?', 'rating_1_5', $rating_labels, 1, 5],
                        ['Does the teacher create a safe and respectful learning environment?', 'yes_no', NULL, 1, 6],
                        ['What suggestions do you have for improving classroom management?', 'text', NULL, 0, 7]
                    ]
                ],
                [
                    'sub_category_name' => 'Teaching Skills',
                    'questions' => [
                        ['How clear and understandable are the teacher\'s explanations?', 'rating_1_5', $rating_labels, 1, 1],
                        ['Does the teacher use effective teaching methods and strategies?', 'rating_1_5', $rating_labels, 1, 2],
                        ['How well does the teacher adapt teaching to different learning styles?', 'rating_1_5', $rating_labels, 1, 3],
                        ['Does the teacher provide clear learning objectives for each lesson?', 'rating_1_5', $rating_labels, 1, 4],
                        ['How effective are the teacher\'s examples and demonstrations?', 'rating_1_5', $rating_labels, 1, 5],
                        ['Does the teacher use technology and resources effectively?', 'rating_1_5', $rating_labels, 1, 6],
                        ['How well does the teacher assess student understanding during lessons?', 'rating_1_5', $rating_labels, 1, 7],
                        ['What teaching methods do you find most effective?', 'text', NULL, 0, 8]
                    ]
                ],
                [
                    'sub_category_name' => 'Subject Knowledge',
                    'questions' => [
                        ['How well does the teacher demonstrate mastery of the subject matter?', 'rating_1_5', $rating_labels, 1, 1],
                        ['Does the teacher provide accurate and up-to-date information?', 'rating_1_5', $rating_labels, 1, 2],
                        ['How well does the teacher connect concepts to real-world applications?', 'rating_1_5', $rating_labels, 1, 3],
                        ['Does the teacher answer student questions accurately and thoroughly?', 'rating_1_5', $rating_labels, 1, 4],
                        ['How well does the teacher explain complex topics in simple terms?', 'rating_1_5', $rating_labels, 1, 5],
                        ['Does the teacher stay current with developments in their field?', 'yes_no', NULL, 1, 6],
                        ['What topics would you like the teacher to explain better?', 'text', NULL, 0, 7]
                    ]
                ],
                [
                    'sub_category_name' => 'Communication Skills',
                    'questions' => [
                        ['How clear and audible is the teacher\'s voice?', 'rating_1_5', $rating_labels, 1, 1],
                        ['Does the teacher speak at an appropriate pace for students to follow?', 'rating_1_5', $rating_labels, 1, 2],
                        ['How well does the teacher use body language and gestures?', 'rating_1_5', $rating_labels, 1, 3],
                        ['Does the teacher listen actively to student questions and concerns?', 'rating_1_5', $rating_labels, 1, 4],
                        ['How well does the teacher provide constructive feedback?', 'rating_1_5', $rating_labels, 1, 5],
                        ['Does the teacher encourage open communication in the classroom?', 'yes_no', NULL, 1, 6],
                        ['How approachable is the teacher for questions outside of class?', 'rating_1_5', $rating_labels, 1, 7],
                        ['What communication improvements would you suggest?', 'text', NULL, 0, 8]
                    ]
                ],
                [
                    'sub_category_name' => 'Student Engagement',
                    'questions' => [
                        ['How well does the teacher motivate students to participate?', 'rating_1_5', $rating_labels, 1, 1],
                        ['Does the teacher create interesting and engaging lessons?', 'rating_1_5', $rating_labels, 1, 2],
                        ['How well does the teacher encourage critical thinking and discussion?', 'rating_1_5', $rating_labels, 1, 3],
                        ['Does the teacher use interactive activities and group work effectively?', 'rating_1_5', $rating_labels, 1, 4],
                        ['How well does the teacher recognize and respond to student interests?', 'rating_1_5', $rating_labels, 1, 5],
                        ['Does the teacher provide opportunities for hands-on learning?', 'yes_no', NULL, 1, 6],
                        ['How well does the teacher maintain student attention throughout the lesson?', 'rating_1_5', $rating_labels, 1, 7],
                        ['What activities do you find most engaging in this class?', 'text', NULL, 0, 8]
                    ]
                ],

                // PEER TO PEER EVALUATION
                [
                    'sub_category_name' => 'Professional Competence',
                    'questions' => [
                        ['How well does the colleague demonstrate expertise in their subject area?', 'rating_1_5', $rating_labels, 1, 1],
                        ['Does the colleague stay updated with current educational practices?', 'rating_1_5', $rating_labels, 1, 2],
                        ['How well does the colleague plan and organize their lessons?', 'rating_1_5', $rating_labels, 1, 3],
                        ['Does the colleague demonstrate strong pedagogical skills?', 'rating_1_5', $rating_labels, 1, 4],
                        ['How well does the colleague assess and evaluate student learning?', 'rating_1_5', $rating_labels, 1, 5],
                        ['Does the colleague maintain professional standards in their work?', 'yes_no', NULL, 1, 6],
                        ['How well does the colleague handle classroom challenges?', 'rating_1_5', $rating_labels, 1, 7],
                        ['What areas of professional development would you recommend?', 'text', NULL, 0, 8]
                    ]
                ],
                [
                    'sub_category_name' => 'Collaboration',
                    'questions' => [
                        ['How well does the colleague work with other teachers?', 'rating_1_5', $rating_labels, 1, 1],
                        ['Does the colleague share resources and ideas with the team?', 'rating_1_5', $rating_labels, 1, 2],
                        ['How well does the colleague participate in team meetings and discussions?', 'rating_1_5', $rating_labels, 1, 3],
                        ['Does the colleague support and help other teachers when needed?', 'rating_1_5', $rating_labels, 1, 4],
                        ['How well does the colleague contribute to school-wide initiatives?', 'rating_1_5', $rating_labels, 1, 5],
                        ['Does the colleague respect different viewpoints and approaches?', 'yes_no', NULL, 1, 6],
                        ['How well does the colleague communicate with other staff members?', 'rating_1_5', $rating_labels, 1, 7],
                        ['What suggestions do you have for improving collaboration?', 'text', NULL, 0, 8]
                    ]
                ],
                [
                    'sub_category_name' => 'Innovation',
                    'questions' => [
                        ['How creative and innovative is the colleague in their teaching methods?', 'rating_1_5', $rating_labels, 1, 1],
                        ['Does the colleague try new approaches and technologies?', 'rating_1_5', $rating_labels, 1, 2],
                        ['How well does the colleague adapt to changing educational needs?', 'rating_1_5', $rating_labels, 1, 3],
                        ['Does the colleague suggest improvements to existing programs?', 'rating_1_5', $rating_labels, 1, 4],
                        ['How well does the colleague integrate new ideas into their teaching?', 'rating_1_5', $rating_labels, 1, 5],
                        ['Does the colleague experiment with different assessment methods?', 'yes_no', NULL, 1, 6],
                        ['How well does the colleague inspire creativity in students?', 'rating_1_5', $rating_labels, 1, 7],
                        ['What innovative practices have you observed from this colleague?', 'text', NULL, 0, 8]
                    ]
                ],
                [
                    'sub_category_name' => 'Mentoring',
                    'questions' => [
                        ['How well does the colleague mentor new or less experienced teachers?', 'rating_1_5', $rating_labels, 1, 1],
                        ['Does the colleague provide constructive feedback to other teachers?', 'rating_1_5', $rating_labels, 1, 2],
                        ['How well does the colleague share their expertise and knowledge?', 'rating_1_5', $rating_labels, 1, 3],
                        ['Does the colleague serve as a positive role model for other teachers?', 'rating_1_5', $rating_labels, 1, 4],
                        ['How well does the colleague support professional development of others?', 'rating_1_5', $rating_labels, 1, 5],
                        ['Does the colleague create opportunities for peer learning?', 'yes_no', NULL, 1, 6],
                        ['How well does the colleague guide others in improving their teaching?', 'rating_1_5', $rating_labels, 1, 7],
                        ['What mentoring strengths does this colleague demonstrate?', 'text', NULL, 0, 8]
                    ]
                ],

                // HEAD TO TEACHER EVALUATION
                [
                    'sub_category_name' => 'Leadership',
                    'questions' => [
                        ['How well does the teacher demonstrate leadership qualities?', 'rating_1_5', $rating_labels, 1, 1],
                        ['Does the teacher take initiative in school improvement projects?', 'rating_1_5', $rating_labels, 1, 2],
                        ['How well does the teacher inspire and motivate other staff members?', 'rating_1_5', $rating_labels, 1, 3],
                        ['Does the teacher demonstrate vision and strategic thinking?', 'rating_1_5', $rating_labels, 1, 4],
                        ['How well does the teacher handle conflicts and difficult situations?', 'rating_1_5', $rating_labels, 1, 5],
                        ['Does the teacher lead by example in professional conduct?', 'yes_no', NULL, 1, 6],
                        ['How well does the teacher represent the school in external activities?', 'rating_1_5', $rating_labels, 1, 7],
                        ['What leadership opportunities would you recommend for this teacher?', 'text', NULL, 0, 8]
                    ]
                ],
                [
                    'sub_category_name' => 'Administrative Skills',
                    'questions' => [
                        ['How well does the teacher manage administrative tasks and paperwork?', 'rating_1_5', $rating_labels, 1, 1],
                        ['Does the teacher submit reports and documents on time?', 'rating_1_5', $rating_labels, 1, 2],
                        ['How well does the teacher organize and maintain records?', 'rating_1_5', $rating_labels, 1, 3],
                        ['Does the teacher follow administrative procedures correctly?', 'rating_1_5', $rating_labels, 1, 4],
                        ['How well does the teacher manage time and prioritize tasks?', 'rating_1_5', $rating_labels, 1, 5],
                        ['Does the teacher coordinate effectively with other departments?', 'yes_no', NULL, 1, 6],
                        ['How well does the teacher handle budget and resource management?', 'rating_1_5', $rating_labels, 1, 7],
                        ['What administrative improvements would you suggest?', 'text', NULL, 0, 8]
                    ]
                ],
                [
                    'sub_category_name' => 'Professional Development',
                    'questions' => [
                        ['How committed is the teacher to continuous professional learning?', 'rating_1_5', $rating_labels, 1, 1],
                        ['Does the teacher actively participate in training and workshops?', 'rating_1_5', $rating_labels, 1, 2],
                        ['How well does the teacher apply new learning to their practice?', 'rating_1_5', $rating_labels, 1, 3],
                        ['Does the teacher seek feedback and reflect on their teaching?', 'rating_1_5', $rating_labels, 1, 4],
                        ['How well does the teacher stay current with educational trends?', 'rating_1_5', $rating_labels, 1, 5],
                        ['Does the teacher pursue advanced degrees or certifications?', 'yes_no', NULL, 1, 6],
                        ['How well does the teacher share new knowledge with colleagues?', 'rating_1_5', $rating_labels, 1, 7],
                        ['What professional development goals would you recommend?', 'text', NULL, 0, 8]
                    ]
                ],
                [
                    'sub_category_name' => 'Compliance',
                    'questions' => [
                        ['How well does the teacher follow school policies and procedures?', 'rating_1_5', $rating_labels, 1, 1],
                        ['Does the teacher comply with curriculum standards and requirements?', 'rating_1_5', $rating_labels, 1, 2],
                        ['How well does the teacher adhere to safety and security protocols?', 'rating_1_5', $rating_labels, 1, 3],
                        ['Does the teacher follow ethical guidelines and professional standards?', 'rating_1_5', $rating_labels, 1, 4],
                        ['How well does the teacher maintain confidentiality when required?', 'rating_1_5', $rating_labels, 1, 5],
                        ['Does the teacher attend required meetings and events?', 'yes_no', NULL, 1, 6],
                        ['How well does the teacher follow assessment and grading policies?', 'rating_1_5', $rating_labels, 1, 7],
                        ['What compliance issues, if any, need to be addressed?', 'text', NULL, 0, 8]
                    ]
                ]
            ];

            // Process each sub-category
            foreach ($questionnaires_data as $sub_category_data) {
                // Get sub-category ID
                $sub_category_query = "SELECT id FROM evaluation_sub_categories WHERE name = ? AND status = 'active'";
                $sub_category_stmt = mysqli_prepare($conn, $sub_category_query);
                mysqli_stmt_bind_param($sub_category_stmt, "s", $sub_category_data['sub_category_name']);
                mysqli_stmt_execute($sub_category_stmt);
                $sub_category_result = mysqli_stmt_get_result($sub_category_stmt);
                $sub_category = mysqli_fetch_assoc($sub_category_result);

                if ($sub_category) {
                    $sub_category_id = $sub_category['id'];

                    // Check if questionnaires already exist for this sub-category
                    $check_query = "SELECT COUNT(*) as count FROM evaluation_questionnaires WHERE sub_category_id = ?";
                    $check_stmt = mysqli_prepare($conn, $check_query);
                    mysqli_stmt_bind_param($check_stmt, "i", $sub_category_id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $check_row = mysqli_fetch_assoc($check_result);

                    if ($check_row['count'] == 0) {
                        // Insert questions for this sub-category
                        foreach ($sub_category_data['questions'] as $question_data) {
                            $insert_query = "INSERT INTO evaluation_questionnaires
                                            (sub_category_id, question_text, question_type, rating_labels, required, order_number, created_by)
                                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $insert_stmt = mysqli_prepare($conn, $insert_query);
                            mysqli_stmt_bind_param($insert_stmt, "isssiii",
                                $sub_category_id, $question_data[0], $question_data[1], $question_data[2],
                                $question_data[3], $question_data[4], $_SESSION['user_id']);

                            if (mysqli_stmt_execute($insert_stmt)) {
                                $questions_created++;
                            } else {
                                $errors[] = "Error creating question: " . mysqli_error($conn);
                            }
                        }
                    } else {
                        $errors[] = "Questionnaires already exist for sub-category: " . $sub_category_data['sub_category_name'];
                    }
                } else {
                    $errors[] = "Sub-category not found: " . $sub_category_data['sub_category_name'];
                }
            }

            if ($questions_created > 0) {
                mysqli_commit($conn);
                $message = "Successfully created " . $questions_created . " sample questionnaires across all sub-categories!";
                if (!empty($errors)) {
                    $message .= " Some issues occurred: " . implode(", ", $errors);
                }
                $message_type = "success";
            } else {
                mysqli_rollback($conn);
                $message = "No new questionnaires were created. " . implode(", ", $errors);
                $message_type = "warning";
            }

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Error seeding questionnaires: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get current questionnaire statistics
$stats_query = "SELECT
    esc.name as sub_category_name,
    COUNT(eq.id) as question_count
FROM evaluation_sub_categories esc
LEFT JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id AND eq.status = 'active'
WHERE esc.status = 'active'
GROUP BY esc.id, esc.name
ORDER BY esc.main_category_id, esc.order_number";

$stats_result = mysqli_query($conn, $stats_query);
$questionnaire_stats = [];
while ($row = mysqli_fetch_assoc($stats_result)) {
    $questionnaire_stats[] = $row;
}

// Set page title
$page_title = 'Seed Sample Questionnaires';

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Seed Sample Questionnaires</h1>
            <p class="text-sm sm:text-base text-gray-600">
                Automatically create sample questionnaires for all sub-categories
            </p>
        </div>
        <a href="evaluations.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Evaluations
        </a>
    </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : ($message_type === 'warning' ? 'bg-yellow-50 border border-yellow-200 text-yellow-800' : 'bg-red-50 border border-red-200 text-red-800'); ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Current Questionnaire Statistics -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Current Questionnaire Statistics</h2>
    </div>
    <div class="p-6">
        <?php if (empty($questionnaire_stats)): ?>
            <p class="text-gray-500 text-center py-4">No sub-categories found.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($questionnaire_stats as $stat): ?>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-medium text-gray-900"><?php echo htmlspecialchars($stat['sub_category_name']); ?></h3>
                                <p class="text-sm text-gray-600">Questions: <?php echo $stat['question_count']; ?></p>
                            </div>
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-question text-blue-600 text-sm"></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Seed Questionnaires Form -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Seed Sample Questionnaires</h2>
    </div>
    <div class="p-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-medium text-blue-900 mb-2">What This Will Do</h3>
            <p class="text-blue-700 text-sm mb-3">
                This will automatically create comprehensive sample questionnaires for all sub-categories in the evaluation system.
            </p>
            <ul class="text-blue-700 text-sm space-y-1">
                <li>• Creates 100+ sample questions across all 13 sub-categories</li>
                <li>• Uses standardized 1-5 rating scale with proper labels</li>
                <li>• Includes rating, yes/no, and text response questions</li>
                <li>• Only creates questionnaires for sub-categories that don't have any yet</li>
                <li>• Questions are tailored to each evaluation type (Student to Teacher, Peer to Peer, Head to Teacher)</li>
            </ul>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="action" value="seed_questionnaires">

            <div class="flex justify-end">
                <button type="submit"
                        class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition"
                        onclick="return confirm('Are you sure you want to seed sample questionnaires? This will create questions for all sub-categories that don\'t have questionnaires yet.')">
                    <i class="fas fa-seedling mr-2"></i>Seed Sample Questionnaires
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>