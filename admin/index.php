<?php
require_once '../includes/config.php';

// Check if user is admin
if(!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

$page_title = "Admin Dashboard";
include 'includes/admin-header.php';

// Get statistics
$sql = "SELECT COUNT(*) as total FROM products";
$result = mysqli_query($conn, $sql);
$total_products = mysqli_fetch_assoc($result)['total'];

$sql = "SELECT COUNT(*) as total FROM orders WHERE status != 'cancelled'";
$result = mysqli_query($conn, $sql);
$total_orders = mysqli_fetch_assoc($result)['total'];

$sql = "SELECT COUNT(*) as total FROM users WHERE is_admin = 0";
$result = mysqli_query($conn, $sql);
$total_customers = mysqli_fetch_assoc($result)['total'];

$sql = "SELECT SUM(total_amount) as total FROM orders WHERE status = 'delivered'";
$result = mysqli_query($conn, $sql);
$total_revenue = mysqli_fetch_assoc($result)['total'] ?: 0;
?>

<div class="admin-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Admin Dashboard</h1>
        <div class="export-buttons">
            <a href="export-report.php?type=full&format=html" class="btn btn-export" target="_blank" title="Export as HTML">
                <i class="fas fa-file-download"></i> Export Report (HTML)
            </a>
            <a href="export-report.php?type=full&format=pdf" class="btn btn-export" title="Export as PDF">
                <i class="fas fa-file-pdf"></i> Export Report (PDF)
            </a>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-candle-holder"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $total_products; ?></h3>
                <p>Total Products</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $total_orders; ?></h3>
                <p>Total Orders</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $total_customers; ?></h3>
                <p>Total Customers</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo format_price($total_revenue); ?></h3>
                <p>Total Revenue</p>
            </div>
        </div>
    </div>
    
    <div class="admin-sections">
        <div class="admin-section">
            <h2>Recent Orders</h2>
            <?php
            $sql = "SELECT o.*, u.first_name, u.last_name FROM orders o 
                    LEFT JOIN users u ON o.user_id = u.id 
                    ORDER BY o.created_at DESC LIMIT 5";
            $result = mysqli_query($conn, $sql);
            
            if(mysqli_num_rows($result) > 0):
            ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($order = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td>#<?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo $order['first_name'] . ' ' . $order['last_name']; ?></td>
                        <td><?php echo format_price($order['total_amount']); ?></td>
                        <td><span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                        <td><a href="<?php echo site_path('admin/orders.php') . '?action=view&id=' . $order['id']; ?>" class="btn-small">View</a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No orders found.</p>
            <?php endif; ?>
            <a href="orders.php" class="btn">View All Orders</a>
        </div>
        
        <div class="admin-section">
            <h2>Quick Actions</h2>
            <div class="quick-actions">
                <a href="products.php?action=add" class="quick-action">
                    <i class="fas fa-plus"></i>
                    <span>Add New Product</span>
                </a>
                <a href="orders.php" class="quick-action">
                    <i class="fas fa-shopping-bag"></i>
                    <span>Manage Orders</span>
                </a>
                <a href="users.php" class="quick-action">
                    <i class="fas fa-users"></i>
                    <span>Manage Users</span>
                </a>
                <a href="../index.php" class="quick-action">
                    <i class="fas fa-store"></i>
                    <span>View Store</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>