<?php
/**
 * Test autoload for PhpSerial
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>PhpSerial Autoload Test</h2>";

// Test 1: Check if vendor autoload exists
echo "<h3>1. Vendor Autoload</h3>";
$vendorPath = '../vendor/autoload.php';
if (file_exists($vendorPath)) {
    echo "✅ Vendor autoload found<br>";
    require_once $vendorPath;
} else {
    echo "❌ Vendor autoload not found<br>";
    exit;
}

// Test 2: Check if class exists after autoload
echo "<h3>2. Class Check</h3>";
if (class_exists('PhpSerial\PhpSerial')) {
    echo "✅ PhpSerial\\PhpSerial class found<br>";
} else {
    echo "❌ PhpSerial\\PhpSerial class not found<br>";
}

// Test 3: Try to load manually
echo "<h3>3. Manual Load Test</h3>";
$manualPath = '../vendor/gregwar/php-serial/PhpSerial/PhpSerial.php';
if (file_exists($manualPath)) {
    echo "✅ Manual file exists<br>";
    require_once $manualPath;
    
    if (class_exists('PhpSerial\PhpSerial')) {
        echo "✅ PhpSerial\\PhpSerial class found after manual load<br>";
    } else {
        echo "❌ PhpSerial\\PhpSerial class still not found after manual load<br>";
    }
} else {
    echo "❌ Manual file not found<br>";
}

// Test 4: List all declared classes
echo "<h3>4. All PhpSerial Classes</h3>";
$classes = get_declared_classes();
$phpSerialClasses = [];
foreach ($classes as $class) {
    if (strpos($class, 'PhpSerial') !== false) {
        $phpSerialClasses[] = $class;
    }
}

if (empty($phpSerialClasses)) {
    echo "❌ No PhpSerial classes found<br>";
} else {
    echo "✅ Found PhpSerial classes:<br>";
    foreach ($phpSerialClasses as $class) {
        echo "- $class<br>";
    }
}

// Test 5: Try to instantiate
echo "<h3>5. Instantiation Test</h3>";
try {
    $serial = new PhpSerial\PhpSerial();
    echo "✅ Successfully created PhpSerial\\PhpSerial instance<br>";
} catch (Exception $e) {
    echo "❌ Failed to create instance: " . $e->getMessage() . "<br>";
}

echo "<br><strong>Test complete.</strong>";
?>
