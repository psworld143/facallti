<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../config/database.php';
require_once '../includes/functions.php';

check_content_creator();

// Ensure upload directory exists and has proper permissions
function ensure_upload_directory($path) {
    if (!is_dir($path)) {
        if (!mkdir($path, 0755, true)) {
            error_log("Failed to create directory: " . $path);
            return false;
        }
    }
    
    // Check if directory is writable (don't try to change permissions on macOS)
    return is_writable($path);
}

// Upload directory permissions have been configured for XAMPP on macOS
// The assets/images/news directory is now owned by the daemon user with proper permissions

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Detect oversized POST (when PHP discards body, $_POST becomes empty)
    if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
        $message = display_message('Upload too large. Please reduce file sizes or increase post_max_size and upload_max_filesize in php.ini, then restart Apache.', 'error');
    } else {
    $title = isset($_POST['title']) ? sanitize_input($_POST['title']) : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    $type = isset($_POST['type']) ? sanitize_input($_POST['type']) : '';

    // Server-side required validation
    if (trim($title) === '' || trim($type) === '' || trim($content) === '') {
        $message = display_message('Please complete Title, Type, and Content before submitting.', 'error');
        } else {
    // Handle image upload
    $image_url = NULL;
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/images/news/';
                
                // Ensure upload directory exists and is writable
                if (!ensure_upload_directory($upload_dir)) {
                    $message = display_message('Error: Could not create or access upload directory. Please check permissions.', 'error');
                } else {
        $file_tmp = $_FILES['post_image']['tmp_name'];
        $file_name = basename($_FILES['post_image']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($file_ext, $allowed_exts)) {
            $new_name = uniqid('news_', true) . '.' . $file_ext;
            $dest_path = $upload_dir . $new_name;
            if (move_uploaded_file($file_tmp, $dest_path)) {
                $image_url = 'assets/images/news/' . $new_name;
                        } else {
                            error_log("Failed to move uploaded file from {$file_tmp} to {$dest_path}");
                            $message = display_message('Error uploading main image. Please try again.', 'error');
                        }
            }
        }
    }

    // Determine status based on action
    if (isset($_POST['action']) && $_POST['action'] === 'save_draft') {
        $status = 'draft';
                $success_message = 'Draft saved successfully!';
    } else {
        $status = 'pending'; // All posts start as pending for approval
                $success_message = 'Post created successfully and submitted for approval!';
    }
    
    // Handle additional images upload
    $additional_images_urls = [];
    if (isset($_FILES['additional_images']) && is_array($_FILES['additional_images']['name'])) {
        $upload_dir = '../assets/images/news/';
        $max_images = 5; // Maximum 5 additional images
        
                // Ensure upload directory exists and is writable
                if (!ensure_upload_directory($upload_dir)) {
                    $message = display_message('Error: Could not create or access upload directory. Please check permissions.', 'error');
                } else {
        for ($i = 0; $i < count($_FILES['additional_images']['name']) && $i < $max_images; $i++) {
            // Check if file was uploaded successfully
            if (isset($_FILES['additional_images']['error'][$i]) && $_FILES['additional_images']['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['additional_images']['tmp_name'][$i];
                $file_name = basename($_FILES['additional_images']['name'][$i]);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_ext, $allowed_exts)) {
                    $new_name = uniqid('additional_' . $i . '_', true) . '.' . $file_ext;
                    $dest_path = $upload_dir . $new_name;
                    
                    // Check if file is actually uploaded
                                if (is_uploaded_file($file_tmp)) {
                                    if (move_uploaded_file($file_tmp, $dest_path)) {
                        $additional_images_urls[] = 'assets/images/news/' . $new_name;
                                    } else {
                                        error_log("Failed to move uploaded file from {$file_tmp} to {$dest_path}");
                                        $message = display_message('Error uploading additional image ' . ($i + 1) . '. Please try again.', 'error');
                                    }
                                }
                    }
                }
            }
        }
    }
    
    // Convert array to JSON string for database storage
    $additional_images_json = !empty($additional_images_urls) ? json_encode($additional_images_urls) : NULL;
    
            // Only proceed with database insertion if no errors occurred
            if (empty($message)) {
                // Check if additional_image_url column exists, if not create it
                $check_column = "SHOW COLUMNS FROM posts LIKE 'additional_image_url'";
                $column_result = mysqli_query($conn, $check_column);
                
                if (!$column_result) {
                    error_log("Error checking column: " . mysqli_error($conn));
                    $message = display_message('Database error. Please contact administrator.', 'error');
                } else {
                    if (mysqli_num_rows($column_result) == 0) {
                        // Column doesn't exist, create it
                        $alter_table = "ALTER TABLE posts ADD COLUMN additional_image_url TEXT NULL AFTER image_url";
                        if (!mysqli_query($conn, $alter_table)) {
                            error_log("Error creating column: " . mysqli_error($conn));
                            $message = display_message('Database error. Please contact administrator.', 'error');
                        } else {
                            error_log("Created additional_image_url column in posts table");
                        }
                    }
                    
                    // Proceed with database insertion
                    if (empty($message)) {
    $query = "INSERT INTO posts (title, content, type, status, author_id, image_url, additional_image_url) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
                        
                        if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ssssiss", $title, $content, $type, $status, $_SESSION['user_id'], $image_url, $additional_images_json);

    if (mysqli_stmt_execute($stmt)) {
                                $message = display_message($success_message, 'success');
    } else {
                                $message = display_message('Error creating post: ' . mysqli_stmt_error($stmt), 'error');
                            }
                            mysqli_stmt_close($stmt);
                        } else {
                            $message = display_message('Error preparing database statement: ' . mysqli_error($conn), 'error');
                        }
                    }
                }
            }
        }
    }
}
?>

<?php
$page_title = 'Create Post';
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

        /* Preview styling to match news-detail.php exactly */
        .prose {
            max-width: none;
        }
        .prose h1, .prose h2, .prose h3, .prose h4, .prose h5, .prose h6 {
            color: #2C3E50;
            font-weight: 600;
            margin-top: 1.5em;
            margin-bottom: 0.5em;
        }
        .prose h1 { font-size: 2.5rem; }
        .prose h2 { font-size: 2rem; }
        .prose h3 { font-size: 1.75rem; }
        .prose h4 { font-size: 1.5rem; }
        .prose p {
            margin-bottom: 1.5em;
            line-height: 1.8;
            font-size: 1.1rem;
        }
        .prose ul, .prose ol {
            margin-bottom: 1.5em;
            padding-left: 1.5em;
        }
        .prose li {
            margin-bottom: 0.75em;
            line-height: 1.7;
        }
        .prose blockquote {
            border-left: 4px solid #FF6B35;
            padding-left: 1.5rem;
            margin: 2rem 0;
            font-style: italic;
            color: #6B7280;
            font-size: 1.1rem;
        }
        .prose table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
        }
        .prose table th, .prose table td {
            border: 1px solid #E5E7EB;
            padding: 1rem;
            text-align: left;
        }
        .prose table th {
            background-color: #F9FAFB;
            font-weight: 600;
        }
        .prose a {
            color: #FF6B35;
            text-decoration: underline;
        }
        .prose a:hover {
            color: #EA580C;
        }
        .prose img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 2rem 0;
        }
        .prose code {
            background-color: #F3F4F6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .prose pre {
            background-color: #1F2937;
            color: #F9FAFB;
            padding: 1.5rem;
            border-radius: 8px;
            overflow-x: auto;
            margin: 2rem 0;
        }

        /* Enhanced image styles for news detail - Landscape */
        .article-header-image {
            background-size: cover !important;
            background-position: center center !important;
            background-repeat: no-repeat !important;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            image-rendering: high-quality;
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            transform: translateZ(0);
            -webkit-transform: translateZ(0);
            aspect-ratio: 16/9 !important;
            width: 100% !important;
        }

        /* Responsive landscape image sizing */
        @media (min-width: 768px) {
            .article-header-image {
                aspect-ratio: 21/9 !important;
                min-height: 60vh !important;
            }
        }

        @media (min-width: 1024px) {
            .article-header-image {
                aspect-ratio: 21/9 !important;
                min-height: 70vh !important;
            }
        }

        @media (min-width: 1280px) {
            .article-header-image {
                aspect-ratio: 21/9 !important;
                min-height: 80vh !important;
            }
        }
    </style>
                <div class="mb-8">
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Create New Post</h1>
                    <p class="text-gray-600">Create content for the SEAIT website with rich text editing</p>
                </div>

                <!-- Information Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 lg:p-6 mb-6 lg:mb-8">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 mb-2">Post Creation</h3>
                            <div class="text-sm text-blue-700 space-y-1">
                                <p><strong>Content Types:</strong> Create different types of posts including news, announcements, hiring notices, events, and articles.</p>
                                <p><strong>Rich Text Editor:</strong> Use the built-in editor to format your content with headings, bold text, lists, and more.</p>
                                <p><strong>Review Process:</strong> All posts are submitted for review by the Social Media Manager before publication.</p>
                                <p><strong>Drafts:</strong> Save your work as drafts to continue editing later without losing your progress.</p>
                            </div>
                        </div>
                    </div>
                </div>

            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $message; ?>
                    <?php if (strpos($message, 'Draft saved') !== false): ?>
                        <div class="mt-2">
                            <a href="drafts.php" class="text-green-800 underline hover:text-green-900">View your drafts</a> |
                            <a href="create-post.php" class="text-green-800 underline hover:text-green-900">Create another post</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="bg-white rounded-lg shadow-md mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6">
                        <button onclick="switchTab('creation')" id="creationTab" class="py-4 px-1 border-b-2 font-medium text-sm border-seait-orange text-seait-orange">
                            <i class="fas fa-edit mr-2"></i>Content Creation
                        </button>
                        <button onclick="switchTab('preview')" id="previewTab" class="py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            <i class="fas fa-eye mr-2"></i>Preview
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Content Creation Tab -->
            <div id="creationContent" class="bg-white rounded-lg shadow-md p-6">
                <form method="POST" class="space-y-6" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="submit">

                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                            Post Title *
                        </label>
                        <input type="text" id="title" name="title" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                               placeholder="Enter post title"
                               oninput="updatePreview()">
                    </div>

                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                            Post Type *
                        </label>
                        <select id="type" name="type" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                                onchange="updatePreview()">
                            <option value="">Select post type</option>
                            <option value="news">News</option>
                            <option value="announcement">Announcement</option>
                            <option value="hiring">Hiring</option>
                            <option value="event">Event</option>
                            <option value="article">Article</option>
                        </select>
                    </div>

                    <div>
                        <label for="post_image" class="block text-sm font-medium text-gray-700 mb-2">
                            Post Main Image
                        </label>
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
                                   placeholder="Write your content here..."
                                   oninput="updatePreview()"></textarea>
                     </div>

                                           <div>
                          <label for="additional_images" class="block text-sm font-medium text-gray-700 mb-2">
                              Additional Images
                          </label>
                          <input type="file" id="additional_images" name="additional_images[]" accept="image/*" multiple
                                 class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                                 onchange="previewAdditionalImages(event)">
                          <div id="additionalImagesPreview" class="mt-2"></div>
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
                                    <p>Your post will be submitted for review by the Social Media Manager.
                                    Once approved, it will be published on the website.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4">
                        <button type="submit" onclick="prepareFormSubmission()"
                                class="bg-seait-orange text-white px-4 sm:px-6 py-2 rounded-md hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:ring-offset-2 transition">
                            <i class="fas fa-paper-plane mr-2"></i><span class="text-sm sm:text-base">Submit for Review</span>
                        </button>
                        <button type="button" onclick="saveDraft()"
                                class="bg-gray-500 text-white px-4 sm:px-6 py-2 rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition">
                            <i class="fas fa-save mr-2"></i><span class="text-sm sm:text-base">Save as Draft</span>
                        </button>
                        <a href="dashboard.php"
                           class="bg-gray-300 text-gray-700 px-4 sm:px-6 py-2 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 transition text-center">
                            <i class="fas fa-times mr-2"></i><span class="text-sm sm:text-base">Cancel</span>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Preview Tab -->
            <div id="previewContent" class="bg-white rounded-lg shadow-md p-6 hidden">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-seait-dark">Live Preview</h3>
                </div>
                
                <!-- Preview Container -->
                <div id="previewContainer" class="border border-gray-200 rounded-lg p-6 bg-gray-50">
                    <!-- Preview content will be populated here -->
                </div>
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
                        'insertImage',
                        'imageUpload',
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
                },
                image: {
                    toolbar: [
                        'imageTextAlternative',
                        'imageStyle:full',
                        'imageStyle:side'
                    ]
                }
            })
            .then(editorInstance => {
                editor = editorInstance;
                console.log('CKEditor initialized successfully');
                
                // Add real-time preview updates for CKEditor
                editor.model.document.on('change:data', () => {
                    console.log('CKEditor content changed, updating preview...');
                    updatePreview();
                });
                
                // Also listen for specific events like typing, formatting, etc.
                editor.editing.view.document.on('input', () => {
                    console.log('CKEditor input detected, updating preview...');
                    updatePreview();
                });
                
                // Listen for selection changes that might indicate content updates
                editor.model.document.selection.on('change:range', () => {
                    // Debounce the preview update to avoid too many updates
                    clearTimeout(editor.previewUpdateTimeout);
                    editor.previewUpdateTimeout = setTimeout(() => {
                        updatePreview();
                    }, 500); // Update preview after 500ms of no changes
                });
            })
            .catch(error => {
                console.error('Error initializing CKEditor:', error);
            });

        // Set up a more frequent update interval for CKEditor content changes
        setInterval(() => {
            if (editor && editor.getData) {
                const currentContent = editor.getData();
                if (currentContent !== editor.lastKnownContent) {
                    console.log('CKEditor content changed via interval check, updating preview...');
                    editor.lastKnownContent = currentContent;
                    updatePreview();
                }
            }
        }, 1000); // Check every second for content changes

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
                img.onload = function() { 
                    console.log('Main image loaded, updating preview...');
                    updatePreview(); // Update preview when main image changes
                };
                preview.appendChild(img);
                console.log('Main image preview created:', img);
                
                // Store the file reference for the preview
                img.dataset.file = file;
            }
        }

        function previewAdditionalImages(event) {
            const preview = document.getElementById('additionalImagesPreview');
            const files = event.target.files;
            
            console.log('previewAdditionalImages called with files:', files);
            console.log('Number of files:', files ? files.length : 0);
            
            // Clear previous preview
            preview.innerHTML = '';
            
            if (!files || files.length === 0) {
                console.log('No files selected, clearing preview');
                updatePreview(); // Update preview to show empty state
                return;
            }
            
            const container = document.createElement('div');
            container.className = 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2';
            
            let loadedImages = 0;
            const totalImages = files.length;
            
            Array.from(files).forEach((file, index) => {
                if (!file || !file.type.startsWith('image/')) {
                    console.log('Skipping non-image file:', file);
                    return;
                }
                
                console.log('Processing image file:', file.name, file.type);
                
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = `Additional Image ${index + 1}`;
                img.className = 'w-full h-24 object-cover rounded border';
                
                img.onload = function() { 
                    console.log('Additional image loaded:', img.src);
                    loadedImages++;
                    
                    // Update preview when all images are loaded
                    if (loadedImages === totalImages) {
                        console.log('All additional images loaded, updating preview...');
                        updatePreview();
                    }
                };
                
                img.onerror = function() {
                    console.error('Failed to load image:', img.src);
                    loadedImages++;
                    
                    // Update preview even if some images fail
                    if (loadedImages === totalImages) {
                        console.log('All additional images processed, updating preview...');
                        updatePreview();
                    }
                };
                
                container.appendChild(img);
                
                // Store the file reference for the preview
                img.dataset.file = file;
            });
            
            preview.appendChild(container);
            console.log('Additional images preview container added to DOM');
            
            // If no images were processed, update preview immediately
            if (totalImages === 0) {
                updatePreview();
            }
        }

        // Tab switching functionality
        function switchTab(tabName) {
            const creationTab = document.getElementById('creationTab');
            const previewTab = document.getElementById('previewTab');
            const creationContent = document.getElementById('creationContent');
            const previewContent = document.getElementById('previewContent');

            if (tabName === 'creation') {
                creationTab.className = 'py-4 px-1 border-b-2 font-medium text-sm border-seait-orange text-seait-orange';
                previewTab.className = 'py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';
                creationContent.classList.remove('hidden');
                previewContent.classList.add('hidden');
            } else if (tabName === 'preview') {
                creationTab.className = 'py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';
                previewTab.className = 'py-4 px-1 border-b-2 font-medium text-sm border-seait-orange text-seait-orange';
                creationContent.classList.add('hidden');
                previewContent.classList.remove('hidden');
                updatePreview(); // Update preview when switching to preview tab
            }
        }

        // Update preview in real-time
        function updatePreview() {
            const title = document.getElementById('title').value;
            const type = document.getElementById('type').value;
            
            // Get content from CKEditor if available, otherwise from textarea
            let content = '';
            if (editor && editor.getData) {
                content = editor.getData();
                console.log('Content from CKEditor:', content.substring(0, 100) + '...');
            } else {
                content = document.getElementById('content').value;
                console.log('Content from textarea:', content.substring(0, 100) + '...');
            }
            
            const previewContainer = document.getElementById('previewContainer');
            
            // Get main image preview
            const mainImagePreview = document.getElementById('imagePreview');
            let mainImageHtml = '';
            const mainImg = mainImagePreview.querySelector('img');
            console.log('Main image found:', mainImg ? 'Yes' : 'No', mainImg);
            console.log('Main image preview element:', mainImagePreview);
            console.log('Main image preview HTML:', mainImagePreview.innerHTML);
            
            if (mainImg && mainImg.src) {
                console.log('Main image src:', mainImg.src);
                mainImageHtml = `
                    <div class="mb-6">
                        <img src="${mainImg.src}" alt="${title || 'Post Image'}" 
                             class="w-full h-64 object-cover rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200">
                    </div>
                `;
                console.log('Main image HTML generated:', mainImageHtml);
            } else {
                console.log('No main image to display');
                // Show placeholder for main image area
                mainImageHtml = `
                    <div class="mb-6">
                        <div class="w-full h-64 bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center">
                            <div class="text-center text-gray-500">
                                <i class="fas fa-image text-4xl mb-2"></i>
                                <p class="text-sm">Main image will appear here</p>
                                <p class="text-xs">Upload an image to see preview</p>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Get additional images preview
            const additionalImagesPreview = document.getElementById('additionalImagesPreview');
            const additionalImages = additionalImagesPreview.querySelectorAll('img');
            console.log('Additional images found:', additionalImages.length, additionalImages);
            console.log('Additional images preview element:', additionalImagesPreview);
            console.log('Additional images preview HTML:', additionalImagesPreview.innerHTML);
            
            // Convert NodeList to Array for easier manipulation
            const additionalImagesArray = Array.from(additionalImages);
            console.log('Additional images array:', additionalImagesArray);
            console.log('Additional images array length:', additionalImagesArray.length);

            // Create preview HTML exactly matching news-detail.php
            const previewHtml = `
                <div class="max-w-7xl mx-auto">
                    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                        <!-- Article Header - Exactly like news-detail.php -->
                        <div class="article-header-image text-white relative" style="min-height:400px; min-height:50vh; ${mainImg ? `background: linear-gradient(rgba(44,62,80,0.3), rgba(44,62,80,0.3)), url('${mainImg.src}') center center / cover no-repeat;` : 'background: linear-gradient(to right, #FF6B35, #EA580C);'}">
                            <div class="absolute left-0 bottom-0 z-10 max-w-3xl w-full p-4 md:p-6">
                                <div class="flex items-center space-x-4 mb-2">
                                    <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                                        ${type || 'Post Type'}
                                    </span>
                                    <span class="text-xs opacity-90">
                                        ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                                    </span>
                                </div>
                                <h1 class="text-xl md:text-2xl lg:text-3xl font-bold mb-1">${title || 'Post Title'}</h1>
                                <p class="text-sm md:text-base opacity-90 mb-0">
                                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Article Content - Exactly like news-detail.php -->
                        <div class="p-8 md:p-12">
                            <div class="prose prose-lg md:prose-xl max-w-none">
                                ${content || '<p class="text-gray-500 italic">Start typing your content to see the preview...</p>'}
                            </div>
                            
                            <!-- Additional Images Section - Exactly like news-detail.php -->
                            ${additionalImagesArray.length > 0 ? `
                                <div class="mt-8 border-t border-gray-200 pt-8">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-xl font-semibold text-seait-dark">Additional Images</h3>
                                        <div class="bg-seait-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2">
                                            <i class="fas fa-images"></i>
                                            <span>View All Images (${additionalImagesArray.length})</span>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                                        ${additionalImagesArray.map((img, index) => `
                                            <div class="relative group">
                                                <img src="${img.src}" 
                                                     alt="${title || 'Post Title'} - Image ${index + 1}" 
                                                     class="w-full h-40 object-cover rounded-lg shadow-md transition-all duration-200 hover:shadow-lg border-2 border-gray-100">
                                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-200 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-image text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200 text-lg"></i>
                                                </div>
                                                <div class="absolute bottom-2 left-2 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded">
                                                    ${(title || 'Post Title').substring(0, 20)}${(title || 'Post Title').length > 20 ? '...' : ''}
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            ` : `
                                <div class="mt-8 border-t border-gray-200 pt-8">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-xl font-semibold text-seait-dark">Additional Images</h3>
                                        <div class="text-gray-500 text-sm">
                                            <i class="fas fa-info-circle"></i>
                                            <span>No additional images available</span>
                                        </div>
                                    </div>
                                    <div class="text-center py-8 text-gray-500">
                                        <i class="fas fa-images text-4xl mb-4"></i>
                                        <p>No additional images for this post</p>
                                    </div>
                                </div>
                            `}
                        </div>
                    </div>
                </div>
            `;

            previewContainer.innerHTML = previewHtml;
        }

        // Refresh preview manually
        function refreshPreview() {
            updatePreview();
        }

        // Force update preview (useful for CKEditor content)
        function forceUpdatePreview() {
            console.log('Force updating preview...');
            if (editor && editor.getData) {
                console.log('Current CKEditor content:', editor.getData());
            }
            updatePreview();
        }

        // Debug additional images function
        function debugAdditionalImages() {
            console.log('=== DEBUG ADDITIONAL IMAGES ===');
            const additionalImagesPreview = document.getElementById('additionalImagesPreview');
            console.log('Additional images preview element:', additionalImagesPreview);
            console.log('Additional images preview HTML:', additionalImagesPreview.innerHTML);
            
            const additionalImages = additionalImagesPreview.querySelectorAll('img');
            console.log('Additional images found:', additionalImages.length);
            console.log('Additional images:', additionalImages);
            
            Array.from(additionalImages).forEach((img, index) => {
                console.log(`Image ${index + 1}:`, {
                    src: img.src,
                    alt: img.alt,
                    className: img.className,
                    width: img.width,
                    height: img.height,
                    naturalWidth: img.naturalWidth,
                    naturalHeight: img.naturalHeight
                });
            });
            
            console.log('=== END DEBUG ===');
        }

        // Initialize preview on page load
        document.addEventListener('DOMContentLoaded', function() {
            updatePreview();
        });

        // Cleanup function to revoke blob URLs when no longer needed
        function cleanupBlobUrls() {
            const mainImg = document.querySelector('#imagePreview img');
            const additionalImgs = document.querySelectorAll('#additionalImagesPreview img');
            
            if (mainImg && mainImg.src.startsWith('blob:')) {
                URL.revokeObjectURL(mainImg.src);
            }
            
            additionalImgs.forEach(img => {
                if (img.src.startsWith('blob:')) {
                    URL.revokeObjectURL(img.src);
                }
            });
        }

        // Cleanup blob URLs when leaving the page
        window.addEventListener('beforeunload', cleanupBlobUrls);
    </script>
</body>
</html>