<?php
require_once 'includes/config.php';

// Clear all old password reset requests
$delete_sql = "DELETE FROM password_resets";
$result = mysqli_query($conn, $delete_sql);

if($result) {
    echo "<h2 style='color: green;'>✓ Password resets table cleared</h2>";
    echo "<p>All old password reset requests have been deleted.</p>";
    echo "<p><a href='forgot-password.php'>Click here to start fresh with forgot password</a></p>";
} else {
    echo "<h2 style='color: red;'>✗ Error clearing table</h2>";
    echo "<p>" . mysqli_error($conn) . "</p>";
}

// Verify table is empty
$check_sql = "SELECT COUNT(*) as count FROM password_resets";
$check_result = mysqli_query($conn, $check_sql);
$row = mysqli_fetch_assoc($check_result);
echo "<p>Records remaining: " . $row['count'] . "</p>";
?>
