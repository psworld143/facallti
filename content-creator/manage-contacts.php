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
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'departments';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_department') {
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $icon = mysqli_real_escape_string($conn, $_POST['icon']);
            $color_theme = mysqli_real_escape_string($conn, $_POST['color_theme']);
            $sort_order = (int)$_POST['sort_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $query = "INSERT INTO departments (name, description, icon, color_theme, sort_order, is_active, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ssssiis', $name, $description, $icon, $color_theme, $sort_order, $is_active, $_SESSION['user_id']);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Department added successfully!';
            } else {
                $error = 'Failed to add department.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'edit_department') {
            $id = (int)$_POST['department_id'];
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $icon = mysqli_real_escape_string($conn, $_POST['icon']);
            $color_theme = mysqli_real_escape_string($conn, $_POST['color_theme']);
            $sort_order = (int)$_POST['sort_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $query = "UPDATE departments SET name = ?, description = ?, icon = ?, color_theme = ?, sort_order = ?, is_active = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ssssiis', $name, $description, $icon, $color_theme, $sort_order, $is_active, $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Department updated successfully!';
            } else {
                $error = 'Failed to update department.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'delete_department') {
            $id = (int)$_POST['department_id'];

            $query = "DELETE FROM departments WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Department deleted successfully!';
            } else {
                $error = 'Failed to delete department.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'add_contact') {
            $department_id = (int)$_POST['department_id'];
            $contact_type = mysqli_real_escape_string($conn, $_POST['contact_type']);
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            $contact_value = mysqli_real_escape_string($conn, $_POST['contact_value']);
            $icon = mysqli_real_escape_string($conn, $_POST['icon']);
            $sort_order = (int)$_POST['sort_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $query = "INSERT INTO department_contacts (department_id, contact_type, title, contact_value, icon, sort_order, is_active, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'issssiis', $department_id, $contact_type, $title, $contact_value, $icon, $sort_order, $is_active, $_SESSION['user_id']);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Contact added successfully!';
            } else {
                $error = 'Failed to add contact.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'edit_contact') {
            $id = (int)$_POST['contact_id'];
            $department_id = (int)$_POST['department_id'];
            $contact_type = mysqli_real_escape_string($conn, $_POST['contact_type']);
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            $contact_value = mysqli_real_escape_string($conn, $_POST['contact_value']);
            $icon = mysqli_real_escape_string($conn, $_POST['icon']);
            $sort_order = (int)$_POST['sort_order'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $query = "UPDATE department_contacts SET department_id = ?, contact_type = ?, title = ?, contact_value = ?, icon = ?, sort_order = ?, is_active = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'issssiis', $department_id, $contact_type, $title, $contact_value, $icon, $sort_order, $is_active, $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Contact updated successfully!';
            } else {
                $error = 'Failed to update contact.';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] === 'delete_contact') {
            $id = (int)$_POST['contact_id'];

            $query = "DELETE FROM department_contacts WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $id);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Contact deleted successfully!';
            } else {
                $error = 'Failed to delete contact.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get data for display
$departments_query = "SELECT * FROM departments ORDER BY sort_order ASC, name ASC";
$departments_result = mysqli_query($conn, $departments_query);

$contacts_query = "SELECT dc.*, d.name as department_name
                  FROM department_contacts dc
                  JOIN departments d ON dc.department_id = d.id
                  ORDER BY d.sort_order ASC, dc.sort_order ASC";
$contacts_result = mysqli_query($conn, $contacts_query);
?>

<?php
$page_title = 'Manage Contacts';
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
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Manage Contacts</h1>
                    <p class="text-gray-600">Manage departments and their contact information</p>
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
                            <h3 class="text-sm font-medium text-blue-800 mb-2">Contact Management</h3>
                            <div class="text-sm text-blue-700 space-y-1">
                                <p><strong>Departments:</strong> Create and manage institutional departments with custom icons and color themes.</p>
                                <p><strong>Contact Information:</strong> Add detailed contact information for each department including phone, email, and location.</p>
                                <p><strong>Organization:</strong> Use the tabs to switch between departments and contact information management.</p>
                                <p><strong>Display:</strong> Contact information will be displayed on the website for visitors to easily reach the appropriate departments.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white rounded-lg shadow-sm mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="flex space-x-8 px-6">
                            <a href="?tab=departments" class="py-4 px-1 border-b-2 font-medium text-sm <?php echo $active_tab === 'departments' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                Departments
                            </a>
                            <a href="?tab=contacts" class="py-4 px-1 border-b-2 font-medium text-sm <?php echo $active_tab === 'contacts' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                Contact Information
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Tab Content -->
                <?php if ($active_tab === 'departments'): ?>
                    <!-- Departments Tab -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-seait-dark">Departments</h2>
                            <button onclick="openModal('addDepartmentModal')" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition">
                                <i class="fas fa-plus mr-2"></i>Add Department
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Icon</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Color</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sort Order</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($row = mysqli_fetch_assoc($departments_result)): ?>
                                        <tr data-department-id="<?php echo $row['id']; ?>" data-department-name="<?php echo htmlspecialchars($row['name']); ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <i class="<?php echo $row['icon']; ?> text-2xl" style="color: <?php echo $row['color_theme']; ?>"></i>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo $row['name']; ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?php echo substr($row['description'], 0, 100); ?>...</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="w-6 h-6 rounded-full" style="background-color: <?php echo $row['color_theme']; ?>"></div>
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
                                                <button onclick="editDepartment(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="text-seait-orange hover:text-orange-600 mr-3">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="deleteDepartment(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($active_tab === 'contacts'): ?>
                    <!-- Contacts Tab -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-seait-dark">Contact Information</h2>
                            <button onclick="openModal('addContactModal')" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition">
                                <i class="fas fa-plus mr-2"></i>Add Contact
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Value</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Icon</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sort Order</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($row = mysqli_fetch_assoc($contacts_result)): ?>
                                        <tr data-contact-id="<?php echo $row['id']; ?>" data-contact-title="<?php echo htmlspecialchars($row['title']); ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo $row['department_name']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    <?php echo ucfirst(str_replace('_', ' ', $row['contact_type'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo $row['title']; ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?php echo substr($row['contact_value'], 0, 50); ?>...</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <i class="<?php echo $row['icon']; ?> text-lg text-gray-600"></i>
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
                                                <button onclick="editContact(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="text-seait-orange hover:text-orange-600 mr-3">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="deleteContact(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-900">
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

    <!-- Add Department Modal -->
    <div id="addDepartmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Department</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_department">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department Name</label>
                        <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Icon</label>
                        <select name="icon" id="departmentIconSelect" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange icon-select">
                            <option value="">Select Icon</option>
                            <option value="fas fa-building" data-icon="fas fa-building">🏢 Building</option>
                            <option value="fas fa-graduation-cap" data-icon="fas fa-graduation-cap">🎓 Graduation Cap</option>
                            <option value="fas fa-university" data-icon="fas fa-university">🏛️ University</option>
                            <option value="fas fa-school" data-icon="fas fa-school">🏫 School</option>
                            <option value="fas fa-users" data-icon="fas fa-users">👥 Users</option>
                            <option value="fas fa-user-graduate" data-icon="fas fa-user-graduate">🎓 User Graduate</option>
                            <option value="fas fa-chalkboard-teacher" data-icon="fas fa-chalkboard-teacher">👨‍🏫 Teacher</option>
                            <option value="fas fa-book" data-icon="fas fa-book">📚 Book</option>
                            <option value="fas fa-microscope" data-icon="fas fa-microscope">🔬 Microscope</option>
                            <option value="fas fa-flask" data-icon="fas fa-flask">🧪 Flask</option>
                            <option value="fas fa-laptop-code" data-icon="fas fa-laptop-code">💻 Laptop Code</option>
                            <option value="fas fa-cogs" data-icon="fas fa-cogs">⚙️ Cogs</option>
                            <option value="fas fa-chart-line" data-icon="fas fa-chart-line">📈 Chart Line</option>
                            <option value="fas fa-briefcase" data-icon="fas fa-briefcase">💼 Briefcase</option>
                            <option value="fas fa-heartbeat" data-icon="fas fa-heartbeat">💓 Heartbeat</option>
                            <option value="fas fa-stethoscope" data-icon="fas fa-stethoscope">🩺 Stethoscope</option>
                            <option value="fas fa-pills" data-icon="fas fa-pills">💊 Pills</option>
                            <option value="fas fa-hospital" data-icon="fas fa-hospital">🏥 Hospital</option>
                            <option value="fas fa-ambulance" data-icon="fas fa-ambulance">🚑 Ambulance</option>
                            <option value="fas fa-user-md" data-icon="fas fa-user-md">👨‍⚕️ User MD</option>
                            <option value="fas fa-user-nurse" data-icon="fas fa-user-nurse">👩‍⚕️ User Nurse</option>
                            <option value="fas fa-tooth" data-icon="fas fa-tooth">🦷 Tooth</option>
                            <option value="fas fa-eye" data-icon="fas fa-eye">👁️ Eye</option>
                            <option value="fas fa-brain" data-icon="fas fa-brain">🧠 Brain</option>
                            <option value="fas fa-dna" data-icon="fas fa-dna">🧬 DNA</option>
                            <option value="fas fa-virus" data-icon="fas fa-virus">🦠 Virus</option>
                            <option value="fas fa-shield-virus" data-icon="fas fa-shield-virus">🛡️ Shield Virus</option>
                            <option value="fas fa-syringe" data-icon="fas fa-syringe">💉 Syringe</option>
                            <option value="fas fa-thermometer-half" data-icon="fas fa-thermometer-half">🌡️ Thermometer</option>
                            <option value="fas fa-heart" data-icon="fas fa-heart">❤️ Heart</option>
                            <option value="fas fa-lungs" data-icon="fas fa-lungs">🫁 Lungs</option>
                            <option value="fas fa-kidney" data-icon="fas fa-kidney">🫘 Kidney</option>
                            <option value="fas fa-liver" data-icon="fas fa-liver">🫘 Liver</option>
                            <option value="fas fa-stomach" data-icon="fas fa-stomach">🫃 Stomach</option>
                            <option value="fas fa-spine" data-icon="fas fa-spine">🦴 Spine</option>
                            <option value="fas fa-bone" data-icon="fas fa-bone">🦴 Bone</option>
                            <option value="fas fa-muscle" data-icon="fas fa-muscle">💪 Muscle</option>
                            <option value="fas fa-nerve" data-icon="fas fa-nerve">🫀 Nerve</option>
                            <option value="fas fa-cell" data-icon="fas fa-cell">🔬 Cell</option>
                            <option value="fas fa-atom" data-icon="fas fa-atom">⚛️ Atom</option>
                            <option value="fas fa-molecule" data-icon="fas fa-molecule">🧪 Molecule</option>
                            <option value="fas fa-droplet" data-icon="fas fa-droplet">💧 Droplet</option>
                            <option value="fas fa-capsules" data-icon="fas fa-capsules">💊 Capsules</option>
                            <option value="fas fa-tablets" data-icon="fas fa-tablets">💊 Tablets</option>
                            <option value="fas fa-prescription-bottle" data-icon="fas fa-prescription-bottle">💊 Prescription Bottle</option>
                            <option value="fas fa-prescription-bottle-medical" data-icon="fas fa-prescription-bottle-medical">💊 Prescription Bottle Medical</option>
                            <option value="fas fa-band-aid" data-icon="fas fa-band-aid">🩹 Band Aid</option>
                            <option value="fas fa-first-aid" data-icon="fas fa-first-aid">🩺 First Aid</option>
                            <option value="fas fa-procedures" data-icon="fas fa-procedures">🏥 Procedures</option>
                            <option value="fas fa-user-injured" data-icon="fas fa-user-injured">🤕 User Injured</option>
                            <option value="fas fa-wheelchair" data-icon="fas fa-wheelchair">♿ Wheelchair</option>
                            <option value="fas fa-blind" data-icon="fas fa-blind">👨‍🦯 Blind</option>
                            <option value="fas fa-deaf" data-icon="fas fa-deaf">👨‍🦻 Deaf</option>
                            <option value="fas fa-sign-language" data-icon="fas fa-sign-language">🤟 Sign Language</option>
                            <option value="fas fa-accessible-icon" data-icon="fas fa-accessible-icon">♿ Accessible Icon</option>
                            <option value="fas fa-universal-access" data-icon="fas fa-universal-access">♿ Universal Access</option>
                            <option value="fas fa-assistive-listening-systems" data-icon="fas fa-assistive-listening-systems">🔊 Assistive Listening Systems</option>
                            <option value="fas fa-american-sign-language-interpreting" data-icon="fas fa-american-sign-language-interpreting">🤟 American Sign Language Interpreting</option>
                            <option value="fas fa-deafness" data-icon="fas fa-deafness">👨‍🦻 Deafness</option>
                            <option value="fas fa-hard-of-hearing" data-icon="fas fa-hard-of-hearing">👨‍🦻 Hard of Hearing</option>
                            <option value="fas fa-low-vision" data-icon="fas fa-low-vision">👁️ Low Vision</option>
                            <option value="fas fa-eye-slash" data-icon="fas fa-eye-slash">🙈 Eye Slash</option>
                            <option value="fas fa-eye-low-vision" data-icon="fas fa-eye-low-vision">👁️ Eye Low Vision</option>
                            <option value="fas fa-eye-dropper" data-icon="fas fa-eye-dropper">💧 Eye Dropper</option>
                            <option value="fas fa-glasses" data-icon="fas fa-glasses">👓 Glasses</option>
                            <option value="fas fa-sunglasses" data-icon="fas fa-sunglasses">🕶️ Sunglasses</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Color Theme</label>
                        <input type="color" name="color_theme" value="#FF6B35" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
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
                        <button type="button" onclick="closeModal('addDepartmentModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Add Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div id="editDepartmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Department</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="edit_department">
                    <input type="hidden" name="department_id" id="edit_department_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department Name</label>
                        <input type="text" name="name" id="edit_department_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" id="edit_department_description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Icon</label>
                        <select name="icon" id="edit_department_icon" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Icon</option>
                            <option value="fas fa-building" data-icon="fas fa-building">🏢 Building</option>
                            <option value="fas fa-graduation-cap" data-icon="fas fa-graduation-cap">🎓 Graduation Cap</option>
                            <option value="fas fa-university" data-icon="fas fa-university">🏛️ University</option>
                            <option value="fas fa-school" data-icon="fas fa-school">🏫 School</option>
                            <option value="fas fa-users" data-icon="fas fa-users">👥 Users</option>
                            <option value="fas fa-user-graduate" data-icon="fas fa-user-graduate">🎓 User Graduate</option>
                            <option value="fas fa-chalkboard-teacher" data-icon="fas fa-chalkboard-teacher">👨‍🏫 Teacher</option>
                            <option value="fas fa-book" data-icon="fas fa-book">📚 Book</option>
                            <option value="fas fa-microscope" data-icon="fas fa-microscope">🔬 Microscope</option>
                            <option value="fas fa-flask" data-icon="fas fa-flask">🧪 Flask</option>
                            <option value="fas fa-laptop-code" data-icon="fas fa-laptop-code">💻 Laptop Code</option>
                            <option value="fas fa-cogs" data-icon="fas fa-cogs">⚙️ Cogs</option>
                            <option value="fas fa-chart-line" data-icon="fas fa-chart-line">📈 Chart Line</option>
                            <option value="fas fa-briefcase" data-icon="fas fa-briefcase">💼 Briefcase</option>
                            <option value="fas fa-heartbeat" data-icon="fas fa-heartbeat">💓 Heartbeat</option>
                            <option value="fas fa-stethoscope" data-icon="fas fa-stethoscope">🩺 Stethoscope</option>
                            <option value="fas fa-pills" data-icon="fas fa-pills">💊 Pills</option>
                            <option value="fas fa-hospital" data-icon="fas fa-hospital">🏥 Hospital</option>
                            <option value="fas fa-ambulance" data-icon="fas fa-ambulance">🚑 Ambulance</option>
                            <option value="fas fa-user-md" data-icon="fas fa-user-md">👨‍⚕️ User MD</option>
                            <option value="fas fa-user-nurse" data-icon="fas fa-user-nurse">👩‍⚕️ User Nurse</option>
                            <option value="fas fa-tooth" data-icon="fas fa-tooth">🦷 Tooth</option>
                            <option value="fas fa-eye" data-icon="fas fa-eye">👁️ Eye</option>
                            <option value="fas fa-brain" data-icon="fas fa-brain">🧠 Brain</option>
                            <option value="fas fa-dna" data-icon="fas fa-dna">🧬 DNA</option>
                            <option value="fas fa-virus" data-icon="fas fa-virus">🦠 Virus</option>
                            <option value="fas fa-shield-virus" data-icon="fas fa-shield-virus">🛡️ Shield Virus</option>
                            <option value="fas fa-syringe" data-icon="fas fa-syringe">💉 Syringe</option>
                            <option value="fas fa-thermometer-half" data-icon="fas fa-thermometer-half">🌡️ Thermometer</option>
                            <option value="fas fa-heart" data-icon="fas fa-heart">❤️ Heart</option>
                            <option value="fas fa-lungs" data-icon="fas fa-lungs">🫁 Lungs</option>
                            <option value="fas fa-kidney" data-icon="fas fa-kidney">🫘 Kidney</option>
                            <option value="fas fa-liver" data-icon="fas fa-liver">🫘 Liver</option>
                            <option value="fas fa-stomach" data-icon="fas fa-stomach">🫃 Stomach</option>
                            <option value="fas fa-spine" data-icon="fas fa-spine">🦴 Spine</option>
                            <option value="fas fa-bone" data-icon="fas fa-bone">🦴 Bone</option>
                            <option value="fas fa-muscle" data-icon="fas fa-muscle">💪 Muscle</option>
                            <option value="fas fa-nerve" data-icon="fas fa-nerve">🫀 Nerve</option>
                            <option value="fas fa-cell" data-icon="fas fa-cell">🔬 Cell</option>
                            <option value="fas fa-atom" data-icon="fas fa-atom">⚛️ Atom</option>
                            <option value="fas fa-molecule" data-icon="fas fa-molecule">🧪 Molecule</option>
                            <option value="fas fa-droplet" data-icon="fas fa-droplet">💧 Droplet</option>
                            <option value="fas fa-capsules" data-icon="fas fa-capsules">💊 Capsules</option>
                            <option value="fas fa-tablets" data-icon="fas fa-tablets">💊 Tablets</option>
                            <option value="fas fa-prescription-bottle" data-icon="fas fa-prescription-bottle">💊 Prescription Bottle</option>
                            <option value="fas fa-prescription-bottle-medical" data-icon="fas fa-prescription-bottle-medical">💊 Prescription Bottle Medical</option>
                            <option value="fas fa-band-aid" data-icon="fas fa-band-aid">🩹 Band Aid</option>
                            <option value="fas fa-first-aid" data-icon="fas fa-first-aid">🩺 First Aid</option>
                            <option value="fas fa-procedures" data-icon="fas fa-procedures">🏥 Procedures</option>
                            <option value="fas fa-user-injured" data-icon="fas fa-user-injured">🤕 User Injured</option>
                            <option value="fas fa-wheelchair" data-icon="fas fa-wheelchair">♿ Wheelchair</option>
                            <option value="fas fa-blind" data-icon="fas fa-blind">👨‍🦯 Blind</option>
                            <option value="fas fa-deaf" data-icon="fas fa-deaf">👨‍🦻 Deaf</option>
                            <option value="fas fa-sign-language" data-icon="fas fa-sign-language">🤟 Sign Language</option>
                            <option value="fas fa-accessible-icon" data-icon="fas fa-accessible-icon">♿ Accessible Icon</option>
                            <option value="fas fa-universal-access" data-icon="fas fa-universal-access">♿ Universal Access</option>
                            <option value="fas fa-assistive-listening-systems" data-icon="fas fa-assistive-listening-systems">🔊 Assistive Listening Systems</option>
                            <option value="fas fa-american-sign-language-interpreting" data-icon="fas fa-american-sign-language-interpreting">🤟 American Sign Language Interpreting</option>
                            <option value="fas fa-deafness" data-icon="fas fa-deafness">👨‍🦻 Deafness</option>
                            <option value="fas fa-hard-of-hearing" data-icon="fas fa-hard-of-hearing">👨‍🦻 Hard of Hearing</option>
                            <option value="fas fa-low-vision" data-icon="fas fa-low-vision">👁️ Low Vision</option>
                            <option value="fas fa-eye-slash" data-icon="fas fa-eye-slash">🙈 Eye Slash</option>
                            <option value="fas fa-eye-low-vision" data-icon="fas fa-eye-low-vision">👁️ Eye Low Vision</option>
                            <option value="fas fa-eye-dropper" data-icon="fas fa-eye-dropper">💧 Eye Dropper</option>
                            <option value="fas fa-glasses" data-icon="fas fa-glasses">👓 Glasses</option>
                            <option value="fas fa-sunglasses" data-icon="fas fa-sunglasses">🕶️ Sunglasses</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Color Theme</label>
                        <input type="color" name="color_theme" id="edit_department_color_theme" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" id="edit_department_sort_order" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" id="edit_department_is_active" class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('editDepartmentModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Update Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Contact Modal -->
    <div id="addContactModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Contact</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_contact">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <select name="department_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Department</option>
                            <?php
                            mysqli_data_seek($departments_result, 0);
                            while($dept = mysqli_fetch_assoc($departments_result)):
                            ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Type</label>
                        <select name="contact_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="phone">Phone</option>
                            <option value="email">Email</option>
                            <option value="address">Address</option>
                            <option value="social_media">Social Media</option>
                            <option value="website">Website</option>
                            <option value="office_hours">Office Hours</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                        <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Value</label>
                        <textarea name="contact_value" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Icon</label>
                        <select name="icon" id="contactIconSelect" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange icon-select">
                            <option value="">Select Icon</option>
                            <option value="fas fa-phone" data-icon="fas fa-phone">📞 Phone</option>
                            <option value="fas fa-envelope" data-icon="fas fa-envelope">✉️ Email</option>
                            <option value="fas fa-map-marker-alt" data-icon="fas fa-map-marker-alt">📍 Location</option>
                            <option value="fas fa-globe" data-icon="fas fa-globe">🌐 Website</option>
                            <option value="fas fa-clock" data-icon="fas fa-clock">🕐 Office Hours</option>
                            <option value="fab fa-facebook" data-icon="fab fa-facebook">📘 Facebook</option>
                            <option value="fab fa-twitter" data-icon="fab fa-twitter">🐦 Twitter</option>
                            <option value="fab fa-instagram" data-icon="fab fa-instagram">📷 Instagram</option>
                            <option value="fab fa-linkedin" data-icon="fab fa-linkedin">💼 LinkedIn</option>
                            <option value="fab fa-youtube" data-icon="fab fa-youtube">📺 YouTube</option>
                            <option value="fab fa-whatsapp" data-icon="fab fa-whatsapp">💬 WhatsApp</option>
                            <option value="fab fa-telegram" data-icon="fab fa-telegram">📱 Telegram</option>
                            <option value="fab fa-discord" data-icon="fab fa-discord">🎮 Discord</option>
                            <option value="fab fa-slack" data-icon="fab fa-slack">💬 Slack</option>
                            <option value="fab fa-skype" data-icon="fab fa-skype">📞 Skype</option>
                            <option value="fab fa-zoom" data-icon="fab fa-zoom">📹 Zoom</option>
                            <option value="fab fa-google" data-icon="fab fa-google">🔍 Google</option>
                            <option value="fab fa-microsoft" data-icon="fab fa-microsoft">🪟 Microsoft</option>
                            <option value="fab fa-apple" data-icon="fab fa-apple">🍎 Apple</option>
                            <option value="fab fa-android" data-icon="fab fa-android">🤖 Android</option>
                            <option value="fab fa-chrome" data-icon="fab fa-chrome">🌐 Chrome</option>
                            <option value="fab fa-firefox" data-icon="fab fa-firefox">🦊 Firefox</option>
                            <option value="fab fa-safari" data-icon="fab fa-safari">🌐 Safari</option>
                            <option value="fab fa-edge" data-icon="fab fa-edge">🌐 Edge</option>
                            <option value="fab fa-opera" data-icon="fab fa-opera">🌐 Opera</option>
                            <option value="fab fa-internet-explorer" data-icon="fab fa-internet-explorer">🌐 Internet Explorer</option>
                            <option value="fab fa-cc-visa" data-icon="fab fa-cc-visa">💳 Visa</option>
                            <option value="fab fa-cc-mastercard" data-icon="fab fa-cc-mastercard">💳 Mastercard</option>
                            <option value="fab fa-cc-amex" data-icon="fab fa-cc-amex">💳 American Express</option>
                            <option value="fab fa-cc-paypal" data-icon="fab fa-cc-paypal">💳 PayPal</option>
                            <option value="fab fa-cc-stripe" data-icon="fab fa-cc-stripe">💳 Stripe</option>
                            <option value="fab fa-bitcoin" data-icon="fab fa-bitcoin">₿ Bitcoin</option>
                            <option value="fab fa-ethereum" data-icon="fab fa-ethereum">Ξ Ethereum</option>
                            <option value="fab fa-cc-apple-pay" data-icon="fab fa-cc-apple-pay">💳 Apple Pay</option>
                            <option value="fab fa-cc-google-pay" data-icon="fab fa-cc-google-pay">💳 Google Pay</option>
                            <option value="fab fa-cc-amazon-pay" data-icon="fab fa-cc-amazon-pay">💳 Amazon Pay</option>
                            <option value="fab fa-cc-discover" data-icon="fab fa-cc-discover">💳 Discover</option>
                            <option value="fab fa-cc-jcb" data-icon="fab fa-cc-jcb">💳 JCB</option>
                            <option value="fab fa-cc-diners-club" data-icon="fab fa-cc-diners-club">💳 Diners Club</option>
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
                        <button type="button" onclick="closeModal('addContactModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Add Contact
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Contact Modal -->
    <div id="editContactModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Contact</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="edit_contact">
                    <input type="hidden" name="contact_id" id="edit_contact_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <select name="department_id" id="edit_contact_department_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Department</option>
                            <?php
                            mysqli_data_seek($departments_result, 0);
                            while($dept = mysqli_fetch_assoc($departments_result)):
                            ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Type</label>
                        <select name="contact_type" id="edit_contact_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="phone">Phone</option>
                            <option value="email">Email</option>
                            <option value="address">Address</option>
                            <option value="social_media">Social Media</option>
                            <option value="website">Website</option>
                            <option value="office_hours">Office Hours</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                        <input type="text" name="title" id="edit_contact_title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Value</label>
                        <textarea name="contact_value" id="edit_contact_value" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Icon</label>
                        <select name="icon" id="edit_contact_icon" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Icon</option>
                            <option value="fas fa-phone" data-icon="fas fa-phone">📞 Phone</option>
                            <option value="fas fa-envelope" data-icon="fas fa-envelope">✉️ Email</option>
                            <option value="fas fa-map-marker-alt" data-icon="fas fa-map-marker-alt">📍 Location</option>
                            <option value="fas fa-globe" data-icon="fas fa-globe">🌐 Website</option>
                            <option value="fas fa-clock" data-icon="fas fa-clock">🕐 Office Hours</option>
                            <option value="fab fa-facebook" data-icon="fab fa-facebook">📘 Facebook</option>
                            <option value="fab fa-twitter" data-icon="fab fa-twitter">🐦 Twitter</option>
                            <option value="fab fa-instagram" data-icon="fab fa-instagram">📷 Instagram</option>
                            <option value="fab fa-linkedin" data-icon="fab fa-linkedin">💼 LinkedIn</option>
                            <option value="fab fa-youtube" data-icon="fab fa-youtube">📺 YouTube</option>
                            <option value="fab fa-whatsapp" data-icon="fab fa-whatsapp">💬 WhatsApp</option>
                            <option value="fab fa-telegram" data-icon="fab fa-telegram">📱 Telegram</option>
                            <option value="fab fa-discord" data-icon="fab fa-discord">🎮 Discord</option>
                            <option value="fab fa-slack" data-icon="fab fa-slack">💬 Slack</option>
                            <option value="fab fa-skype" data-icon="fab fa-skype">📞 Skype</option>
                            <option value="fab fa-zoom" data-icon="fab fa-zoom">📹 Zoom</option>
                            <option value="fab fa-google" data-icon="fab fa-google">🔍 Google</option>
                            <option value="fab fa-microsoft" data-icon="fab fa-microsoft">🪟 Microsoft</option>
                            <option value="fab fa-apple" data-icon="fab fa-apple">🍎 Apple</option>
                            <option value="fab fa-android" data-icon="fab fa-android">🤖 Android</option>
                            <option value="fab fa-chrome" data-icon="fab fa-chrome">🌐 Chrome</option>
                            <option value="fab fa-firefox" data-icon="fab fa-firefox">🦊 Firefox</option>
                            <option value="fab fa-safari" data-icon="fab fa-safari">🌐 Safari</option>
                            <option value="fab fa-edge" data-icon="fab fa-edge">🌐 Edge</option>
                            <option value="fab fa-opera" data-icon="fab fa-opera">🌐 Opera</option>
                            <option value="fab fa-internet-explorer" data-icon="fab fa-internet-explorer">🌐 Internet Explorer</option>
                            <option value="fab fa-cc-visa" data-icon="fab fa-cc-visa">💳 Visa</option>
                            <option value="fab fa-cc-mastercard" data-icon="fab fa-cc-mastercard">💳 Mastercard</option>
                            <option value="fab fa-cc-amex" data-icon="fab fa-cc-amex">💳 American Express</option>
                            <option value="fab fa-cc-paypal" data-icon="fab fa-cc-paypal">💳 PayPal</option>
                            <option value="fab fa-cc-stripe" data-icon="fab fa-cc-stripe">💳 Stripe</option>
                            <option value="fab fa-bitcoin" data-icon="fab fa-bitcoin">₿ Bitcoin</option>
                            <option value="fab fa-ethereum" data-icon="fab fa-ethereum">Ξ Ethereum</option>
                            <option value="fab fa-cc-apple-pay" data-icon="fab fa-cc-apple-pay">💳 Apple Pay</option>
                            <option value="fab fa-cc-google-pay" data-icon="fab fa-cc-google-pay">💳 Google Pay</option>
                            <option value="fab fa-cc-amazon-pay" data-icon="fab fa-cc-amazon-pay">💳 Amazon Pay</option>
                            <option value="fab fa-cc-discover" data-icon="fab fa-cc-discover">💳 Discover</option>
                            <option value="fab fa-cc-jcb" data-icon="fab fa-cc-jcb">💳 JCB</option>
                            <option value="fab fa-cc-diners-club" data-icon="fab fa-cc-diners-club">💳 Diners Club</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" id="edit_contact_sort_order" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" id="edit_contact_is_active" class="rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('editContactModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-seait-orange text-white rounded-md hover:bg-orange-600">
                            Update Contact
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Department Confirmation Modal -->
    <div id="deleteDepartmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Department</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete "<span id="deleteDepartmentName" class="font-semibold"></span>"? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Department will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-link mr-2 text-red-500"></i>
                                    All associated contacts will be deleted
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
                    <form id="deleteDepartmentForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_department">
                        <input type="hidden" name="department_id" id="deleteDepartmentId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteDepartmentModal()"
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

    <!-- Delete Contact Confirmation Modal -->
    <div id="deleteContactModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Contact</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete "<span id="deleteContactTitle" class="font-semibold"></span>"? This action cannot be undone.</p>
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
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Edit functions
        function editDepartment(data) {
            document.getElementById('edit_department_id').value = data.id;
            document.getElementById('edit_department_name').value = data.name;
            document.getElementById('edit_department_description').value = data.description;
            document.getElementById('edit_department_icon').value = data.icon;
            document.getElementById('edit_department_color_theme').value = data.color_theme;
            document.getElementById('edit_department_sort_order').value = data.sort_order;
            document.getElementById('edit_department_is_active').checked = data.is_active == 1;
            openModal('editDepartmentModal');
        }

        function editContact(data) {
            document.getElementById('edit_contact_id').value = data.id;
            document.getElementById('edit_contact_department_id').value = data.department_id;
            document.getElementById('edit_contact_type').value = data.contact_type;
            document.getElementById('edit_contact_title').value = data.title;
            document.getElementById('edit_contact_value').value = data.contact_value;
            document.getElementById('edit_contact_icon').value = data.icon;
            document.getElementById('edit_contact_sort_order').value = data.sort_order;
            document.getElementById('edit_contact_is_active').checked = data.is_active == 1;
            openModal('editContactModal');
        }

        // Delete functions
        function deleteDepartment(id) {
            document.getElementById('deleteDepartmentId').value = id;
            document.getElementById('deleteDepartmentName').textContent = document.querySelector(`tr[data-department-id="${id}"]`).dataset.departmentName;
            openModal('deleteDepartmentModal');
        }

        function deleteContact(id) {
            document.getElementById('deleteContactId').value = id;
            document.getElementById('deleteContactTitle').textContent = document.querySelector(`tr[data-contact-id="${id}"]`).dataset.contactTitle;
            openModal('deleteContactModal');
        }

        function closeDeleteDepartmentModal() {
            document.getElementById('deleteDepartmentModal').classList.add('hidden');
        }

        function closeDeleteContactModal() {
            document.getElementById('deleteContactModal').classList.add('hidden');
        }

        // Close specific delete modals when clicking outside
        const deleteDepartmentModal = document.getElementById('deleteDepartmentModal');
        const deleteContactModal = document.getElementById('deleteContactModal');

        if (deleteDepartmentModal) {
            deleteDepartmentModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteDepartmentModal();
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

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['addDepartmentModal', 'editDepartmentModal', 'addContactModal', 'editContactModal', 'deleteDepartmentModal', 'deleteContactModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        }
    </script>