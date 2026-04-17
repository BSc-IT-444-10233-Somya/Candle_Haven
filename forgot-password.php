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
$email_masked = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['step']) && $_POST['step'] == '1') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        
        // Validate email
        if(empty($email)) {
            $error = 'Email address is required.';
        } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $result = create_password_reset_request($email);
            if($result['success'] && $result['found']) {
                $success = $result['message'];
                $email_masked = $result['email'] ?? $email;
                $_SESSION['reset_email'] = $email;
            } elseif($result['success']) {
                // For security, don't reveal if email exists
                $success = $result['message'];
                $_SESSION['reset_email'] = $email;
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Check if we're in OTP verification step
$show_otp_form = isset($_SESSION['reset_email']) && !empty($success);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Candle Haven</title>
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
        
        .alert-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
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
        
        .btn-link {
            background: none;
            color: #8B6F47;
            padding: 0;
            text-decoration: underline;
            cursor: pointer;
        }
        
        .btn-link:hover {
            color: #725d3d;
            background: none;
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
        
        .otp-info {
            background-color: #f0e6d2;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .email-display {
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-weight: 600;
            color: #333;
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
        
        <?php if(!empty($success) && $show_otp_form): ?>
            <div class="alert alert-success">
                ✓ <?php echo htmlspecialchars($success); ?>
            </div>
            
            <div class="step-indicator">
                <div class="step active" data-step="1">Request</div>
                <div class="step active" data-step="2">Verify OTP</div>
                <div class="step" data-step="3">Reset</div>
            </div>
            
            <div class="otp-info">
                <strong>📧 Check your email</strong><br>
                We've sent a 6-digit OTP code to <strong><?php echo htmlspecialchars($email_masked); ?></strong><br>
                The code is valid for <strong>15 minutes</strong>.
            </div>
            
            <form method="POST" action="verify-otp.php">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_SESSION['reset_email']); ?>">
                
                <div class="form-group">
                    <label for="otp">Enter 6-Digit OTP Code</label>
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
                
                <button type="submit" class="btn">Verify OTP</button>
            </form>
            
            <div class="auth-footer" style="margin-top: 20px;">
                <p style="margin: 10px 0; color: #666; font-size: 14px;">Didn't receive the code?</p>
                <a href="forgot-password.php" style="color: #8B6F47; text-decoration: none;">← Request New OTP</a>
            </div>
        <?php else: ?>
            <div class="step-indicator">
                <div class="step active" data-step="1">Request</div>
                <div class="step" data-step="2">Verify OTP</div>
                <div class="step" data-step="3">Reset</div>
            </div>
            
            <form method="POST">
                <input type="hidden" name="step" value="1">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="your@email.com"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                        autofocus
                    >
                </div>
                
                <p style="font-size: 13px; color: #666; margin: 15px 0;">
                    ℹ️ Enter your email address and we'll send you an OTP code to reset your password.
                </p>
                
                <button type="submit" class="btn">Send OTP Code</button>
            </form>
            
            <div class="auth-footer">
                <p style="margin: 15px 0; color: #666; font-size: 14px;">Remember your password?</p>
                <a href="login.php">← Back to Login</a>
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
    </script>
</body>
</html>
