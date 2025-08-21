<?php
$page_title = "Sign Up";
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
        // Get and sanitize input
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($full_name)) {
            $errors[] = "Full name is required.";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!validate_email($email)) {
            $errors[] = "Please enter a valid email address.";
        }
        
        if (empty($username)) {
            $errors[] = "Username is required.";
        } elseif (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters long.";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = "Username can only contain letters, numbers, and underscores.";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
        
        // Check if email or username already exists
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->bind_param("ss", $email, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $existing_user = $result->fetch_assoc();
                $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
                $stmt->bind_param("i", $existing_user['id']);
                $stmt->execute();
                $user_data = $stmt->get_result()->fetch_assoc();
                
                if ($user_data['email'] === $email) {
                    $errors[] = "An account with this email already exists.";
                }
                if ($user_data['username'] === $username) {
                    $errors[] = "This username is already taken.";
                }
            }
        }
        
        // Create account if no errors
        if (empty($errors)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, username, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $email, $username, $password_hash);
            
            if ($stmt->execute()) {
                $success_message = "Account created successfully! You can now log in.";
                
                // Optional: Auto-login after registration
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['full_name'] = $full_name;
                
                // Redirect after successful registration
                $redirect_url = '../index.php';
                if (isset($_GET['redirect'])) {
                    $redirect_url = '../' . ltrim($_GET['redirect'], '/');
                }
                
                header("Location: $redirect_url");
                exit();
            } else {
                $errors[] = "Registration failed. Please try again.";
                log_error("Registration failed for email: $email - " . $conn->error);
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
            <h2>Create Your Account</h2>
            <p>Join <?php echo SITE_NAME; ?> and start shopping!</p>
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

        <form method="POST" id="registerForm" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="form-group">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" 
                       id="full_name" 
                       name="full_name" 
                       class="form-input" 
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                       required>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       class="form-input" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       required>
            </div>

            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       class="form-input" 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       required
                       minlength="3"
                       pattern="[a-zA-Z0-9_]+">
                <small class="form-hint">
                    Only letters, numbers, and underscores allowed
                </small>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="password-field">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input" 
                           required
                           minlength="6">
                    <button type="button" 
                            class="password-toggle"
                            onclick="togglePassword('password')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
                <div class="password-strength" id="passwordStrength"></div>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="password-field">
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="form-input" 
                           required>
                    <button type="button" 
                            class="password-toggle"
                            onclick="togglePassword('confirm_password')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" required class="checkbox-input">
                    <span class="checkbox-text">
                        I agree to the <a href="#" class="link">Terms of Service</a> 
                        and <a href="#" class="link">Privacy Policy</a>
                    </span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-large">
                Create Account
            </button>
        </form>

        <div class="auth-footer">
            <p class="auth-footer-text">Already have an account?</p>
            <a href="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" 
               class="btn btn-outline btn-block">
                Sign In
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

// Password strength indicator
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthIndicator = document.getElementById('passwordStrength');
    
    let strength = 0;
    let strengthText = '';
    let strengthColor = '';
    
    // Check password criteria
    if (password.length >= 6) strength++;
    if (password.match(/[a-z]/)) strength++;
    if (password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    
    switch (strength) {
        case 0:
        case 1:
            strengthText = 'Weak';
            strengthColor = 'var(--error-color)';
            break;
        case 2:
        case 3:
            strengthText = 'Medium';
            strengthColor = 'var(--warning-color)';
            break;
        case 4:
        case 5:
            strengthText = 'Strong';
            strengthColor = 'var(--success-color)';
            break;
    }
    
    if (password.length > 0) {
        strengthIndicator.textContent = 'Password strength: ' + strengthText;
        strengthIndicator.style.color = strengthColor;
    } else {
        strengthIndicator.textContent = '';
    }
});

// Form validation
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const isValid = validateForm('registerForm', {
        full_name: {
            required: true,
            requiredMessage: 'Full name is required'
        },
        email: {
            required: true,
            email: true,
            requiredMessage: 'Email is required',
            emailMessage: 'Please enter a valid email address'
        },
        username: {
            required: true,
            minLength: 3,
            requiredMessage: 'Username is required',
            minLengthMessage: 'Username must be at least 3 characters long'
        },
        password: {
            required: true,
            minLength: 6,
            requiredMessage: 'Password is required',
            minLengthMessage: 'Password must be at least 6 characters long'
        },
        confirm_password: {
            required: true,
            confirmPassword: 'password',
            requiredMessage: 'Please confirm your password',
            confirmPasswordMessage: 'Passwords do not match'
        }
    });
    
    if (!isValid) {
        e.preventDefault();
    }
});
</script>

<?php include '../includes/footer.php'; ?>