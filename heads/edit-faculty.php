<?php
// Set appropriate limits for photo uploads
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 120);

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Edit Faculty';

// Get user information
$user_id = $_SESSION['user_id'];

// Get head information
$head_query = "SELECT h.* FROM heads h WHERE h.user_id = ?";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, "i", $user_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);
$head_info = mysqli_fetch_assoc($head_result);

if (!$head_info) {
    header('Location: teachers.php?error=unauthorized');
    exit();
}

// Check if faculty ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: teachers.php?error=invalid_faculty');
    exit();
}

// Decrypt the faculty ID
try {
    $faculty_id = IDEncryption::decrypt($_GET['id']);
} catch (Exception $e) {
    error_log("ID decryption failed: " . $e->getMessage());
    header('Location: teachers.php?error=invalid_faculty');
    exit();
}

// Get faculty information (faculty_details table was removed during FaCallTi cleanup)
$faculty_query = "SELECT f.* FROM faculty f WHERE f.id = ? AND f.department = ?";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, "is", $faculty_id, $head_info['department']);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);
$faculty = mysqli_fetch_assoc($faculty_result);

if (!$faculty) {
    header('Location: teachers.php?error=faculty_not_found');
    exit();
}

// Initialize variables
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input data
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $middle_name = sanitize_input($_POST['middle_name']);
    $email = sanitize_input($_POST['email']);
    $position = sanitize_input($_POST['position']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = "Please fill in all required fields (First Name, Last Name, Email).";
    } else {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Check if email already exists for another faculty
            $email_check_query = "SELECT id FROM faculty WHERE email = ? AND id != ?";
            $email_check_stmt = mysqli_prepare($conn, $email_check_query);
            mysqli_stmt_bind_param($email_check_stmt, "si", $email, $faculty_id);
            mysqli_stmt_execute($email_check_stmt);
            $email_check_result = mysqli_stmt_get_result($email_check_stmt);
            
            if (mysqli_num_rows($email_check_result) > 0) {
                $error_message = "This email address is already in use by another faculty member.";
            } else {
                // Handle photo upload
                $photo_path = $faculty['image_url']; // Keep existing photo by default
                
                // Check for captured photo (base64) first
                if (isset($_POST['captured_photo']) && !empty($_POST['captured_photo'])) {
                    try {
                        $base64_data = $_POST['captured_photo'];
                        
                        // Validate base64 data length to prevent memory issues
                        if (strlen($base64_data) > 10 * 1024 * 1024) { // 10MB limit for base64
                            throw new Exception("Captured photo is too large.");
                        }
                        
                        // Remove data URL prefix if present
                        if (strpos($base64_data, 'data:image/') === 0) {
                            $base64_data = substr($base64_data, strpos($base64_data, ',') + 1);
                        }
                        
                        // Validate base64 format
                        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $base64_data)) {
                            throw new Exception("Invalid image data format.");
                        }
                        
                        // Decode base64
                        $image_data = base64_decode($base64_data, true);
                        
                        if ($image_data === false) {
                            throw new Exception("Failed to decode image data.");
                        }
                        
                        // Validate decoded image size
                        if (strlen($image_data) > 2 * 1024 * 1024) { // 2MB limit for decoded image
                            throw new Exception("Decoded image is too large (max 2MB).");
                        }
                        
                        $upload_dir = dirname(__FILE__) . '/../uploads/faculty_photos/';
                        
                        // Create directory if it doesn't exist
                        if (!is_dir($upload_dir)) {
                            if (!mkdir($upload_dir, 0777, true)) {
                                throw new Exception("Failed to create upload directory.");
                            }
                        }
                        
                        // Generate unique filename
                        $filename = $faculty['qrcode'] . '_' . time() . '.jpg';
                        $upload_path = $upload_dir . $filename;
                        
                        if (file_put_contents($upload_path, $image_data) === false) {
                            throw new Exception("Failed to save captured photo to disk.");
                        }
                        
                        // Delete old photo if it exists
                        if (!empty($faculty['image_url'])) {
                            $old_photo_path = dirname(__FILE__) . '/../' . $faculty['image_url'];
                            if (file_exists($old_photo_path)) {
                                unlink($old_photo_path);
                            }
                        }
                        
                        $photo_path = 'uploads/faculty_photos/' . $filename;
                        
                        // Clear image data from memory
                        unset($image_data);
                        unset($base64_data);
                        
                    } catch (Exception $e) {
                        error_log("Photo capture error: " . $e->getMessage());
                        $error_message = $e->getMessage();
                    }
                }
                // Check for uploaded file
                elseif (isset($_FILES['faculty_photo']) && $_FILES['faculty_photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = dirname(__FILE__) . '/../uploads/faculty_photos/';
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_info = pathinfo($_FILES['faculty_photo']['name']);
                    $file_extension = strtolower($file_info['extension']);
                    
                    // Validate file type
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                    if (in_array($file_extension, $allowed_types)) {
                        // Validate file size (2MB max)
                        if ($_FILES['faculty_photo']['size'] <= 2 * 1024 * 1024) {
                            // Generate unique filename
                            $filename = $faculty['qrcode'] . '_' . time() . '.' . $file_extension;
                            $upload_path = $upload_dir . $filename;
                            
                            if (move_uploaded_file($_FILES['faculty_photo']['tmp_name'], $upload_path)) {
                                // Delete old photo if it exists
                                if (!empty($faculty['image_url'])) {
                                    $old_photo_path = dirname(__FILE__) . '/../' . $faculty['image_url'];
                                    if (file_exists($old_photo_path)) {
                                        unlink($old_photo_path);
                                    }
                                }
                                $photo_path = 'uploads/faculty_photos/' . $filename;
                            } else {
                                $error_message = "Failed to upload photo.";
                            }
                        } else {
                            $error_message = "Photo size must be less than 2MB.";
                        }
                    } else {
                        $error_message = "Please select a valid image file (JPG, PNG, GIF).";
                    }
                }
                
                // If no error occurred during photo processing, proceed with database update
                if (empty($error_message)) {
                    try {
                        // Start transaction
                        mysqli_begin_transaction($conn);
                        
                        // Update faculty table
                        $update_faculty_query = "UPDATE faculty SET 
                                                first_name = ?, last_name = ?, middle_name = ?, email = ?, phone = ?, address = ?, position = ?, image_url = ?
                                                WHERE id = ? AND department = ?";
                        $update_faculty_stmt = mysqli_prepare($conn, $update_faculty_query);
                        
                        if (!$update_faculty_stmt) {
                            throw new Exception("Error preparing faculty update statement: " . mysqli_error($conn));
                        }
                        
                        mysqli_stmt_bind_param($update_faculty_stmt, "ssssssssis", 
                            $first_name, $last_name, $middle_name, $email, $phone, $address, $position, $photo_path, $faculty_id, $head_info['department']);
                        
                        if (!mysqli_stmt_execute($update_faculty_stmt)) {
                            throw new Exception("Error updating faculty: " . mysqli_stmt_error($update_faculty_stmt));
                        }
                        
                        // Note: faculty_details table was removed during FaCallTi cleanup
                        // Only basic faculty information is updated in the faculty table
                        
                        // Commit transaction
                        mysqli_commit($conn);
                        
                        // Success - redirect with success message
                        header('Location: teachers.php?success=faculty_updated&name=' . urlencode($first_name . ' ' . $last_name));
                        exit();
                        
                    } catch (Exception $e) {
                        // Rollback transaction
                        mysqli_rollback($conn);
                        
                        // Log error for debugging
                        error_log("Edit Faculty Error: " . $e->getMessage());
                        
                        $error_message = "There was an error updating the faculty member. Please try again.";
                    }
                }
            }
        }
    }
}

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Edit Faculty Member</h1>
            <p class="text-gray-600">Update faculty member information</p>
        </div>
        <a href="teachers.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transform transition-all hover:scale-105 font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Teachers
        </a>
    </div>
</div>

<!-- Error/Success Messages -->
<?php if (!empty($error_message)): ?>
    <div class="mb-6 p-4 rounded-lg bg-red-100 border border-red-400 text-red-700">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-3"></i>
            <?php echo $error_message; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($success_message)): ?>
    <div class="mb-6 p-4 rounded-lg bg-green-100 border border-green-400 text-green-700">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-3"></i>
            <?php echo $success_message; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Edit Faculty Form -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <!-- Faculty Photo Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-camera text-purple-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Faculty Photo</h4>
                    <p class="text-gray-600 text-sm">Update faculty member photo</p>
                </div>
            </div>
            
            <div class="text-center">
                <div class="flex flex-col items-center space-y-3">
                    <!-- Photo Preview Area -->
                    <div id="photoPreview" class="w-32 h-32 rounded-full bg-gray-200 border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden">
                        <?php if (!empty($faculty['image_url']) && file_exists('../' . $faculty['image_url'])): ?>
                            <img src="../<?php echo htmlspecialchars($faculty['image_url']); ?>" alt="Current Photo" class="w-full h-full object-cover rounded-full">
                        <?php else: ?>
                            <div class="flex flex-col items-center">
                                <i class="fas fa-camera text-gray-400 text-xl mb-1"></i>
                                <span class="text-xs text-gray-500">No Photo</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Camera View (Hidden by default) -->
                    <div id="cameraView" class="hidden w-full max-w-sm">
                        <video id="cameraVideo" class="w-full h-48 bg-gray-900 rounded-lg" autoplay playsinline></video>
                        <canvas id="cameraCanvas" class="hidden"></canvas>
                        
                        <div id="cameraError" class="hidden text-red-600 text-sm mt-2 p-2 bg-red-50 rounded-lg">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <span id="cameraErrorMessage">Unable to access camera</span>
                        </div>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-2 mt-2">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-yellow-600 mt-0.5 mr-2 text-sm"></i>
                                <div class="text-xs text-yellow-700">
                                    <p><strong>Note:</strong> Camera access works best on HTTPS.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-center space-x-2 mt-3">
                            <button type="button" id="cancelCamera" class="px-3 py-1 text-gray-600 bg-gray-100 rounded-lg text-sm hover:bg-gray-200 transition-colors">
                                <i class="fas fa-times mr-1"></i>Cancel
                            </button>
                            <button type="button" id="capturePhoto" class="px-3 py-1 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600 transition-colors">
                                <i class="fas fa-camera mr-1"></i>Capture
                            </button>
                        </div>
                    </div>
                    
                    <!-- Photo Controls -->
                    <div id="photoControls" class="flex flex-wrap justify-center gap-2">
                        <label for="faculty_photo" class="cursor-pointer bg-blue-500 text-white px-3 py-1 rounded-lg text-sm hover:bg-blue-600 transition-colors">
                            <i class="fas fa-upload mr-1"></i>Choose Photo
                        </label>
                        <button type="button" id="takePhoto" class="bg-green-500 text-white px-3 py-1 rounded-lg text-sm hover:bg-green-600 transition-colors">
                            <i class="fas fa-camera mr-1"></i>Take Photo
                        </button>
                        <button type="button" id="removePhoto" class="bg-red-500 text-white px-3 py-1 rounded-lg text-sm hover:bg-red-600 transition-colors <?php echo empty($faculty['image_url']) ? 'hidden' : ''; ?>">
                            <i class="fas fa-trash mr-1"></i>Remove
                        </button>
                    </div>
                    
                    <input type="file" id="faculty_photo" name="faculty_photo" accept="image/*" class="hidden">
                    <input type="hidden" id="captured_photo" name="captured_photo">
                    <p class="text-xs text-gray-500">Optional. Max 2MB. JPG, PNG, GIF allowed.</p>
                </div>
            </div>
        </div>

        <!-- Basic Information -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-user text-blue-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Basic Information</h4>
                    <p class="text-gray-600 text-sm">Essential faculty member details</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="first_name" class="block text-sm font-semibold text-gray-700 mb-2">
                        First Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="first_name" name="first_name" required
                           value="<?php echo htmlspecialchars($faculty['first_name']); ?>"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter first name">
                </div>
                
                <div>
                    <label for="middle_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Middle Name
                    </label>
                    <input type="text" id="middle_name" name="middle_name"
                           value="<?php echo htmlspecialchars($faculty['middle_name'] ?? ''); ?>"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter middle name">
                </div>
                
                <div>
                    <label for="last_name" class="block text-sm font-semibold text-gray-700 mb-2">
                        Last Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="last_name" name="last_name" required
                           value="<?php echo htmlspecialchars($faculty['last_name']); ?>"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter last name">
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-envelope text-green-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Contact Information</h4>
                    <p class="text-gray-600 text-sm">Email and contact details</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                        Email Address <span class="text-red-500">*</span>
                    </label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo htmlspecialchars($faculty['email']); ?>"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter email address">
                </div>
                
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                        Phone Number
                    </label>
                    <input type="tel" id="phone" name="phone"
                           value="<?php echo htmlspecialchars($faculty['phone'] ?? ''); ?>"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter phone number">
                </div>
            </div>
            
            <div class="mt-6">
                <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                    Address
                </label>
                <textarea id="address" name="address" rows="3"
                          class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                          placeholder="Enter address"><?php echo htmlspecialchars($faculty['address'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Professional Information -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-briefcase text-purple-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Professional Information</h4>
                    <p class="text-gray-600 text-sm">Position and department details</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="position" class="block text-sm font-medium text-gray-700 mb-2">
                        Position
                    </label>
                    <input type="text" id="position" name="position"
                           value="<?php echo htmlspecialchars($faculty['position']); ?>"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter position">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Department
                    </label>
                    <input type="text" value="<?php echo htmlspecialchars($faculty['department']); ?>"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg bg-gray-100 text-gray-600 cursor-not-allowed"
                           readonly>
                    <p class="text-xs text-gray-500 mt-1">Department cannot be changed</p>
                </div>
            </div>
            
            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    QR Code
                </label>
                <input type="text" value="<?php echo htmlspecialchars($faculty['qrcode']); ?>"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg bg-gray-100 text-gray-600 cursor-not-allowed font-mono"
                       readonly>
                <p class="text-xs text-gray-500 mt-1">QR Code cannot be changed</p>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
            <a href="teachers.php" 
               class="px-6 py-3 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all duration-200 font-medium">
                <i class="fas fa-times mr-2"></i>Cancel
            </a>
            <button type="submit" 
                    class="px-6 py-3 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition-all duration-200 transform hover:scale-105 font-medium">
                <i class="fas fa-save mr-2"></i>Update Faculty
            </button>
        </div>
    </form>
</div>

<script>
// Camera functionality
let currentStream = null;

function showCameraView() {
    document.getElementById('cameraView').classList.remove('hidden');
    document.getElementById('photoControls').classList.add('hidden');
}

function hideCameraView() {
    document.getElementById('cameraView').classList.add('hidden');
    document.getElementById('photoControls').classList.remove('hidden');
}

function startCamera() {
    const video = document.getElementById('cameraVideo');
    const errorDiv = document.getElementById('cameraError');
    const errorMessage = document.getElementById('cameraErrorMessage');
    
    // Hide any previous errors
    errorDiv.classList.add('hidden');
    
    // Check if getUserMedia is supported
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        errorMessage.textContent = 'Camera access not supported in this browser.';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    // Modern approach with constraints
    const constraints = {
        video: {
            facingMode: 'user',
            width: { ideal: 640, min: 320, max: 1280 },
            height: { ideal: 480, min: 240, max: 720 }
        }
    };
    
    navigator.mediaDevices.getUserMedia(constraints)
        .then(function(stream) {
            currentStream = stream;
            video.srcObject = stream;
            video.play();
        })
        .catch(function(error) {
            console.error('Camera access error:', error);
            handleCameraError(error);
        });
}

function handleCameraError(error) {
    const errorDiv = document.getElementById('cameraError');
    const errorMessage = document.getElementById('cameraErrorMessage');
    
    let message = 'Unable to access camera. ';
    
    if (error.name === 'NotAllowedError') {
        message += 'Please allow camera permissions.';
    } else if (error.name === 'NotFoundError') {
        message += 'No camera found on this device.';
    } else if (error.name === 'NotSupportedError') {
        message += 'Camera not supported.';
    } else {
        message += 'Please check your camera settings.';
    }
    
    errorMessage.textContent = message;
    errorDiv.classList.remove('hidden');
}

function stopCamera() {
    if (currentStream) {
        currentStream.getTracks().forEach(track => track.stop());
        currentStream = null;
    }
    
    const video = document.getElementById('cameraVideo');
    video.srcObject = null;
}

function capturePhoto() {
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    const context = canvas.getContext('2d');
    
    // Set canvas dimensions to match video
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    // Draw video frame to canvas
    context.drawImage(video, 0, 0);
    
    // Convert to base64 and store in hidden input
    const base64Data = canvas.toDataURL('image/jpeg', 0.8);
    document.getElementById('captured_photo').value = base64Data;
    
    // Clear file input when photo is captured
    document.getElementById('faculty_photo').value = '';
    
    // Show preview
    const photoPreview = document.getElementById('photoPreview');
    photoPreview.innerHTML = `<img src="${base64Data}" alt="Preview" class="w-full h-full object-cover rounded-full">`;
    photoPreview.className = 'w-32 h-32 rounded-full bg-gray-200 border-2 border-solid border-green-300 flex items-center justify-center overflow-hidden';
    
    // Show remove button
    document.getElementById('removePhoto').classList.remove('hidden');
    
    // Stop camera and hide camera view
    stopCamera();
    hideCameraView();
}

function resetPhotoPreview() {
    const preview = document.getElementById('photoPreview');
    const removeBtn = document.getElementById('removePhoto');
    const fileInput = document.getElementById('faculty_photo');
    const capturedPhoto = document.getElementById('captured_photo');
    
    <?php if (!empty($faculty['image_url']) && file_exists('../' . $faculty['image_url'])): ?>
        // Reset to original photo
        preview.innerHTML = '<img src="../<?php echo htmlspecialchars($faculty['image_url']); ?>" alt="Current Photo" class="w-full h-full object-cover rounded-full">';
        preview.className = 'w-32 h-32 rounded-full bg-gray-200 border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden';
        removeBtn.classList.remove('hidden');
    <?php else: ?>
        // Reset to no photo state
        preview.innerHTML = '<div class="flex flex-col items-center"><i class="fas fa-camera text-gray-400 text-xl mb-1"></i><span class="text-xs text-gray-500">No Photo</span></div>';
        preview.className = 'w-32 h-32 rounded-full bg-gray-200 border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden';
        removeBtn.classList.add('hidden');
    <?php endif; ?>
    
    fileInput.value = '';
    capturedPhoto.value = '';
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    const photoInput = document.getElementById('faculty_photo');
    const photoPreview = document.getElementById('photoPreview');
    const removeBtn = document.getElementById('removePhoto');
    
    // Handle photo selection
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        
        if (file) {
            // Clear captured photo when file is selected
            document.getElementById('captured_photo').value = '';
            
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please select a valid image file (JPG, PNG, GIF).');
                photoInput.value = '';
                return;
            }
            
            // Validate file size (2MB max)
            const maxSize = 2 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('Photo size must be less than 2MB.');
                photoInput.value = '';
                return;
            }
            
            // Create preview
            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="w-full h-full object-cover rounded-full">`;
                photoPreview.className = 'w-32 h-32 rounded-full bg-gray-200 border-2 border-solid border-green-300 flex items-center justify-center overflow-hidden';
                removeBtn.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Handle photo removal
    removeBtn.addEventListener('click', function() {
        resetPhotoPreview();
    });
    
    // Handle take photo button
    document.getElementById('takePhoto').addEventListener('click', function() {
        showCameraView();
        startCamera();
    });
    
    // Handle capture photo button
    document.getElementById('capturePhoto').addEventListener('click', function() {
        capturePhoto();
    });
    
    // Handle cancel camera button
    document.getElementById('cancelCamera').addEventListener('click', function() {
        stopCamera();
        hideCameraView();
    });
});

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    const email = document.getElementById('email').value.trim();
    
    if (!firstName || !lastName || !email) {
        e.preventDefault();
        alert('Please fill in all required fields (First Name, Last Name, Email).');
        return false;
    }
    
    // Basic email validation
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address.');
        return false;
    }
    
    return true;
});
</script>

<?php include 'includes/footer.php'; ?>