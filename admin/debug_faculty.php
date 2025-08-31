<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Test database connection
$db_status = "Database Connection: ";
if ($conn) {
    $db_status .= "SUCCESS - Connected to " . $dbname;
} else {
    $db_status .= "FAILED - " . mysqli_connect_error();
}

// Test faculty table
$table_status = "Faculty Table: ";
$table_query = "SHOW TABLES LIKE 'faculty'";
$table_result = mysqli_query($conn, $table_query);
if (mysqli_num_rows($table_result) > 0) {
    $table_status .= "EXISTS";
} else {
    $table_status .= "NOT FOUND";
}

// Test uploads directory
$uploads_status = "Uploads Directory: ";
$uploads_dir = '../uploads/faculty/';
if (is_dir($uploads_dir)) {
    if (is_writable($uploads_dir)) {
        $uploads_status .= "EXISTS AND WRITABLE";
    } else {
        $uploads_status .= "EXISTS BUT NOT WRITABLE";
    }
} else {
    $uploads_status .= "NOT FOUND";
}

// Test form submission
$form_status = "Form Submission: ";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_status .= "RECEIVED POST DATA";
    if (isset($_POST['action'])) {
        $form_status .= " - Action: " . $_POST['action'];
    }
} else {
    $form_status .= "NO POST DATA";
}

// Test file upload
$file_status = "File Upload: ";
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file_status .= "FILE RECEIVED - " . $_FILES['image']['name'];
} else {
    $file_status .= "NO FILE OR ERROR";
    if (isset($_FILES['image'])) {
        $file_status .= " - Error: " . $_FILES['image']['error'];
    }
}

// Test session
$session_status = "Session: ";
if (isset($_SESSION['user_id'])) {
    $session_status .= "ACTIVE - User ID: " . $_SESSION['user_id'] . ", Role: " . $_SESSION['role'];
} else {
    $session_status .= "INACTIVE";
}

// Test PHP errors
$error_status = "PHP Errors: ";
$error_log = error_get_last();
if ($error_log) {
    $error_status .= "FOUND - " . $error_log['message'];
} else {
    $error_status .= "NONE";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Debug - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Faculty Debug Information</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">System Status</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="font-medium">Database:</span>
                        <span class="<?php echo strpos($db_status, 'SUCCESS') !== false ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo strpos($db_status, 'SUCCESS') !== false ? '✓' : '✗'; ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">Faculty Table:</span>
                        <span class="<?php echo strpos($table_status, 'EXISTS') !== false ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo strpos($table_status, 'EXISTS') !== false ? '✓' : '✗'; ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">Uploads Directory:</span>
                        <span class="<?php echo strpos($uploads_status, 'WRITABLE') !== false ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo strpos($uploads_status, 'WRITABLE') !== false ? '✓' : '✗'; ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">Session:</span>
                        <span class="<?php echo strpos($session_status, 'ACTIVE') !== false ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo strpos($session_status, 'ACTIVE') !== false ? '✓' : '✗'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Form Status</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="font-medium">Form Submission:</span>
                        <span class="<?php echo strpos($form_status, 'RECEIVED') !== false ? 'text-green-600' : 'text-gray-600'; ?>">
                            <?php echo strpos($form_status, 'RECEIVED') !== false ? '✓' : '○'; ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">File Upload:</span>
                        <span class="<?php echo strpos($file_status, 'RECEIVED') !== false ? 'text-green-600' : 'text-gray-600'; ?>">
                            <?php echo strpos($file_status, 'RECEIVED') !== false ? '✓' : '○'; ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">PHP Errors:</span>
                        <span class="<?php echo strpos($error_status, 'NONE') !== false ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo strpos($error_status, 'NONE') !== false ? '✓' : '✗'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Detailed Information</h2>
            <div class="space-y-4">
                <div>
                    <h3 class="font-medium text-gray-900">Database Status:</h3>
                    <p class="text-sm text-gray-600 mt-1"><?php echo $db_status; ?></p>
                </div>
                <div>
                    <h3 class="font-medium text-gray-900">Table Status:</h3>
                    <p class="text-sm text-gray-600 mt-1"><?php echo $table_status; ?></p>
                </div>
                <div>
                    <h3 class="font-medium text-gray-900">Uploads Status:</h3>
                    <p class="text-sm text-gray-600 mt-1"><?php echo $uploads_status; ?></p>
                </div>
                <div>
                    <h3 class="font-medium text-gray-900">Form Status:</h3>
                    <p class="text-sm text-gray-600 mt-1"><?php echo $form_status; ?></p>
                </div>
                <div>
                    <h3 class="font-medium text-gray-900">File Status:</h3>
                    <p class="text-sm text-gray-600 mt-1"><?php echo $file_status; ?></p>
                </div>
                <div>
                    <h3 class="font-medium text-gray-900">Session Status:</h3>
                    <p class="text-sm text-gray-600 mt-1"><?php echo $session_status; ?></p>
                </div>
                <div>
                    <h3 class="font-medium text-gray-900">Error Status:</h3>
                    <p class="text-sm text-gray-600 mt-1"><?php echo $error_status; ?></p>
                </div>
            </div>
        </div>

        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Test Faculty Addition</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="add">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                        <input type="text" name="first_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                        <input type="text" name="last_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Position</label>
                        <input type="text" name="position" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <input type="text" name="department" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="text" name="password" value="Seait123" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Profile Image</label>
                    <input type="file" name="image" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Bio</label>
                    <textarea name="bio" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="is_active" checked class="mr-2">
                    <label class="text-sm font-medium text-gray-700">Active</label>
                </div>

                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                    Test Add Faculty
                </button>
            </form>
        </div>

        <div class="mt-8">
            <a href="faculty.php" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700">
                Back to Faculty Management
            </a>
        </div>
    </div>

    <script>
        // Test JavaScript functionality
        console.log('Debug page loaded successfully');
        
        // Test fetch functionality
        fetch('get_faculty.php?id=1')
            .then(response => {
                console.log('Fetch test response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Fetch test data:', data);
            })
            .catch(error => {
                console.error('Fetch test error:', error);
            });
    </script>
</body>
</html>
