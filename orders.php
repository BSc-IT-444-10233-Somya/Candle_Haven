<?php
require_once 'includes/config.php';
$page_title = 'My Orders';
include 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('orders.php'));
    exit;
}

$user_id = intval($_SESSION['user_id']);
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action === 'view' && $id > 0) {
    // Fetch order ensuring it belongs to current user
    $sql = "SELECT o.*, u.first_name, u.last_name, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ? AND o.user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$order) {
        echo '<div class="container"><p class="alert alert-error">Order not found.</p></div>';
        include 'includes/footer.php';
        exit;
    }

    // Fetch items
    $items_sql = "SELECT oi.*, p.name, p.image_url FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?";
    $items_stmt = mysqli_prepare($conn, $items_sql);
    mysqli_stmt_bind_param($items_stmt, 'i', $id);
    mysqli_stmt_execute($items_stmt);
    $items_res = mysqli_stmt_get_result($items_stmt);

    ?>
    <div class="container">
        <h2>Order #<?php echo htmlspecialchars($order['order_number'] ?: $order['id']); ?></h2>
        <p><strong>Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></p>
        <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($order['status'])); ?></p>
        <h3>Items</h3>
        <div class="admin-table-container">
        <table class="admin-table order-items-table">
            <thead>
                <tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th></tr>
            </thead>
            <tbody>
                <?php $subtotal = 0; while($item = mysqli_fetch_assoc($items_res)): 
                    $price = isset($item['product_price']) ? $item['product_price'] : ($item['price'] ?? 0);
                    $qty = intval($item['quantity'] ?? 0);
                    $total = $price * $qty;
                    $subtotal += $total;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name'] ?? ''); ?></td>
                    <td style="text-align:right"><?php echo format_price($price); ?></td>
                    <td style="text-align:center"><?php echo $qty; ?></td>
                    <td style="text-align:right"><?php echo format_price($total); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="3" class="text-right"><strong>Subtotal</strong></td><td><?php echo format_price($subtotal); ?></td></tr>
                <tr><td colspan="3" class="text-right"><strong>Total</strong></td><td><?php echo format_price($order['total_amount']); ?></td></tr>
            </tfoot>
        </table>
        </div>
        <p>
            <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
            <a href="download-receipt.php?id=<?php echo $order['id']; ?>" class="btn" style="margin-left:12px;">Download Receipt</a>
        </p>
    </div>
    <?php
    include 'includes/footer.php';
    exit;
}

// List orders for user
$sql = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
?>
<div class="container">
    <h2>My Orders</h2>
    <?php if(mysqli_num_rows($res) == 0): ?>
        <p>You have no orders yet.</p>
    <?php else: ?>
        <div class="admin-table-container">
        <table class="admin-table orders-table">
            <thead>
                <tr><th>Order #</th><th>Date</th><th>Amount</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($res)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['order_number'] ?: $row['id']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        <td><?php echo format_price($row['total_amount']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($row['status'])); ?></td>
                        <td><a href="<?php echo site_path('orders.php') . '?action=view&id=' . $row['id']; ?>" class="btn-small"><i class="fas fa-eye"></i> View</a></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
