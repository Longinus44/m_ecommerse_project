<?php
$page_title = "Forgot Password";
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('../index.php');
}

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        $email = sanitize_input($_POST['email'] ?? '');
        
        // Validation
        if (empty($email)) {
            $errors[] = "Email address is required.";
        } elseif (!validate_email($email)) {
            $errors[] = "Please enter a valid email address.";
        }
        
        if (empty($errors)) {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Generate password reset token
                $reset_token = bin2hex(random_bytes(32));
                $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store reset token in database (you might want to create a password_resets table)
                // For now, we'll store it in the users table (add columns: reset_token, reset_expires)
                
                // Since we don't have the reset columns yet, we'll just simulate the process
                $success_message = "If an account with that email exists, we've sent password reset instructions to your email address.";
                
                // In a real implementation, you would:
                // 1. Store the reset token in database
                // 2. Send email with reset link
                // 3. Create reset password page
                
                /*
                // Example implementation (uncomment when you add reset columns to users table):
                
                $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
                $stmt->bind_param("sss", $reset_token, $reset_expires, $email);
                $stmt->execute();
                
                // Send email (you'll need to configure email settings)
                $reset_link = SITE_URL . "/auth/reset-password.php?token=" . $reset_token;
                $subject = "Password Reset - " . SITE_NAME;
                $message = "Hi " . $user['full_name'] . ",\n\n";
                $message .= "You requested a password reset. Click the link below to reset your password:\n\n";
                $message .= $reset_link . "\n\n";
                $message .= "This link will expire in 1 hour.\n\n";
                $message .= "If you didn't request this, please ignore this email.\n\n";
                $message .= "Best regards,\n" . SITE_NAME;
                
                $headers = "From: noreply@" . parse_url(SITE_URL, PHP_URL_HOST);
                mail($email, $subject, $message, $headers);
                */
                
            } else {
                // Don't reveal if email exists or not for security
                $success_message = "If an account with that email exists, we've sent password reset instructions to your email address.";
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="auth-container" style="max-width: 400px; margin: 2rem auto; padding: 2rem; background: var(--white); border-radius: 0.5rem; box-shadow: var(--shadow-lg);">
        <div class="auth-header" style="text-align: center; margin-bottom: 2rem;">
            <h2 style="color: var(--text-color); margin-bottom: 0.5rem;">Forgot Password</h2>
            <p style="color: var(--text-muted);">Enter your email address and we'll send you instructions to reset your password.</p>
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
                <p style="margin-top: 1rem; margin-bottom: 0;">
                    <a href="login.php" style="color: var(--primary-color);">Return to Sign In</a>
                </p>
            </div>
        <?php else: ?>
            <form method="POST" id="forgotPasswordForm" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-input" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="Enter your email address"
                           required>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-large">
                    Send Reset Instructions
                </button>
            </form>
        <?php endif; ?>

        <div class="auth-footer" style="text-align: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
            <p style="color: var(--text-muted); margin-bottom: 1rem;">Remember your password?</p>
            <a href="login.php" class="btn btn-outline btn-block">
                Back to Sign In
            </a>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('forgotPasswordForm')?.addEventListener('submit', function(e) {
    const isValid = validateForm('forgotPasswordForm', {
        email: {
            required: true,
            email: true,
            requiredMessage: 'Email address is required',
            emailMessage: 'Please enter a valid email address'
        }
    });
    
    if (!isValid) {
        e.preventDefault();
    }
});

// Auto-focus on email input
document.addEventListener('DOMContentLoaded', function() {
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.focus();
    }
});
</script>

<?php include '../includes/footer.php'; ?>