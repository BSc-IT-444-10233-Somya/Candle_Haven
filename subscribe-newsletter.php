<?php
/**
 * AJAX endpoint to subscribe an email to newsletter_subscribers
 * Expects POST 'email'
 * Returns JSON { success: bool, message: string }
 */
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');

// Log for debugging
error_log('subscribe-newsletter.php called; method=' . ($_SERVER['REQUEST_METHOD'] ?? ''));

$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

// Normalize email
$email = strtolower($email);

// Prevent duplicate subscriptions
$safe_email = mysqli_real_escape_string($conn, $email);
$check_sql = "SELECT id FROM newsletter_subscribers WHERE email = '$safe_email' LIMIT 1";
$res = mysqli_query($conn, $check_sql);
if ($res && mysqli_num_rows($res) > 0) {
    echo json_encode(['success' => true, 'message' => 'You are already subscribed.']);
    exit;
}

// Attempt to include name if provided. Use safe escaping. If the table doesn't have the `name` column, fall back to email-only insert.
$safe_name = mysqli_real_escape_string($conn, $name);
if ($safe_name !== '') {
    $insert_sql = "INSERT INTO newsletter_subscribers (email, name) VALUES ('$safe_email', '$safe_name')";
    if (mysqli_query($conn, $insert_sql)) {
        echo json_encode(['success' => true, 'message' => 'Thank you for subscribing!', 'name' => $name]);
        exit;
    }
    // If insert failed, log and try fallback to email-only
    error_log('Newsletter insert with name failed: ' . mysqli_error($conn) . ' -- trying fallback.');
}

$insert_sql = "INSERT INTO newsletter_subscribers (email) VALUES ('$safe_email')";
if (mysqli_query($conn, $insert_sql)) {
    echo json_encode(['success' => true, 'message' => 'Thank you for subscribing!']);
    exit;
} else {
    error_log('Newsletter insert error: ' . mysqli_error($conn));
    echo json_encode(['success' => false, 'message' => 'Failed to subscribe. Please try again later.']);
    exit;
}
