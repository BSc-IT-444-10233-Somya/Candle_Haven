<?php
require_once 'includes/config.php';

error_log("=== OTP DIAGNOSTIC TEST ===");

echo "<h2>OTP System Diagnostic</h2>";

// Test 1: Generate OTP
echo "<h3>Test 1: OTP Generation</h3>";
function generate_otp() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

$test_otp = generate_otp();
echo "Generated OTP: <strong>$test_otp</strong><br>";
$test_hash = hash('sha256', $test_otp);
echo "Hashed: <strong>$test_hash</strong><br>";

// Test 2: Check database connection
echo "<h3>Test 2: Database Connection</h3>";
$test_sql = "SELECT 1";
$result = mysqli_query($conn, $test_sql);
if($result) {
    echo "✓ Database connection OK<br>";
} else {
    echo "✗ Database connection FAILED: " . mysqli_error($conn) . "<br>";
}

// Test 3: Check password_resets table
echo "<h3>Test 3: Password Resets Table</h3>";
$test_sql = "SELECT COUNT(*) as count FROM password_resets";
$result = mysqli_query($conn, $test_sql);
$row = mysqli_fetch_assoc($result);
echo "Total records: " . $row['count'] . "<br>";

// Test 4: Check Gmail credentials
echo "<h3>Test 4: Gmail Configuration</h3>";
$user_email = 'somyakri2300@gmail.com';
$app_password = 'lcgi uidj thhs mkxh';
echo "Gmail Username: <strong>$user_email</strong><br>";
echo "Password loaded: " . (empty($app_password) ? "NO" : "YES") . "<br>";

// Test 5: Check PHPMailer
echo "<h3>Test 5: PHPMailer Files</h3>";
$paths = [
    __DIR__ . '/../PHPMailer/src/PHPMailer.php',
    __DIR__ . '/../PHPMailer/src/SMTP.php',
    __DIR__ . '/../PHPMailer/src/Exception.php'
];

foreach($paths as $path) {
    $exists = file_exists($path);
    echo ($exists ? "✓" : "✗") . " " . basename($path) . ": " . ($exists ? "EXISTS" : "MISSING") . "<br>";
}

// Test 6: Try a manual hash test with known email
echo "<h3>Test 6: Database Query Test</h3>";
$test_email = strtolower('test@example.com');
$test_email_escaped = mysqli_real_escape_string($conn, $test_email);
$test_sql = "SELECT * FROM password_resets WHERE LOWER(email) = LOWER('$test_email_escaped') LIMIT 1";
$result = mysqli_query($conn, $test_sql);
echo "Query executed: " . ($result ? "OK" : "FAILED") . "<br>";
if(!$result) {
    echo "Error: " . mysqli_error($conn) . "<br>";
}

error_log("=== END DIAGNOSTIC ===");
?>
<hr>
<h2>Quick OTP Test</h2>
<p>Enter your email below to request an OTP, then check your email for the code and enter it here to verify it works:</p>

<form method="POST">
    <input type="email" name="test_email" placeholder="your-email@gmail.com" required>
    <button type="submit" name="request_otp">Request OTP</button>
</form>

<?php
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_otp'])) {
    $email = strtolower(trim($_POST['test_email']));
    
    require_once 'includes/password-reset.php';
    
    $result = create_password_reset_request($email);
    
    echo "<h3>Result:</h3>";
    echo "<strong>Success: " . ($result['success'] ? "YES" : "NO") . "</strong><br>";
    echo "Message: " . $result['message'] . "<br>";
    
    if($result['success']) {
        echo "<p style='color: green;'>Check your email for the OTP code</p>";
    }
}
?>
