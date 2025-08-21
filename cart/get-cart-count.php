<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode([
        'success' => true,
        'count' => 0,
        'total' => format_price(0)
    ]);
    exit();
}

try {
    $cart_count = get_cart_count($conn);
    $cart_total = get_cart_total($conn);
    
    echo json_encode([
        'success' => true,
        'count' => $cart_count,
        'total' => format_price($cart_total)
    ]);
    
} catch (Exception $e) {
    error_log("Get cart count error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'count' => 0,
        'total' => format_price(0),
        'message' => 'Error fetching cart data'
    ]);
}
?>