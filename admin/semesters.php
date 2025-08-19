<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_admin();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name']);
    $academic_year = trim($_POST['academic_year']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $status = $_POST['status'];
    $created_by = $_SESSION['user_id'];

    if (empty($name) || empty($academic_year) || empty($start_date) || empty($end_date) || empty($status)) {
        $message = 'All fields are required.';
        $message_type = 'error';
    } else {
        if ($id > 0) {
            // Update
            $query = "UPDATE semesters SET name=?, academic_year=?, start_date=?, end_date=?, status=?, updated_at=NOW() WHERE id=?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sssssi", $name, $academic_year, $start_date, $end_date, $status, $id);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Semester updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating semester.';
                $message_type = 'error';
            }
        } else {
            // Add
            $query = "INSERT INTO semesters (name, academic_year, start_date, end_date, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sssssi", $name, $academic_year, $start_date, $end_date, $status, $created_by);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Semester added successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error adding semester.';
                $message_type = 'error';
            }
        }
    }
}

// Fetch all semesters
$semesters = [];
$result = mysqli_query($conn, "SELECT * FROM semesters ORDER BY academic_year DESC, start_date DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $semesters[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Semesters - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
<?php include 'includes/admin-header.php'; ?>
<div class="flex pt-16">
    <?php include 'includes/admin-sidebar.php'; ?>
    <div class="flex-1 ml-64 p-8 overflow-y-auto h-screen">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-seait-dark mb-2">Manage Semesters</h1>
            <p class="text-gray-600">Add, update, and view academic semesters</p>
        </div>
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Add/Update Semester Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-calendar-alt text-seait-orange mr-2"></i>Add / Update Semester
                </h3>
                <form method="POST" id="semesterForm">
                    <input type="hidden" name="id" id="semester_id" value="">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Semester Name</label>
                            <input type="text" name="name" id="name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Academic Year</label>
                            <input type="text" name="academic_year" id="academic_year" placeholder="e.g. 2024-2025" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="mt-6 bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                        <i class="fas fa-save mr-2"></i>Save Semester
                    </button>
                </form>
            </div>
            <!-- Semester List -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-list text-seait-orange mr-2"></i>All Semesters
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Academic Year</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Start</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">End</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($semesters as $sem): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2"><?php echo htmlspecialchars($sem['name']); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($sem['academic_year']); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($sem['start_date']); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($sem['end_date']); ?></td>
                                <td class="px-4 py-2 capitalize"><?php echo htmlspecialchars($sem['status']); ?></td>
                                <td class="px-4 py-2">
                                    <button class="text-blue-600 hover:underline edit-btn" 
                                            data-id="<?php echo $sem['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($sem['name']); ?>"
                                            data-academic_year="<?php echo htmlspecialchars($sem['academic_year']); ?>"
                                            data-start_date="<?php echo $sem['start_date']; ?>"
                                            data-end_date="<?php echo $sem['end_date']; ?>"
                                            data-status="<?php echo $sem['status']; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
// Fill form for editing
const editBtns = document.querySelectorAll('.edit-btn');
const form = document.getElementById('semesterForm');
editBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('semester_id').value = this.dataset.id;
        document.getElementById('name').value = this.dataset.name;
        document.getElementById('academic_year').value = this.dataset.academic_year;
        document.getElementById('start_date').value = this.dataset.start_date;
        document.getElementById('end_date').value = this.dataset.end_date;
        document.getElementById('status').value = this.dataset.status;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
</script>
</body>
</html>
