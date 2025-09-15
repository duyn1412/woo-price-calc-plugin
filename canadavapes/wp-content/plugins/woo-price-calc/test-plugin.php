<?php
/**
 * Test file for Custom Age Verifier and Tax Plugin
 * This file helps debug any issues with the main plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../');
}

// Include WordPress core
require_once(ABSPATH . 'wp-config.php');
require_once(ABSPATH . 'wp-load.php');

// Test basic functions
echo "<h1>Plugin Test Results</h1>";

// Test 1: Check if WordPress is loaded
if (function_exists('wp_version_check')) {
    echo "<p>✅ WordPress loaded successfully</p>";
} else {
    echo "<p>❌ WordPress not loaded</p>";
}

// Test 2: Check if plugin functions exist
if (function_exists('get_province_from_all_sources')) {
    echo "<p>✅ get_province_from_all_sources function exists</p>";
    
    // Test the function
    $province = get_province_from_all_sources();
    echo "<p>Province from function: " . ($province ?: 'null') . "</p>";
} else {
    echo "<p>❌ get_province_from_all_sources function not found</p>";
}

// Test 3: Check if WooCommerce is active
if (class_exists('WooCommerce')) {
    echo "<p>✅ WooCommerce is active</p>";
} else {
    echo "<p>❌ WooCommerce not active</p>";
}

// Test 4: Check current page type
echo "<p>Front page: " . (is_front_page() ? 'Yes' : 'No') . "</p>";
echo "<p>Shop: " . (is_shop() ? 'Yes' : 'No') . "</p>";
echo "<p>Product: " . (is_product() ? 'Yes' : 'No') . "</p>";

// Test 5: Check cookies
echo "<p>Cookie province: " . (isset($_COOKIE['province']) ? $_COOKIE['province'] : 'Not set') . "</p>";

// Test 6: Check GET parameters
echo "<p>GET province: " . (isset($_GET['province']) ? $_GET['province'] : 'None') . "</p>";

echo "<hr>";
echo "<p>Test completed. If you see this, the plugin is working.</p>";
?>
