<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has appropriate role (teacher, head, or admin)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'head', 'admin'])) {
    header('Location: ../index.php');
    exit();
}

// Get class_id from URL
$encrypted_class_id = $_GET['class_id'] ?? '';

if (empty($encrypted_class_id)) {
    header('Location: class-management.php?message=' . urlencode('No class ID provided.') . '&type=error');
    exit();
}

$class_id = safe_decrypt_id($encrypted_class_id);

if (!$class_id) {
    header('Location: class-management.php?message=' . urlencode('Invalid class ID provided.') . '&type=error');
    exit();
}

// Verify the class belongs to the logged-in teacher or user has admin/head role
$class_query = "SELECT tc.*, cc.subject_title, cc.subject_code, cc.units, cc.description as subject_description
                FROM teacher_classes tc
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                WHERE tc.id = ? AND (tc.teacher_id = ? OR ? IN ('admin', 'head'))";

$class_stmt = mysqli_prepare($conn, $class_query);
mysqli_stmt_bind_param($class_stmt, "iis", $class_id, $_SESSION['user_id'], $_SESSION['role']);
mysqli_stmt_execute($class_stmt);
$class_result = mysqli_stmt_get_result($class_stmt);
$class_data = mysqli_fetch_assoc($class_result);

if (!$class_data) {
    header('Location: class-management.php');
    exit();
}

// Get syllabus data
$syllabus_query = "SELECT * FROM class_syllabus WHERE class_id = ?";
$syllabus_stmt = mysqli_prepare($conn, $syllabus_query);
mysqli_stmt_bind_param($syllabus_stmt, "i", $class_id);
mysqli_stmt_execute($syllabus_stmt);
$syllabus_result = mysqli_stmt_get_result($syllabus_stmt);
$syllabus_data = mysqli_fetch_assoc($syllabus_result);

if (!$syllabus_data) {
    header('Location: class_syllabus.php?class_id=' . encrypt_id($class_id) . '&message=' . urlencode('Please create a syllabus first.') . '&type=error');
    exit();
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_peos':
                // Save PEOs
                $peo_codes = $_POST['peo_code'] ?? [];
                $peo_descriptions = $_POST['peo_description'] ?? [];
                $peo_mission_alignments = $_POST['peo_mission_alignment'] ?? [];
                
                // Delete existing PEOs
                $delete_peos = "DELETE FROM syllabus_peos WHERE syllabus_id = ?";
                $delete_stmt = mysqli_prepare($conn, $delete_peos);
                mysqli_stmt_bind_param($delete_stmt, "i", $syllabus_data['id']);
                mysqli_stmt_execute($delete_stmt);
                
                // Insert new PEOs
                for ($i = 0; $i < count($peo_codes); $i++) {
                    if (!empty($peo_codes[$i]) && !empty($peo_descriptions[$i])) {
                        $insert_peo = "INSERT INTO syllabus_peos (syllabus_id, peo_code, peo_description, aligned_to_mission, order_number) VALUES (?, ?, ?, ?, ?)";
                        $insert_stmt = mysqli_prepare($conn, $insert_peo);
                        $aligned = isset($peo_mission_alignments[$i]) ? 1 : 0;
                        mysqli_stmt_bind_param($insert_stmt, "issii", $syllabus_data['id'], $peo_codes[$i], $peo_descriptions[$i], $aligned, $i);
                        mysqli_stmt_execute($insert_stmt);
                    }
                }
                $message = "Program Educational Objectives saved successfully!";
                $message_type = "success";
                break;

            case 'save_pos':
                // Save POs
                $po_codes = $_POST['po_code'] ?? [];
                $po_descriptions = $_POST['po_description'] ?? [];
                
                // Delete existing POs
                $delete_pos = "DELETE FROM syllabus_pos WHERE syllabus_id = ?";
                $delete_stmt = mysqli_prepare($conn, $delete_pos);
                mysqli_stmt_bind_param($delete_stmt, "i", $syllabus_data['id']);
                mysqli_stmt_execute($delete_stmt);
                
                // Insert new POs
                for ($i = 0; $i < count($po_codes); $i++) {
                    if (!empty($po_codes[$i]) && !empty($po_descriptions[$i])) {
                        $insert_po = "INSERT INTO syllabus_pos (syllabus_id, po_code, po_description, order_number) VALUES (?, ?, ?, ?)";
                        $insert_stmt = mysqli_prepare($conn, $insert_po);
                        mysqli_stmt_bind_param($insert_stmt, "issi", $syllabus_data['id'], $po_codes[$i], $po_descriptions[$i], $i);
                        mysqli_stmt_execute($insert_stmt);
                    }
                }
                $message = "Program Outcomes saved successfully!";
                $message_type = "success";
                break;

            case 'save_clos':
                // Save CLOs
                $clo_codes = $_POST['clo_code'] ?? [];
                $clo_descriptions = $_POST['clo_description'] ?? [];
                
                // Delete existing CLOs
                $delete_clos = "DELETE FROM syllabus_clos WHERE syllabus_id = ?";
                $delete_stmt = mysqli_prepare($conn, $delete_clos);
                mysqli_stmt_bind_param($delete_stmt, "i", $syllabus_data['id']);
                mysqli_stmt_execute($delete_stmt);
                
                // Insert new CLOs
                for ($i = 0; $i < count($clo_codes); $i++) {
                    if (!empty($clo_codes[$i]) && !empty($clo_descriptions[$i])) {
                        $insert_clo = "INSERT INTO syllabus_clos (syllabus_id, clo_code, clo_description, order_number) VALUES (?, ?, ?, ?)";
                        $insert_stmt = mysqli_prepare($conn, $insert_clo);
                        mysqli_stmt_bind_param($insert_stmt, "issi", $syllabus_data['id'], $clo_codes[$i], $clo_descriptions[$i], $i);
                        mysqli_stmt_execute($insert_stmt);
                    }
                }
                $message = "Course Learning Outcomes saved successfully!";
                $message_type = "success";
                break;

            case 'save_alignments':
                // Save PEO-PO alignments
                $peo_po_alignments = $_POST['peo_po_alignment'] ?? [];
                
                // Clear existing alignments
                $clear_peo_po = "DELETE FROM syllabus_peo_po_alignment WHERE syllabus_id = ?";
                $clear_stmt = mysqli_prepare($conn, $clear_peo_po);
                mysqli_stmt_bind_param($clear_stmt, "i", $syllabus_data['id']);
                mysqli_stmt_execute($clear_stmt);
                
                // Insert new alignments
                foreach ($peo_po_alignments as $alignment) {
                    list($peo_id, $po_id) = explode('_', $alignment);
                    $insert_alignment = "INSERT INTO syllabus_peo_po_alignment (syllabus_id, peo_id, po_id, is_aligned) VALUES (?, ?, ?, 1)";
                    $insert_stmt = mysqli_prepare($conn, $insert_alignment);
                    mysqli_stmt_bind_param($insert_stmt, "iii", $syllabus_data['id'], $peo_id, $po_id);
                    mysqli_stmt_execute($insert_stmt);
                }
                
                // Save CLO-PO alignments
                $clo_po_alignments = $_POST['clo_po_alignment'] ?? [];
                
                // Clear existing CLO-PO alignments
                $clear_clo_po = "DELETE FROM syllabus_clo_po_alignment WHERE syllabus_id = ?";
                $clear_stmt = mysqli_prepare($conn, $clear_clo_po);
                mysqli_stmt_bind_param($clear_stmt, "i", $syllabus_data['id']);
                mysqli_stmt_execute($clear_stmt);
                
                // Insert new CLO-PO alignments
                foreach ($clo_po_alignments as $alignment) {
                    list($clo_id, $po_id) = explode('_', $alignment);
                    $insert_alignment = "INSERT INTO syllabus_clo_po_alignment (syllabus_id, clo_id, po_id, is_aligned) VALUES (?, ?, ?, 1)";
                    $insert_stmt = mysqli_prepare($conn, $insert_alignment);
                    mysqli_stmt_bind_param($insert_stmt, "iii", $syllabus_data['id'], $clo_id, $po_id);
                    mysqli_stmt_execute($insert_stmt);
                }
                
                $message = "Alignments saved successfully!";
                $message_type = "success";
                break;
        }
    }
}

// Get existing data
$peos_query = "SELECT * FROM syllabus_peos WHERE syllabus_id = ? ORDER BY order_number";
$peos_stmt = mysqli_prepare($conn, $peos_query);
mysqli_stmt_bind_param($peos_stmt, "i", $syllabus_data['id']);
mysqli_stmt_execute($peos_stmt);
$peos_result = mysqli_stmt_get_result($peos_stmt);

$pos_query = "SELECT * FROM syllabus_pos WHERE syllabus_id = ? ORDER BY order_number";
$pos_stmt = mysqli_prepare($conn, $pos_query);
mysqli_stmt_bind_param($pos_stmt, "i", $syllabus_data['id']);
mysqli_stmt_execute($pos_stmt);
$pos_result = mysqli_stmt_get_result($pos_stmt);

$clos_query = "SELECT * FROM syllabus_clos WHERE syllabus_id = ? ORDER BY order_number";
$clos_stmt = mysqli_prepare($conn, $clos_query);
mysqli_stmt_bind_param($clos_stmt, "i", $syllabus_data['id']);
mysqli_stmt_execute($clos_stmt);
$clos_result = mysqli_stmt_get_result($clos_stmt);

// Get alignment data
$peo_po_alignments = [];
$peo_po_query = "SELECT peo_id, po_id FROM syllabus_peo_po_alignment WHERE syllabus_id = ? AND is_aligned = 1";
$peo_po_stmt = mysqli_prepare($conn, $peo_po_query);
mysqli_stmt_bind_param($peo_po_stmt, "i", $syllabus_data['id']);
mysqli_stmt_execute($peo_po_stmt);
$peo_po_result = mysqli_stmt_get_result($peo_po_stmt);
while ($row = mysqli_fetch_assoc($peo_po_result)) {
    $peo_po_alignments[] = $row['peo_id'] . '_' . $row['po_id'];
}

$clo_po_alignments = [];
$clo_po_query = "SELECT clo_id, po_id FROM syllabus_clo_po_alignment WHERE syllabus_id = ? AND is_aligned = 1";
$clo_po_stmt = mysqli_prepare($conn, $clo_po_query);
mysqli_stmt_bind_param($clo_po_stmt, "i", $syllabus_data['id']);
mysqli_stmt_execute($clo_po_stmt);
$clo_po_result = mysqli_stmt_get_result($clo_po_stmt);
while ($row = mysqli_fetch_assoc($clo_po_result)) {
    $clo_po_alignments[] = $row['clo_id'] . '_' . $row['po_id'];
}

// Set page title
$page_title = 'Syllabus Alignment';

// Include the unified LMS header
$sidebar_context = 'lms';
include 'includes/lms_header.php';
?>

<!-- Add Enhanced CSS -->
<link rel="stylesheet" href="../assets/css/syllabus-enhanced.css">

<!-- Enhanced Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-seait-orange to-orange-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div class="mb-4 lg:mb-0">
                <div class="flex items-center mb-2">
                    <div class="bg-white bg-opacity-20 rounded-lg p-2 mr-3">
                        <i class="fas fa-link text-xl"></i>
                    </div>
                    <h1 class="text-3xl lg:text-4xl font-bold">Syllabus Alignment</h1>
                </div>
                <p class="text-lg text-orange-100"><?php echo htmlspecialchars($class_data['subject_title'] . ' - Section ' . $class_data['section']); ?></p>
                <p class="text-sm text-orange-200 mt-1">Manage PEO-PO-CLO alignments and learning outcome relationships</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="class_syllabus.php?class_id=<?php echo encrypt_id($class_id); ?>" class="inline-flex items-center px-4 py-3 bg-white bg-opacity-20 text-white text-sm font-medium rounded-lg hover:bg-white hover:text-seait-orange transition-all duration-200 backdrop-blur-sm">
                    <i class="fas fa-edit mr-2"></i>Edit Syllabus
                </a>
                <a href="syllabus_topics.php?class_id=<?php echo encrypt_id($class_id); ?>" class="inline-flex items-center px-4 py-3 bg-white bg-opacity-20 text-white text-sm font-medium rounded-lg hover:bg-white hover:text-seait-orange transition-all duration-200 backdrop-blur-sm">
                    <i class="fas fa-list mr-2"></i>Manage Topics
                </a>
                <a href="class_dashboard.php?class_id=<?php echo encrypt_id($class_id); ?>" class="inline-flex items-center px-4 py-3 bg-white bg-opacity-20 text-white text-sm font-medium rounded-lg hover:bg-white hover:text-seait-orange transition-all duration-200 backdrop-blur-sm">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg border <?php echo $message_type === 'success' ? 'bg-seait-orange bg-opacity-10 border-seait-orange text-seait-orange' : 'bg-gray-100 border-gray-300 text-gray-700'; ?>">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        </div>
        <div class="ml-3">
            <p class="text-sm font-medium"><?php echo $message; ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Alignment Management Tabs -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="border-b border-gray-200">
        <nav class="flex space-x-8 px-6" aria-label="Tabs">
            <button onclick="showTab('peos')" class="tab-button active py-4 px-1 border-b-2 border-seait-orange font-medium text-sm text-seait-orange">
                Program Educational Objectives
            </button>
            <button onclick="showTab('pos')" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700">
                Program Outcomes
            </button>
            <button onclick="showTab('clos')" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700">
                Course Learning Outcomes
            </button>
            <button onclick="showTab('alignments')" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700">
                Alignments
            </button>
        </nav>
    </div>

    <!-- PEOs Tab -->
    <div id="peos-tab" class="tab-content p-6">
        <form method="POST">
            <input type="hidden" name="action" value="save_peos">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-seait-dark mb-4">Program Educational Objectives (PEOs)</h3>
                <p class="text-sm text-gray-600 mb-4">Define the program educational objectives and their alignment to the institution's mission.</p>
            </div>
            
            <div id="peos-container">
                <?php 
                $peos = [];
                while ($peo = mysqli_fetch_assoc($peos_result)) {
                    $peos[] = $peo;
                }
                if (empty($peos)) {
                    $peos = [['peo_code' => '', 'peo_description' => '', 'aligned_to_mission' => 0]];
                }
                ?>
                
                <?php foreach ($peos as $index => $peo): ?>
                <div class="peo-item border border-gray-200 rounded-lg p-4 mb-4">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">PEO Code</label>
                            <input type="text" name="peo_code[]" value="<?php echo htmlspecialchars($peo['peo_code']); ?>" 
                                   placeholder="e.g., PEO1" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange">
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">PEO Description</label>
                            <textarea name="peo_description[]" rows="3" 
                                      placeholder="Describe the program educational objective..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange"><?php echo htmlspecialchars($peo['peo_description']); ?></textarea>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="flex items-center">
                            <input type="checkbox" name="peo_mission_alignment[]" value="<?php echo $index; ?>" 
                                   <?php echo $peo['aligned_to_mission'] ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Aligned to Institution Mission</span>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="flex justify-between">
                <button type="button" onclick="addPEO()" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>Add PEO
                </button>
                <button type="submit" class="inline-flex items-center px-6 py-2 bg-seait-orange text-white text-sm font-medium rounded-lg hover:bg-orange-600 transition-colors">
                    <i class="fas fa-save mr-2"></i>Save PEOs
                </button>
            </div>
        </form>
    </div>

    <!-- POs Tab -->
    <div id="pos-tab" class="tab-content p-6 hidden">
        <form method="POST">
            <input type="hidden" name="action" value="save_pos">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-seait-dark mb-4">Program Outcomes (POs)</h3>
                <p class="text-sm text-gray-600 mb-4">Define the program outcomes that students should achieve upon graduation.</p>
            </div>
            
            <div id="pos-container">
                <?php 
                $pos = [];
                while ($po = mysqli_fetch_assoc($pos_result)) {
                    $pos[] = $po;
                }
                if (empty($pos)) {
                    $pos = [['po_code' => '', 'po_description' => '']];
                }
                ?>
                
                <?php foreach ($pos as $po): ?>
                <div class="po-item border border-gray-200 rounded-lg p-4 mb-4">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">PO Code</label>
                            <input type="text" name="po_code[]" value="<?php echo htmlspecialchars($po['po_code']); ?>" 
                                   placeholder="e.g., PO1" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange">
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">PO Description</label>
                            <textarea name="po_description[]" rows="3" 
                                      placeholder="Describe the program outcome..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange"><?php echo htmlspecialchars($po['po_description']); ?></textarea>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="flex justify-between">
                <button type="button" onclick="addPO()" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>Add PO
                </button>
                <button type="submit" class="inline-flex items-center px-6 py-2 bg-seait-orange text-white text-sm font-medium rounded-lg hover:bg-orange-600 transition-colors">
                    <i class="fas fa-save mr-2"></i>Save POs
                </button>
            </div>
        </form>
    </div>

    <!-- CLOs Tab -->
    <div id="clos-tab" class="tab-content p-6 hidden">
        <form method="POST">
            <input type="hidden" name="action" value="save_clos">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-seait-dark mb-4">Course Learning Outcomes (CLOs)</h3>
                <p class="text-sm text-gray-600 mb-4">Define the specific learning outcomes for this course.</p>
            </div>
            
            <div id="clos-container">
                <?php 
                $clos = [];
                while ($clo = mysqli_fetch_assoc($clos_result)) {
                    $clos[] = $clo;
                }
                if (empty($clos)) {
                    $clos = [['clo_code' => '', 'clo_description' => '']];
                }
                ?>
                
                <?php foreach ($clos as $clo): ?>
                <div class="clo-item border border-gray-200 rounded-lg p-4 mb-4">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">CLO Code</label>
                            <input type="text" name="clo_code[]" value="<?php echo htmlspecialchars($clo['clo_code']); ?>" 
                                   placeholder="e.g., CLO1" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange">
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">CLO Description</label>
                            <textarea name="clo_description[]" rows="3" 
                                      placeholder="Describe the course learning outcome..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange"><?php echo htmlspecialchars($clo['clo_description']); ?></textarea>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="flex justify-between">
                <button type="button" onclick="addCLO()" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>Add CLO
                </button>
                <button type="submit" class="inline-flex items-center px-6 py-2 bg-seait-orange text-white text-sm font-medium rounded-lg hover:bg-orange-600 transition-colors">
                    <i class="fas fa-save mr-2"></i>Save CLOs
                </button>
            </div>
        </form>
    </div>

    <!-- Alignments Tab -->
    <div id="alignments-tab" class="tab-content p-6 hidden">
        <form method="POST">
            <input type="hidden" name="action" value="save_alignments">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-seait-dark mb-4">Alignment Matrix</h3>
                <p class="text-sm text-gray-600 mb-4">Define the relationships between PEOs, POs, and CLOs.</p>
            </div>
            
            <!-- PEO-PO Alignment Matrix -->
            <div class="mb-8">
                <h4 class="text-md font-semibold text-seait-dark mb-4">PEO-PO Alignment Matrix</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full border border-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border border-gray-200 px-4 py-2 text-left text-sm font-medium text-gray-700">PEOs</th>
                                <?php 
                                mysqli_data_seek($pos_result, 0);
                                while ($po = mysqli_fetch_assoc($pos_result)): 
                                ?>
                                <th class="border border-gray-200 px-4 py-2 text-center text-sm font-medium text-gray-700"><?php echo htmlspecialchars($po['po_code']); ?></th>
                                <?php endwhile; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($peos_result, 0);
                            while ($peo = mysqli_fetch_assoc($peos_result)): 
                            ?>
                            <tr>
                                <td class="border border-gray-200 px-4 py-2 text-sm text-gray-700">
                                    <strong><?php echo htmlspecialchars($peo['peo_code']); ?></strong><br>
                                    <span class="text-xs text-gray-500"><?php echo htmlspecialchars(substr($peo['peo_description'], 0, 50)) . '...'; ?></span>
                                </td>
                                <?php 
                                mysqli_data_seek($pos_result, 0);
                                while ($po = mysqli_fetch_assoc($pos_result)): 
                                    $alignment_key = $peo['id'] . '_' . $po['id'];
                                    $is_aligned = in_array($alignment_key, $peo_po_alignments);
                                ?>
                                <td class="border border-gray-200 px-4 py-2 text-center">
                                    <input type="checkbox" name="peo_po_alignment[]" value="<?php echo $alignment_key; ?>" 
                                           <?php echo $is_aligned ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                                </td>
                                <?php endwhile; ?>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- CLO-PO Alignment Matrix -->
            <div class="mb-8">
                <h4 class="text-md font-semibold text-seait-dark mb-4">CLO-PO Alignment Matrix</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full border border-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border border-gray-200 px-4 py-2 text-left text-sm font-medium text-gray-700">CLOs</th>
                                <?php 
                                mysqli_data_seek($pos_result, 0);
                                while ($po = mysqli_fetch_assoc($pos_result)): 
                                ?>
                                <th class="border border-gray-200 px-4 py-2 text-center text-sm font-medium text-gray-700"><?php echo htmlspecialchars($po['po_code']); ?></th>
                                <?php endwhile; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($clos_result, 0);
                            while ($clo = mysqli_fetch_assoc($clos_result)): 
                            ?>
                            <tr>
                                <td class="border border-gray-200 px-4 py-2 text-sm text-gray-700">
                                    <strong><?php echo htmlspecialchars($clo['clo_code']); ?></strong><br>
                                    <span class="text-xs text-gray-500"><?php echo htmlspecialchars(substr($clo['clo_description'], 0, 50)) . '...'; ?></span>
                                </td>
                                <?php 
                                mysqli_data_seek($pos_result, 0);
                                while ($po = mysqli_fetch_assoc($pos_result)): 
                                    $alignment_key = $clo['id'] . '_' . $po['id'];
                                    $is_aligned = in_array($alignment_key, $clo_po_alignments);
                                ?>
                                <td class="border border-gray-200 px-4 py-2 text-center">
                                    <input type="checkbox" name="clo_po_alignment[]" value="<?php echo $alignment_key; ?>" 
                                           <?php echo $is_aligned ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                                </td>
                                <?php endwhile; ?>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center px-6 py-2 bg-seait-orange text-white text-sm font-medium rounded-lg hover:bg-orange-600 transition-colors">
                    <i class="fas fa-save mr-2"></i>Save Alignments
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => content.classList.add('hidden'));
    
    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active', 'border-seait-orange', 'text-seait-orange');
        button.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Show selected tab content
    document.getElementById(tabName + '-tab').classList.remove('hidden');
    
    // Add active class to selected tab button
    event.target.classList.add('active', 'border-seait-orange', 'text-seait-orange');
    event.target.classList.remove('border-transparent', 'text-gray-500');
}

function addPEO() {
    const container = document.getElementById('peos-container');
    const newIndex = container.children.length;
    
    const peoItem = document.createElement('div');
    peoItem.className = 'peo-item border border-gray-200 rounded-lg p-4 mb-4';
    peoItem.innerHTML = `
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">PEO Code</label>
                <input type="text" name="peo_code[]" placeholder="e.g., PEO1" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange">
            </div>
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">PEO Description</label>
                <textarea name="peo_description[]" rows="3" 
                          placeholder="Describe the program educational objective..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange"></textarea>
            </div>
        </div>
        <div class="mt-3">
            <label class="flex items-center">
                <input type="checkbox" name="peo_mission_alignment[]" value="${newIndex}" 
                       class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                <span class="ml-2 text-sm text-gray-700">Aligned to Institution Mission</span>
            </label>
        </div>
    `;
    
    container.appendChild(peoItem);
}

function addPO() {
    const container = document.getElementById('pos-container');
    
    const poItem = document.createElement('div');
    poItem.className = 'po-item border border-gray-200 rounded-lg p-4 mb-4';
    poItem.innerHTML = `
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">PO Code</label>
                <input type="text" name="po_code[]" placeholder="e.g., PO1" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange">
            </div>
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">PO Description</label>
                <textarea name="po_description[]" rows="3" 
                          placeholder="Describe the program outcome..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange"></textarea>
            </div>
        </div>
    `;
    
    container.appendChild(poItem);
}

function addCLO() {
    const container = document.getElementById('clos-container');
    
    const cloItem = document.createElement('div');
    cloItem.className = 'clo-item border border-gray-200 rounded-lg p-4 mb-4';
    cloItem.innerHTML = `
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">CLO Code</label>
                <input type="text" name="clo_code[]" placeholder="e.g., CLO1" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange">
            </div>
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">CLO Description</label>
                <textarea name="clo_description[]" rows="3" 
                          placeholder="Describe the course learning outcome..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange"></textarea>
            </div>
        </div>
    `;
    
    container.appendChild(cloItem);
}
</script>

<?php include 'includes/unified-footer.php'; ?>
