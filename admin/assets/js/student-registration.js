/**
 * Student Registration JavaScript
 * Handles all frontend interactions for student registration module
 */

// Global variables
let currentStudentId = null;

/**
 * Modal Management Functions
 */

function openManualRegistration() {
    document.getElementById('manualRegistrationModal').classList.remove('hidden');
    document.getElementById('studentRegistrationForm').reset();
    document.getElementById('student_id').focus();
}

function closeManualRegistration() {
    document.getElementById('manualRegistrationModal').classList.add('hidden');
    document.getElementById('studentRegistrationForm').reset();
}

function openExcelImport() {
    document.getElementById('excelImportModal').classList.remove('hidden');
    document.getElementById('excelImportForm').reset();
}

function closeExcelImport() {
    document.getElementById('excelImportModal').classList.add('hidden');
    document.getElementById('excelImportForm').reset();
}

function openEditStudent() {
    const modal = document.getElementById('editStudentModal');
    modal.classList.remove('hidden');
}

function closeEditStudent() {
    const modal = document.getElementById('editStudentModal');
    modal.classList.add('hidden');
    document.getElementById('editStudentForm').reset();
    currentStudentId = null;
}

function openDeleteStudent() {
    const modal = document.getElementById('deleteStudentModal');
    modal.classList.remove('hidden');
}

function closeDeleteStudent() {
    const modal = document.getElementById('deleteStudentModal');
    modal.classList.add('hidden');
    currentStudentId = null;
}

/**
 * Form Validation Functions
 */

function validateStudentForm() {
    const studentId = document.getElementById('student_id').value.trim();
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    const email = document.getElementById('email').value.trim();
    
    // Clear previous error messages
    clearFormErrors();
    
    let isValid = true;
    
    // Validate Student ID
    if (!studentId) {
        showFieldError('student_id', 'Student ID is required');
        isValid = false;
    } else if (studentId.length < 3) {
        showFieldError('student_id', 'Student ID must be at least 3 characters');
        isValid = false;
    }
    
    // Validate First Name
    if (!firstName) {
        showFieldError('first_name', 'First name is required');
        isValid = false;
    } else if (firstName.length < 2) {
        showFieldError('first_name', 'First name must be at least 2 characters');
        isValid = false;
    }
    
    // Validate Last Name
    if (!lastName) {
        showFieldError('last_name', 'Last name is required');
        isValid = false;
    } else if (lastName.length < 2) {
        showFieldError('last_name', 'Last name must be at least 2 characters');
        isValid = false;
    }
    
    // Validate Email
    if (!email) {
        showFieldError('email', 'Email is required');
        isValid = false;
    } else if (!isValidEmail(email)) {
        showFieldError('email', 'Please enter a valid email address');
        isValid = false;
    }
    
    return isValid;
}

function validateExcelFile() {
    const fileInput = document.getElementById('excel_file');
    const file = fileInput.files[0];
    
    clearFormErrors();
    
    if (!file) {
        showFieldError('excel_file', 'Please select an Excel file');
        return false;
    }
    
    const allowedExtensions = ['.xlsx', '.xls'];
    const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
    
    if (!allowedExtensions.includes(fileExtension)) {
        showFieldError('excel_file', 'Please select a valid Excel file (.xlsx or .xls)');
        return false;
    }
    
    const maxSize = 5 * 1024 * 1024; // 5MB
    if (file.size > maxSize) {
        showFieldError('excel_file', 'File size must be less than 5MB');
        return false;
    }
    
    return true;
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorDiv = document.createElement('div');
    errorDiv.className = 'text-red-600 text-sm mt-1';
    errorDiv.id = fieldId + '_error';
    errorDiv.textContent = message;
    
    field.classList.add('border-red-500');
    field.parentNode.appendChild(errorDiv);
}

function clearFormErrors() {
    // Remove all error messages
    const errorElements = document.querySelectorAll('[id$="_error"]');
    errorElements.forEach(element => element.remove());
    
    // Remove red border from fields
    const fields = document.querySelectorAll('.border-red-500');
    fields.forEach(field => field.classList.remove('border-red-500'));
}

/**
 * AJAX Functions
 */

function submitStudentRegistration(formData) {
    showLoading();
    
    fetch('students.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        showAlert(data.message, data.success);
        
        if (data.success) {
            closeManualRegistration();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('An error occurred while registering the student.', false);
        console.error('Error:', error);
    });
}

function submitExcelImport(formData) {
    showLoading();
    
    fetch('students.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        showAlert(data.message, data.success);
        
        if (data.success) {
            closeExcelImport();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('An error occurred while importing students.', false);
        console.error('Error:', error);
    });
}

/**
 * Student Management Functions
 */

function viewStudent(studentId) {
    // Redirect to student detail page or open modal
    window.open(`student-detail.php?id=${studentId}`, '_blank');
}

function editStudent(studentId) {
    currentStudentId = studentId;
    
    // Fetch student data and populate the edit form
    showLoading();
    
    const formData = new FormData();
    formData.append('action', 'get_student');
    formData.append('student_id', studentId);
    
    fetch('students.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            const student = data.student;
            
            // Populate the edit form
            document.getElementById('edit_student_id').value = student.id;
            document.getElementById('edit_student_number').value = student.student_id;
            document.getElementById('edit_first_name').value = student.first_name;
            document.getElementById('edit_middle_name').value = student.middle_name || '';
            document.getElementById('edit_last_name').value = student.last_name;
            document.getElementById('edit_email').value = student.email;
            document.getElementById('edit_status').value = student.status;
            
            // Open the edit modal with animation
            openEditStudent();
        } else {
            showAlert(data.message, false);
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('An error occurred while fetching student data.', false);
        console.error('Error:', error);
    });
}

function deleteStudent(studentId) {
    currentStudentId = studentId;
    openDeleteStudent();
}

function confirmDeleteStudent() {
    if (!currentStudentId) return;
    
    showLoading();
    closeDeleteStudent();
    
    const formData = new FormData();
    formData.append('action', 'delete_student');
    formData.append('student_id', currentStudentId);
    
    fetch('students.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        showAlert(data.message, data.success);
        
        if (data.success) {
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('An error occurred while deleting the student.', false);
        console.error('Error:', error);
    });
}

function resetStudentPassword(studentId) {
    if (confirm('Are you sure you want to reset this student\'s password to the default? (Seait123)')) {
        showLoading();
        
        const formData = new FormData();
        formData.append('action', 'reset_password');
        formData.append('student_id', studentId);
        
        fetch('students.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            showAlert(data.message, data.success);
        })
        .catch(error => {
            hideLoading();
            showAlert('An error occurred while resetting the password.', false);
            console.error('Error:', error);
        });
    }
}

/**
 * Utility Functions
 */

function showLoading() {
    document.getElementById('loadingOverlay').classList.remove('hidden');
}

function hideLoading() {
    document.getElementById('loadingOverlay').classList.add('hidden');
}

function showAlert(message, isSuccess) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert-message');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert-message fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-md ${
        isSuccess ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'
    }`;
    
    alertDiv.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-auto text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

function downloadExcelTemplate() {
    // Create a simple Excel template with headers
    const headers = ['Student ID', 'First Name', 'Middle Name', 'Last Name', 'Email'];
    const csvContent = headers.join(',') + '\n';
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'student_import_template.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

/**
 * Search and Filter Functions
 */

function searchStudents() {
    const searchTerm = document.getElementById('searchInput').value.trim();
    const statusFilter = document.getElementById('statusFilter').value;
    
    if (searchTerm.length === 0 && statusFilter === '') {
        // Show all students
        window.location.reload();
        return;
    }
    
    showLoading();
    
    const formData = new FormData();
    formData.append('action', 'search_students');
    formData.append('search_term', searchTerm);
    formData.append('status', statusFilter);
    
    fetch('students.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        hideLoading();
        
        // Update the table content
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        
        const newTable = tempDiv.querySelector('table');
        const currentTable = document.querySelector('table');
        
        if (newTable && currentTable) {
            currentTable.innerHTML = newTable.innerHTML;
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('An error occurred while searching.', false);
        console.error('Error:', error);
    });
}

/**
 * Event Listeners
 */

// Form submission handlers
document.addEventListener('DOMContentLoaded', function() {
    // Manual registration form
    const studentRegistrationForm = document.getElementById('studentRegistrationForm');
    if (studentRegistrationForm) {
        studentRegistrationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (validateStudentForm()) {
                const formData = new FormData(this);
                submitStudentRegistration(formData);
            }
        });
    }
    
    // Excel import form
    const excelImportForm = document.getElementById('excelImportForm');
    if (excelImportForm) {
        excelImportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (validateExcelFile()) {
                const formData = new FormData(this);
                submitExcelImport(formData);
            }
        });
    }
    
    // Edit student form
    const editStudentForm = document.getElementById('editStudentForm');
    if (editStudentForm) {
        editStudentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (validateEditStudentForm()) {
                const formData = new FormData(this);
                submitEditStudent(formData);
            }
        });
    }
    
    // Close modals when clicking outside
    const modals = ['manualRegistrationModal', 'excelImportModal', 'editStudentModal', 'deleteStudentModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    switch(modalId) {
                        case 'manualRegistrationModal':
                            closeManualRegistration();
                            break;
                        case 'excelImportModal':
                            closeExcelImport();
                            break;
                        case 'editStudentModal':
                            closeEditStudent();
                            break;
                        case 'deleteStudentModal':
                            closeDeleteStudent();
                            break;
                    }
                }
            });
        }
    });
});

function validateEditStudentForm() {
    const studentNumber = document.getElementById('edit_student_number').value.trim();
    const firstName = document.getElementById('edit_first_name').value.trim();
    const lastName = document.getElementById('edit_last_name').value.trim();
    const email = document.getElementById('edit_email').value.trim();
    
    // Clear previous error messages
    clearFormErrors();
    
    let isValid = true;
    
    // Validate Student ID
    if (!studentNumber) {
        showFieldError('edit_student_number', 'Student ID is required');
        isValid = false;
    } else if (studentNumber.length < 3) {
        showFieldError('edit_student_number', 'Student ID must be at least 3 characters');
        isValid = false;
    }
    
    // Validate First Name
    if (!firstName) {
        showFieldError('edit_first_name', 'First name is required');
        isValid = false;
    } else if (firstName.length < 2) {
        showFieldError('edit_first_name', 'First name must be at least 2 characters');
        isValid = false;
    }
    
    // Validate Last Name
    if (!lastName) {
        showFieldError('edit_last_name', 'Last name is required');
        isValid = false;
    } else if (lastName.length < 2) {
        showFieldError('edit_last_name', 'Last name must be at least 2 characters');
        isValid = false;
    }
    
    // Validate Email
    if (!email) {
        showFieldError('edit_email', 'Email is required');
        isValid = false;
    } else if (!isValidEmail(email)) {
        showFieldError('edit_email', 'Please enter a valid email address');
        isValid = false;
    }
    
    return isValid;
}

function submitEditStudent(formData) {
    showLoading();
    
    fetch('students.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        showAlert(data.message, data.success);
        
        if (data.success) {
            closeEditStudent();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('An error occurred while updating the student.', false);
        console.error('Error:', error);
    });
}

/**
 * Export Functions
 */

function exportStudents(format = 'csv') {
    showLoading();
    
    const formData = new FormData();
    formData.append('action', 'export_students');
    formData.append('format', format);
    
    fetch('students.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.blob())
    .then(blob => {
        hideLoading();
        
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `students_export_${new Date().toISOString().split('T')[0]}.${format}`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    })
    .catch(error => {
        hideLoading();
        showAlert('An error occurred while exporting data.', false);
        console.error('Error:', error);
    });
}

/**
 * Bulk Operations
 */

function selectAllStudents() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateBulkActionsVisibility();
}

function updateBulkActionsVisibility() {
    const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
    const bulkActionsDiv = document.getElementById('bulkActions');
    
    if (checkedBoxes.length > 0) {
        bulkActionsDiv.classList.remove('hidden');
    } else {
        bulkActionsDiv.classList.add('hidden');
    }
}

function performBulkAction(action) {
    const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
    const studentIds = Array.from(checkedBoxes).map(cb => cb.value);
    
    if (studentIds.length === 0) {
        showAlert('Please select at least one student.', false);
        return;
    }
    
    let confirmMessage = '';
    switch (action) {
        case 'activate':
            confirmMessage = `Are you sure you want to activate ${studentIds.length} student(s)?`;
            break;
        case 'deactivate':
            confirmMessage = `Are you sure you want to deactivate ${studentIds.length} student(s)?`;
            break;
        case 'delete':
            confirmMessage = `Are you sure you want to delete ${studentIds.length} student(s)? This action cannot be undone.`;
            break;
        case 'reset_password':
            confirmMessage = `Are you sure you want to reset passwords for ${studentIds.length} student(s) to default?`;
            break;
    }
    
    if (confirm(confirmMessage)) {
        showLoading();
        
        const formData = new FormData();
        formData.append('action', 'bulk_' + action);
        formData.append('student_ids', JSON.stringify(studentIds));
        
        fetch('students.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            showAlert(data.message, data.success);
            
            if (data.success) {
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        })
        .catch(error => {
            hideLoading();
            showAlert('An error occurred while performing bulk action.', false);
            console.error('Error:', error);
        });
    }
} 