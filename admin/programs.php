<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_admin();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize_input($_POST['name']);
                $description = $_POST['description']; // Don't sanitize HTML content
                $level = sanitize_input($_POST['level']);
                $duration = sanitize_input($_POST['duration']);
                $credits = (int)$_POST['credits'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                if (!empty($name)) {
                    $query = "INSERT INTO academic_programs (name, description, level, duration, credits, is_active) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "ssssii", $name, $description, $level, $duration, $credits, $is_active);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Program added successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error adding program.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Program name is required.";
                    $message_type = "error";
                }
                break;

            case 'update':
                $id = (int)$_POST['id'];
                $name = sanitize_input($_POST['name']);
                $description = $_POST['description'];
                $level = sanitize_input($_POST['level']);
                $duration = sanitize_input($_POST['duration']);
                $credits = (int)$_POST['credits'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                if (!empty($name)) {
                    $query = "UPDATE academic_programs SET name = ?, description = ?, level = ?, duration = ?, credits = ?, is_active = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "ssssiii", $name, $description, $level, $duration, $credits, $is_active, $id);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Program updated successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error updating program.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Program name is required.";
                    $message_type = "error";
                }
                break;

            case 'delete':
                $id = (int)$_POST['id'];

                $query = "DELETE FROM academic_programs WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "i", $id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = "Program deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error deleting program.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get programs
$query = "SELECT * FROM academic_programs ORDER BY name ASC";
$programs_result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Programs - Admin Dashboard</title>
    <link rel="icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="../assets/images/seait-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.ckeditor.com/ckeditor5/27.1.0/classic/ckeditor.js"></script>
    <style>
        @keyframes bounce-in {
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
            animation: bounce-in 0.6s ease-out;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/admin-header.php'; ?>

    <div class="flex pt-16">
        <?php include 'includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 ml-64 p-8 overflow-y-auto h-screen">
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-seait-dark mb-2">Academic Programs</h1>
                        <p class="text-gray-600">Manage academic programs and courses</p>
                    </div>
                    <button onclick="showAddModal()" class="bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                        <i class="fas fa-plus mr-2"></i>Add Program
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Programs Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while ($program = mysqli_fetch_assoc($programs_result)): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($program['name']); ?></h3>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                <?php echo $program['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                <?php echo $program['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>

                        <div class="space-y-2 text-sm text-gray-600 mb-4">
                            <div class="flex justify-between">
                                <span>Level:</span>
                                <span class="font-medium"><?php echo htmlspecialchars($program['level']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Duration:</span>
                                <span class="font-medium"><?php echo htmlspecialchars($program['duration']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Credits:</span>
                                <span class="font-medium"><?php echo $program['credits']; ?></span>
                            </div>
                        </div>

                        <div class="text-sm text-gray-600 mb-4">
                            <?php echo substr(strip_tags($program['description']), 0, 100) . '...'; ?>
                        </div>

                        <div class="flex space-x-2">
                            <button onclick="editProgram(<?php echo $program['id']; ?>)"
                                    class="flex-1 bg-blue-600 text-white px-3 py-2 rounded-md hover:bg-blue-700 transition text-sm">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </button>
                            <button onclick="deleteProgram(<?php echo $program['id']; ?>)"
                                    class="flex-1 bg-red-600 text-white px-3 py-2 rounded-md hover:bg-red-700 transition text-sm">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Program Modal -->
    <div id="programModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 id="modalTitle" class="text-lg font-medium text-gray-900">Add Program</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="programForm" method="POST">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="programId">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Program Name</label>
                            <input type="text" name="name" id="programName" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Level</label>
                            <select name="level" id="programLevel" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="">Select Level</option>
                                <option value="undergraduate">Undergraduate</option>
                                <option value="graduate">Graduate</option>
                                <option value="postgraduate">Postgraduate</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Duration</label>
                            <input type="text" name="duration" id="programDuration" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                                   placeholder="e.g., 4 years">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Credits</label>
                            <input type="number" name="credits" id="programCredits" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                                   min="1">
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="programActive" class="mr-2">
                            <label for="programActive" class="text-sm font-medium text-gray-700">Active</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" id="programDescription" rows="6" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                            <i class="fas fa-save mr-2"></i>Save Program
                        </button>
                        <button type="button" onclick="closeModal()" class="flex-1 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Program Confirmation Modal -->
    <div id="deleteProgramModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Program</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete this program? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Program will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    No longer visible to students
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteProgramId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteProgramModal()"
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
        let editor;

        ClassicEditor
            .create(document.querySelector('#programDescription'), {
                toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'outdent', 'indent', '|', 'blockQuote', 'undo', 'redo'],
                heading: {
                    options: [
                        { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                        { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                        { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                        { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' }
                    ]
                }
            })
            .then(newEditor => {
                editor = newEditor;
            })
            .catch(error => {
                console.error(error);
            });

        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Program';
            document.getElementById('formAction').value = 'add';
            document.getElementById('programForm').reset();
            document.getElementById('programId').value = '';
            if (editor) {
                editor.setData('');
            }
            document.getElementById('programModal').classList.remove('hidden');
        }

        function editProgram(id) {
            // Fetch program data via AJAX
            fetch(`get_program.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('modalTitle').textContent = 'Edit Program';
                    document.getElementById('formAction').value = 'update';
                    document.getElementById('programId').value = data.id;
                    document.getElementById('programName').value = data.name;
                    document.getElementById('programLevel').value = data.level;
                    document.getElementById('programDuration').value = data.duration;
                    document.getElementById('programCredits').value = data.credits;
                    document.getElementById('programActive').checked = data.is_active == 1;
                    if (editor) {
                        editor.setData(data.description);
                    }
                    document.getElementById('programModal').classList.remove('hidden');
                });
        }

        function deleteProgram(id) {
            document.getElementById('deleteProgramId').value = id;
            document.getElementById('deleteProgramModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('programModal').classList.add('hidden');
        }

        function closeDeleteProgramModal() {
            document.getElementById('deleteProgramModal').classList.add('hidden');
        }

        // Close delete program modal when clicking outside
        const deleteProgramModal = document.getElementById('deleteProgramModal');
        if (deleteProgramModal) {
            deleteProgramModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteProgramModal();
                }
            });
        }

        // Update form data before submission
        document.getElementById('programForm').addEventListener('submit', function(e) {
            if (editor) {
                const descriptionField = document.getElementById('programDescription');
                descriptionField.value = editor.getData();
            }
        });
    </script>
</body>
</html>