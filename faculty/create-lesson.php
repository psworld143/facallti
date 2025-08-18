<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Create New Lesson';
$message = '';
$message_type = '';

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
        $file_path = '';
        $file_name = '';
        $file_type = '';
        $file_size = 0;

        if (isset($_FILES['lesson_file']) && $_FILES['lesson_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/lessons/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_info = pathinfo($_FILES['lesson_file']['name']);
            $file_extension = strtolower($file_info['extension']);
            $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'mp4', 'avi', 'mov', 'jpg', 'jpeg', 'png'];

            if (in_array($file_extension, $allowed_extensions)) {
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
            $lesson_query = "INSERT INTO lessons (teacher_id, title, description, content, file_path, file_name, file_type, file_size, lesson_type, status, order_number)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $lesson_stmt = mysqli_prepare($conn, $lesson_query);
            mysqli_stmt_bind_param($lesson_stmt, "isssssssssi", $_SESSION['user_id'], $title, $description, $content, $file_path, $file_name, $file_type, $file_size, $lesson_type, $status, $order_number);

            if (mysqli_stmt_execute($lesson_stmt)) {
                $lesson_id = mysqli_insert_id($conn);

                // Insert class assignments
                foreach ($selected_classes as $class_id) {
                    $assignment_query = "INSERT INTO lesson_class_assignments (lesson_id, class_id) VALUES (?, ?)";
                    $assignment_stmt = mysqli_prepare($conn, $assignment_query);
                    mysqli_stmt_bind_param($assignment_stmt, "ii", $lesson_id, $class_id);
                    mysqli_stmt_execute($assignment_stmt);
                }

                mysqli_commit($conn);
                $success_message = "Lesson created successfully and assigned to " . count($selected_classes) . " class(es)!";
                if ($reuse_lesson) {
                    $success_message .= " (Reused from: " . htmlspecialchars($reuse_lesson['title']) . ")";
                }
                header('Location: lessons.php?message=' . urlencode($success_message) . '&type=success');
                exit();
            } else {
                throw new Exception("Error creating lesson: " . mysqli_error($conn));
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

// Handle reuse lesson functionality
$reuse_lesson = null;
$reuse_lesson_classes = [];
if (isset($_GET['reuse_id'])) {
    $reuse_id = (int)$_GET['reuse_id'];

    // Get the lesson to reuse
    $reuse_query = "SELECT l.* FROM lessons l WHERE l.id = ? AND l.teacher_id = ? AND l.status = 'published'";
    $reuse_stmt = mysqli_prepare($conn, $reuse_query);
    mysqli_stmt_bind_param($reuse_stmt, "ii", $reuse_id, $_SESSION['user_id']);
    mysqli_stmt_execute($reuse_stmt);
    $reuse_result = mysqli_stmt_get_result($reuse_stmt);

    if (mysqli_num_rows($reuse_result) > 0) {
        $reuse_lesson = mysqli_fetch_assoc($reuse_result);

        // Get the classes this lesson was assigned to
        $reuse_classes_query = "SELECT lca.class_id FROM lesson_class_assignments lca WHERE lca.lesson_id = ?";
        $reuse_classes_stmt = mysqli_prepare($conn, $reuse_classes_query);
        mysqli_stmt_bind_param($reuse_classes_stmt, "i", $reuse_id);
        mysqli_stmt_execute($reuse_classes_stmt);
        $reuse_classes_result = mysqli_stmt_get_result($reuse_classes_stmt);

        while ($class = mysqli_fetch_assoc($reuse_classes_result)) {
            $reuse_lesson_classes[] = $class['class_id'];
        }
    }
}

// Get previous lessons for reuse (grouped by subject)
$previous_lessons_query = "SELECT l.id, l.title, l.description, l.content, l.lesson_type, l.created_at,
                          cc.subject_title, cc.subject_code, tc.section,
                          lca.class_id
                          FROM lessons l
                          JOIN lesson_class_assignments lca ON l.id = lca.lesson_id
                          JOIN teacher_classes tc ON lca.class_id = tc.id
                          JOIN course_curriculum cc ON tc.subject_id = cc.id
                          WHERE l.teacher_id = ? AND l.status = 'published'
                          ORDER BY cc.subject_title, l.created_at DESC";
$previous_lessons_stmt = mysqli_prepare($conn, $previous_lessons_query);
mysqli_stmt_bind_param($previous_lessons_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($previous_lessons_stmt);
$previous_lessons_result = mysqli_stmt_get_result($previous_lessons_stmt);

// Group lessons by subject
$lessons_by_subject = [];
while ($lesson = mysqli_fetch_assoc($previous_lessons_result)) {
    $subject_key = $lesson['subject_code'] . ' - ' . $lesson['subject_title'];
    if (!isset($lessons_by_subject[$subject_key])) {
        $lessons_by_subject[$subject_key] = [];
    }
    $lessons_by_subject[$subject_key][] = $lesson;
}

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Create New Lesson</h1>
            <p class="text-sm sm:text-base text-gray-600">Create a new lesson with rich content and assign it to multiple classes</p>
        </div>
        <div class="flex space-x-2">
            <button onclick="openReuseLessonModal()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition">
                <i class="fas fa-copy mr-2"></i>Reuse Previous Lesson
            </button>
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

<?php if ($reuse_lesson): ?>
<div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800">
    <div class="flex items-center">
        <i class="fas fa-info-circle mr-2"></i>
        <span>You are reusing lesson: <strong><?php echo htmlspecialchars($reuse_lesson['title']); ?></strong>. The form has been pre-filled with the original content. You can modify it before saving.</span>
    </div>
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
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ($reuse_lesson ? htmlspecialchars($reuse_lesson['title'] . ' (Copy)') : ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                              placeholder="Brief description of the lesson..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ($reuse_lesson ? htmlspecialchars($reuse_lesson['description']) : ''); ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Content *</label>
                    <textarea name="content" id="editor" rows="15"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                              placeholder="Enter your lesson content here..."><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ($reuse_lesson ? htmlspecialchars($reuse_lesson['content']) : ''); ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">Use the rich text editor to format your content with headings, lists, links, and more.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">File Upload</label>
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
                                <option value="text" <?php echo (isset($_POST['lesson_type']) && $_POST['lesson_type'] === 'text') || ($reuse_lesson && $reuse_lesson['lesson_type'] === 'text') ? 'selected' : ''; ?>>Text</option>
                                <option value="video" <?php echo (isset($_POST['lesson_type']) && $_POST['lesson_type'] === 'video') || ($reuse_lesson && $reuse_lesson['lesson_type'] === 'video') ? 'selected' : ''; ?>>Video</option>
                                <option value="document" <?php echo (isset($_POST['lesson_type']) && $_POST['lesson_type'] === 'document') || ($reuse_lesson && $reuse_lesson['lesson_type'] === 'document') ? 'selected' : ''; ?>>Document</option>
                                <option value="presentation" <?php echo (isset($_POST['lesson_type']) && $_POST['lesson_type'] === 'presentation') || ($reuse_lesson && $reuse_lesson['lesson_type'] === 'presentation') ? 'selected' : ''; ?>>Presentation</option>
                                <option value="link" <?php echo (isset($_POST['lesson_type']) && $_POST['lesson_type'] === 'link') || ($reuse_lesson && $reuse_lesson['lesson_type'] === 'link') ? 'selected' : ''; ?>>Link</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] === 'draft') || ($reuse_lesson && $reuse_lesson['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo (isset($_POST['status']) && $_POST['status'] === 'published') || ($reuse_lesson && $reuse_lesson['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                                <option value="archived" <?php echo (isset($_POST['status']) && $_POST['status'] === 'archived') || ($reuse_lesson && $reuse_lesson['status'] === 'archived') ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Order Number</label>
                            <input type="number" name="order_number" value="<?php echo isset($_POST['order_number']) ? (int)$_POST['order_number'] : 0; ?>" min="0"
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
                                       <?php echo (isset($_POST['selected_classes']) && in_array($class['id'], $_POST['selected_classes'])) || ($reuse_lesson && in_array($class['id'], $reuse_lesson_classes)) ? 'checked' : ''; ?>
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
                <i class="fas fa-save mr-2"></i>Create Lesson
            </button>
        </div>
    </form>
</div>

<!-- Reuse Previous Lesson Modal -->
<div id="reuseLessonModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Reuse Previous Lesson</h3>
                <button onclick="closeReuseLessonModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="space-y-4">
                <p class="text-sm text-gray-600">Select a previous lesson to copy its content and settings. You can modify the copied content before saving.</p>

                <?php if (empty($lessons_by_subject)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-book text-gray-300 text-4xl mb-4"></i>
                        <p class="text-gray-500">No previous lessons available to reuse.</p>
                        <p class="text-sm text-gray-400 mt-2">Create your first lesson to enable this feature.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($lessons_by_subject as $subject => $lessons): ?>
                        <div class="border border-gray-200 rounded-lg">
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                <h4 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($subject); ?></h4>
                            </div>
                            <div class="p-4 space-y-3">
                                <?php foreach ($lessons as $lesson): ?>
                                <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                    <div class="flex-1">
                                        <h5 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($lesson['title']); ?></h5>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <?php echo htmlspecialchars($lesson['description']); ?>
                                        </p>
                                        <div class="flex items-center mt-2 space-x-4 text-xs text-gray-500">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?php echo ucfirst($lesson['lesson_type']); ?>
                                            </span>
                                            <span>Section: <?php echo htmlspecialchars($lesson['section']); ?></span>
                                            <span>Created: <?php echo date('M j, Y', strtotime($lesson['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <button onclick="reuseLesson(<?php echo htmlspecialchars(json_encode($lesson)); ?>)"
                                            class="ml-4 bg-seait-orange text-white px-3 py-1 rounded-md hover:bg-orange-600 transition text-sm">
                                        <i class="fas fa-copy mr-1"></i>Reuse
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex justify-end pt-4">
                <button onclick="closeReuseLessonModal()" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                    Cancel
                </button>
            </div>
        </div>
    </div>
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

// Reuse Lesson Modal Functions
function openReuseLessonModal() {
    document.getElementById('reuseLessonModal').classList.remove('hidden');
}

function closeReuseLessonModal() {
    document.getElementById('reuseLessonModal').classList.add('hidden');
}

function reuseLesson(lessonData) {
    // Fill the form with the selected lesson data
    document.querySelector('input[name="title"]').value = lessonData.title + ' (Copy)';
    document.querySelector('textarea[name="description"]').value = lessonData.description;

    // Set the lesson type
    document.querySelector('select[name="lesson_type"]').value = lessonData.lesson_type;

    // Set the content in CKEditor
    if (editor) {
        editor.setData(lessonData.content);
    }

    // Auto-select the class that the original lesson was assigned to
    const classId = lessonData.class_id;
    const checkboxes = document.querySelectorAll('input[name="selected_classes[]"]');
    checkboxes.forEach(checkbox => {
        if (checkbox.value == classId) {
            checkbox.checked = true;
        }
    });

    // Close the modal
    closeReuseLessonModal();

    // Show a success message
    showMessage('Lesson content copied successfully! You can now modify and save the new lesson.', 'success');
}

function showMessage(message, type) {
    // Create a temporary message element
    const messageDiv = document.createElement('div');
    messageDiv.className = `mb-6 p-4 rounded-lg ${type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'}`;
    messageDiv.textContent = message;

    // Insert at the top of the form
    const form = document.querySelector('form');
    form.parentNode.insertBefore(messageDiv, form);

    // Remove after 5 seconds
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

// Close modal when clicking outside
document.getElementById('reuseLessonModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReuseLessonModal();
    }
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