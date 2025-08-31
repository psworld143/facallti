<?php
// Disable error display for API
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Function to check database connection and get table count
function checkDatabaseConnection($host, $username, $password, $dbname, $port = 3306, $socket = null) {
    try {
        if ($socket) {
            $conn = mysqli_connect($host, $username, $password, $dbname, $port, $socket);
        } else {
            $conn = mysqli_connect($host, $username, $password, $dbname, $port);
        }
        
        if (!$conn) {
            return [
                'connected' => false,
                'error' => mysqli_connect_error(),
                'tables' => 0
            ];
        }
        
        mysqli_set_charset($conn, "utf8");
        
        // Get table count
        $result = mysqli_query($conn, "SHOW TABLES");
        $table_count = $result ? mysqli_num_rows($result) : 0;
        
        mysqli_close($conn);
        
        return [
            'connected' => true,
            'tables' => $table_count,
            'error' => null
        ];
        
    } catch (Exception $e) {
        return [
            'connected' => false,
            'error' => $e->getMessage(),
            'tables' => 0
        ];
    }
}

// Check local database
$local_result = checkDatabaseConnection(
    'localhost',
    'root',
    '',
    'seait_website',
    3306,
    '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock'
);

// Check online database
$online_result = checkDatabaseConnection(
    'seait-edu.ph',
    'seaitedu_seait_website',
    '020894Website',
    'seaitedu_seait_website'
);

// Return results with error handling
try {
    $response = [
        'local' => $local_result,
        'online' => $online_result,
        'timestamp' => date('Y-m-d H:i:s'),
        'success' => true
    ];
    
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to check database connections: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
