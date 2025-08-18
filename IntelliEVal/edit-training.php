<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'Edit Training/Seminar';

$message = '';
$message_type = '';

// Get training ID
$training_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$training_id) {
    $_SESSION['message'] = 'Invalid training ID provided.';
    $_SESSION['message_type'] = 'error';
    header('Location: trainings.php');
    exit();
}

// Get training details
$training_query = "SELECT * FROM trainings_seminars WHERE id = ?";
$stmt = mysqli_prepare($conn, $training_query);
mysqli_stmt_bind_param($stmt, "i", $training_id);
mysqli_stmt_execute($stmt);
$training_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($training_result) == 0) {
    $_SESSION['message'] = 'Training not found.';
    $_SESSION['message_type'] = 'error';
    header('Location: trainings.php');
    exit();
}

$training = mysqli_fetch_assoc($training_result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $main_category_id = !empty($_POST['main_category_id']) ? (int)$_POST['main_category_id'] : null;
    $sub_category_id = !empty($_POST['sub_category_id']) ? (int)$_POST['sub_category_id'] : null;
    $duration_hours = !empty($_POST['duration_hours']) ? (float)$_POST['duration_hours'] : null;
    $max_participants = !empty($_POST['max_participants']) ? (int)$_POST['max_participants'] : null;
    $venue = trim($_POST['venue']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $registration_deadline = !empty($_POST['registration_deadline']) ? $_POST['registration_deadline'] : null;
    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
    $certificate_provided = isset($_POST['certificate_provided']) ? 1 : 0;
    $materials_provided = isset($_POST['materials_provided']) ? 1 : 0;
    $cost = !empty($_POST['cost']) ? (float)$_POST['cost'] : 0.00;
    $status = $_POST['status'];

    // Validation
    $errors = [];

    if (empty($title)) {
        $errors[] = "Title is required";
    }

    if (empty($description)) {
        $errors[] = "Description is required";
    }

    if (empty($start_date)) {
        $errors[] = "Start date is required";
    }

    if (empty($end_date)) {
        $errors[] = "End date is required";
    }

    if (!empty($start_date) && !empty($end_date) && $start_date >= $end_date) {
        $errors[] = "End date must be after start date";
    }

    if (!empty($registration_deadline) && $registration_deadline >= $start_date) {
        $errors[] = "Registration deadline must be before start date";
    }

    if (empty($errors)) {
        // Update training/seminar
        $update_query = "UPDATE trainings_seminars SET
            title = ?, description = ?, type = ?, category_id = ?, main_category_id = ?, sub_category_id = ?,
            duration_hours = ?, max_participants = ?, venue = ?, start_date = ?, end_date = ?, registration_deadline = ?,
            status = ?, is_mandatory = ?, certificate_provided = ?, materials_provided = ?, cost = ?
            WHERE id = ?";

        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "sssiiidssssssiiidi",
            $title, $description, $type, $category_id, $main_category_id, $sub_category_id,
            $duration_hours, $max_participants, $venue, $start_date, $end_date, $registration_deadline,
            $status, $is_mandatory, $certificate_provided, $materials_provided, $cost, $training_id
        );

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "Training/Seminar updated successfully!";
            $_SESSION['message_type'] = 'success';
            header('Location: view-training.php?id=' . $training_id);
            exit();
        } else {
            $message = "Error updating training/seminar: " . mysqli_error($conn);
            $message_type = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// Get training categories
$categories_query = "SELECT id, name FROM training_categories WHERE status = 'active' ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

// Get main evaluation categories
$main_categories_query = "SELECT id, name FROM main_evaluation_categories WHERE status = 'active' ORDER BY name";
$main_categories_result = mysqli_query($conn, $main_categories_query);

// Get sub categories (will be populated via AJAX)
$sub_categories_query = "SELECT id, name FROM evaluation_sub_categories WHERE status = 'active' AND main_category_id = ? ORDER BY name";
$sub_stmt = mysqli_prepare($conn, $sub_categories_query);
mysqli_stmt_bind_param($sub_stmt, "i", $training['main_category_id']);
mysqli_stmt_execute($sub_stmt);
$sub_categories_result = mysqli_stmt_get_result($sub_stmt);

// Include the shared header
include 'includes/header.php';
?>

<!-- Custom CSS for edit-training page -->
<link rel="stylesheet" href="assets/css/edit-training.css">

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Edit Training/Seminar</h1>
            <p class="text-sm sm:text-base text-gray-600">Update training information and settings</p>
        </div>
        <div class="flex space-x-2">
            <a href="view-training.php?id=<?php echo $training_id; ?>" class="btn-secondary">
                <i class="fas fa-eye mr-2"></i>View Training
            </a>
            <a href="trainings.php" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Back to Trainings
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> mb-6">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<form method="POST" class="edit-training-form">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Form Section -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Information -->
            <div class="form-section">
                <h2 class="section-title">Basic Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="title" class="form-label">Title *</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($training['title']); ?>" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="type" class="form-label">Type *</label>
                        <select id="type" name="type" class="form-select" required>
                            <option value="training" <?php echo $training['type'] === 'training' ? 'selected' : ''; ?>>Training</option>
                            <option value="seminar" <?php echo $training['type'] === 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                            <option value="workshop" <?php echo $training['type'] === 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                            <option value="conference" <?php echo $training['type'] === 'conference' ? 'selected' : ''; ?>>Conference</option>
                        </select>
                    </div>

                    <div class="form-group lg:col-span-2">
                        <label for="description" class="form-label">Description *</label>
                        <textarea id="description" name="description" rows="4" class="form-textarea" required><?php echo htmlspecialchars($training['description']); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Categories -->
            <div class="form-section">
                <h2 class="section-title">Categories</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="category_id" class="form-label">Training Category</label>
                        <select id="category_id" name="category_id" class="form-select">
                            <option value="">Select Category</option>
                            <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $training['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="main_category_id" class="form-label">Main Evaluation Category</label>
                        <select id="main_category_id" name="main_category_id" class="form-select">
                            <option value="">Select Main Category</option>
                            <?php while ($main_category = mysqli_fetch_assoc($main_categories_result)): ?>
                            <option value="<?php echo $main_category['id']; ?>" <?php echo $training['main_category_id'] == $main_category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($main_category['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="sub_category_id" class="form-label">Sub Category</label>
                        <select id="sub_category_id" name="sub_category_id" class="form-select">
                            <option value="">Select Sub Category</option>
                            <?php while ($sub_category = mysqli_fetch_assoc($sub_categories_result)): ?>
                            <option value="<?php echo $sub_category['id']; ?>" <?php echo $training['sub_category_id'] == $sub_category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sub_category['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Schedule & Venue -->
            <div class="form-section">
                <h2 class="section-title">Schedule & Venue</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="start_date" class="form-label">Start Date & Time *</label>
                        <input type="datetime-local" id="start_date" name="start_date" value="<?php echo date('Y-m-d\TH:i', strtotime($training['start_date'])); ?>" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="end_date" class="form-label">End Date & Time *</label>
                        <input type="datetime-local" id="end_date" name="end_date" value="<?php echo date('Y-m-d\TH:i', strtotime($training['end_date'])); ?>" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="registration_deadline" class="form-label">Registration Deadline</label>
                        <input type="datetime-local" id="registration_deadline" name="registration_deadline" value="<?php echo $training['registration_deadline'] ? date('Y-m-d\TH:i', strtotime($training['registration_deadline'])) : ''; ?>" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="venue" class="form-label">Venue</label>
                        <input type="text" id="venue" name="venue" value="<?php echo htmlspecialchars($training['venue']); ?>" class="form-input" placeholder="Enter venue location">
                    </div>
                </div>
            </div>

            <!-- Details -->
            <div class="form-section">
                <h2 class="section-title">Additional Details</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="duration_hours" class="form-label">Duration (Hours)</label>
                        <input type="number" id="duration_hours" name="duration_hours" value="<?php echo $training['duration_hours']; ?>" class="form-input" step="0.5" min="0">
                    </div>

                    <div class="form-group">
                        <label for="max_participants" class="form-label">Max Participants</label>
                        <input type="number" id="max_participants" name="max_participants" value="<?php echo $training['max_participants']; ?>" class="form-input" min="1">
                    </div>

                    <div class="form-group">
                        <label for="cost" class="form-label">Cost (₱)</label>
                        <input type="number" id="cost" name="cost" value="<?php echo $training['cost']; ?>" class="form-input" step="0.01" min="0">
                    </div>

                    <div class="form-group">
                        <label for="status" class="form-label">Status *</label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="draft" <?php echo $training['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo $training['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="ongoing" <?php echo $training['status'] === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                            <option value="completed" <?php echo $training['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $training['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Options -->
            <div class="form-section">
                <h2 class="section-title">Options</h2>
                <div class="space-y-4">
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_mandatory" value="1" <?php echo $training['is_mandatory'] ? 'checked' : ''; ?> class="checkbox-input">
                            <span class="checkbox-text">Mandatory Training</span>
                        </label>
                    </div>

                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="certificate_provided" value="1" <?php echo $training['certificate_provided'] ? 'checked' : ''; ?> class="checkbox-input">
                            <span class="checkbox-text">Certificate Provided</span>
                        </label>
                    </div>

                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="materials_provided" value="1" <?php echo $training['materials_provided'] ? 'checked' : ''; ?> class="checkbox-input">
                            <span class="checkbox-text">Materials Provided</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Training Info -->
            <div class="info-card">
                <h3 class="info-title">Training Information</h3>
                <div class="info-content">
                    <div class="info-item">
                        <span class="info-label">Created:</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($training['created_at'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Updated:</span>
                        <span class="info-value"><?php echo $training['updated_at'] ? date('M d, Y', strtotime($training['updated_at'])) : 'Never'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Current Status:</span>
                        <span class="status-badge status-<?php echo $training['status']; ?>"><?php echo ucfirst($training['status']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save mr-2"></i>Update Training
                </button>
                <a href="view-training.php?id=<?php echo $training_id; ?>" class="btn-secondary">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
            </div>
        </div>
    </div>
</form>

<script>
// Dynamic sub-category loading
document.getElementById('main_category_id').addEventListener('change', function() {
    const mainCategoryId = this.value;
    const subCategorySelect = document.getElementById('sub_category_id');

    // Clear current options
    subCategorySelect.innerHTML = '<option value="">Select Sub Category</option>';

    if (mainCategoryId) {
        // Fetch sub-categories via AJAX
        fetch('get_sub_categories.php?main_category_id=' + mainCategoryId)
            .then(response => response.json())
            .then(data => {
                data.forEach(subCategory => {
                    const option = document.createElement('option');
                    option.value = subCategory.id;
                    option.textContent = subCategory.name;
                    subCategorySelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading sub-categories:', error);
            });
    }
});

// Form validation
document.querySelector('.edit-training-form').addEventListener('submit', function(e) {
    const startDate = new Date(document.getElementById('start_date').value);
    const endDate = new Date(document.getElementById('end_date').value);
    const registrationDeadline = document.getElementById('registration_deadline').value;

    if (endDate <= startDate) {
        e.preventDefault();
        alert('End date must be after start date.');
        return false;
    }

    if (registrationDeadline && new Date(registrationDeadline) >= startDate) {
        e.preventDefault();
        alert('Registration deadline must be before start date.');
        return false;
    }
});
</script>