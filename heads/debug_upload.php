<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    echo "Not logged in or not a head user";
    exit();
}

echo "<h2>Heads Profile Photo Upload Debug</h2>";
echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
echo "<p>Role: " . $_SESSION['role'] . "</p>";

// Check PHP upload settings
echo "<h3>PHP Upload Settings:</h3>";
echo "<p>file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "</p>";
echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
echo "<p>max_file_uploads: " . ini_get('max_file_uploads') . "</p>";

// Check uploads directory
$upload_dir = dirname(__FILE__) . '/../uploads/profile-photos/';
echo "<h3>Upload Directory:</h3>";
echo "<p>Path: " . $upload_dir . "</p>";
echo "<p>Exists: " . (is_dir($upload_dir) ? 'Yes' : 'No') . "</p>";
echo "<p>Writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "</p>";

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Form Submission Results:</h3>";
    echo "<p>POST data:</p>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    echo "<p>FILES data:</p>";
    echo "<pre>" . print_r($_FILES, true) . "</pre>";
    
    if (isset($_POST['action']) && $_POST['action'] === 'update_photo') {
        echo "<h4>Photo Upload Action Detected</h4>";
        
        if (isset($_FILES['profile_photo'])) {
            $file = $_FILES['profile_photo'];
            echo "<h5>File Details:</h5>";
            echo "<p>Name: " . $file['name'] . "</p>";
            echo "<p>Type: " . $file['type'] . "</p>";
            echo "<p>Size: " . $file['size'] . " bytes</p>";
            echo "<p>Error: " . $file['error'] . "</p>";
            echo "<p>Tmp Name: " . $file['tmp_name'] . "</p>";
            
            if ($file['error'] === UPLOAD_ERR_OK) {
                echo "<p style='color: green;'>File uploaded successfully to temp location</p>";
                
                // Try to move the file
                $filename = 'debug_' . $_SESSION['user_id'] . '_' . time() . '.jpg';
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    echo "<p style='color: green;'>File moved successfully to: " . $filepath . "</p>";
                    
                    // Update database
                    $photo_path = 'uploads/profile-photos/' . $filename;
                    $update_query = "UPDATE users SET profile_photo = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($stmt, "si", $photo_path, $_SESSION['user_id']);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        echo "<p style='color: green;'>Database updated successfully</p>";
                        echo "<p>New photo path: " . $photo_path . "</p>";
                    } else {
                        echo "<p style='color: red;'>Database update failed: " . mysqli_error($conn) . "</p>";
                    }
                } else {
                    echo "<p style='color: red;'>Failed to move uploaded file</p>";
                    echo "<p>Upload error details:</p>";
                    echo "<ul>";
                    echo "<li>Source: " . $file['tmp_name'] . "</li>";
                    echo "<li>Destination: " . $filepath . "</li>";
                    echo "<li>Source exists: " . (file_exists($file['tmp_name']) ? 'Yes' : 'No') . "</li>";
                    echo "<li>Destination dir writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "</li>";
                    echo "</ul>";
                }
            } else {
                echo "<p style='color: red;'>File upload error: " . $file['error'] . "</p>";
                $error_messages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                    UPLOAD_ERR_PARTIAL => 'File partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
                    UPLOAD_ERR_EXTENSION => 'PHP extension stopped upload'
                ];
                if (isset($error_messages[$file['error']])) {
                    echo "<p>Error meaning: " . $error_messages[$file['error']] . "</p>";
                }
            }
        } else {
            echo "<p style='color: red;'>No profile_photo in FILES array</p>";
        }
    } else {
        echo "<p>No update_photo action detected</p>";
    }
}

// Display current profile photo
$user_query = "SELECT profile_photo FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

echo "<h3>Current Profile Photo:</h3>";
if ($user && !empty($user['profile_photo'])) {
    echo "<p>Path: " . $user['profile_photo'] . "</p>";
    echo "<img src='../" . $user['profile_photo'] . "' alt='Profile Photo' style='max-width: 200px;'>";
} else {
    echo "<p>No profile photo set</p>";
}
?>

<form method="POST" enctype="multipart/form-data">
    <h3>Test Upload Form:</h3>
    <input type="hidden" name="action" value="update_photo">
    <input type="file" name="profile_photo" accept="image/*" required>
    <br><br>
    <button type="submit">Test Upload</button>
</form>

<p><a href="profile.php">Back to Profile</a></p>
