<?php
require_once 'includes/config.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch order details
$sql = "SELECT o.*, u.first_name, u.last_name, u.email 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE o.id = $order_id AND o.user_id = {$_SESSION['user_id']}";
$result = mysqli_query($conn, $sql);
$order = mysqli_fetch_assoc($result);

if(!$order) {
    header('Location: index.php');
    exit();
}

$page_title = "Order Confirmation";
include 'includes/header.php';
?>

<div class="container">
    <div class="confirmation-container">
        <div class="confirmation-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1>Thank You for Your Order!</h1>
        <p class="confirmation-message">Your order has been received and is being processed. You will receive a confirmation email shortly.</p>
        
        <div class="order-info-card">
            <h2>Order Details</h2>
            
            <div class="order-info-grid">
                <div class="info-item">
                    <h3>Order Number</h3>
                    <p>#<?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></p>
                </div>
                
                <div class="info-item">
                    <h3>Order Date</h3>
                    <p><?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></p>
                </div>
                
                <div class="info-item">
                    <h3>Total Amount</h3>
                    <p class="order-total"><?php echo format_price($order['total_amount']); ?></p>
                </div>
                
                <div class="info-item">
                    <h3>Payment Method</h3>
                    <p><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></p>
                </div>
                
                <div class="info-item">
                    <h3>Shipping Address</h3>
                    <p><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                </div>
                
                <div class="info-item">
                    <h3>Order Status</h3>
                    <span class="status-badge status-<?php echo $order['status']; ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="confirmation-actions">
            <a href="orders.php" class="btn"><i class="fas fa-shopping-bag"></i> View All Orders</a>
            <a href="index.php" class="btn btn-secondary"><i class="fas fa-shopping-cart"></i> Continue Shopping</a>
        </div>
        
        <div class="confirmation-help">
            <h3>Need Help?</h3>
            <p>If you have any questions about your order, please contact our customer support team at <a href="mailto:support@candlehaven.com">support@candlehaven.com</a> or call us at (123) 456-7890.</p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>