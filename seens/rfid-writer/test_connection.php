<?php
/**
 * Simple connection test for RFID Writer
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>RFID Writer Connection Test</h2>";

// Test 1: Check if vendor autoload exists
echo "<h3>Test 1: Dependencies</h3>";
$vendorPath = '../vendor/autoload.php';
if (file_exists($vendorPath)) {
    echo "✅ Vendor autoload found<br>";
    require_once $vendorPath;
} else {
    echo "❌ Vendor autoload not found at: $vendorPath<br>";
    exit;
}

// Test 2: Check if PhpSerial class exists
if (class_exists('PhpSerial\PhpSerial')) {
    echo "✅ PhpSerial class found<br>";
} else {
    echo "❌ PhpSerial class not found<br>";
    exit;
}

// Test 3: Test basic serial connection
echo "<h3>Test 2: Serial Connection</h3>";
$testPort = (PHP_OS === 'WINNT') ? 'COM3' : '/dev/ttyUSB0';
echo "Testing port: $testPort<br>";

try {
    $serial = new PhpSerial\PhpSerial();
    $serial->deviceSet($testPort);
    $serial->confBaudRate(9600);
    $serial->confParity("none");
    $serial->confCharacterLength(8);
    $serial->confStopBits(1);
    $serial->confFlowControl("none");
    
    $result = $serial->deviceOpen();
    if ($result) {
        echo "✅ Successfully opened $testPort<br>";
        $serial->deviceClose();
    } else {
        echo "❌ Failed to open $testPort<br>";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

// Test 4: Test JSON response
echo "<h3>Test 3: JSON Response</h3>";
$testData = [
    'status' => 'success',
    'message' => 'Test response',
    'port' => $testPort
];

$jsonResponse = json_encode($testData);
if ($jsonResponse !== false) {
    echo "✅ JSON encoding works: $jsonResponse<br>";
} else {
    echo "❌ JSON encoding failed<br>";
}

// Test 5: Test RFIDWriter class
echo "<h3>Test 4: RFIDWriter Class</h3>";
try {
    require_once 'rfid_writer.php';
    $rfid = new RFIDWriter($testPort);
    echo "✅ RFIDWriter instance created<br>";
    
    $connected = $rfid->connect();
    if ($connected) {
        echo "✅ Connection successful<br>";
        $rfid->disconnect();
    } else {
        echo "❌ Connection failed<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><strong>Test complete. Check the results above.</strong>";
?>
