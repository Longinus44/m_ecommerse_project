<?php
// Database configuration
define('DB_HOST', '127.0.0.1');   // Try 127.0.0.1 fdb1032.awardspace.net
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'ecommerce_app');
// define('PORT', 3306);

// // Site configuration 
// define('SITE_URL', 'https://4674985_root.awardspace.info');
// define('SITE_NAME', 'ShopPortal');


// Site configuration 
define('SITE_URL', 'http://localhost/ecommerce-app');
define('SITE_NAME', 'ShopPortal');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection
try {
    $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Set charset
    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    echo $e->getMessage();
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed. Please try again later.");

}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Africa/Lagos');
?>
