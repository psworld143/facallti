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
$page_title = 'Add Regularization Record';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $faculty_id = (int)$_POST['faculty_id'];
    $staff_category_id = (int)$_POST['staff_category_id'];
    $date_of_hire = $_POST['date_of_hire'];
    $probation_start_date = $_POST['probation_start_date'];
    $notes = sanitize_input($_POST['notes'] ?? '');

    // Validate required fields
    if (empty($faculty_id) || empty($staff_category_id) || empty($date_of_hire) || empty($probation_start_date)) {
        $error = 'All required fields must be filled';
    } else {
        // Check if faculty already has a regularization record
        $check_query = "SELECT id FROM faculty_regularization WHERE faculty_id = ? AND is_active = 1";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "i", $faculty_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $error = 'This faculty member already has an active regularization record';
        } else {
            // Get staff category details to calculate review date
            $category_query = "SELECT regularization_period_months FROM staff_categories WHERE id = ?";
            $category_stmt = mysqli_prepare($conn, $category_query);
            mysqli_stmt_bind_param($category_stmt, "i", $staff_category_id);
            mysqli_stmt_execute($category_stmt);
            $category_result = mysqli_stmt_get_result($category_stmt);
            $category_data = mysqli_fetch_assoc($category_result);

            if (!$category_data) {
                $error = 'Invalid staff category selected';
            } else {
                // Calculate probation end date and review date
                $probation_end_date = date('Y-m-d', strtotime($probation_start_date . ' + ' . $category_data['regularization_period_months'] . ' months'));
                $regularization_review_date = date('Y-m-d', strtotime($probation_end_date . ' - 30 days')); // Review 30 days before end

                // Get probationary status ID
                $status_query = "SELECT id FROM regularization_status WHERE name = 'Probationary'";
                $status_result = mysqli_query($conn, $status_query);
                $status_data = mysqli_fetch_assoc($status_result);

                if (!$status_data) {
                    $error = 'Probationary status not found in system';
                } else {
                    // Insert regularization record
                    $insert_query = "INSERT INTO faculty_regularization (
                        faculty_id, staff_category_id, current_status_id, date_of_hire, 
                        probation_start_date, probation_end_date, regularization_review_date, 
                        review_notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "iiisssss", 
                        $faculty_id, $staff_category_id, $status_data['id'], $date_of_hire,
                        $probation_start_date, $probation_end_date, $regularization_review_date, $notes
                    );

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $success = 'Regularization record added successfully';
                        
                        // Redirect to manage page after short delay
                        header("Refresh: 2; URL=manage-regularization.php");
                    } else {
                        $error = 'Error adding regularization record: ' . mysqli_error($conn);
                    }
                }
            }
        }
    }
}

// Get faculty members who don't have regularization records
$faculty_query = "SELECT f.id, f.first_name, f.last_name, f.email, f.position, f.department, fd.employee_id, fd.date_of_hire
                  FROM faculty f
                  LEFT JOIN faculty_details fd ON f.id = fd.faculty_id
                  WHERE f.is_active = 1 
                  AND f.id NOT IN (SELECT faculty_id FROM faculty_regularization WHERE is_active = 1)
                  ORDER BY f.last_name, f.first_name";
$faculty_result = mysqli_query($conn, $faculty_query);

// Get staff categories
$categories_query = "SELECT * FROM staff_categories WHERE is_active = 1 ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Add Regularization Record</h1>
            <p class="text-gray-600">Set up regularization tracking for faculty members</p>
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

<!-- Add Regularization Form -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="POST" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Faculty Member <span class="text-red-500">*</span></label>
                <select name="faculty_id" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm">
                    <option value="">Select Faculty Member</option>
                    <?php while ($faculty = mysqli_fetch_assoc($faculty_result)): ?>
                        <option value="<?php echo $faculty['id']; ?>">
                            <?php echo htmlspecialchars($faculty['last_name'] . ', ' . $faculty['first_name']); ?> 
                            (<?php echo htmlspecialchars($faculty['position']); ?> - <?php echo htmlspecialchars($faculty['department']); ?>)
                            <?php if ($faculty['employee_id']): ?>
                                - <?php echo htmlspecialchars($faculty['employee_id']); ?>
                            <?php endif; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <p class="text-sm text-gray-500 mt-1">Only faculty members without existing regularization records are shown</p>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Staff Category <span class="text-red-500">*</span></label>
                <select name="staff_category_id" id="staff_category_id" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm">
                    <option value="">Select Staff Category</option>
                    <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                        <option value="<?php echo $category['id']; ?>" data-months="<?php echo $category['regularization_period_months']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?> 
                            (<?php echo $category['regularization_period_months']; ?> months)
                        </option>
                    <?php endwhile; ?>
                </select>
                <p class="text-sm text-gray-500 mt-1">Teaching: 36 months, Non-Teaching: 6 months</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Date of Hire <span class="text-red-500">*</span></label>
                <input type="date" name="date_of_hire" id="date_of_hire" required
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                       max="<?php echo date('Y-m-d'); ?>">
                <p class="text-sm text-gray-500 mt-1">Original employment start date</p>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Probation Start Date <span class="text-red-500">*</span></label>
                <input type="date" name="probation_start_date" id="probation_start_date" required
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                       max="<?php echo date('Y-m-d'); ?>">
                <p class="text-sm text-gray-500 mt-1">Start of probation period</p>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
            <textarea name="notes" rows="3"
                      class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                      placeholder="Additional notes about the regularization setup"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
        </div>

        <!-- Calculated Dates Preview -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <h4 class="text-lg font-semibold text-gray-900 mb-4">Calculated Dates Preview</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Probation End Date</label>
                    <div id="probation_end_preview" class="text-lg font-semibold text-gray-900">-</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Review Due Date</label>
                    <div id="review_due_preview" class="text-lg font-semibold text-gray-900">-</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Total Probation Period</label>
                    <div id="probation_period_preview" class="text-lg font-semibold text-gray-900">-</div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-4">
            <a href="manage-regularization.php" 
               class="px-8 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium border border-gray-300">
                <i class="fas fa-times mr-2"></i>Cancel
            </a>
            <button type="submit" 
                    class="px-8 py-3 bg-gradient-to-r from-seait-orange to-orange-500 text-white rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 font-medium shadow-lg">
                <i class="fas fa-save mr-2"></i>Add Regularization Record
            </button>
        </div>
    </form>
</div>

<!-- Information Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
    <div class="bg-blue-50 rounded-xl p-6 border border-blue-200">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-info-circle text-blue-600 text-lg"></i>
            </div>
            <h4 class="text-lg font-semibold text-blue-900">Teaching Staff</h4>
        </div>
        <ul class="text-sm text-blue-800 space-y-2">
            <li><i class="fas fa-clock mr-2"></i>36-month probation period</li>
            <li><i class="fas fa-calendar-check mr-2"></i>Review due 30 days before probation ends</li>
            <li><i class="fas fa-user-graduate mr-2"></i>Includes professors, instructors, academic personnel</li>
        </ul>
    </div>

    <div class="bg-green-50 rounded-xl p-6 border border-green-200">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-info-circle text-green-600 text-lg"></i>
            </div>
            <h4 class="text-lg font-semibold text-green-900">Non-Teaching Staff</h4>
        </div>
        <ul class="text-sm text-green-800 space-y-2">
            <li><i class="fas fa-clock mr-2"></i>6-month probation period</li>
            <li><i class="fas fa-calendar-check mr-2"></i>Review due 30 days before probation ends</li>
            <li><i class="fas fa-users-cog mr-2"></i>Includes administrative, support, technical personnel</li>
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const staffCategorySelect = document.getElementById('staff_category_id');
    const dateOfHireInput = document.getElementById('date_of_hire');
    const probationStartInput = document.getElementById('probation_start_date');
    const probationEndPreview = document.getElementById('probation_end_preview');
    const reviewDuePreview = document.getElementById('review_due_preview');
    const probationPeriodPreview = document.getElementById('probation_period_preview');

    function updateCalculatedDates() {
        const selectedOption = staffCategorySelect.options[staffCategorySelect.selectedIndex];
        const probationStartDate = probationStartInput.value;
        
        if (selectedOption && selectedOption.dataset.months && probationStartDate) {
            const months = parseInt(selectedOption.dataset.months);
            const startDate = new Date(probationStartDate);
            
            // Calculate probation end date
            const endDate = new Date(startDate);
            endDate.setMonth(endDate.getMonth() + months);
            
            // Calculate review due date (30 days before end)
            const reviewDate = new Date(endDate);
            reviewDate.setDate(reviewDate.getDate() - 30);
            
            // Format dates
            const formatDate = (date) => {
                return date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
            };
            
            probationEndPreview.textContent = formatDate(endDate);
            reviewDuePreview.textContent = formatDate(reviewDate);
            probationPeriodPreview.textContent = `${months} months`;
        } else {
            probationEndPreview.textContent = '-';
            reviewDuePreview.textContent = '-';
            probationPeriodPreview.textContent = '-';
        }
    }

    staffCategorySelect.addEventListener('change', updateCalculatedDates);
    probationStartInput.addEventListener('change', updateCalculatedDates);
    
    // Auto-fill probation start date with hire date if available
    dateOfHireInput.addEventListener('change', function() {
        if (this.value && !probationStartInput.value) {
            probationStartInput.value = this.value;
            updateCalculatedDates();
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
