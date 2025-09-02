<?php
// Suppress warnings to ensure clean JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Include configuration and establish connection
if (!function_exists('establishConnection')) {
    include('../configuration.php');
}

$conn = establishConnection();

if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode(array('success' => 0, 'message' => 'Database connection failed!'));
    exit;
}

// Get all users from the database
$sql = "SELECT * FROM seens_student ORDER BY ss_date_added DESC";
$result = mysqli_query($conn, $sql);

if (!$result) {
    header('Content-Type: application/json');
    echo json_encode(array('success' => 0, 'message' => 'Query failed: ' . mysqli_error($conn)));
    exit;
}

$users = array();
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = array(
        'id' => $row['ss_id'],
        'id_no' => $row['ss_id_no'],
        'photo_location' => $row['ss_photo_location'],
        'date_added' => $row['ss_date_added']
    );
}

header('Content-Type: application/json');
echo json_encode(array('success' => 1, 'data' => $users));
?>
