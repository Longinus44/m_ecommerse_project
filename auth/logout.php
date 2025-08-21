<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Verify CSRF token if provided
if (isset($_GET['token']) && !verify_csrf_token($_GET['token'])) {
    redirect('../index.php');
}

// Clear all session data
session_unset();
session_destroy();

// Clear remember me cookie if it exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Start a new session for the logout message
session_start();

// Redirect to login page with success message
redirect('login.php?logout=1');
?>