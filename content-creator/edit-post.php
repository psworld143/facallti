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

    $query = "UPDATE posts SET title = ?, content = ?, type = ?, status = ?, image_url = ?, updated_at = NOW() WHERE id = ? AND author_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssssssi", $title, $content, $type, $status, $image_url, $post_id, $_SESSION['user_id']);

    if (mysqli_stmt_execute($stmt)) {
        // Update the post data for display
        $post['title'] = $title;
        $post['content'] = $content;
        $post['type'] = $type;
        $post['status'] = $status;
        $post['image_url'] = $image_url;
    } else {
        $message = display_message('Error updating post. Please try again.', 'error');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post - SEAIT Content Creator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50'
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
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
    <!-- CKEditor CDN -->
    <script src="https://cdn.ckeditor.com/ckeditor5/40.1.0/classic/ckeditor.js"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white fixed top-0 left-0 right-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-10 w-auto">
                    <div>
                        <h1 class="text-xl font-bold text-seait-dark">SEAIT Content Creator</h1>
                        <p class="text-sm text-gray-600">Welcome, <?php echo $_SESSION['first_name']; ?></p>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-seait-dark hover:text-seait-orange transition">
                        <i class="fas fa-home mr-2"></i><span class="hidden sm:inline">View Site</span>
                    </a>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">
                        <i class="fas fa-sign-out-alt mr-2"></i><span class="hidden sm:inline">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex min-h-screen pt-16">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 lg:ml-72 overflow-y-auto h-screen">
            <div class="p-4 lg:p-8">
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
    </script>
</body>
</html>