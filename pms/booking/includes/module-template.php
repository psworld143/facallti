<?php
/**
 * Module Template for Hotel PMS
 * 
 * Usage:
 * 1. Set $page_title before including this file
 * 2. Set $required_roles array if specific roles are required
 * 3. Set $additional_css if needed
 * 4. Set $additional_js if needed
 * 5. Include this file at the top of your module page
 * 6. Add your content between the template start and end
 */

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Check role requirements if specified
if (isset($required_roles) && !in_array($_SESSION['user_role'], $required_roles)) {
    header('Location: ../../login.php');
    exit();
}

// Set default values if not set
if (!isset($page_title)) {
    $page_title = 'Module Page';
}

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

<!-- Main Content -->
<main class="ml-0 lg:ml-64 mt-16 p-6 flex-1">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-semibold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if (isset($page_subtitle)): ?>
                <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($page_subtitle); ?></p>
            <?php endif; ?>
        </div>
        <div class="text-right">
            <div id="current-date" class="text-sm text-gray-600"></div>
            <div id="current-time" class="text-sm text-gray-600"></div>
        </div>
    </div>

    <!-- Page Content -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <!-- Your page content goes here -->
        <?php if (isset($page_content)): ?>
            <?php echo $page_content; ?>
        <?php endif; ?>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
