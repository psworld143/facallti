<?php
/**
 * ID Encryption Update Script
 * 
 * This script helps identify and update all files that need ID encryption implementation.
 * Run this script to see which files still need to be updated.
 */

require_once 'includes/id_encryption.php';

echo "=== ID Encryption Implementation Status ===\n\n";

// Files that need encryption includes and ID decryption
$files_to_update = [
    // Main site files
    'calendar.php',
    'content-creator/edit-post.php',
    'content-creator/delete-post.php',
    'content-creator/get_curriculum.php',
    'content-creator/get_requirement.php',
    
    // Faculty files
    'faculty/view-lesson.php',
    'faculty/edit-lesson.php',
    'faculty/view-training.php',
    'faculty/view-evaluation.php',
    'faculty/quiz-leaderboard.php',
    'faculty/class_quizzes.php',
    'faculty/class_materials.php',
    'faculty/my-trainings.php',
    'faculty/evaluation-results-table.php',
    'faculty/training-suggestions.php',
    'faculty/evaluation-results.php',
    
    // Admin files
    'admin/get_faculty.php',
    'admin/get_program.php',
    'admin/faculty.php',
    'admin/programs.php',
    
    // IntelliEVal files
    'IntelliEVal/view-training.php',
    'IntelliEVal/edit-training.php',
    'IntelliEVal/view-student.php',
    'IntelliEVal/edit-student.php',
    'IntelliEVal/view-evaluation.php',
    'IntelliEVal/teacher_details.php',
    'IntelliEVal/edit-teacher-assignment.php',
    'IntelliEVal/get_head_data.php',
    'IntelliEVal/head-teachers.php',
    'IntelliEVal/head-evaluations.php',
    'IntelliEVal/trainings.php',
    'IntelliEVal/students.php',
    'IntelliEVal/teacher-subjects.php',
    'IntelliEVal/view-teacher-suggestions.php',
    'IntelliEVal/reports/performance_report.php',
    
    // Social media files
    'social-media/view-post.php',
    'social-media/view-post-content.php',
    'social-media/approve-post.php',
    'social-media/reject-post.php',
    'social-media/approve-carousel.php',
    'social-media/reject-carousel.php',
    'social-media/dashboard.php',
    'social-media/approved-posts.php',
    'social-media/pending-post.php',
    'social-media/rejected-posts.php',
    
    // API files
    'api/get-announcement-details.php',
    'api/get-event-details.php',
    'api/get-faculty-event-details.php',
    'api/get-quiz-leaderboard.php',
    'api/get-quiz-answers.php',
    
    // Students files
    'students/view-evaluation.php',
    'students/take-quiz.php',
    'students/class_dashboard.php',
    'students/class_syllabus.php',
    'students/class_materials.php',
    'students/lms_assignments.php',
    'students/lms_discussions.php',
    'students/lms_materials.php',
    'students/discussion_detail.php',
    'students/evaluate-teacher.php',
    'students/conduct-evaluation.php',
    
    // Heads files
    'heads/view-evaluation.php',
    'heads/view-evaluation-details.php',
    'heads/conduct-evaluation.php',
    
    // Admin assets
    'admin/assets/js/student-registration.js'
];

echo "Files that need ID encryption implementation:\n";
foreach ($files_to_update as $file) {
    if (file_exists($file)) {
        echo "✓ $file\n";
    } else {
        echo "✗ $file (not found)\n";
    }
}

echo "\n=== Implementation Instructions ===\n";
echo "1. Add require_once 'includes/id_encryption.php'; (or '../includes/id_encryption.php' for subdirectories)\n";
echo "2. Replace (int)\$_GET['id'] with safe_decrypt_id(\$_GET['id'])\n";
echo "3. Replace echo \$id; with echo encrypt_id(\$id); in links\n";
echo "4. Update JavaScript functions to use encrypted IDs\n\n";

echo "=== Example Implementation ===\n";
echo "Before:\n";
echo "require_once 'config/database.php';\n";
echo "\$id = (int)\$_GET['id'];\n";
echo "<a href=\"page.php?id=\$id\">\n\n";

echo "After:\n";
echo "require_once 'config/database.php';\n";
echo "require_once 'includes/id_encryption.php';\n";
echo "\$id = safe_decrypt_id(\$_GET['id']);\n";
echo "<a href=\"page.php?id=\" . encrypt_id(\$id) . \"\">\n\n";

echo "=== Testing ===\n";
echo "Test the encryption with:\n";
echo "Original ID: 15\n";
echo "Encrypted: " . encrypt_id(15) . "\n";
echo "Decrypted: " . decrypt_id(encrypt_id(15)) . "\n";
?>
