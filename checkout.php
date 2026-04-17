<?php
require_once 'includes/config.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_to'] = 'checkout.php';
    header('Location: login.php');
    exit();
}

// Check if cart is empty
if(!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit();
}

$page_title = "Checkout";

// Calculate totals
$subtotal = 0;
$shipping = 5.99;
$cart_items = [];

foreach($_SESSION['cart'] as $product_id => $item) {
    // Support two cart formats:
    // 1) associative: product_id => ['quantity' => X]
    // 2) full item array with product data: ['product' => [...], 'quantity' => X]

    // If this is a custom item (added via custom-candle.php), it may have a type
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
        $quantity = intval($item['quantity'] ?? 1);
    } elseif (is_array($item) && isset($item['product']) && is_array($item['product'])) {
        // If item already includes product data, use it
        $product = $item['product'];
        $quantity = intval($item['quantity'] ?? 1);
    } else {
        // Ensure product id is an integer to avoid SQL syntax errors
        $pid = intval($product_id);
        if ($pid <= 0) {
            // Skip unknown/non-database items (could be custom items stored differently)
            continue;
        }

        // Use prepared statement to fetch product safely
        $pstmt = mysqli_prepare($conn, "SELECT id, name, price, image_url, in_stock FROM products WHERE id = ? LIMIT 1");
        if ($pstmt) {
            mysqli_stmt_bind_param($pstmt, 'i', $pid);
            mysqli_stmt_execute($pstmt);
            $pres = mysqli_stmt_get_result($pstmt);
            $product = mysqli_fetch_assoc($pres);
            mysqli_stmt_close($pstmt);
        } else {
            // Fallback: skip this item if we cannot prepare statement
            continue;
        }

        $quantity = intval(is_array($item) && isset($item['quantity']) ? $item['quantity'] : 1);
    }

    if ($product) {
        $item_total = floatval($product['price']) * $quantity;
        $subtotal += $item_total;

        $cart_items[] = [
            'product' => $product,
            'quantity' => $quantity,
            'total' => $item_total
        ];
    }
}

// Calculate shipping
if($subtotal > 50) {
    $shipping = 0;
}

// Calculate tax (8% to match cart calculations)
$tax = round($subtotal * 0.08, 2);

$total = $subtotal + $shipping + $tax;

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shipping_address = mysqli_real_escape_string($conn, $_POST['shipping_address']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $zip = mysqli_real_escape_string($conn, $_POST['zip']);
    $country = mysqli_real_escape_string($conn, $_POST['country'] ?? 'India');
    $contact_email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $contact_phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);

    // Create order
    $full_address = "$shipping_address, $city, $state $zip, $country";
    $user_id = $_SESSION['user_id'];
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Generate unique order number and insert order (use prepared statement)
        $order_number = generate_order_number();
        $order_sql = "INSERT INTO orders (order_number, user_id, total_amount, shipping_address, city, state, zip_code, country, phone, email, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $order_stmt = mysqli_prepare($conn, $order_sql);
        if (!$order_stmt) {
            throw new Exception('Prepare order failed: ' . mysqli_error($conn));
        }
        // types: s (order_number), i (user_id), d (total), then 8 strings
        mysqli_stmt_bind_param($order_stmt, 'sidssssssss', $order_number, $user_id, $total, $shipping_address, $city, $state, $zip, $country, $contact_phone, $contact_email, $payment_method);
        if (!mysqli_stmt_execute($order_stmt)) {
            throw new Exception('Execute order failed: ' . mysqli_error($conn));
        }
        $order_id = mysqli_insert_id($conn);
        mysqli_stmt_close($order_stmt);

        // Prepare order item inserts
        $item_sql_db = "INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)";
        $item_stmt_db = mysqli_prepare($conn, $item_sql_db);
        if (!$item_stmt_db) {
            throw new Exception('Prepare order item (db) failed: ' . mysqli_error($conn));
        }

        // Prepared statement for custom items (product_id set to NULL)
        $item_sql_custom = "INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal) VALUES (?, NULL, ?, ?, ?, ?)";
        $item_stmt_custom = mysqli_prepare($conn, $item_sql_custom);
        if (!$item_stmt_custom) {
            throw new Exception('Prepare order item (custom) failed: ' . mysqli_error($conn));
        }

        // Prepare stock update
        $stock_sql = "UPDATE products SET in_stock = in_stock - ? WHERE id = ?";
        $stock_stmt = mysqli_prepare($conn, $stock_sql);
        if (!$stock_stmt) {
            throw new Exception('Prepare stock update failed: ' . mysqli_error($conn));
        }

        // Insert order items and update stock
        foreach($cart_items as $item) {
            $raw_pid = $item['product']['id'];
            $is_db_product = is_numeric($raw_pid) && intval($raw_pid) > 0;
            $product_id = $is_db_product ? intval($raw_pid) : null;
            $quantity = intval($item['quantity']);
            $price = floatval($item['product']['price']);
            $subtotal_item = $price * $quantity;
            $product_name = $item['product']['name'];

            if ($is_db_product) {
                // Bind and execute order item for DB product
                mysqli_stmt_bind_param($item_stmt_db, 'iisdid', $order_id, $product_id, $product_name, $price, $quantity, $subtotal_item);
                if (!mysqli_stmt_execute($item_stmt_db)) {
                    throw new Exception('Insert order item (db) failed: ' . mysqli_stmt_error($item_stmt_db));
                }
                $affected = mysqli_stmt_affected_rows($item_stmt_db);
                if ($affected <= 0) {
                    throw new Exception('Insert order item (db) affected 0 rows: ' . mysqli_stmt_error($item_stmt_db));
                }

                // Update product stock
                mysqli_stmt_bind_param($stock_stmt, 'ii', $quantity, $product_id);
                if (!mysqli_stmt_execute($stock_stmt)) {
                    throw new Exception('Update stock failed: ' . mysqli_stmt_error($stock_stmt));
                }
            } else {
                // Custom item: insert with product_id = NULL
                mysqli_stmt_bind_param($item_stmt_custom, 'isdid', $order_id, $product_name, $price, $quantity, $subtotal_item);
                if (!mysqli_stmt_execute($item_stmt_custom)) {
                    throw new Exception('Insert order item (custom) failed: ' . mysqli_stmt_error($item_stmt_custom));
                }
                $affected = mysqli_stmt_affected_rows($item_stmt_custom);
                if ($affected <= 0) {
                    throw new Exception('Insert order item (custom) affected 0 rows: ' . mysqli_stmt_error($item_stmt_custom));
                }
            }
        }

        mysqli_stmt_close($item_stmt_db);
        mysqli_stmt_close($item_stmt_custom);
        mysqli_stmt_close($stock_stmt);

        // Commit transaction
        mysqli_commit($conn);

        // Clear cart
        unset($_SESSION['cart']);

        // Redirect to order confirmation
        header("Location: order-confirmation.php?id=$order_id");
        exit();

    } catch(Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        error_log('Checkout error: ' . $e->getMessage());
        $error = "An error occurred while processing your order. Please try again. Debug: " . $e->getMessage();
    }
}

// Get user info for autofill
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($result);

include 'includes/header.php';

?>

<div class="container">
    <h1 class="page-title">Checkout</h1>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="checkout-container">
        <div class="checkout-form">
            <form method="POST" action="">
                <div class="checkout-section">
                    <h2><i class="fas fa-shipping-fast"></i> Shipping Information</h2>
                    
                    <div class="form-group">
                        <label for="shipping_address">Shipping Address *</label>
                        <input type="text" id="shipping_address" name="shipping_address" 
                               value="<?php echo isset($user['address']) ? htmlspecialchars($user['address']) : ''; ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo isset($user['phone']) ? htmlspecialchars($user['phone']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="city">City *</label>
                            <input type="text" id="city" name="city" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="state">State / Union Territory *</label>
                            <select id="state" name="state" required>
                                <option value="">Select State</option>
                                <option value="Andhra Pradesh">Andhra Pradesh</option>
                                <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                                <option value="Assam">Assam</option>
                                <option value="Bihar">Bihar</option>
                                <option value="Chhattisgarh">Chhattisgarh</option>
                                <option value="Goa">Goa</option>
                                <option value="Gujarat">Gujarat</option>
                                <option value="Haryana">Haryana</option>
                                <option value="Himachal Pradesh">Himachal Pradesh</option>
                                <option value="Jharkhand">Jharkhand</option>
                                <option value="Karnataka">Karnataka</option>
                                <option value="Kerala">Kerala</option>
                                <option value="Madhya Pradesh">Madhya Pradesh</option>
                                <option value="Maharashtra">Maharashtra</option>
                                <option value="Manipur">Manipur</option>
                                <option value="Meghalaya">Meghalaya</option>
                                <option value="Mizoram">Mizoram</option>
                                <option value="Nagaland">Nagaland</option>
                                <option value="Odisha">Odisha</option>
                                <option value="Punjab">Punjab</option>
                                <option value="Rajasthan">Rajasthan</option>
                                <option value="Sikkim">Sikkim</option>
                                <option value="Tamil Nadu">Tamil Nadu</option>
                                <option value="Telangana">Telangana</option>
                                <option value="Tripura">Tripura</option>
                                <option value="Uttar Pradesh">Uttar Pradesh</option>
                                <option value="Uttarakhand">Uttarakhand</option>
                                <option value="West Bengal">West Bengal</option>
                                <option value="Andaman and Nicobar Islands">Andaman and Nicobar Islands</option>
                                <option value="Chandigarh">Chandigarh</option>
                                <option value="Dadra and Nagar Haveli and Daman and Diu">Dadra and Nagar Haveli and Daman and Diu</option>
                                <option value="Delhi">Delhi</option>
                                <option value="Jammu and Kashmir">Jammu and Kashmir</option>
                                <option value="Ladakh">Ladakh</option>
                                <option value="Lakshadweep">Lakshadweep</option>
                                <option value="Puducherry">Puducherry</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="district">District *</label>
                            <select id="district" name="district" required>
                                <option value="">Select District</option>
                                <!-- District options will be populated dynamically -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="zip">PIN Code *</label>
                            <input type="text" id="zip" name="zip" pattern="[0-9]{6}" required>
                        </div>

                        <div class="form-group">
                            <label for="country">Country *</label>
                            <select id="country" name="country" required>
                                <option value="India" selected>India</option>
                                <option value="United States">United States</option>
                                <option value="United Kingdom">United Kingdom</option>
                                <option value="Australia">Australia</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="checkout-section">
                    <h2><i class="fas fa-credit-card"></i> Payment Method</h2>
                    
                    <div class="payment-methods">
                        <div class="payment-option">
                            <input type="radio" id="cod" name="payment_method" value="cod" checked required>
                            <label for="cod">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Cash on Delivery (COD)</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="checkout-section">
                    <h2><i class="fas fa-sticky-note"></i> Order Notes (Optional)</h2>
                    <div class="form-group">
                        <textarea id="order_notes" name="order_notes" rows="3" placeholder="Add special instructions for your order..."></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-large">Place Order</button>
            </form>
        </div>
        
        <div class="order-summary">
            <h2>Order Summary</h2>
            
            <div class="order-items">
                <?php foreach($cart_items as $item): ?>
                <div class="order-item">
                    <div class="item-image">
                        <img src="<?php echo $item['product']['image_url'] ?: 'images/default-candle.jpg'; ?>" alt="<?php echo $item['product']['name']; ?>">
                    </div>
                    <div class="item-details">
                        <h4><?php echo $item['product']['name']; ?></h4>
                        <p>Qty: <?php echo $item['quantity']; ?></p>
                    </div>
                    <div class="item-price"><?php echo format_price($item['total']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="summary-totals">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span><?php echo format_price($subtotal); ?></span>
                </div>
                
                <div class="summary-row">
                    <span>Shipping</span>
                    <span><?php echo $shipping == 0 ? 'FREE' : format_price($shipping); ?></span>
                </div>

                <div class="summary-row">
                    <span>Tax</span>
                    <span><?php echo format_price($tax); ?></span>
                </div>
                
                <div class="summary-row total">
                    <span>Total</span>
                    <span><?php echo format_price($total); ?></span>
                </div>
            </div>
            
            <div class="secure-checkout">
                <i class="fas fa-lock"></i>
                <span>Secure Checkout</span>
                <p>Your payment information is encrypted and secure.</p>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle payment details based on selected method
document.addEventListener('DOMContentLoaded', function() {
    // Only Cash on Delivery is available; no dynamic payment fields required.
});
</script>

<?php include 'includes/footer.php'; ?>