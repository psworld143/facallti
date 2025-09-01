<?php
// Suppress warnings to ensure clean JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

include('../configuration.php');

// Ensure establishConnection function is available
if (!function_exists('establishConnection')) {
    function establishConnection() {
        global $host, $username, $password, $dbname, $socket;
        
        try {
            if ($socket) {
                return new mysqli($host, $username, $password, $dbname, 3306, $socket);
            } else {
                return new mysqli($host, $username, $password, $dbname, 3306);
            }
        } catch (Exception $e) {
            return null;
        }
    }
}

if(isset($_POST['token']) && $_POST['token'] == "Seait123"){
    $qr = $_POST['qr'];
    
    // Establish database connection
    $conn = establishConnection();
    if (!$conn || $conn->connect_error) {
        header('Content-Type: application/json');
        echo json_encode(array('success' => 0, 'message' => 'Database connection failed!'));
        exit;
    }

    $sql_delete = mysqli_query($conn, "DELETE FROM seens_student WHERE ss_id_no = '$qr'");
    if($sql_delete){
        header('Content-Type: application/json');
        echo json_encode(array('success' => 1, 'message' => 'Registration deleted successfully!'));
    }
    else{
        header('Content-Type: application/json');
        echo json_encode(array('success' => 0, 'message' => 'Failed to delete registration!'));
    }
}
else{
    header('Content-Type: application/json');
    echo json_encode(array('success' => 0, 'message' => 'Invalid Token Provided!'));
}
