<?php
require_once '../includes/config.php';

// Check if user is admin
if(!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

$page_title = "Manage Orders";
include 'includes/admin-header.php';

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Update order status
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $sql = "UPDATE orders SET status = '$status' WHERE id = $order_id";
    if(mysqli_query($conn, $sql)) {
        $success = "Order status updated successfully!";
    } else {
        $error = "Error updating order status: " . mysqli_error($conn);
    }
}

// Fetch orders
$sql = "SELECT o.*, u.first_name, u.last_name, u.email 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<div class="admin-container">
    <h1>Manage Orders</h1>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if($action == 'view' && $id > 0): 
        // Fetch order details
        $order_sql = "SELECT o.*, u.first_name, u.last_name, u.email, u.phone 
                     FROM orders o 
                     LEFT JOIN users u ON o.user_id = u.id 
                     WHERE o.id = $id";
        $order_result = mysqli_query($conn, $order_sql);
        $order = mysqli_fetch_assoc($order_result);
        
        // Fetch order items
        $items_sql = "SELECT oi.*, p.name, p.image_url 
                     FROM order_items oi 
                     LEFT JOIN products p ON oi.product_id = p.id 
                     WHERE oi.order_id = $id";
        $items_result = mysqli_query($conn, $items_sql);
    ?>
        <div class="order-details">
            <div class="order-header">
                <h2>Order #<?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></h2>
                <div class="order-meta">
                    <p><strong>Order Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></p>
                    <p><strong>Customer:</strong> <?php echo htmlspecialchars((($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email'] ?? ''); ?></p>
                    <p><strong>Phone:</strong> <?php echo $order['phone'] ?: 'Not provided'; ?></p>
                </div>
            </div>
            
            <div class="order-content">
                <div class="order-info">
                    <h3>Order Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <h4>Shipping Address</h4>
                            <p><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                        </div>
                        
                        <div class="info-item">
                            <h4>Payment Method</h4>
                            <p><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></p>
                        </div>
                        
                        <div class="info-item">
                            <h4>Order Status</h4>
                            <form method="POST" class="status-form">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                        </div>
                        
                        <div class="info-item">
                            <h4>Order Total</h4>
                            <p class="order-total"><?php echo format_price($order['total_amount']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="order-items">
                    <h3>Order Items</h3>
                    <table class="order-items-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $subtotal = 0;
                            while($item = mysqli_fetch_assoc($items_result)):
                                if (isset($item['price'])) {
                                    $item_price = $item['price'];
                                } elseif (isset($item['unit_price'])) {
                                    $item_price = $item['unit_price'];
                                } else {
                                    $item_price = 0;
                                }

                                $item_qty = isset($item['quantity']) ? (int)$item['quantity'] : 0;
                                $item_total = $item_price * $item_qty;
                                $subtotal += $item_total;
                            ?>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <?php if(!empty($item['image_url'])): ?>
                                            <img src="../<?php echo $item['image_url']; ?>" alt="<?php echo htmlspecialchars($item['name'] ?? ''); ?>">
                                        <?php endif; ?>
                                        <div>
                                            <h4><?php echo htmlspecialchars($item['name'] ?? ''); ?></h4>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo format_price($item_price); ?></td>
                                <td><?php echo $item_qty; ?></td>
                                <td><?php echo format_price($item_total); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-right"><strong>Subtotal:</strong></td>
                                <td><strong><?php echo format_price($subtotal); ?></strong></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-right"><strong>Shipping:</strong></td>
                                <td><strong><?php echo format_price($order['total_amount'] - $subtotal); ?></strong></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-right"><strong>Total:</strong></td>
                                <td><strong><?php echo format_price($order['total_amount']); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <div class="order-actions">
                <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
                <button class="btn" onclick="window.print()"><i class="fas fa-print"></i> Print Order</button>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): 
                        while($row = mysqli_fetch_assoc($result)):
                    ?>
                    <tr>
                        <td>#<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars((($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        <td><?php echo format_price($row['total_amount']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $row['status']; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $row['payment_method'])); ?></td>
                        <td class="actions">
                            <a href="<?php echo site_path('admin/orders.php') . '?action=view&id=' . $row['id']; ?>" class="btn-small">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <form method="POST" action="" style="display:inline-block;margin:0 0 0 8px;vertical-align:middle;">
                                <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="update_status" value="1">
                                <select name="status" onchange="this.form.submit()" class="small-select">
                                    <option value="pending" <?php echo $row['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="shipped" <?php echo $row['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo $row['status'] == 'delivered' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No orders found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/admin-footer.php'; ?>