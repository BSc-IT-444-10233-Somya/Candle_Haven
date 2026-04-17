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
$show_success = false;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = trim($_POST['token'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if(empty($token)) {
        $error = 'Invalid reset request. Please start over.';
    } else {
        // Reset password
        $result = reset_user_password($token, $new_password, $confirm_password);
        
        if($result['success']) {
            $success = $result['message'];
            $show_success = true;
            
            // Clear session data
            unset($_SESSION['password_reset_token']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_email']);
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - Candle Haven</title>
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
        
        .success-message {
            text-align: center;
            padding: 40px 20px;
        }
        
        .success-message .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .success-message h2 {
            color: #28a745;
            margin: 15px 0;
            font-size: 24px;
        }
        
        .success-message p {
            color: #666;
            margin: 10px 0;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background-color: #8B6F47;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            width: auto;
            margin-top: 20px;
        }
        
        .btn:hover {
            background-color: #725d3d;
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
        
        .step.completed::before {
            content: "✓";
            background-color: #28a745;
            border-color: #28a745;
            color: white;
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
        
        <?php if($show_success): ?>
            <div class="step-indicator">
                <div class="step completed" data-step="1">Request</div>
                <div class="step completed" data-step="2">Verify OTP</div>
                <div class="step completed" data-step="3">Reset</div>
            </div>
            
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
            
            <div class="success-message">
                <div class="icon">🎉</div>
                <h2>Password Reset Successfully!</h2>
                <p>Your password has been changed. You can now login with your new password.</p>
                <p style="font-size: 12px; color: #999; margin-top: 20px;">
                    An email confirmation has been sent to your registered email address.
                </p>
                <a href="login.php" class="btn">Go to Login</a>
            </div>
            
        <?php elseif(!empty($error)): ?>
            <div class="alert alert-error">
                ✗ <?php echo htmlspecialchars($error); ?>
            </div>
            
            <div style="text-align: center; padding: 20px;">
                <p style="color: #666; margin-bottom: 20px;">
                    Your reset session may have expired. Please start over.
                </p>
                <a href="forgot-password.php" class="btn">Request New OTP</a>
            </div>
            
        <?php else: ?>
            <div class="step-indicator">
                <div class="step completed" data-step="1">Request</div>
                <div class="step completed" data-step="2">Verify OTP</div>
                <div class="step active" data-step="3">Reset</div>
            </div>
            
            <p style="color: #666; margin-bottom: 20px;">
                Create a strong new password for your account.
            </p>
            
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_POST['token'] ?? ''); ?>">
                
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
                
                <div style="margin: 20px 0; padding: 12px; background-color: #f9f9f9; border-radius: 4px; font-size: 13px; color: #666;">
                    <strong>🔐 Password Requirements:</strong>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li>Minimum 6 characters long</li>
                        <li>Use a mix of uppercase and lowercase letters</li>
                        <li>Include numbers or special characters</li>
                        <li>Avoid using personal information</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn" style="width: 100;">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
    
    <?php require_once 'includes/footer.php'; ?>
    
    <script>
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
