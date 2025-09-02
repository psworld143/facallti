<?php
// Suppress warnings to ensure clean JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Ensure no output before JSON
ob_clean();

$recent_photos = array();
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
    echo json_encode(array('message' => 'Database connection failed!'));
    exit;
}
if(isset($_POST['token']) && $_POST['token'] == "Seait123"){
    if ($_POST['qr'] == '' || $_POST['qr'] =='Scan ID') {
       // echo json_encode(array('message' => 'Please scan QR Code first!'));
    } 
    else{
        $qr = $_POST['qr'];
        
        // Debug: Log the QR code being processed
        error_log("Processing QR: " . $qr);
        
        // Try seens_logs table first, fallback to logs table
        $table_name = 'seens_logs';
        $check_table = $conn->query("SHOW TABLES LIKE '$table_name'");
        if($check_table && $check_table->num_rows == 0) {
            $table_name = 'logs';
            error_log("seens_logs table not found, using logs table");
        } else {
            error_log("Using seens_logs table");
        }
        
        $sql_check = $conn->query("SELECT * FROM $table_name WHERE qr_code ='$qr' AND ABS(TIMESTAMPDIFF(MINUTE, date_added, NOW())) <= 5");
        if($sql_check && $sql_check->num_rows > 0){
            //echo json_encode(array('message' => 'Logs added already, please wait after 5 minutes'));
            $sql_get_recent_logs_new = $conn->query("SELECT * FROM $table_name LEFT JOIN seens_student ON seens_student.ss_id_no = $table_name.qr_code ORDER BY $table_name.date_added DESC LIMIT 14");
            if($sql_get_recent_logs_new) {
                while ($row_recent = $sql_get_recent_logs_new->fetch_assoc()) {
                    $photos = $row_recent['ss_photo_location'];
                    if($photos && $photos != '') {
                        array_push($recent_photos, $photos);
                        error_log("Added photo: " . substr($photos, 0, 50) . "...");
                    }
                }
            } else {
                error_log("Query failed: " . $conn->error);
            }
            error_log("Recent photos count: " . count($recent_photos));
            echo json_encode($recent_photos);
        }
        else{
            $sql_insert = $conn->query("INSERT INTO $table_name(qr_code) VALUES('$qr')");
            if($sql_insert){
                error_log("Successfully inserted into $table_name table");
                $sql_get_recent_logs_new = $conn->query("SELECT * FROM $table_name LEFT JOIN seens_student ON seens_student.ss_id_no = $table_name.qr_code ORDER BY $table_name.date_added DESC LIMIT 14");
                if($sql_get_recent_logs_new) {
                    while ($row_recent = $sql_get_recent_logs_new->fetch_assoc()) {
                        $photos = $row_recent['ss_photo_location'];
                        if($photos && $photos != '') {
                            array_push($recent_photos, $photos);
                            error_log("Added photo: " . substr($photos, 0, 50) . "...");
                        }
                    }
                } else {
                    error_log("Query failed: " . $conn->error);
                }
            } else {
                error_log("Insert failed: " . $conn->error);
            }
            error_log("Recent photos count: " . count($recent_photos));
            echo json_encode($recent_photos);
        }
        
        
    }
}
else{
    echo json_encode(array('message' => 'Invalid Token Provided!'));
}