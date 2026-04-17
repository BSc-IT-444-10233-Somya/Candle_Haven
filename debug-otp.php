<?php
require_once 'includes/config.php';

$email = 'somyakri2300@gmail.com';

echo "<h2>Debug: Password Resets for $email</h2>";

$sql = "SELECT id, user_id, email, otp_hash, expires_at, is_used, verified_at FROM password_resets WHERE LOWER(email) = LOWER('$email') ORDER BY created_at DESC LIMIT 5";

$result = mysqli_query($conn, $sql);

if(mysqli_num_rows($result) === 0) {
    echo "No records found!";
} else {
    echo "Found " . mysqli_num_rows($result) . " record(s)<br><br>";
    
    while($row = mysqli_fetch_assoc($result)) {
        echo "<strong>Record ID: " . $row['id'] . "</strong><br>";
        echo "User ID: " . $row['user_id'] . "<br>";
        echo "Email: " . $row['email'] . "<br>";
        echo "OTP Hash: " . $row['otp_hash'] . "<br>";
        echo "Expires: " . $row['expires_at'] . " (Current: " . date('Y-m-d H:i:s') . ")<br>";
        echo "Is Used: " . ($row['is_used'] ? 'YES' : 'NO') . "<br>";
        echo "Verified At: " . ($row['verified_at'] ?? 'NULL') . "<br>";
        echo "<hr>";
    }
}

echo "<h3>Test Hash Calculation</h3>";
$test_otp = "250542";
$test_hash = hash('sha256', $test_otp);
echo "OTP: $test_otp<br>";
echo "Hash: $test_hash<br>";
?>
