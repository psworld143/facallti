<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// For testing, bypass session check
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
//     header('Location: ../index.php');
//     exit();
// }

echo "<h1>Test Form Submission</h1>";

// Test with faculty ID 8
$faculty_id = 8;

// Get faculty details
$query = "SELECT f.*, fd.middle_name, fd.phone FROM faculty f LEFT JOIN faculty_details fd ON f.id = fd.faculty_id WHERE f.id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $faculty_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$faculty = mysqli_fetch_assoc($result)) {
    echo "❌ Faculty not found<br>";
    exit();
}

echo "<h2>Current Faculty Data:</h2>";
echo "Name: " . $faculty['first_name'] . " " . $faculty['last_name'] . "<br>";
echo "Email: " . $faculty['email'] . "<br>";
echo "Position: " . $faculty['position'] . "<br>";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Form Submitted!</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    // Simple validation
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name = sanitize_input($_POST['last_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    
    if (empty($first_name) || empty($last_name) || empty($email)) {
        echo "<div style='color: red;'>❌ Required fields missing</div>";
    } else {
        echo "<div style='color: green;'>✅ Basic validation passed</div>";
        
        // Try to update
        try {
            $update_query = "UPDATE faculty SET first_name = ?, last_name = ?, email = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "sssi", $first_name, $last_name, $email, $faculty_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                echo "<div style='color: green;'>✅ Database updated successfully</div>";
                // Refresh faculty data
                $faculty['first_name'] = $first_name;
                $faculty['last_name'] = $last_name;
                $faculty['email'] = $email;
            } else {
                echo "<div style='color: red;'>❌ Database update failed: " . mysqli_error($conn) . "</div>";
            }
        } catch (Exception $e) {
            echo "<div style='color: red;'>❌ Error: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<form method="POST" onsubmit="return validateSimpleForm()">
    <h3>Basic Information</h3>
    <p>First Name: <input type="text" name="first_name" value="<?php echo htmlspecialchars($faculty['first_name']); ?>" required></p>
    <p>Last Name: <input type="text" name="last_name" value="<?php echo htmlspecialchars($faculty['last_name']); ?>" required></p>
    <p>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($faculty['email']); ?>" required></p>
    
    <input type="submit" value="Test Update">
</form>

<script>
function validateSimpleForm() {
    console.log('Form validation running...');
    
    const firstName = document.querySelector('[name="first_name"]').value.trim();
    const lastName = document.querySelector('[name="last_name"]').value.trim();
    const email = document.querySelector('[name="email"]').value.trim();
    
    if (!firstName || !lastName || !email) {
        alert('Please fill in all required fields');
        return false;
    }
    
    console.log('Validation passed, submitting form...');
    return true;
}

// Add form submit event listener
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, form ready');
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            console.log('Form submit event triggered');
        });
    }
});
</script>
