<?php
require_once 'includes/config.php';
$page_title = "Shopping Cart";

// Initialize cart if not exists
if(!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions via AJAX or regular form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    // Keep product_id as raw string so custom item keys (e.g. "CUSTOM_1234") are preserved
    $product_id = isset($_POST['product_id']) ? $_POST['product_id'] : '';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    if($action == 'add') {
        if(isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'quantity' => $quantity
            ];
        }
    } elseif($action == 'update') {
        if($quantity > 0) {
            $_SESSION['cart'][$product_id]['quantity'] = $quantity;
        } else {
            unset($_SESSION['cart'][$product_id]);
        }
    } elseif($action == 'remove') {
        unset($_SESSION['cart'][$product_id]);
    }
    
    // If it's an AJAX request, return JSON response
    if(isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
        $cart_count = 0;
        foreach($_SESSION['cart'] as $item) {
            $cart_count += $item['quantity'];
        }
        
        echo json_encode([
            'success' => true,
            'cart_count' => $cart_count,
            'message' => 'Cart updated successfully'
        ]);
        exit;
    } else {
        header('Location: cart.php');
        exit();
    }
}

// Calculate totals
$total = 0;
$cart_items = [];
$has_error = false;

foreach($_SESSION['cart'] as $product_id => $item) {
    // If this is a custom item (added via custom-candle.php), it won't have a numeric product id
    // Keep custom items as-is and use their stored price/details
    if (is_array($item) && isset($item['type']) && $item['type'] === 'custom') {
        $product = [
            'id' => $product_id,
            'name' => $item['name'] ?? 'Custom Candle',
            'price' => isset($item['price']) ? floatval($item['price']) : 0,
            'image_url' => $item['details']['image_url'] ?? 'images/default-candle.jpg',
            'in_stock' => 1,
            'category' => 'Custom',
            'scent' => $item['details']['custom_scent'] ?? ''
        ];

        $item_total = $product['price'] * $item['quantity'];
        $total += $item_total;

        $cart_items[] = [
            'product' => $product,
            'quantity' => $item['quantity'],
            'total' => $item_total
        ];

        continue;
    }

    // Fetch product details from DB for regular products
    $sql = "SELECT * FROM products WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);

    if($stmt) {
        $pid = intval($product_id);
        mysqli_stmt_bind_param($stmt, 'i', $pid);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if($result && mysqli_num_rows($result) > 0) {
            $product = mysqli_fetch_assoc($result);

            if($product) {
                $item_total = $product['price'] * $item['quantity'];
                $total += $item_total;

                $cart_items[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'total' => $item_total
                ];
            }
        } else {
            // Product not found, remove from cart
            unset($_SESSION['cart'][$product_id]);
            $has_error = true;
        }

        mysqli_stmt_close($stmt);
    } else {
        // SQL preparation failed
        $has_error = true;
        error_log("Failed to prepare SQL statement: " . mysqli_error($conn));
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="container">
    <h1 class="page-title">Your Shopping Cart</h1>
    
    <?php if($has_error): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            Some items in your cart are no longer available and have been removed.
        </div>
    <?php endif; ?>
    
    <?php if(empty($cart_items)): ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-cart"></i>
            <h2>Your cart is empty</h2>
            <p>Add some candles to brighten up your space!</p>
            <a href="shop.php" class="btn">Continue Shopping</a>
        </div>
    <?php else: ?>
        <div class="cart-container">
            <div class="cart-items">
                <?php foreach($cart_items as $item): 
                    $product = $item['product'];
                ?>
                <div class="cart-item">
                    <div class="cart-item-image">
                        <img src="<?php echo $product['image_url'] ?: 'images/default-candle.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>
                    <div class="cart-item-details">
                        <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                        <p class="cart-item-category"><?php echo htmlspecialchars($product['category']); ?></p>
                        <p class="cart-item-scent">
                            <i class="fas fa-wind"></i> <?php echo htmlspecialchars($product['scent']); ?>
                        </p>
                        <div class="cart-item-price"><?php echo format_price($product['price']); ?></div>
                        <div class="cart-item-actions">
                            <form method="post" class="quantity-form">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="hidden" name="action" value="update">
                                <div class="quantity-control">
                                    <button type="button" class="quantity-btn minus">-</button>
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                           min="1" max="<?php echo $product['in_stock']; ?>" 
                                           class="quantity-input" data-product-id="<?php echo $product['id']; ?>">
                                    <button type="button" class="quantity-btn plus">+</button>
                                </div>
                                <button type="submit" class="btn-update" style="display: none;">Update</button>
                            </form>
                            <form method="post" class="remove-form">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="hidden" name="action" value="remove">
                                <button type="submit" class="remove-item">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="cart-item-subtotal">
                        <?php echo format_price($item['total']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="cart-summary">
                <h3>Order Summary</h3>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span><?php echo format_price($total); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span>
                        <?php 
                        $shipping = $total > 50 ? 0 : 5.99;
                        echo $shipping == 0 ? 'FREE' : format_price($shipping);
                        ?>
                    </span>
                </div>
                <div class="summary-row">
                    <span>Tax</span>
                    <span><?php echo format_price($total * 0.08); ?></span>
                </div>
                <div class="summary-row summary-total">
                    <span>Total</span>
                    <span><?php echo format_price($total + $shipping + ($total * 0.08)); ?></span>
                </div>
                
                <div class="cart-actions">
                    <a href="checkout.php" class="btn btn-checkout">Proceed to Checkout</a>
                    <a href="shop.php" class="btn btn-secondary">Continue Shopping</a>
                </div>
                
                <?php if($total < 50): ?>
                    <div class="free-shipping-notice">
                        <i class="fas fa-shipping-fast"></i>
                        Add <?php echo format_price(50 - $total); ?> more to get free shipping!
                    </div>
                <?php else: ?>
                    <div class="free-shipping-achieved">
                        <i class="fas fa-check-circle"></i>
                        You've earned free shipping!
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>