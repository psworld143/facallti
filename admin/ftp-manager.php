<?php
session_start();
require_once '../config/database.php';
require_once '../includes/unified-error-handler.php';

// Use the existing check_admin function
require_once '../includes/functions.php';
check_admin();

$page_title = 'FTP Manager';

// Load FTP accounts for dropdown
$ftp_accounts = require_once '../config/ftp-accounts.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SEAIT Admin</title>
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
    <style>
        .ftp-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .ftp-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: #FF6B35;
        }
        
        .file-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            margin-right: 8px;
        }
        
        .file-directory { background-color: #FEF3C7; color: #D97706; }
        
        /* Programming Languages */
        .file-php { background-color: #F3E8FF; color: #7C3AED; }
        .file-js { background-color: #FEF3C7; color: #D97706; }
        .file-html { background-color: #FEE2E2; color: #DC2626; }
        .file-css { background-color: #DBEAFE; color: #2563EB; }
        .file-python { background-color: #F0FDF4; color: #16A34A; }
        .file-java { background-color: #FEF3C7; color: #D97706; }
        .file-cpp { background-color: #E0E7FF; color: #7C3AED; }
        .file-c { background-color: #E0E7FF; color: #7C3AED; }
        .file-sql { background-color: #FCE7F3; color: #EC4899; }
        .file-json { background-color: #FEF3C7; color: #D97706; }
        .file-xml { background-color: #DBEAFE; color: #2563EB; }
        .file-typescript { background-color: #DBEAFE; color: #2563EB; }
        .file-react { background-color: #E0F2FE; color: #0284C7; }
        .file-vue { background-color: #F0FDF4; color: #16A34A; }
        .file-ruby { background-color: #FEE2E2; color: #DC2626; }
        .file-go { background-color: #E0F2FE; color: #0284C7; }
        .file-rust { background-color: #FEF3C7; color: #D97706; }
        .file-swift { background-color: #FEF3C7; color: #D97706; }
        .file-kotlin { background-color: #F3E8FF; color: #7C3AED; }
        .file-scala { background-color: #FEE2E2; color: #DC2626; }
        .file-shell { background-color: #F3F4F6; color: #6B7280; }
        .file-powershell { background-color: #E0F2FE; color: #0284C7; }
        .file-batch { background-color: #F3F4F6; color: #6B7280; }
        
        /* Documents */
        .file-pdf { background-color: #FEE2E2; color: #DC2626; }
        .file-word { background-color: #DBEAFE; color: #2563EB; }
        .file-excel { background-color: #F0FDF4; color: #16A34A; }
        .file-powerpoint { background-color: #FEF3C7; color: #D97706; }
        .file-text { background-color: #F3F4F6; color: #6B7280; }
        
        /* Images */
        .file-image { background-color: #DBEAFE; color: #2563EB; }
        
        /* Archives */
        .file-archive { background-color: #E0E7FF; color: #7C3AED; }
        
        /* Media */
        .file-video { background-color: #FCE7F3; color: #EC4899; }
        .file-audio { background-color: #F0FDF4; color: #16A34A; }
        
        /* Default */
        .file-default { background-color: #F3F4F6; color: #6B7280; }
        
        /* Checkbox styling */
        .local-file-checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
            border: 2px solid #D1D5DB;
            border-radius: 4px;
            background-color: white;
            transition: all 0.2s ease;
        }
        
        .local-file-checkbox:checked {
            background-color: #FF6B35;
            border-color: #FF6B35;
        }
        
        .local-file-checkbox:hover {
            border-color: #FF6B35;
        }
        
        .breadcrumb-item {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .breadcrumb-item:hover {
            color: #FF6B35;
            text-decoration: underline;
        }
        
        .local-breadcrumb-item {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .local-breadcrumb-item:hover {
            color: #2563EB;
            text-decoration: underline;
        }
        
        .upload-zone {
            border: 2px dashed #D1D5DB;
            transition: all 0.3s ease;
        }
        
        .upload-zone.dragover {
            border-color: #FF6B35;
            background-color: #FFF8F0;
        }
        
        .file-table {
            font-size: 13px;
        }
        
        .file-table th,
        .file-table td {
            padding: 8px 12px;
        }
        
        .file-row {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .file-row:hover {
            background-color: #F9FAFB;
        }
        
        .loading-spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #FF6B35;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Modal animations */
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        @keyframes modalFadeOut {
            from {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
            to {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
        }
        
        .modal-content {
            animation: modalFadeIn 0.3s ease-out;
        }
        
        .modal-content.fade-out {
            animation: modalFadeOut 0.2s ease-in;
        }
        
        /* Modal backdrop blur effect */
        .modal-backdrop {
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/admin-header.php'; ?>
    
    <div class="flex">
        <?php include 'includes/admin-sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 lg:ml-64 pt-16">
            <div class="p-6">
                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-seait-orange to-orange-600 rounded-xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-server text-white text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900">FTP Manager</h1>
                                <p class="text-gray-600">Browse and manage files on your FTP server</p>
                            </div>
                        </div>
                        
                        <!-- Account Selector -->
                        <div class="flex items-center space-x-4">
                            <label class="text-sm font-medium text-gray-700">FTP Account:</label>
                            <select id="accountSelector" class="border border-gray-300 rounded-lg px-3 py-2 bg-white">
                                <?php foreach ($ftp_accounts as $account_name => $config): ?>
                                    <option value="<?php echo htmlspecialchars($account_name); ?>">
                                        <?php echo htmlspecialchars($account_name); ?> (<?php echo htmlspecialchars($config['host']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Connection Status -->
                    <div class="ftp-card rounded-xl p-4 shadow-lg mb-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div id="connectionStatus" class="w-3 h-3 rounded-full bg-gray-400"></div>
                                <span id="connectionText" class="font-medium text-gray-700">Not Connected</span>
                                <span id="currentPath" class="text-sm text-gray-500">/</span>
                            </div>
                            <button id="connectBtn" class="bg-seait-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                                <i class="fas fa-plug mr-2"></i>Connect
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Breadcrumb Navigation -->
                <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
                    <div class="flex items-center space-x-2 text-sm">
                        <i class="fas fa-folder text-gray-400"></i>
                        <span class="text-gray-500">Path:</span>
                        <div id="breadcrumb" class="flex items-center space-x-2">
                            <span class="breadcrumb-item text-seait-orange font-medium" data-path="/">/</span>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <button id="refreshBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                    
                    <button id="uploadBtn" class="bg-green-500 hover:bg-green-600 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-upload mr-2"></i>Upload Files
                    </button>
                    
                    <button id="createFolderBtn" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-folder-plus mr-2"></i>New Folder
                    </button>
                    
                    <button id="deleteBtn" class="bg-red-500 hover:bg-red-600 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg" disabled>
                        <i class="fas fa-trash mr-2"></i>Delete Selected
                    </button>
                    
                    <button id="testFileBtn" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg" onclick="testFileSelection()">
                        <i class="fas fa-vial mr-2"></i>Test File Selection
                    </button>
                </div>
                
                <!-- Dual Pane File Browser -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="p-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-800">File Manager</h3>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-500">Drag files between panes to transfer</span>
                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dual Pane Container -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 p-4">
                        <!-- Local Files Pane -->
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-semibold text-gray-800">
                                        <i class="fas fa-desktop mr-2 text-blue-600"></i>Local Files
                                    </h4>
                                    <div class="flex items-center space-x-2">
                                        <button id="refreshLocalBtn" class="text-blue-600 hover:text-blue-800 p-1">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        <button id="selectLocalFolderBtn" class="text-blue-600 hover:text-blue-800 p-1">
                                            <i class="fas fa-folder-open"></i>
                                        </button>
                                    </div>
                                </div>
                                                                                            <div class="flex items-center space-x-2 mt-2">
                                    <i class="fas fa-folder text-gray-400"></i>
                                    <span class="text-sm text-gray-600">Path:</span>
                                    <div id="localBreadcrumb" class="flex items-center space-x-2">
                                        <span class="local-breadcrumb-item text-blue-600 font-medium" data-path="">Select a folder</span>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2 mt-1">
                                    <span class="text-xs text-gray-500" id="localFileCount">No files selected</span>
                                </div>
                            </div>
                            
                            <div id="localLoadingIndicator" class="flex items-center justify-center p-8">
                                <div class="loading-spinner mr-3"></div>
                                <span class="text-gray-600">Select a local folder</span>
                            </div>
                            
                            <div id="localFileList" class="hidden">
                                <table class="file-table w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="text-left">
                                                <input type="checkbox" id="selectAllLocal" class="rounded">
                                            </th>
                                            <th class="text-left">Name</th>
                                            <th class="text-left">Size</th>
                                            <th class="text-left">Modified</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="localFileTableBody">
                                        <!-- Local files will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <div id="localEmptyState" class="hidden text-center py-12 text-gray-500">
                                <i class="fas fa-folder-open text-4xl mb-4"></i>
                                <p>No local folder selected</p>
                            </div>
                        </div>
                        
                        <!-- Online FTP Pane -->
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-semibold text-gray-800">
                                        <i class="fas fa-server mr-2 text-green-600"></i>Online FTP
                                    </h4>
                                    <div class="flex items-center space-x-2">
                                        <button id="refreshFTPBtn" class="text-green-600 hover:text-green-800 p-1">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        <button id="uploadBtnPane" class="text-green-600 hover:text-green-800 p-1">
                                            <i class="fas fa-upload"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2 mt-2">
                                    <i class="fas fa-folder text-gray-400"></i>
                                    <span class="text-sm text-gray-600" id="ftpPath">/</span>
                                </div>
                            </div>
                            
                            <div id="ftpLoadingIndicator" class="flex items-center justify-center p-8">
                                <div class="loading-spinner mr-3"></div>
                                <span class="text-gray-600">Connecting to FTP...</span>
                            </div>
                            
                            <div id="ftpFileList" class="hidden">
                                <table class="file-table w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="text-left">
                                                <input type="checkbox" id="selectAllFTP" class="rounded">
                                            </th>
                                            <th class="text-left">Name</th>
                                            <th class="text-left">Size</th>
                                            <th class="text-left">Modified</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ftpFileTableBody">
                                        <!-- FTP files will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <div id="ftpEmptyState" class="hidden text-center py-12 text-gray-500">
                                <i class="fas fa-folder-open text-4xl mb-4"></i>
                                <p>This directory is empty</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Transfer Controls -->
                    <div class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                        <div class="flex items-center justify-center space-x-4">
                            <button id="uploadSelectedBtn" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition-colors" disabled>
                                <i class="fas fa-arrow-right mr-2"></i>Upload Selected
                            </button>
                            <button id="downloadSelectedBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-colors" disabled>
                                <i class="fas fa-arrow-left mr-2"></i>Download Selected
                            </button>
                            <button id="syncFolderBtn" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg font-medium transition-colors" disabled>
                                <i class="fas fa-sync mr-2"></i>Sync Folder
                            </button>
                        </div>
                        
                        <!-- Transfer Progress -->
                        <div id="transferProgress" class="hidden mt-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">Transfer Progress</span>
                                <span id="transferProgressPercent" class="text-sm text-gray-500">0%</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-2">
                                <div id="transferProgressBar" class="bg-seait-orange h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
                            <p id="transferProgressText" class="text-sm text-gray-600 mt-2">Preparing transfer...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Upload Modal -->
    <div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-upload text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Upload Files</h3>
                <p class="text-gray-600 text-sm">Select files or folders to upload. Folder uploads will preserve the directory structure on the FTP server.</p>
            </div>
            
            <!-- Upload Options -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <!-- File Upload -->
                <div class="upload-zone rounded-lg p-6 text-center border-2 border-dashed border-gray-300" id="uploadZone">
                    <i class="fas fa-file-upload text-3xl text-gray-400 mb-2"></i>
                    <h4 class="font-semibold text-gray-700 mb-2">Upload Files</h4>
                    <p class="text-gray-600 mb-4 text-sm">Select individual files to upload</p>
                    <input type="file" id="fileInput" multiple class="hidden">
                    <button type="button" id="chooseFilesBtn" class="bg-seait-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-folder-open mr-2"></i>Choose Files
                    </button>
                </div>
                
                <!-- Folder Upload -->
                <div class="upload-zone rounded-lg p-6 text-center border-2 border-dashed border-gray-300" id="folderUploadZone">
                    <i class="fas fa-folder-upload text-3xl text-gray-400 mb-2"></i>
                    <h4 class="font-semibold text-gray-700 mb-2">Upload Folder</h4>
                    <p class="text-gray-600 mb-4 text-sm">Select a folder to upload all contents with directory structure preserved</p>
                    <input type="file" id="folderInput" webkitdirectory directory multiple class="hidden">
                    <button type="button" id="chooseFolderBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-folder-plus mr-2"></i>Choose Folder
                    </button>
                </div>
            </div>
            
            <!-- Selected Files List -->
            <div id="selectedFilesList" class="hidden mb-4">
                <h4 class="font-semibold text-gray-700 mb-2">Selected Files:</h4>
                <div class="max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-3 bg-gray-50">
                    <div id="filesList" class="space-y-1"></div>
                </div>
            </div>
            
            <!-- Folder Structure Preview -->
            <div id="folderStructurePreview" class="hidden mb-4">
                <h4 class="font-semibold text-gray-700 mb-2">📁 Folder Structure Preview:</h4>
                <div class="max-h-32 overflow-y-auto border border-gray-200 rounded-lg p-3 bg-blue-50">
                    <div id="structurePreview" class="text-sm font-mono text-blue-800"></div>
                </div>
            </div>
            
            <!-- Upload Progress -->
            <div id="uploadProgress" class="hidden mb-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700">Upload Progress</span>
                    <span id="uploadProgressPercent" class="text-sm text-gray-500">0%</span>
                </div>
                <div class="bg-gray-200 rounded-full h-2">
                    <div id="uploadProgressBar" class="bg-seait-orange h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <p id="uploadProgressText" class="text-sm text-gray-600 mt-2">Preparing upload...</p>
            </div>
            
            <div class="flex space-x-3">
                <button id="cancelUpload" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium">
                    Cancel
                </button>
                <button id="startUpload" class="flex-1 bg-seait-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-medium" disabled>
                    Upload All
                </button>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4 modal-backdrop">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 modal-content">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirm Deletion</h3>
                <p class="text-gray-600 text-sm" id="deleteMessage">Are you sure you want to delete the selected items?</p>
            </div>
            
            <div class="flex space-x-3">
                <button id="cancelDelete" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium">
                    Cancel
                </button>
                <button id="confirmDelete" class="flex-1 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4 modal-backdrop">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 modal-content">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Success!</h3>
                <p class="text-gray-600 text-sm" id="successMessage">Operation completed successfully.</p>
            </div>
            
            <div class="flex justify-center">
                <button id="closeSuccessModal" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg font-medium">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div id="errorModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4 modal-backdrop">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 modal-content">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-circle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Error</h3>
                <p class="text-gray-600 text-sm" id="errorMessage">An error occurred. Please try again.</p>
            </div>
            
            <div class="flex justify-center">
                <button id="closeErrorModal" class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-lg font-medium">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- Warning Modal -->
    <div id="warningModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4 modal-backdrop">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 modal-content">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Warning</h3>
                <p class="text-gray-600 text-sm" id="warningMessage">Please review the information below.</p>
            </div>
            
            <div class="flex justify-center">
                <button id="closeWarningModal" class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-2 rounded-lg font-medium">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- Info Modal -->
    <div id="infoModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4 modal-backdrop">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 modal-content">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-info-circle text-blue-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Information</h3>
                <p class="text-gray-600 text-sm" id="infoMessage">Here's some important information.</p>
            </div>
            
            <div class="flex justify-center">
                <button id="closeInfoModal" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-medium">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4 modal-backdrop">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 modal-content">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-question-circle text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirm Action</h3>
                <p class="text-gray-600 text-sm" id="confirmMessage">Are you sure you want to proceed?</p>
            </div>
            
            <div class="flex justify-center space-x-3">
                <button onclick="hideConfirmModal()"
                        class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
                <button id="confirmActionBtn"
                        class="px-6 py-3 bg-gradient-to-r from-yellow-500 to-yellow-600 text-white rounded-lg hover:from-yellow-600 hover:to-yellow-700 transition-all duration-200 font-semibold">
                    <i class="fas fa-check mr-2"></i>Confirm
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentDirectory = '/';
        let currentAccount = 'default';
        let selectedFiles = new Set();
        let isConnected = false;
        let filesToUpload = [];
        let uploadQueue = [];
        let currentUploadIndex = 0;
        
        // Dual pane variables
        let localDirectory = '';
        let localFiles = [];
        let selectedLocalFiles = new Set();
        let selectedFTPFiles = new Set();
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeEventListeners();
            setupModalEnhancements();
            
            // Set SMS account as default
            const accountSelector = document.getElementById('accountSelector');
            accountSelector.value = 'sms';
            currentAccount = 'sms';
            
            // Auto-connect to SMS FTP
            setTimeout(() => {
                connectToFTP();
            }, 1000);
        });
        
        function initializeEventListeners() {
            console.log('Initializing event listeners...');
            // Account selector
            const accountSelector = document.getElementById('accountSelector');
            if (accountSelector) {
                accountSelector.addEventListener('change', function() {
                    currentAccount = this.value;
                    isConnected = false;
                    updateConnectionStatus(false);
                    
                    // Show info for SMS account
                    if (currentAccount === 'sms') {
                        showInfoModal('SMS FTP Account selected. This account connects to the SMS subdomain directory.');
                    }
                });
            }
            
            // Connect button
            const connectBtn = document.getElementById('connectBtn');
            if (connectBtn) {
                connectBtn.addEventListener('click', connectToFTP);
            }
            
            // Action buttons
            const refreshBtn = document.getElementById('refreshBtn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', refreshDirectory);
            }
            
            const uploadBtn = document.getElementById('uploadBtn');
            if (uploadBtn) {
                uploadBtn.addEventListener('click', showUploadModal);
            }
            
            const uploadBtnPane = document.getElementById('uploadBtnPane');
            if (uploadBtnPane) {
                uploadBtnPane.addEventListener('click', showUploadModal);
            }
            
            const deleteBtn = document.getElementById('deleteBtn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', showDeleteModal);
            }
            
            const createFolderBtn = document.getElementById('createFolderBtn');
            if (createFolderBtn) {
                createFolderBtn.addEventListener('click', createNewFolder);
            }
            
            // Dual pane buttons
            const refreshLocalBtn = document.getElementById('refreshLocalBtn');
            if (refreshLocalBtn) {
                refreshLocalBtn.addEventListener('click', refreshLocalDirectory);
            }
            
            const selectLocalFolderBtn = document.getElementById('selectLocalFolderBtn');
            if (selectLocalFolderBtn) {
                selectLocalFolderBtn.addEventListener('click', selectLocalFolder);
            }
            
            const refreshFTPBtn = document.getElementById('refreshFTPBtn');
            if (refreshFTPBtn) {
                refreshFTPBtn.addEventListener('click', refreshDirectory);
            }
            
            const uploadSelectedBtn = document.getElementById('uploadSelectedBtn');
            if (uploadSelectedBtn) {
                uploadSelectedBtn.addEventListener('click', uploadSelectedFiles);
            }
            
            const downloadSelectedBtn = document.getElementById('downloadSelectedBtn');
            if (downloadSelectedBtn) {
                downloadSelectedBtn.addEventListener('click', downloadSelectedFiles);
            }
            
            const syncFolderBtn = document.getElementById('syncFolderBtn');
            if (syncFolderBtn) {
                syncFolderBtn.addEventListener('click', syncFolder);
            }
            
            // Select all checkboxes
            const selectAllLocal = document.getElementById('selectAllLocal');
            if (selectAllLocal) {
                selectAllLocal.addEventListener('change', toggleSelectAllLocal);
            }
            
            const selectAllFTP = document.getElementById('selectAllFTP');
            if (selectAllFTP) {
                selectAllFTP.addEventListener('change', toggleSelectAllFTP);
            }
            
            // Upload modal
            const cancelUpload = document.getElementById('cancelUpload');
            if (cancelUpload) {
                cancelUpload.addEventListener('click', hideUploadModal);
            }
            
            const startUpload = document.getElementById('startUpload');
            if (startUpload) {
                startUpload.addEventListener('click', uploadAllFiles);
            }
            
            const fileInput = document.getElementById('fileInput');
            if (fileInput) {
                fileInput.addEventListener('change', handleFileSelect);
            }
            
            const folderInput = document.getElementById('folderInput');
            if (folderInput) {
                folderInput.addEventListener('change', handleFolderSelect);
            }
            
            // Upload modal buttons
            const chooseFilesBtn = document.getElementById('chooseFilesBtn');
            if (chooseFilesBtn) {
                chooseFilesBtn.addEventListener('click', function() {
                    console.log('Choose files button clicked');
                    const fileInput = document.getElementById('fileInput');
                    if (fileInput) {
                        fileInput.click();
                    } else {
                        console.error('File input not found');
                    }
                });
            }
            
            const chooseFolderBtn = document.getElementById('chooseFolderBtn');
            if (chooseFolderBtn) {
                chooseFolderBtn.addEventListener('click', function() {
                    console.log('Choose folder button clicked');
                    const folderInput = document.getElementById('folderInput');
                    if (folderInput) {
                        folderInput.click();
                    } else {
                        console.error('Folder input not found');
                    }
                });
            }
            
            // Delete modal
            const cancelDelete = document.getElementById('cancelDelete');
            if (cancelDelete) {
                cancelDelete.addEventListener('click', hideDeleteModal);
            }
            
            const confirmDelete = document.getElementById('confirmDelete');
            if (confirmDelete) {
                confirmDelete.addEventListener('click', deleteSelectedFiles);
            }
            
            // Modal close buttons
            const closeSuccessModal = document.getElementById('closeSuccessModal');
            if (closeSuccessModal) {
                closeSuccessModal.addEventListener('click', hideSuccessModal);
            }
            
            const closeErrorModal = document.getElementById('closeErrorModal');
            if (closeErrorModal) {
                closeErrorModal.addEventListener('click', hideErrorModal);
            }
            
            const closeWarningModal = document.getElementById('closeWarningModal');
            if (closeWarningModal) {
                closeWarningModal.addEventListener('click', hideWarningModal);
            }
            
            const closeInfoModal = document.getElementById('closeInfoModal');
            if (closeInfoModal) {
                closeInfoModal.addEventListener('click', hideInfoModal);
            }
            
            // Select all checkbox (only if it exists)
            const selectAllElement = document.getElementById('selectAll');
            if (selectAllElement) {
                selectAllElement.addEventListener('change', toggleSelectAll);
            }
            
            // Drag and drop zones (only if they exist)
            const uploadZone = document.getElementById('uploadZone');
            const folderUploadZone = document.getElementById('folderUploadZone');
            const dragDropZone = document.getElementById('dragDropZone');
            const fileList = document.getElementById('fileList');
            
            // Modal drag and drop
            if (uploadZone) {
                uploadZone.addEventListener('dragover', handleDragOver);
                uploadZone.addEventListener('dragleave', handleDragLeave);
                uploadZone.addEventListener('drop', handleDrop);
            }
            
            if (folderUploadZone) {
                folderUploadZone.addEventListener('dragover', handleDragOver);
                folderUploadZone.addEventListener('dragleave', handleDragLeave);
                folderUploadZone.addEventListener('drop', handleFolderDrop);
            }
            
            // Main area drag and drop (only if elements exist)
            if (fileList) {
                fileList.addEventListener('dragover', handleMainDragOver);
                fileList.addEventListener('dragleave', handleMainDragLeave);
                fileList.addEventListener('drop', handleMainDrop);
                fileList.addEventListener('dragenter', showDragDropZone);
            }
            
            if (dragDropZone) {
                dragDropZone.addEventListener('dragleave', hideDragDropZone);
                dragDropZone.addEventListener('drop', handleMainDrop);
            }
            
            console.log('Event listeners initialized successfully');
        }
        
        async function connectToFTP() {
            const connectBtn = document.getElementById('connectBtn');
            const originalText = connectBtn.innerHTML;
            
            connectBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Connecting...';
            connectBtn.disabled = true;
            
            try {
                await browseDirectory('/');
                isConnected = true;
                updateConnectionStatus(true);
                connectBtn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Refresh';
                
                // Show success message for SMS account
                if (currentAccount === 'sms') {
                    showSuccessModal('Successfully connected to SMS FTP! You can now manage files for the SMS subdomain.');
                }
            } catch (error) {
                console.error('Connection failed:', error);
                updateConnectionStatus(false);
                connectBtn.innerHTML = originalText;
                
                // Show error message
                showErrorModal('Failed to connect to FTP: ' + error.message);
            }
            
            connectBtn.disabled = false;
        }
        
        function updateConnectionStatus(connected) {
            const statusIndicator = document.getElementById('connectionStatus');
            const statusText = document.getElementById('connectionText');
            
            if (connected) {
                statusIndicator.className = 'w-3 h-3 rounded-full bg-green-500';
                statusText.textContent = 'Connected';
                statusText.className = 'font-medium text-green-700';
            } else {
                statusIndicator.className = 'w-3 h-3 rounded-full bg-red-500';
                statusText.textContent = 'Disconnected';
                statusText.className = 'font-medium text-red-700';
            }
        }
        
        async function browseDirectory(directory) {
            showLoading(true);
            
            try {
                const response = await fetch('api/ftp-browse.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        directory: directory,
                        account: currentAccount
                    })
                });
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error);
                }
                
                currentDirectory = data.data.current_directory;
                updateBreadcrumb(currentDirectory);
                updateFileList(data.data.files);
                document.getElementById('ftpPath').textContent = currentDirectory;
                
            } catch (error) {
                console.error('Browse failed:', error);
                showError('Failed to browse directory: ' + error.message);
            }
            
            showLoading(false);
        }
        
        function updateBreadcrumb(path) {
            const breadcrumb = document.getElementById('breadcrumb');
            const parts = path.split('/').filter(part => part !== '');
            
            let html = '<span class="breadcrumb-item text-seait-orange font-medium" data-path="/">Root</span>';
            let currentPath = '';
            
            parts.forEach(part => {
                currentPath += '/' + part;
                html += ' <i class="fas fa-chevron-right text-gray-400 text-xs"></i> ';
                html += `<span class="breadcrumb-item text-seait-orange font-medium" data-path="${currentPath}">${part}</span>`;
            });
            
            breadcrumb.innerHTML = html;
            
            // Add click handlers to breadcrumb items
            breadcrumb.querySelectorAll('.breadcrumb-item').forEach(item => {
                item.addEventListener('click', function() {
                    const path = this.dataset.path;
                    browseDirectory(path);
                });
            });
        }
        
        function updateFileList(files) {
            const fileTableBody = document.getElementById('ftpFileTableBody');
            const fileList = document.getElementById('ftpFileList');
            const emptyState = document.getElementById('ftpEmptyState');
            const loadingIndicator = document.getElementById('ftpLoadingIndicator');
            
            // Hide loading indicator
            loadingIndicator.classList.add('hidden');
            
            if (files.length === 0) {
                fileList.classList.add('hidden');
                emptyState.classList.remove('hidden');
                return;
            }
            
            fileList.classList.remove('hidden');
            emptyState.classList.add('hidden');
            
            let html = '';
            files.forEach(file => {
                const icon = getFileIcon(file);
                const size = file.is_directory ? '-' : formatFileSize(file.size);
                
                html += `
                    <tr class="file-row ${file.is_directory ? 'bg-yellow-50 hover:bg-yellow-100' : 'hover:bg-gray-50'}" data-file="${file.name}" data-is-directory="${file.is_directory}">
                        <td>
                            <input type="checkbox" class="ftp-file-checkbox rounded" data-file="${file.name}">
                        </td>
                        <td>
                            <div class="flex items-center">
                                <div class="file-icon ${icon.class} mr-3">
                                    <i class="${icon.icon}"></i>
                                </div>
                                <div>
                                    <span class="font-medium ${file.is_directory ? 'text-yellow-800' : 'text-gray-900'}">${file.name}</span>
                                    ${file.is_directory ? '<div class="text-xs text-yellow-600">Directory</div>' : ''}
                                </div>
                            </div>
                        </td>
                        <td class="text-gray-600">${size}</td>
                        <td class="text-gray-600">${file.modified}</td>
                        <td class="text-center">
                            <div class="flex items-center justify-center space-x-2">
                                ${file.is_directory ? 
                                    '<button class="text-blue-600 hover:text-blue-800" onclick="openDirectory(\'' + file.name + '\')"><i class="fas fa-folder-open"></i></button>' :
                                    '<button class="text-green-600 hover:text-green-800" onclick="downloadFile(\'' + file.name + '\')"><i class="fas fa-download"></i></button>'
                                }
                                <button class="text-red-600 hover:text-red-800" onclick="deleteFile('${file.name}', ${file.is_directory})"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            fileTableBody.innerHTML = html;
            
            // Add event listeners to checkboxes
            document.querySelectorAll('.ftp-file-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedFTPFiles);
            });
            
            // Add double-click handlers to rows
            document.querySelectorAll('.file-row').forEach(row => {
                row.addEventListener('dblclick', function() {
                    const fileName = this.dataset.file;
                    const isDirectory = this.dataset.isDirectory === 'true';
                    
                    if (isDirectory) {
                        openDirectory(fileName);
                    } else {
                        downloadFile(fileName);
                    }
                });
            });
        }
        
        function getFileIcon(file) {
            if (file.is_directory) {
                return { class: 'file-directory', icon: 'fas fa-folder' };
            }
            
            const ext = file.type.toLowerCase();
            
            // Programming Languages
            if (ext === 'php') {
                return { class: 'file-php', icon: 'fab fa-php' };
            } else if (ext === 'js') {
                return { class: 'file-js', icon: 'fab fa-js-square' };
            } else if (ext === 'html' || ext === 'htm') {
                return { class: 'file-html', icon: 'fab fa-html5' };
            } else if (ext === 'css') {
                return { class: 'file-css', icon: 'fab fa-css3-alt' };
            } else if (ext === 'py') {
                return { class: 'file-python', icon: 'fab fa-python' };
            } else if (ext === 'java') {
                return { class: 'file-java', icon: 'fab fa-java' };
            } else if (ext === 'cpp' || ext === 'cc' || ext === 'cxx') {
                return { class: 'file-cpp', icon: 'fas fa-code' };
            } else if (ext === 'c') {
                return { class: 'file-c', icon: 'fas fa-code' };
            } else if (ext === 'sql') {
                return { class: 'file-sql', icon: 'fas fa-database' };
            } else if (ext === 'json') {
                return { class: 'file-json', icon: 'fas fa-brackets-curly' };
            } else if (ext === 'xml') {
                return { class: 'file-xml', icon: 'fas fa-file-code' };
            } else if (ext === 'ts') {
                return { class: 'file-typescript', icon: 'fab fa-js-square' };
            } else if (ext === 'jsx' || ext === 'tsx') {
                return { class: 'file-react', icon: 'fab fa-react' };
            } else if (ext === 'vue') {
                return { class: 'file-vue', icon: 'fab fa-vuejs' };
            } else if (ext === 'rb') {
                return { class: 'file-ruby', icon: 'fas fa-gem' };
            } else if (ext === 'go') {
                return { class: 'file-go', icon: 'fas fa-code' };
            } else if (ext === 'rs') {
                return { class: 'file-rust', icon: 'fas fa-code' };
            } else if (ext === 'swift') {
                return { class: 'file-swift', icon: 'fas fa-code' };
            } else if (ext === 'kt') {
                return { class: 'file-kotlin', icon: 'fas fa-code' };
            } else if (ext === 'scala') {
                return { class: 'file-scala', icon: 'fas fa-code' };
            } else if (ext === 'sh' || ext === 'bash') {
                return { class: 'file-shell', icon: 'fas fa-terminal' };
            } else if (ext === 'ps1') {
                return { class: 'file-powershell', icon: 'fas fa-terminal' };
            } else if (ext === 'bat' || ext === 'cmd') {
                return { class: 'file-batch', icon: 'fas fa-terminal' };
            }
            
            // Images
            if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico', 'tiff'].includes(ext)) {
                return { class: 'file-image', icon: 'fas fa-image' };
            }
            
            // Documents
            if (['pdf'].includes(ext)) {
                return { class: 'file-pdf', icon: 'fas fa-file-pdf' };
            } else if (['doc', 'docx'].includes(ext)) {
                return { class: 'file-word', icon: 'fas fa-file-word' };
            } else if (['xls', 'xlsx'].includes(ext)) {
                return { class: 'file-excel', icon: 'fas fa-file-excel' };
            } else if (['ppt', 'pptx'].includes(ext)) {
                return { class: 'file-powerpoint', icon: 'fas fa-file-powerpoint' };
            } else if (['txt', 'rtf', 'odt', 'md'].includes(ext)) {
                return { class: 'file-text', icon: 'fas fa-file-alt' };
            }
            
            // Archives
            if (['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'].includes(ext)) {
                return { class: 'file-archive', icon: 'fas fa-file-archive' };
            }
            
            // Media
            if (['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'].includes(ext)) {
                return { class: 'file-video', icon: 'fas fa-video' };
            } else if (['mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a'].includes(ext)) {
                return { class: 'file-audio', icon: 'fas fa-music' };
            }
            
            // Default
            return { class: 'file-default', icon: 'fas fa-file' };
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
        
        function openDirectory(dirName) {
            const newPath = currentDirectory === '/' ? '/' + dirName : currentDirectory + '/' + dirName;
            browseDirectory(newPath);
        }
        
        function downloadFile(fileName) {
            const filePath = currentDirectory === '/' ? '/' + fileName : currentDirectory + '/' + fileName;
            const url = `api/ftp-download.php?file=${encodeURIComponent(filePath)}&account=${encodeURIComponent(currentAccount)}`;
            window.open(url, '_blank');
        }
        
        function deleteFile(fileName, isDirectory) {
            selectedFiles.clear();
            selectedFiles.add(fileName);
            showDeleteModal();
        }
        
        function refreshDirectory() {
            browseDirectory(currentDirectory);
        }
        
        // Legacy functions - kept for compatibility
        function updateSelectedFiles() {
            // This function is now handled by updateSelectedFTPFiles
            updateSelectedFTPFiles();
        }
        
        function toggleSelectAll() {
            // This function is now handled by toggleSelectAllFTP
            toggleSelectAllFTP();
        }
        
        function showLoading(show) {
            const loadingIndicator = document.getElementById('ftpLoadingIndicator');
            const fileList = document.getElementById('ftpFileList');
            const emptyState = document.getElementById('ftpEmptyState');
            
            if (show) {
                loadingIndicator.classList.remove('hidden');
                fileList.classList.add('hidden');
                emptyState.classList.add('hidden');
            } else {
                loadingIndicator.classList.add('hidden');
            }
        }
        
        // Modal functions
        function showSuccessModal(message) {
            document.getElementById('successMessage').textContent = message;
            showModalWithAnimation('successModal');
        }
        
        function hideSuccessModal() {
            closeModalWithAnimation(document.getElementById('successModal'));
        }
        
        function showErrorModal(message) {
            document.getElementById('errorMessage').textContent = message;
            showModalWithAnimation('errorModal');
        }
        
        function hideErrorModal() {
            closeModalWithAnimation(document.getElementById('errorModal'));
        }
        
        function showWarningModal(message) {
            document.getElementById('warningMessage').textContent = message;
            showModalWithAnimation('warningModal');
        }
        
        function hideWarningModal() {
            closeModalWithAnimation(document.getElementById('warningModal'));
        }
        
        function showInfoModal(message) {
            document.getElementById('infoMessage').textContent = message;
            showModalWithAnimation('infoModal');
        }
        
        function hideInfoModal() {
            closeModalWithAnimation(document.getElementById('infoModal'));
        }
        
        function showConfirmModal(message, onConfirm) {
            document.getElementById('confirmMessage').textContent = message;
            showModalWithAnimation('confirmModal');
            
            const confirmBtn = document.getElementById('confirmActionBtn');
            confirmBtn.onclick = function() {
                hideConfirmModal();
                onConfirm();
            };
        }
        
        function hideConfirmModal() {
            closeModalWithAnimation(document.getElementById('confirmModal'));
        }
        
        // Legacy functions for compatibility
        function showError(message) {
            showErrorModal(message);
        }
        
        function showSuccess(message) {
            showSuccessModal(message);
        }
        
        // Enhanced modal functionality
        function setupModalEnhancements() {
            // Close modals when clicking outside
            const modals = ['successModal', 'errorModal', 'warningModal', 'infoModal', 'deleteModal', 'confirmModal'];
            
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            closeModalWithAnimation(modal);
                        }
                    });
                }
            });
            
            // Close modals with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    modals.forEach(modalId => {
                        const modal = document.getElementById(modalId);
                        if (modal && !modal.classList.contains('hidden')) {
                            closeModalWithAnimation(modal);
                        }
                    });
                }
            });
        }
        
        function closeModalWithAnimation(modal) {
            const content = modal.querySelector('.modal-content');
            if (content) {
                content.classList.add('fade-out');
                setTimeout(() => {
                    modal.classList.add('hidden');
                    content.classList.remove('fade-out');
                }, 200);
            } else {
                modal.classList.add('hidden');
            }
        }
        
        function showModalWithAnimation(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
                const content = modal.querySelector('.modal-content');
                if (content) {
                    content.classList.remove('fade-out');
                }
            }
        }
        
        // Upload functionality
        function showUploadModal() {
            document.getElementById('uploadModal').classList.remove('hidden');
        }
        
        function hideUploadModal() {
            document.getElementById('uploadModal').classList.add('hidden');
            document.getElementById('fileInput').value = '';
            document.getElementById('folderInput').value = '';
            filesToUpload = [];
            updateFilesList();
            updateUploadButton();
            document.getElementById('uploadProgress').classList.add('hidden');
        }
        
        function handleFileSelect() {
            console.log('handleFileSelect called');
            const fileInput = document.getElementById('fileInput');
            console.log('File input element:', fileInput);
            
            if (fileInput && fileInput.files) {
                const files = Array.from(fileInput.files);
                console.log('Files selected for upload:', files.length);
                console.log('File names:', files.map(f => f.name));
                addFilesToUpload(files);
            } else {
                console.error('File input not found or no files selected');
                showErrorModal('No files selected. Please try again.');
            }
        }
        
        function handleFolderSelect() {
            console.log('handleFolderSelect called');
            const folderInput = document.getElementById('folderInput');
            console.log('Folder input element:', folderInput);
            
            if (folderInput && folderInput.files) {
                const files = Array.from(folderInput.files);
                console.log('Files selected from folder:', files.length);
                console.log('Sample file paths:', files.slice(0, 3).map(f => f.webkitRelativePath || f.name));
                
                if (files.length > 0) {
                    // Analyze folder structure
                    const folderStructure = analyzeFolderStructure(files);
                    addFilesToUpload(files);
                    
                    // Show info about the folder structure
                    showInfoModal(`Folder selected with ${files.length} files. Directory structure will be preserved during upload.`);
                } else {
                    console.log('No files found in selected folder');
                    showInfoModal('No files found in the selected folder. Please try again.');
                }
            } else {
                console.error('Folder input not found or no files selected');
                showErrorModal('No folder selected. Please try again.');
            }
        }
        
        function analyzeFolderStructure(files) {
            const structure = {};
            
            files.forEach(file => {
                if (file.webkitRelativePath) {
                    const pathParts = file.webkitRelativePath.split('/');
                    let current = structure;
                    
                    for (let i = 0; i < pathParts.length - 1; i++) {
                        const part = pathParts[i];
                        if (!current[part]) {
                            current[part] = {};
                        }
                        current = current[part];
                    }
                }
            });
            
            return structure;
        }
        
        function updateFolderStructurePreview() {
            const structurePreview = document.getElementById('structurePreview');
            const structure = analyzeFolderStructure(filesToUpload);
            
            let html = '';
            
            function renderStructure(obj, indent = 0) {
                const spaces = '  '.repeat(indent);
                Object.keys(obj).forEach(key => {
                    html += `${spaces}📁 ${key}/\n`;
                    if (Object.keys(obj[key]).length > 0) {
                        renderStructure(obj[key], indent + 1);
                    }
                });
            }
            
            renderStructure(structure);
            
            if (html === '') {
                html = 'No folder structure detected';
            }
            
            structurePreview.innerHTML = html;
        }
        
        // Local directory navigation functions
        function groupFilesByDirectory(files) {
            const directories = {};
            const fileList = [];
            
            files.forEach(file => {
                if (file.webkitRelativePath) {
                    const pathParts = file.webkitRelativePath.split('/');
                    
                    // If we're in a specific directory, filter files accordingly
                    if (localDirectory && localDirectory !== '') {
                        if (pathParts[0] === localDirectory) {
                            // We're in the root of the selected directory
                            if (pathParts.length > 1) {
                                // This file is in a subdirectory
                                const subDir = pathParts[1];
                                if (!directories[subDir]) {
                                    directories[subDir] = { fileCount: 0 };
                                }
                                directories[subDir].fileCount++;
                            } else {
                                // This file is in the current directory
                                fileList.push(file);
                            }
                        }
                    } else {
                        // We're at the root level
                        if (pathParts.length > 1) {
                            // This file is in a subdirectory
                            const subDir = pathParts[0];
                            if (!directories[subDir]) {
                                directories[subDir] = { fileCount: 0 };
                            }
                            directories[subDir].fileCount++;
                        } else {
                            // This file is in the current directory
                            fileList.push(file);
                        }
                    }
                } else {
                    // File without relative path (single file upload)
                    fileList.push(file);
                }
            });
            
            return { directories, files: fileList };
        }
        
        function getParentDirectory(currentDir) {
            if (!currentDir || currentDir === '') return '';
            
            const pathParts = currentDir.split('/');
            if (pathParts.length <= 1) return '';
            
            return pathParts.slice(0, -1).join('/');
        }
        
        function navigateToLocalDirectory(directory) {
            if (directory === '') {
                // Navigate to root
                localDirectory = '';
            } else {
                // Navigate to specific directory
                localDirectory = directory;
            }
            
            // Update breadcrumb
            updateLocalBreadcrumb(localDirectory);
            
            // Re-render the file list with the new directory context
            updateLocalFileList();
            updateLocalFileCount();
        }
        
        function updateLocalFileCount() {
            const fileCountElement = document.getElementById('localFileCount');
            
            if (localFiles.length === 0) {
                fileCountElement.textContent = 'No files selected';
                return;
            }
            
            const fileGroups = groupFilesByDirectory(localFiles);
            const directoryCount = Object.keys(fileGroups.directories).length;
            const fileCount = fileGroups.files.length;
            
            let countText = '';
            if (directoryCount > 0 && fileCount > 0) {
                countText = `${directoryCount} directories, ${fileCount} files`;
            } else if (directoryCount > 0) {
                countText = `${directoryCount} directories`;
            } else if (fileCount > 0) {
                countText = `${fileCount} files`;
            } else {
                countText = 'No files in current directory';
            }
            
            fileCountElement.textContent = countText;
        }
        
        function updateLocalBreadcrumb(path) {
            const breadcrumb = document.getElementById('localBreadcrumb');
            
            if (!path || path === '') {
                breadcrumb.innerHTML = '<span class="local-breadcrumb-item text-blue-600 font-medium" data-path="">Root</span>';
            } else {
                const parts = path.split('/').filter(part => part !== '');
                
                let html = '<span class="local-breadcrumb-item text-blue-600 font-medium" data-path="">Root</span>';
                let currentPath = '';
                
                parts.forEach(part => {
                    currentPath += '/' + part;
                    html += ' <i class="fas fa-chevron-right text-gray-400 text-xs"></i> ';
                    html += `<span class="local-breadcrumb-item text-blue-600 font-medium" data-path="${currentPath}">${part}</span>`;
                });
                
                breadcrumb.innerHTML = html;
            }
            
            // Add click handlers to breadcrumb items
            breadcrumb.querySelectorAll('.local-breadcrumb-item').forEach(item => {
                item.addEventListener('click', function() {
                    const path = this.dataset.path;
                    navigateToLocalDirectory(path);
                });
            });
        }
        
        function addFilesToUpload(files) {
            console.log('addFilesToUpload called with', files.length, 'files');
            console.log('Current filesToUpload length:', filesToUpload.length);
            
            filesToUpload = filesToUpload.concat(files);
            console.log('New filesToUpload length:', filesToUpload.length);
            
            updateFilesList();
            updateUploadButton();
            
            // Update main upload button text
            const mainUploadBtn = document.getElementById('uploadBtn');
            if (mainUploadBtn && filesToUpload.length > 0) {
                const folderCount = filesToUpload.filter(f => f.webkitRelativePath).length;
                const fileCount = filesToUpload.length;
                
                console.log('Folder count:', folderCount, 'File count:', fileCount);
                
                if (folderCount > 0) {
                    mainUploadBtn.innerHTML = `<i class="fas fa-upload mr-2"></i>Upload ${fileCount} Files with Structure`;
                } else {
                    mainUploadBtn.innerHTML = `<i class="fas fa-upload mr-2"></i>Upload ${fileCount} Files`;
                }
            }
        }
        
        function updateFilesList() {
            console.log('updateFilesList called');
            console.log('filesToUpload length:', filesToUpload.length);
            
            const filesList = document.getElementById('filesList');
            const selectedFilesList = document.getElementById('selectedFilesList');
            const folderStructurePreview = document.getElementById('folderStructurePreview');
            
            if (filesToUpload.length === 0) {
                console.log('No files to upload, hiding lists');
                if (selectedFilesList) selectedFilesList.classList.add('hidden');
                if (folderStructurePreview) folderStructurePreview.classList.add('hidden');
                return;
            }
            
            selectedFilesList.classList.remove('hidden');
            
            // Check if any files have webkitRelativePath (folder upload)
            const hasFolderStructure = filesToUpload.some(file => file.webkitRelativePath);
            
            if (hasFolderStructure) {
                folderStructurePreview.classList.remove('hidden');
                updateFolderStructurePreview();
            } else {
                folderStructurePreview.classList.add('hidden');
            }
            
            let html = '';
            filesToUpload.forEach((file, index) => {
                const size = formatFileSize(file.size);
                const icon = getFileIconFromFile(file);
                const relativePath = file.webkitRelativePath ? file.webkitRelativePath : file.name;
                const isDirectory = icon.isDirectory;
                
                html += `
                    <div class="flex items-center justify-between p-2 bg-white rounded border ${isDirectory ? 'border-yellow-200 bg-yellow-50' : ''}">
                        <div class="flex items-center">
                            <div class="file-icon ${icon.class} mr-3">
                                <i class="${icon.icon}"></i>
                            </div>
                            <div>
                                <div class="font-medium text-sm ${isDirectory ? 'text-yellow-800' : 'text-gray-900'}">${file.name}</div>
                                <div class="text-xs text-gray-500">${isDirectory ? 'Directory' : size}</div>
                                ${file.webkitRelativePath ? `<div class="text-xs text-blue-600">📁 ${relativePath}</div>` : ''}
                            </div>
                        </div>
                        <button onclick="removeFile(${index})" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            });
            
            filesList.innerHTML = html;
        }
        
        function removeFile(index) {
            filesToUpload.splice(index, 1);
            updateFilesList();
            updateUploadButton();
            
            // Reset main upload button text if no files
            const mainUploadBtn = document.getElementById('uploadBtn');
            if (mainUploadBtn && filesToUpload.length === 0) {
                mainUploadBtn.innerHTML = `<i class="fas fa-upload mr-2"></i>Upload Files`;
            }
        }
        
        function updateUploadButton() {
            const startUpload = document.getElementById('startUpload');
            if (startUpload) {
                startUpload.disabled = filesToUpload.length === 0;
                
                if (filesToUpload.length > 0) {
                    const folderCount = filesToUpload.filter(f => f.webkitRelativePath).length;
                    const fileCount = filesToUpload.length;
                    
                    if (folderCount > 0) {
                        startUpload.innerHTML = `<i class="fas fa-upload mr-2"></i>Upload ${fileCount} Files with Structure`;
                    } else {
                        startUpload.innerHTML = `<i class="fas fa-upload mr-2"></i>Upload ${fileCount} Files`;
                    }
                } else {
                    startUpload.innerHTML = `<i class="fas fa-upload mr-2"></i>Upload All`;
                }
            }
        }
        
        function getFileIconFromFile(file) {
            // Check if it's a directory (has webkitRelativePath and no file extension)
            if (file.webkitRelativePath && !file.name.includes('.')) {
                return { class: 'file-directory', icon: 'fas fa-folder', isDirectory: true };
            }
            
            const ext = file.name.split('.').pop().toLowerCase();
            
            // Programming Languages
            if (ext === 'php') {
                return { class: 'file-php', icon: 'fab fa-php', isDirectory: false };
            } else if (ext === 'js') {
                return { class: 'file-js', icon: 'fab fa-js-square', isDirectory: false };
            } else if (ext === 'html' || ext === 'htm') {
                return { class: 'file-html', icon: 'fab fa-html5', isDirectory: false };
            } else if (ext === 'css') {
                return { class: 'file-css', icon: 'fab fa-css3-alt', isDirectory: false };
            } else if (ext === 'py') {
                return { class: 'file-python', icon: 'fab fa-python', isDirectory: false };
            } else if (ext === 'java') {
                return { class: 'file-java', icon: 'fab fa-java', isDirectory: false };
            } else if (ext === 'cpp' || ext === 'cc' || ext === 'cxx') {
                return { class: 'file-cpp', icon: 'fas fa-code', isDirectory: false };
            } else if (ext === 'c') {
                return { class: 'file-c', icon: 'fas fa-code', isDirectory: false };
            } else if (ext === 'sql') {
                return { class: 'file-sql', icon: 'fas fa-database', isDirectory: false };
            } else if (ext === 'json') {
                return { class: 'file-json', icon: 'fas fa-brackets-curly', isDirectory: false };
            } else if (ext === 'xml') {
                return { class: 'file-xml', icon: 'fas fa-file-code', isDirectory: false };
            } else if (ext === 'ts') {
                return { class: 'file-typescript', icon: 'fab fa-js-square', isDirectory: false };
            } else if (ext === 'jsx' || ext === 'tsx') {
                return { class: 'file-react', icon: 'fab fa-react', isDirectory: false };
            } else if (ext === 'vue') {
                return { class: 'file-vue', icon: 'fab fa-vuejs', isDirectory: false };
            } else if (ext === 'rb') {
                return { class: 'file-ruby', icon: 'fas fa-gem', isDirectory: false };
            } else if (ext === 'go') {
                return { class: 'file-go', icon: 'fas fa-code', isDirectory: false };
            } else if (ext === 'rs') {
                return { class: 'file-rust', icon: 'fas fa-code', isDirectory: false };
            } else if (ext === 'swift') {
                return { class: 'file-swift', icon: 'fas fa-code', isDirectory: false };
            } else if (ext === 'kt') {
                return { class: 'file-kotlin', icon: 'fas fa-code', isDirectory: false };
            } else if (ext === 'scala') {
                return { class: 'file-scala', icon: 'fas fa-code', isDirectory: false };
            } else if (ext === 'sh' || ext === 'bash') {
                return { class: 'file-shell', icon: 'fas fa-terminal', isDirectory: false };
            } else if (ext === 'ps1') {
                return { class: 'file-powershell', icon: 'fas fa-terminal', isDirectory: false };
            } else if (ext === 'bat' || ext === 'cmd') {
                return { class: 'file-batch', icon: 'fas fa-terminal', isDirectory: false };
            }
            
            // Images
            if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico', 'tiff'].includes(ext)) {
                return { class: 'file-image', icon: 'fas fa-image', isDirectory: false };
            }
            
            // Documents
            if (['pdf'].includes(ext)) {
                return { class: 'file-pdf', icon: 'fas fa-file-pdf', isDirectory: false };
            } else if (['doc', 'docx'].includes(ext)) {
                return { class: 'file-word', icon: 'fas fa-file-word', isDirectory: false };
            } else if (['xls', 'xlsx'].includes(ext)) {
                return { class: 'file-excel', icon: 'fas fa-file-excel', isDirectory: false };
            } else if (['ppt', 'pptx'].includes(ext)) {
                return { class: 'file-powerpoint', icon: 'fas fa-file-powerpoint', isDirectory: false };
            } else if (['txt', 'rtf', 'odt', 'md'].includes(ext)) {
                return { class: 'file-text', icon: 'fas fa-file-alt', isDirectory: false };
            }
            
            // Archives
            if (['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'].includes(ext)) {
                return { class: 'file-archive', icon: 'fas fa-file-archive', isDirectory: false };
            }
            
            // Media
            if (['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'].includes(ext)) {
                return { class: 'file-video', icon: 'fas fa-video', isDirectory: false };
            } else if (['mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a'].includes(ext)) {
                return { class: 'file-audio', icon: 'fas fa-music', isDirectory: false };
            }
            
            // Default
            return { class: 'file-default', icon: 'fas fa-file', isDirectory: false };
        }
        
        function handleDragOver(e) {
            e.preventDefault();
            e.currentTarget.classList.add('dragover');
        }
        
        function handleDragLeave(e) {
            e.preventDefault();
            e.currentTarget.classList.remove('dragover');
        }
        
        function handleDrop(e) {
            e.preventDefault();
            e.currentTarget.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                addFilesToUpload(Array.from(files));
            }
        }
        
        function handleFolderDrop(e) {
            e.preventDefault();
            e.currentTarget.classList.remove('dragover');
            
            const items = e.dataTransfer.items;
            if (items) {
                const files = [];
                for (let i = 0; i < items.length; i++) {
                    const item = items[i];
                    if (item.kind === 'file') {
                        const entry = item.webkitGetAsEntry();
                        if (entry && entry.isDirectory) {
                            readDirectory(entry, files);
                        } else if (entry && entry.isFile) {
                            entry.file(file => files.push(file));
                        }
                    }
                }
                setTimeout(() => {
                    addFilesToUpload(files);
                }, 100);
            }
        }
        
        function readDirectory(dirEntry, files) {
            const dirReader = dirEntry.createReader();
            dirReader.readEntries(entries => {
                entries.forEach(entry => {
                    if (entry.isFile) {
                        entry.file(file => files.push(file));
                    } else if (entry.isDirectory) {
                        readDirectory(entry, files);
                    }
                });
            });
        }
        
        function handleMainDragOver(e) {
            e.preventDefault();
            e.currentTarget.style.backgroundColor = '#f0f9ff';
        }
        
        function handleMainDragLeave(e) {
            e.preventDefault();
            e.currentTarget.style.backgroundColor = '';
        }
        
        function handleMainDrop(e) {
            e.preventDefault();
            e.currentTarget.style.backgroundColor = '';
            hideDragDropZone();
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                addFilesToUpload(Array.from(files));
                showUploadModal();
            }
        }
        
        function showDragDropZone() {
            const dragDropZone = document.getElementById('dragDropZone');
            if (dragDropZone) {
                dragDropZone.classList.remove('hidden');
            }
        }
        
        function hideDragDropZone() {
            const dragDropZone = document.getElementById('dragDropZone');
            if (dragDropZone) {
                dragDropZone.classList.add('hidden');
            }
        }
        
        function createNewFolder() {
            const folderName = prompt('Enter folder name:');
            if (folderName && folderName.trim()) {
                // This would typically make an API call to create the folder on FTP
                showInfoModal(`Folder creation feature will be implemented. Requested folder: ${folderName}`);
            }
        }
        
        // Test function to verify file selection
        function testFileSelection() {
            console.log('Testing file selection...');
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.multiple = true;
            
            fileInput.addEventListener('change', function(e) {
                console.log('Test file selection successful!');
                console.log('Files selected:', e.target.files.length);
                if (e.target.files.length > 0) {
                    console.log('First file name:', e.target.files[0].name);
                    showSuccessModal(`Test successful! Selected ${e.target.files.length} files.`);
                }
            });
            
            fileInput.click();
        }
        
        // Local folder functions
        function selectLocalFolder() {
            console.log('selectLocalFolder called');
            
            const input = document.createElement('input');
            input.type = 'file';
            input.webkitdirectory = true;
            input.multiple = true;
            input.accept = '*/*'; // Accept all file types
            
            input.addEventListener('change', function(e) {
                console.log('File input change event triggered');
                console.log('Files selected:', e.target.files.length);
                
                if (e.target.files.length > 0) {
                    const folderPath = e.target.files[0].webkitRelativePath.split('/')[0];
                    console.log('Selected folder:', folderPath);
                    
                    localDirectory = folderPath;
                    loadLocalFiles(e.target.files);
                    
                    // Update breadcrumb to show the selected folder
                    updateLocalBreadcrumb(folderPath);
                    
                    // Show success message
                    showSuccessModal(`Local folder selected: ${folderPath} (${e.target.files.length} files found)`);
                } else {
                    console.log('No files selected');
                    // Show info message if no folder selected
                    showInfoModal('No folder selected. Please try again.');
                }
            });
            
            input.addEventListener('cancel', function() {
                console.log('File selection cancelled');
                // Show info message if user cancels
                showInfoModal('Folder selection cancelled.');
            });
            
            // Remove the info modal that was interfering with file selection
            // showInfoModal('Please select a local folder to browse files.');
            
            console.log('Triggering file input click');
            input.click();
        }
        
        // Enhanced local directory browsing
        function browseLocalDirectory(directory) {
            // For now, we'll use the file input approach since we can't directly browse local directories
            // due to browser security restrictions. In a real implementation, you might use a server-side
            // directory listing API or Electron-like environment.
            
            const input = document.createElement('input');
            input.type = 'file';
            input.webkitdirectory = true;
            input.multiple = true;
            
            input.addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    const files = Array.from(e.target.files);
                    
                    // Filter files based on the selected directory
                    const filteredFiles = files.filter(file => {
                        const pathParts = file.webkitRelativePath.split('/');
                        return pathParts[0] === directory || file.webkitRelativePath.startsWith(directory + '/');
                    });
                    
                    if (filteredFiles.length > 0) {
                        localDirectory = directory;
                        document.getElementById('localPath').textContent = directory;
                        loadLocalFiles(filteredFiles);
                    } else {
                        showInfoModal(`No files found in directory: ${directory}`);
                    }
                }
            });
            
            input.click();
        }
        
        function loadLocalFiles(files) {
            try {
                localFiles = Array.from(files);
                
                // Initialize breadcrumb if this is the first load
                if (!localDirectory) {
                    updateLocalBreadcrumb('');
                }
                
                updateLocalFileList();
                showLocalFileList();
                updateLocalFileCount();
                
                // Update transfer buttons
                updateTransferButtons();
                
            } catch (error) {
                console.error('Error loading local files:', error);
                showErrorModal('Error loading local files: ' + error.message);
            }
        }
        
        function updateLocalFileList() {
            const fileTableBody = document.getElementById('localFileTableBody');
            const localFileList = document.getElementById('localFileList');
            const localEmptyState = document.getElementById('localEmptyState');
            
            if (localFiles.length === 0) {
                localFileList.classList.add('hidden');
                localEmptyState.classList.remove('hidden');
                return;
            }
            
            // Check if we have a valid directory structure
            const hasValidStructure = localFiles.some(file => file.webkitRelativePath);
            if (!hasValidStructure && localFiles.length > 0) {
                // Single files without directory structure
                localFileList.classList.remove('hidden');
                localEmptyState.classList.add('hidden');
                
                let html = '';
                localFiles.forEach((file, index) => {
                    const icon = getFileIconFromFile(file);
                    const size = formatFileSize(file.size);
                    const modified = new Date(file.lastModified).toLocaleDateString();
                    
                    html += `
                        <tr class="file-row" data-file="${file.name}" data-index="${index}">
                            <td>
                                <input type="checkbox" class="local-file-checkbox rounded" data-index="${index}">
                            </td>
                            <td>
                                <div class="flex items-center">
                                    <div class="file-icon ${icon.class} mr-2">
                                        <i class="${icon.icon}"></i>
                                    </div>
                                    <span class="font-medium">${file.name}</span>
                                </div>
                            </td>
                            <td class="text-gray-600">${size}</td>
                            <td class="text-gray-600">${modified}</td>
                            <td class="text-center">
                                <button class="text-blue-600 hover:text-blue-800" onclick="downloadLocalFile(${index})">
                                    <i class="fas fa-download"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                fileTableBody.innerHTML = html;
                
                // Add event listeners to checkboxes
                document.querySelectorAll('.local-file-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', updateSelectedLocalFiles);
                });
                
                return;
            }
            
            localFileList.classList.remove('hidden');
            localEmptyState.classList.add('hidden');
            
            // Group files by directory structure
            const fileGroups = groupFilesByDirectory(localFiles);
            
            let html = '';
            
            // Add parent directory navigation if we're in a subdirectory
            if (localDirectory && localDirectory !== '') {
                const parentDir = getParentDirectory(localDirectory);
                html += `
                    <tr class="file-row directory-row bg-blue-50 hover:bg-blue-100" data-directory="${parentDir}" data-is-parent="true">
                        <td>
                            <input type="checkbox" class="local-file-checkbox rounded" data-type="parent" data-directory="${parentDir}" title="Select all files in parent directory">
                        </td>
                        <td>
                            <div class="flex items-center">
                                <div class="file-icon file-directory mr-3">
                                    <i class="fas fa-level-up-alt"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-blue-700">.. (Parent Directory)</span>
                                    <div class="text-xs text-blue-600">Navigate up - Click checkbox to select all files</div>
                                </div>
                            </div>
                        </td>
                        <td class="text-gray-600">-</td>
                        <td class="text-gray-600">-</td>
                        <td class="text-center">
                            <button class="text-blue-600 hover:text-blue-800" onclick="navigateToLocalDirectory('${parentDir}')" title="Go to parent directory">
                                <i class="fas fa-folder-open"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }
            
            // Add directories first
            Object.keys(fileGroups.directories).forEach(dirName => {
                const dirInfo = fileGroups.directories[dirName];
                html += `
                    <tr class="file-row directory-row bg-yellow-50 hover:bg-yellow-100" data-directory="${dirName}">
                        <td>
                            <input type="checkbox" class="local-file-checkbox rounded" data-type="directory" data-directory="${dirName}" title="Select all files in ${dirName}">
                        </td>
                        <td>
                            <div class="flex items-center">
                                <div class="file-icon file-directory mr-3">
                                    <i class="fas fa-folder"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-yellow-800">${dirName}</span>
                                    <div class="text-xs text-yellow-600">Directory (${dirInfo.fileCount} files) - Click checkbox to select all files</div>
                                </div>
                            </div>
                        </td>
                        <td class="text-gray-600">-</td>
                        <td class="text-gray-600">-</td>
                        <td class="text-center">
                            <button class="text-blue-600 hover:text-blue-800" onclick="navigateToLocalDirectory('${dirName}')" title="Open directory">
                                <i class="fas fa-folder-open"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            // Add files
            fileGroups.files.forEach((file, index) => {
                const icon = getFileIconFromFile(file);
                const size = formatFileSize(file.size);
                const modified = new Date(file.lastModified).toLocaleDateString();
                const relativePath = file.webkitRelativePath ? file.webkitRelativePath.split('/').slice(1).join('/') : file.name;
                const isDirectory = icon.isDirectory;
                
                html += `
                    <tr class="file-row ${isDirectory ? 'bg-yellow-50 hover:bg-yellow-100' : 'hover:bg-gray-50'}" data-file="${file.name}" data-index="${index}" data-relative-path="${relativePath}">
                        <td>
                            <input type="checkbox" class="local-file-checkbox rounded" data-index="${index}">
                        </td>
                        <td>
                            <div class="flex items-center">
                                <div class="file-icon ${icon.class} mr-3">
                                    <i class="${icon.icon}"></i>
                                </div>
                                <div>
                                    <span class="font-medium ${isDirectory ? 'text-yellow-800' : 'text-gray-900'}">${file.name}</span>
                                    ${file.webkitRelativePath ? `<div class="text-xs text-blue-600">📁 ${relativePath}</div>` : ''}
                                </div>
                            </div>
                        </td>
                        <td class="text-gray-600">${isDirectory ? '-' : size}</td>
                        <td class="text-gray-600">${modified}</td>
                        <td class="text-center">
                            <button class="text-blue-600 hover:text-blue-800" onclick="downloadLocalFile(${index})">
                                <i class="fas fa-download"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            fileTableBody.innerHTML = html;
            
            // Add event listeners to checkboxes
            document.querySelectorAll('.local-file-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedLocalFiles);
            });
            
            // Add double-click handlers for directory navigation
            document.querySelectorAll('.directory-row').forEach(row => {
                row.addEventListener('dblclick', function() {
                    const directory = this.dataset.directory;
                    if (directory) {
                        navigateToLocalDirectory(directory);
                    }
                });
            });
            
            // Update file count display
            updateLocalFileCount();
        }
        
        function showLocalFileList() {
            document.getElementById('localLoadingIndicator').classList.add('hidden');
            document.getElementById('localFileList').classList.remove('hidden');
        }
        
        function refreshLocalDirectory() {
            if (localDirectory) {
                // Re-read the local directory
                selectLocalFolder();
            } else {
                showInfoModal('No local folder selected. Please select a folder first.');
            }
        }
        
        function downloadLocalFile(index) {
            const file = localFiles[index];
            if (file) {
                // Create a download link for the local file
                const url = URL.createObjectURL(file);
                const a = document.createElement('a');
                a.href = url;
                a.download = file.name;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                showSuccessModal(`Downloaded: ${file.name}`);
            }
        }
        
        function updateSelectedLocalFiles() {
            selectedLocalFiles.clear();
            document.querySelectorAll('.local-file-checkbox:checked').forEach(checkbox => {
                const index = parseInt(checkbox.dataset.index);
                const type = checkbox.dataset.type;
                const directory = checkbox.dataset.directory;
                
                if (type === 'directory' || type === 'parent') {
                    // For directories, we need to select all files in that directory
                    if (directory) {
                        localFiles.forEach((file, fileIndex) => {
                            if (file.webkitRelativePath) {
                                const pathParts = file.webkitRelativePath.split('/');
                                if (type === 'parent') {
                                    // For parent directory, select files from parent level
                                    if (pathParts[0] === directory) {
                                        selectedLocalFiles.add(fileIndex);
                                    }
                                } else {
                                    // For regular directory, select files in that specific directory
                                    if (pathParts[0] === localDirectory && pathParts[1] === directory) {
                                        selectedLocalFiles.add(fileIndex);
                                    }
                                }
                            }
                        });
                    }
                } else {
                    // For regular files, just add the index
                    selectedLocalFiles.add(index);
                }
            });
            updateTransferButtons();
        }
        
        function toggleSelectAllLocal() {
            const selectAll = document.getElementById('selectAllLocal');
            const checkboxes = document.querySelectorAll('.local-file-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelectedLocalFiles();
        }
        
        function toggleSelectAllFTP() {
            const selectAll = document.getElementById('selectAllFTP');
            const checkboxes = document.querySelectorAll('.ftp-file-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelectedFTPFiles();
        }
        
        function updateSelectedFTPFiles() {
            selectedFTPFiles.clear();
            document.querySelectorAll('.ftp-file-checkbox:checked').forEach(checkbox => {
                const fileName = checkbox.dataset.file;
                selectedFTPFiles.add(fileName);
            });
            updateTransferButtons();
        }
        
        function updateTransferButtons() {
            const uploadBtn = document.getElementById('uploadSelectedBtn');
            const downloadBtn = document.getElementById('downloadSelectedBtn');
            const syncBtn = document.getElementById('syncFolderBtn');
            
            uploadBtn.disabled = selectedLocalFiles.size === 0;
            downloadBtn.disabled = selectedFTPFiles.size === 0;
            syncBtn.disabled = !localDirectory || !isConnected;
            
            // Update button text to show selection count
            if (uploadBtn) {
                if (selectedLocalFiles.size > 0) {
                    uploadBtn.innerHTML = `<i class="fas fa-arrow-right mr-2"></i>Upload Selected (${selectedLocalFiles.size})`;
                } else {
                    uploadBtn.innerHTML = `<i class="fas fa-arrow-right mr-2"></i>Upload Selected`;
                }
            }
            
            if (downloadBtn) {
                if (selectedFTPFiles.size > 0) {
                    downloadBtn.innerHTML = `<i class="fas fa-arrow-left mr-2"></i>Download Selected (${selectedFTPFiles.size})`;
                } else {
                    downloadBtn.innerHTML = `<i class="fas fa-arrow-left mr-2"></i>Download Selected`;
                }
            }
        }
        
        function uploadSelectedFiles() {
            if (selectedLocalFiles.size === 0) {
                showWarningModal('No files selected for upload. Please select files from the local folder.');
                return;
            }
            
            if (!localDirectory) {
                showWarningModal('No local folder selected. Please select a local folder first.');
                return;
            }
            
            const filesToUpload = Array.from(selectedLocalFiles).map(index => localFiles[index]);
            uploadFilesToFTP(filesToUpload);
        }
        
        function downloadSelectedFiles() {
            if (selectedFTPFiles.size === 0) return;
            
            selectedFTPFiles.forEach(fileName => {
                downloadFile(fileName);
            });
        }
        
        function syncFolder() {
            if (!localDirectory) {
                showWarningModal('No local folder selected. Please select a local folder first.');
                return;
            }
            
            if (!isConnected) {
                showWarningModal('Not connected to FTP. Please connect to FTP first.');
                return;
            }
            
            if (localFiles.length === 0) {
                showWarningModal('No files in the selected folder to sync.');
                return;
            }
            
            // Show confirmation before syncing
            showConfirmModal(`Sync entire folder "${localDirectory}" (${localFiles.length} files)?`, function() {
                const filesToUpload = localFiles;
                uploadFilesToFTP(filesToUpload);
            });
        }
        
        function uploadFilesToFTP(files) {
            if (files.length === 0) return;
            
            // Show upload progress
            showUploadProgress();
            
            let successCount = 0;
            let errorCount = 0;
            
            files.forEach((file, index) => {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('directory', currentDirectory);
                formData.append('account', currentAccount);
                
                // Add relative path for folder structure preservation
                if (file.webkitRelativePath) {
                    formData.append('relative_path', file.webkitRelativePath);
                }
                
                fetch('api/ftp-upload.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        successCount++;
                    } else {
                        errorCount++;
                    }
                    
                    // Update progress
                    const progress = Math.round(((index + 1) / files.length) * 100);
                    const progressText = file.webkitRelativePath ? 
                        `Uploading ${file.webkitRelativePath} (${index + 1}/${files.length})` :
                        `Uploading ${file.name} (${index + 1}/${files.length})`;
                    updateTransferProgress(progress, progressText);
                    
                    if (index === files.length - 1) {
                        // All uploads complete
                        setTimeout(() => {
                            hideUploadProgress();
                            if (successCount > 0) {
                                const folderCount = files.filter(f => f.webkitRelativePath).length;
                                const message = folderCount > 0 ? 
                                    `${successCount} files uploaded successfully with directory structure preserved` :
                                    `${successCount} files uploaded successfully`;
                                showSuccess(message);
                                refreshDirectory();
                            }
                        }, 1000);
                    }
                })
                .catch(error => {
                    errorCount++;
                    console.error('Upload failed:', error);
                });
            });
        }
        
        function showUploadProgress() {
            document.getElementById('transferProgress').classList.remove('hidden');
        }
        
        function hideUploadProgress() {
            document.getElementById('transferProgress').classList.add('hidden');
        }
        
        function updateTransferProgress(percent, text) {
            const progressBar = document.getElementById('transferProgressBar');
            const progressText = document.getElementById('transferProgressText');
            const progressPercent = document.getElementById('transferProgressPercent');
            
            progressBar.style.width = percent + '%';
            progressText.textContent = text;
            progressPercent.textContent = percent + '%';
        }
        
        async function uploadAllFiles() {
            if (filesToUpload.length === 0) return;
            
            document.getElementById('uploadProgress').classList.remove('hidden');
            document.getElementById('startUpload').disabled = true;
            
            const totalFiles = filesToUpload.length;
            let successCount = 0;
            let errorCount = 0;
            
            for (let i = 0; i < filesToUpload.length; i++) {
                const file = filesToUpload[i];
                currentUploadIndex = i;
                
                // Update progress
                const progress = Math.round(((i + 1) / totalFiles) * 100);
                const progressText = file.webkitRelativePath ? 
                    `Uploading ${file.webkitRelativePath} (${i + 1}/${totalFiles})` :
                    `Uploading ${file.name} (${i + 1}/${totalFiles})`;
                updateUploadProgress(progress, progressText);
                
                try {
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('directory', currentDirectory);
                    formData.append('account', currentAccount);
                    
                    // Add relative path for folder structure preservation
                    if (file.webkitRelativePath) {
                        formData.append('relative_path', file.webkitRelativePath);
                    }
                    
                    const response = await fetch('api/ftp-upload.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        successCount++;
                    } else {
                        errorCount++;
                        console.error(`Failed to upload ${file.name}:`, data.error);
                    }
                    
                } catch (error) {
                    errorCount++;
                    console.error(`Error uploading ${file.name}:`, error);
                }
            }
            
            // Final progress update
            updateUploadProgress(100, `Upload completed. ${successCount} successful, ${errorCount} failed.`);
            
            setTimeout(() => {
                if (successCount > 0) {
                    const folderCount = filesToUpload.filter(f => f.webkitRelativePath).length;
                    const message = folderCount > 0 ? 
                        `${successCount} files uploaded successfully with directory structure preserved` :
                        `${successCount} files uploaded successfully`;
                    showSuccess(message);
                    hideUploadModal();
                    refreshDirectory();
                } else {
                    showError('No files were uploaded successfully');
                }
                document.getElementById('startUpload').disabled = false;
            }, 2000);
        }
        
        function updateUploadProgress(percent, text) {
            const progressBar = document.getElementById('uploadProgressBar');
            const progressText = document.getElementById('uploadProgressText');
            const progressPercent = document.getElementById('uploadProgressPercent');
            
            if (progressBar) progressBar.style.width = percent + '%';
            if (progressText) progressText.textContent = text;
            if (progressPercent) progressPercent.textContent = percent + '%';
        }
        
        // Delete functionality
        function showDeleteModal() {
            const deleteMessage = document.getElementById('deleteMessage');
            const count = selectedFiles.size;
            
            if (count === 1) {
                deleteMessage.textContent = `Are you sure you want to delete "${Array.from(selectedFiles)[0]}"?`;
            } else {
                deleteMessage.textContent = `Are you sure you want to delete ${count} selected items?`;
            }
            
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        async function deleteSelectedFiles() {
            const confirmBtn = document.getElementById('confirmDelete');
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';
            confirmBtn.disabled = true;
            
            try {
                for (const fileName of selectedFiles) {
                    const filePath = currentDirectory === '/' ? '/' + fileName : currentDirectory + '/' + fileName;
                    const isDirectory = document.querySelector(`[data-file="${fileName}"]`).dataset.isDirectory === 'true';
                    
                    const response = await fetch('api/ftp-delete.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            file: filePath,
                            account: currentAccount,
                            is_directory: isDirectory
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (!data.success) {
                        throw new Error(data.error);
                    }
                }
                
                showSuccess('Files deleted successfully');
                hideDeleteModal();
                refreshDirectory();
                selectedFiles.clear();
                
            } catch (error) {
                console.error('Delete failed:', error);
                showError('Delete failed: ' + error.message);
            }
            
            confirmBtn.innerHTML = 'Delete';
            confirmBtn.disabled = false;
        }
    </script>
</body>
</html>
