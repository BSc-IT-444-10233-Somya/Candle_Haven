<?php
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: shop.php');
    exit;
}

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    $redirect = 'login.php?redirect=' . urlencode('product.php?id=' . ($_POST['product_id'] ?? '') . '#reviews');
    header('Location: ' . $redirect);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : null;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// Basic validation
if ($product_id <= 0 || $rating < 1 || $rating > 5 || $comment === '') {
    $params = http_build_query(['error' => 'Please provide a valid rating and review.']);
    header('Location: product.php?id=' . $product_id . '#reviews&' . $params);
    exit;
}

// Insert review (is_approved default false)
$insert_sql = "INSERT INTO reviews (product_id, user_id, rating, title, comment, is_approved) VALUES (?, ?, ?, ?, ?, 0)";
$stmt = mysqli_prepare($conn, $insert_sql);
if (!$stmt) {
    error_log('submit-review prepare failed: ' . mysqli_error($conn));
    header('Location: product.php?id=' . $product_id . '&error=Server+error#reviews');
    exit;
}
mysqli_stmt_bind_param($stmt, 'iiiss', $product_id, $user_id, $rating, $title, $comment);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    error_log('submit-review execute failed: ' . mysqli_error($conn));
    header('Location: product.php?id=' . $product_id . '&error=Unable+to+submit+review#reviews');
    exit;
}

// Recalculate product rating and review_count (only approved reviews should affect rating, but here we'll include all reviews and let admin approve separately)
$calc_sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE product_id = ? AND is_approved = 1";
$calc_stmt = mysqli_prepare($conn, $calc_sql);
if ($calc_stmt) {
    mysqli_stmt_bind_param($calc_stmt, 'i', $product_id);
    mysqli_stmt_execute($calc_stmt);
    $res = mysqli_stmt_get_result($calc_stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($calc_stmt);

    $avg = $row['avg_rating'] ?? 0;
    $count = $row['review_count'] ?? 0;

    $update_sql = "UPDATE products SET rating = ?, review_count = ? WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    if ($update_stmt) {
        mysqli_stmt_bind_param($update_stmt, 'dii', $avg, $count, $product_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
}

// Redirect back to product page with a confirmation that review was submitted (pending approval)
header('Location: product.php?id=' . $product_id . '&message=' . urlencode('Review submitted and pending approval') . '#reviews');
exit;

?>
