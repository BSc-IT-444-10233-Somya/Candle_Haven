<?php
require_once 'includes/config.php';

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Check for registration success message
if(isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success = 'Registration successful! Please login with your credentials.';
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $identifier = mysqli_real_escape_string($conn, trim($_POST['identifier'] ?? ''));
    $password = $_POST['password'] ?? '';
    
    // Validation
    if(empty($identifier)) {
        $error = "Email or username is required.";
    } elseif(empty($password)) {
        $error = "Password is required.";
    } elseif(filter_var($identifier, FILTER_VALIDATE_EMAIL) && !preg_match('/@gmail\.com$/', strtolower($identifier))) {
        $error = "Only Gmail email addresses are accepted.";
    } else {
        // Query users table
        $sql = "SELECT id, username, email, password, first_name, last_name FROM users WHERE email = '$identifier' OR username = '$identifier' LIMIT 1";
        $result = mysqli_query($conn, $sql);
        
        if($result && mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            // Verify password
            if(password_verify($password, $user['password'])) {
                // Regenerate session ID for security
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['user_role'] = 'customer';
                
                // Handle remember me
                $remember = isset($_POST['remember']) && $_POST['remember'] == '1';
                if($remember) {
                    $lifetime = 30 * 24 * 60 * 60; // 30 days
                    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                    setcookie(session_name(), session_id(), [
                        'expires' => time() + $lifetime,
                        'path' => '/',
                        'secure' => $secure,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }
                
                // Redirect to next page or dashboard
                $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
                header('Location: ' . $redirect);
                exit();
            } else {
                $error = "Invalid email/username or password. Please try again.";
            }
        } else {
            $error = "No account found with that email or username.";
        }
    }
}

$page_title = "Login";
include 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h2>Customer Login</h2>
        
        <?php if(!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="identifier">Gmail or Username *</label>
                <input type="text" id="identifier" name="identifier" required autofocus
                       placeholder="your-email@gmail.com or username"
                       value="<?php echo isset($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : ''; ?>">
                <small>Email must be a Gmail address (@gmail.com)</small>
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
                <label style="display:flex;align-items:center;gap:8px;margin:0;">
                    <input type="checkbox" name="remember" value="1">
                    Remember me
                </label>
                <a href="forgot-password.php" style="color: #d9534f; margin-left: auto; text-decoration: none;">Forgot Password?</a>
            </div>
            
            <button type="submit" class="btn" style="width: 100%;">Login</button>
        </form>
        
        <p style="text-align: center; margin-top: 20px;">
            Don't have an account? <a href="register.php">Register here</a>
        </p>
        
        <p style="text-align: center; margin-top: 10px; font-size:0.9rem;">
            <a href="admin/login.php" style="color: #666;">Admin Login</a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
