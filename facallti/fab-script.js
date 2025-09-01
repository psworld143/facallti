// Floating Action Button Functionality
class FloatingActionButton {
    constructor() {
        this.fabButton = document.getElementById('fabButton');
        this.fabModal = document.getElementById('fabModal');
        this.fabModalOverlay = document.getElementById('fabModalOverlay');
        this.fabModalClose = document.getElementById('fabModalClose');
        this.fabModalTitle = document.getElementById('fabModalTitle');
        this.fabTeachersGrid = document.getElementById('fabTeachersGrid');
        
        this.init();
    }

    init() {
        this.bindEvents();
        console.log('Floating Action Button initialized');
    }

    bindEvents() {
        this.fabButton.addEventListener('click', () => this.showModal());
        this.fabModalOverlay.addEventListener('click', () => this.hideModal());
        this.fabModalClose.addEventListener('click', () => this.hideModal());
        
        // Close modal with Escape key
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.fabModal.classList.contains('show')) {
                this.hideModal();
            }
        });
    }

    showModal() {
        this.fabModal.classList.add('show');
        this.fabButton.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
        
        // Add bounce effect after main animation
        setTimeout(() => {
            const modalContent = this.fabModal.querySelector('.fab-modal-content');
            if (modalContent) {
                modalContent.classList.add('bounce');
            }
        }, 600);
        
        this.loadTeachers();
    }

    hideModal() {
        // Remove bounce class first
        const modalContent = this.fabModal.querySelector('.fab-modal-content');
        if (modalContent) {
            modalContent.classList.remove('bounce');
        }
        
        // Add closing class for smooth exit animation
        this.fabModal.classList.add('closing');
        this.fabButton.classList.remove('modal-open');
        this.fabButton.classList.add('modal-closing');
        
        // Remove closing class and hide modal after animation completes
        setTimeout(() => {
            this.fabModal.classList.remove('show', 'closing');
            this.fabButton.classList.remove('modal-closing');
            document.body.style.overflow = '';
        }, 600); // Match the CSS transition duration
    }

    async loadTeachers() {
        // Show loading state
        this.fabTeachersGrid.innerHTML = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                <div class="loading-spinner rounded-full h-12 w-12 border-4 border-orange-200 border-t-orange-500 mx-auto mb-6"></div>
                <p class="text-gray-500 text-lg">Loading teachers...</p>
                <p class="text-gray-400 text-sm mt-2">Please wait while we fetch the latest information</p>
            </div>
        `;

        try {
            // Get selected department from PHP variable
            const selectedDept = window.selectedDepartment || '';
            const title = selectedDept ? `${selectedDept} Teachers` : 'All Teachers';
            this.fabModalTitle.textContent = title;

            // Fetch teachers from API
            const url = `get-fab-teachers.php${selectedDept ? `?department=${encodeURIComponent(selectedDept)}` : ''}`;
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                // Update title with count
                const consultationCount = data.teachers.filter(t => t.has_consultation).length;
                const totalCount = data.teachers.length;
                const titleWithCount = `${this.fabModalTitle.textContent} (${consultationCount}/${totalCount} available)`;
                this.fabModalTitle.textContent = titleWithCount;
                
                let teachersHTML = '';

                if (data.teachers.length > 0) {
                    data.teachers.forEach(teacher => {
                        const statusClass = teacher.has_consultation ? 'fab-teacher-time' : 'fab-teacher-no-consultation';
                        const statusText = teacher.has_consultation ? teacher.consultation_time : 'Click to request consultation';
                        const cardClass = teacher.has_consultation ? 'fab-teacher-card has-consultation' : 'fab-teacher-card no-consultation';
                        
                        // Create avatar HTML - show photo if available, otherwise show initials
                        let avatarHTML = '';
                        if (teacher.image_url && teacher.image_url.trim() !== '') {
                            avatarHTML = `<img src="../${teacher.image_url}" alt="${teacher.full_name}" class="fab-teacher-photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" onload="this.style.opacity='1'; this.nextElementSibling.style.display='none';" style="opacity: 0;">`;
                        }
                        // Always include initials as fallback
                        avatarHTML += `<div class="fab-teacher-initials" style="${teacher.image_url && teacher.image_url.trim() !== '' ? 'display: flex;' : 'display: flex;'}">${teacher.initials}</div>`;
                        
                        teachersHTML += `
                            <div class="${cardClass}" 
                                 data-teacher-id="${teacher.id}" 
                                 data-teacher-name="${teacher.full_name}" 
                                 data-teacher-dept="${teacher.department}"
                                 data-has-consultation="${teacher.has_consultation}">
                                <div class="fab-teacher-avatar">
                                    ${avatarHTML}
                                </div>
                                <div class="fab-teacher-name">${teacher.full_name}</div>
                                <div class="fab-teacher-dept">${teacher.department}</div>
                                <div class="${statusClass}">${statusText}</div>
                            </div>
                        `;
                    });
                } else {
                    teachersHTML = `
                        <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                            <i class="fas fa-user-slash text-gray-400 text-4xl mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-600 mb-2">No Teachers Found</h3>
                            <p class="text-gray-500">No teachers found in this department.</p>
                        </div>
                    `;
                }

                this.fabTeachersGrid.innerHTML = teachersHTML;
                this.bindTeacherCardEvents();
            } else {
                throw new Error(data.error || 'Failed to load teachers');
            }
        } catch (error) {
            console.error('Error loading teachers:', error);
            this.fabTeachersGrid.innerHTML = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                    <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">Error Loading Teachers</h3>
                    <p class="text-gray-500">Failed to load teachers. Please try again.</p>
                </div>
            `;
        }
    }

    bindTeacherCardEvents() {
        const fabTeacherCards = this.fabTeachersGrid.querySelectorAll('.fab-teacher-card');
        fabTeacherCards.forEach(card => {
            card.addEventListener('click', (e) => {
                e.preventDefault();
                const teacherId = card.getAttribute('data-teacher-id');
                const teacherName = card.getAttribute('data-teacher-name');
                const teacherDept = card.getAttribute('data-teacher-dept');
                
                // Allow consultation request for all teachers
                // Hide FAB modal
                this.hideModal();
                
                // Trigger consultation for this teacher
                if (window.showConfirmationDialog) {
                    window.showConfirmationDialog(teacherName, teacherId, teacherDept);
                } else {
                    console.log('showConfirmationDialog function not found');
                }
            });
        });
    }


}

// Initialize FAB when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new FloatingActionButton();
});

// Export for use in other scripts
window.FloatingActionButton = FloatingActionButton;
