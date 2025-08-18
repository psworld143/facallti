<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Get class_id from URL
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

if (!$class_id) {
    header('Location: class-management.php');
    exit();
}

// Verify the class belongs to the logged-in teacher
$class_query = "SELECT tc.*, cc.subject_title, cc.subject_code, cc.units, cc.description as subject_description
                FROM teacher_classes tc
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                JOIN faculty f ON tc.teacher_id = f.id
                WHERE tc.id = ? AND f.email = ? AND f.is_active = 1";
$class_stmt = mysqli_prepare($conn, $class_query);
mysqli_stmt_bind_param($class_stmt, "is", $class_id, $_SESSION['username']);
mysqli_stmt_execute($class_stmt);
$class_result = mysqli_stmt_get_result($class_stmt);
$class_data = mysqli_fetch_assoc($class_result);

if (!$class_data) {
    header('Location: class-management.php');
    exit();
}

// Set page title
$page_title = 'Import Students - ' . $class_data['subject_title'];

// Handle AJAX import processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_import') {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => '', 'progress' => 0, 'total' => 0, 'processed' => 0, 'success_count' => 0, 'error_count' => 0, 'errors' => []];
    
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['excel_file'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check file extension
        if (!in_array($file_extension, ['xlsx', 'xls'])) {
            $response['message'] = 'Please upload an Excel file (.xlsx or .xls)';
            echo json_encode($response);
            exit();
        }
        
        // Process the Excel file
        require_once '../vendor/autoload.php';
        
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            // Remove header row
            $header = array_shift($rows);
            
            // Validate header
            $expected_headers = ['ID Number', 'Firstname', 'Lastname'];
            $debug = [];
            $debug['header'] = $header;
            $debug['expected_headers'] = $expected_headers;
            $debug['file_extension'] = $file_extension;
            if (
                count($header) < 3 ||
                $header[0] !== $expected_headers[0] ||
                $header[1] !== $expected_headers[1] ||
                $header[2] !== $expected_headers[2]
            ) {
                $response['message'] = 'Invalid file format. The first three columns must be: ID Number, Firstname, Lastname.';
                $response['debug'] = $debug;
                echo json_encode($response);
                exit();
            }
            
            // Filter out empty rows
            $data_rows = array_filter($rows, function($row) {
                return !empty(array_filter($row));
            });
            
            $total_rows = count($data_rows);
            $response['total'] = $total_rows;
            
            if ($total_rows === 0) {
                $response['message'] = 'No data found in the file.';
                echo json_encode($response);
                exit();
            }
            
            // Process in batches of 50 for better performance
            $batch_size = 50;
            $success_count = 0;
            $error_count = 0;
            $errors = [];
            
            foreach (array_chunk($data_rows, $batch_size) as $batch_index => $batch) {
                foreach ($batch as $index => $row) {
                    $global_index = $batch_index * $batch_size + $index;
                    
                    $student_id = trim($row[0]);
                    $firstname = trim($row[1]);
                    $lastname = trim($row[2]);
                    
                    // Validate data
                    if (empty($student_id) || empty($firstname) || empty($lastname)) {
                        $errors[] = "Row " . ($global_index + 2) . ": Missing required data";
                        $error_count++;
                        continue;
                    }
                    
                    // Check if student already exists in database
                    $check_query = "SELECT id FROM students WHERE student_id = ?";
                    $check_stmt = mysqli_prepare($conn, $check_query);
                    mysqli_stmt_bind_param($check_stmt, "s", $student_id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        // Student exists, check if already enrolled in this class
                        $enrollment_check = "SELECT id FROM class_enrollments WHERE student_id = (SELECT id FROM students WHERE student_id = ?) AND class_id = ?";
                        $enrollment_stmt = mysqli_prepare($conn, $enrollment_check);
                        mysqli_stmt_bind_param($enrollment_stmt, "si", $student_id, $class_id);
                        mysqli_stmt_execute($enrollment_stmt);
                        $enrollment_result = mysqli_stmt_get_result($enrollment_stmt);
                        
                        if (mysqli_num_rows($enrollment_result) > 0) {
                            $errors[] = "Row " . ($global_index + 2) . ": Student $student_id is already enrolled in this class";
                            $error_count++;
                        } else {
                            // Enroll existing student
                            $student_data = mysqli_fetch_assoc($check_result);
                            $enroll_query = "INSERT INTO class_enrollments (student_id, class_id, status) VALUES (?, ?, 'enrolled')";
                            $enroll_stmt = mysqli_prepare($conn, $enroll_query);
                            mysqli_stmt_bind_param($enroll_stmt, "ii", $student_data['id'], $class_id);
                            
                            if (mysqli_stmt_execute($enroll_stmt)) {
                                $success_count++;
                            } else {
                                $errors[] = "Row " . ($global_index + 2) . ": Failed to enroll student $student_id";
                                $error_count++;
                            }
                        }
                    } else {
                        // Create new student
                        $default_password = password_hash('Seait123', PASSWORD_DEFAULT);
                        // Remove spaces from firstname and lastname for email
                        $email_firstname = str_replace(' ', '', strtolower($firstname));
                        $email_lastname = str_replace(' ', '', strtolower($lastname));
                        $email = $email_firstname . '.' . $email_lastname . '@seait.edu.ph';
                        
                        // Check if email already exists
                        $email_check = "SELECT id FROM students WHERE email = ?";
                        $email_stmt = mysqli_prepare($conn, $email_check);
                        mysqli_stmt_bind_param($email_stmt, "s", $email);
                        mysqli_stmt_execute($email_stmt);
                        $email_result = mysqli_stmt_get_result($email_stmt);
                        
                        if (mysqli_num_rows($email_result) > 0) {
                            // Generate unique email
                            $counter = 1;
                            do {
                                $email = $email_firstname . '.' . $email_lastname . $counter . '@seait.edu.ph';
                                $email_stmt = mysqli_prepare($conn, $email_check);
                                mysqli_stmt_bind_param($email_stmt, "s", $email);
                                mysqli_stmt_execute($email_stmt);
                                $email_result = mysqli_stmt_get_result($email_stmt);
                                $counter++;
                            } while (mysqli_num_rows($email_result) > 0);
                        }
                        
                        $insert_query = "INSERT INTO students (student_id, first_name, last_name, email, password_hash, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())";
                        $insert_stmt = mysqli_prepare($conn, $insert_query);
                        mysqli_stmt_bind_param($insert_stmt, "sssss", $student_id, $firstname, $lastname, $email, $default_password);
                        
                        if (mysqli_stmt_execute($insert_stmt)) {
                            $new_student_id = mysqli_insert_id($conn);
                            
                            // Enroll the new student
                            $enroll_query = "INSERT INTO class_enrollments (student_id, class_id, status) VALUES (?, ?, 'enrolled')";
                            $enroll_stmt = mysqli_prepare($conn, $enroll_query);
                            mysqli_stmt_bind_param($enroll_stmt, "ii", $new_student_id, $class_id);
                            
                            if (mysqli_stmt_execute($enroll_stmt)) {
                                $success_count++;
                            } else {
                                $errors[] = "Row " . ($global_index + 2) . ": Failed to enroll new student $student_id";
                                $error_count++;
                            }
                        } else {
                            $errors[] = "Row " . ($global_index + 2) . ": Failed to create student $student_id";
                            $error_count++;
                        }
                    }
                    
                    $response['processed'] = $global_index + 1;
                    $response['progress'] = round(($response['processed'] / $total_rows) * 100, 2);
                    $response['success_count'] = $success_count;
                    $response['error_count'] = $error_count;
                }
                
                // Send progress update every batch
                // if ($batch_index % 2 == 0) {
                //     echo json_encode($response);
                //     ob_flush();
                //     flush();
                // }
            }
            
            // Final response
            $response['success'] = true;
            $response['progress'] = 100;
            $response['message'] = "Import completed! Successfully imported $success_count students";
            if ($error_count > 0) {
                $response['message'] .= " with $error_count errors";
            }
            $response['errors'] = array_slice($errors, 0, 20); // Limit errors to first 20
            $response['debug'] = $debug;
            echo json_encode($response);
            
        } catch (Exception $e) {
            $response['message'] = 'Error processing file: ' . $e->getMessage();
            $response['debug'] = $debug;
            echo json_encode($response);
        }
    } else {
        $response['message'] = 'Please select a file to upload';
        $response['debug'] = $debug;
        echo json_encode($response);
    }
    exit();
}

// Include the unified LMS header
include 'includes/lms_header.php';
?>

<div class="mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark">Import Students</h1>
            <p class="text-gray-600 mt-1">Import students from Excel file to <?php echo htmlspecialchars($class_data['subject_title'] . ' - ' . $class_data['section']); ?></p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-2">
            <a href="class_students.php?class_id=<?php echo $class_id; ?>" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Students
            </a>
        </div>
    </div>
</div>

    <!-- Import Instructions -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-info-circle mr-2"></i>Import Instructions
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="font-medium text-gray-800 mb-2">File Requirements:</h3>
                <ul class="text-sm text-gray-700 space-y-1">
                    <li>• Excel format (.xlsx or .xls)</li>
                    <li>• Use the provided template</li>
                    <li>• Columns: ID Number, Firstname, Lastname</li>
                    <li>• No empty rows or missing data</li>
                    <li>• Supports 500+ records with progress tracking</li>
                </ul>
            </div>
            <div>
                <h3 class="font-medium text-gray-800 mb-2">Student Creation:</h3>
                <ul class="text-sm text-gray-700 space-y-1">
                    <li>• New students will be created automatically</li>
                    <li>• Default password: <strong>Seait123</strong></li>
                    <li>• Email: firstname.lastname@seait.edu.ph</li>
                    <li>• Duplicate students will be enrolled only</li>
                    <li>• Real-time progress tracking</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Download Template -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-download mr-2"></i>Download Template
        </h2>
        <p class="text-gray-600 mb-4">Download the Excel template to ensure proper formatting:</p>
        <a href="download_template.php?class_id=<?php echo $class_id; ?>" class="inline-flex items-center bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
            <i class="fas fa-file-excel mr-2"></i>Download Template
        </a>
    </div>

    <!-- Upload Form -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-upload mr-2"></i>Upload Excel File
        </h2>
        
        <form id="importForm" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="action" value="process_import">
            <div>
                <label for="excel_file" class="block text-sm font-medium text-gray-700 mb-2">
                    Select Excel File
                </label>
                <input type="file" 
                       id="excel_file" 
                       name="excel_file" 
                       accept=".xlsx,.xls"
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-seait-orange file:text-white hover:file:bg-orange-600 transition"
                       required>
                <p class="text-xs text-gray-500 mt-1">Accepted formats: .xlsx, .xls (Supports 500+ records)</p>
            </div>
            
            <div class="flex items-center space-x-4">
                <button type="submit" id="importBtn" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition">
                    <i class="fas fa-upload mr-2"></i>Import Students
                </button>
                <a href="class_students.php?class_id=<?php echo $class_id; ?>" class="text-gray-600 hover:text-gray-800 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Progress Section (Hidden by default) -->
    <div id="progressSection" class="bg-white rounded-lg shadow-md p-6 mb-6 hidden">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-spinner fa-spin mr-2"></i>Import Progress
        </h2>
        
        <div class="space-y-4">
            <!-- Progress Bar -->
            <div>
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span id="progressText">Processing...</span>
                    <span id="progressPercent">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div id="progressBar" class="bg-seait-orange h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>
            
            <!-- Status Information -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div class="bg-blue-50 p-3 rounded-lg">
                    <div class="font-medium text-blue-900">Total Records</div>
                    <div id="totalRecords" class="text-blue-700">0</div>
                </div>
                <div class="bg-green-50 p-3 rounded-lg">
                    <div class="font-medium text-green-900">Successfully Imported</div>
                    <div id="successCount" class="text-green-700">0</div>
                </div>
                <div class="bg-red-50 p-3 rounded-lg">
                    <div class="font-medium text-red-900">Errors</div>
                    <div id="errorCount" class="text-red-700">0</div>
                </div>
            </div>
            
            <!-- Error Details -->
            <div id="errorDetails" class="hidden">
                <h3 class="font-medium text-gray-900 mb-2">Error Details:</h3>
                <div id="errorList" class="bg-red-50 border border-red-200 rounded-lg p-4 max-h-40 overflow-y-auto text-sm text-red-700"></div>
            </div>
        </div>
    </div>

    <!-- Sample Data Preview -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-table mr-2"></i>Sample Data Format
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Firstname</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lastname</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2021-0001</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">John</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Doe</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2021-0002</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Jane</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Smith</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2021-0003</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Mike</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Johnson</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('importForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const importBtn = document.getElementById('importBtn');
    const progressSection = document.getElementById('progressSection');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const progressPercent = document.getElementById('progressPercent');
    const totalRecords = document.getElementById('totalRecords');
    const successCount = document.getElementById('successCount');
    const errorCount = document.getElementById('errorCount');
    const errorDetails = document.getElementById('errorDetails');
    const errorList = document.getElementById('errorList');
    // Show progress section and disable button
    progressSection.classList.remove('hidden');
    importBtn.disabled = true;
    importBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Importing...';
    // Reset progress
    progressBar.style.width = '0%';
    progressPercent.textContent = '0%';
    totalRecords.textContent = '0';
    successCount.textContent = '0';
    errorCount.textContent = '0';
    errorDetails.classList.add('hidden');
    errorList.innerHTML = '';
    // Scroll to progress section
    progressSection.scrollIntoView({ behavior: 'smooth' });
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        console.log('Raw response:', text);
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', e);
            showImportStatusModal('Import failed: Invalid server response. See console for details.', false);
            importBtn.disabled = false;
            importBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Import Students';
            return;
        }
        if (data.success) {
            // Update progress
            progressBar.style.width = data.progress + '%';
            progressPercent.textContent = data.progress + '%';
            totalRecords.textContent = data.total;
            successCount.textContent = data.success_count;
            errorCount.textContent = data.error_count;
            if (data.progress === 100) {
                progressText.textContent = 'Import completed!';
                importBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Import Complete';
                // Show errors if any
                if (data.errors && data.errors.length > 0) {
                    errorDetails.classList.remove('hidden');
                    errorList.innerHTML = data.errors.map(error => `<div class="mb-1">• ${error}</div>`).join('');
                    // Log all errors to the console
                    console.error('Import errors:', data.errors);
                }
                // Show success message in modal (with error count if any)
                let msg = 'Import completed successfully!\n\n' + data.message;
                if (data.errors && data.errors.length > 0) {
                    msg += '\n\nSome records could not be imported. See error details below.';
                }
                showImportStatusModal(msg, true);
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                progressText.textContent = `Processing ${data.processed} of ${data.total} records...`;
            }
        } else {
            progressText.textContent = 'Error: ' + data.message;
            importBtn.disabled = false;
            importBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Import Students';
            console.error('Import error:', data);
            if (data.errors && data.errors.length > 0) {
                console.error('Import errors:', data.errors);
            }
            if (data.debug) {
                console.error('Debug info:', data.debug);
            }
            let msg = 'Import failed: ' + data.message;
            if (data.errors && data.errors.length > 0) {
                msg += '\n\nSome records could not be imported. See error details below.';
            }
            showImportStatusModal(msg, false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        progressText.textContent = 'Error occurred during import';
        importBtn.disabled = false;
        importBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Import Students';
        showImportStatusModal('An error occurred during import. Please try again.', false);
    });
});

// Custom Import Status Modal
function showImportStatusModal(message, isSuccess = true) {
    let modal = document.getElementById('importStatusModal');
    let modalContent = document.getElementById('importStatusModalContent');
    let title = document.getElementById('importStatusTitle');
    let msg = document.getElementById('importStatusMessage');
    title.textContent = isSuccess ? 'Import Successful' : 'Import Failed';
    msg.textContent = message;
    // Change icon and color based on success or error
    let icon = modalContent.querySelector('i');
    icon.className = isSuccess ? 'fas fa-check-circle text-green-600' : 'fas fa-times-circle text-red-600';
    // Animate in
    modalContent.classList.remove('scale-95', 'opacity-0');
    modalContent.classList.add('scale-100', 'opacity-100');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeImportStatusModal() {
    let modal = document.getElementById('importStatusModal');
    let modalContent = document.getElementById('importStatusModalContent');
    // Animate out
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }, 300);
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImportStatusModal();
    }
});
</script>

<!-- Import Status Modal -->
<div id="importStatusModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl max-w-md w-full transform transition-all duration-300 scale-95 opacity-0" id="importStatusModalContent">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-green-100">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900" id="importStatusTitle">Import Status</h3>
                </div>
            </div>
        </div>
        <div class="px-6 py-6">
            <p class="text-gray-700 mb-3" id="importStatusMessage"></p>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg flex justify-end">
            <button type="button" onclick="closeImportStatusModal()"
                class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 transition-all duration-200 transform hover:scale-105">
                <i class="fas fa-times mr-2"></i>Close
            </button>
        </div>
    </div>
</div>

<?php
// Include the unified footer
include 'includes/footer.php';
?> 
