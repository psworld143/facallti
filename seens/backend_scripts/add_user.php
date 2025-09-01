<?php
// Increase PHP limits for large image data
ini_set('post_max_size', '50M');
ini_set('upload_max_filesize', '50M');
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

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
    error_log("Database connection failed in add_user.php");
    header('Content-Type: application/json');
    echo json_encode(array('message' => 'Database connection failed!'));
    exit;
}

// Debug logging
error_log("add_user.php called");
error_log("POST data received: " . (isset($_POST) ? "Yes" : "No"));
error_log("POST data size: " . (isset($_POST) ? strlen(serialize($_POST)) : 0));

try {
    if(isset($_POST['token']) && $_POST['token'] == "Seait123"){
        error_log("QR value: '" . $_POST['qr'] . "'");
        error_log("Picture length: " . strlen($_POST['picture']));
        
        if ($_POST['qr'] == '' || $_POST['qr'] =='Scan ID') {
            error_log("QR validation failed");
            header('Content-Type: application/json');
            echo json_encode(array('message' => 'Please scan QR Code first!'));
        } else if($_POST['picture'] == '') {
            error_log("Picture validation failed");
            header('Content-Type: application/json');
            echo json_encode(array('message' => 'Please capture student photo!'));
        }
        else{
            $qr = $_POST['qr'];
            $picture = $_POST['picture'];
            $sql_check = mysqli_query($conn, "SELECT * FROM seens_student WHERE ss_id_no ='$qr'");
            if(mysqli_num_rows($sql_check) > 0){
                header('Content-Type: application/json');
                echo json_encode(array('message' => 'Student already registered!'));
            }
            else{
                $sql_insert = mysqli_query($conn, "INSERT INTO seens_student(ss_id_no, ss_photo_location) VALUES('$qr','$picture')");
                if($sql_insert){
                    header('Content-Type: application/json');
                    echo json_encode(array('message' => 'Student successfully registered!'));
                }
                else{
                    header('Content-Type: application/json');
                    echo json_encode(array('message' => 'There is an error registering student!'));
                }
            }
        }
    }
    else{
        header('Content-Type: application/json');
        echo json_encode(array('message' => 'Invalid Token Provided!'));
    }
} catch (Exception $e) {
    error_log("Error in add_user.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(array('message' => 'Server error: ' . $e->getMessage()));
}
?>
