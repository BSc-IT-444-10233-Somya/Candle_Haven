<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css"> 
</head>
<body>
<?php
// profile.php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = sanitize_input($_POST['name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $address = sanitize_input($_POST['address']);
        
        // Update user data
        // Split full name into first and last name to match DB columns
        $parts = preg_split('/\s+/', trim($name));
        $first_name = $parts[0] ?? '';
        array_shift($parts);
        $last_name = count($parts) ? join(' ', $parts) : '';

        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
        if ($stmt->execute([$first_name, $last_name, $email, $phone, $address, $user_id])) {
            $_SESSION['success'] = "Profile updated successfully!";
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $_SESSION['error'] = "Failed to update profile.";
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashed_password, $user_id])) {
                    $_SESSION['success'] = "Password changed successfully!";
                }
            } else {
                $_SESSION['error'] = "New passwords do not match.";
            }
        } else {
            $_SESSION['error'] = "Current password is incorrect.";
        }
    }
}

// Get user's orders
$orders_stmt = $pdo->prepare("
    SELECT o.*, COUNT(oi.id) as item_count, COALESCE(SUM(oi.subtotal), 0) as total 
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.user_id = ? 
    GROUP BY o.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$orders_stmt->execute([$user_id]);
$orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's favorite candles
// Load user's favorites from `wishlist`/`products` if available. Use try/catch
// to avoid fatal errors when the table doesn't exist.
try {
    $favorites_stmt = $pdo->prepare(
        "SELECT p.* 
         FROM wishlist w 
         JOIN products p ON w.product_id = p.id 
         WHERE w.user_id = ? 
         LIMIT 6"
    );
    $favorites_stmt->execute([$user_id]);
    $favorites = $favorites_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If wishlist table doesn't exist or query fails, continue without favorites
    error_log('Favorites query failed: ' . $e->getMessage());
    $favorites = [];
}

$page_title = "My Profile - " . SITE_NAME;
require_once 'includes/header.php';
?>

<div class="container mt-4">
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-user-circle fa-5x text-warning"></i>
                    </div>
                    <h5 class="card-title"><?php echo htmlspecialchars((($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?></h5>
                    <p class="card-text text-muted"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                    <p class="card-text">
                        <small>Member since: <?php echo !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : 'N/A'; ?></small>
                    </p>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <a href="#profile-info" class="text-decoration-none d-block">
                            <i class="fas fa-user me-2"></i> Profile Information
                        </a>
                    </li>
                    <li class="list-group-item">
                        <a href="#orders" class="text-decoration-none d-block">
                            <i class="fas fa-shopping-bag me-2"></i> My Orders
                        </a>
                    </li>
                    <li class="list-group-item">
                        <a href="#favorites" class="text-decoration-none d-block">
                            <i class="fas fa-heart me-2"></i> My Favorites
                        </a>
                    </li>
                    <li class="list-group-item">
                        <a href="#password-change" class="text-decoration-none d-block">
                            <i class="fas fa-key me-2"></i> Change Password
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Profile Information -->
            <div class="card mb-4" id="profile-info">
                <div class="card-header bg-warning text-white">
                    <h4 class="mb-0"><i class="fas fa-user me-2"></i>Profile Information</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars((($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="card mb-4" id="orders">
                <div class="card-header bg-warning text-white">
                    <h4 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Recent Orders</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <p class="text-muted">You haven't placed any orders yet.</p>
                        <a href="index.php" class="btn btn-outline-warning">Start Shopping</a>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                            <td><?php echo $order['item_count']; ?> items</td>
                                            <td><?php echo format_price($order['total']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $order['status'] == 'completed' ? 'success' : 
                                                           ($order['status'] == 'processing' ? 'warning' : 'secondary');
                                                ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-warning">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="orders.php" class="btn btn-outline-warning">View All Orders</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Favorite Candles -->
            <div class="card mb-4" id="favorites">
                <div class="card-header bg-warning text-white">
                    <h4 class="mb-0"><i class="fas fa-heart me-2"></i>Favorite Candles</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($favorites)): ?>
                        <p class="text-muted">You haven't added any favorites yet.</p>
                        <a href="index.php" class="btn btn-outline-warning">Browse Candles</a>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($favorites as $candle): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                         <img src="<?php echo htmlspecialchars($candle['image_url'] ?? ($candle['image'] ?? 'images/default-candle.jpg')); ?>" 
                                             class="card-img-top" alt="<?php echo htmlspecialchars($candle['name'] ?? 'Product'); ?>" 
                                             style="height: 200px; object-fit: cover;">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($candle['name'] ?? 'Product'); ?></h6>
                                            <p class="card-text text-warning fw-bold"><?php echo isset($candle['price']) ? format_price($candle['price']) : ''; ?></p>
                                            <a href="product.php?id=<?php echo $candle['id']; ?>" class="btn btn-sm btn-outline-warning">View Product</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="favorites.php" class="btn btn-outline-warning mt-3">View All Favorites</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="card mb-4" id="password-change">
                <div class="card-header bg-warning text-white">
                    <h4 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>