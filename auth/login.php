<?php
$page_title = "Sign In";
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('../index.php');
}

$errors = [];
$success_message = '';

// Handle logout message
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $success_message = "You have been successfully logged out.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        // Get and sanitize input
        $login = sanitize_input($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        
        // Validation
        if (empty($login)) {
            $errors[] = "Email or username is required.";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required.";
        }
        
        // Attempt login if no validation errors
        if (empty($errors)) {
            // Check if login is email or username
            $stmt = $conn->prepare("SELECT id, full_name, username, email, password FROM users WHERE email = ? OR username = ?");
            $stmt->bind_param("ss", $login, $login);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if (password_verify($password, $user['password'])) {
                    // Successful login
                    session_regenerate_id(true); // Prevent session fixation
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    
                    // Handle "Remember Me" functionality
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                        
                        setcookie('remember_token', $token, $expiry, '/', '', false, true);
                    }
                    
                    // Update last login (optional)
                    $stmt = $conn->prepare("UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->bind_param("i", $user['id']);
                    $stmt->execute();
                    
                    // Redirect to intended page or dashboard
                    $redirect_url = '../index.php';
                    if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
                        $redirect_url = '../' . ltrim($_GET['redirect'], '/');
                    }
                    
                    redirect($redirect_url);
                } else {
                    $errors[] = "Invalid email/username or password.";
                }
            } else {
                $errors[] = "Invalid email/username or password.";
            }
        }
    }
}

include '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/styles.css">

<div class="container">
    <div class="auth-container">
        <div class="auth-header">
            <h2>Welcome Back</h2>
            <p>Sign in to your <?php echo SITE_NAME; ?> account</p>
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

        <form method="POST" id="loginForm" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="form-group">
                <label for="login" class="form-label">Email or Username</label>
                <input type="text" 
                       id="login" 
                       name="login" 
                       class="form-input" 
                       value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>"
                       placeholder="Enter your email or username"
                       required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="password-field">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input" 
                           placeholder="Enter your password"
                           required>
                    <button type="button" 
                            class="password-toggle"
                            onclick="togglePassword('password')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-group form-options">
                <label class="checkbox-label">
                    <input type="checkbox" name="remember_me" class="checkbox-input">
                    <span class="checkbox-text">Remember me</span>
                </label>
                <a href="forgot-password.php" class="forgot-password-link">
                    Forgot password?
                </a>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-large">
                Sign In
            </button>
        </form>

        <!-- Social Login Options (Optional) -->
        <div class="social-login">
            <div class="social-divider">
                <span class="social-divider-text">Or continue with</span>
                <div class="social-divider-line"></div>
            </div>
            
            <div class="social-buttons">
                <button type="button" class="btn btn-outline social-btn" onclick="loginWithGoogle()">
                    <svg width="16" height="16" viewBox="0 0 24 24">
                        <path fill="#4285f4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34a853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#fbbc05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#ea4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Google
                </button>
                
                <!-- <button type="button" class="btn btn-outline social-btn" onclick="loginWithFacebook()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#1877f2">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    Facebook
                </button> -->
            </div>
        </div>

        <div class="auth-footer">
            <p class="auth-footer-text">Don't have an account?</p>
            <a href="register.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" 
               class="btn btn-outline btn-block">
                Create Account
            </a>
        </div>
    </div>
</div>

<script>
// Password visibility toggle
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
    field.setAttribute('type', type);
}

// Social login functions (placeholders)
function loginWithGoogle() {
    showNotification('Google login coming soon!', 'info');
    // Implement Google OAuth integration here
}

function loginWithFacebook() {
    showNotification('Facebook login coming soon!', 'info');
    // Implement Facebook OAuth integration here
}

// Form validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const isValid = validateForm('loginForm', {
        login: {
            required: true,
            requiredMessage: 'Email or username is required'
        },
        password: {
            required: true,
            requiredMessage: 'Password is required'
        }
    });
    
    if (!isValid) {
        e.preventDefault();
    }
});

// Auto-focus on first input
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('login').focus();
});
</script>

<?php include '../includes/footer.php'; ?>