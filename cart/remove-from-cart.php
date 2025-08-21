<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to remove items from cart'
    ]);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

$user_id = get_current_user_id();
$cart_id = (int)($_POST['cart_id'] ?? 0);

// Validation
if ($cart_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid cart item'
    ]);
    exit();
}

try {
    // Verify cart item belongs to user and get product name
    $stmt = $conn->prepare("
        SELECT c.id, p.name 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.id = ? AND c.user_id = ?
    ");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Cart item not found'
        ]);
        exit();
    }
    
    $cart_item = $result->fetch_assoc();
    
    // Remove item from cart
    $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Get updated cart count and total
        $cart_count = get_cart_count($conn);
        $cart_total = get_cart_total($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Item removed from cart',
            'product_name' => $cart_item['name'],
            'cart_count' => $cart_count,
            'cart_total' => format_price($cart_total)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to remove item from cart'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Remove from cart error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>