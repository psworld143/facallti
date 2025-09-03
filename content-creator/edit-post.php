<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_content_creator();

$message = '';
$error = '';

// Get post ID from URL
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$post_id) {
    header('Location: my-posts.php');
    exit();
}

// Get post data
$post_query = "SELECT * FROM posts WHERE id = ? AND author_id = ?";
$stmt = mysqli_prepare($conn, $post_query);
mysqli_stmt_bind_param($stmt, "ii", $post_id, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$post_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($post_result) === 0) {
    header('Location: my-posts.php');
    exit();
}

$post = mysqli_fetch_assoc($post_result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    $title = sanitize_input($_POST['title']);
    $content = $_POST['content']; // Don't sanitize HTML content from CKEditor
    $type = sanitize_input($_POST['type']);

    // Handle image upload
    $image_url = $post['image_url']; // Default to existing image
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/images/news/';
        $file_tmp = $_FILES['post_image']['tmp_name'];
        $file_name = basename($_FILES['post_image']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($file_ext, $allowed_exts)) {
            $new_name = uniqid('news_', true) . '.' . $file_ext;
            $dest_path = $upload_dir . $new_name;
            if (move_uploaded_file($file_tmp, $dest_path)) {
                $image_url = 'assets/images/news/' . $new_name;
            }
        }
    }

    // Determine status based on action
    if (isset($_POST['action']) && $_POST['action'] === 'save_draft') {
        $status = 'draft';
        $message = display_message('Draft updated successfully!', 'success');
    } else {
        // When editing a post (not saving as draft), always mark for approval
        // unless it was already a draft
        if ($post['status'] === 'draft') {
            $status = 'draft'; // Keep as draft if it was already a draft
            $message = display_message('Draft updated successfully!', 'success');
        } else {
            $status = 'pending'; // Mark for approval for all other statuses
            if ($post['status'] === 'approved') {
                $message = display_message('Post updated successfully and submitted for re-approval!', 'success');
            } else {
                $message = display_message('Post updated successfully and submitted for review!', 'success');
            }
        }
    }

    // Author name is taken from the logged-in content creator; no separate author field stored
    
    // Handle additional images upload
    $additional_images_urls = [];
    
    // Get existing images
    if (!empty($post['additional_image_url'])) {
        $decoded = json_decode($post['additional_image_url'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $additional_images_urls = $decoded;
        } else {
            $additional_images_urls = [$post['additional_image_url']];
        }
    }
    
    // Handle deleted images
    if (!empty($_POST['deleted_images'])) {
        $deleted_images = json_decode($_POST['deleted_images'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($deleted_images)) {
            // Remove deleted images from the array
            $additional_images_urls = array_filter($additional_images_urls, function($image_url) use ($deleted_images) {
                return !in_array($image_url, $deleted_images);
            });
            // Re-index the array
            $additional_images_urls = array_values($additional_images_urls);
        }
    }
    
    // Handle new image uploads
    if (isset($_FILES['additional_images']) && is_array($_FILES['additional_images']['name'])) {
        $upload_dir = '../assets/images/news/';
        $max_images = 5; // Maximum 5 additional images
        
        // Check if upload directory exists, if not create it
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        for ($i = 0; $i < count($_FILES['additional_images']['name']) && count($additional_images_urls) < $max_images; $i++) {
            // Check if file was uploaded successfully
            if (isset($_FILES['additional_images']['error'][$i]) && $_FILES['additional_images']['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['additional_images']['tmp_name'][$i];
                $file_name = basename($_FILES['additional_images']['name'][$i]);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_ext, $allowed_exts)) {
                    $new_name = uniqid('additional_' . count($additional_images_urls) . '_', true) . '.' . $file_ext;
                    $dest_path = $upload_dir . $new_name;
                    
                    // Check if file is actually uploaded
                    if (is_uploaded_file($file_tmp) && move_uploaded_file($file_tmp, $dest_path)) {
                        $additional_images_urls[] = 'assets/images/news/' . $new_name;
                    }
                }
            }
        }
    }
    
    // Convert array to JSON string for database storage
    $additional_images_json = !empty($additional_images_urls) ? json_encode($additional_images_urls) : NULL;
    
    $query = "UPDATE posts SET title = ?, content = ?, type = ?, status = ?, image_url = ?, additional_image_url = ?, updated_at = NOW() WHERE id = ? AND author_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssssssii", $title, $content, $type, $status, $image_url, $additional_images_json, $post_id, $_SESSION['user_id']);

    if (mysqli_stmt_execute($stmt)) {
        // Update the post data for display
        $post['title'] = $title;
        $post['content'] = $content;
        $post['type'] = $type;
        $post['status'] = $status;
        // No separate author field maintained
        $post['image_url'] = $image_url;
        $post['additional_image_url'] = $additional_images_json;
    } else {
        $message = display_message('Error updating post. Please try again.', 'error');
    }
}
?>

<?php
$page_title = 'Edit Post';
include 'includes/header.php';
?>
    <!-- CKEditor CDN -->
    <script src="https://cdn.ckeditor.com/ckeditor5/40.1.0/classic/ckeditor.js"></script>
    <style>
        .image-preview-auto-fit {
            max-width: 100%;
            max-height: 12rem;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
    </style>
                <div class="mb-8">
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Edit Post</h1>
                    <p class="text-gray-600">Update your post content and settings</p>
                </div>

                <!-- Information Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 lg:p-6 mb-6 lg:mb-8">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 mb-2">Post Editing</h3>
                            <div class="text-sm text-blue-700 space-y-1">
                                <p><strong>Content Updates:</strong> Edit your post title, type, and content using the rich text editor.</p>
                                <p><strong>Status Tracking:</strong> View the current status of your post (draft, pending, approved, or rejected).</p>
                                <p><strong>Review Process:</strong> Changes to approved posts will require re-approval by the Social Media Manager.</p>
                                <p><strong>Save Options:</strong> Save as draft to continue editing later or submit for review when ready.</p>
                            </div>
                        </div>
                    </div>
                </div>

            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $message; ?>
                    <?php if (strpos($message, 'Draft updated') !== false): ?>
                        <div class="mt-2">
                            <a href="drafts.php" class="text-green-800 underline hover:text-green-900">View your drafts</a> |
                            <a href="my-posts.php" class="text-green-800 underline hover:text-green-900">View all posts</a>
                        </div>
                    <?php elseif (strpos($message, 're-approval') !== false): ?>
                        <div class="mt-2">
                            <a href="my-posts.php" class="text-green-800 underline hover:text-green-900">View all posts</a> |
                            <a href="dashboard.php" class="text-green-800 underline hover:text-green-900">Return to dashboard</a>
                        </div>
                    <?php else: ?>
                        <div class="mt-2">
                            <a href="my-posts.php" class="text-green-800 underline hover:text-green-900">View all posts</a> |
                            <a href="dashboard.php" class="text-green-800 underline hover:text-green-900">Return to dashboard</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Post Status Banner -->
            <div class="mb-6">
                <div class="bg-white rounded-lg shadow-md p-4 border-l-4 <?php
                    echo $post['status'] == 'approved' ? 'border-green-500 bg-green-50' :
                        ($post['status'] == 'pending' ? 'border-yellow-500 bg-yellow-50' :
                        ($post['status'] == 'rejected' ? 'border-red-500 bg-red-50' : 'border-gray-500 bg-gray-50'));
                ?>">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <?php if ($post['status'] == 'approved'): ?>
                                <i class="fas fa-check-circle text-green-500"></i>
                            <?php elseif ($post['status'] == 'pending'): ?>
                                <i class="fas fa-clock text-yellow-500"></i>
                            <?php elseif ($post['status'] == 'rejected'): ?>
                                <i class="fas fa-times-circle text-red-500"></i>
                            <?php else: ?>
                                <i class="fas fa-edit text-gray-500"></i>
                            <?php endif; ?>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium <?php
                                echo $post['status'] == 'approved' ? 'text-green-800' :
                                    ($post['status'] == 'pending' ? 'text-yellow-800' :
                                    ($post['status'] == 'rejected' ? 'text-red-800' : 'text-gray-800'));
                            ?>">
                                Post Status: <?php echo ucfirst($post['status']); ?>
                            </h3>
                            <p class="text-sm <?php
                                echo $post['status'] == 'approved' ? 'text-green-700' :
                                    ($post['status'] == 'pending' ? 'text-yellow-700' :
                                    ($post['status'] == 'rejected' ? 'text-red-700' : 'text-gray-700'));
                            ?>">
                                <?php if ($post['status'] == 'approved'): ?>
                                    This post has been approved and is published on the website. Any edits will require re-approval.
                                <?php elseif ($post['status'] == 'pending'): ?>
                                    This post is awaiting review by the Social Media Manager.
                                <?php elseif ($post['status'] == 'rejected'): ?>
                                    This post was rejected. You can edit and resubmit it for approval.
                                <?php else: ?>
                                    This is a draft. You can continue editing or submit for review.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <form method="POST" class="space-y-6" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="submit">

                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                            Post Title *
                        </label>
                        <input type="text" id="title" name="title" required
                               value="<?php echo htmlspecialchars($post['title']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                               placeholder="Enter post title">
                    </div>

                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                            Post Type *
                        </label>
                        <select id="type" name="type" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                            <option value="">Select post type</option>
                            <option value="news" <?php echo $post['type'] === 'news' ? 'selected' : ''; ?>>News</option>
                            <option value="announcement" <?php echo $post['type'] === 'announcement' ? 'selected' : ''; ?>>Announcement</option>
                            <option value="hiring" <?php echo $post['type'] === 'hiring' ? 'selected' : ''; ?>>Hiring</option>
                            <option value="event" <?php echo $post['type'] === 'event' ? 'selected' : ''; ?>>Event</option>
                            <option value="article" <?php echo $post['type'] === 'article' ? 'selected' : ''; ?>>Article</option>
                        </select>
                    </div>

                    <!-- Author input removed: author inferred from logged-in content creator -->

                    <div>
                        <label for="post_image" class="block text-sm font-medium text-gray-700 mb-2">
                            Post Main Image
                        </label>
                        <?php if (!empty($post['image_url'])): ?>
                            <div class="mb-2">
                                <img src="../<?php echo htmlspecialchars($post['image_url']); ?>" alt="Current Image" class="image-preview-auto-fit">
                                <div class="text-xs text-gray-500">Current Image</div>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="post_image" name="post_image" accept="image/*"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                               onchange="previewImage(event)">
                        <div id="imagePreview" class="mt-2"></div>
                    </div>

                                         <div>
                         <label for="content" class="block text-sm font-medium text-gray-700 mb-2">
                             Content *
                         </label>
                         <textarea id="content" name="content" rows="12" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                                   placeholder="Write your content here..."><?php echo htmlspecialchars($post['content']); ?></textarea>
                     </div>

                     <div>
                         <label for="additional_images" class="block text-sm font-medium text-gray-700 mb-2">
                             Additional Images
                         </label>
                         <?php 
                         $current_images = [];
                         if (!empty($post['additional_image_url'])) {
                             // Check if it's JSON (new format) or single image (old format)
                             $decoded = json_decode($post['additional_image_url'], true);
                             if (json_last_error() === JSON_ERROR_NONE) {
                                 $current_images = $decoded;
                             } else {
                                 $current_images = [$post['additional_image_url']];
                             }
                         }
                         ?>
                         
                         <!-- Hidden input to track deleted images -->
                         <input type="hidden" id="deleted_images" name="deleted_images" value="">
                         
                         <?php if (!empty($current_images)): ?>
                              <div class="mb-4">
                                  <div class="text-sm font-medium text-gray-700 mb-2">Current Additional Images:</div>
                                  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                      <?php foreach ($current_images as $index => $image_url): ?>
                                          <div class="relative group" id="image-container-<?php echo $index; ?>">
                                              <img src="../<?php echo htmlspecialchars($image_url); ?>" 
                                                   alt="Additional Image <?php echo $index + 1; ?>" 
                                                   class="w-full h-32 object-cover rounded-lg border-2 border-gray-200 hover:border-seait-orange transition-all duration-200 shadow-sm hover:shadow-md cursor-pointer"
                                                   onclick="openImageModal('../<?php echo htmlspecialchars($image_url); ?>', 'Additional Image <?php echo $index + 1; ?>')">
                                              
                                              <!-- Delete button -->
                                              <button type="button" 
                                                      onclick="deleteImage(<?php echo $index; ?>, '<?php echo htmlspecialchars($image_url); ?>')"
                                                      class="absolute top-1 right-1 bg-red-500 hover:bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs transition-all duration-200 opacity-0 group-hover:opacity-100 shadow-lg">
                                                  <i class="fas fa-times"></i>
                                              </button>
                                              
                                              <div class="text-xs text-gray-500 mt-1 text-center">Image <?php echo $index + 1; ?></div>
                                          </div>
                                      <?php endforeach; ?>
                                  </div>
                              </div>
                          <?php endif; ?>
                         <input type="file" id="additional_images" name="additional_images[]" accept="image/*" multiple
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                                onchange="previewAdditionalImages(event)">
                         <div id="additionalImagesPreview" class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2"></div>
                         <p class="text-xs text-gray-500 mt-1">Upload multiple additional images to display below the content (max 5 images)</p>
                     </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">
                                    Content Review Process
                                </h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <p>Your updated post will be submitted for review by the Social Media Manager.
                                    <?php if ($post['status'] === 'approved'): ?>
                                        Since this post was previously approved, any changes will require re-approval before being published.
                                    <?php else: ?>
                                        Once approved, it will be published on the website.
                                    <?php endif; ?>
                                </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4">
                        <button type="submit" onclick="prepareFormSubmission()"
                                class="bg-seait-orange text-white px-4 sm:px-6 py-2 rounded-md hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:ring-offset-2 transition">
                            <i class="fas fa-paper-plane mr-2"></i><span class="text-sm sm:text-base">
                                <?php if ($post['status'] === 'approved'): ?>
                                    Update and Submit for Re-approval
                                <?php else: ?>
                                    Update and Submit for Review
                                <?php endif; ?>
                            </span>
                        </button>
                        <button type="button" onclick="saveDraft()"
                                class="bg-gray-500 text-white px-4 sm:px-6 py-2 rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition">
                            <i class="fas fa-save mr-2"></i><span class="text-sm sm:text-base">Save as Draft</span>
                        </button>
                        <a href="my-posts.php"
                           class="bg-gray-300 text-gray-700 px-4 sm:px-6 py-2 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 transition text-center">
                            <i class="fas fa-times mr-2"></i><span class="text-sm sm:text-base">Cancel</span>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Content Guidelines -->
            <div class="mt-6 lg:mt-8 bg-white rounded-lg shadow-md p-4 lg:p-6">
                <h3 class="text-base lg:text-lg font-semibold text-seait-dark mb-4">Content Guidelines</h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-2 text-sm lg:text-base">Do's</h4>
                        <ul class="space-y-2 text-xs lg:text-sm text-gray-600">
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                                <span>Use clear, professional language</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                                <span>Include relevant details and context</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                                <span>Use the rich text editor for formatting</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                                <span>Follow SEAIT branding guidelines</span>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-2 text-sm lg:text-base">Don'ts</h4>
                        <ul class="space-y-2 text-xs lg:text-sm text-gray-600">
                            <li class="flex items-start">
                                <i class="fas fa-times text-red-500 mt-1 mr-2"></i>
                                <span>Avoid informal or inappropriate language</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-times text-red-500 mt-1 mr-2"></i>
                                <span>Don't include personal opinions</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-times text-red-500 mt-1 mr-2"></i>
                                <span>Avoid unverified information</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-times text-red-500 mt-1 mr-2"></i>
                                <span>Don't use excessive formatting</span>
                            </li>
                        </ul>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize CKEditor
        let editor;
        ClassicEditor
            .create(document.querySelector('#content'), {
                toolbar: {
                    items: [
                        'heading',
                        '|',
                        'bold',
                        'italic',
                        'underline',
                        'strikethrough',
                        '|',
                        'fontSize',
                        'fontColor',
                        'fontBackgroundColor',
                        '|',
                        'alignment',
                        '|',
                        'numberedList',
                        'bulletedList',
                        '|',
                        'indent',
                        'outdent',
                        '|',
                        'link',
                        'blockQuote',
                        'insertTable',
                        'mediaEmbed',
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
                fontSize: {
                    options: [
                        10,
                        12,
                        14,
                        'default',
                        18,
                        20,
                        22
                    ]
                },
                table: {
                    contentToolbar: [
                        'tableColumn',
                        'tableRow',
                        'mergeTableCells'
                    ]
                },
                mediaEmbed: {
                    previewsInData: true
                }
            })
            .then(editorInstance => {
                editor = editorInstance;
                console.log('CKEditor initialized successfully');
            })
            .catch(error => {
                console.error('Error initializing CKEditor:', error);
            });

        function saveDraft() {
            // Change form action to save draft
            document.getElementById('formAction').value = 'save_draft';

            // Validate required fields
            const title = document.getElementById('title').value.trim();
            const type = document.getElementById('type').value;

            if (!title) {
                alert('Please enter a post title.');
                document.getElementById('title').focus();
                return;
            }

            if (!type) {
                alert('Please select a post type.');
                document.getElementById('type').focus();
                return;
            }

            // Get content from CKEditor
            let content = '';
            if (editor) {
                content = editor.getData().trim();
            } else {
                // Fallback to textarea if editor is not initialized
                content = document.getElementById('content').value.trim();
            }

            if (!content) {
                alert('Please enter some content.');
                if (editor) {
                    editor.focus();
                } else {
                    document.getElementById('content').focus();
                }
                return;
            }

            // Update the textarea with CKEditor content before submitting
            if (editor) {
                document.getElementById('content').value = editor.getData();
            }

            // Disable the save draft button to prevent double submission
            const saveDraftBtn = event.target;
            const originalText = saveDraftBtn.innerHTML;
            saveDraftBtn.disabled = true;
            saveDraftBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

            // Submit the form
            document.querySelector('form').submit();
        }

        function prepareFormSubmission() {
            // Get content from CKEditor
            let content = '';
            if (editor) {
                content = editor.getData().trim();
            } else {
                // Fallback to textarea if editor is not initialized
                content = document.getElementById('content').value.trim();
            }

            // Update the textarea with CKEditor content before form submission
            if (editor) {
                document.getElementById('content').value = editor.getData();
            }
        }

        function previewImage(event) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            const file = event.target.files[0];
            if (file) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.className = 'image-preview-auto-fit';
                img.onload = function() { URL.revokeObjectURL(img.src); };
                preview.appendChild(img);
            }
        }

        function previewAdditionalImages(event) {
            const preview = document.getElementById('additionalImagesPreview');
            const files = event.target.files;
            
            // Clear previous preview
            preview.innerHTML = '';
            
            if (!files || files.length === 0) return;
            
            const container = document.createElement('div');
            container.className = 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2';
            
            Array.from(files).forEach((file, index) => {
                if (!file || !file.type.startsWith('image/')) return;
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = `Additional Image ${index + 1}`;
                img.className = 'w-full h-24 object-cover rounded border';
                img.onload = function() { URL.revokeObjectURL(img.src); };
                container.appendChild(img);
            });
            
            preview.appendChild(container);
        }

        // Function to delete an additional image
        function deleteImage(index, imageUrl) {
            if (confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
                // Hide the image container
                const container = document.getElementById(`image-container-${index}`);
                if (container) {
                    container.style.display = 'none';
                }
                
                // Add to deleted images list
                const deletedImagesInput = document.getElementById('deleted_images');
                let deletedImages = [];
                
                if (deletedImagesInput.value) {
                    try {
                        deletedImages = JSON.parse(deletedImagesInput.value);
                    } catch (e) {
                        deletedImages = [];
                    }
                }
                
                if (!deletedImages.includes(imageUrl)) {
                    deletedImages.push(imageUrl);
                    deletedImagesInput.value = JSON.stringify(deletedImages);
                }
                
                console.log('Image deleted:', imageUrl);
                console.log('Deleted images list:', deletedImages);
            }
        }

        // Function to open image modal
        function openImageModal(imageSrc, imageTitle) {
            // Remove any existing modal
            const existingModal = document.querySelector('.image-modal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Create modal HTML
            const modalHTML = `
                <div class="image-modal fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50" onclick="closeImageModal()">
                    <div class="relative w-full h-full flex items-center justify-center p-4" onclick="event.stopPropagation()">
                        <!-- Close button -->
                        <button onclick="closeImageModal()" class="absolute top-4 right-4 bg-white text-gray-800 rounded-full w-10 h-10 flex items-center justify-center hover:bg-gray-200 transition z-10">
                            <i class="fas fa-times"></i>
                        </button>
                        
                        <!-- Image container -->
                        <div class="max-w-4xl max-h-full flex flex-col items-center">
                            <div class="text-white text-center mb-4">
                                <h3 class="text-lg font-semibold">${imageTitle}</h3>
                            </div>
                            <img src="${imageSrc}" alt="${imageTitle}" class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl">
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Add keyboard listener for escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeImageModal();
                }
            });
        }
        
        // Function to close image modal
        function closeImageModal() {
            const modal = document.querySelector('.image-modal');
            if (modal) {
                modal.remove();
            }
        }
    </script>
</body>
</html>