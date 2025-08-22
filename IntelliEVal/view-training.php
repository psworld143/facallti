<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../index.php');
    exit();
}

// Get training ID
$training_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$training_id) {
    header('Location: trainings.php');
    exit();
}

// Get training details
$training_query = "SELECT ts.*,
                  tc.name as category_name,
                  mec.name as main_category_name,
                  esc.name as sub_category_name,
                  u.first_name, u.last_name
                  FROM trainings_seminars ts
                  LEFT JOIN training_categories tc ON ts.category_id = tc.id
                  LEFT JOIN main_evaluation_categories mec ON ts.main_category_id = mec.id
                  LEFT JOIN evaluation_sub_categories esc ON ts.sub_category_id = esc.id
                  LEFT JOIN users u ON ts.created_by = u.id
                  WHERE ts.id = ?";

$stmt = mysqli_prepare($conn, $training_query);
mysqli_stmt_bind_param($stmt, "i", $training_id);
mysqli_stmt_execute($stmt);
$training_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($training_result) == 0) {
    header('Location: trainings.php');
    exit();
}

$training = mysqli_fetch_assoc($training_result);

// Get participants
$participants_query = "SELECT tr.*, u.first_name, u.last_name, u.email
                      FROM training_registrations tr
                      JOIN users u ON tr.user_id = u.id
                      WHERE tr.training_id = ?
                      ORDER BY tr.registration_date DESC";

$stmt = mysqli_prepare($conn, $participants_query);
mysqli_stmt_bind_param($stmt, "i", $training_id);
mysqli_stmt_execute($stmt);
$participants_result = mysqli_stmt_get_result($stmt);

// Set page title
$page_title = 'View Training: ' . $training['title'];

// Include the shared header
include 'includes/header.php';
?>

<!-- Enhanced Training View Styles -->
<style>
/* Enhanced Training View Styles */
.training-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
}

.training-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
}

.training-hero-content {
    position: relative;
    z-index: 1;
}

.training-status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.training-status-badge.published {
    background-color: rgba(34, 197, 94, 0.9);
    color: white;
}

.training-status-badge.draft {
    background-color: rgba(107, 114, 128, 0.9);
    color: white;
}

.training-status-badge.ongoing {
    background-color: rgba(59, 130, 246, 0.9);
    color: white;
}

.training-status-badge.completed {
    background-color: rgba(147, 51, 234, 0.9);
    color: white;
}

.training-status-badge.cancelled {
    background-color: rgba(239, 68, 68, 0.9);
    color: white;
}

.training-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.training-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.training-card-header {
    border-bottom: 2px solid #f3f4f6;
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
}

.training-card-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
    display: flex;
    align-items: center;
}

.training-card-header h3 i {
    margin-right: 0.75rem;
    color: #667eea;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.info-item {
    background: #f8fafc;
    border-radius: 8px;
    padding: 1rem;
    border-left: 4px solid #667eea;
}

.info-item h4 {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.info-item p {
    font-size: 0.875rem;
    font-weight: 500;
    color: #1f2937;
}

.feature-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
}

.feature-badge.enabled {
    background-color: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.feature-badge.disabled {
    background-color: #f3f4f6;
    color: #6b7280;
    border: 1px solid #e5e7eb;
}

.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
}

.stats-number {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.stats-label {
    font-size: 0.875rem;
    opacity: 0.9;
}

.participant-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
}

.participants-table {
    width: 100%;
    border-collapse: collapse;
}

.participants-table th {
    background: #f8fafc;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
}

.participants-table td {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.participants-table tr:hover {
    background-color: #f9fafb;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6b7280;
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state-text {
    font-size: 1.125rem;
    font-weight: 500;
}

.btn-modern {
    display: inline-flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
}

.btn-modern.primary {
    background: linear-gradient(135deg, #FF6B35 0%, #ea580c 100%);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(255, 107, 53, 0.3);
}

.btn-modern.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px -1px rgba(255, 107, 53, 0.4);
}

.btn-modern.secondary {
    background: #6b7280;
    color: white;
}

.btn-modern.secondary:hover {
    background: #4b5563;
    transform: translateY(-2px);
}

.btn-modern.info {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
}

.btn-modern.info:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px -1px rgba(59, 130, 246, 0.4);
}

.btn-modern i {
    margin-right: 0.5rem;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background-color: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 0.5rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981 0%, #059669 100%);
    border-radius: 4px;
    transition: width 0.3s ease;
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
    left: -1.25rem;
    top: 0.25rem;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #667eea;
    border: 3px solid white;
    box-shadow: 0 0 0 2px #e5e7eb;
}

.timeline-item.completed::before {
    background: #10b981;
}

.timeline-item.current::before {
    background: #f59e0b;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@media (max-width: 768px) {
    .training-hero {
        padding: 1.5rem;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .stats-card {
        padding: 1rem;
    }

    .stats-number {
        font-size: 1.5rem;
    }
}
</style>

<!-- Enhanced Header Section -->
<div class="training-hero">
    <div class="training-hero-content">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div class="flex-1">
                <div class="flex items-center mb-4">
                    <span class="training-status-badge <?php echo $training['status']; ?>">
                        <i class="fas fa-circle mr-2"></i>
                        <?php echo ucfirst($training['status']); ?>
                    </span>
                </div>
                <h1 class="text-3xl lg:text-4xl font-bold mb-2"><?php echo htmlspecialchars($training['title']); ?></h1>
                <p class="text-lg opacity-90"><?php echo htmlspecialchars($training['category_name'] ?? 'General Training'); ?></p>
            </div>
            <div class="mt-6 lg:mt-0 lg:ml-8">
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="edit-training.php?id=<?php echo $training_id; ?>" class="btn-modern primary">
                        <i class="fas fa-edit"></i>Edit Training
                    </a>
                    <a href="trainings.php" class="btn-modern secondary">
                        <i class="fas fa-arrow-left"></i>Back to Trainings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Training Details -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Description Card -->
        <div class="training-card">
            <div class="training-card-header">
                <h3><i class="fas fa-info-circle"></i>Description</h3>
            </div>
            <div class="prose max-w-none">
                <p class="text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($training['description'])); ?></p>
            </div>
        </div>

        <!-- Training Information -->
        <div class="training-card">
            <div class="training-card-header">
                <h3><i class="fas fa-calendar-alt"></i>Training Information</h3>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <h4>Type</h4>
                    <p><?php echo ucfirst($training['type']); ?></p>
                </div>
                <div class="info-item">
                    <h4>Category</h4>
                    <p><?php echo htmlspecialchars($training['category_name'] ?? 'N/A'); ?></p>
                </div>
                <div class="info-item">
                    <h4>Duration</h4>
                    <p><?php echo $training['duration_hours'] ? $training['duration_hours'] . ' hours' : 'N/A'; ?></p>
                </div>
                <div class="info-item">
                    <h4>Venue</h4>
                    <p><?php echo htmlspecialchars($training['venue'] ?? 'TBD'); ?></p>
                </div>
                <div class="info-item">
                    <h4>Start Date</h4>
                    <p><?php echo date('M d, Y H:i', strtotime($training['start_date'])); ?></p>
                </div>
                <div class="info-item">
                    <h4>End Date</h4>
                    <p><?php echo date('M d, Y H:i', strtotime($training['end_date'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Linked Evaluation Categories -->
        <?php if ($training['main_category_name'] || $training['sub_category_name']): ?>
        <div class="training-card">
            <div class="training-card-header">
                <h3><i class="fas fa-link"></i>Linked Evaluation Categories</h3>
            </div>
            <div class="info-grid">
                <?php if ($training['main_category_name']): ?>
                <div class="info-item">
                    <h4>Main Category</h4>
                    <p><?php echo htmlspecialchars($training['main_category_name']); ?></p>
                </div>
                <?php endif; ?>

                <?php if ($training['sub_category_name']): ?>
                <div class="info-item">
                    <h4>Sub Category</h4>
                    <p><?php echo htmlspecialchars($training['sub_category_name']); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Features and Cost -->
        <div class="training-card">
            <div class="training-card-header">
                <h3><i class="fas fa-cogs"></i>Features & Cost</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Training Features</h4>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <span class="feature-badge <?php echo $training['is_mandatory'] ? 'enabled' : 'disabled'; ?>">
                                <i class="fas <?php echo $training['is_mandatory'] ? 'fa-check' : 'fa-times'; ?> mr-2"></i>
                                Mandatory Training
                            </span>
                        </div>
                        <div class="flex items-center">
                            <span class="feature-badge <?php echo $training['certificate_provided'] ? 'enabled' : 'disabled'; ?>">
                                <i class="fas <?php echo $training['certificate_provided'] ? 'fa-check' : 'fa-times'; ?> mr-2"></i>
                                Certificate Provided
                            </span>
                        </div>
                        <div class="flex items-center">
                            <span class="feature-badge <?php echo $training['materials_provided'] ? 'enabled' : 'disabled'; ?>">
                                <i class="fas <?php echo $training['materials_provided'] ? 'fa-check' : 'fa-times'; ?> mr-2"></i>
                                Materials Provided
                            </span>
                        </div>
                    </div>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Cost Information</h4>
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4">
                        <div class="text-2xl font-bold text-green-600">₱<?php echo number_format($training['cost'], 2); ?></div>
                        <div class="text-sm text-green-700">Training Cost</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Quick Stats -->
        <div class="stats-card">
            <div class="stats-number"><?php echo mysqli_num_rows($participants_result); ?></div>
            <div class="stats-label">Registered Participants</div>
            <?php if ($training['max_participants']): ?>
            <div class="mt-4">
                <div class="flex justify-between text-sm opacity-90 mb-2">
                    <span>Capacity</span>
                    <span><?php echo $training['max_participants']; ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min(100, (mysqli_num_rows($participants_result) / $training['max_participants']) * 100); ?>%"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Created By -->
        <div class="training-card">
            <div class="training-card-header">
                <h3><i class="fas fa-user"></i>Created By</h3>
            </div>
            <div class="flex items-center">
                <div class="participant-avatar mr-4">
                    <?php echo strtoupper(substr($training['first_name'], 0, 1) . substr($training['last_name'], 0, 1)); ?>
                </div>
                <div>
                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($training['first_name'] . ' ' . $training['last_name']); ?></p>
                    <p class="text-sm text-gray-500">Guidance Officer</p>
                </div>
            </div>
        </div>

        <!-- Registration Timeline -->
        <div class="training-card">
            <div class="training-card-header">
                <h3><i class="fas fa-clock"></i>Timeline</h3>
            </div>
            <div class="timeline">
                <?php if ($training['registration_deadline']): ?>
                <div class="timeline-item <?php echo strtotime($training['registration_deadline']) < time() ? 'completed' : 'current'; ?>">
                    <div class="text-sm font-medium text-gray-900">Registration Deadline</div>
                    <div class="text-xs text-gray-500"><?php echo date('M d, Y H:i', strtotime($training['registration_deadline'])); ?></div>
                </div>
                <?php endif; ?>

                <div class="timeline-item <?php echo strtotime($training['start_date']) < time() ? 'completed' : 'current'; ?>">
                    <div class="text-sm font-medium text-gray-900">Training Start</div>
                    <div class="text-xs text-gray-500"><?php echo date('M d, Y H:i', strtotime($training['start_date'])); ?></div>
                </div>

                <div class="timeline-item <?php echo strtotime($training['end_date']) < time() ? 'completed' : ''; ?>">
                    <div class="text-sm font-medium text-gray-900">Training End</div>
                    <div class="text-xs text-gray-500"><?php echo date('M d, Y H:i', strtotime($training['end_date'])); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Participants Section -->
<div class="mt-8">
    <div class="training-card">
        <div class="training-card-header">
            <div class="flex justify-between items-center">
                <h3><i class="fas fa-users"></i>Participants</h3>
                <a href="training-participants.php?id=<?php echo $training_id; ?>" class="btn-modern info">
                    <i class="fas fa-cog"></i>Manage Participants
                </a>
            </div>
        </div>

        <?php if (mysqli_num_rows($participants_result) > 0): ?>
        <div class="overflow-x-auto">
            <table class="participants-table">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Email</th>
                        <th>Registration Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($participant = mysqli_fetch_assoc($participants_result)): ?>
                    <tr>
                        <td>
                            <div class="flex items-center">
                                <div class="participant-avatar mr-3">
                                    <?php echo strtoupper(substr($participant['first_name'], 0, 1) . substr($participant['last_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($participant['last_name'] . ', ' . $participant['first_name']); ?>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="text-sm text-gray-900">
                            <?php echo htmlspecialchars($participant['email']); ?>
                        </td>
                        <td class="text-sm text-gray-900">
                            <?php echo date('M d, Y', strtotime($participant['registration_date'])); ?>
                        </td>
                        <td>
                            <span class="training-status-badge <?php echo $participant['status']; ?>">
                                <?php echo ucfirst($participant['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="view-participant.php?id=<?php echo $participant['id']; ?>"
                               class="text-blue-600 hover:text-blue-800 transition-colors">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-users empty-state-icon"></i>
            <p class="empty-state-text">No participants registered yet.</p>
            <p class="text-sm text-gray-500 mt-2">Participants will appear here once they register for this training.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>