<?php
$page_title = "Checkout";
require_once '../includes/config.php';
require_once '../includes/functions.php';

define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost:8000');
define ('TEST_KEY', getenv('MSFT_live_S4X0Q0AQOVGR0AAX3I55BKLOUFZM6R0'));

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = get_current_user_id();
$current_user = get_current_user_info($conn);
$errors = [];
$success_message = '';

// Get cart items
$stmt = $conn->prepare("
    SELECT c.id as cart_id, c.quantity,
           p.id as product_id, p.name, p.price, p.image_url, p.stock_quantity
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ? AND p.status = 'active'
    ORDER BY c.added_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Redirect if cart is empty
if (empty($cart_items)) {
    redirect('cart.php');
}

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}

$shipping_threshold = 100;
$shipping_cost = ($subtotal >= $shipping_threshold) ? 0 : 10;
$total = $subtotal + $shipping_cost;

// Convert total to kobo (multiply by 100 for NGN)
$amount_in_kobo = $total * 100;

// Marasoft Pay configuration
$marasoft_config = [
    'public_key' => TEST_KEY, 
    'request_type' => 'test',
    'currency' => 'NGN',
    'redirect_url' => BASE_URL . '/cart/order-confirmation.php',
// 'webhook_url'  => BASE_URL . '/cart/webhook.php',
];

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        // Get form data
        $shipping_name = sanitize_input($_POST['shipping_name'] ?? '');
        $shipping_email = sanitize_input($_POST['shipping_email'] ?? '');
        $shipping_phone = sanitize_input($_POST['shipping_phone'] ?? '');
        $shipping_address = sanitize_input($_POST['shipping_address'] ?? '');
        $shipping_city = sanitize_input($_POST['shipping_city'] ?? '');
        $shipping_state = sanitize_input($_POST['shipping_state'] ?? '');
        $payment_method = sanitize_input($_POST['payment_method'] ?? '');
        $order_notes = sanitize_input($_POST['order_notes'] ?? '');
        
        // Validation
        if (empty($shipping_name)) {
            $errors[] = "Full name is required.";
        }
        
        if (empty($shipping_email)) {
            $errors[] = "Email address is required.";
        } elseif (!validate_email($shipping_email)) {
            $errors[] = "Please enter a valid email address.";
        }
        
        if (empty($shipping_phone)) {
            $errors[] = "Phone number is required.";
        }
        
        if (empty($shipping_address)) {
            $errors[] = "Shipping address is required.";
        }
        
        if (empty($shipping_city)) {
            $errors[] = "City is required.";
        }
        
        if (empty($shipping_state)) {
            $errors[] = "State is required.";
        }
        
        if (empty($payment_method)) {
            $errors[] = "Please select a payment method.";
        }
        
        // Double-check stock availability
        if (empty($errors)) {
            foreach ($cart_items as $item) {
                $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                $stmt->bind_param("i", $item['product_id']);
                $stmt->execute();
                $current_stock = $stmt->get_result()->fetch_assoc()['stock_quantity'];
                
                if ($current_stock < $item['quantity']) {
                    $errors[] = "Sorry, " . htmlspecialchars($item['name']) . " is no longer available in the requested quantity.";
                }
            }
        }
        
        // Create order if no errors
        if (empty($errors)) {
            $conn->begin_transaction();
            
            try {
                // Create shipping address
                $full_address = $shipping_address . ", " . $shipping_city . ", " . $shipping_state;
                
                // Generate unique merchant reference
                $merchant_ref = 'ORDER_' . time() . '_' . $user_id;
                
                // Insert order with pending status
                $stmt = $conn->prepare("
                    INSERT INTO orders (user_id, total_amount, status, shipping_address, merchant_ref, payment_method, order_notes, shipping_name, shipping_email, shipping_phone) 
                    VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("idsssssss", $user_id, $total, $full_address, $merchant_ref, $payment_method, $order_notes, $shipping_name, $shipping_email, $shipping_phone);
                $stmt->execute();
                $order_id = $stmt->insert_id;
                
                // Insert order items
                foreach ($cart_items as $item) {
                    $stmt = $conn->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity, price) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                    $stmt->execute();
                }
                
                // Store order details in session for payment processing
                $_SESSION['pending_order'] = [
                    'order_id' => $order_id,
                    'merchant_ref' => $merchant_ref,
                    'amount' => $amount_in_kobo,
                    'customer_name' => $shipping_name,
                    'customer_email' => $shipping_email,
                    'customer_phone' => $shipping_phone,
                    'payment_method' => $payment_method
                ];
                
                $conn->commit();
                
                // If Cash on Delivery, complete the order immediately
                if ($payment_method === 'cod') {
                    // Update order status
                    $stmt = $conn->prepare("UPDATE orders SET status = 'processing' WHERE id = ?");
                    $stmt->bind_param("i", $order_id);
                    $stmt->execute();
                    
                    // Update product stock
                    foreach ($cart_items as $item) {
                        $stmt = $conn->prepare("
                            UPDATE products 
                            SET stock_quantity = stock_quantity - ? 
                            WHERE id = ?
                        ");
                        $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                        $stmt->execute();
                    }
                    
                    // Clear cart
                    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    
                    redirect('/cart/order-confirmation.php?order_id=' . $order_id);
                } else {
                    // For online payments, redirect to Marasoft Pay
                    $payment_redirect = true;
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "Order creation failed. Please try again.";
                error_log("Checkout error: " . $e->getMessage());
                echo $e->getMessage();
            }
        }
    }
}

include '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/styles.css">

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Checkout</h1>
        <p class="page-subtitle">Complete your order</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul style="margin: 0; padding-left: 1.25rem;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (isset($payment_redirect) && $payment_redirect): ?>
        <!-- Marasoft Pay Form - Auto-submit for seamless user experience -->
        <div class="alert alert-info">
            <p>Redirecting to payment gateway...</p>
        </div>
        
        <form id="marasoftPayForm" action="https://checkout.marasoftpay.live/" method="POST" style="display: none;">
            <input type="hidden" name="public_key" value="<?php echo $marasoft_config['public_key']; ?>">
            <input type="hidden" name="merchant_ref" value="<?php echo $_SESSION['pending_order']['merchant_ref']; ?>">
            <input type="hidden" name="email_address" value="<?php echo htmlspecialchars($_SESSION['pending_order']['customer_email']); ?>">
            <input type="hidden" name="name" value="<?php echo htmlspecialchars($_SESSION['pending_order']['customer_name']); ?>">
            <input type="hidden" name="phone_number" value="<?php echo htmlspecialchars($_SESSION['pending_order']['customer_phone']); ?>">
            <input type="hidden" name="request_type" value="<?php echo $marasoft_config['request_type']; ?>">
            <input type="hidden" name="description" value="Order payment for <?php echo $total_items; ?> items">
            <input type="hidden" name="currency" value="<?php echo $marasoft_config['currency']; ?>">
            <input type="hidden" name="amount" value="<?php echo $amount_in_kobo; ?>">
            <input type="hidden" name="redirect_url" value="<?php echo $marasoft_config['redirect_url'] . '?order_id=' . $_SESSION['pending_order']['order_id'] . '&merchant_ref=' . $_SESSION['pending_order']['merchant_ref']; ?>">
            <input type="hidden" name="user_bear_charge" value="yes">
        </form>
        
        <script>
            // Auto-submit the payment form
            document.getElementById('marasoftPayForm').submit();
        </script>
        
    <?php else: ?>

    <form method="POST" id="checkoutForm">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 2rem;">
            <!-- Checkout Form -->
            <div class="checkout-form">
                <!-- Shipping Information -->
                <div style="background: var(--white); padding: 2rem; border-radius: 0.5rem; box-shadow: var(--shadow); margin-bottom: 2rem;">
                    <h3 style="color: var(--text-color); margin-bottom: 1.5rem;">Shipping Information</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label for="shipping_name" class="form-label">Full Name *</label>
                            <input type="text" 
                                   id="shipping_name" 
                                   name="shipping_name" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($_POST['shipping_name'] ?? $current_user['full_name']); ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="shipping_email" class="form-label">Email Address *</label>
                            <input type="email" 
                                   id="shipping_email" 
                                   name="shipping_email" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($_POST['shipping_email'] ?? $current_user['email']); ?>"
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="shipping_phone" class="form-label">Phone Number *</label>
                        <input type="tel" 
                               id="shipping_phone" 
                               name="shipping_phone" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['shipping_phone'] ?? ''); ?>"
                               placeholder="+234 xxx xxx xxxx"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="shipping_address" class="form-label">Street Address *</label>
                        <textarea id="shipping_address" 
                                  name="shipping_address" 
                                  class="form-input" 
                                  rows="3" 
                                  placeholder="Enter your full address"
                                  required><?php echo htmlspecialchars($_POST['shipping_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="shipping_city" class="form-label">City *</label>
                            <input type="text" 
                                   id="shipping_city" 
                                   name="shipping_city" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($_POST['shipping_city'] ?? ''); ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="shipping_state" class="form-label">State *</label>
                            <select id="shipping_state" name="shipping_state" class="form-input" required>
                                <option value="">Select State</option>
                                <option value="Abia" <?php echo ($_POST['shipping_state'] ?? '') == 'Abia' ? 'selected' : ''; ?>>Abia</option>
                                <option value="Adamawa" <?php echo ($_POST['shipping_state'] ?? '') == 'Adamawa' ? 'selected' : ''; ?>>Adamawa</option>
                                <option value="Akwa Ibom" <?php echo ($_POST['shipping_state'] ?? '') == 'Akwa Ibom' ? 'selected' : ''; ?>>Akwa Ibom</option>
                                <option value="Anambra" <?php echo ($_POST['shipping_state'] ?? '') == 'Anambra' ? 'selected' : ''; ?>>Anambra</option>
                                <option value="Bauchi" <?php echo ($_POST['shipping_state'] ?? '') == 'Bauchi' ? 'selected' : ''; ?>>Bauchi</option>
                                <option value="Bayelsa" <?php echo ($_POST['shipping_state'] ?? '') == 'Bayelsa' ? 'selected' : ''; ?>>Bayelsa</option>
                                <option value="Benue" <?php echo ($_POST['shipping_state'] ?? '') == 'Benue' ? 'selected' : ''; ?>>Benue</option>
                                <option value="Borno" <?php echo ($_POST['shipping_state'] ?? '') == 'Borno' ? 'selected' : ''; ?>>Borno</option>
                                <option value="Cross River" <?php echo ($_POST['shipping_state'] ?? '') == 'Cross River' ? 'selected' : ''; ?>>Cross River</option>
                                <option value="Delta" <?php echo ($_POST['shipping_state'] ?? '') == 'Delta' ? 'selected' : ''; ?>>Delta</option>
                                <option value="Ebonyi" <?php echo ($_POST['shipping_state'] ?? '') == 'Ebonyi' ? 'selected' : ''; ?>>Ebonyi</option>
                                <option value="Edo" <?php echo ($_POST['shipping_state'] ?? '') == 'Edo' ? 'selected' : ''; ?>>Edo</option>
                                <option value="Ekiti" <?php echo ($_POST['shipping_state'] ?? '') == 'Ekiti' ? 'selected' : ''; ?>>Ekiti</option>
                                <option value="Enugu" <?php echo ($_POST['shipping_state'] ?? '') == 'Enugu' ? 'selected' : ''; ?>>Enugu</option>
                                <option value="FCT" <?php echo ($_POST['shipping_state'] ?? '') == 'FCT' ? 'selected' : ''; ?>>FCT</option>
                                <option value="Gombe" <?php echo ($_POST['shipping_state'] ?? '') == 'Gombe' ? 'selected' : ''; ?>>Gombe</option>
                                <option value="Imo" <?php echo ($_POST['shipping_state'] ?? '') == 'Imo' ? 'selected' : ''; ?>>Imo</option>
                                <option value="Jigawa" <?php echo ($_POST['shipping_state'] ?? '') == 'Jigawa' ? 'selected' : ''; ?>>Jigawa</option>
                                <option value="Kaduna" <?php echo ($_POST['shipping_state'] ?? '') == 'Kaduna' ? 'selected' : ''; ?>>Kaduna</option>
                                <option value="Kano" <?php echo ($_POST['shipping_state'] ?? '') == 'Kano' ? 'selected' : ''; ?>>Kano</option>
                                <option value="Katsina" <?php echo ($_POST['shipping_state'] ?? '') == 'Katsina' ? 'selected' : ''; ?>>Katsina</option>
                                <option value="Kebbi" <?php echo ($_POST['shipping_state'] ?? '') == 'Kebbi' ? 'selected' : ''; ?>>Kebbi</option>
                                <option value="Kogi" <?php echo ($_POST['shipping_state'] ?? '') == 'Kogi' ? 'selected' : ''; ?>>Kogi</option>
                                <option value="Kwara" <?php echo ($_POST['shipping_state'] ?? '') == 'Kwara' ? 'selected' : ''; ?>>Kwara</option>
                                <option value="Lagos" <?php echo ($_POST['shipping_state'] ?? '') == 'Lagos' ? 'selected' : ''; ?>>Lagos</option>
                                <option value="Nasarawa" <?php echo ($_POST['shipping_state'] ?? '') == 'Nasarawa' ? 'selected' : ''; ?>>Nasarawa</option>
                                <option value="Niger" <?php echo ($_POST['shipping_state'] ?? '') == 'Niger' ? 'selected' : ''; ?>>Niger</option>
                                <option value="Ogun" <?php echo ($_POST['shipping_state'] ?? '') == 'Ogun' ? 'selected' : ''; ?>>Ogun</option>
                                <option value="Ondo" <?php echo ($_POST['shipping_state'] ?? '') == 'Ondo' ? 'selected' : ''; ?>>Ondo</option>
                                <option value="Osun" <?php echo ($_POST['shipping_state'] ?? '') == 'Osun' ? 'selected' : ''; ?>>Osun</option>
                                <option value="Oyo" <?php echo ($_POST['shipping_state'] ?? '') == 'Oyo' ? 'selected' : ''; ?>>Oyo</option>
                                <option value="Plateau" <?php echo ($_POST['shipping_state'] ?? '') == 'Plateau' ? 'selected' : ''; ?>>Plateau</option>
                                <option value="Rivers" <?php echo ($_POST['shipping_state'] ?? '') == 'Rivers' ? 'selected' : ''; ?>>Rivers</option>
                                <option value="Sokoto" <?php echo ($_POST['shipping_state'] ?? '') == 'Sokoto' ? 'selected' : ''; ?>>Sokoto</option>
                                <option value="Taraba" <?php echo ($_POST['shipping_state'] ?? '') == 'Taraba' ? 'selected' : ''; ?>>Taraba</option>
                                <option value="Yobe" <?php echo ($_POST['shipping_state'] ?? '') == 'Yobe' ? 'selected' : ''; ?>>Yobe</option>
                                <option value="Zamfara" <?php echo ($_POST['shipping_state'] ?? '') == 'Zamfara' ? 'selected' : ''; ?>>Zamfara</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div style="background: var(--white); padding: 2rem; border-radius: 0.5rem; box-shadow: var(--shadow); margin-bottom: 2rem;">
                    <h3 style="color: var(--text-color); margin-bottom: 1.5rem;">Payment Method</h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <label style="display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 2px solid var(--border-color); border-radius: 0.5rem; cursor: pointer; transition: all 0.2s;">
                            <input type="radio" name="payment_method" value="card" style="accent-color: var(--primary-color);" required>
                            <div>
                                <div style="font-weight: 500;">Credit/Debit Card</div>
                                <div style="color: var(--text-muted); font-size: 0.875rem;">Pay securely with your card via Marasoft Pay</div>
                            </div>
                        </label>
                        
                        <label style="display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 2px solid var(--border-color); border-radius: 0.5rem; cursor: pointer; transition: all 0.2s;">
                            <input type="radio" name="payment_method" value="transfer" style="accent-color: var(--primary-color);" required>
                            <div>
                                <div style="font-weight: 500;">Bank Transfer</div>
                                <div style="color: var(--text-muted); font-size: 0.875rem;">Pay via bank transfer through Marasoft Pay</div>
                            </div>
                        </label>
                        
                        <label style="display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 2px solid var(--border-color); border-radius: 0.5rem; cursor: pointer; transition: all 0.2s;">
                            <input type="radio" name="payment_method" value="cod" style="accent-color: var(--primary-color);" required>
                            <div>
                                <div style="font-weight: 500;">Cash on Delivery</div>
                                <div style="color: var(--text-muted); font-size: 0.875rem;">Pay when you receive your order</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Order Notes -->
                <div style="background: var(--white); padding: 2rem; border-radius: 0.5rem; box-shadow: var(--shadow);">
                    <h3 style="color: var(--text-color); margin-bottom: 1.5rem;">Order Notes (Optional)</h3>
                    <div class="form-group">
                        <textarea id="order_notes" 
                                  name="order_notes" 
                                  class="form-input" 
                                  rows="4" 
                                  placeholder="Any special instructions for your order..."><?php echo htmlspecialchars($_POST['order_notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <div style="background: var(--white); border-radius: 0.5rem; box-shadow: var(--shadow); padding: 2rem; position: sticky; top: 2rem;">
                    <h3 style="color: var(--text-color); margin-bottom: 1.5rem;">Order Summary</h3>
                    
                    <!-- Order Items -->
                    <div style="max-height: 300px; overflow-y: auto; margin-bottom: 1.5rem; border: 1px solid var(--border-color); border-radius: 0.375rem;">
                        <?php foreach ($cart_items as $item): ?>
                            <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; border-bottom: 1px solid var(--border-color);">
                                <div style="width: 50px; height: 50px; flex-shrink: 0;">
                                    <?php if (!empty($item['image_url']) && file_exists('../' . $item['image_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.25rem;">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; background-color: var(--background-color); border-radius: 0.25rem; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; color: var(--text-muted);">
                                            No Image
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 500; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </div>
                                    <div style="color: var(--text-muted); font-size: 0.8125rem;">
                                        Qty: <?php echo $item['quantity']; ?> × <?php echo format_price($item['price']); ?>
                                    </div>
                                </div>
                                <div style="font-weight: 600; color: var(--primary-color);">
                                    <?php echo format_price($item['price'] * $item['quantity']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Price Summary -->
                    <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Subtotal</span>
                            <span style="font-weight: 500;"><?php echo format_price($subtotal); ?></span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Shipping</span>
                            <span style="font-weight: 500;">
                                <?php if ($shipping_cost == 0): ?>
                                    <span style="color: var(--success-color);">FREE</span>
                                <?php else: ?>
                                    <?php echo format_price($shipping_cost); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div style="border-top: 2px solid var(--border-color); padding-top: 1rem; display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: 700;">
                            <span style="color: var(--text-color);">Total</span>
                            <span style="color: var(--primary-color);"><?php echo format_price($total); ?></span>
                        </div>
                    </div>
                    
                    <button type="submit" name="place_order" class="btn btn-primary btn-block btn-large">
                        Place Order
                    </button>
                    
                    <a href="cart.php" class="btn btn-outline btn-block" style="margin-top: 1rem;">
                        Back to Cart
                    </a>
                    
                    <!-- Payment Security Notice -->
                    <div style="margin-top: 1.5rem; padding: 1rem; background-color: rgba(37, 99, 235, 0.05); border-radius: 0.375rem; border-left: 4px solid var(--primary-color);">
                        <div style="font-size: 0.875rem; color: var(--text-muted);">
                            <strong>Secure Payment</strong><br>
                            Your payment is processed securely through Marasoft Pay. We don't store your payment information.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <?php endif; ?>
</div>

<script>
// Payment method selection styling
document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        // Remove active styling from all labels
        document.querySelectorAll('label:has(input[name="payment_method"])').forEach(label => {
            label.style.borderColor = 'var(--border-color)';
            label.style.backgroundColor = 'transparent';
        });
        
        // Add active styling to selected label
        if (this.checked) {
            this.closest('label').style.borderColor = 'var(--primary-color)';
            this.closest('label').style.backgroundColor = 'rgba(37, 99, 235, 0.05)';
        }
    });
});

// Form validation
document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
    const isValid = validateForm('checkoutForm', {
        shipping_name: {
            required: true,
            requiredMessage: 'Full name is required'
        },
        shipping_email: {
            required: true,
            email: true,
            requiredMessage: 'Email address is required',
            emailMessage: 'Please enter a valid email address'
        },
        shipping_phone: {
            required: true,
            requiredMessage: 'Phone number is required'
        },
        shipping_address: {
            required: true,
            requiredMessage: 'Shipping address is required'
        },
        shipping_city: {
            required: true,
            requiredMessage: 'City is required'
        },
        shipping_state: {
            required: true,
            requiredMessage: 'Please select a state'
        }
    });
    
    // Check payment method
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
    if (!paymentMethod) {
        showNotification('Please select a payment method', 'error');
        e.preventDefault();
        return false;
    }
    
    if (!isValid) {
        e.preventDefault();
        return false;
    }
    
    // Show loading state
    const submitBtn = document.querySelector('button[name="place_order"]');
    if (submitBtn) {
        submitBtn.textContent = paymentMethod.value === 'cod' ? 'Processing Order...' : 'Redirecting to Payment...';
        submitBtn.disabled = true;
    }
});
</script>

<!-- Responsive styles -->
<!-- <style>
@media (max-width: 768px) {
    .container > form > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    
    .order-summary {
        order: -1;
    }
    
    .checkout-form > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style> -->

<?php include '../includes/footer.php'; ?>