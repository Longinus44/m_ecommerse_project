<?php
$page_title = "Order Confirmation";
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$user_id = get_current_user_id();
$order_id = (int)($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    redirect('../index.php');
}

// Get order details
$stmt = $conn->prepare("
    SELECT o.*, u.full_name, u.email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect('../index.php');
}

$order = $result->fetch_assoc();

// Get order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name, p.description, p.image_url
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<!-- Link to external CSS -->
<link rel="stylesheet" href="../assets/css/styles.css">

<div class="container">
    <!-- Success Header -->
    <div class="success-header">
        <div class="success-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"></path>
                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
            </svg>
        </div>
        <h1>Order Confirmed!</h1>
        <p>Thank you for your purchase. Your order has been successfully placed.</p>
        <div class="order-number">
            <span>Order #<?php echo $order['id']; ?></span>
        </div>
    </div>

    <!-- Order Details -->
    <div class="order-details-grid">
        <!-- Order Items & Info -->
        <div class="order-main">
            <!-- Order Items -->
            <div class="order-items-section">
                <div class="section-header">
                    <h2>Order Items</h2>
                    <p>Items in your order</p>
                </div>
                
                <div class="order-items-list">
                    <?php foreach ($order_items as $item): ?>
                        <div class="order-item">
                            <div class="item-image">
                                <?php if (!empty($item['image_url']) && file_exists('../' . $item['image_url'])): ?>
                                    <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php else: ?>
                                    <div class="no-image">No Image</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-details">
                                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                <p class="item-description">
                                    <?php 
                                    $description = htmlspecialchars($item['description']);
                                    echo strlen($description) > 80 ? substr($description, 0, 77) . '...' : $description;
                                    ?>
                                </p>
                                <div class="item-pricing">
                                    <span class="quantity-price">
                                        Qty: <?php echo $item['quantity']; ?> × <?php echo format_price($item['price']); ?>
                                    </span>
                                    <span class="total-price">
                                        <?php echo format_price($item['price'] * $item['quantity']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Shipping Information -->
            <div class="shipping-section">
                <h3>Shipping Information</h3>
                <div class="shipping-grid">
                    <div class="shipping-address">
                        <h4>Delivery Address</h4>
                        <p><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                    </div>
                    <div class="shipping-delivery">
                        <h4>Estimated Delivery</h4>
                        <p class="delivery-date">
                            <?php 
                            $delivery_date = date('M j, Y', strtotime($order['created_at'] . ' +3 days'));
                            echo $delivery_date;
                            ?>
                        </p>
                        <p class="shipping-method">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20,6 9,17 4,12"></polyline>
                            </svg>
                            Standard Shipping (3-5 business days)
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Summary & Actions -->
        <div class="order-sidebar">
            <!-- Order Summary -->
            <div class="order-summary">
                <h3>Order Summary</h3>
                
                <div class="summary-details">
                    <div class="summary-row">
                        <span>Order Number</span>
                        <span>#<?php echo $order['id']; ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Order Date</span>
                        <span><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Status</span>
                        <span class="status-badge"><?php echo ucfirst($order['status']); ?></span>
                    </div>
                    
                    <div class="summary-total">
                        <span>Total Paid</span>
                        <span><?php echo format_price($order['total_amount']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3>Quick Actions</h3>
                <div class="action-buttons">
                    <a href="../orders.php" class="btn btn-primary btn-block">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"></path>
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                        </svg>
                        Track Order
                    </a>
                    
                    <a href="../index.php" class="btn btn-outline btn-block">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17M17 13v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6.01"></path>
                        </svg>
                        Continue Shopping
                    </a>
                    
                    <button onclick="printReceipt()" class="btn btn-outline btn-block">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6,9 6,2 18,2 18,9"></polyline>
                            <path d="M6,18H4a2 2 0 01-2-2v-5a2 2 0 012-2H20a2 2 0 012 2v5a2 2 0 01-2 2H18"></path>
                            <polyline points="6,14 6,22 18,22 18,14"></polyline>
                        </svg>
                        Print Receipt
                    </button>
                </div>
            </div>

            <!-- What's Next -->
            <div class="whats-next">
                <h3>What's Next?</h3>
                <div class="progress-steps">
                    <div class="step completed">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h4>Order Confirmation</h4>
                            <p>✓ Complete</p>
                        </div>
                    </div>
                    
                    <div class="step current">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h4>Processing</h4>
                            <p>We're preparing your order</p>
                        </div>
                    </div>
                    
                    <div class="step pending">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h4>Shipping</h4>
                            <p>Your order is on its way</p>
                        </div>
                    </div>
                    
                    <div class="step pending">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h4>Delivered</h4>
                            <p>Enjoy your purchase!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Confirmation Notice -->
    <div class="email-notice">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
            <polyline points="22,6 12,13 2,6"></polyline>
        </svg>
        <h3>Check Your Email</h3>
        <p>
            We've sent an order confirmation to <strong><?php echo htmlspecialchars($order['email']); ?></strong>
            <br>You'll receive shipping updates and tracking information via email.
        </p>
    </div>
</div>

<script src="../assets/js/order-confirmation.js"></script>

<?php include '../includes/footer.php'; ?>