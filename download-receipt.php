<?php
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('orders.php'));
    exit;
}

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($order_id <= 0) {
    http_response_code(400);
    echo 'Invalid order id.';
    exit;
}

// Fetch order and ensure ownership
$sql = "SELECT o.*, u.first_name, u.last_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ? AND o.user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $order_id, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order) {
    http_response_code(404);
    echo 'Order not found.';
    exit;
}

// Fetch items
$items_sql = "SELECT oi.*, p.name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?";
$items_stmt = mysqli_prepare($conn, $items_sql);
mysqli_stmt_bind_param($items_stmt, 'i', $order_id);
mysqli_stmt_execute($items_stmt);
$items_res = mysqli_stmt_get_result($items_stmt);

$order_number = $order['order_number'] ?: 'ORD-' . $order['id'];
$filename = 'receipt-' . preg_replace('/[^A-Za-z0-9_-]/', '', $order_number) . '.html';

// Build the receipt HTML
$receipt = '<!doctype html><html><head><meta charset="utf-8"><title>Receipt ' . htmlspecialchars($order_number) . '</title>';
$receipt .= '<style>body{font-family:Arial,Helvetica,sans-serif;padding:20px;color:#222} .receipt{max-width:800px;margin:0 auto} h1{color:#6b3f1b} table{width:100%;border-collapse:collapse;margin-top:20px} th,td{padding:10px;border-bottom:1px solid #eee;text-align:left} th{background:#faf5f0;color:#6b3f1b} .totals td{border:none} .right{text-align:right} .muted{color:#666;font-size:0.95rem}</style>';
$receipt .= '</head><body><div class="receipt">';
$receipt .= '<h1>Candle Haven — Receipt</h1>';
$receipt .= '<p class="muted">Order: <strong>' . htmlspecialchars($order_number) . '</strong><br>';
$receipt .= 'Date: <strong>' . date('F j, Y, g:i a', strtotime($order['created_at'])) . '</strong><br>';
$receipt .= 'Customer: <strong>' . htmlspecialchars(trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))) . '</strong></p>';

$receipt .= '<h2>Shipping Address</h2>';
$receipt .= '<p class="muted">' . nl2br(htmlspecialchars($order['shipping_address'] ?? '')) . '</p>';

$receipt .= '<h2>Items</h2>';
$receipt .= '<table><thead><tr><th>Product</th><th style="text-align:right">Price</th><th style="text-align:center">Qty</th><th style="text-align:right">Total</th></tr></thead><tbody>';

$subtotal = 0;
while ($item = mysqli_fetch_assoc($items_res)) {
    $price = isset($item['product_price']) ? $item['product_price'] : ($item['price'] ?? 0);
    $qty = intval($item['quantity'] ?? 0);
    $total = $price * $qty;
    $subtotal += $total;
    $receipt .= '<tr>';
    $receipt .= '<td>' . htmlspecialchars($item['name'] ?? '') . '</td>';
    $receipt .= '<td style="text-align:right">' . format_price($price) . '</td>';
    $receipt .= '<td style="text-align:center">' . $qty . '</td>';
    $receipt .= '<td style="text-align:right">' . format_price($total) . '</td>';
    $receipt .= '</tr>';
}

$receipt .= '</tbody><tfoot class="totals">';
$receipt .= '<tr><td colspan="3" class="right"><strong>Subtotal</strong></td><td style="text-align:right">' . format_price($subtotal) . '</td></tr>';
$receipt .= '<tr><td colspan="3" class="right"><strong>Total</strong></td><td style="text-align:right">' . format_price($order['total_amount']) . '</td></tr>';
$receipt .= '</tfoot></table>';

$receipt .= '<p class="muted">Payment method: ' . htmlspecialchars($order['payment_method'] ?? 'N/A') . '</p>';
$receipt .= '<p class="muted">Thank you for your purchase!</p>';
$receipt .= '</div></body></html>';

// Send as downloadable HTML file
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $receipt;
exit;

?>
