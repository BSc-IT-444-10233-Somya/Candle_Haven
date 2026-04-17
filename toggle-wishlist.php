<?php
require_once 'includes/config.php';
header('Content-Type: application/json');

// Simple error handler to return JSON on fatal PHP errors if possible
set_error_handler(function($errno, $errstr, $errfile, $errline){
    // Convert warnings/notices to JSON response
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "PHP error: $errstr in $errfile:$errline"]);
    exit;
});

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product id']);
    exit;
}

// Check if already in wishlist using mysqli_stmt_store_result
$check_sql = "SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
if (!$check_stmt) {
    echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
    exit;
}
mysqli_stmt_bind_param($check_stmt, 'ii', $user_id, $product_id);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);
$exists = mysqli_stmt_num_rows($check_stmt) > 0;
mysqli_stmt_close($check_stmt);

if ($exists) {
    // Remove from wishlist
    $delete_sql = "DELETE FROM wishlist WHERE user_id = ? AND product_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    if (!$delete_stmt) {
        echo json_encode(['success' => false, 'message' => 'DB prepare failed (delete)']);
        exit;
    }
    mysqli_stmt_bind_param($delete_stmt, 'ii', $user_id, $product_id);
    $ok = mysqli_stmt_execute($delete_stmt);
    mysqli_stmt_close($delete_stmt);

    if ($ok) {
        echo json_encode(['success' => true, 'added' => false]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove from wishlist']);
    }
} else {
    // Add to wishlist
    $insert_sql = "INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    if (!$insert_stmt) {
        echo json_encode(['success' => false, 'message' => 'DB prepare failed (insert)']);
        exit;
    }
    mysqli_stmt_bind_param($insert_stmt, 'ii', $user_id, $product_id);
    $ok = mysqli_stmt_execute($insert_stmt);
    mysqli_stmt_close($insert_stmt);

    if ($ok) {
        echo json_encode(['success' => true, 'added' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add to wishlist']);
    }
}

// Restore previous error handler
restore_error_handler();
?>