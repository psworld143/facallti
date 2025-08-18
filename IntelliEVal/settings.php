<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'System Settings';

$message = '';
$message_type = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Handle backup action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    $backup_type = $_POST['backup_type'] ?? 'full';
    $include_data = isset($_POST['include_data']);
    
    try {
        // Create backup directory if it doesn't exist
        $backup_dir = 'backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        // Generate backup filename
        $timestamp = date('Y-m-d_H-i-s');
        $backup_filename = "evaluation_backup_{$backup_type}_{$timestamp}.sql";
        $backup_path = $backup_dir . $backup_filename;
        
        // Define tables to backup based on type
        $evaluation_tables = [
            'main_evaluation_categories',
            'evaluation_sub_categories', 
            'evaluation_questionnaires',
            'evaluation_sessions',
            'evaluation_responses',
            'training_suggestions',
            'trainings_seminars',
            'training_registrations',
            'training_categories',
            'training_materials'
        ];
        
        $faculty_tables = [
            'faculty',
            'students',
            'users'
        ];
        
        $system_tables = [
            'semesters',
            'subjects',
            'departments',
            'colleges'
        ];
        
        $tables_to_backup = [];
        
        switch ($backup_type) {
            case 'evaluations':
                $tables_to_backup = $evaluation_tables;
                break;
            case 'faculty':
                $tables_to_backup = $faculty_tables;
                break;
            case 'system':
                $tables_to_backup = $system_tables;
                break;
            case 'full':
            default:
                $tables_to_backup = array_merge($evaluation_tables, $faculty_tables, $system_tables);
                break;
        }
        
        // Start backup file
        $backup_content = "-- IntelliEVal System Backup\n";
        $backup_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $backup_content .= "-- Backup Type: " . ucfirst($backup_type) . "\n";
        $backup_content .= "-- Include Data: " . ($include_data ? 'Yes' : 'No') . "\n\n";
        $backup_content .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        foreach ($tables_to_backup as $table) {
            // Check if table exists
            $table_check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
            if (mysqli_num_rows($table_check) == 0) {
                continue;
            }
            
            // Get table structure
            $structure_query = "SHOW CREATE TABLE $table";
            $structure_result = mysqli_query($conn, $structure_query);
            $structure_row = mysqli_fetch_assoc($structure_result);
            
            $backup_content .= "-- Table structure for table `$table`\n";
            $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
            $backup_content .= $structure_row['Create Table'] . ";\n\n";
            
            // Include data if requested
            if ($include_data) {
                $data_query = "SELECT * FROM $table";
                $data_result = mysqli_query($conn, $data_query);
                
                if (mysqli_num_rows($data_result) > 0) {
                    $backup_content .= "-- Data for table `$table`\n";
                    
                    while ($row = mysqli_fetch_assoc($data_result)) {
                        $columns = array_keys($row);
                        $values = array_map(function($value) use ($conn) {
                            if ($value === null) {
                                return 'NULL';
                            }
                            return "'" . mysqli_real_escape_string($conn, $value) . "'";
                        }, array_values($row));
                        
                        $backup_content .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $backup_content .= "\n";
                }
            }
        }
        
        $backup_content .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        
        // Write backup file
        if (file_put_contents($backup_path, $backup_content)) {
            $_SESSION['message'] = "Backup created successfully: $backup_filename";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Failed to create backup file.";
            $_SESSION['message_type'] = 'error';
        }
        
    } catch (Exception $e) {
        $_SESSION['message'] = "Backup failed: " . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: settings.php');
    exit();
}

// Handle restore action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $uploaded_file = $_FILES['backup_file']['tmp_name'];
        $file_content = file_get_contents($uploaded_file);
        
        if ($file_content === false) {
            $_SESSION['message'] = "Failed to read backup file.";
            $_SESSION['message_type'] = 'error';
        } else {
            try {
                // Split SQL commands
                $sql_commands = array_filter(array_map('trim', explode(';', $file_content)));
                
                // Execute each SQL command
                $success_count = 0;
                $error_count = 0;
                
                foreach ($sql_commands as $sql) {
                    if (!empty($sql) && !preg_match('/^--/', $sql)) {
                        if (mysqli_query($conn, $sql)) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }
                
                if ($error_count == 0) {
                    $_SESSION['message'] = "Restore completed successfully. $success_count commands executed.";
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = "Restore completed with $error_count errors. $success_count commands executed successfully.";
                    $_SESSION['message_type'] = 'warning';
                }
                
            } catch (Exception $e) {
                $_SESSION['message'] = "Restore failed: " . $e->getMessage();
                $_SESSION['message_type'] = 'error';
            }
        }
    } else {
        $_SESSION['message'] = "Please select a valid backup file.";
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: settings.php');
    exit();
}

// Get existing backups
$backup_files = [];
$backup_dir = 'backups/';
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $file_path = $backup_dir . $file;
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($file_path),
                'date' => filemtime($file_path),
                'path' => $file_path
            ];
    }
    }
    // Sort by date (newest first)
    usort($backup_files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Get system statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM evaluation_sessions) as total_evaluations,
                (SELECT COUNT(*) FROM evaluation_responses) as total_responses,
                (SELECT COUNT(*) FROM training_suggestions) as total_suggestions,
                (SELECT COUNT(*) FROM faculty WHERE is_active = 1) as active_faculty,
                (SELECT COUNT(*) FROM students WHERE status = 'active') as active_students,
                (SELECT COUNT(*) FROM trainings_seminars WHERE status = 'published') as published_trainings";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Include the shared header
include 'includes/header.php';
?>

<!-- Custom CSS for settings page -->
<link rel="stylesheet" href="assets/css/settings.css">

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">System Settings</h1>
            <p class="text-sm sm:text-base text-gray-600">Manage system configuration, backups, and data restoration</p>
        </div>
        <div class="flex space-x-2">
            <a href="dashboard.php" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- System Statistics -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <div class="stats-card">
        <div class="flex items-center">
            <div class="stats-icon stats-icon-blue">
                <i class="fas fa-clipboard-check text-blue-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Evaluations</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_evaluations']); ?></p>
            </div>
        </div>
    </div>

    <div class="stats-card">
        <div class="flex items-center">
            <div class="stats-icon stats-icon-green">
                <i class="fas fa-comments text-green-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Responses</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_responses']); ?></p>
            </div>
        </div>
    </div>

    <div class="stats-card">
        <div class="flex items-center">
            <div class="stats-icon stats-icon-orange">
                <i class="fas fa-lightbulb text-orange-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Training Suggestions</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_suggestions']); ?></p>
            </div>
        </div>
    </div>

    <div class="stats-card">
        <div class="flex items-center">
            <div class="stats-icon stats-icon-purple">
                <i class="fas fa-chalkboard-teacher text-purple-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Active Faculty</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['active_faculty']); ?></p>
            </div>
        </div>
    </div>

    <div class="stats-card">
        <div class="flex items-center">
            <div class="stats-icon stats-icon-yellow">
                <i class="fas fa-user-graduate text-yellow-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Active Students</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['active_students']); ?></p>
            </div>
        </div>
    </div>

    <div class="stats-card">
        <div class="flex items-center">
            <div class="stats-icon stats-icon-red">
                <i class="fas fa-graduation-cap text-red-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Published Trainings</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['published_trainings']); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Backup and Restore Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <!-- Create Backup -->
    <div class="settings-card">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-download mr-2 text-blue-600"></i>Create Backup
        </h3>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="backup">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Backup Type</label>
                <select name="backup_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                    <option value="full">Full System Backup</option>
                    <option value="evaluations">Evaluations Only</option>
                    <option value="faculty">Faculty & Users Only</option>
                    <option value="system">System Data Only</option>
                </select>
            </div>
            
            <div class="flex items-center">
                <input type="checkbox" id="include_data" name="include_data" checked class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                <label for="include_data" class="ml-2 block text-sm text-gray-900">
                    Include data (uncheck for structure only)
                </label>
            </div>
            
            <button type="submit" class="w-full btn-primary">
                <i class="fas fa-download mr-2"></i>Create Backup
            </button>
        </form>
    </div>

    <!-- Restore Backup -->
    <div class="settings-card">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-upload mr-2 text-green-600"></i>Restore Backup
        </h3>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="restore">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Select Backup File</label>
                <input type="file" name="backup_file" accept=".sql" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">Select a .sql backup file to restore</p>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Warning</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>Restoring a backup will overwrite existing data. Make sure to create a backup before proceeding.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="w-full btn-warning" onclick="return confirm('Are you sure you want to restore this backup? This will overwrite existing data.')">
                <i class="fas fa-upload mr-2"></i>Restore Backup
            </button>
        </form>
    </div>
</div>

<!-- Existing Backups -->
<div class="settings-card">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
        <i class="fas fa-archive mr-2 text-purple-600"></i>Existing Backups
    </h3>
    
    <?php if (empty($backup_files)): ?>
    <div class="text-center py-8">
        <i class="fas fa-archive text-gray-300 text-4xl mb-4"></i>
        <p class="text-gray-500">No backup files found. Create your first backup to get started.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($backup_files as $backup): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($backup['name']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?php echo formatBytes($backup['size']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?php echo date('M d, Y H:i', $backup['date']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="<?php echo htmlspecialchars($backup['path']); ?>" download class="text-seait-orange hover:text-orange-600 mr-3">
                            <i class="fas fa-download mr-1"></i>Download
                        </a>
                        <a href="#" onclick="deleteBackup('<?php echo htmlspecialchars($backup['name']); ?>')" class="text-red-600 hover:text-red-900">
                            <i class="fas fa-trash mr-1"></i>Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- System Information -->
<div class="settings-card">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
        <i class="fas fa-info-circle mr-2 text-blue-600"></i>System Information
    </h3>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h4 class="text-sm font-medium text-gray-700 mb-3">Server Information</h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600">PHP Version:</span>
                    <span class="font-medium"><?php echo phpversion(); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">MySQL Version:</span>
                    <span class="font-medium"><?php echo mysqli_get_server_info($conn); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Server Software:</span>
                    <span class="font-medium"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                </div>
            </div>
        </div>
        
        <div>
            <h4 class="text-sm font-medium text-gray-700 mb-3">Application Information</h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600">Application:</span>
                    <span class="font-medium">IntelliEVal System</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Version:</span>
                    <span class="font-medium">1.0.0</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Last Updated:</span>
                    <span class="font-medium"><?php echo date('M d, Y'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteBackup(filename) {
    if (confirm('Are you sure you want to delete this backup file? This action cannot be undone.')) {
        // You can implement AJAX deletion here or redirect to a delete script
        window.location.href = 'delete_backup.php?file=' + encodeURIComponent(filename);
    }
}
</script>

<?php
// Helper function to format bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Include the shared footer
include 'includes/footer.php';
?>