<?php
/**
 * Upload Helper Functions
 * Automatically handles directory creation and permissions for uploads
 */

/**
 * Ensures upload directory exists with correct permissions
 * @param string $upload_path Relative path from project root
 * @return array ['success' => bool, 'message' => string, 'full_path' => string]
 */
function ensureUploadDirectory($upload_path) {
    $project_root = realpath(dirname(__FILE__) . '/../');
    $full_path = $project_root . '/' . trim($upload_path, '/') . '/';
    
    // Create directory if it doesn't exist
    if (!is_dir($full_path)) {
        if (!mkdir($full_path, 0755, true)) {
            return [
                'success' => false,
                'message' => 'Failed to create upload directory: ' . $full_path,
                'full_path' => $full_path
            ];
        }
    }
    
    // Check if directory is writable by current process
    if (!is_writable($full_path)) {
        // Try to change permissions
        if (!chmod($full_path, 0755)) {
            return [
                'success' => false,
                'message' => 'Directory exists but is not writable: ' . $full_path,
                'full_path' => $full_path
            ];
        }
    }
    
    // Check ownership - if owned by current user, change to daemon
    $current_owner = posix_getpwuid(fileowner($full_path));
    $current_user = get_current_user();
    
    if ($current_owner && $current_owner['name'] !== 'daemon') {
        // Try to change ownership to daemon (requires sudo)
        $chown_command = "sudo chown -R daemon:daemon " . escapeshellarg($full_path);
        exec($chown_command, $output, $return_code);
        
        if ($return_code !== 0) {
            // If chown fails, at least ensure permissions are correct
            chmod($full_path, 0755);
        }
    }
    
    return [
        'success' => true,
        'message' => 'Upload directory ready',
        'full_path' => $full_path
    ];
}

/**
 * Safe file upload with automatic directory handling
 * @param array $file $_FILES array element
 * @param string $upload_path Relative upload path
 * @param string $filename_prefix Prefix for the uploaded file
 * @return array ['success' => bool, 'message' => string, 'filepath' => string]
 */
function safeFileUpload($file, $upload_path, $filename_prefix = 'file') {
    // Ensure upload directory exists
    $dir_result = ensureUploadDirectory($upload_path);
    if (!$dir_result['success']) {
        return $dir_result;
    }
    
    $full_path = $dir_result['full_path'];
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $filename_prefix . '_' . time() . '_' . uniqid() . '.' . $file_extension;
    $filepath = $full_path . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'message' => 'File uploaded successfully',
            'filepath' => $upload_path . '/' . $filename,
            'full_path' => $filepath
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to move uploaded file',
            'filepath' => '',
            'full_path' => $filepath
        ];
    }
}

/**
 * Clean up old files in upload directory
 * @param string $upload_path Relative upload path
 * @param int $max_age_days Maximum age in days (default: 30)
 * @return int Number of files cleaned up
 */
function cleanupOldUploads($upload_path, $max_age_days = 30) {
    $project_root = realpath(dirname(__FILE__) . '/../');
    $full_path = $project_root . '/' . trim($upload_path, '/') . '/';
    
    if (!is_dir($full_path)) {
        return 0;
    }
    
    $cutoff_time = time() - ($max_age_days * 24 * 60 * 60);
    $cleaned_count = 0;
    
    $files = glob($full_path . '*');
    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < $cutoff_time) {
            if (unlink($file)) {
                $cleaned_count++;
            }
        }
    }
    
    return $cleaned_count;
}
?>
