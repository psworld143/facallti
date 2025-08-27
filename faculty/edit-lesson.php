<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$lesson_id = safe_decrypt_id($_GET['id']);
$message = '';
$message_type = '';

// Get lesson data
if (!$lesson_id) {
    header('Location: lessons.php');
    exit();
}

$lesson_query = "SELECT * FROM lessons WHERE id = ? AND teacher_id = ?";
$lesson_stmt = mysqli_prepare($conn, $lesson_query);
mysqli_stmt_bind_param($lesson_stmt, "ii", $lesson_id, $_SESSION['user_id']);
mysqli_stmt_execute($lesson_stmt);
$lesson_result = mysqli_stmt_get_result($lesson_stmt);

if (mysqli_num_rows($lesson_result) == 0) {
    header('Location: lessons.php?message=' . urlencode('Lesson not found or access denied.') . '&type=error');
    exit();
}

$lesson = mysqli_fetch_assoc($lesson_result);

// Get current class assignments
$current_classes_query = "SELECT class_id FROM lesson_class_assignments WHERE lesson_id = ?";
$current_classes_stmt = mysqli_prepare($conn, $current_classes_query);
mysqli_stmt_bind_param($current_classes_stmt, "i", $lesson_id);
mysqli_stmt_execute($current_classes_stmt);
$current_classes_result = mysqli_stmt_get_result($current_classes_stmt);

$current_class_ids = [];
while ($class = mysqli_fetch_assoc($current_classes_result)) {
    $current_class_ids[] = $class['class_id'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $content = $_POST['content']; // Don't sanitize HTML content from CKEditor
    $lesson_type = sanitize_input($_POST['lesson_type']);
    $status = sanitize_input($_POST['status']);
    $order_number = (int)$_POST['order_number'];
    $selected_classes = isset($_POST['selected_classes']) ? $_POST['selected_classes'] : [];

    if (empty($selected_classes)) {
        $message = "Please select at least one class to assign this lesson to.";
        $message_type = "error";
    } else {
        // Handle file upload
        $file_path = $lesson['file_path'];
        $file_name = $lesson['file_name'];
        $file_type = $lesson['file_type'];
        $file_size = $lesson['file_size'];

        if (isset($_FILES['lesson_file']) && $_FILES['lesson_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/lessons/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_info = pathinfo($_FILES['lesson_file']['name']);
            $file_extension = strtolower($file_info['extension']);
            $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'mp4', 'avi', 'mov', 'jpg', 'jpeg', 'png'];

            if (in_array($file_extension, $allowed_extensions)) {
                // Delete old file if exists
                if ($lesson['file_path'] && file_exists($lesson['file_path'])) {
                    unlink($lesson['file_path']);
                }

                $file_name = time() . '_' . sanitize_input($_FILES['lesson_file']['name']);
                $file_path = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES['lesson_file']['tmp_name'], $file_path)) {
                    $file_type = $_FILES['lesson_file']['type'];
                    // Truncate file_type if it's too long (safety measure)
                    if (strlen($file_type) > 255) {
                        $file_type = substr($file_type, 0, 255);
                    }
                    $file_size = $_FILES['lesson_file']['size'];
                } else {
                    $message = "Error uploading file. Please try again.";
                    $message_type = "error";
                }
            } else {
                $message = "Invalid file type. Allowed types: " . implode(', ', $allowed_extensions);
                $message_type = "error";
            }
        }

        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            $lesson_query = "UPDATE lessons SET title = ?, description = ?, content = ?, file_path = ?, file_name = ?, file_type = ?, file_size = ?, lesson_type = ?, status = ?, order_number = ? WHERE id = ? AND teacher_id = ?";
            $lesson_stmt = mysqli_prepare($conn, $lesson_query);
            mysqli_stmt_bind_param($lesson_stmt, "sssssssssiis", $title, $description, $content, $file_path, $file_name, $file_type, $file_size, $lesson_type, $status, $order_number, $lesson_id, $_SESSION['user_id']);

            if (mysqli_stmt_execute($lesson_stmt)) {
                // Update class assignments
                // First, remove all current assignments
                $delete_assignments_query = "DELETE FROM lesson_class_assignments WHERE lesson_id = ?";
                $delete_assignments_stmt = mysqli_prepare($conn, $delete_assignments_query);
                mysqli_stmt_bind_param($delete_assignments_stmt, "i", $lesson_id);
                mysqli_stmt_execute($delete_assignments_stmt);

                // Then, add new assignments
                foreach ($selected_classes as $class_id) {
                    $assignment_query = "INSERT INTO lesson_class_assignments (lesson_id, class_id) VALUES (?, ?)";
                    $assignment_stmt = mysqli_prepare($conn, $assignment_query);
                    mysqli_stmt_bind_param($assignment_stmt, "ii", $lesson_id, $class_id);
                    mysqli_stmt_execute($assignment_stmt);
                }

                mysqli_commit($conn);
                $success_message = "Lesson updated successfully and assigned to " . count($selected_classes) . " class(es)!";
                header('Location: lessons.php?message=' . urlencode($success_message) . '&type=success');
                exit();
            } else {
                throw new Exception("Error updating lesson: " . mysqli_error($conn));
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get classes for form
$classes_query = "SELECT tc.id, tc.section, cc.subject_title, cc.subject_code
                  FROM teacher_classes tc
                  JOIN course_curriculum cc ON tc.subject_id = cc.id
                  WHERE tc.teacher_id = ?
                  ORDER BY cc.subject_title, tc.section";
$classes_stmt = mysqli_prepare($conn, $classes_query);
mysqli_stmt_bind_param($classes_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($classes_stmt);
$classes_result = mysqli_stmt_get_result($classes_stmt);

$page_title = 'Edit Lesson: ' . $lesson['title'];

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Edit Lesson</h1>
            <p class="text-sm sm:text-base text-gray-600">Modify lesson content and settings</p>
        </div>
        <div class="flex space-x-2">
            <a href="view-lesson.php?id=<?php echo encrypt_id($lesson_id); ?>" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                <i class="fas fa-eye mr-2"></i>View Lesson
            </a>
            <a href="lessons.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Lessons
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Lesson Information</h2>
    </div>

    <form method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                    <input type="text" name="title" required
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : htmlspecialchars($lesson['title']); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                              placeholder="Brief description of the lesson..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : htmlspecialchars($lesson['description']); ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Content *</label>
                    <textarea name="content" id="editor" rows="15"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                              placeholder="Enter your lesson content here..."><?php echo isset($_POST['content']) ? $_POST['content'] : $lesson['content']; ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">Use the rich text editor to format your content with headings, lists, links, and more.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">File Upload</label>
                    <?php if ($lesson['file_name']): ?>
                    <div class="mb-3 p-3 bg-gray-50 rounded-md">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas fa-file text-gray-600 mr-2"></i>
                                <span class="text-sm text-gray-700"><?php echo htmlspecialchars($lesson['file_name']); ?></span>
                            </div>
                            <a href="<?php echo $lesson['file_path']; ?>" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-download mr-1"></i>Download
                            </a>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Upload a new file to replace the current one</p>
                    </div>
                    <?php endif; ?>
                    <input type="file" name="lesson_file"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    <p class="text-xs text-gray-500 mt-1">Supported formats: PDF, DOC, DOCX, PPT, PPTX, TXT, MP4, AVI, MOV, JPG, JPEG, PNG</p>
                </div>
            </div>

            <!-- Sidebar Settings -->
            <div class="space-y-6">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-gray-900 mb-4">Lesson Settings</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lesson Type</label>
                            <select name="lesson_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="text" <?php echo (isset($_POST['lesson_type']) ? $_POST['lesson_type'] : $lesson['lesson_type']) === 'text' ? 'selected' : ''; ?>>Text</option>
                                <option value="video" <?php echo (isset($_POST['lesson_type']) ? $_POST['lesson_type'] : $lesson['lesson_type']) === 'video' ? 'selected' : ''; ?>>Video</option>
                                <option value="document" <?php echo (isset($_POST['lesson_type']) ? $_POST['lesson_type'] : $lesson['lesson_type']) === 'document' ? 'selected' : ''; ?>>Document</option>
                                <option value="presentation" <?php echo (isset($_POST['lesson_type']) ? $_POST['lesson_type'] : $lesson['lesson_type']) === 'presentation' ? 'selected' : ''; ?>>Presentation</option>
                                <option value="link" <?php echo (isset($_POST['lesson_type']) ? $_POST['lesson_type'] : $lesson['lesson_type']) === 'link' ? 'selected' : ''; ?>>Link</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="draft" <?php echo (isset($_POST['status']) ? $_POST['status'] : $lesson['status']) === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo (isset($_POST['status']) ? $_POST['status'] : $lesson['status']) === 'published' ? 'selected' : ''; ?>>Published</option>
                                <option value="archived" <?php echo (isset($_POST['status']) ? $_POST['status'] : $lesson['status']) === 'archived' ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Order Number</label>
                            <input type="number" name="order_number" value="<?php echo isset($_POST['order_number']) ? (int)$_POST['order_number'] : (int)$lesson['order_number']; ?>" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <p class="text-xs text-gray-500 mt-1">Used for ordering lessons in sequence</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-gray-900 mb-4">Class Assignment *</h3>
                    <div class="max-h-60 overflow-y-auto space-y-2">
                        <?php if (mysqli_num_rows($classes_result) == 0): ?>
                            <p class="text-sm text-gray-500">No classes available. Please create a class first.</p>
                        <?php else: ?>
                            <?php while ($class = mysqli_fetch_assoc($classes_result)): ?>
                            <label class="flex items-center">
                                <input type="checkbox" name="selected_classes[]" value="<?php echo $class['id']; ?>"
                                       <?php echo (isset($_POST['selected_classes']) && in_array($class['id'], $_POST['selected_classes'])) || (!isset($_POST['selected_classes']) && in_array($class['id'], $current_class_ids)) ? 'checked' : ''; ?>
                                       class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                                <span class="ml-2 text-sm text-gray-700">
                                    <?php echo htmlspecialchars($class['subject_code'] . ' - ' . $class['subject_title'] . ' (' . $class['section'] . ')'); ?>
                                </span>
                            </label>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Select at least one class to assign this lesson to</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
            <a href="lessons.php" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition">
                Cancel
            </a>
            <button type="submit" class="bg-seait-orange text-white px-6 py-2 rounded-md hover:bg-orange-600 transition">
                <i class="fas fa-save mr-2"></i>Update Lesson
            </button>
        </div>
    </form>
</div>

<!-- CKEditor Integration -->
<script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script>
<script>
let editor;

ClassicEditor
    .create(document.querySelector('#editor'), {
        toolbar: {
            items: [
                'heading',
                '|',
                'bold',
                'italic',
                'underline',
                'strikethrough',
                '|',
                'bulletedList',
                'numberedList',
                '|',
                'indent',
                'outdent',
                '|',
                'link',
                'blockQuote',
                'insertTable',
                '|',
                'undo',
                'redo'
            ]
        },
        heading: {
            options: [
                { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
                { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' }
            ]
        },
        table: {
            contentToolbar: [
                'tableColumn',
                'tableRow',
                'mergeTableCells'
            ]
        }
    })
    .then(newEditor => {
        editor = newEditor;
        console.log('CKEditor initialized successfully');
    })
    .catch(error => {
        console.error(error);
    });
</script>

<style>
.ck-editor__editable {
    min-height: 300px;
    max-height: 500px;
    overflow-y: auto;
}

.ck-editor__editable_inline {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
}

.ck.ck-editor__main > .ck-editor__editable:not(.ck-focused) {
    border-color: #d1d5db;
}

.ck.ck-editor__main > .ck-editor__editable.ck-focused {
    border-color: #ff6b35;
    box-shadow: 0 0 0 2px rgba(255, 107, 53, 0.2);
}
</style>

<?php include 'includes/footer.php'; ?>