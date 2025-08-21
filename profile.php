<?php
$page_title = "My Profile";
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$current_user = get_current_user_info($conn);
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            // Update profile information
            $full_name = sanitize_input($_POST['full_name'] ?? '');
            $email = sanitize_input($_POST['email'] ?? '');
            
            // Validation
            if (empty($full_name)) {
                $errors[] = "Full name is required.";
            }
            
            if (empty($email)) {
                $errors[] = "Email is required.";
            } elseif (!validate_email($email)) {
                $errors[] = "Please enter a valid email address.";
            }
            
            // Check if email is already taken by another user
            if (empty($errors) && $email !== $current_user['email']) {
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->bind_param("si", $email, $current_user['id']);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $errors[] = "This email address is already taken.";
                }
            }
            
            // Update profile if no errors
            if (empty($errors)) {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $full_name, $email, $current_user['id']);
                
                if ($stmt->execute()) {
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    $success_message = "Profile updated successfully!";
                    $current_user['full_name'] = $full_name;
                    $current_user['email'] = $email;
                } else {
                    $errors[] = "Failed to update profile. Please try again.";
                }
            }
            
        } elseif ($action === 'change_password') {
            // Change password
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_new_password = $_POST['confirm_new_password'] ?? '';
            
            // Validation
            if (empty($current_password)) {
                $errors[] = "Current password is required.";
            }
            
            if (empty($new_password)) {
                $errors[] = "New password is required.";
            } elseif (strlen($new_password) < 6) {
                $errors[] = "New password must be at least 6 characters long.";
            }
            
            if ($new_password !== $confirm_new_password) {
                $errors[] = "New passwords do not match.";
            }
            
            // Verify current password
            if (empty($errors)) {
                $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->bind_param("i", $current_user['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                
                if (!password_verify($current_password, $user_data['password_hash'])) {
                    $errors[] = "Current password is incorrect.";
                }
            }
            
            // Update password if no errors
            if (empty($errors)) {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->bind_param("si", $new_password_hash, $current_user['id']);
                
                if ($stmt->execute()) {
                    $success_message = "Password changed successfully!";
                } else {
                    $errors[] = "Failed to change password. Please try again.";
                }
            }
        }
    }
}

// Get user's recent orders
$stmt = $conn->prepare("
    SELECT o.id, o.total_amount, o.status, o.created_at,
           COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $current_user['id']);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">My Profile</h1>
        <p class="page-subtitle">Manage your account settings and preferences</p>
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

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; margin-top: 2rem;">
        <!-- Profile Sidebar -->
        <div class="profile-sidebar">
            <div style="background: var(--white); padding: 2rem; border-radius: 0.5rem; box-shadow: var(--shadow); margin-bottom: 1.5rem;">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="width: 80px; height: 80px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: var(--white); font-size: 2rem; font-weight: 600;">
                        <?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?>
                    </div>
                    <h3 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($current_user['full_name']); ?></h3>
                    <p style="color: var(--text-muted); font-size: 0.875rem;">@<?php echo htmlspecialchars($current_user['username']); ?></p>
                </div>
                
                <div style="border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                    <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 0.5rem;">Member since</p>
                    <p style="font-weight: 500;"><?php echo date('F Y', strtotime($current_user['created_at'] ?? 'now')); ?></p>
                </div>
            </div>

            <!-- Quick Stats -->
            <div style="background: var(--white); padding: 1.5rem; border-radius: 0.5rem; box-shadow: var(--shadow);">
                <h4 style="margin-bottom: 1rem;">Quick Stats</h4>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--text-muted);">Total Orders</span>
                        <span style="font-weight: 600;"><?php echo count($recent_orders); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--text-muted);">Cart Items</span>
                        <span style="font-weight: 600;"><?php echo get_cart_count($conn); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="profile-content">
            <!-- Profile Information -->
            <div style="background: var(--white); padding: 2rem; border-radius: 0.5rem; box-shadow: var(--shadow); margin-bottom: 2rem;">
                <h3 style="margin-bottom: 1.5rem; color: var(--text-color);">Profile Information</h3>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" 
                               id="full_name" 
                               name="full_name" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($current_user['full_name']); ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($current_user['email']); ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" 
                               id="username" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($current_user['username']); ?>"
                               disabled
                               style="background-color: var(--background-color); cursor: not-allowed;">
                        <small style="color: var(--text-muted); font-size: 0.8125rem;">Username cannot be changed</small>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Update Profile
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div style="background: var(--white); padding: 2rem; border-radius: 0.5rem; box-shadow: var(--shadow); margin-bottom: 2rem;">
                <h3 style="margin-bottom: 1.5rem; color: var(--text-color);">Change Password</h3>
                
                <form method="POST" id="changePasswordForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" 
                               id="current_password" 
                               name="current_password" 
                               class="form-input" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               class="form-input" 
                               minlength="6"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                        <input type="password" 
                               id="confirm_new_password" 
                               name="confirm_new_password" 
                               class="form-input" 
                               required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Change Password
                    </button>
                </form>
            </div>

            <!-- Recent Orders -->
            <div style="background: var(--white); padding: 2rem; border-radius: 0.5rem; box-shadow: var(--shadow);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3 style="color: var(--text-color);">Recent Orders</h3>
                    <a href="orders.php" style="color: var(--primary-color); text-decoration: none; font-size: 0.875rem;">View All</a>
                </div>
                
                <?php if (empty($recent_orders)): ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        <p>No orders yet. <a href="index.php" style="color: var(--primary-color);">Start shopping!</a></p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($recent_orders as $order): ?>
                            <div style="border: 1px solid var(--border-color); border-radius: 0.375rem; padding: 1rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                    <span style="font-weight: 600;">Order #<?php echo $order['id']; ?></span>
                                    <span style="font-size: 0.875rem; color: var(--text-muted);">
                                        <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                    </span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="color: var(--text-muted); font-size: 0.875rem;">
                                        <?php echo $order['item_count']; ?> items • <?php echo format_price($order['total_amount']); ?>
                                    </span>
                                    <span style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.8125rem; font-weight: 500; 
                                                <?php 
                                                switch($order['status']) {
                                                    case 'pending':
                                                        echo 'background-color: #fef3c7; color: #92400e;';
                                                        break;
                                                    case 'processing':
                                                        echo 'background-color: #dbeafe; color: #1e40af;';
                                                        break;
                                                    case 'shipped':
                                                        echo 'background-color: #d1fae5; color: #065f46;';
                                                        break;
                                                    case 'delivered':
                                                        echo 'background-color: #dcfce7; color: #166534;';
                                                        break;
                                                    case 'cancelled':
                                                        echo 'background-color: #fee2e2; color: #dc2626;';
                                                        break;
                                                    default:
                                                        echo 'background-color: var(--background-color); color: var(--text-muted);';
                                                }
                                                ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation for password change
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const isValid = validateForm('changePasswordForm', {
        current_password: {
            required: true,
            requiredMessage: 'Current password is required'
        },
        new_password: {
            required: true,
            minLength: 6,
            requiredMessage: 'New password is required',
            minLengthMessage: 'New password must be at least 6 characters long'
        },
        confirm_new_password: {
            required: true,
            confirmPassword: 'new_password',
            requiredMessage: 'Please confirm your new password',
            confirmPasswordMessage: 'New passwords do not match'
        }
    });
    
    if (!isValid) {
        e.preventDefault();
    }
});
</script>

<!-- Responsive styles -->
<style>
@media (max-width: 768px) {
    .container > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    
    .profile-sidebar {
        order: 2;
    }
    
    .profile-content {
        order: 1;
    }
}
</style>

<?php include 'includes/footer.php'; ?>