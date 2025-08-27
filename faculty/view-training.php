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

// Get training ID
$training_id = safe_decrypt_id($_GET['id']);

if (!$training_id) {
    $_SESSION['message'] = 'Invalid training ID provided.';
    $_SESSION['message_type'] = 'error';
    header('Location: evaluation-results.php');
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
                  WHERE ts.id = ? AND ts.status = 'published'";

$stmt = mysqli_prepare($conn, $training_query);
mysqli_stmt_bind_param($stmt, "i", $training_id);
mysqli_stmt_execute($stmt);
$training_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($training_result) == 0) {
    $_SESSION['message'] = 'Training not found or not available.';
    $_SESSION['message_type'] = 'error';
    header('Location: evaluation-results.php');
    exit();
}

$training = mysqli_fetch_assoc($training_result);

// Check if faculty is already registered
$registration_query = "SELECT * FROM training_registrations
                      WHERE training_id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $registration_query);
mysqli_stmt_bind_param($stmt, "ii", $training_id, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$registration_result = mysqli_stmt_get_result($stmt);
$is_registered = mysqli_num_rows($registration_result) > 0;
$registration_data = $is_registered ? mysqli_fetch_assoc($registration_result) : null;

// Get current participants count
$participants_count_query = "SELECT COUNT(*) as count FROM training_registrations
                            WHERE training_id = ? AND status = 'registered'";
$stmt = mysqli_prepare($conn, $participants_count_query);
mysqli_stmt_bind_param($stmt, "i", $training_id);
mysqli_stmt_execute($stmt);
$participants_count_result = mysqli_stmt_get_result($stmt);
$participants_count = mysqli_fetch_assoc($participants_count_result)['count'];

// Check if registration is still open
$registration_deadline_passed = false;
if ($training['registration_deadline']) {
    $registration_deadline_passed = strtotime($training['registration_deadline']) < time();
}

$training_started = strtotime($training['start_date']) < time();
$training_ended = strtotime($training['end_date']) < time();

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    if (!$is_registered && !$registration_deadline_passed && !$training_started) {
        // Check if there are available spots
        if (!$training['max_participants'] || $participants_count < $training['max_participants']) {
            $insert_query = "INSERT INTO training_registrations (training_id, user_id, registration_date, status)
                           VALUES (?, ?, NOW(), 'registered')";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "ii", $training_id, $_SESSION['user_id']);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = 'Successfully registered for the training!';
                $_SESSION['message_type'] = 'success';
                header('Location: view-training.php?id=' . encrypt_id($training_id));
                exit();
            } else {
                $_SESSION['message'] = 'Failed to register for the training. Please try again.';
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = 'Sorry, this training is already full.';
            $_SESSION['message_type'] = 'error';
        }
    } else {
        $_SESSION['message'] = 'Registration is not available for this training.';
        $_SESSION['message_type'] = 'error';
    }
}

// Handle cancellation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_registration'])) {
    if ($is_registered && !$training_started) {
        $delete_query = "DELETE FROM training_registrations
                        WHERE training_id = ? AND user_id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "ii", $training_id, $_SESSION['user_id']);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = 'Registration cancelled successfully.';
            $_SESSION['message_type'] = 'success';
            header('Location: view-training.php?id=' . encrypt_id($training_id));
            exit();
        } else {
            $_SESSION['message'] = 'Failed to cancel registration. Please try again.';
            $_SESSION['message_type'] = 'error';
        }
    } else {
        $_SESSION['message'] = 'Cannot cancel registration for this training.';
        $_SESSION['message_type'] = 'error';
    }
}

// Set page title
$page_title = 'Training Details: ' . $training['title'];

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2"><?php echo htmlspecialchars($training['title']); ?></h1>
            <p class="text-sm sm:text-base text-gray-600">Training & Seminar Details</p>
        </div>
        <div class="flex space-x-2">
            <a href="evaluation-results.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Results
            </a>
            <button onclick="window.print()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                <i class="fas fa-print mr-2"></i>Print Details
            </button>
        </div>
    </div>
</div>

<!-- Message Display -->
<?php if (isset($_SESSION['message'])): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $_SESSION['message_type'] === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($_SESSION['message']); ?>
</div>
<?php
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
endif;
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Training Details -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-seait-orange to-orange-600">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-white flex items-center">
                        <i class="fas fa-graduation-cap mr-3"></i>Training Information
                    </h2>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                        <?php
                        switch($training['status']) {
                            case 'published':
                                echo 'bg-green-100 text-green-800';
                                break;
                            case 'draft':
                                echo 'bg-gray-100 text-gray-800';
                                break;
                            case 'cancelled':
                                echo 'bg-red-100 text-red-800';
                                break;
                            default:
                                echo 'bg-blue-100 text-blue-800';
                        }
                        ?>">
                        <i class="fas fa-circle text-xs mr-2"></i>
                        <?php echo ucfirst($training['status']); ?>
                    </span>
                </div>
            </div>

            <div class="p-6">
                <div class="space-y-6">
                    <!-- Description -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-info-circle text-seait-orange mr-2"></i>Description
                        </h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($training['description'])); ?></p>
                        </div>
                    </div>

                    <!-- Basic Information -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-calendar-alt text-seait-orange mr-2"></i>Basic Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-tag text-gray-400 mr-2"></i>
                                    <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Type</span>
                                </div>
                                <p class="text-gray-900 font-medium"><?php echo ucfirst($training['type']); ?></p>
                            </div>

                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-folder text-gray-400 mr-2"></i>
                                    <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Category</span>
                                </div>
                                <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($training['category_name'] ?? 'N/A'); ?></p>
                            </div>

                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-clock text-gray-400 mr-2"></i>
                                    <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Duration</span>
                                </div>
                                <p class="text-gray-900 font-medium"><?php echo $training['duration_hours'] ? $training['duration_hours'] . ' hours' : 'N/A'; ?></p>
                            </div>

                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-map-marker-alt text-gray-400 mr-2"></i>
                                    <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Venue</span>
                                </div>
                                <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($training['venue'] ?? 'TBD'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-calendar text-seait-orange mr-2"></i>Schedule
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-play text-green-500 mr-2"></i>
                                    <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Start Date</span>
                                </div>
                                <p class="text-gray-900 font-medium"><?php echo date('M d, Y H:i', strtotime($training['start_date'])); ?></p>
                            </div>

                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-stop text-red-500 mr-2"></i>
                                    <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">End Date</span>
                                </div>
                                <p class="text-gray-900 font-medium"><?php echo date('M d, Y H:i', strtotime($training['end_date'])); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Linked Evaluation Categories -->
                    <?php if ($training['main_category_name'] || $training['sub_category_name']): ?>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-link text-seait-orange mr-2"></i>Linked Evaluation Categories
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php if ($training['main_category_name']): ?>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-layer-group text-blue-500 mr-2"></i>
                                    <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Main Category</span>
                                </div>
                                <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($training['main_category_name']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($training['sub_category_name']): ?>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-tags text-purple-500 mr-2"></i>
                                    <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Sub Category</span>
                                </div>
                                <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($training['sub_category_name']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Additional Information -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-cog text-seait-orange mr-2"></i>Additional Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-money-bill text-green-500 mr-2"></i>
                                    <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Cost</span>
                                </div>
                                <p class="text-gray-900 font-medium">
                                    <?php if ($training['cost'] > 0): ?>
                                        ₱<?php echo number_format($training['cost'], 2); ?>
                                    <?php else: ?>
                                        <span class="text-green-600 font-bold">FREE</span>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-users text-blue-500 mr-2"></i>
                                    <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Max Participants</span>
                                </div>
                                <p class="text-gray-900 font-medium"><?php echo $training['max_participants'] ?? 'Unlimited'; ?></p>
                            </div>
                        </div>

                        <!-- Checkboxes -->
                        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="flex items-center bg-white border border-gray-200 rounded-lg p-4">
                                <input type="checkbox" <?php echo $training['is_mandatory'] ? 'checked' : ''; ?> disabled
                                       class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                                <label class="ml-3 text-sm text-gray-900">
                                    <i class="fas fa-exclamation-triangle text-orange-500 mr-1"></i>
                                    Mandatory Training
                                </label>
                            </div>

                            <div class="flex items-center bg-white border border-gray-200 rounded-lg p-4">
                                <input type="checkbox" <?php echo $training['certificate_provided'] ? 'checked' : ''; ?> disabled
                                       class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                                <label class="ml-3 text-sm text-gray-900">
                                    <i class="fas fa-certificate text-green-500 mr-1"></i>
                                    Certificate Provided
                                </label>
                            </div>

                            <div class="flex items-center bg-white border border-gray-200 rounded-lg p-4">
                                <input type="checkbox" <?php echo $training['materials_provided'] ? 'checked' : ''; ?> disabled
                                       class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                                <label class="ml-3 text-sm text-gray-900">
                                    <i class="fas fa-book text-blue-500 mr-1"></i>
                                    Materials Provided
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Registration Status -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-500 to-blue-600">
                <h3 class="text-lg font-semibold text-white flex items-center">
                    <i class="fas fa-user-check mr-3"></i>Registration Status
                </h3>
            </div>
            <div class="p-6">
                <?php if ($is_registered): ?>
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                            <i class="fas fa-check text-green-600 text-xl"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Registered</h4>
                        <p class="text-sm text-gray-600 mb-4">
                            You are registered for this training.
                        </p>
                        <?php if ($registration_data): ?>
                            <p class="text-xs text-gray-500 mb-4">
                                Registered on: <?php echo date('M d, Y', strtotime($registration_data['registration_date'])); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!$training_started): ?>
                            <form method="POST" class="mt-4">
                                <button type="submit" name="cancel_registration"
                                        class="w-full bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition"
                                        onclick="return confirm('Are you sure you want to cancel your registration?')">
                                    <i class="fas fa-times mr-2"></i>Cancel Registration
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <p class="text-sm text-blue-800">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Training has started. Registration cannot be cancelled.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-gray-100 mb-4">
                            <i class="fas fa-user-plus text-gray-600 text-xl"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Not Registered</h4>
                        <p class="text-sm text-gray-600 mb-4">
                            You are not registered for this training.
                        </p>

                        <?php if ($registration_deadline_passed): ?>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                <p class="text-sm text-red-800">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Registration deadline has passed.
                                </p>
                            </div>
                        <?php elseif ($training_started): ?>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                <p class="text-sm text-red-800">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Training has already started.
                                </p>
                            </div>
                        <?php elseif ($training['max_participants'] && $participants_count >= $training['max_participants']): ?>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                <p class="text-sm text-red-800">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Training is full.
                                </p>
                            </div>
                        <?php else: ?>
                            <form method="POST" class="mt-4">
                                <button type="submit" name="register"
                                        class="w-full bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                                    <i class="fas fa-user-plus mr-2"></i>Register Now
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-500 to-green-600">
                <h3 class="text-lg font-semibold text-white flex items-center">
                    <i class="fas fa-chart-bar mr-3"></i>Quick Stats
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Registered</span>
                        <span class="text-sm font-medium text-gray-900"><?php echo $participants_count; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Available Spots</span>
                        <span class="text-sm font-medium text-gray-900">
                            <?php
                            if ($training['max_participants']) {
                                $available = max(0, $training['max_participants'] - $participants_count);
                                echo $available;
                            } else {
                                echo '∞';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Days Until Start</span>
                        <span class="text-sm font-medium text-gray-900">
                            <?php
                            $days_until = ceil((strtotime($training['start_date']) - time()) / (60 * 60 * 24));
                            if ($days_until > 0) {
                                echo $days_until . ' days';
                            } elseif ($days_until == 0) {
                                echo 'Today';
                            } else {
                                echo 'Started';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registration Deadline -->
        <?php if ($training['registration_deadline']): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-500 to-purple-600">
                <h3 class="text-lg font-semibold text-white flex items-center">
                    <i class="fas fa-clock mr-3"></i>Registration Deadline
                </h3>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-900 mb-2"><?php echo date('M d, Y H:i', strtotime($training['registration_deadline'])); ?></p>
                <?php if ($registration_deadline_passed): ?>
                    <p class="text-xs text-red-600">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Registration closed
                    </p>
                <?php else: ?>
                    <p class="text-xs text-green-600">
                        <i class="fas fa-check-circle mr-1"></i>
                        Registration open
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Created By -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-500 to-indigo-600">
                <h3 class="text-lg font-semibold text-white flex items-center">
                    <i class="fas fa-user-tie mr-3"></i>Created By
                </h3>
            </div>
            <div class="p-6">
                <div class="flex items-center">
                    <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center mr-3">
                        <span class="text-white font-medium"><?php echo strtoupper(substr($training['first_name'], 0, 1) . substr($training['last_name'], 0, 1)); ?></span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($training['first_name'] . ' ' . $training['last_name']); ?></p>
                        <p class="text-xs text-gray-500">Guidance Officer</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Training Status Timeline -->
<div class="mt-8">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-yellow-500 to-yellow-600">
            <h2 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-timeline mr-3"></i>Training Timeline
            </h2>
        </div>
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="flex items-center justify-center h-8 w-8 rounded-full <?php echo time() >= strtotime($training['registration_deadline']) ? 'bg-green-500' : 'bg-gray-300'; ?> mr-3">
                        <i class="fas fa-check text-white text-sm"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Registration Period</p>
                        <p class="text-xs text-gray-500">Until <?php echo date('M d, Y', strtotime($training['registration_deadline'])); ?></p>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="flex items-center justify-center h-8 w-8 rounded-full <?php echo time() >= strtotime($training['start_date']) ? 'bg-green-500' : 'bg-gray-300'; ?> mr-3">
                        <i class="fas <?php echo time() >= strtotime($training['start_date']) ? 'fa-check' : 'fa-clock'; ?> text-white text-sm"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Training Start</p>
                        <p class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($training['start_date'])); ?></p>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="flex items-center justify-center h-8 w-8 rounded-full <?php echo time() >= strtotime($training['end_date']) ? 'bg-green-500' : 'bg-gray-300'; ?> mr-3">
                        <i class="fas <?php echo time() >= strtotime($training['end_date']) ? 'fa-check' : 'fa-clock'; ?> text-white text-sm"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Training End</p>
                        <p class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($training['end_date'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Print styles */
@media print {
    .bg-white {
        background-color: white !important;
    }

    .shadow-md {
        box-shadow: none !important;
    }

    .rounded-lg {
        border-radius: 0 !important;
    }

    .border {
        border: 1px solid #e5e7eb !important;
    }

    .text-seait-orange {
        color: #f97316 !important;
    }

    .bg-gradient-to-r {
        background: linear-gradient(to right, #f97316, #ea580c) !important;
        color: white !important;
    }
}
</style>

<?php
// Include the shared footer
include 'includes/footer.php';
?>