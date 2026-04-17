<?php
// wishlist.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    $_SESSION['error'] = "Please login to view your wishlist";
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Add to wishlist
if (isset($_GET['add']) && is_numeric($_GET['add'])) {
    $candle_id = intval($_GET['add']);
    
    // Check if candle exists
    $stmt = $pdo->prepare("SELECT id FROM candles WHERE id = ?");
    $stmt->execute([$candle_id]);
    if ($stmt->rowCount() > 0) {
        // Check if already in wishlist
        $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND candle_id = ?");
        $stmt->execute([$user_id, $candle_id]);
        
        if ($stmt->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, candle_id, created_at) VALUES (?, ?, NOW())");
            if ($stmt->execute([$user_id, $candle_id])) {
                $_SESSION['success'] = "Candle added to wishlist!";
            }
        } else {
            $_SESSION['info'] = "Candle is already in your wishlist";
        }
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'wishlist.php'));
    exit();
}

// Remove from wishlist
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $wishlist_id = intval($_GET['remove']);
    $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$wishlist_id, $user_id])) {
        $_SESSION['success'] = "Candle removed from wishlist";
    }
    header('Location: wishlist.php');
    exit();
}

// Remove all from wishlist
if (isset($_GET['remove_all'])) {
    $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ?");
    if ($stmt->execute([$user_id])) {
        $_SESSION['success'] = "All candles removed from wishlist";
    }
    header('Location: wishlist.php');
    exit();
}

// Move to cart
if (isset($_GET['move_to_cart']) && is_numeric($_GET['move_to_cart'])) {
    $wishlist_id = intval($_GET['move_to_cart']);
    
    // Get candle details
    $stmt = $pdo->prepare("
        SELECT w.candle_id, c.price 
        FROM wishlist w 
        JOIN candles c ON w.candle_id = c.id 
        WHERE w.id = ? AND w.user_id = ?
    ");
    $stmt->execute([$wishlist_id, $user_id]);
    $wishlist_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($wishlist_item) {
        // Add to cart (using session cart)
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        $candle_id = $wishlist_item['candle_id'];
        $found = false;
        
        // Check if already in cart
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $candle_id) {
                $item['quantity']++;
                $found = true;
                break;
            }
        }
        
        // Add new item to cart
        if (!$found) {
            $_SESSION['cart'][] = [
                'id' => $candle_id,
                'quantity' => 1,
                'price' => $wishlist_item['price']
            ];
        }
        
        // Remove from wishlist
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?");
        $stmt->execute([$wishlist_id, $user_id]);
        
        $_SESSION['success'] = "Candle moved to cart!";
    }
    header('Location: wishlist.php');
    exit();
}

// Get wishlist items
$stmt = $pdo->prepare("
    SELECT 
        w.id as wishlist_id,
        c.*,
        w.created_at as added_date,
        cat.name as category_name
    FROM wishlist w
    JOIN candles c ON w.candle_id = c.id
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
");
$stmt->execute([$user_id]);
$wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get wishlist count for sidebar
$wishlist_count = count($wishlist_items);

$page_title = "My Wishlist - " . SITE_NAME;
require_once 'includes/header.php';
?>

<div class="container py-5">
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['info'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['info']; unset($_SESSION['info']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Wishlist</li>
                </ol>
            </nav>
            <h1 class="display-6">
                <i class="fas fa-heart text-warning me-2"></i>My Wishlist
                <span class="badge bg-warning text-dark ms-2"><?php echo $wishlist_count; ?> items</span>
            </h1>
            <p class="text-muted">Save your favorite candles for later</p>
        </div>
    </div>
    
    <!-- Wishlist Content -->
    <?php if (empty($wishlist_items)): ?>
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-heart-broken fa-4x text-muted"></i>
            </div>
            <h3 class="mb-3">Your wishlist is empty</h3>
            <p class="text-muted mb-4">You haven't added any candles to your wishlist yet.</p>
            <a href="index.php" class="btn btn-warning btn-lg">
                <i class="fas fa-shopping-bag me-2"></i>Browse Our Candles
            </a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <!-- Wishlist Items -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Wishlist Items</h5>
                        <?php if ($wishlist_count > 0): ?>
                            <a href="wishlist.php?remove_all=1" class="btn btn-sm btn-outline-danger" 
                               onclick="return confirm('Remove all items from wishlist?')">
                                <i class="fas fa-trash me-1"></i>Clear All
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($wishlist_items as $item): ?>
                                <div class="list-group-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-2">
                                            <a href="product.php?id=<?php echo $item['id']; ?>">
                                                <img src="images/<?php echo htmlspecialchars($item['image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                     class="img-fluid rounded" style="height: 100px; object-fit: cover;">
                                            </a>
                                        </div>
                                        <div class="col-md-5">
                                            <h6 class="mb-1">
                                                <a href="product.php?id=<?php echo $item['id']; ?>" class="text-decoration-none text-dark">
                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                </a>
                                            </h6>
                                            <?php if (!empty($item['category_name'])): ?>
                                                <span class="badge bg-light text-dark mb-2"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                            <?php endif; ?>
                                            <p class="text-muted small mb-2">Added: <?php echo date('M d, Y', strtotime($item['added_date'])); ?></p>
                                            <?php if ($item['stock'] > 0): ?>
                                                <span class="badge bg-success">In Stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="h5 mb-0 text-warning">
                                                <?php echo format_price($item['price']); ?>
                                            </div>
                                            <?php if ($item['original_price'] > $item['price']): ?>
                                                <small class="text-muted text-decoration-line-through">
                                                    <?php echo format_price($item['original_price']); ?>
                                                </small>
                                                <span class="badge bg-danger ms-1">Save <?php echo number_format((($item['original_price'] - $item['price']) / $item['original_price']) * 100, 0); ?>%</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="btn-group-vertical btn-group-sm">
                                                <a href="wishlist.php?move_to_cart=<?php echo $item['wishlist_id']; ?>" 
                                                   class="btn btn-warning mb-1">
                                                    <i class="fas fa-cart-plus me-1"></i>Add to Cart
                                                </a>
                                                <a href="wishlist.php?remove=<?php echo $item['wishlist_id']; ?>" 
                                                   class="btn btn-outline-danger" 
                                                   onclick="return confirm('Remove from wishlist?')">
                                                    <i class="fas fa-trash me-1"></i>Remove
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-flex justify-content-between mb-5">
                    <a href="index.php" class="btn btn-outline-warning">
                        <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                    </a>
                    <?php if ($wishlist_count > 0): ?>
                        <div>
                            <a href="cart.php" class="btn btn-warning">
                                <i class="fas fa-shopping-cart me-2"></i>View Cart
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Summary Card -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Wishlist Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Items in wishlist:</span>
                            <strong><?php echo $wishlist_count; ?></strong>
                        </div>
                        
                        <?php 
                        // Calculate totals
                        $total_original = 0;
                        $total_sale = 0;
                        $total_savings = 0;
                        
                        foreach ($wishlist_items as $item) {
                            $total_original += $item['original_price'];
                            $total_sale += $item['price'];
                            $total_savings += ($item['original_price'] - $item['price']);
                        }
                        ?>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span>Total Value:</span>
                            <strong class="text-warning"><?php echo format_price($total_sale); ?></strong>
                        </div>
                        
                        <?php if ($total_savings > 0): ?>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Total Savings:</span>
                                <strong class="text-success"><?php echo format_price($total_savings); ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="text-center">
                            <?php if ($wishlist_count > 0): ?>
                                <p class="small text-muted mb-3">
                                    <i class="fas fa-lightbulb text-warning me-1"></i>
                                    Add all items to cart with one click
                                </p>
                                <form action="add-all-to-cart.php" method="POST">
                                    <button type="submit" class="btn btn-warning w-100 mb-2">
                                        <i class="fas fa-cart-plus me-2"></i>Add All to Cart
                                    </button>
                                </form>
                                <a href="wishlist.php?remove_all=1" 
                                   class="btn btn-outline-danger w-100"
                                   onclick="return confirm('Remove all items from wishlist?')">
                                    <i class="fas fa-trash me-2"></i>Clear Wishlist
                                </a>
                            <?php else: ?>
                                <p class="text-muted">Add items to see options</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recommendations -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-fire text-warning me-2"></i>Popular Candles</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get popular candles (not already in wishlist)
                        $popular_stmt = $pdo->prepare("
                            SELECT c.* 
                            FROM candles c 
                            LEFT JOIN wishlist w ON c.id = w.candle_id AND w.user_id = ?
                            WHERE w.candle_id IS NULL 
                            ORDER BY c.sales_count DESC, c.created_at DESC 
                            LIMIT 3
                        ");
                        $popular_stmt->execute([$user_id]);
                        $popular_candles = $popular_stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <?php if (!empty($popular_candles)): ?>
                            <?php foreach ($popular_candles as $candle): ?>
                                <div class="d-flex mb-3">
                                    <a href="product.php?id=<?php echo $candle['id']; ?>" class="flex-shrink-0">
                                        <img src="images/<?php echo htmlspecialchars($candle['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($candle['name']); ?>"
                                             class="rounded" style="width: 60px; height: 60px; object-fit: cover;">
                                    </a>
                                    <div class="ms-3 flex-grow-1">
                                        <h6 class="mb-1">
                                            <a href="product.php?id=<?php echo $candle['id']; ?>" class="text-decoration-none text-dark small">
                                                <?php echo htmlspecialchars($candle['name']); ?>
                                            </a>
                                        </h6>
                                        <div class="text-warning mb-1">
                                            <?php echo format_price($candle['price']); ?>
                                        </div>
                                        <a href="wishlist.php?add=<?php echo $candle['id']; ?>" 
                                           class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-heart"></i>
                                        </a>
                                        <a href="cart.php?add=<?php echo $candle['id']; ?>" 
                                           class="btn btn-sm btn-outline-dark">
                                            <i class="fas fa-cart-plus"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted small">No recommendations available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add to Cart Modal -->
<div class="modal fade" id="addToCartModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Added to Cart!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5>Item moved to cart successfully!</h5>
                <p>You can continue shopping or proceed to checkout.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Continue Browsing</button>
                <a href="cart.php" class="btn btn-warning">View Cart</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>