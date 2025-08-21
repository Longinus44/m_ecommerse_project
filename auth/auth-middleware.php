<?php
/**
 * Authentication Middleware
 * Include this file at the top of pages that require authentication
 */

require_once 'config.php';
require_once 'functions.php';

/**
 * Require authentication - redirect to login if not authenticated
 */
function require_auth($redirect_after_login = null) {
    if (!is_logged_in()) {
        $redirect_url = $redirect_after_login ?? $_SERVER['REQUEST_URI'];
        $login_url = '/auth/login.php?redirect=' . urlencode($redirect_url);
        
        // If we're already in the auth directory, adjust the path
        if (strpos($_SERVER['REQUEST_URI'], '/auth/') !== false) {
            $login_url = 'login.php?redirect=' . urlencode($redirect_url);
        }
        
        redirect($login_url);
    }
}

/**
 * Require guest (not logged in) - redirect to dashboard if authenticated
 */
function require_guest($redirect_if_authenticated = null) {
    if (is_logged_in()) {
        $redirect_url = $redirect_if_authenticated ?? '/index.php';
        redirect($redirect_url);
    }
}

/**
 * Check if user has specific role/permission (for future use)
 */
function has_permission($permission) {
    // This is a placeholder for future role-based access control
    // For now, all authenticated users have basic permissions
    return is_logged_in();
}

/**
 * Require specific permission
 */
function require_permission($permission) {
    require_auth();
    
    if (!has_permission($permission)) {
        // Redirect to unauthorized page or show error
        http_response_code(403);
        die('Access denied. You do not have permission to access this page.');
    }
}

/**
 * Auto-logout inactive users (call this on protected pages)
 */
function check_session_timeout($timeout_minutes = 60) {
    if (!is_logged_in()) {
        return;
    }
    
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        
        if ($inactive_time > ($timeout_minutes * 60)) {
            // Session expired
            session_unset();
            session_destroy();
            
            redirect('/auth/login.php?timeout=1');
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

/**
 * Remember Me functionality check
 */
function check_remember_token() {
    if (!is_logged_in() && isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        
        // In a full implementation, you would:
        // 1. Look up the token in the database
        // 2. Verify it's valid and not expired
        // 3. Auto-login the user
        // 4. Generate a new token for security
        
        // For now, this is just a placeholder
    }
}

// Auto-check remember token on every page load
check_remember_token();
?>