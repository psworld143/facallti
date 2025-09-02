<?php
// Suppress warnings to ensure clean JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Ensure no output before JSON
ob_clean();

include('../configuration.php');

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
    header('Content-Type: application/json');
    echo json_encode(array('message' => 'Database connection failed!'));
    exit;
}
if(isset($_POST['token']) && $_POST['token'] == "Seait123"){
    if ($_POST['qr'] == '') {
        header('Content-Type: application/json');
        echo json_encode(array('message' => 'Please scan QR Code first!'));
    }
    else{
        $qr = trim($_POST['qr']);
        $sql_check = $conn->query("SELECT * FROM seens_student WHERE TRIM(ss_id_no) = '$qr'");
        if($sql_check && $sql_check->num_rows > 0){

            $row = $sql_check->fetch_assoc();
            $image = $row['ss_photo_location'];

            header('Content-Type: application/json');
            echo json_encode(array('message' => $image));
        }
        else{
            
            header('Content-Type: application/json');
            echo json_encode(array('message' => 0));
            
        }
        
        
    }
}
else{
    header('Content-Type: application/json');
    echo json_encode(array('message' => 'Invalid Token Provided!'));
}
