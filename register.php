<?php
require_once 'includes/config.php';

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$errors = [];
$success = false;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    
    // Validation
    if(empty($username)) {
        $errors[] = "Username is required.";
    } elseif(strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters.";
    }
    
    if(empty($first_name)) {
        $errors[] = "First name is required.";
    } elseif(!preg_match("/^[a-zA-Z\s]+$/", $first_name)) {
        $errors[] = "First name should contain only alphabets and spaces.";
    }
    
    if(empty($last_name)) {
        $errors[] = "Last name is required.";
    } elseif(!preg_match("/^[a-zA-Z\s]+$/", $last_name)) {
        $errors[] = "Last name should contain only alphabets and spaces.";
    }
    
    if(empty($email)) {
        $errors[] = "Email is required.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif(!preg_match('/@gmail\.com$/', strtolower($email))) {
        $errors[] = "Only Gmail email addresses are allowed.";
    }
    
    if(empty($password)) {
        $errors[] = "Password is required.";
    } elseif(strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    } elseif($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if(!empty($phone)) {
        $phone_digits = preg_replace('/[^0-9]/', '', $phone);
        if(strlen($phone_digits) !== 10) {
            $errors[] = "Phone number must be exactly 10 digits.";
        }
    }
    
    // Check if username or email already exists
    if(empty($errors)) {
        $sql = "SELECT id FROM users WHERE username = '$username' OR email = '$email'";
        $result = mysqli_query($conn, $sql);
        
        if(mysqli_num_rows($result) > 0) {
            $errors[] = "Username or email already exists.";
        }
    }
    
    // If no errors, create user
    if(empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, email, password, first_name, last_name, created_at) 
                VALUES ('$username', '$email', '$hashed_password', '$first_name', '$last_name', NOW())";
        
        if(mysqli_query($conn, $sql)) {
            // Do NOT auto-login. Ask the user to login after registering.
            header('Location: login.php?registered=1');
            exit();
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}

$page_title = "Register";
include 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h2>Create an Account</h2>
        
        <?php if($success): ?>
            <div class="alert alert-success">
                <h3>Registration Successful!</h3>
                <p>Your account has been created. Please <a href="login.php">login</a> to continue.</p>
            </div>
        <?php else: ?>
            <?php if(!empty($errors)): ?>
                <div class="alert alert-error">
                    <h3>Please fix the following errors:</h3>
                    <ul>
                        <?php foreach($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" required
                               pattern="[a-zA-Z\s]+" title="First name should contain only alphabets and spaces"
                               value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                        <small>Only alphabets and spaces allowed</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required
                               pattern="[a-zA-Z\s]+" title="Last name should contain only alphabets and spaces"
                               value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                        <small>Only alphabets and spaces allowed</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" required
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    <small>Must be at least 3 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="email">Gmail Address *</label>
                    <input type="email" id="email" name="email" required placeholder="example@gmail.com"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <small>Only Gmail addresses (@gmail.com) are accepted</small>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" placeholder="10-digit phone number" 
                           pattern="[0-9\s\-\(\)]{10,}" maxlength="15" title="Phone number must be 10 digits"
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                    <small>Optional - 10 digits required if provided (e.g., 1234567890)</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required>
                        <small>Must be at least 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="terms" required>
                        I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a> *
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="newsletter">
                        Subscribe to our newsletter for updates and special offers
                    </label>
                </div>
                
                <button type="submit" class="btn" style="width: 100%;">Create Account</button>
            </form>
            
            <p style="text-align: center; margin-top: 20px;">
                Already have an account? <a href="login.php">Login here</a>
            </p>
            
            <div class="benefits">
                <h3>Benefits of Creating an Account:</h3>
                <ul>
                    <li><i class="fas fa-check"></i> Faster checkout process</li>
                    <li><i class="fas fa-check"></i> Track your orders</li>
                    <li><i class="fas fa-check"></i> Save your shipping addresses</li>
                    <li><i class="fas fa-check"></i> Exclusive member discounts</li>
                    <li><i class="fas fa-check"></i> Wishlist functionality</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Form validation
    document.querySelector('form')?.addEventListener('submit', function(e) {
        const firstName = document.getElementById('first_name').value.trim();
        const lastName = document.getElementById('last_name').value.trim();
        const phone = document.getElementById('phone').value.trim();
        
        // Validate first name - only alphabets and spaces
        if (!firstName || !/^[a-zA-Z\s]+$/.test(firstName)) {
            e.preventDefault();
            alert('First name should contain only alphabets and spaces!');
            return;
        }
        
        // Validate last name - only alphabets and spaces
        if (!lastName || !/^[a-zA-Z\s]+$/.test(lastName)) {
            e.preventDefault();
            alert('Last name should contain only alphabets and spaces!');
            return;
        }
        
        // Validate phone if provided - must be 10 digits
        if (phone) {
            const phoneDigits = phone.replace(/[^0-9]/g, '');
            if (phoneDigits.length !== 10) {
                e.preventDefault();
                alert('Phone number must be exactly 10 digits!');
                return;
            }
        }
    });
</script>

<?php include 'includes/footer.php'; ?>