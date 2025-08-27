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

// Get lesson ID and class ID from URL
$lesson_id = safe_decrypt_id($_GET['id']);
$class_id = safe_decrypt_id($_GET['class_id']);

if (!$lesson_id || !$class_id) {
    header('Location: class-management.php');
    exit();
}

// Verify the class belongs to the logged-in teacher
$class_query = "SELECT tc.*, cc.subject_title, cc.subject_code, cc.units, cc.description as subject_description
                FROM teacher_classes tc
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                WHERE tc.id = ? AND tc.teacher_id = ?";
$class_stmt = mysqli_prepare($conn, $class_query);
mysqli_stmt_bind_param($class_stmt, "ii", $class_id, $_SESSION['user_id']);
mysqli_stmt_execute($class_stmt);
$class_result = mysqli_stmt_get_result($class_stmt);
$class_data = mysqli_fetch_assoc($class_result);

if (!$class_data) {
    header('Location: class-management.php');
    exit();
}

// Get lesson details and verify it's assigned to this class
$lesson_query = "SELECT l.*, lca.class_id
                 FROM lessons l
                 JOIN lesson_class_assignments lca ON l.id = lca.lesson_id
                 WHERE l.id = ? AND lca.class_id = ? AND l.teacher_id = ?";
$lesson_stmt = mysqli_prepare($conn, $lesson_query);
mysqli_stmt_bind_param($lesson_stmt, "iii", $lesson_id, $class_id, $_SESSION['user_id']);
mysqli_stmt_execute($lesson_stmt);
$lesson_result = mysqli_stmt_get_result($lesson_stmt);

if (mysqli_num_rows($lesson_result) == 0) {
    header('Location: class_materials.php?class_id=' . encrypt_id($class_id) . '&message=' . urlencode('Lesson not found or not assigned to this class.') . '&type=error');
    exit();
}

$lesson = mysqli_fetch_assoc($lesson_result);

// Get related lessons for this class
$related_lessons_query = "SELECT l.id, l.title, l.order_number, l.status
                         FROM lessons l
                         JOIN lesson_class_assignments lca ON l.id = lca.lesson_id
                         WHERE lca.class_id = ? AND l.teacher_id = ? AND l.id != ?
                         ORDER BY l.order_number ASC, l.created_at ASC
                         LIMIT 5";
$related_stmt = mysqli_prepare($conn, $related_lessons_query);
mysqli_stmt_bind_param($related_stmt, "iii", $class_id, $_SESSION['user_id'], $lesson_id);
mysqli_stmt_execute($related_stmt);
$related_lessons = mysqli_stmt_get_result($related_stmt);

// Set page title
$page_title = 'View Lesson: ' . $lesson['title'];

// Set current page for sidebar active state (we're viewing a lesson within class materials)
$current_page = 'class_materials.php';

// Include the unified LMS header
$sidebar_context = 'lms';
include 'includes/lms_header.php';
?>

<style>
.lesson-content {
    line-height: 1.8;
    font-size: 1rem;
}

.lesson-content h1, .lesson-content h2, .lesson-content h3, .lesson-content h4, .lesson-content h5, .lesson-content h6 {
    color: #1f2937;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}

.lesson-content h1 { font-size: 1.875rem; }
.lesson-content h2 { font-size: 1.5rem; }
.lesson-content h3 { font-size: 1.25rem; }
.lesson-content h4 { font-size: 1.125rem; }

.lesson-content p {
    margin-bottom: 1rem;
    color: #374151;
}

.lesson-content ul, .lesson-content ol {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

.lesson-content li {
    margin-bottom: 0.5rem;
    color: #374151;
}

.lesson-content blockquote {
    border-left: 4px solid #f59e0b;
    padding-left: 1rem;
    margin: 1.5rem 0;
    font-style: italic;
    color: #6b7280;
    background-color: #fef3c7;
    padding: 1rem;
    border-radius: 0.5rem;
}

.lesson-content code {
    background-color: #f3f4f6;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
    color: #dc2626;
}

.lesson-content pre {
    background-color: #1f2937;
    color: #f9fafb;
    padding: 1rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin: 1rem 0;
}

.lesson-content pre code {
    background-color: transparent;
    color: inherit;
    padding: 0;
}

.lesson-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}

.lesson-content th, .lesson-content td {
    border: 1px solid #d1d5db;
    padding: 0.75rem;
    text-align: left;
}

.lesson-content th {
    background-color: #f9fafb;
    font-weight: 600;
    color: #374151;
}

.lesson-content img {
    max-width: 100%;
    height: auto;
    border-radius: 0.5rem;
    margin: 1rem 0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.lesson-content a {
    color: #f59e0b;
    text-decoration: none;
    border-bottom: 1px solid transparent;
    transition: border-color 0.2s;
}

.lesson-content a:hover {
    border-bottom-color: #f59e0b;
}

.progress-ring {
    transform: rotate(-90deg);
}

.progress-ring-circle {
    transition: stroke-dasharray 0.35s;
    transform-origin: 50% 50%;
}

.lesson-stats {
    background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
    color: white;
    border-radius: 1rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.lesson-stats h3 {
    color: white;
    margin-bottom: 1rem;
    font-size: 1.25rem;
    font-weight: 600;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
}

.stat-item {
    text-align: center;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    backdrop-filter: blur(10px);
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    opacity: 0.9;
}

.content-section {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.content-header {
    background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
    color: white;
    padding: 1.5rem;
    position: relative;
}

.content-header h2 {
    color: white;
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.content-header .header-icon {
    position: absolute;
    right: 1.5rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 2rem;
    opacity: 0.3;
}

.content-body {
    padding: 2rem;
}

.file-preview {
    border: 2px dashed #d1d5db;
    border-radius: 0.75rem;
    padding: 2rem;
    text-align: center;
    background: #f9fafb;
    transition: all 0.3s ease;
}

.file-preview:hover {
    border-color: #f59e0b;
    background: #fef3c7;
}

.file-icon {
    font-size: 3rem;
    color: #f59e0b;
    margin-bottom: 1rem;
}

.file-info {
    margin-bottom: 1.5rem;
}

.file-name {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.file-meta {
    color: #6b7280;
    font-size: 0.875rem;
}

.file-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.action-btn-primary {
    background: #f59e0b;
    color: white;
}

.action-btn-primary:hover {
    background: #d97706;
    transform: translateY(-1px);
}

.action-btn-secondary {
    background: #6b7280;
    color: white;
}

.action-btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
}

.related-lessons {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.related-header {
    background: #6b7280;
    color: white;
    padding: 1rem 1.5rem;
    font-weight: 600;
}

.related-list {
    padding: 0;
    margin: 0;
    list-style: none;
}

.related-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f3f4f6;
    transition: background-color 0.2s ease;
}

.related-item:last-child {
    border-bottom: none;
}

.related-item:hover {
    background-color: #f9fafb;
}

.related-item a {
    display: flex;
    align-items: center;
    text-decoration: none;
    color: #374151;
}

.related-item .lesson-number {
    background: #f59e0b;
    color: white;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    margin-right: 1rem;
    flex-shrink: 0;
}

.related-item .lesson-info {
    flex: 1;
}

.related-item .lesson-title {
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.related-item .lesson-status {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-weight: 500;
}

.related-item .lesson-status.published {
    background: #fef3c7;
    color: #92400e;
}

.related-item .lesson-status.draft {
    background: #f3f4f6;
    color: #6b7280;
}

.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0.75rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -1.5rem;
    top: 0.5rem;
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 50%;
    background: #f59e0b;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #f59e0b;
}

.timeline-content {
    background: white;
    border-radius: 0.5rem;
    padding: 1rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.timeline-date {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.timeline-title {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.25rem;
}

.timeline-description {
    font-size: 0.875rem;
    color: #6b7280;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .file-actions {
        flex-direction: column;
    }
    
    .action-btn {
        justify-content: center;
    }
}
</style>

<!-- Enhanced Header with Lesson Stats -->
<div class="lesson-stats">
    <h3><i class="fas fa-graduation-cap mr-2"></i>Lesson Overview</h3>
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-value"><?php echo $lesson['order_number'] ?: 'N/A'; ?></div>
            <div class="stat-label">Lesson Order</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?php echo ucfirst($lesson['lesson_type'] ?? 'Standard'); ?></div>
            <div class="stat-label">Type</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?php echo ucfirst($lesson['status']); ?></div>
            <div class="stat-label">Status</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?php echo $lesson['file_name'] ? 'Yes' : 'No'; ?></div>
            <div class="stat-label">Has Files</div>
        </div>
    </div>
</div>

<!-- Main Header -->
<div class="mb-6 sm:mb-8">
    <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start gap-4">
        <div class="flex-1">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-12 h-12 bg-seait-orange rounded-lg flex items-center justify-center">
                    <i class="fas fa-book-open text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark"><?php echo htmlspecialchars($lesson['title']); ?></h1>
                    <p class="text-sm sm:text-base text-gray-600">
                        <i class="fas fa-chalkboard-teacher mr-1"></i>
                        <?php echo htmlspecialchars($class_data['subject_title'] . ' - ' . $class_data['section']); ?>
                    </p>
                </div>
            </div>
            
            <?php if ($lesson['description']): ?>
            <div class="bg-gray-50 border-l-4 border-seait-orange p-4 rounded-r-lg">
                <p class="text-gray-700 italic"><?php echo htmlspecialchars($lesson['description']); ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="flex flex-wrap gap-2">
            <a href="edit-lesson.php?id=<?php echo encrypt_id($lesson_id); ?>" 
               class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition flex items-center">
                <i class="fas fa-edit mr-2"></i>Edit Lesson
            </a>
            <a href="class_materials.php?class_id=<?php echo encrypt_id($class_id); ?>" 
               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition flex items-center">
                <i class="fas fa-arrow-left mr-2"></i>Back to Materials
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-3 space-y-6">
        <!-- Lesson Content -->
        <div class="content-section">
            <div class="content-header">
                <h2><i class="fas fa-file-alt mr-2"></i>Lesson Content</h2>
                <i class="fas fa-book-open header-icon"></i>
            </div>
            <div class="content-body">
                <?php if ($lesson['content']): ?>
                    <div class="lesson-content">
                        <?php echo $lesson['content']; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-file-alt text-gray-300 text-4xl mb-4"></i>
                        <p class="text-gray-500 mb-4">No content available for this lesson.</p>
                        <a href="edit-lesson.php?id=<?php echo encrypt_id($lesson_id); ?>" 
                           class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                            <i class="fas fa-plus mr-2"></i>Add Content
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- File Attachment -->
        <?php if ($lesson['file_name']): ?>
        <div class="content-section">
            <div class="content-header">
                <h2><i class="fas fa-paperclip mr-2"></i>File Attachment</h2>
                <i class="fas fa-file header-icon"></i>
            </div>
            <div class="content-body">
                <div class="file-preview">
                    <div class="file-icon">
                        <i class="fas <?php echo getFileIconByExtension(pathinfo($lesson['file_name'], PATHINFO_EXTENSION)); ?>"></i>
                    </div>
                    <div class="file-info">
                        <div class="file-name"><?php echo htmlspecialchars($lesson['file_name']); ?></div>
                        <div class="file-meta">
                            <i class="fas fa-hdd mr-1"></i><?php echo formatFileSize($lesson['file_size'] ?? 0); ?> • 
                            <i class="fas fa-file mr-1"></i><?php echo strtoupper(pathinfo($lesson['file_name'], PATHINFO_EXTENSION)); ?>
                        </div>
                    </div>
                    <div class="file-actions">
                        <?php
                        $file_path = '../uploads/lessons/' . $lesson['file_name'];
                        $file_extension = strtolower(pathinfo($lesson['file_name'], PATHINFO_EXTENSION));
                        $can_display = in_array($file_extension, ['pdf', 'txt', 'jpg', 'jpeg', 'png', 'gif']);
                        ?>
                        <button onclick="viewFileContent('<?php echo htmlspecialchars($lesson['title']); ?>', '<?php echo $file_path; ?>', '<?php echo $can_display ? 'text' : 'file'; ?>', '<?php echo htmlspecialchars($lesson['file_name']); ?>')"
                                class="action-btn action-btn-primary">
                            <i class="fas fa-eye mr-2"></i>Preview
                        </button>
                        <a href="<?php echo $file_path; ?>" target="_blank" download
                           class="action-btn action-btn-secondary">
                            <i class="fas fa-download mr-2"></i>Download
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Lesson Timeline -->
        <div class="content-section">
            <div class="content-header">
                <h2><i class="fas fa-history mr-2"></i>Lesson Timeline</h2>
                <i class="fas fa-clock header-icon"></i>
            </div>
            <div class="content-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-date"><?php echo date('M j, Y g:i A', strtotime($lesson['created_at'])); ?></div>
                            <div class="timeline-title">Lesson Created</div>
                            <div class="timeline-description">Lesson was initially created and added to the class materials.</div>
                        </div>
                    </div>
                    
                    <?php if ($lesson['updated_at'] && $lesson['updated_at'] !== $lesson['created_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-date"><?php echo date('M j, Y g:i A', strtotime($lesson['updated_at'])); ?></div>
                            <div class="timeline-title">Lesson Updated</div>
                            <div class="timeline-description">Content or details were modified.</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-date">Current</div>
                            <div class="timeline-title">Status: <?php echo ucfirst($lesson['status']); ?></div>
                            <div class="timeline-description">
                                <?php if ($lesson['status'] === 'published'): ?>
                                    Lesson is published and available to students.
                                <?php else: ?>
                                    Lesson is in draft mode and not visible to students.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Lesson Information -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-gray-600 text-white">
                <h2 class="text-lg font-medium"><i class="fas fa-info-circle mr-2"></i>Lesson Information</h2>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Status</h3>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                        <?php
                        switch($lesson['status']) {
                            case 'published':
                                echo 'bg-seait-orange bg-opacity-10 text-seait-orange border border-seait-orange border-opacity-20';
                                break;
                            case 'draft':
                                echo 'bg-gray-100 text-gray-700 border border-gray-200';
                                break;
                            default:
                                echo 'bg-gray-100 text-gray-700 border border-gray-200';
                        }
                        ?>">
                        <i class="fas <?php echo $lesson['status'] === 'published' ? 'fa-check-circle' : 'fa-edit'; ?> mr-1"></i>
                        <?php echo ucfirst($lesson['status']); ?>
                    </span>
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Type</h3>
                    <p class="text-sm text-gray-900 flex items-center">
                        <i class="fas fa-tag mr-2 text-seait-orange"></i>
                        <?php echo ucfirst($lesson['lesson_type'] ?? 'Standard'); ?>
                    </p>
                </div>

                <?php if ($lesson['order_number']): ?>
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Order</h3>
                    <p class="text-sm text-gray-900 flex items-center">
                        <i class="fas fa-sort-numeric-up mr-2 text-seait-orange"></i>
                        Lesson <?php echo $lesson['order_number']; ?>
                    </p>
                </div>
                <?php endif; ?>

                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Created</h3>
                    <p class="text-sm text-gray-900 flex items-center">
                        <i class="fas fa-calendar-plus mr-2 text-seait-orange"></i>
                        <?php echo date('M j, Y g:i A', strtotime($lesson['created_at'])); ?>
                    </p>
                </div>

                <?php if ($lesson['updated_at'] && $lesson['updated_at'] !== $lesson['created_at']): ?>
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Last Updated</h3>
                    <p class="text-sm text-gray-900 flex items-center">
                        <i class="fas fa-calendar-check mr-2 text-seait-orange"></i>
                        <?php echo date('M j, Y g:i A', strtotime($lesson['updated_at'])); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-gray-600 text-white">
                <h2 class="text-lg font-medium"><i class="fas fa-bolt mr-2"></i>Quick Actions</h2>
            </div>
            <div class="p-6 space-y-3">
                <a href="edit-lesson.php?id=<?php echo encrypt_id($lesson_id); ?>" 
                   class="flex items-center p-3 rounded-lg hover:bg-seait-orange hover:text-white transition-colors text-seait-orange">
                    <i class="fas fa-edit mr-3"></i>
                    <span>Edit Lesson</span>
                </a>
                <a href="create-lesson.php?reuse_id=<?php echo encrypt_id($lesson_id); ?>" 
                   class="flex items-center p-3 rounded-lg hover:bg-gray-600 hover:text-white transition-colors text-gray-600">
                    <i class="fas fa-copy mr-3"></i>
                    <span>Reuse Lesson</span>
                </a>
                <a href="class_materials.php?class_id=<?php echo encrypt_id($class_id); ?>" 
                   class="flex items-center p-3 rounded-lg hover:bg-gray-600 hover:text-white transition-colors text-gray-600">
                    <i class="fas fa-list mr-3"></i>
                    <span>Back to Materials</span>
                </a>
            </div>
        </div>

        <!-- Related Lessons -->
        <?php if (mysqli_num_rows($related_lessons) > 0): ?>
        <div class="related-lessons">
            <div class="related-header">
                <i class="fas fa-link mr-2"></i>Related Lessons
            </div>
            <ul class="related-list">
                <?php while ($related = mysqli_fetch_assoc($related_lessons)): ?>
                <li class="related-item">
                    <a href="view-class-lesson.php?id=<?php echo encrypt_id($related['id']); ?>&class_id=<?php echo encrypt_id($class_id); ?>">
                        <div class="lesson-number"><?php echo $related['order_number'] ?: '?'; ?></div>
                        <div class="lesson-info">
                            <div class="lesson-title"><?php echo htmlspecialchars($related['title']); ?></div>
                            <span class="lesson-status <?php echo $related['status']; ?>">
                                <?php echo ucfirst($related['status']); ?>
                            </span>
                        </div>
                    </a>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
        <?php endif; ?>
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
                modalBody.innerHTML = `<div class="text-center py-8"><p class="text-gray-700 mb-2">Error reading file content:</p><p class="text-gray-600">${error.message}</p><p class="text-sm text-gray-500 mt-2">The file may be too large or not accessible.</p></div>`;
                contentLoading.classList.add('hidden');
            });
    } else if (displayType === 'image') {
        const img = new Image();
        img.onload = function() {
            modalBody.innerHTML = `<div class="text-center"><img src="${filePath}" alt="${title}" style="max-width: 100%; height: auto; border-radius: 0.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);"></div>`;
            contentLoading.classList.add('hidden');
        };
        img.onerror = function() {
            modalBody.innerHTML = `<div class="text-center py-8"><p class="text-gray-700">Error loading image</p><p class="text-sm text-gray-500 mt-2">The image file may be corrupted or not accessible.</p></div>`;
            contentLoading.classList.add('hidden');
        };
        img.src = filePath;
    } else if (displayType === 'pdf') {
        // Load PDF using PDF.js
        pdfjsLib.getDocument(filePath).promise.then(function(pdf) {
            const numPages = pdf.numPages;
            let pdfContent = '';
            
            // Load first few pages for preview
            const pagesToLoad = Math.min(3, numPages);
            
            for (let pageNum = 1; pageNum <= pagesToLoad; pageNum++) {
                pdf.getPage(pageNum).then(function(page) {
                    const viewport = page.getViewport({scale: 1.0});
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    
                    const renderContext = {
                        canvasContext: context,
                        viewport: viewport
                    };
                    
                    page.render(renderContext).promise.then(function() {
                        pdfContent += `<div class="mb-4"><canvas style="max-width: 100%; height: auto; border: 1px solid #e5e7eb; border-radius: 0.5rem;"></canvas></div>`;
                        modalBody.innerHTML = pdfContent;
                        contentLoading.classList.add('hidden');
                    });
                });
            }
        }).catch(function(error) {
            modalBody.innerHTML = `<div class="text-center py-8"><p class="text-gray-700 mb-2">Error loading PDF:</p><p class="text-gray-600">${error.message}</p><p class="text-sm text-gray-500 mt-2">The PDF file may be corrupted or not accessible.</p></div>`;
            contentLoading.classList.add('hidden');
        });
    } else {
        modalBody.innerHTML = `<div class="text-center py-8"><p class="text-gray-600">This file type cannot be previewed.</p><p class="text-sm text-gray-500 mt-2">Please download the file to view its contents.</p></div>`;
        contentLoading.classList.add('hidden');
    }
}

function closeViewContentModal() {
    document.getElementById('viewContentModal').classList.add('hidden');
}

// Add smooth scrolling for better UX
document.addEventListener('DOMContentLoaded', function() {
    // Add scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observe all content sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(section);
    });
});
</script>

<?php
// Include the unified footer
include 'includes/footer.php';
?>
