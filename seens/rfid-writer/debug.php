<?php
/**
 * Debug script for RFID Writer
 * This script helps identify connection issues
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>RFID Writer Debug Information</h2>";

// Check if vendor autoload exists
echo "<h3>1. Checking Dependencies</h3>";
$vendorPath = '../vendor/autoload.php';
if (file_exists($vendorPath)) {
    echo "✅ Vendor autoload found at: $vendorPath<br>";
    require_once $vendorPath;
} else {
    echo "❌ Vendor autoload not found at: $vendorPath<br>";
    echo "Please ensure Composer dependencies are installed.<br>";
    exit;
}

// Check if php-serial class exists
if (class_exists('PhpSerial\PhpSerial')) {
    echo "✅ PhpSerial class found<br>";
} else {
    echo "❌ PhpSerial class not found<br>";
    echo "Available classes in PhpSerial namespace:<br>";
    $classes = get_declared_classes();
    foreach ($classes as $class) {
        if (strpos($class, 'PhpSerial') !== false) {
            echo "- $class<br>";
        }
    }
    exit;
}

// Check platform
echo "<h3>2. Platform Information</h3>";
echo "Operating System: " . PHP_OS . "<br>";
echo "PHP Version: " . PHP_VERSION . "<br>";

// Test port detection
echo "<h3>3. Port Detection Test</h3>";
try {
    require_once 'rfid_writer.php';
    $ports = RFIDWriter::getAvailablePorts();
    echo "Available ports: " . implode(', ', $ports) . "<br>";
} catch (Exception $e) {
    echo "❌ Error in port detection: " . $e->getMessage() . "<br>";
}

// Test serial connection
echo "<h3>4. Serial Connection Test</h3>";
if (PHP_OS === 'WINNT') {
    $testPorts = ['COM3', 'COM4', 'COM5', 'COM6'];
} else {
    $testPorts = ['/dev/ttyUSB0', '/dev/ttyACM0'];
}

foreach ($testPorts as $port) {
    echo "Testing port: $port<br>";
    try {
        $serial = new PhpSerial\PhpSerial();
        $serial->deviceSet($port);
        $serial->confBaudRate(9600);
        $serial->confParity("none");
        $serial->confCharacterLength(8);
        $serial->confStopBits(1);
        $serial->confFlowControl("none");
        
        $result = $serial->deviceOpen();
        if ($result) {
            echo "✅ Successfully opened $port<br>";
            $serial->deviceClose();
            break;
        } else {
            echo "❌ Failed to open $port<br>";
        }
    } catch (Exception $e) {
        echo "❌ Exception with $port: " . $e->getMessage() . "<br>";
    }
}

// Test RFIDWriter class
echo "<h3>5. RFIDWriter Class Test</h3>";
try {
    $testPort = (PHP_OS === 'WINNT') ? 'COM3' : '/dev/ttyUSB0';
    $rfid = new RFIDWriter($testPort);
    echo "✅ RFIDWriter instance created successfully<br>";
    
    // Test connection
    $connected = $rfid->connect();
    if ($connected) {
        echo "✅ Connection successful to $testPort<br>";
        $rfid->disconnect();
    } else {
        echo "❌ Connection failed to $testPort<br>";
    }
} catch (Exception $e) {
    echo "❌ Error creating RFIDWriter: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

// Test JSON response
echo "<h3>6. JSON Response Test</h3>";
try {
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
} catch (Exception $e) {
    echo "❌ JSON test error: " . $e->getMessage() . "<br>";
}

echo "<h3>7. File Permissions</h3>";
if (PHP_OS !== 'WINNT') {
    $testPort = '/dev/ttyUSB0';
    if (file_exists($testPort)) {
        echo "Port file exists: $testPort<br>";
        echo "Readable: " . (is_readable($testPort) ? 'Yes' : 'No') . "<br>";
        echo "Writable: " . (is_writable($testPort) ? 'Yes' : 'No') . "<br>";
        echo "Permissions: " . substr(sprintf('%o', fileperms($testPort)), -4) . "<br>";
    } else {
        echo "Port file does not exist: $testPort<br>";
    }
}

echo "<h3>8. Recommendations</h3>";
if (PHP_OS === 'WINNT') {
    echo "- Make sure Arduino drivers are installed<br>";
    echo "- Check Device Manager for correct COM port<br>";
    echo "- Try different COM ports (COM3, COM4, COM5)<br>";
} else {
    echo "- Run: sudo usermod -a -G dialout www-data<br>";
    echo "- Run: sudo chmod 666 /dev/ttyUSB0<br>";
    echo "- Restart web server after permission changes<br>";
}

echo "<br><strong>Debug complete. Check the results above for issues.</strong>";
?>
