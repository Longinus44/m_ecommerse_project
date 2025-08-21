<?php
$page_title = "Shopping Cart";
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = get_current_user_id();
$errors = [];
$success_message = '';

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        $action = $_POST['action'];
        
        if ($action === 'update_quantity') {
            $cart_id = (int)($_POST['cart_id'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 0);
            
            if ($quantity <= 0) {
                // Remove item if quantity is 0 or negative
                $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $cart_id, $user_id);
                $stmt->execute();
                $success_message = "Item removed from cart.";
            } else {
                // Update quantity
                $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
                $stmt->execute();
                $success_message = "Cart updated.";
            }
        } elseif ($action === 'remove_item') {
            $cart_id = (int)($_POST['cart_id'] ?? 0);
            
            $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $cart_id, $user_id);
            $stmt->execute();
            $success_message = "Item removed from cart.";
        } elseif ($action === 'clear_cart') {
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $success_message = "Cart cleared.";
        }
    }
}

// Get cart items
$stmt = $conn->prepare("
    SELECT c.id as cart_id, c.quantity, c.added_at,
           p.id as product_id, p.name, p.description, p.price, p.image_url, p.stock_quantity
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ? AND p.status = 'active'
    ORDER BY c.added_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}

// Shipping calculation (simple flat rate)
$shipping_threshold = 100; // Free shipping over ₦100
$shipping_cost = ($subtotal >= $shipping_threshold) ? 0 : 10;
$total = $subtotal + $shipping_cost;

include '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/styles.css">
<link rel="stylesheet" href="../assets/css/cart.css">

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Shopping Cart</h1>
        <p class="page-subtitle">Review your items before checkout</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul class="error-list">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <!-- Empty Cart -->
        <div class="empty-cart">
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="empty-cart-icon">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            <h2 class="empty-cart-title">Your cart is empty</h2>
            <p class="empty-cart-text">Add some products to get started!</p>
            <a href="../index.php" class="btn btn-primary btn-large">Continue Shopping</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <!-- Cart Items -->
            <div class="cart-items">
                <div class="cart-items-container">
                    <div class="cart-header">
                        <h3 class="cart-items-title">Cart Items (<?php echo $total_items; ?>)</h3>
                        <form method="POST" class="clear-cart-form">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="clear_cart">
                            <button type="submit" class="btn btn-outline btn-small" onclick="return confirm('Are you sure you want to clear your cart?')">
                                Clear Cart
                            </button>
                        </form>
                    </div>
                    
                    <div class="cart-items-list">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item">
                                <!-- Product Image -->
                                <div class="cart-item-image">
                                    <?php if (!empty($item['image_url']) && file_exists('../' . $item['image_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             class="cart-product-image">
                                    <?php else: ?>
                                        <div class="no-image-placeholder">
                                            No Image
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Product Details -->
                                <div class="cart-item-details">
                                    <h4 class="product-name">
                                        <a href="../product.php?id=<?php echo $item['product_id']; ?>">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </a>
                                    </h4>
                                    <p class="product-description">
                                        <?php 
                                        $description = htmlspecialchars($item['description']);
                                        echo strlen($description) > 100 ? substr($description, 0, 97) . '...' : $description;
                                        ?>
                                    </p>
                                    <div class="cart-product-info">
                                        <span class="cart-product-price">
                                            <?php echo format_price($item['price']); ?>
                                        </span>
                                        <span class="stock-info">
                                            <?php echo $item['stock_quantity']; ?> in stock
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Quantity and Actions -->
                                <div class="cart-item-actions">
                                    <div class="quantity-controls">
                                        <form method="POST" class="quantity-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="update_quantity">
                                            <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                            
                                            <button type="button" onclick="decrementQuantity(this)" class="btn btn-outline btn-small quantity-btn">-</button>
                                            
                                            <input type="number" 
                                                   name="quantity" 
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="0" 
                                                   max="<?php echo $item['stock_quantity']; ?>"
                                                   class="quantity-input"
                                                   onchange="this.form.submit()">
                                            
                                            <button type="button" onclick="incrementQuantity(this)" class="btn btn-outline btn-small quantity-btn">+</button>
                                        </form>
                                    </div>
                                    
                                    <div class="item-total">
                                        <?php echo format_price($item['price'] * $item['quantity']); ?>
                                    </div>
                                    
                                    <form method="POST" class="remove-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="remove_item">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                        <button type="submit" class="btn btn-outline btn-small remove-btn" onclick="return confirm('Remove this item from cart?')">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3,6 5,6 21,6"></polyline>
                                                <path d="M19,6l-2,14H7L5,6"></path>
                                                <path d="M10,11v6"></path>
                                                <path d="M14,11v6"></path>
                                                <path d="M5,6l1-3h12l1,3"></path>
                                            </svg>
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Cart Summary -->
            <div class="cart-summary">
                <div class="cart-summary-container">
                    <h3 class="summary-title">Order Summary</h3>
                    
                    <div class="summary-details">
                        <div class="summary-row">
                            <span class="summary-label">Subtotal (<?php echo $total_items; ?> items)</span>
                            <span class="summary-value"><?php echo format_price($subtotal); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="summary-label">Shipping</span>
                            <span class="summary-value">
                                <?php if ($shipping_cost == 0): ?>
                                    <span class="free-shipping">FREE</span>
                                <?php else: ?>
                                    <?php echo format_price($shipping_cost); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($subtotal < $shipping_threshold && $shipping_cost > 0): ?>
                            <div class="shipping-notice">
                                Add <?php echo format_price($shipping_threshold - $subtotal); ?> more for FREE shipping!
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-total">
                            <span class="total-label">Total</span>
                            <span class="total-value"><?php echo format_price($total); ?></span>
                        </div>
                    </div>
                    
                    <a href="checkout.php" class="btn btn-primary btn-block btn-large checkout-btn">
                        Proceed to Checkout
                    </a>
                    
                    <a href="../index.php" class="btn btn-outline btn-block continue-shopping-btn">
                        Continue Shopping
                    </a>
                    
                    <!-- Security Badges -->
                    <div class="security-badges">
                        <p class="security-text">Secure Checkout</p>
                        <div class="security-icons">
                            <span class="security-badge">SSL</span>
                            <span class="security-badge">256-bit</span>
                            <span class="security-badge">Secure</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="../assets/js/cart.js"></script>

<?php include '../includes/footer.php'; ?>