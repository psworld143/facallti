<?php
// DEBUG: Show all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php');
    exit();
}

if (!function_exists('is_head_evaluation_active') || !is_head_evaluation_active()) {
    $_SESSION['message'] = 'There is no ongoing faculty evaluation period.';
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$evaluatee_id = isset($_GET['evaluatee_id']) ? (int)$_GET['evaluatee_id'] : 0;
if (!$evaluatee_id) {
    $_SESSION['message'] = 'Invalid faculty selected.';
    $_SESSION['message_type'] = 'error';
    header('Location: evaluate-faculty.php');
    exit();
}

// Get current active evaluation schedule for head_to_teacher
$sched_query = "SELECT * FROM evaluation_schedules WHERE evaluation_type = 'head_to_teacher' AND status = 'active' AND NOW() BETWEEN start_date AND end_date ORDER BY start_date DESC LIMIT 1";
$sched_result = mysqli_query($conn, $sched_query);
$schedule = mysqli_fetch_assoc($sched_result);
$current_semester_id = $schedule ? $schedule['semester_id'] : null;
if (!$current_semester_id) {
    $_SESSION['message'] = 'No active evaluation schedule found.';
    $_SESSION['message_type'] = 'error';
    header('Location: evaluate-faculty.php');
    exit();
}

// Get faculty info
$faculty_query = "SELECT * FROM faculty WHERE id = ? AND is_active = 1";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, "i", $evaluatee_id);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);
$faculty = mysqli_fetch_assoc($faculty_result);
if (!$faculty) {
    $_SESSION['message'] = 'Faculty not found.';
    $_SESSION['message_type'] = 'error';
    header('Location: evaluate-faculty.php');
    exit();
}

// Get main category for head_to_teacher
$main_cat_query = "SELECT * FROM main_evaluation_categories WHERE evaluation_type = 'head_to_teacher' AND status = 'active' LIMIT 1";
$main_cat_result = mysqli_query($conn, $main_cat_query);
$main_category = mysqli_fetch_assoc($main_cat_result);
if (!$main_category) {
    $_SESSION['message'] = 'No main evaluation category found for Head to Teacher.';
    $_SESSION['message_type'] = 'error';
    header('Location: evaluate-faculty.php');
    exit();
}
$main_category_id = $main_category['id'];

// Get sub-categories for this main category
$subcat_query = "SELECT * FROM evaluation_sub_categories WHERE main_category_id = ? AND status = 'active' ORDER BY order_number ASC, id ASC";
$subcat_stmt = mysqli_prepare($conn, $subcat_query);
mysqli_stmt_bind_param($subcat_stmt, "i", $main_category_id);
mysqli_stmt_execute($subcat_stmt);
$subcat_result = mysqli_stmt_get_result($subcat_stmt);
$subcategories = [];
while ($row = mysqli_fetch_assoc($subcat_result)) {
    $subcategories[] = $row;
}

// Get questions for each subcategory
$questions_by_subcat = [];
$total_questions = 0;
foreach ($subcategories as $subcat) {
    $q_query = "SELECT * FROM evaluation_questionnaires WHERE sub_category_id = ? AND status = 'active' ORDER BY order_number ASC, id ASC";
    $q_stmt = mysqli_prepare($conn, $q_query);
    mysqli_stmt_bind_param($q_stmt, "i", $subcat['id']);
    mysqli_stmt_execute($q_stmt);
    $q_result = mysqli_stmt_get_result($q_stmt);
    $questions = [];
    while ($q = mysqli_fetch_assoc($q_result)) {
        $questions[] = $q;
        $total_questions++;
    }
    $questions_by_subcat[$subcat['id']] = $questions;
}

// Check if an evaluation session already exists for this head/faculty/semester
$session_id = null;
$session_query = "SELECT id FROM evaluation_sessions WHERE evaluator_id = ? AND evaluator_type = 'head' AND evaluatee_id = ? AND evaluatee_type = 'teacher' AND semester_id = ? AND status IN ('in_progress', 'draft', 'active', 'completed') LIMIT 1";
$session_stmt = mysqli_prepare($conn, $session_query);
mysqli_stmt_bind_param($session_stmt, "iii", $user_id, $evaluatee_id, $current_semester_id);
mysqli_stmt_execute($session_stmt);
$session_result = mysqli_stmt_get_result($session_stmt);
if ($row = mysqli_fetch_assoc($session_result)) {
    $session_id = $row['id'];
}

// Handle form submission
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_evaluation') {
    if ($session_id) {
        $update_query = "UPDATE evaluation_sessions SET status = 'completed', updated_at = NOW() WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "i", $session_id);
        mysqli_stmt_execute($update_stmt);
    } else {
        $insert_query = "INSERT INTO evaluation_sessions (evaluator_id, evaluator_type, evaluatee_id, evaluatee_type, semester_id, main_category_id, status, created_at, updated_at) VALUES (?, 'head', ?, 'teacher', ?, ?, 'completed', NOW(), NOW())";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "iiii", $user_id, $evaluatee_id, $current_semester_id, $main_category_id);
        mysqli_stmt_execute($insert_stmt);
        $session_id = mysqli_insert_id($conn);
    }
    foreach ($subcategories as $subcat) {
        foreach ($questions_by_subcat[$subcat['id']] as $q) {
            $qid = $q['id'];
            $rating = isset($_POST['responses'][$qid]['rating']) ? (int)$_POST['responses'][$qid]['rating'] : null;
            $text = isset($_POST['responses'][$qid]['text']) ? trim($_POST['responses'][$qid]['text']) : '';
            $yes_no = isset($_POST['responses'][$qid]['yes_no']) ? $_POST['responses'][$qid]['yes_no'] : null;
            if ($rating !== null || $text !== '' || $yes_no !== null) {
                $resp_query = "SELECT id FROM evaluation_responses WHERE evaluation_session_id = ? AND questionnaire_id = ?";
                $resp_stmt = mysqli_prepare($conn, $resp_query);
                mysqli_stmt_bind_param($resp_stmt, "ii", $session_id, $qid);
                mysqli_stmt_execute($resp_stmt);
                $resp_result = mysqli_stmt_get_result($resp_stmt);
                if ($resp_row = mysqli_fetch_assoc($resp_result)) {
                    $update_resp = "UPDATE evaluation_responses SET rating_value = ?, text_response = ?, yes_no_response = ?, updated_at = NOW() WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_resp);
                    mysqli_stmt_bind_param($update_stmt, "issi", $rating, $text, $yes_no, $resp_row['id']);
                    mysqli_stmt_execute($update_stmt);
                } else {
                    $insert_resp = "INSERT INTO evaluation_responses (evaluation_session_id, questionnaire_id, rating_value, text_response, yes_no_response, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                    $insert_stmt = mysqli_prepare($conn, $insert_resp);
                    mysqli_stmt_bind_param($insert_stmt, "iiiss", $session_id, $qid, $rating, $text, $yes_no);
                    mysqli_stmt_execute($insert_stmt);
                }
            }
        }
    }
    $_SESSION['message'] = 'Evaluation submitted successfully.';
    $_SESSION['message_type'] = 'success';
    header('Location: evaluate-faculty.php');
    exit();
}

// Calculate per-category progress and total answered (all zero for new evaluation)
$category_progress = [];
$total_answered = 0;
foreach ($subcategories as $subcat) {
    $catName = $subcat['name'];
    $qCount = count($questions_by_subcat[$subcat['id']]);
    $category_progress[$catName] = [
        'total' => $qCount,
        'answered' => 0,
        'percentage' => 0
    ];
}
?>
<script>
var total_questions = <?php echo (int)$total_questions; ?>;
var total_answered = <?php echo (int)$total_answered; ?>;
var category_progress = <?php echo json_encode($category_progress); ?>;
</script>
<script>
// Use PHP-generated variables for progress tracking
let progressData = {
    total: total_questions,
    answered: total_answered
};
let categoryProgress = category_progress;

function updateProgress() {
    let answered = 0;
    Object.keys(categoryProgress).forEach(cat => { categoryProgress[cat].answered = 0; });
    document.querySelectorAll('.category-content').forEach(content => {
        const cat = content.getAttribute('data-category');
        if (cat && categoryProgress[cat]) {
            let catAnswered = 0;
            content.querySelectorAll('input[type="radio"]:checked, textarea').forEach(input => {
                if (input.type === 'radio' || (input.tagName === 'TEXTAREA' && input.value.trim() !== '')) {
                    catAnswered++;
                    answered++;
                }
            });
            categoryProgress[cat].answered = catAnswered;
            categoryProgress[cat].percentage = categoryProgress[cat].total > 0 ? Math.round((catAnswered / categoryProgress[cat].total) * 100) : 0;
        }
    });
    progressData.answered = answered;
    // Update progress bars and text
    // Mobile
    const mobileProgressBar = document.querySelector('.block.sm\\:hidden .bg-white.h-2');
    if (mobileProgressBar) {
        mobileProgressBar.style.width = (progressData.total > 0 ? Math.round((answered / progressData.total) * 100) : 0) + '%';
    }
    const mobileProgressText = document.querySelector('.block.sm\\:hidden .text-lg.font-bold');
    if (mobileProgressText) {
        mobileProgressText.textContent = (progressData.total > 0 ? Math.round((answered / progressData.total) * 100) : 0) + '% Complete';
    }
    const mobileProgressCount = document.querySelector('.block.sm\\:hidden .text-sm.font-medium');
    if (mobileProgressCount) {
        mobileProgressCount.textContent = answered + ' of ' + progressData.total + ' questions answered';
    }
    // Desktop
    const desktopProgressBar = document.querySelector('.hidden.sm\\:block .bg-white.h-2');
    if (desktopProgressBar) {
        desktopProgressBar.style.width = (progressData.total > 0 ? Math.round((answered / progressData.total) * 100) : 0) + '%';
    }
    const desktopProgressText = document.querySelector('.hidden.sm\\:block .text-lg.font-bold');
    if (desktopProgressText) {
        desktopProgressText.textContent = (progressData.total > 0 ? Math.round((answered / progressData.total) * 100) : 0) + '% Complete';
    }
    const desktopProgressCount = document.querySelector('.hidden.sm\\:block .text-sm.font-medium');
    if (desktopProgressCount) {
        desktopProgressCount.textContent = answered + ' of ' + progressData.total + ' questions answered';
    }
    // Update category tabs (if needed)
}
function switchTab(tabId, callback) {
    document.querySelectorAll('.category-content').forEach(function(content) {
        content.classList.add('hidden');
        content.classList.remove('active');
    });
    document.getElementById(tabId + '-content').classList.remove('hidden');
    document.getElementById(tabId + '-content').classList.add('active');
    if (typeof callback === 'function') callback();
}
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[type="radio"]').forEach(function(input) {
        input.addEventListener('change', function() {
            // Highlight selected rating star, yes-no, or multiple-choice
            document.querySelectorAll('.rating-star, .yes-no-option, .multiple-choice-option').forEach(el => el.classList.remove('selected', 'selected-yes', 'selected-no'));
            document.querySelectorAll('input[type="radio"]:checked').forEach(function(checked) {
                let label = checked.closest('label');
                if (label) {
                    if (label.classList.contains('rating-star')) label.classList.add('selected');
                    if (label.classList.contains('yes-no-option')) label.classList.add(checked.value === 'yes' ? 'selected-yes' : 'selected-no');
                    if (label.classList.contains('multiple-choice-option')) label.classList.add('selected');
                }
            });
            updateProgress();
        });
    });
    updateProgress();
});
</script> 