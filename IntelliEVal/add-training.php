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
$page_title = 'Add Training/Seminar';

$message = '';
$message_type = '';

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
        // Insert training/seminar
        $insert_query = "INSERT INTO trainings_seminars (
            title, description, type, category_id, main_category_id, sub_category_id,
            duration_hours, max_participants, venue, start_date, end_date, registration_deadline,
            status, is_mandatory, certificate_provided, materials_provided, cost, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "sssiiidssssssiiid",
            $title, $description, $type, $category_id, $main_category_id, $sub_category_id,
            $duration_hours, $max_participants, $venue, $start_date, $end_date, $registration_deadline,
            $status, $is_mandatory, $certificate_provided, $materials_provided, $cost, $_SESSION['user_id']
        );

        if (mysqli_stmt_execute($stmt)) {
            $training_id = mysqli_insert_id($conn);
            $_SESSION['message'] = "Training/Seminar added successfully!";
            $_SESSION['message_type'] = 'success';
            header('Location: trainings.php');
            exit();
        } else {
            $message = "Error adding training/seminar: " . mysqli_error($conn);
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

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Add Training/Seminar</h1>
            <p class="text-sm sm:text-base text-gray-600">Create a new training or seminar program</p>
        </div>
        <a href="trainings.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition text-center">
            <i class="fas fa-arrow-left mr-2"></i>Back to Trainings
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md p-6">
    <form method="POST" class="space-y-6">
        <!-- Basic Information -->
        <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                    <input type="text" name="title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type *</label>
                    <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" required>
                        <option value="">Select Type</option>
                        <option value="training" <?php echo (isset($_POST['type']) && $_POST['type'] === 'training') ? 'selected' : ''; ?>>Training</option>
                        <option value="seminar" <?php echo (isset($_POST['type']) && $_POST['type'] === 'seminar') ? 'selected' : ''; ?>>Seminar</option>
                        <option value="workshop" <?php echo (isset($_POST['type']) && $_POST['type'] === 'workshop') ? 'selected' : ''; ?>>Workshop</option>
                        <option value="conference" <?php echo (isset($_POST['type']) && $_POST['type'] === 'conference') ? 'selected' : ''; ?>>Conference</option>
                    </select>
                </div>
            </div>

            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                <textarea name="description" rows="4"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>
        </div>

        <!-- Categories -->
        <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Categories</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Training Category</label>
                    <select name="category_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <option value="">Select Category</option>
                        <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Evaluation Main Category</label>
                    <select name="main_category_id" id="main_category_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <option value="">Select Main Category</option>
                        <?php while ($main_category = mysqli_fetch_assoc($main_categories_result)): ?>
                        <option value="<?php echo $main_category['id']; ?>" <?php echo (isset($_POST['main_category_id']) && $_POST['main_category_id'] == $main_category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($main_category['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Evaluation Sub-Category</label>
                    <select name="sub_category_id" id="sub_category_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <option value="">Select Sub-Category</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Schedule -->
        <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Schedule</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Start Date & Time *</label>
                    <input type="datetime-local" name="start_date" value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : ''; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">End Date & Time *</label>
                    <input type="datetime-local" name="end_date" value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : ''; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Registration Deadline</label>
                    <input type="datetime-local" name="registration_deadline" value="<?php echo isset($_POST['registration_deadline']) ? $_POST['registration_deadline'] : ''; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>
            </div>
        </div>

        <!-- Details -->
        <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Duration (Hours)</label>
                    <input type="number" name="duration_hours" step="0.5" min="0" value="<?php echo isset($_POST['duration_hours']) ? $_POST['duration_hours'] : ''; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Max Participants</label>
                    <input type="number" name="max_participants" min="1" value="<?php echo isset($_POST['max_participants']) ? $_POST['max_participants'] : ''; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Venue</label>
                    <input type="text" name="venue" value="<?php echo isset($_POST['venue']) ? htmlspecialchars($_POST['venue']) : ''; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cost (₱)</label>
                    <input type="number" name="cost" step="0.01" min="0" value="<?php echo isset($_POST['cost']) ? $_POST['cost'] : '0.00'; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>
            </div>
        </div>

        <!-- Options -->
        <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Options</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="flex items-center">
                    <input type="checkbox" name="is_mandatory" id="is_mandatory" value="1" <?php echo (isset($_POST['is_mandatory']) && $_POST['is_mandatory']) ? 'checked' : ''; ?>
                           class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                    <label for="is_mandatory" class="ml-2 block text-sm text-gray-900">Mandatory Training</label>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="certificate_provided" id="certificate_provided" value="1" <?php echo (isset($_POST['certificate_provided']) && $_POST['certificate_provided']) ? 'checked' : ''; ?>
                           class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                    <label for="certificate_provided" class="ml-2 block text-sm text-gray-900">Certificate Provided</label>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="materials_provided" id="materials_provided" value="1" <?php echo (isset($_POST['materials_provided']) && $_POST['materials_provided']) ? 'checked' : ''; ?>
                           class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                    <label for="materials_provided" class="ml-2 block text-sm text-gray-900">Materials Provided</label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" required>
                        <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo (isset($_POST['status']) && $_POST['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
            <a href="trainings.php" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700 transition-colors">
                Cancel
            </a>
            <button type="submit" class="bg-seait-orange text-white px-6 py-2 rounded-md hover:bg-orange-600 transition-colors">
                <i class="fas fa-save mr-2"></i>Save Training
            </button>
        </div>
    </form>
</div>

<script>
// Dynamic sub-category loading
document.getElementById('main_category_id').addEventListener('change', function() {
    const mainCategoryId = this.value;
    const subCategorySelect = document.getElementById('sub_category_id');

    // Clear sub-category options
    subCategorySelect.innerHTML = '<option value="">Select Sub-Category</option>';

    if (mainCategoryId) {
        // Fetch sub-categories for the selected main category
        fetch(`get_sub_categories.php?main_category_id=${mainCategoryId}`)
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
                console.error('Error fetching sub-categories:', error);
            });
    }
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>