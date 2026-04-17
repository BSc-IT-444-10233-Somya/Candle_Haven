
<?php
require_once 'includes/config.php';
// Disable auto-login on the public homepage: remove remember_token cookie and server-side token
if (isset($_COOKIE['remember_token']) && isset($pdo)) {
    try {
        $token = $_COOKIE['remember_token'];
        $token_hash = hash_hmac('sha256', $token, SITE_NAME);
        // Remove token from DB so it cannot be used again
        $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE token = ?");
        $stmt->execute([$token_hash]);
    } catch (Throwable $e) {
        error_log('Failed to clear remember_token on index: ' . $e->getMessage());
    }
}
// Clear cookie from browser
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    unset($_COOKIE['remember_token']);
}

// Require login: if no user session exists, send visitor to login page
$isLoggedIn = (isset($_SESSION['user']['is_logged_in']) && $_SESSION['user']['is_logged_in'] === true)
            || (isset($_SESSION['user_id']) && !empty($_SESSION['user_id']));
if (!$isLoggedIn) {
    header('Location: login.php');
    exit();
}
$page_title = "Home";
include 'includes/header.php';
?>
<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <h1>Illuminate Your Space with Handcrafted Candles</h1>
        <p>Discover our collection of premium, eco-friendly candles crafted with natural ingredients and captivating scents that transform any room into a sanctuary.</p>
        <a href="#products" class="btn">Shop Now</a>
        <a href="about.php" class="btn btn-secondary">Learn More</a>
    </div>
</section>

<!-- Featured Products -->
<section id="products" class="section">
    <div class="container">
        <h2 class="section-title">Our Featured Candles</h2>
        <div class="products-grid">
            <?php
            // Fetch products from database
            $sql = "SELECT * FROM products WHERE in_stock > 0 LIMIT 6";
            $result = mysqli_query($conn, $sql);
            
            if(mysqli_num_rows($result) > 0) {
                while($row = mysqli_fetch_assoc($result)) {
            ?>
            <div class="product-card">
                <div class="product-image">
                    <img src="<?php echo $row['image_url'] ?: 'images/default-candle.jpg'; ?>" alt="<?php echo $row['name']; ?>">
                </div>
                <div class="product-info">
                    <h3><?php echo $row['name']; ?></h3>
                    <p><?php echo substr($row['description'], 0, 100) . '...'; ?></p>
                    <div class="product-price">
                        <span class="price"><?php echo format_price($row['price']); ?></span>
                        <button class="add-to-cart" data-id="<?php echo $row['id']; ?>" data-name="<?php echo $row['name']; ?>" data-price="<?php echo $row['price']; ?>" data-price-inr="<?php echo htmlspecialchars(format_price($row['price'])); ?>">
                            <i class="fas fa-cart-plus"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php
                }
            } else {
                echo "<p>No products available at the moment.</p>";
            }
            ?>
        </div>
        <div class="text-center">
            <a href="shop.php" class="btn">View All Products</a>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="section bg-light">
    <div class="container">
        <h2 class="section-title">Why Choose Our Candles</h2>
        <div class="features-grid">
            <div class="feature">
                <div class="feature-icon">
                    <i class="fas fa-leaf"></i>
                </div>
                <h3>Eco-Friendly</h3>
                <p>Made with sustainable, natural ingredients that are kind to the environment.</p>
            </div>
            <div class="feature">
                <div class="feature-icon">
                    <i class="fas fa-hand-sparkles"></i>
                </div>
                <h3>Handcrafted</h3>
                <p>Each candle is carefully hand-poured with attention to detail and quality.</p>
            </div>
            <div class="feature">
                <div class="feature-icon">
                    <i class="fas fa-smile"></i>
                </div>
                <h3>Premium Scents</h3>
                <p>Expertly blended fragrances that create the perfect ambiance for any occasion.</p>
            </div>
            <div class="feature">
                <div class="feature-icon">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <h3>Free Shipping</h3>
                <p>Free shipping on all orders over $50 within the continental United States.</p>
            </div>
        </div>
    </div>
</section>
<!-- Newsletter -->


<?php include 'includes/footer.php'; ?>


</body>
</html>
