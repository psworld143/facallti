<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Get lesson ID from URL
$lesson_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$lesson_id) {
    header('Location: lessons.php');
    exit();
}

// Get lesson details with class assignments
$lesson_query = "SELECT l.*,
                 GROUP_CONCAT(CONCAT(cc.subject_code, ' - ', cc.subject_title, ' (', tc.section, ')') SEPARATOR ', ') as assigned_classes,
                 COUNT(lca.class_id) as class_count
                 FROM lessons l
                 LEFT JOIN lesson_class_assignments lca ON l.id = lca.lesson_id
                 LEFT JOIN teacher_classes tc ON lca.class_id = tc.id
                 LEFT JOIN course_curriculum cc ON tc.subject_id = cc.id
                 WHERE l.id = ? AND l.teacher_id = ?
                 GROUP BY l.id";
$lesson_stmt = mysqli_prepare($conn, $lesson_query);
mysqli_stmt_bind_param($lesson_stmt, "ii", $lesson_id, $_SESSION['user_id']);
mysqli_stmt_execute($lesson_stmt);
$lesson_result = mysqli_stmt_get_result($lesson_stmt);

if (mysqli_num_rows($lesson_result) == 0) {
    header('Location: lessons.php?message=' . urlencode('Lesson not found or access denied.') . '&type=error');
    exit();
}

$lesson = mysqli_fetch_assoc($lesson_result);

// Set page title
$page_title = 'View Lesson: ' . $lesson['title'];

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2"><?php echo htmlspecialchars($lesson['title']); ?></h1>
            <p class="text-sm sm:text-base text-gray-600">Lesson Details</p>
        </div>
        <div class="flex space-x-2">
            <a href="edit-lesson.php?id=<?php echo $lesson_id; ?>" class="bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                <i class="fas fa-edit mr-2"></i>Edit Lesson
            </a>
        <a href="lessons.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Lessons
        </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Lesson Content -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Lesson Content</h2>
            </div>
            <div class="p-6">
                <?php if ($lesson['content']): ?>
                    <div class="prose max-w-none">
                        <?php echo $lesson['content']; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 italic">No content available for this lesson.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- File Attachment -->
        <?php if ($lesson['file_name']): ?>
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">File Attachment</h2>
            </div>
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas <?php echo getFileIconByExtension(pathinfo($lesson['file_name'], PATHINFO_EXTENSION)); ?> text-gray-600"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($lesson['file_name']); ?></h3>
                            <p class="text-xs text-gray-500">
                                <?php echo $lesson['file_type']; ?> •
                                <?php echo number_format($lesson['file_size'] / 1024, 1); ?> KB
                            </p>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <?php
                        // Check if file content can be displayed
                        $file_extension = pathinfo($lesson['file_name'], PATHINFO_EXTENSION);
                        $can_display = canDisplayContent($lesson['file_type'], $file_extension);
                        if ($can_display):
                        ?>
                        <button onclick="viewFileContent('<?php echo htmlspecialchars($lesson['title']); ?>', '<?php echo $lesson['file_path']; ?>', '<?php echo $can_display; ?>', '<?php echo htmlspecialchars($lesson['file_name']); ?>')"
                                class="bg-green-600 text-white px-3 py-1 rounded-md hover:bg-green-700 transition text-sm">
                            <i class="fas fa-eye mr-1"></i>Preview
                        </button>
                        <?php endif; ?>
                    <a href="<?php echo $lesson['file_path']; ?>" target="_blank"
                       class="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-blue-700 transition text-sm">
                        <i class="fas fa-download mr-1"></i>Download
                    </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Lesson Information -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Lesson Information</h2>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <p class="text-sm text-gray-900"><?php echo $lesson['description'] ? htmlspecialchars($lesson['description']) : 'No description provided'; ?></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lesson Type</label>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        <?php echo ucfirst($lesson['lesson_type']); ?>
                    </span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $lesson['status'] === 'published' ? 'bg-green-100 text-green-800' : ($lesson['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                        <?php echo ucfirst($lesson['status']); ?>
                    </span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Order Number</label>
                    <p class="text-sm text-gray-900"><?php echo $lesson['order_number']; ?></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Created</label>
                    <p class="text-sm text-gray-900"><?php echo date('F j, Y \a\t g:i A', strtotime($lesson['created_at'])); ?></p>
                </div>

                <?php if ($lesson['updated_at']): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Updated</label>
                    <p class="text-sm text-gray-900"><?php echo date('F j, Y \a\t g:i A', strtotime($lesson['updated_at'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Class Assignments -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Class Assignments</h2>
            </div>
            <div class="p-6">
                <?php if ($lesson['assigned_classes']): ?>
                    <div class="space-y-2">
                        <?php
                        $classes = explode(', ', $lesson['assigned_classes']);
                        foreach ($classes as $class):
                        ?>
                        <div class="flex items-center">
                            <div class="w-2 h-2 bg-seait-orange rounded-full mr-3"></div>
                            <span class="text-sm text-gray-700"><?php echo htmlspecialchars($class); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-xs text-gray-500 mt-3">Assigned to <?php echo $lesson['class_count']; ?> class(es)</p>
                <?php else: ?>
                    <p class="text-sm text-gray-500 italic">No classes assigned to this lesson.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Quick Actions</h2>
            </div>
            <div class="p-6 space-y-3">
                <a href="edit-lesson.php?id=<?php echo $lesson_id; ?>" class="flex items-center text-seait-orange hover:text-orange-600">
                    <i class="fas fa-edit mr-2"></i>
                    <span>Edit Lesson</span>
                </a>
                <a href="create-lesson.php?reuse_id=<?php echo $lesson_id; ?>" class="flex items-center text-purple-600 hover:text-purple-800">
                    <i class="fas fa-copy mr-2"></i>
                    <span>Reuse Lesson</span>
                </a>
                <a href="lessons.php" class="flex items-center text-gray-600 hover:text-gray-800">
                    <i class="fas fa-list mr-2"></i>
                    <span>Back to Lessons</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- View File Content Modal -->
<div id="viewContentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-5/6 lg:w-4/5 xl:w-3/4 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900" id="contentModalTitle">View File Content</h3>
                <button onclick="closeViewContentModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div id="contentModalBody" class="max-h-screen overflow-y-auto">
                <!-- Loading indicator -->
                <div id="contentLoading" class="hidden text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-seait-orange"></div>
                    <p class="mt-2 text-gray-600">Loading content...</p>
                </div>
                <!-- Content will be loaded here -->
            </div>

            <div class="flex justify-end pt-4">
                <button onclick="closeViewContentModal()"
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- PDF.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
// Set PDF.js worker path
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
</script>

<script>
function viewFileContent(title, filePath, displayType, fileName) {
    const modalBody = document.getElementById('contentModalBody');
    const modalTitle = document.getElementById('contentModalTitle');
    const contentLoading = document.getElementById('contentLoading');
    modalTitle.textContent = `View File Content: ${title}`;
    contentLoading.classList.remove('hidden');
    modalBody.innerHTML = ''; // Clear previous content

    // Show modal first
    document.getElementById('viewContentModal').classList.remove('hidden');

    if (displayType === 'text') {
        fetch(filePath)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(text => {
                modalBody.innerHTML = `<pre style="white-space: pre-wrap; word-wrap: break-word; font-family: monospace; background: #f8f9fa; padding: 1rem; border-radius: 0.5rem; max-height: 70vh; overflow-y: auto;">${text}</pre>`;
                contentLoading.classList.add('hidden');
            })
            .catch(error => {
                modalBody.innerHTML = `<div class="text-center py-8"><p class="text-red-600 mb-2">Error reading file content:</p><p class="text-gray-600">${error.message}</p><p class="text-sm text-gray-500 mt-2">The file may be too large or not accessible.</p></div>`;
                contentLoading.classList.add('hidden');
            });
    } else if (displayType === 'image') {
        const img = new Image();
        img.onload = function() {
            modalBody.innerHTML = `<div class="text-center"><img src="${filePath}" alt="${title}" style="max-width: 100%; height: auto; border-radius: 0.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);"></div>`;
            contentLoading.classList.add('hidden');
        };
        img.onerror = function() {
            modalBody.innerHTML = `<div class="text-center py-8"><p class="text-red-600">Error loading image</p><p class="text-sm text-gray-500 mt-2">The image file may be corrupted or not accessible.</p></div>`;
            contentLoading.classList.add('hidden');
        };
        img.src = filePath;
    } else if (displayType === 'video') {
        modalBody.innerHTML = `<div class="text-center"><video controls style="max-width: 100%; height: auto; border-radius: 0.5rem;"><source src="${filePath}" type="video/mp4">Your browser does not support the video tag.</video></div>`;
        contentLoading.classList.add('hidden');
    } else if (displayType === 'audio') {
        modalBody.innerHTML = `<div class="text-center"><audio controls style="width: 100%;"><source src="${filePath}" type="audio/mpeg">Your browser does not support the audio tag.</audio></div>`;
        contentLoading.classList.add('hidden');
    } else if (displayType === 'pdf') {
        // Use PDF.js for better PDF viewing
        modalBody.innerHTML = `
            <div class="text-center">
                <div class="mb-4 flex justify-center space-x-2">
                    <button onclick="loadPdfWithPdfJs('${filePath}')" class="px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition">
                        <i class="fas fa-eye mr-2"></i>Enhanced PDF Viewer
                    </button>
                    <a href="${filePath}" target="_blank" class="px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 transition">
                        <i class="fas fa-external-link-alt mr-2"></i>Open in New Tab
                    </a>
                </div>
                <div id="pdfViewer" class="border rounded-lg">
                    <iframe src="${filePath}#toolbar=1&navpanes=1&scrollbar=1" style="width: 100%; height: 70vh; border: none; border-radius: 0.5rem;"></iframe>
                </div>
            </div>`;
        contentLoading.classList.add('hidden');
    } else if (displayType === 'document' || displayType === 'spreadsheet' || displayType === 'presentation') {
        // For Office documents, try multiple viewer options
        const fullUrl = window.location.origin + '/' + filePath.replace('../', '');
        const encodedUrl = encodeURIComponent(fullUrl);

        // Multiple viewer options
        const officeViewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodedUrl}`;
        const googleViewerUrl = `https://docs.google.com/viewer?url=${encodedUrl}&embedded=true`;
        const office365ViewerUrl = `https://view.officeapps.live.com/op/view.aspx?src=${encodedUrl}`;

        modalBody.innerHTML = `
            <div class="text-center">
                <div class="mb-4 flex flex-wrap justify-center gap-2">
                    <button onclick="switchToOfficeViewer('${officeViewerUrl}')" class="px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition">
                        <i class="fas fa-file-word mr-2"></i>Microsoft Viewer
                    </button>
                    <button onclick="switchToGoogleViewer('${googleViewerUrl}')" class="px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 transition">
                        <i class="fab fa-google mr-2"></i>Google Viewer
                    </button>
                    <button onclick="switchToOffice365Viewer('${office365ViewerUrl}')" class="px-3 py-2 bg-purple-600 text-white text-sm rounded-md hover:bg-purple-700 transition">
                        <i class="fas fa-external-link-alt mr-2"></i>Office 365
                    </button>
                    <a href="${filePath}" target="_blank" class="px-3 py-2 bg-orange-600 text-white text-sm rounded-md hover:bg-orange-700 transition">
                        <i class="fas fa-download mr-2"></i>Download
                    </a>
                    <button onclick="openFileDirectly('${filePath}')" class="px-3 py-2 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 transition">
                        <i class="fas fa-external-link-alt mr-2"></i>Open Directly
                    </button>
                </div>
                <div id="documentViewer" class="border rounded-lg">
                    <div id="documentLoading" class="text-center py-8">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-seait-orange"></div>
                        <p class="mt-2 text-gray-600">Loading Microsoft Office viewer...</p>
                        <p class="text-xs text-gray-500 mt-2">If this doesn't work, try the other viewer options above.</p>
                    </div>
                    <iframe id="documentIframe" src="${officeViewerUrl}" style="width: 100%; height: 70vh; border: none; border-radius: 0.5rem; display: none;" frameborder="0" onload="hideDocumentLoading()" onerror="showViewerError()"></iframe>
                </div>
                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-2">If you see an error message, try the other viewer options or download the file.</p>
                    <p class="text-xs text-gray-500">Note: Office document viewers may not work with all file types or configurations.</p>
                    <div id="viewerError" class="hidden mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-700 mb-2"><i class="fas fa-exclamation-triangle mr-2"></i>Viewer Error</p>
                        <p class="text-xs text-red-600">The document viewer encountered an error. Please try:</p>
                        <ul class="text-xs text-red-600 mt-1 ml-4 list-disc">
                            <li>Switching to a different viewer option</li>
                            <li>Downloading the file to view locally</li>
                            <li>Opening the file directly in a new tab</li>
                        </ul>
                    </div>
                </div>
            </div>`;
        contentLoading.classList.add('hidden');

        // Set a timeout to show the iframe even if onload doesn't fire
        setTimeout(function() {
            const iframe = document.getElementById('documentIframe');
            const loading = document.getElementById('documentLoading');
            if (iframe && loading) {
                loading.style.display = 'none';
                iframe.style.display = 'block';
            }
        }, 8000); // 8 second timeout
    } else {
        modalBody.innerHTML = `<div class="text-center py-8"><p class="text-gray-600">No specific viewer available for this file type.</p><p class="text-sm text-gray-500 mt-2">File: ${fileName}</p><p class="text-sm text-gray-500">You can <a href="${filePath}" target="_blank" class="text-blue-600 hover:text-blue-800">download the file</a> to view it in your preferred application.</p></div>`;
        contentLoading.classList.add('hidden');
    }
}

function closeViewContentModal() {
    document.getElementById('viewContentModal').classList.add('hidden');
    document.getElementById('contentModalBody').innerHTML = ''; // Clear content
    document.getElementById('contentLoading').classList.add('hidden'); // Hide loading indicator
}

function loadPdfWithPdfJs(pdfUrl) {
    const pdfViewer = document.getElementById('pdfViewer');
    const contentLoading = document.getElementById('contentLoading');

    contentLoading.classList.remove('hidden');
    pdfViewer.innerHTML = '';

    // Load PDF using PDF.js
    pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
        const numPages = pdf.numPages;
        let currentPage = 1;

        function renderPage(pageNum) {
            pdf.getPage(pageNum).then(function(page) {
                const viewport = page.getViewport({scale: 1.5});
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };

                page.render(renderContext).promise.then(function() {
                    pdfViewer.innerHTML = `
                        <div class="text-center">
                            <div class="mb-4 flex justify-center items-center space-x-4">
                                <button onclick="changePage(${pageNum - 1})" ${pageNum <= 1 ? 'disabled' : ''} class="px-3 py-2 bg-gray-600 text-white text-sm rounded-md hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-chevron-left mr-2"></i>Previous
                                </button>
                                <span class="text-sm text-gray-600">Page ${pageNum} of ${numPages}</span>
                                <button onclick="changePage(${pageNum + 1})" ${pageNum >= numPages ? 'disabled' : ''} class="px-3 py-2 bg-gray-600 text-white text-sm rounded-md hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                    Next<i class="fas fa-chevron-right ml-2"></i>
                                </button>
                            </div>
                            <div class="border rounded-lg p-4 bg-white">
                                <canvas id="pdfCanvas" style="max-width: 100%; height: auto;"></canvas>
                            </div>
                        </div>`;

                    const canvasContainer = pdfViewer.querySelector('#pdfCanvas');
                    canvasContainer.appendChild(canvas);
                    contentLoading.classList.add('hidden');
                });
            });
        }

        // Store PDF and current page globally for navigation
        window.currentPdf = pdf;
        window.currentPage = 1;
        window.totalPages = numPages;
        window.renderPage = renderPage;

        renderPage(1);
    }).catch(function(error) {
        pdfViewer.innerHTML = `<div class="text-center py-8"><p class="text-red-600">Error loading PDF: ${error.message}</p></div>`;
        contentLoading.classList.add('hidden');
    });
}

function changePage(newPage) {
    if (window.currentPdf && newPage >= 1 && newPage <= window.totalPages) {
        window.currentPage = newPage;
        window.renderPage(newPage);
    }
}

function switchToOfficeViewer(url) {
    const documentIframe = document.getElementById('documentIframe');
    const contentLoading = document.getElementById('documentLoading');
    const viewerError = document.getElementById('viewerError');

    if (documentIframe) {
        contentLoading.classList.remove('hidden');
        documentIframe.style.display = 'none';
        viewerError.classList.add('hidden'); // Hide previous error

        documentIframe.onload = function() {
            contentLoading.classList.add('hidden');
            documentIframe.style.display = 'block';
        };

        documentIframe.onerror = function() {
            contentLoading.classList.add('hidden');
            documentIframe.style.display = 'block';
            viewerError.classList.remove('hidden'); // Show error
            alert('Error loading Microsoft viewer. Please try the Google viewer or download the file.');
        };

        documentIframe.src = url;
    }
}

function switchToGoogleViewer(url) {
    const documentIframe = document.getElementById('documentIframe');
    const contentLoading = document.getElementById('documentLoading');
    const viewerError = document.getElementById('viewerError');

    if (documentIframe) {
        contentLoading.classList.remove('hidden');
        documentIframe.style.display = 'none';
        viewerError.classList.add('hidden'); // Hide previous error

        documentIframe.onload = function() {
            contentLoading.classList.add('hidden');
            documentIframe.style.display = 'block';
        };

        documentIframe.onerror = function() {
            contentLoading.classList.add('hidden');
            documentIframe.style.display = 'block';
            viewerError.classList.remove('hidden'); // Show error
            alert('Error loading Google viewer. Please try the Microsoft viewer or download the file.');
        };

        documentIframe.src = url;
    }
}

function switchToOffice365Viewer(url) {
    const documentIframe = document.getElementById('documentIframe');
    const contentLoading = document.getElementById('documentLoading');
    const viewerError = document.getElementById('viewerError');

    if (documentIframe) {
        contentLoading.classList.remove('hidden');
        documentIframe.style.display = 'none';
        viewerError.classList.add('hidden'); // Hide previous error

        documentIframe.onload = function() {
            contentLoading.classList.add('hidden');
            documentIframe.style.display = 'block';
        };

        documentIframe.onerror = function() {
            contentLoading.classList.add('hidden');
            documentIframe.style.display = 'block';
            viewerError.classList.remove('hidden'); // Show error
            alert('Error loading Office 365 viewer. Please try the Microsoft viewer or download the file.');
        };

        documentIframe.src = url;
    }
}

function openFileDirectly(filePath) {
    window.open(filePath, '_blank');
}

function hideDocumentLoading() {
    const documentLoading = document.getElementById('documentLoading');
    const documentIframe = document.getElementById('documentIframe');

    if (documentLoading) {
        documentLoading.style.display = 'none';
    }
    if (documentIframe) {
        documentIframe.style.display = 'block';
    }
}

function showViewerError() {
    const viewerError = document.getElementById('viewerError');
    if (viewerError) {
        viewerError.classList.remove('hidden');
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    const viewContentModal = document.getElementById('viewContentModal');

    if (event.target === viewContentModal) {
        closeViewContentModal();
    }
}
</script>

<?php
// Helper functions
function getFileIconByExtension($file_extension) {
    $extension = strtolower($file_extension);

    switch ($extension) {
        case 'pdf':
            return 'fa-file-pdf';
        case 'doc':
        case 'docx':
        case 'rtf':
            return 'fa-file-word';
        case 'ppt':
        case 'pptx':
            return 'fa-file-powerpoint';
        case 'xls':
        case 'xlsx':
        case 'csv':
            return 'fa-file-excel';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'bmp':
        case 'svg':
        case 'webp':
        case 'tiff':
        case 'ico':
            return 'fa-file-image';
        case 'mp4':
        case 'avi':
        case 'mov':
        case 'wmv':
        case 'flv':
        case 'webm':
        case 'mkv':
        case '3gp':
        case 'm4v':
            return 'fa-file-video';
        case 'mp3':
        case 'wav':
        case 'ogg':
        case 'aac':
        case 'flac':
        case 'm4a':
        case 'wma':
            return 'fa-file-audio';
        case 'zip':
        case 'rar':
        case '7z':
        case 'tar':
        case 'gz':
            return 'fa-file-archive';
        case 'txt':
        case 'md':
        case 'html':
        case 'htm':
        case 'css':
        case 'js':
        case 'json':
        case 'xml':
        case 'log':
            return 'fa-file-alt';
        default:
            return 'fa-file';
    }
}

function canDisplayContent($file_type, $file_extension) {
    $displayable_types = [
        'pdf' => ['pdf'],
        'text' => ['txt', 'md', 'html', 'htm', 'css', 'js', 'json', 'xml', 'csv', 'log'],
        'document' => ['doc', 'docx', 'rtf'],
        'spreadsheet' => ['xls', 'xlsx', 'csv'],
        'presentation' => ['ppt', 'pptx'],
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'tiff', 'ico'],
        'video' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', '3gp', 'm4v'],
        'audio' => ['mp3', 'wav', 'ogg', 'aac', 'flac', 'm4a', 'wma']
    ];

    $file_extension = strtolower($file_extension);

    foreach ($displayable_types as $type => $extensions) {
        if (in_array($file_extension, $extensions)) {
            return $type;
        }
    }

    return false;
}

include 'includes/footer.php';
?>