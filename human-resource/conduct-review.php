<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Conduct Regularization Review';

// Check if faculty ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage-regularization.php');
    exit();
}

// Decrypt the faculty ID
$faculty_id = safe_decrypt_id($_GET['id']);
if ($faculty_id <= 0) {
    header('Location: manage-regularization.php');
    exit();
}

// Get faculty regularization details
$query = "SELECT 
    fr.*,
    f.first_name,
    f.last_name,
    f.email,
    f.position,
    f.department,
            f.qrcode as employee_id,
    fd.date_of_hire,
    sc.name as category_name,
    sc.regularization_period_months,
    rs.name as status_name,
    rs.color as status_color,
    DATEDIFF(fr.regularization_review_date, CURDATE()) as days_until_review,
    DATEDIFF(CURDATE(), fr.regularization_review_date) as days_overdue
FROM faculty_regularization fr
LEFT JOIN faculty f ON fr.faculty_id = f.id
LEFT JOIN faculty_details fd ON f.id = fd.faculty_id
LEFT JOIN staff_categories sc ON fr.staff_category_id = sc.id
LEFT JOIN regularization_status rs ON fr.current_status_id = rs.id
WHERE fr.faculty_id = ? AND fr.is_active = 1";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $faculty_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$regularization = mysqli_fetch_assoc($result)) {
    header('Location: manage-regularization.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_type = sanitize_input($_POST['review_type']);
    $review_date = $_POST['review_date'];
    $status_before = (int)$_POST['status_before'];
    $status_after = (int)$_POST['status_after'];
    $performance_rating = isset($_POST['performance_rating']) ? (float)$_POST['performance_rating'] : null;
    $attendance_score = isset($_POST['attendance_score']) ? (float)$_POST['attendance_score'] : null;
    $work_quality_score = isset($_POST['work_quality_score']) ? (float)$_POST['work_quality_score'] : null;
    $teamwork_score = isset($_POST['teamwork_score']) ? (float)$_POST['teamwork_score'] : null;
    $overall_rating = isset($_POST['overall_rating']) ? (float)$_POST['overall_rating'] : null;
    $strengths = sanitize_input($_POST['strengths'] ?? '');
    $areas_for_improvement = sanitize_input($_POST['areas_for_improvement'] ?? '');
    $recommendations = sanitize_input($_POST['recommendations'] ?? '');
    $decision = sanitize_input($_POST['decision']);
    $next_review_date = $_POST['next_review_date'] ?? null;
    $notes = sanitize_input($_POST['notes'] ?? '');

    // Validate required fields
    if (empty($review_type) || empty($review_date) || empty($decision)) {
        $error = 'Review type, review date, and decision are required';
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert review record
            $insert_review_query = "INSERT INTO regularization_reviews (
                faculty_regularization_id, review_type, review_date, reviewer_id, status_before, status_after,
                performance_rating, attendance_score, work_quality_score, teamwork_score, overall_rating,
                strengths, areas_for_improvement, recommendations, decision, next_review_date, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $insert_review_stmt = mysqli_prepare($conn, $insert_review_query);
            mysqli_stmt_bind_param($insert_review_stmt, "issiiiddddsssssss", 
                $regularization['id'], $review_type, $review_date, $_SESSION['user_id'], $status_before, $status_after,
                $performance_rating, $attendance_score, $work_quality_score, $teamwork_score, $overall_rating,
                $strengths, $areas_for_improvement, $recommendations, $decision, $next_review_date, $notes
            );

            if (!mysqli_stmt_execute($insert_review_stmt)) {
                throw new Exception('Error inserting review: ' . mysqli_error($conn));
            }

            // Update regularization status
            $update_regularization_query = "UPDATE faculty_regularization SET 
                current_status_id = ?, 
                next_review_date = ?,
                reviewed_by = ?,
                reviewed_at = NOW(),
                updated_at = NOW()
                WHERE id = ?";

            $update_regularization_stmt = mysqli_prepare($conn, $update_regularization_query);
            mysqli_stmt_bind_param($update_regularization_stmt, "isii", 
                $status_after, $next_review_date, $_SESSION['user_id'], $regularization['id']
            );

            if (!mysqli_stmt_execute($update_regularization_stmt)) {
                throw new Exception('Error updating regularization status: ' . mysqli_error($conn));
            }

            // If decision is to regularize, set regularization date
            if ($decision === 'Recommend_Regularization') {
                $regularize_query = "UPDATE faculty_regularization SET 
                    regularization_date = CURDATE(),
                    updated_at = NOW()
                    WHERE id = ?";
                
                $regularize_stmt = mysqli_prepare($conn, $regularize_query);
                mysqli_stmt_bind_param($regularize_stmt, "i", $regularization['id']);
                
                if (!mysqli_stmt_execute($regularize_stmt)) {
                    throw new Exception('Error updating regularization date: ' . mysqli_error($conn));
                }
            }

            // Commit transaction
            mysqli_commit($conn);
            
            $success = 'Review conducted successfully';
            
            // Redirect to manage page after short delay
            header("Refresh: 2; URL=manage-regularization.php");
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error = 'Error conducting review: ' . $e->getMessage();
        }
    }
}

// Get status options
$statuses_query = "SELECT * FROM regularization_status WHERE is_active = 1 ORDER BY name";
$statuses_result = mysqli_query($conn, $statuses_query);

// Get previous reviews
$previous_reviews_query = "SELECT * FROM regularization_reviews 
                          WHERE faculty_regularization_id = ? 
                          ORDER BY review_date DESC, created_at DESC";
$previous_reviews_stmt = mysqli_prepare($conn, $previous_reviews_query);
mysqli_stmt_bind_param($previous_reviews_stmt, "i", $regularization['id']);
mysqli_stmt_execute($previous_reviews_stmt);
$previous_reviews_result = mysqli_stmt_get_result($previous_reviews_stmt);

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Conduct Regularization Review</h1>
            <p class="text-gray-600">Evaluate faculty member for regularization</p>
        </div>
        <a href="manage-regularization.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transform transition-all hover:scale-105 font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Regularization
        </a>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if (isset($success)): ?>
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Faculty Information Card -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex items-center mb-6">
        <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-xl mr-4">
            <?php echo strtoupper(substr($regularization['first_name'], 0, 1) . substr($regularization['last_name'], 0, 1)); ?>
        </div>
        <div class="flex-1">
            <h2 class="text-2xl font-bold text-gray-900">
                <?php echo htmlspecialchars($regularization['first_name'] . ' ' . $regularization['last_name']); ?>
            </h2>
            <p class="text-gray-600"><?php echo htmlspecialchars($regularization['position']); ?></p>
            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($regularization['department']); ?></p>
        </div>
        <div class="text-right">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" style="background-color: <?php echo $regularization['status_color']; ?>20; color: <?php echo $regularization['status_color']; ?>;">
                <?php echo htmlspecialchars($regularization['status_name']); ?>
            </span>
            <div class="text-sm text-gray-500 mt-1">
                <?php echo htmlspecialchars($regularization['category_name']); ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-semibold text-gray-900 mb-2">Probation Period</h4>
            <div class="text-sm text-gray-600">
                <div>Start: <?php echo date('M j, Y', strtotime($regularization['probation_start_date'])); ?></div>
                <div>End: <?php echo date('M j, Y', strtotime($regularization['probation_end_date'])); ?></div>
                <div class="font-medium mt-1"><?php echo $regularization['regularization_period_months']; ?> months</div>
            </div>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-semibold text-gray-900 mb-2">Review Due</h4>
            <div class="text-sm text-gray-600">
                <div><?php echo date('M j, Y', strtotime($regularization['regularization_review_date'])); ?></div>
                <?php if ($regularization['days_overdue'] > 0): ?>
                    <div class="text-red-600 font-medium mt-1">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <?php echo $regularization['days_overdue']; ?> days overdue
                    </div>
                <?php elseif ($regularization['days_until_review'] >= 0 && $regularization['days_until_review'] <= 7): ?>
                    <div class="text-yellow-600 font-medium mt-1">
                        <i class="fas fa-clock mr-1"></i>
                        Due in <?php echo $regularization['days_until_review']; ?> days
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-semibold text-gray-900 mb-2">Progress</h4>
            <?php
            $hire_date = new DateTime($regularization['probation_start_date']);
            $review_date = new DateTime($regularization['probation_end_date']);
            $today = new DateTime();
            $total_days = $hire_date->diff($review_date)->days;
            $elapsed_days = $hire_date->diff($today)->days;
            $progress_percentage = min(100, max(0, ($elapsed_days / $total_days) * 100));
            ?>
            <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: <?php echo $progress_percentage; ?>%"></div>
            </div>
            <div class="text-sm text-gray-600">
                <?php echo round($progress_percentage); ?>% complete
            </div>
        </div>
    </div>
</div>

<!-- Review Form -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="POST" class="space-y-6">
        <input type="hidden" name="status_before" value="<?php echo $regularization['current_status_id']; ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Review Type <span class="text-red-500">*</span></label>
                <select name="review_type" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm">
                    <option value="">Select Review Type</option>
                    <option value="Initial">Initial Review</option>
                    <option value="Follow-up">Follow-up Review</option>
                    <option value="Final">Final Review</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Review Date <span class="text-red-500">*</span></label>
                <input type="date" name="review_date" required value="<?php echo date('Y-m-d'); ?>"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">New Status <span class="text-red-500">*</span></label>
                <select name="status_after" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm">
                    <option value="">Select New Status</option>
                    <?php while ($status = mysqli_fetch_assoc($statuses_result)): ?>
                        <option value="<?php echo $status['id']; ?>">
                            <?php echo htmlspecialchars($status['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <!-- Performance Ratings -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <h4 class="text-lg font-semibold text-gray-900 mb-4">Performance Assessment</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Performance Rating</label>
                    <select name="performance_rating" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="">Select Rating</option>
                        <option value="5.00">5.00 - Outstanding</option>
                        <option value="4.50">4.50 - Very Satisfactory</option>
                        <option value="4.00">4.00 - Satisfactory</option>
                        <option value="3.50">3.50 - Fair</option>
                        <option value="3.00">3.00 - Needs Improvement</option>
                        <option value="2.50">2.50 - Poor</option>
                        <option value="2.00">2.00 - Very Poor</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Attendance Score</label>
                    <select name="attendance_score" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="">Select Score</option>
                        <option value="5.00">5.00 - Excellent</option>
                        <option value="4.50">4.50 - Very Good</option>
                        <option value="4.00">4.00 - Good</option>
                        <option value="3.50">3.50 - Fair</option>
                        <option value="3.00">3.00 - Needs Improvement</option>
                        <option value="2.50">2.50 - Poor</option>
                        <option value="2.00">2.00 - Very Poor</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Work Quality Score</label>
                    <select name="work_quality_score" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="">Select Score</option>
                        <option value="5.00">5.00 - Excellent</option>
                        <option value="4.50">4.50 - Very Good</option>
                        <option value="4.00">4.00 - Good</option>
                        <option value="3.50">3.50 - Fair</option>
                        <option value="3.00">3.00 - Needs Improvement</option>
                        <option value="2.50">2.50 - Poor</option>
                        <option value="2.00">2.00 - Very Poor</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Teamwork Score</label>
                    <select name="teamwork_score" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="">Select Score</option>
                        <option value="5.00">5.00 - Excellent</option>
                        <option value="4.50">4.50 - Very Good</option>
                        <option value="4.00">4.00 - Good</option>
                        <option value="3.50">3.50 - Fair</option>
                        <option value="3.00">3.00 - Needs Improvement</option>
                        <option value="2.50">2.50 - Poor</option>
                        <option value="2.00">2.00 - Very Poor</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Overall Rating</label>
                <select name="overall_rating" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                    <option value="">Select Overall Rating</option>
                    <option value="5.00">5.00 - Outstanding</option>
                    <option value="4.50">4.50 - Very Satisfactory</option>
                    <option value="4.00">4.00 - Satisfactory</option>
                    <option value="3.50">3.50 - Fair</option>
                    <option value="3.00">3.00 - Needs Improvement</option>
                    <option value="2.50">2.50 - Poor</option>
                    <option value="2.00">2.00 - Very Poor</option>
                </select>
            </div>
        </div>

        <!-- Assessment Details -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Strengths</label>
                <textarea name="strengths" rows="4"
                          class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                          placeholder="List employee strengths and positive contributions"></textarea>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Areas for Improvement</label>
                <textarea name="areas_for_improvement" rows="4"
                          class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                          placeholder="Identify areas that need improvement"></textarea>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Recommendations</label>
            <textarea name="recommendations" rows="3"
                      class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                      placeholder="Provide specific recommendations for improvement or next steps"></textarea>
        </div>

        <!-- Decision Section -->
        <div class="bg-blue-50 rounded-xl p-6 border border-blue-200">
            <h4 class="text-lg font-semibold text-blue-900 mb-4">Review Decision</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Decision <span class="text-red-500">*</span></label>
                    <select name="decision" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm">
                        <option value="">Select Decision</option>
                        <option value="Continue_Probation">Continue Probation</option>
                        <option value="Recommend_Regularization">Recommend Regularization</option>
                        <option value="Extend_Probation">Extend Probation</option>
                        <option value="Terminate">Terminate Employment</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Next Review Date</label>
                    <input type="date" name="next_review_date"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           min="<?php echo date('Y-m-d'); ?>">
                    <p class="text-sm text-gray-500 mt-1">Required if extending probation</p>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
            <textarea name="notes" rows="3"
                      class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                      placeholder="Any additional notes or comments"></textarea>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-4">
            <a href="manage-regularization.php" 
               class="px-8 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium border border-gray-300">
                <i class="fas fa-times mr-2"></i>Cancel
            </a>
            <button type="submit" 
                    class="px-8 py-3 bg-gradient-to-r from-seait-orange to-orange-500 text-white rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 font-medium shadow-lg">
                <i class="fas fa-clipboard-check mr-2"></i>Submit Review
            </button>
        </div>
    </form>
</div>

<!-- Previous Reviews -->
<?php if (mysqli_num_rows($previous_reviews_result) > 0): ?>
    <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Previous Reviews</h3>
        <div class="space-y-4">
            <?php while ($review = mysqli_fetch_assoc($previous_reviews_result)): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center space-x-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <?php echo htmlspecialchars($review['review_type']); ?>
                            </span>
                            <span class="text-sm text-gray-600">
                                <?php echo date('M j, Y', strtotime($review['review_date'])); ?>
                            </span>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            <?php 
                            switch($review['decision']) {
                                case 'Recommend_Regularization': echo 'bg-green-100 text-green-800'; break;
                                case 'Continue_Probation': echo 'bg-yellow-100 text-yellow-800'; break;
                                case 'Extend_Probation': echo 'bg-orange-100 text-orange-800'; break;
                                case 'Terminate': echo 'bg-red-100 text-red-800'; break;
                                default: echo 'bg-gray-100 text-gray-800';
                            }
                            ?>">
                            <?php echo str_replace('_', ' ', $review['decision']); ?>
                        </span>
                    </div>
                    
                    <?php if ($review['overall_rating']): ?>
                        <div class="text-sm text-gray-600 mb-2">
                            Overall Rating: <span class="font-medium"><?php echo $review['overall_rating']; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($review['recommendations']): ?>
                        <div class="text-sm text-gray-600">
                            <strong>Recommendations:</strong> <?php echo htmlspecialchars($review['recommendations']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
