<?php
/**
 * PHP Syntax Test File
 * This file tests basic PHP syntax to identify any issues
 */

echo "<h1>PHP Syntax Test</h1>";

// Test 1: Basic PHP
echo "<p>✅ PHP is working</p>";

// Test 2: Array function
if (function_exists('array')) {
    echo "<p>✅ Array function exists</p>";
} else {
    echo "<p>❌ Array function not found</p>";
}

// Test 3: Create array
try {
    $test_array = array('test' => 'value');
    echo "<p>✅ Array created successfully</p>";
} catch (Exception $e) {
    echo "<p>❌ Error creating array: " . $e->getMessage() . "</p>";
}

// Test 4: Foreach with colon syntax
try {
    $test_array = array('a' => 'A', 'b' => 'B');
    foreach ($test_array as $key => $value):
        echo "<p>✅ Foreach colon syntax works: $key = $value</p>";
    endforeach;
} catch (Exception $e) {
    echo "<p>❌ Foreach colon syntax error: " . $e->getMessage() . "</p>";
}

// Test 5: For loop with colon syntax
try {
    for ($i = 1; $i <= 3; $i++):
        echo "<p>✅ For colon syntax works: $i</p>";
    endfor;
} catch (Exception $e) {
    echo "<p>❌ For colon syntax error: " . $e->getMessage() . "</p>";
}

// Test 6: Basic for loop
try {
    for ($i = 1; $i <= 3; $i++) {
        echo "<p>✅ Basic for loop works: $i</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Basic for loop error: " . $e->getMessage() . "</p>";
}

// Test 7: Date function
try {
    $current_year = date('Y');
    echo "<p>✅ Date function works: $current_year</p>";
} catch (Exception $e) {
    echo "<p>❌ Date function error: " . $e->getMessage() . "</p>";
}

// Test 8: String functions
try {
    $test_string = "Hello World";
    $length = strlen($test_string);
    echo "<p>✅ String functions work: Length = $length</p>";
} catch (Exception $e) {
    echo "<p>❌ String functions error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p>PHP syntax test completed.</p>";

// Show PHP version
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>PHP SAPI: " . php_sapi_name() . "</p>";
?>
