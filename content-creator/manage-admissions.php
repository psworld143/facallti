<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a content creator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'content_creator') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_level':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $icon = trim($_POST['icon']);
                $sort_order = (int)$_POST['sort_order'];

                if (empty($name)) {
                    $error = 'Level name is required.';
                } else {
                    $query = "INSERT INTO admission_levels (name, description, icon, sort_order, created_by) VALUES (?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, 'sssii', $name, $description, $icon, $sort_order, $user_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Admission level added successfully!';
                    } else {
                        $error = 'Error adding admission level: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'update_level':
                $level_id = (int)$_POST['level_id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $icon = trim($_POST['icon']);
                $sort_order = (int)$_POST['sort_order'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                $query = "UPDATE admission_levels SET name = ?, description = ?, icon = ?, sort_order = ?, is_active = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'sssiii', $name, $description, $icon, $sort_order, $is_active, $level_id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Admission level updated successfully!';
                } else {
                    $error = 'Error updating admission level: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;

            case 'delete_level':
                $level_id = (int)$_POST['level_id'];

                $delete_query = "DELETE FROM admission_levels WHERE id = ?";
                $stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($stmt, "i", $level_id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Admission level deleted successfully!';
                } else {
                    $error = 'Error deleting admission level: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;

            case 'add_requirement':
                $level_id = (int)$_POST['level_id'];
                $step_number = (int)$_POST['step_number'];
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);

                if (empty($title)) {
                    $error = 'Requirement title is required.';
                } else {
                    $query = "INSERT INTO admission_requirements (level_id, step_number, title, description, created_by) VALUES (?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, 'iissi', $level_id, $step_number, $title, $description, $user_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Admission requirement added successfully!';
                    } else {
                        $error = 'Error adding admission requirement: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'add_program':
                $level_id = (int)$_POST['level_id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);

                if (empty($name)) {
                    $error = 'Program name is required.';
                } else {
                    $query = "INSERT INTO admission_programs (level_id, name, description, created_by) VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, 'issi', $level_id, $name, $description, $user_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Admission program added successfully!';
                    } else {
                        $error = 'Error adding admission program: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'add_contact':
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $contact_info = trim($_POST['contact_info']);
                $additional_info = trim($_POST['additional_info']);
                $icon = trim($_POST['icon']);
                $sort_order = (int)$_POST['sort_order'];

                if (empty($title) || empty($contact_info)) {
                    $error = 'Title and contact information are required.';
                } else {
                    $query = "INSERT INTO admission_contacts (title, description, contact_info, additional_info, icon, sort_order, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, 'sssssii', $title, $description, $contact_info, $additional_info, $icon, $sort_order, $user_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Admission contact added successfully!';
                    } else {
                        $error = 'Error adding admission contact: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'update_contact':
                $contact_id = (int)$_POST['contact_id'];
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $contact_info = trim($_POST['contact_info']);
                $additional_info = trim($_POST['additional_info']);
                $icon = trim($_POST['icon']);
                $sort_order = (int)$_POST['sort_order'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                if (empty($title) || empty($contact_info)) {
                    $error = 'Title and contact information are required.';
                } else {
                    $query = "UPDATE admission_contacts SET title = ?, description = ?, contact_info = ?, additional_info = ?, icon = ?, sort_order = ?, is_active = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, 'sssssiii', $title, $description, $contact_info, $additional_info, $icon, $sort_order, $is_active, $contact_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Admission contact updated successfully!';
                    } else {
                        $error = 'Error updating admission contact: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'delete_contact':
                $contact_id = (int)$_POST['contact_id'];

                $delete_query = "DELETE FROM admission_contacts WHERE id = ?";
                $stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($stmt, "i", $contact_id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Admission contact deleted successfully!';
                } else {
                    $error = 'Error deleting admission contact: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;
        }
    }
}

// Fetch admission levels
$levels_query = "SELECT * FROM admission_levels ORDER BY sort_order ASC, name ASC";
$levels_result = mysqli_query($conn, $levels_query);

// Fetch admission contacts
$contacts_query = "SELECT * FROM admission_contacts ORDER BY sort_order ASC, title ASC";
$contacts_result = mysqli_query($conn, $contacts_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admissions - SEAIT Content Creator</title>
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
    </style>
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
                        <p class="text-sm text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></p>
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
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Manage Admissions</h1>
                    <p class="text-gray-600">Manage admission levels, requirements, programs, and contact information</p>
                </div>

                <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Information Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 lg:p-6 mb-6 lg:mb-8">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 mb-2">Admission Management</h3>
                            <div class="text-sm text-blue-700 space-y-1">
                                <p><strong>Admission Levels:</strong> Manage Basic Education, Senior High School, and College levels.</p>
                                <p><strong>Requirements:</strong> Set step-by-step admission requirements for each level.</p>
                                <p><strong>Programs:</strong> Add and manage programs offered at each level.</p>
                                <p><strong>Contacts:</strong> Manage admission contact information and methods.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Forms Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 mb-8">
                    <!-- Add Level Form -->
                    <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="p-2 bg-green-100 rounded-full mr-3">
                                    <i class="fas fa-plus text-green-600"></i>
                                </div>
                                <h2 class="text-lg lg:text-xl font-semibold text-seait-dark">Add Admission Level</h2>
                            </div>
                            <button type="button" onclick="toggleForm('level-form')" class="text-seait-orange hover:text-orange-600 p-2 rounded-full hover:bg-orange-50 transition">
                                <i class="fas fa-chevron-down" id="level-toggle-icon"></i>
                            </button>
                        </div>
                        <form method="POST" id="level-form" class="space-y-4">
                            <input type="hidden" name="action" value="add_level">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Level Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" required placeholder="e.g., Basic Education" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Icon Class</label>
                                    <select name="icon" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                        <option value="">Select an icon</option>
                                        <option value="fas fa-child">👶 Child (Basic Education)</option>
                                        <option value="fas fa-graduation-cap">🎓 Graduation Cap (Undergraduate)</option>
                                        <option value="fas fa-user-graduate">👨‍🎓 User Graduate (Graduate)</option>
                                        <option value="fas fa-user-tie">👔 User Tie (Postgraduate)</option>
                                        <option value="fas fa-book">📚 Book (Education)</option>
                                        <option value="fas fa-school">🏫 School (Institution)</option>
                                        <option value="fas fa-university">🏛️ University (Higher Education)</option>
                                        <option value="fas fa-chalkboard-teacher">👨‍🏫 Chalkboard Teacher (Teaching)</option>
                                        <option value="fas fa-users">👥 Users (Group)</option>
                                        <option value="fas fa-star">⭐ Star (Featured)</option>
                                        <option value="fas fa-award">🏆 Award (Achievement)</option>
                                        <option value="fas fa-lightbulb">💡 Lightbulb (Innovation)</option>
                                        <option value="fas fa-rocket">🚀 Rocket (Advancement)</option>
                                        <option value="fas fa-heart">❤️ Heart (Care)</option>
                                        <option value="fas fa-shield-alt">🛡️ Shield (Protection)</option>
                                        <option value="fas fa-globe">🌍 Globe (International)</option>
                                        <option value="fas fa-home">🏠 Home (Foundation)</option>
                                        <option value="fas fa-tree">🌳 Tree (Growth)</option>
                                        <option value="fas fa-seedling">🌱 Seedling (Development)</option>
                                        <option value="fas fa-leaf">🍃 Leaf (Nature)</option>
                                        <option value="fas fa-sun">☀️ Sun (Bright Future)</option>
                                        <option value="fas fa-moon">🌙 Moon (Night Classes)</option>
                                        <option value="fas fa-clock">⏰ Clock (Time)</option>
                                        <option value="fas fa-calendar">📅 Calendar (Schedule)</option>
                                        <option value="fas fa-map-marker-alt">📍 Map Marker (Location)</option>
                                        <option value="fas fa-flag">🏁 Flag (Goal)</option>
                                        <option value="fas fa-trophy">🏆 Trophy (Excellence)</option>
                                        <option value="fas fa-medal">🥇 Medal (Achievement)</option>
                                        <option value="fas fa-certificate">📜 Certificate (Qualification)</option>
                                        <option value="fas fa-diploma">🎓 Diploma (Degree)</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea name="description" rows="3" placeholder="Enter level description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                                <input type="number" name="sort_order" value="0" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="w-full bg-seait-orange text-white px-4 lg:px-6 py-2 lg:py-3 rounded-md hover:bg-orange-600 transition text-sm lg:text-base">
                                    <i class="fas fa-plus mr-2"></i>Add Admission Level
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Add Contact Form -->
                    <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="p-2 bg-blue-100 rounded-full mr-3">
                                    <i class="fas fa-phone text-blue-600"></i>
                                </div>
                                <h2 class="text-lg lg:text-xl font-semibold text-seait-dark">Add Contact Information</h2>
                            </div>
                            <button type="button" onclick="toggleForm('contact-form')" class="text-seait-orange hover:text-orange-600 p-2 rounded-full hover:bg-orange-50 transition">
                                <i class="fas fa-chevron-down" id="contact-toggle-icon"></i>
                            </button>
                        </div>
                        <form method="POST" id="contact-form" class="space-y-4">
                            <input type="hidden" name="action" value="add_contact">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                                    <input type="text" name="title" required placeholder="e.g., Call Us" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Icon Class</label>
                                    <select name="icon" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                        <option value="">Select an icon</option>
                                        <option value="fas fa-phone">📞 Phone (Call)</option>
                                        <option value="fas fa-envelope">✉️ Envelope (Email)</option>
                                        <option value="fas fa-users">👥 Users (Team)</option>
                                        <option value="fas fa-user">👤 User (Person)</option>
                                        <option value="fas fa-user-tie">👔 User Tie (Professional)</option>
                                        <option value="fas fa-headset">🎧 Headset (Support)</option>
                                        <option value="fas fa-comments">💬 Comments (Chat)</option>
                                        <option value="fas fa-comment">💭 Comment (Message)</option>
                                        <option value="fas fa-info-circle">ℹ️ Info Circle (Information)</option>
                                        <option value="fas fa-question-circle">❓ Question Circle (Help)</option>
                                        <option value="fas fa-life-ring">🛟 Life Ring (Support)</option>
                                        <option value="fas fa-hands-helping">🤝 Hands Helping (Assistance)</option>
                                        <option value="fas fa-clock">⏰ Clock (Hours)</option>
                                        <option value="fas fa-calendar">📅 Calendar (Schedule)</option>
                                        <option value="fas fa-map-marker-alt">📍 Map Marker (Location)</option>
                                        <option value="fas fa-building">🏢 Building (Office)</option>
                                        <option value="fas fa-home">🏠 Home (Main Office)</option>
                                        <option value="fas fa-university">🏛️ University (Campus)</option>
                                        <option value="fas fa-graduation-cap">🎓 Graduation Cap (Academic)</option>
                                        <option value="fas fa-book">📚 Book (Education)</option>
                                        <option value="fas fa-chalkboard-teacher">👨‍🏫 Chalkboard Teacher (Teaching)</option>
                                        <option value="fas fa-student">👨‍🎓 Student (Learner)</option>
                                        <option value="fas fa-user-graduate">👨‍🎓 User Graduate (Graduate)</option>
                                        <option value="fas fa-certificate">📜 Certificate (Qualification)</option>
                                        <option value="fas fa-award">🏆 Award (Achievement)</option>
                                        <option value="fas fa-star">⭐ Star (Featured)</option>
                                        <option value="fas fa-heart">❤️ Heart (Care)</option>
                                        <option value="fas fa-shield-alt">🛡️ Shield (Protection)</option>
                                        <option value="fas fa-check-circle">✅ Check Circle (Approved)</option>
                                        <option value="fas fa-exclamation-circle">⚠️ Exclamation Circle (Important)</option>
                                        <option value="fas fa-lightbulb">💡 Lightbulb (Idea)</option>
                                        <option value="fas fa-rocket">🚀 Rocket (Fast Response)</option>
                                        <option value="fas fa-globe">🌍 Globe (International)</option>
                                        <option value="fas fa-flag">🏁 Flag (Priority)</option>
                                        <option value="fas fa-bell">🔔 Bell (Notification)</option>
                                        <option value="fas fa-bullhorn">📢 Bullhorn (Announcement)</option>
                                        <option value="fas fa-handshake">🤝 Handshake (Partnership)</option>
                                        <option value="fas fa-coffee">☕ Coffee (Meeting)</option>
                                        <option value="fas fa-laptop">💻 Laptop (Online)</option>
                                        <option value="fas fa-mobile-alt">📱 Mobile (Mobile Support)</option>
                                        <option value="fas fa-tablet-alt">📱 Tablet (Digital)</option>
                                        <option value="fas fa-wifi">📶 WiFi (Online Services)</option>
                                        <option value="fas fa-video">📹 Video (Video Call)</option>
                                        <option value="fas fa-camera">📷 Camera (Photo)</option>
                                        <option value="fas fa-microphone">🎤 Microphone (Voice)</option>
                                        <option value="fas fa-headphones">🎧 Headphones (Audio)</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea name="description" rows="2" placeholder="Brief description of this contact method" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact Info <span class="text-red-500">*</span></label>
                                    <input type="text" name="contact_info" required placeholder="e.g., +63 123 456 7890" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Additional Info</label>
                                    <input type="text" name="additional_info" placeholder="e.g., Monday - Friday, 8:00 AM - 5:00 PM" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                                <input type="number" name="sort_order" value="0" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="w-full bg-seait-orange text-white px-4 lg:px-6 py-2 lg:py-3 rounded-md hover:bg-orange-600 transition text-sm lg:text-base">
                                    <i class="fas fa-plus mr-2"></i>Add Contact Information
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Requirements and Programs Forms -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 mb-8">
                    <!-- Add Requirement Form -->
                    <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="p-2 bg-purple-100 rounded-full mr-3">
                                    <i class="fas fa-list-ol text-purple-600"></i>
                                </div>
                                <h2 class="text-lg lg:text-xl font-semibold text-seait-dark">Add Admission Requirement</h2>
                            </div>
                            <button type="button" onclick="toggleForm('requirement-form')" class="text-seait-orange hover:text-orange-600 p-2 rounded-full hover:bg-orange-50 transition">
                                <i class="fas fa-chevron-down" id="requirement-toggle-icon"></i>
                            </button>
                        </div>
                        <form method="POST" id="requirement-form" class="space-y-4">
                            <input type="hidden" name="action" value="add_requirement">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Level <span class="text-red-500">*</span></label>
                                    <select name="level_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                        <option value="">Select Level</option>
                                        <?php
                                        mysqli_data_seek($levels_result, 0);
                                        while($level = mysqli_fetch_assoc($levels_result)):
                                        ?>
                                        <option value="<?php echo $level['id']; ?>"><?php echo htmlspecialchars($level['name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Step Number <span class="text-red-500">*</span></label>
                                    <input type="number" name="step_number" required min="1" placeholder="e.g., 1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                                <input type="text" name="title" required placeholder="e.g., Application Form" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea name="description" rows="3" placeholder="Detailed description of this requirement" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="w-full bg-seait-orange text-white px-4 lg:px-6 py-2 lg:py-3 rounded-md hover:bg-orange-600 transition text-sm lg:text-base">
                                    <i class="fas fa-plus mr-2"></i>Add Requirement
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Add Program Form -->
                    <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="p-2 bg-indigo-100 rounded-full mr-3">
                                    <i class="fas fa-graduation-cap text-indigo-600"></i>
                                </div>
                                <h2 class="text-lg lg:text-xl font-semibold text-seait-dark">Add Admission Program</h2>
                            </div>
                            <button type="button" onclick="toggleForm('program-form')" class="text-seait-orange hover:text-orange-600 p-2 rounded-full hover:bg-orange-50 transition">
                                <i class="fas fa-chevron-down" id="program-toggle-icon"></i>
                            </button>
                        </div>
                        <form method="POST" id="program-form" class="space-y-4">
                            <input type="hidden" name="action" value="add_program">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Level <span class="text-red-500">*</span></label>
                                <select name="level_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                    <option value="">Select Level</option>
                                    <?php
                                    mysqli_data_seek($levels_result, 0);
                                    while($level = mysqli_fetch_assoc($levels_result)):
                                    ?>
                                    <option value="<?php echo $level['id']; ?>"><?php echo htmlspecialchars($level['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Program Name <span class="text-red-500">*</span></label>
                                <input type="text" name="name" required placeholder="e.g., Bachelor of Science in Information Technology" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea name="description" rows="3" placeholder="Brief description of this program" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="w-full bg-seait-orange text-white px-4 lg:px-6 py-2 lg:py-3 rounded-md hover:bg-orange-600 transition text-sm lg:text-base">
                                    <i class="fas fa-plus mr-2"></i>Add Program
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Levels List -->
                <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6">
                    <h2 class="text-lg lg:text-xl font-semibold text-seait-dark mb-4">Manage Admission Levels</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Icon</th>
                                    <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level Name</th>
                                    <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                mysqli_data_seek($levels_result, 0);
                                while($level = mysqli_fetch_assoc($levels_result)):
                                ?>
                                <tr data-level-id="<?php echo $level['id']; ?>" data-level-name="<?php echo htmlspecialchars($level['name']); ?>" data-level-description="<?php echo htmlspecialchars($level['description']); ?>" data-level-icon="<?php echo htmlspecialchars($level['icon']); ?>" data-level-sort="<?php echo $level['sort_order']; ?>" data-level-active="<?php echo $level['is_active']; ?>">
                                    <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                        <?php if (!empty($level['icon'])): ?>
                                        <i class="<?php echo htmlspecialchars($level['icon']); ?> text-seait-orange text-xl"></i>
                                        <?php else: ?>
                                        <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                            <i class="fas fa-graduation-cap text-gray-400"></i>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($level['name']); ?></div>
                                    </td>
                                    <td class="hidden md:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars(substr($level['description'], 0, 100)) . (strlen($level['description']) > 100 ? '...' : ''); ?>
                                    </td>
                                    <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $level['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $level['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="px-3 lg:px-6 py-4 whitespace-nowrap text-xs lg:text-sm font-medium">
                                        <button onclick="editLevel(<?php echo $level['id']; ?>)" class="text-seait-orange hover:text-orange-600 mr-3">Edit</button>
                                        <button onclick="confirmDeleteLevel(<?php echo $level['id']; ?>, '<?php echo htmlspecialchars($level['name']); ?>')" class="text-red-600 hover:text-red-800">Delete</button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Contacts List -->
                <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6 mt-8">
                    <h2 class="text-lg lg:text-xl font-semibold text-seait-dark mb-4">Manage Contact Information</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Icon</th>
                                    <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                    <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Info</th>
                                    <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Additional Info</th>
                                    <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php while($contact = mysqli_fetch_assoc($contacts_result)): ?>
                                <tr data-contact-id="<?php echo $contact['id']; ?>" data-contact-title="<?php echo htmlspecialchars($contact['title']); ?>" data-contact-description="<?php echo htmlspecialchars($contact['description']); ?>" data-contact-info="<?php echo htmlspecialchars($contact['contact_info']); ?>" data-contact-additional="<?php echo htmlspecialchars($contact['additional_info']); ?>" data-contact-icon="<?php echo htmlspecialchars($contact['icon']); ?>" data-contact-sort="<?php echo $contact['sort_order']; ?>" data-contact-active="<?php echo $contact['is_active']; ?>">
                                    <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                        <?php if (!empty($contact['icon'])): ?>
                                        <i class="<?php echo htmlspecialchars($contact['icon']); ?> text-seait-orange text-xl"></i>
                                        <?php else: ?>
                                        <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                            <i class="fas fa-phone text-gray-400"></i>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($contact['title']); ?></div>
                                    </td>
                                    <td class="hidden md:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($contact['contact_info']); ?>
                                    </td>
                                    <td class="hidden lg:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($contact['additional_info']); ?>
                                    </td>
                                    <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $contact['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $contact['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="px-3 lg:px-6 py-4 whitespace-nowrap text-xs lg:text-sm font-medium">
                                        <button onclick="editContact(<?php echo $contact['id']; ?>)" class="text-seait-orange hover:text-orange-600 mr-3">Edit</button>
                                        <button onclick="confirmDeleteContact(<?php echo $contact['id']; ?>, '<?php echo htmlspecialchars($contact['title']); ?>')" class="text-red-600 hover:text-red-800">Delete</button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Level Modal -->
    <div id="editLevelModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-4 sm:top-20 mx-auto p-4 sm:p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Admission Level</h3>
                <form id="editLevelForm" method="POST">
                    <input type="hidden" name="action" value="update_level">
                    <input type="hidden" name="level_id" id="editLevelId">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Level Name *</label>
                            <input type="text" name="name" id="editLevelName" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Icon Class</label>
                            <select name="icon" id="editLevelIcon" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="">Select an icon</option>
                                <option value="fas fa-child">👶 Child (Basic Education)</option>
                                <option value="fas fa-graduation-cap">🎓 Graduation Cap (Undergraduate)</option>
                                <option value="fas fa-user-graduate">👨‍🎓 User Graduate (Graduate)</option>
                                <option value="fas fa-user-tie">👔 User Tie (Postgraduate)</option>
                                <option value="fas fa-book">📚 Book (Education)</option>
                                <option value="fas fa-school">🏫 School (Institution)</option>
                                <option value="fas fa-university">🏛️ University (Higher Education)</option>
                                <option value="fas fa-chalkboard-teacher">👨‍🏫 Chalkboard Teacher (Teaching)</option>
                                <option value="fas fa-users">👥 Users (Group)</option>
                                <option value="fas fa-star">⭐ Star (Featured)</option>
                                <option value="fas fa-award">🏆 Award (Achievement)</option>
                                <option value="fas fa-lightbulb">💡 Lightbulb (Innovation)</option>
                                <option value="fas fa-rocket">🚀 Rocket (Advancement)</option>
                                <option value="fas fa-heart">❤️ Heart (Care)</option>
                                <option value="fas fa-shield-alt">🛡️ Shield (Protection)</option>
                                <option value="fas fa-globe">🌍 Globe (International)</option>
                                <option value="fas fa-home">🏠 Home (Foundation)</option>
                                <option value="fas fa-tree">🌳 Tree (Growth)</option>
                                <option value="fas fa-seedling">🌱 Seedling (Development)</option>
                                <option value="fas fa-leaf">🍃 Leaf (Nature)</option>
                                <option value="fas fa-sun">☀️ Sun (Bright Future)</option>
                                <option value="fas fa-moon">🌙 Moon (Night Classes)</option>
                                <option value="fas fa-clock">⏰ Clock (Time)</option>
                                <option value="fas fa-calendar">📅 Calendar (Schedule)</option>
                                <option value="fas fa-map-marker-alt">📍 Map Marker (Location)</option>
                                <option value="fas fa-flag">🏁 Flag (Goal)</option>
                                <option value="fas fa-trophy">🏆 Trophy (Excellence)</option>
                                <option value="fas fa-medal">🥇 Medal (Achievement)</option>
                                <option value="fas fa-certificate">📜 Certificate (Qualification)</option>
                                <option value="fas fa-diploma">🎓 Diploma (Degree)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                            <input type="number" name="sort_order" id="editLevelSortOrder" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" id="editLevelDescription" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" id="editLevelIsActive" class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                                <span class="ml-2 block text-sm text-gray-900">Active</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 mt-6">
                        <button type="button" onclick="closeEditLevelModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition w-full sm:w-auto">Cancel</button>
                        <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition w-full sm:w-auto">Update Level</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Level Confirmation Modal -->
    <div id="deleteLevelModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Admission Level</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete "<span id="deleteLevelName" class="font-semibold"></span>"? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Level will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-link mr-2 text-red-500"></i>
                                    All associated requirements will be deleted
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form id="deleteLevelForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_level">
                        <input type="hidden" name="level_id" id="deleteLevelId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteLevelModal()"
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

    <!-- Edit Contact Modal -->
    <div id="editContactModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-4 sm:top-20 mx-auto p-4 sm:p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Admission Contact</h3>
                <form id="editContactForm" method="POST">
                    <input type="hidden" name="action" value="update_contact">
                    <input type="hidden" name="contact_id" id="editContactId">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                            <input type="text" name="title" id="editContactTitle" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Icon Class</label>
                            <select name="icon" id="editContactIcon" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="">Select an icon</option>
                                <option value="fas fa-phone">📞 Phone (Call)</option>
                                <option value="fas fa-envelope">✉️ Envelope (Email)</option>
                                <option value="fas fa-users">👥 Users (Team)</option>
                                <option value="fas fa-user">👤 User (Person)</option>
                                <option value="fas fa-user-tie">👔 User Tie (Professional)</option>
                                <option value="fas fa-headset">🎧 Headset (Support)</option>
                                <option value="fas fa-comments">💬 Comments (Chat)</option>
                                <option value="fas fa-comment">💭 Comment (Message)</option>
                                <option value="fas fa-info-circle">ℹ️ Info Circle (Information)</option>
                                <option value="fas fa-question-circle">❓ Question Circle (Help)</option>
                                <option value="fas fa-life-ring">🛟 Life Ring (Support)</option>
                                <option value="fas fa-hands-helping">🤝 Hands Helping (Assistance)</option>
                                <option value="fas fa-clock">⏰ Clock (Hours)</option>
                                <option value="fas fa-calendar">📅 Calendar (Schedule)</option>
                                <option value="fas fa-map-marker-alt">📍 Map Marker (Location)</option>
                                <option value="fas fa-building">🏢 Building (Office)</option>
                                <option value="fas fa-home">🏠 Home (Main Office)</option>
                                <option value="fas fa-university">🏛️ University (Campus)</option>
                                <option value="fas fa-graduation-cap">🎓 Graduation Cap (Academic)</option>
                                <option value="fas fa-book">📚 Book (Education)</option>
                                <option value="fas fa-chalkboard-teacher">👨‍🏫 Chalkboard Teacher (Teaching)</option>
                                <option value="fas fa-student">👨‍🎓 Student (Learner)</option>
                                <option value="fas fa-user-graduate">👨‍🎓 User Graduate (Graduate)</option>
                                <option value="fas fa-certificate">📜 Certificate (Qualification)</option>
                                <option value="fas fa-award">🏆 Award (Achievement)</option>
                                <option value="fas fa-star">⭐ Star (Featured)</option>
                                <option value="fas fa-heart">❤️ Heart (Care)</option>
                                <option value="fas fa-shield-alt">🛡️ Shield (Protection)</option>
                                <option value="fas fa-check-circle">✅ Check Circle (Approved)</option>
                                <option value="fas fa-exclamation-circle">⚠️ Exclamation Circle (Important)</option>
                                <option value="fas fa-lightbulb">💡 Lightbulb (Idea)</option>
                                <option value="fas fa-rocket">🚀 Rocket (Fast Response)</option>
                                <option value="fas fa-globe">🌍 Globe (International)</option>
                                <option value="fas fa-flag">🏁 Flag (Priority)</option>
                                <option value="fas fa-bell">🔔 Bell (Notification)</option>
                                <option value="fas fa-bullhorn">📢 Bullhorn (Announcement)</option>
                                <option value="fas fa-handshake">🤝 Handshake (Partnership)</option>
                                <option value="fas fa-coffee">☕ Coffee (Meeting)</option>
                                <option value="fas fa-laptop">💻 Laptop (Online)</option>
                                <option value="fas fa-mobile-alt">📱 Mobile (Mobile Support)</option>
                                <option value="fas fa-tablet-alt">📱 Tablet (Digital)</option>
                                <option value="fas fa-wifi">📶 WiFi (Online Services)</option>
                                <option value="fas fa-video">📹 Video (Video Call)</option>
                                <option value="fas fa-camera">📷 Camera (Photo)</option>
                                <option value="fas fa-microphone">🎤 Microphone (Voice)</option>
                                <option value="fas fa-headphones">🎧 Headphones (Audio)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact Information *</label>
                            <input type="text" name="contact_info" id="editContactInfo" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                            <input type="number" name="sort_order" id="editContactSortOrder" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" id="editContactDescription" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Additional Information</label>
                            <textarea name="additional_info" id="editContactAdditional" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" id="editContactIsActive" class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                                <span class="ml-2 block text-sm text-gray-900">Active</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 mt-6">
                        <button type="button" onclick="closeEditContactModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition w-full sm:w-auto">Cancel</button>
                        <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition w-full sm:w-auto">Update Contact</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Contact Confirmation Modal -->
    <div id="deleteContactModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Admission Contact</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete "<span id="deleteContactName" class="font-semibold"></span>"? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Contact will be permanently removed
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
                    <form id="deleteContactForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_contact">
                        <input type="hidden" name="contact_id" id="deleteContactId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteContactModal()"
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
        function toggleForm(formId) {
            const form = document.getElementById(formId);
            const toggleIcon = document.getElementById(formId.replace('-form', '-toggle-icon'));

            if (form.style.display === 'none') {
                form.style.display = 'block';
                toggleIcon.classList.remove('fa-chevron-up');
                toggleIcon.classList.add('fa-chevron-down');
            } else {
                form.style.display = 'none';
                toggleIcon.classList.remove('fa-chevron-down');
                toggleIcon.classList.add('fa-chevron-up');
            }
        }

        function editLevel(levelId) {
            const levelRow = document.querySelector(`tr[data-level-id="${levelId}"]`);
            const dataAttributes = levelRow.dataset;

            document.getElementById('editLevelId').value = levelId;
            document.getElementById('editLevelName').value = dataAttributes.levelName;
            document.getElementById('editLevelDescription').value = dataAttributes.levelDescription;
            document.getElementById('editLevelIcon').value = dataAttributes.levelIcon;
            document.getElementById('editLevelSortOrder').value = dataAttributes.levelSort;
            document.getElementById('editLevelIsActive').checked = dataAttributes.levelActive === '1';

            document.getElementById('editLevelModal').classList.remove('hidden');
        }

        function confirmDeleteLevel(levelId, levelName) {
            const deleteModal = document.getElementById('deleteLevelModal');
            const levelIdField = document.getElementById('deleteLevelId');
            const levelNameField = document.getElementById('deleteLevelName');
            if (deleteModal && levelIdField && levelNameField) {
                levelIdField.value = levelId;
                levelNameField.textContent = levelName;
                deleteModal.classList.remove('hidden');
            }
        }

        function closeEditLevelModal() {
            const editModal = document.getElementById('editLevelModal');
            if (editModal) {
                editModal.classList.add('hidden');
            }
            document.getElementById('editLevelForm').reset();
        }

        function closeDeleteLevelModal() {
            const deleteModal = document.getElementById('deleteLevelModal');
            if (deleteModal) {
                deleteModal.classList.add('hidden');
            }
            document.getElementById('deleteLevelId').value = '';
            document.getElementById('deleteLevelName').textContent = '';
        }

        function editContact(contactId) {
            const contactRow = document.querySelector(`tr[data-contact-id="${contactId}"]`);
            const dataAttributes = contactRow.dataset;

            document.getElementById('editContactId').value = contactId;
            document.getElementById('editContactTitle').value = dataAttributes.contactTitle;
            document.getElementById('editContactDescription').value = dataAttributes.contactDescription;
            document.getElementById('editContactInfo').value = dataAttributes.contactInfo;
            document.getElementById('editContactAdditional').value = dataAttributes.contactAdditional;
            document.getElementById('editContactIcon').value = dataAttributes.contactIcon;
            document.getElementById('editContactSortOrder').value = dataAttributes.contactSort;
            document.getElementById('editContactIsActive').checked = dataAttributes.contactActive === '1';

            document.getElementById('editContactModal').classList.remove('hidden');
        }

        function confirmDeleteContact(contactId, contactName) {
            const deleteModal = document.getElementById('deleteContactModal');
            const contactIdField = document.getElementById('deleteContactId');
            const contactNameField = document.getElementById('deleteContactName');
            if (deleteModal && contactIdField && contactNameField) {
                contactIdField.value = contactId;
                contactNameField.textContent = contactName;
                deleteModal.classList.remove('hidden');
            }
        }

        function closeEditContactModal() {
            const editModal = document.getElementById('editContactModal');
            if (editModal) {
                editModal.classList.add('hidden');
            }
            document.getElementById('editContactForm').reset();
        }

        function closeDeleteContactModal() {
            const deleteModal = document.getElementById('deleteContactModal');
            if (deleteModal) {
                deleteModal.classList.add('hidden');
            }
            document.getElementById('deleteContactId').value = '';
            document.getElementById('deleteContactName').textContent = '';
        }

        // Close modals when clicking outside
        const editLevelModal = document.getElementById('editLevelModal');
        const deleteLevelModal = document.getElementById('deleteLevelModal');
        const editContactModal = document.getElementById('editContactModal');
        const deleteContactModal = document.getElementById('deleteContactModal');

        if (editLevelModal) {
            editLevelModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeEditLevelModal();
                }
            });
        }

        if (deleteLevelModal) {
            deleteLevelModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteLevelModal();
                }
            });
        }

        if (editContactModal) {
            editContactModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeEditContactModal();
                }
            });
        }

        if (deleteContactModal) {
            deleteContactModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteContactModal();
                }
            });
        }
    </script>
</body>
</html>