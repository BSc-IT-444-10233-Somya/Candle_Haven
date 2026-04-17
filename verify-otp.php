<?php
require_once 'includes/config.php';
require_once 'includes/password-reset.php';

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';
$email = '';
$show_password_form = false;
$verification_token = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $otp = trim($_POST['otp'] ?? '');
    
    // Validate inputs
    if(empty($email) || empty($otp)) {
        $error = 'Email and OTP are required.';
    } elseif(strlen($otp) !== 6 || !ctype_digit($otp)) {
        $error = 'OTP must be a 6-digit code.';
    } else {
        // Debug logging
        error_log("OTP Verification Attempt:");
        error_log("  Email received: '" . $email . "'");
        error_log("  OTP received: '" . $otp . "'");
        error_log("  Email length: " . strlen($email));
        error_log("  OTP length: " . strlen($otp));
        
        // Verify OTP
        $result = verify_password_reset_otp($email, $otp);
        
        error_log("  Verification result: " . ($result['success'] ? 'SUCCESS' : 'FAILED'));
        error_log("  Message: " . $result['message']);
        
        if($result['success']) {
            $success = $result['message'];
            $verification_token = $result['verification_token'];
            $show_password_form = true;
            
            // Store verification details in session for next step
            $_SESSION['password_reset_token'] = $verification_token;
            $_SESSION['reset_user_id'] = $result['user_id'];
            $_SESSION['reset_email'] = $email;
        } else {
            $error = $result['message'];
        }
    }
}

// Check if coming from forgot-password.php
if(empty($email) && isset($_POST)) {
    $email = trim($_POST['email'] ?? '');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Candle Haven</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .auth-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-header h1 {
            color: #8B6F47;
            font-size: 28px;
            margin: 0 0 10px 0;
        }
        
        .auth-header p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #8B6F47;
            box-shadow: 0 0 0 3px rgba(139, 111, 71, 0.1);
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background-color: #8B6F47;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .btn:hover {
            background-color: #725d3d;
        }
        
        .btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        .auth-footer {
            text-align: center;
            margin-top: 20px;
        }
        
        .auth-footer a {
            color: #8B6F47;
            text-decoration: none;
        }
        
        .auth-footer a:hover {
            text-decoration: underline;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            font-size: 12px;
        }
        
        .step {
            flex: 1;
            text-align: center;
            color: #999;
        }
        
        .step.active {
            color: #8B6F47;
            font-weight: 600;
        }
        
        .step::before {
            content: attr(data-step);
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
            border: 2px solid #ddd;
            border-radius: 50%;
            margin-bottom: 8px;
        }
        
        .step.active::before {
            background-color: #8B6F47;
            color: white;
            border-color: #8B6F47;
        }
        
        .step.completed::before {
            content: "✓";
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .otp-info {
            background-color: #f0e6d2;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .password-fields {
            display: grid;
            gap: 15px;
        }
        
        .password-eye {
            position: relative;
        }
        
        .password-eye input {
            width: 100%;
            padding-right: 40px;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #8B6F47;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/header.php'; ?>
    
    <div class="auth-container">
        <div class="auth-header">
            <h1>🕯️ Reset Password</h1>
            <p>Secure your account with a new password</p>
        </div>
        
        <?php if(!empty($error)): ?>
            <div class="alert alert-error">
                ✗ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if($show_password_form): ?>
            <div class="alert alert-success">
                ✓ <?php echo htmlspecialchars($success); ?>
            </div>
            
            <div class="step-indicator">
                <div class="step completed" data-step="1">Request</div>
                <div class="step completed" data-step="2">Verify OTP</div>
                <div class="step active" data-step="3">Reset</div>
            </div>
            
            <form method="POST" action="reset-password.php">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($verification_token); ?>">
                
                <div class="otp-info">
                    <strong>✓ OTP Verified Successfully!</strong><br>
                    Now create your new password below.
                </div>
                
                <div class="password-fields">
                    <div class="form-group password-eye">
                        <label for="new_password">New Password</label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            placeholder="Enter new password"
                            minlength="6"
                            required
                            autofocus
                        >
                        <span class="password-toggle" onclick="togglePassword('new_password')">👁</span>
                    </div>
                    
                    <div class="form-group password-eye">
                        <label for="confirm_password">Confirm Password</label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="Confirm password"
                            minlength="6"
                            required
                        >
                        <span class="password-toggle" onclick="togglePassword('confirm_password')">👁</span>
                    </div>
                </div>
                
                <div style="margin: 15px 0; padding: 12px; background-color: #f9f9f9; border-radius: 4px; font-size: 13px; color: #666;">
                    <strong>Password Requirements:</strong>
                    <ul style="margin: 5px 0; padding-left: 20px;">
                        <li>At least 6 characters long</li>
                        <li>Mix of letters and numbers recommended</li>
                        <li>Avoid using personal information</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn">Reset Password</button>
            </form>
            
        <?php else: ?>
            <div class="step-indicator">
                <div class="step completed" data-step="1">Request</div>
                <div class="step active" data-step="2">Verify OTP</div>
                <div class="step" data-step="3">Reset</div>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="your@email.com"
                        value="<?php echo htmlspecialchars($email); ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="otp">6-Digit OTP Code</label>
                    <input 
                        type="text" 
                        id="otp" 
                        name="otp" 
                        placeholder="000000" 
                        maxlength="6"
                        pattern="[0-9]{6}"
                        inputmode="numeric"
                        required
                        autofocus
                    >
                </div>
                
                <div class="otp-info">
                    <strong>📧 Check your email</strong><br>
                    Enter the 6-digit OTP code we sent to your email address.<br>
                    The code is valid for 15 minutes.
                </div>
                
                <button type="submit" class="btn">Verify OTP</button>
            </form>
            
            <div class="auth-footer" style="margin-top: 20px;">
                <p style="margin: 10px 0; color: #666; font-size: 14px;">Didn't receive the code?</p>
                <a href="forgot-password.php" style="color: #8B6F47; text-decoration: none;">← Request New OTP</a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php require_once 'includes/footer.php'; ?>
    
    <script>
        // Auto-format OTP input
        const otpInput = document.getElementById('otp');
        if(otpInput) {
            otpInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
        }
        
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggle = event.target;
            
            if(field.type === 'password') {
                field.type = 'text';
                toggle.textContent = '🔒';
            } else {
                field.type = 'password';
                toggle.textContent = '👁';
            }
        }
    </script>
</body>
</html>
