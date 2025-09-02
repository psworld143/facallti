<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);
header('Content-Type: application/json');

include_once '../configuration.php';

// Ensure establishConnection function is available
if (!function_exists('establishConnection')) {
    function establishConnection() {
        global $host, $username, $password, $dbname, $socket;
        
        try {
            if ($socket) {
                $conn = new mysqli($host, $username, $password, $dbname, 3306, $socket);
            } else {
                $conn = new mysqli($host, $username, $password, $dbname, 3306);
            }
            return $conn;
        } catch (Exception $e) {
            return null;
        }
    }
}

// Establish database connection
$conn = establishConnection();
if (!$conn || $conn->connect_error) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Database connection failed!'
    ));
    exit;
}

try {
    // Get recent registrations
    $sql_recent_scan = mysqli_query($conn, "SELECT * FROM seens_student ORDER BY ss_date_added DESC LIMIT 4");
    
    $recent_registrations = array();
    while($row_recent = mysqli_fetch_assoc($sql_recent_scan)){
        $recent_registrations[] = array(
            'id_no' => $row_recent['ss_id_no'],
            'photo_location' => $row_recent['ss_photo_location'],
            'date_added' => $row_recent['ss_date_added']
        );
    }
    
    echo json_encode(array(
        'success' => true,
        'data' => $recent_registrations
    ));
    
} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Error fetching recent registrations: ' . $e->getMessage()
    ));
}
?>
