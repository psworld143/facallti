<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a content creator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'content_creator') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$error = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'core-values';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_core_value') {
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $icon = mysqli_real_escape_string($conn, $_POST['icon']);
            $sort_order = (int)$_POST['sort_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $query = "INSERT INTO core_values (title, description, icon, sort_order, is_active, created_by)
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sssiis', $title, $description, $icon, $sort_order, $is_active, $_SESSION['user_id']);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Core value added successfully!';
            } else {
                $error = 'Failed to add core value.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'edit_core_value') {
            $id = (int)$_POST['core_value_id'];
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $icon = mysqli_real_escape_string($conn, $_POST['icon']);
            $sort_order = (int)$_POST['sort_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $query = "UPDATE core_values SET title = ?, description = ?, icon = ?, sort_order = ?, is_active = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sssiis', $title, $description, $icon, $sort_order, $is_active, $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Core value updated successfully!';
            } else {
                $error = 'Failed to update core value.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'delete_core_value') {
            $id = (int)$_POST['core_value_id'];

            $query = "DELETE FROM core_values WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Core value deleted successfully!';
            } else {
                $error = 'Failed to delete core value.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'add_mission_vision') {
            $type = mysqli_real_escape_string($conn, $_POST['type']);
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            $content = mysqli_real_escape_string($conn, $_POST['content']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $query = "INSERT INTO mission_vision (type, title, content, is_active, created_by)
                     VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sssiis', $type, $title, $content, $is_active, $_SESSION['user_id']);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Mission/Vision added successfully!';
            } else {
                $error = 'Failed to add mission/vision.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'edit_mission_vision') {
            $id = (int)$_POST['mission_vision_id'];
            $type = mysqli_real_escape_string($conn, $_POST['type']);
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            $content = mysqli_real_escape_string($conn, $_POST['content']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $query = "UPDATE mission_vision SET type = ?, title = ?, content = ?, is_active = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sssiis', $type, $title, $content, $is_active, $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Mission/Vision updated successfully!';
            } else {
                $error = 'Failed to update mission/vision.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'delete_mission_vision') {
            $id = (int)$_POST['mission_vision_id'];

            $query = "DELETE FROM mission_vision WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Mission/Vision deleted successfully!';
            } else {
                $error = 'Failed to delete mission/vision.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'add_timeline_event') {
            $year = (int)$_POST['year'];
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $sort_order = (int)$_POST['sort_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $query = "INSERT INTO timeline_events (year, title, description, sort_order, is_active, created_by)
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'issiis', $year, $title, $description, $sort_order, $is_active, $_SESSION['user_id']);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Timeline event added successfully!';
            } else {
                $error = 'Failed to add timeline event.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'edit_timeline_event') {
            $id = (int)$_POST['timeline_event_id'];
            $year = (int)$_POST['year'];
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $sort_order = (int)$_POST['sort_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $query = "UPDATE timeline_events SET year = ?, title = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'issiis', $year, $title, $description, $sort_order, $is_active, $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Timeline event updated successfully!';
            } else {
                $error = 'Failed to update timeline event.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'delete_timeline_event') {
            $id = (int)$_POST['timeline_event_id'];

            $query = "DELETE FROM timeline_events WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Timeline event deleted successfully!';
            } else {
                $error = 'Failed to delete timeline event.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get data for display
$core_values_query = "SELECT * FROM core_values ORDER BY sort_order ASC, created_at DESC";
$core_values_result = mysqli_query($conn, $core_values_query);

$mission_vision_query = "SELECT * FROM mission_vision ORDER BY type ASC, created_at DESC";
$mission_vision_result = mysqli_query($conn, $mission_vision_query);

$timeline_events_query = "SELECT * FROM timeline_events ORDER BY year DESC, sort_order ASC"; // Latest to oldest
$timeline_events_result = mysqli_query($conn, $timeline_events_query);
?>

<?php
$page_title = 'Manage History';
include 'includes/header.php';
?>
    <style>
        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .animate-bounce-in {
            animation: bounceIn 0.6s ease-out;
        }

        /* Icon dropdown styling */
        .icon-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
        }

        .icon-option i {
            width: 20px;
            text-align: center;
            color: #6B7280;
        }

        .icon-select {
            background-image: none !important;
        }

        .icon-select option {
            padding: 8px 12px;
        }
    </style>
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Manage History</h1>
                    <p class="text-gray-600">Manage core values, mission & vision, and timeline events</p>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Information Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 lg:p-6 mb-6 lg:mb-8">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 mb-2">History & Values Management</h3>
                            <div class="text-sm text-blue-700 space-y-1">
                                <p><strong>Core Values:</strong> Define and manage the institution's core values that guide its mission and operations.</p>
                                <p><strong>Mission & Vision:</strong> Set the institution's mission statement and vision for the future to inspire and guide stakeholders.</p>
                                <p><strong>Timeline Events:</strong> Create a chronological timeline of important events in the institution's history.</p>
                                <p><strong>Organization:</strong> Use the tabs to switch between different sections. All content is organized for easy management and updates.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white rounded-lg shadow-sm mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="flex space-x-8 px-6">
                            <a href="?tab=core-values" class="py-4 px-1 border-b-2 font-medium text-sm <?php echo $active_tab === 'core-values' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                Core Values
                            </a>
                            <a href="?tab=mission-vision" class="py-4 px-1 border-b-2 font-medium text-sm <?php echo $active_tab === 'mission-vision' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                Mission & Vision
                            </a>
                            <a href="?tab=timeline" class="py-4 px-1 border-b-2 font-medium text-sm <?php echo $active_tab === 'timeline' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                Timeline Events
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Tab Content -->
                <?php if ($active_tab === 'core-values'): ?>
                    <!-- Core Values Tab -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-seait-dark">Core Values</h2>
                            <button onclick="openModal('addCoreValueModal')" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition">
                                <i class="fas fa-plus mr-2"></i>Add Core Value
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Icon</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sort Order</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($row = mysqli_fetch_assoc($core_values_result)): ?>
                                        <tr data-core-value-id="<?php echo $row['id']; ?>" data-core-value-title="<?php echo htmlspecialchars($row['title']); ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <i class="<?php echo $row['icon']; ?> text-2xl text-seait-orange"></i>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo $row['title']; ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?php echo substr($row['description'], 0, 100); ?>...</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo $row['sort_order']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $row['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo $row['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="editCoreValue(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="text-seait-orange hover:text-orange-600 mr-3">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="deleteCoreValue(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($active_tab === 'mission-vision'): ?>
                    <!-- Mission & Vision Tab -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-seait-dark">Mission & Vision</h2>
                            <button onclick="openModal('addMissionVisionModal')" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition">
                                <i class="fas fa-plus mr-2"></i>Add Mission/Vision
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Content</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($row = mysqli_fetch_assoc($mission_vision_result)): ?>
                                        <tr data-mission-vision-id="<?php echo $row['id']; ?>" data-mission-vision-title="<?php echo htmlspecialchars($row['title']); ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $row['type'] === 'mission' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'; ?>">
                                                    <?php echo ucfirst($row['type']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo $row['title']; ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?php echo substr($row['content'], 0, 100); ?>...</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $row['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo $row['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="editMissionVision(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="text-seait-orange hover:text-orange-600 mr-3">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="deleteMissionVision(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($active_tab === 'timeline'): ?>
                    <!-- Timeline Events Tab -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-seait-dark">Timeline Events</h2>
                            <button onclick="openModal('addTimelineEventModal')" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition">
                                <i class="fas fa-plus mr-2"></i>Add Timeline Event
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sort Order</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($row = mysqli_fetch_assoc($timeline_events_result)): ?>
                                        <tr data-timeline-event-id="<?php echo $row['id']; ?>" data-timeline-event-title="<?php echo htmlspecialchars($row['title']); ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-bold text-seait-orange"><?php echo $row['year']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo $row['title']; ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?php echo substr($row['description'], 0, 100); ?>...</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo $row['sort_order']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $row['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo $row['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="editTimelineEvent(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="text-seait-orange hover:text-orange-600 mr-3">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="deleteTimelineEvent(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Core Value Modal -->
    <div id="addCoreValueModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Core Value</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_core_value">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                        <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Icon</label>
                        <select name="icon" id="coreValueIconSelect" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange icon-select">
                            <option value="">Select Icon</option>
                            <option value="fas fa-star" data-icon="fas fa-star">⭐ Star</option>
                            <option value="fas fa-heart" data-icon="fas fa-heart">❤️ Heart</option>
                            <option value="fas fa-lightbulb" data-icon="fas fa-lightbulb">💡 Lightbulb</option>
                            <option value="fas fa-graduation-cap" data-icon="fas fa-graduation-cap">🎓 Graduation Cap</option>
                            <option value="fas fa-book" data-icon="fas fa-book">📚 Book</option>
                            <option value="fas fa-users" data-icon="fas fa-users">👥 Users</option>
                            <option value="fas fa-handshake" data-icon="fas fa-handshake">🤝 Handshake</option>
                            <option value="fas fa-shield-alt" data-icon="fas fa-shield-alt">🛡️ Shield</option>
                            <option value="fas fa-award" data-icon="fas fa-award">🏆 Award</option>
                            <option value="fas fa-trophy" data-icon="fas fa-trophy">🏆 Trophy</option>
                            <option value="fas fa-medal" data-icon="fas fa-medal">🥇 Medal</option>
                            <option value="fas fa-crown" data-icon="fas fa-crown">👑 Crown</option>
                            <option value="fas fa-gem" data-icon="fas fa-gem">💎 Gem</option>
                            <option value="fas fa-diamond" data-icon="fas fa-diamond">💎 Diamond</option>
                            <option value="fas fa-fire" data-icon="fas fa-fire">🔥 Fire</option>
                            <option value="fas fa-sun" data-icon="fas fa-sun">☀️ Sun</option>
                            <option value="fas fa-moon" data-icon="fas fa-moon">🌙 Moon</option>
                            <option value="fas fa-leaf" data-icon="fas fa-leaf">🍃 Leaf</option>
                            <option value="fas fa-tree" data-icon="fas fa-tree">🌳 Tree</option>
                            <option value="fas fa-seedling" data-icon="fas fa-seedling">🌱 Seedling</option>
                            <option value="fas fa-flower" data-icon="fas fa-flower">🌸 Flower</option>
                            <option value="fas fa-rose" data-icon="fas fa-rose">🌹 Rose</option>
                            <option value="fas fa-tulip" data-icon="fas fa-tulip">🌷 Tulip</option>
                            <option value="fas fa-lotus" data-icon="fas fa-lotus">🪷 Lotus</option>
                            <option value="fas fa-bamboo" data-icon="fas fa-bamboo">🎋 Bamboo</option>
                            <option value="fas fa-mountain" data-icon="fas fa-mountain">⛰️ Mountain</option>
                            <option value="fas fa-water" data-icon="fas fa-water">💧 Water</option>
                            <option value="fas fa-wave" data-icon="fas fa-wave">🌊 Wave</option>
                            <option value="fas fa-umbrella" data-icon="fas fa-umbrella">☂️ Umbrella</option>
                            <option value="fas fa-rainbow" data-icon="fas fa-rainbow">🌈 Rainbow</option>
                            <option value="fas fa-cloud" data-icon="fas fa-cloud">☁️ Cloud</option>
                            <option value="fas fa-bolt" data-icon="fas fa-bolt">⚡ Bolt</option>
                            <option value="fas fa-snowflake" data-icon="fas fa-snowflake">❄️ Snowflake</option>
                            <option value="fas fa-wind" data-icon="fas fa-wind">💨 Wind</option>
                            <option value="fas fa-compass" data-icon="fas fa-compass">🧭 Compass</option>
                            <option value="fas fa-map" data-icon="fas fa-map">🗺️ Map</option>
                            <option value="fas fa-globe" data-icon="fas fa-globe">🌐 Globe</option>
                            <option value="fas fa-flag" data-icon="fas fa-flag">🏁 Flag</option>
                            <option value="fas fa-anchor" data-icon="fas fa-anchor">⚓ Anchor</option>
                            <option value="fas fa-ship" data-icon="fas fa-ship">🚢 Ship</option>
                            <option value="fas fa-plane" data-icon="fas fa-plane">✈️ Plane</option>
                            <option value="fas fa-car" data-icon="fas fa-car">🚗 Car</option>
                            <option value="fas fa-bicycle" data-icon="fas fa-bicycle">🚲 Bicycle</option>
                            <option value="fas fa-walking" data-icon="fas fa-walking">🚶 Walking</option>
                            <option value="fas fa-running" data-icon="fas fa-running">🏃 Running</option>
                            <option value="fas fa-dumbbell" data-icon="fas fa-dumbbell">🏋️ Dumbbell</option>
                            <option value="fas fa-futbol" data-icon="fas fa-futbol">⚽ Football</option>
                            <option value="fas fa-basketball-ball" data-icon="fas fa-basketball-ball">🏀 Basketball</option>
                            <option value="fas fa-volleyball-ball" data-icon="fas fa-volleyball-ball">🏐 Volleyball</option>
                            <option value="fas fa-table-tennis" data-icon="fas fa-table-tennis">🏓 Table Tennis</option>
                            <option value="fas fa-chess" data-icon="fas fa-chess">♟️ Chess</option>
                            <option value="fas fa-puzzle-piece" data-icon="fas fa-puzzle-piece">🧩 Puzzle Piece</option>
                            <option value="fas fa-cube" data-icon="fas fa-cube">🧊 Cube</option>
                            <option value="fas fa-dice" data-icon="fas fa-dice">🎲 Dice</option>
                            <option value="fas fa-gamepad" data-icon="fas fa-gamepad">🎮 Gamepad</option>
                            <option value="fas fa-music" data-icon="fas fa-music">🎵 Music</option>
                            <option value="fas fa-guitar" data-icon="fas fa-guitar">🎸 Guitar</option>
                            <option value="fas fa-piano" data-icon="fas fa-piano">🎹 Piano</option>
                            <option value="fas fa-microphone" data-icon="fas fa-microphone">🎤 Microphone</option>
                            <option value="fas fa-headphones" data-icon="fas fa-headphones">🎧 Headphones</option>
                            <option value="fas fa-camera" data-icon="fas fa-camera">📷 Camera</option>
                            <option value="fas fa-video" data-icon="fas fa-video">📹 Video</option>
                            <option value="fas fa-film" data-icon="fas fa-film">🎬 Film</option>
                            <option value="fas fa-theater-masks" data-icon="fas fa-theater-masks">🎭 Theater Masks</option>
                            <option value="fas fa-palette" data-icon="fas fa-palette">🎨 Palette</option>
                            <option value="fas fa-paint-brush" data-icon="fas fa-paint-brush">🖌️ Paint Brush</option>
                            <option value="fas fa-pencil-alt" data-icon="fas fa-pencil-alt">✏️ Pencil</option>
                            <option value="fas fa-pen" data-icon="fas fa-pen">🖊️ Pen</option>
                            <option value="fas fa-marker" data-icon="fas fa-marker">🖍️ Marker</option>
                            <option value="fas fa-highlighter" data-icon="fas fa-highlighter">🖍️ Highlighter</option>
                            <option value="fas fa-chalkboard" data-icon="fas fa-chalkboard">📝 Chalkboard</option>
                            <option value="fas fa-chalkboard-teacher" data-icon="fas fa-chalkboard-teacher">👨‍🏫 Chalkboard Teacher</option>
                            <option value="fas fa-user-graduate" data-icon="fas fa-user-graduate">🎓 User Graduate</option>
                            <option value="fas fa-user-tie" data-icon="fas fa-user-tie">👔 User Tie</option>
                            <option value="fas fa-user-ninja" data-icon="fas fa-user-ninja">🥷 User Ninja</option>
                            <option value="fas fa-user-astronaut" data-icon="fas fa-user-astronaut">👨‍🚀 User Astronaut</option>
                            <option value="fas fa-user-shield" data-icon="fas fa-user-shield">🛡️ User Shield</option>
                            <option value="fas fa-user-graduate" data-icon="fas fa-user-graduate">🎓 User Graduate</option>
                            <option value="fas fa-user-tie" data-icon="fas fa-user-tie">👔 User Tie</option>
                            <option value="fas fa-user-ninja" data-icon="fas fa-user-ninja">🥷 User Ninja</option>
                            <option value="fas fa-user-astronaut" data-icon="fas fa-user-astronaut">👨‍🚀 User Astronaut</option>
                            <option value="fas fa-user-shield" data-icon="fas fa-user-shield">🛡️ User Shield</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" checked class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('addCoreValueModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Add Core Value
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Core Value Modal -->
    <div id="editCoreValueModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Core Value</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="edit_core_value">
                    <input type="hidden" name="core_value_id" id="edit_core_value_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                        <input type="text" name="title" id="edit_core_value_title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" id="edit_core_value_description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Icon</label>
                        <select name="icon" id="edit_core_value_icon" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange icon-select">
                            <option value="">Select Icon</option>
                            <option value="fas fa-star" data-icon="fas fa-star">⭐ Star</option>
                            <option value="fas fa-heart" data-icon="fas fa-heart">❤️ Heart</option>
                            <option value="fas fa-lightbulb" data-icon="fas fa-lightbulb">💡 Lightbulb</option>
                            <option value="fas fa-graduation-cap" data-icon="fas fa-graduation-cap">🎓 Graduation Cap</option>
                            <option value="fas fa-book" data-icon="fas fa-book">📚 Book</option>
                            <option value="fas fa-users" data-icon="fas fa-users">👥 Users</option>
                            <option value="fas fa-handshake" data-icon="fas fa-handshake">🤝 Handshake</option>
                            <option value="fas fa-shield-alt" data-icon="fas fa-shield-alt">🛡️ Shield</option>
                            <option value="fas fa-award" data-icon="fas fa-award">🏆 Award</option>
                            <option value="fas fa-trophy" data-icon="fas fa-trophy">🏆 Trophy</option>
                            <option value="fas fa-medal" data-icon="fas fa-medal">🥇 Medal</option>
                            <option value="fas fa-crown" data-icon="fas fa-crown">👑 Crown</option>
                            <option value="fas fa-gem" data-icon="fas fa-gem">💎 Gem</option>
                            <option value="fas fa-diamond" data-icon="fas fa-diamond">💎 Diamond</option>
                            <option value="fas fa-fire" data-icon="fas fa-fire">🔥 Fire</option>
                            <option value="fas fa-sun" data-icon="fas fa-sun">☀️ Sun</option>
                            <option value="fas fa-moon" data-icon="fas fa-moon">🌙 Moon</option>
                            <option value="fas fa-leaf" data-icon="fas fa-leaf">🍃 Leaf</option>
                            <option value="fas fa-tree" data-icon="fas fa-tree">🌳 Tree</option>
                            <option value="fas fa-seedling" data-icon="fas fa-seedling">🌱 Seedling</option>
                            <option value="fas fa-flower" data-icon="fas fa-flower">🌸 Flower</option>
                            <option value="fas fa-rose" data-icon="fas fa-rose">🌹 Rose</option>
                            <option value="fas fa-tulip" data-icon="fas fa-tulip">🌷 Tulip</option>
                            <option value="fas fa-lotus" data-icon="fas fa-lotus">🪷 Lotus</option>
                            <option value="fas fa-bamboo" data-icon="fas fa-bamboo">🎋 Bamboo</option>
                            <option value="fas fa-mountain" data-icon="fas fa-mountain">⛰️ Mountain</option>
                            <option value="fas fa-water" data-icon="fas fa-water">💧 Water</option>
                            <option value="fas fa-wave" data-icon="fas fa-wave">🌊 Wave</option>
                            <option value="fas fa-umbrella" data-icon="fas fa-umbrella">☂️ Umbrella</option>
                            <option value="fas fa-rainbow" data-icon="fas fa-rainbow">🌈 Rainbow</option>
                            <option value="fas fa-cloud" data-icon="fas fa-cloud">☁️ Cloud</option>
                            <option value="fas fa-bolt" data-icon="fas fa-bolt">⚡ Bolt</option>
                            <option value="fas fa-snowflake" data-icon="fas fa-snowflake">❄️ Snowflake</option>
                            <option value="fas fa-wind" data-icon="fas fa-wind">💨 Wind</option>
                            <option value="fas fa-compass" data-icon="fas fa-compass">🧭 Compass</option>
                            <option value="fas fa-map" data-icon="fas fa-map">🗺️ Map</option>
                            <option value="fas fa-globe" data-icon="fas fa-globe">🌐 Globe</option>
                            <option value="fas fa-flag" data-icon="fas fa-flag">🏁 Flag</option>
                            <option value="fas fa-anchor" data-icon="fas fa-anchor">⚓ Anchor</option>
                            <option value="fas fa-ship" data-icon="fas fa-ship">🚢 Ship</option>
                            <option value="fas fa-plane" data-icon="fas fa-plane">✈️ Plane</option>
                            <option value="fas fa-car" data-icon="fas fa-car">🚗 Car</option>
                            <option value="fas fa-bicycle" data-icon="fas fa-bicycle">🚲 Bicycle</option>
                            <option value="fas fa-walking" data-icon="fas fa-walking">🚶 Walking</option>
                            <option value="fas fa-running" data-icon="fas fa-running">🏃 Running</option>
                            <option value="fas fa-dumbbell" data-icon="fas fa-dumbbell">🏋️ Dumbbell</option>
                            <option value="fas fa-futbol" data-icon="fas fa-futbol">⚽ Football</option>
                            <option value="fas fa-basketball-ball" data-icon="fas fa-basketball-ball">🏀 Basketball</option>
                            <option value="fas fa-volleyball-ball" data-icon="fas fa-volleyball-ball">🏐 Volleyball</option>
                            <option value="fas fa-table-tennis" data-icon="fas fa-table-tennis">🏓 Table Tennis</option>
                            <option value="fas fa-chess" data-icon="fas fa-chess">♟️ Chess</option>
                            <option value="fas fa-puzzle-piece" data-icon="fas fa-puzzle-piece">🧩 Puzzle Piece</option>
                            <option value="fas fa-cube" data-icon="fas fa-cube">🧊 Cube</option>
                            <option value="fas fa-dice" data-icon="fas fa-dice">🎲 Dice</option>
                            <option value="fas fa-gamepad" data-icon="fas fa-gamepad">🎮 Gamepad</option>
                            <option value="fas fa-music" data-icon="fas fa-music">🎵 Music</option>
                            <option value="fas fa-guitar" data-icon="fas fa-guitar">🎸 Guitar</option>
                            <option value="fas fa-piano" data-icon="fas fa-piano">🎹 Piano</option>
                            <option value="fas fa-microphone" data-icon="fas fa-microphone">🎤 Microphone</option>
                            <option value="fas fa-headphones" data-icon="fas fa-headphones">🎧 Headphones</option>
                            <option value="fas fa-camera" data-icon="fas fa-camera">📷 Camera</option>
                            <option value="fas fa-video" data-icon="fas fa-video">📹 Video</option>
                            <option value="fas fa-film" data-icon="fas fa-film">🎬 Film</option>
                            <option value="fas fa-theater-masks" data-icon="fas fa-theater-masks">🎭 Theater Masks</option>
                            <option value="fas fa-palette" data-icon="fas fa-palette">🎨 Palette</option>
                            <option value="fas fa-paint-brush" data-icon="fas fa-paint-brush">🖌️ Paint Brush</option>
                            <option value="fas fa-pencil-alt" data-icon="fas fa-pencil-alt">✏️ Pencil</option>
                            <option value="fas fa-pen" data-icon="fas fa-pen">🖊️ Pen</option>
                            <option value="fas fa-marker" data-icon="fas fa-marker">🖍️ Marker</option>
                            <option value="fas fa-highlighter" data-icon="fas fa-highlighter">🖍️ Highlighter</option>
                            <option value="fas fa-chalkboard" data-icon="fas fa-chalkboard">📝 Chalkboard</option>
                            <option value="fas fa-chalkboard-teacher" data-icon="fas fa-chalkboard-teacher">👨‍🏫 Chalkboard Teacher</option>
                            <option value="fas fa-user-graduate" data-icon="fas fa-user-graduate">🎓 User Graduate</option>
                            <option value="fas fa-user-tie" data-icon="fas fa-user-tie">👔 User Tie</option>
                            <option value="fas fa-user-ninja" data-icon="fas fa-user-ninja">🥷 User Ninja</option>
                            <option value="fas fa-user-astronaut" data-icon="fas fa-user-astronaut">👨‍🚀 User Astronaut</option>
                            <option value="fas fa-user-shield" data-icon="fas fa-user-shield">🛡️ User Shield</option>
                            <option value="fas fa-user-graduate" data-icon="fas fa-user-graduate">🎓 User Graduate</option>
                            <option value="fas fa-user-tie" data-icon="fas fa-user-tie">👔 User Tie</option>
                            <option value="fas fa-user-ninja" data-icon="fas fa-user-ninja">🥷 User Ninja</option>
                            <option value="fas fa-user-astronaut" data-icon="fas fa-user-astronaut">👨‍🚀 User Astronaut</option>
                            <option value="fas fa-user-shield" data-icon="fas fa-user-shield">🛡️ User Shield</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" id="edit_core_value_sort_order" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" id="edit_core_value_is_active" class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('editCoreValueModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Update Core Value
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Mission Vision Modal -->
    <div id="addMissionVisionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Mission/Vision</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_mission_vision">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                        <select name="type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="mission">Mission</option>
                            <option value="vision">Vision</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                        <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Content</label>
                        <textarea name="content" rows="4" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" checked class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('addMissionVisionModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Add Mission/Vision
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Mission Vision Modal -->
    <div id="editMissionVisionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Mission/Vision</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="edit_mission_vision">
                    <input type="hidden" name="mission_vision_id" id="edit_mission_vision_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                        <select name="type" id="edit_mission_vision_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="mission">Mission</option>
                            <option value="vision">Vision</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                        <input type="text" name="title" id="edit_mission_vision_title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Content</label>
                        <textarea name="content" id="edit_mission_vision_content" rows="4" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" id="edit_mission_vision_is_active" class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('editMissionVisionModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Update Mission/Vision
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Timeline Event Modal -->
    <div id="addTimelineEventModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Timeline Event</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_timeline_event">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                        <input type="number" name="year" min="2006" max="2030" value="2006" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                        <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" checked class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('addTimelineEventModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Add Timeline Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Timeline Event Modal -->
    <div id="editTimelineEventModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Timeline Event</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="edit_timeline_event">
                    <input type="hidden" name="timeline_event_id" id="edit_timeline_event_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                        <input type="number" name="year" id="edit_timeline_event_year" min="2006" max="2030" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                        <input type="text" name="title" id="edit_timeline_event_title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" id="edit_timeline_event_description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" id="edit_timeline_event_sort_order" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" id="edit_timeline_event_is_active" class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('editTimelineEventModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Update Timeline Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Core Value Confirmation Modal -->
    <div id="deleteCoreValueModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Core Value</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete "<span id="deleteCoreValueTitle" class="font-semibold"></span>"? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Core value will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    No longer visible on the website
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form id="deleteCoreValueForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_core_value">
                        <input type="hidden" name="core_value_id" id="deleteCoreValueId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteCoreValueModal()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-trash mr-2"></i>Delete Permanently
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Mission Vision Confirmation Modal -->
    <div id="deleteMissionVisionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Mission/Vision</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete "<span id="deleteMissionVisionTitle" class="font-semibold"></span>"? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Mission/Vision will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    No longer visible on the website
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form id="deleteMissionVisionForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_mission_vision">
                        <input type="hidden" name="mission_vision_id" id="deleteMissionVisionId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteMissionVisionModal()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-trash mr-2"></i>Delete Permanently
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Timeline Event Confirmation Modal -->
    <div id="deleteTimelineEventModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Timeline Event</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete "<span id="deleteTimelineEventTitle" class="font-semibold"></span>"? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Timeline event will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    No longer visible on the website
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form id="deleteTimelineEventForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_timeline_event">
                        <input type="hidden" name="timeline_event_id" id="deleteTimelineEventId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteTimelineEventModal()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-trash mr-2"></i>Delete Permanently
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Edit functions
        function editCoreValue(data) {
            document.getElementById('edit_core_value_id').value = data.id;
            document.getElementById('edit_core_value_title').value = data.title;
            document.getElementById('edit_core_value_description').value = data.description;
            document.getElementById('edit_core_value_icon').value = data.icon;
            document.getElementById('edit_core_value_sort_order').value = data.sort_order;
            document.getElementById('edit_core_value_is_active').checked = data.is_active == 1;
            openModal('editCoreValueModal');
        }

        function editMissionVision(data) {
            document.getElementById('edit_mission_vision_id').value = data.id;
            document.getElementById('edit_mission_vision_type').value = data.type;
            document.getElementById('edit_mission_vision_title').value = data.title;
            document.getElementById('edit_mission_vision_content').value = data.content;
            document.getElementById('edit_mission_vision_is_active').checked = data.is_active == 1;
            openModal('editMissionVisionModal');
        }

        function editTimelineEvent(data) {
            document.getElementById('edit_timeline_event_id').value = data.id;
            document.getElementById('edit_timeline_event_year').value = data.year;
            document.getElementById('edit_timeline_event_title').value = data.title;
            document.getElementById('edit_timeline_event_description').value = data.description;
            document.getElementById('edit_timeline_event_sort_order').value = data.sort_order;
            document.getElementById('edit_timeline_event_is_active').checked = data.is_active == 1;
            openModal('editTimelineEventModal');
        }

        // Delete functions
        function deleteCoreValue(id) {
            document.getElementById('deleteCoreValueId').value = id;
            document.getElementById('deleteCoreValueTitle').textContent = document.querySelector(`tr[data-core-value-id="${id}"]`).dataset.coreValueTitle;
            openModal('deleteCoreValueModal');
        }

        function deleteMissionVision(id) {
            document.getElementById('deleteMissionVisionId').value = id;
            document.getElementById('deleteMissionVisionTitle').textContent = document.querySelector(`tr[data-mission-vision-id="${id}"]`).dataset.missionVisionTitle;
            openModal('deleteMissionVisionModal');
        }

        function deleteTimelineEvent(id) {
            document.getElementById('deleteTimelineEventId').value = id;
            document.getElementById('deleteTimelineEventTitle').textContent = document.querySelector(`tr[data-timeline-event-id="${id}"]`).dataset.timelineEventTitle;
            openModal('deleteTimelineEventModal');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['addCoreValueModal', 'editCoreValueModal', 'addMissionVisionModal', 'editMissionVisionModal', 'addTimelineEventModal', 'editTimelineEventModal', 'deleteCoreValueModal', 'deleteMissionVisionModal', 'deleteTimelineEventModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        }

        function closeDeleteCoreValueModal() {
            document.getElementById('deleteCoreValueModal').classList.add('hidden');
        }

        function closeDeleteMissionVisionModal() {
            document.getElementById('deleteMissionVisionModal').classList.add('hidden');
        }

        function closeDeleteTimelineEventModal() {
            document.getElementById('deleteTimelineEventModal').classList.add('hidden');
        }

        // Close specific delete modals when clicking outside
        const deleteCoreValueModal = document.getElementById('deleteCoreValueModal');
        const deleteMissionVisionModal = document.getElementById('deleteMissionVisionModal');
        const deleteTimelineEventModal = document.getElementById('deleteTimelineEventModal');

        if (deleteCoreValueModal) {
            deleteCoreValueModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteCoreValueModal();
                }
            });
        }

        if (deleteMissionVisionModal) {
            deleteMissionVisionModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteMissionVisionModal();
                }
            });
        }

        if (deleteTimelineEventModal) {
            deleteTimelineEventModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteTimelineEventModal();
                }
            });
        }
    </script>
