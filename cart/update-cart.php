<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to update cart'
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
$quantity = (int)($_POST['quantity'] ?? 0);

// Validation
if ($cart_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid cart item'
    ]);
    exit();
}

if ($quantity < 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid quantity'
    ]);
    exit();
}

try {
    // Verify cart item belongs to user
    $stmt = $conn->prepare("
        SELECT c.id, c.product_id, c.quantity, p.name, p.stock_quantity 
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
    
    if ($quantity === 0) {
        // Remove item from cart
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();
        
        $message = 'Item removed from cart';
    } else {
        // Check stock availability
        if ($quantity > $cart_item['stock_quantity']) {
            echo json_encode([
                'success' => false,
                'message' => 'Not enough stock available. Only ' . $cart_item['stock_quantity'] . ' items left.'
            ]);
            exit();
        }
        
        // Update quantity
        $stmt = $conn->prepare("UPDATE cart SET quantity = ?, added_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
        $stmt->execute();
        
        $message = 'Cart updated';
    }
    
    // Get updated cart count and total
    $cart_count = get_cart_count($conn);
    $cart_total = get_cart_total($conn);
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'cart_count' => $cart_count,
        'cart_total' => format_price($cart_total),
        'new_quantity' => $quantity
    ]);
    
} catch (Exception $e) {
    error_log("Update cart error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>