<?php
require_once 'includes/config.php';
$page_title = "Product Details";
include 'includes/header.php';

// Get product ID
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($product_id <= 0) {
    header('Location: shop.php');
    exit();
}

// Get product details
$sql = "SELECT p.*, 
        (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') 
         FROM product_categories pc 
         JOIN categories c ON pc.category_id = c.id 
         WHERE pc.product_id = p.id) as categories
        FROM products p 
        WHERE p.id = ? AND p.is_active = 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if(!$product) {
    header('Location: shop.php');
    exit();
}

// Get product images
$images_sql = "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC";
$images_stmt = mysqli_prepare($conn, $images_sql);
mysqli_stmt_bind_param($images_stmt, 'i', $product_id);
mysqli_stmt_execute($images_stmt);
$images_result = mysqli_stmt_get_result($images_stmt);
$product_images = mysqli_fetch_all($images_result, MYSQLI_ASSOC);

// Get reviews
$reviews_sql = "SELECT r.*, u.first_name, u.last_name 
                FROM reviews r 
                LEFT JOIN users u ON r.user_id = u.id 
                WHERE r.product_id = ? AND r.is_approved = 1 
                ORDER BY r.created_at DESC 
                LIMIT 5";
$reviews_stmt = mysqli_prepare($conn, $reviews_sql);
mysqli_stmt_bind_param($reviews_stmt, 'i', $product_id);
mysqli_stmt_execute($reviews_stmt);
$reviews_result = mysqli_stmt_get_result($reviews_stmt);
$reviews = mysqli_fetch_all($reviews_result, MYSQLI_ASSOC);

// Get average rating
$rating_sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
               FROM reviews 
               WHERE product_id = ? AND is_approved = 1";
$rating_stmt = mysqli_prepare($conn, $rating_sql);
mysqli_stmt_bind_param($rating_stmt, 'i', $product_id);
mysqli_stmt_execute($rating_stmt);
$rating_result = mysqli_stmt_get_result($rating_stmt);
$rating_data = ['avg_rating' => 0, 'review_count' => 0];
if ($rating_result) {
    $tmp = mysqli_fetch_assoc($rating_result);
    if ($tmp) {
        $rating_data = $tmp;
    }
}

// Get related products
$related_sql = "SELECT p.* 
                FROM products p
                WHERE p.id != ? 
                AND (p.category = ? OR p.scent = ?) 
                AND p.is_active = 1 
                LIMIT 4";
$related_stmt = mysqli_prepare($conn, $related_sql);
mysqli_stmt_bind_param($related_stmt, 'iss', $product_id, $product['category'], $product['scent']);
mysqli_stmt_execute($related_stmt);
$related_result = mysqli_stmt_get_result($related_stmt);
$related_products = mysqli_fetch_all($related_result, MYSQLI_ASSOC);

// If no related products, get featured products
if(empty($related_products)) {
    $featured_sql = "SELECT * FROM products WHERE is_featured = 1 AND is_active = 1 AND id != ? LIMIT 4";
    $featured_stmt = mysqli_prepare($conn, $featured_sql);
    mysqli_stmt_bind_param($featured_stmt, 'i', $product_id);
    mysqli_stmt_execute($featured_stmt);
    $featured_result = mysqli_stmt_get_result($featured_stmt);
    $related_products = mysqli_fetch_all($featured_result, MYSQLI_ASSOC);
}

// Update page title
$page_title = $product['name'] . " - " . SITE_NAME;

// Helper: check if a stored image URL points to an existing file (or is absolute URL)
function image_is_valid($url) {
    if (empty($url)) return false;
    // Absolute URL (http/https)
    if (preg_match('#^https?://#i', $url)) return true;

    // Normalize server path
    $candidates = [];

    // 1) Path relative to DOCUMENT_ROOT
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $candidates[] = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') . '/' . ltrim($url, '/');
    }

    // 2) Path relative to project root (one level up from includes/)
    $projectRoot = realpath(__DIR__ . '/..');
    if ($projectRoot) {
        $candidates[] = rtrim(str_replace('\\', '/', $projectRoot), '/') . '/' . ltrim($url, '/');
    }

    // 3) Path relative to current script directory
    $currentDir = realpath(__DIR__);
    if ($currentDir) {
        $candidates[] = rtrim(str_replace('\\', '/', $currentDir), '/') . '/' . ltrim($url, '/');
    }

    // 4) Direct path as given (useful if $url is already absolute filesystem path)
    $candidates[] = $url;

    foreach ($candidates as $path) {
        if (@file_exists($path)) {
            return true;
        }
    }

    return false;
}

// Handle add to cart
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $quantity = intval($_POST['quantity']);
    
    if($quantity > 0 && $quantity <= $product['in_stock']) {
        if(!isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] = [
                'quantity' => $quantity,
                'added_at' => time()
            ];
        } else {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        }
        
        $success_message = "{$product['name']} added to cart!";
    } else {
        $error_message = "Invalid quantity selected.";
    }
}

// Handle wishlist
if(isset($_GET['add_to_wishlist']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $check_sql = "SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, 'ii', $user_id, $product_id);
    mysqli_stmt_execute($check_stmt);
    
    if(mysqli_stmt_get_result($check_stmt)->num_rows == 0) {
        $insert_sql = "INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, 'ii', $user_id, $product_id);
        mysqli_stmt_execute($insert_stmt);
        $wishlist_success = "Added to wishlist!";
    }
}
?>

<div class="container">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <a href="index.php">Home</a>
        <i class="fas fa-chevron-right"></i>
        <a href="shop.php">Shop</a>
        <i class="fas fa-chevron-right"></i>
        <?php if($product['category']): ?>
            <a href="shop.php?category=<?php echo urlencode($product['category']); ?>">
                <?php echo htmlspecialchars($product['category']); ?>
            </a>
            <i class="fas fa-chevron-right"></i>
        <?php endif; ?>
        <span><?php echo htmlspecialchars($product['name']); ?></span>
    </nav>
    
    <?php if(isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            <a href="cart.php" class="btn btn-small">View Cart</a>
        </div>
    <?php endif; ?>
    
    <?php if(isset($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if(isset($wishlist_success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $wishlist_success; ?>
        </div>
    <?php endif; ?>
    
    <div class="product-detail">
        <!-- Product Images -->
        <div class="product-gallery">
            <!-- Main Image -->
            <div class="main-image">
                <?php
                // Prefer the first valid product image from product_images. If none valid, fall back to product.image_url
                $mainImage = '';
                if (!empty($product_images)) {
                    foreach ($product_images as $img) {
                        if (!empty($img['image_url']) && image_is_valid($img['image_url'])) {
                            $mainImage = $img['image_url'];
                            break;
                        }
                    }
                }

                if (empty($mainImage) && !empty($product['image_url']) && image_is_valid($product['image_url'])) {
                    $mainImage = $product['image_url'];
                }

                if (!empty($mainImage)): ?>
                    <img src="<?php echo $mainImage; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" id="mainProductImage">
                <?php else: ?>
                    <div class="no-image-large">No Image Available</div>
                <?php endif; ?>
                
                <?php if($product['in_stock'] == 0): ?>
                    <div class="out-of-stock-badge">Out of Stock</div>
                <?php elseif($product['in_stock'] <= 10): ?>
                    <div class="low-stock-badge">Only <?php echo $product['in_stock']; ?> left!</div>
                <?php endif; ?>
            </div>
            
            <!-- Thumbnails -->
            <?php
            // Show thumbnails only for valid image files
            $validThumbnails = [];
            if (!empty($product_images)) {
                foreach ($product_images as $img) {
                    if (!empty($img['image_url']) && image_is_valid($img['image_url'])) {
                        $validThumbnails[] = $img;
                    }
                }
            }

            if (count($validThumbnails) > 1): ?>
            <div class="image-thumbnails">
                <?php foreach($validThumbnails as $index => $image): ?>
                <div class="thumbnail <?php echo $index == 0 ? 'active' : ''; ?>" data-image="<?php echo $image['image_url']; ?>">
                    <img src="<?php echo $image['image_url']; ?>" alt="<?php echo htmlspecialchars($image['alt_text'] ?? $product['name']); ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Product Info -->
        <div class="product-info">
            <div class="product-header">
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="product-meta">
                    <?php if($product['categories']): ?>
                        <div class="product-categories">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($product['categories']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="product-sku">
                        <i class="fas fa-hashtag"></i>
                        SKU: <?php echo $product['sku'] ?? 'N/A'; ?>
                    </div>
                </div>
                
                <div class="product-rating-overview">
                    <div class="stars">
                        <?php
                        $avg_rating = $rating_data['avg_rating'] ?? 0;
                        $review_count = $rating_data['review_count'] ?? 0;
                        $full_stars = floor($avg_rating);
                        $has_half_star = ($avg_rating - $full_stars) >= 0.5;
                        
                        for($i = 1; $i <= 5; $i++):
                            if($i <= $full_stars): ?>
                                <i class="fas fa-star"></i>
                            <?php elseif($i == $full_stars + 1 && $has_half_star): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif;
                        endfor;
                        ?>
                    </div>
                    <a href="#reviews" class="review-count">
                        <?php echo $review_count; ?> review<?php echo $review_count != 1 ? 's' : ''; ?>
                    </a>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="#write-review" class="write-review-link">Write a review</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="product-price-section">
                <div class="price">
                    <span class="current-price"><?php echo format_price($product['price']); ?></span>
                    <?php if($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                        <span class="compare-price"><?php echo format_price($product['compare_price']); ?></span>
                        <span class="discount-badge">
                            Save <?php echo number_format((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100, 0); ?>%
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="stock-status">
                    <?php if($product['in_stock'] > 0): ?>
                        <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                        <span>In Stock</span>
                        <?php if($product['in_stock'] <= 10): ?>
                            <span class="low-stock-text">(Only <?php echo $product['in_stock']; ?> left)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <i class="fas fa-times-circle" style="color: var(--danger-color);"></i>
                        <span>Out of Stock</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="product-description">
                <h3>Description</h3>
                <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                
                <?php if($product['short_description']): ?>
                    <p class="short-description"><?php echo htmlspecialchars($product['short_description']); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Product Specifications -->
            <div class="product-specifications">
                <h3>Specifications</h3>
                <div class="specs-grid">
                    <div class="spec-item">
                        <div class="spec-label">Scent</div>
                        <div class="spec-value"><?php echo htmlspecialchars($product['scent']); ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Burn Time</div>
                        <div class="spec-value"><?php echo $product['burn_time_hours']; ?> hours</div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Weight</div>
                        <div class="spec-value"><?php echo $product['weight_grams']; ?> grams</div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Dimensions</div>
                        <div class="spec-value"><?php echo htmlspecialchars($product['dimensions'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Wax Type</div>
                        <div class="spec-value"><?php echo htmlspecialchars($product['material'] ?? 'Soy Wax'); ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Wick Type</div>
                        <div class="spec-value"><?php echo htmlspecialchars($product['wick_type'] ?? 'Cotton'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Add to Cart Form -->
            <form method="POST" action="" class="add-to-cart-form">
                <?php if($product['in_stock'] > 0): ?>
                    <div class="quantity-selector">
                        <label for="quantity">Quantity:</label>
                        <div class="quantity-control">
                            <button type="button" class="quantity-btn minus">-</button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo min($product['in_stock'], 10); ?>">
                            <button type="button" class="quantity-btn plus">+</button>
                        </div>
                        <span class="max-quantity">Max <?php echo min($product['in_stock'], 10); ?> per order</span>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" name="add_to_cart" class="btn btn-primary btn-add-to-cart">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                        
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <a href="?id=<?php echo $product_id; ?>&add_to_wishlist=1" class="btn btn-secondary btn-wishlist">
                                <i class="far fa-heart"></i> Add to Wishlist
                            </a>
                        <?php else: ?>
                            <a href="login.php?redirect=product.php?id=<?php echo $product_id; ?>" class="btn btn-secondary">
                                <i class="far fa-heart"></i> Login to Wishlist
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="out-of-stock-actions">
                        <button type="button" class="btn btn-secondary" id="notifyMeBtn">
                            <i class="fas fa-bell"></i> Notify When Available
                        </button>
                        <a href="custom-candle.php" class="btn btn-primary">
                            <i class="fas fa-palette"></i> Create Custom Candle
                        </a>
                    </div>
                <?php endif; ?>
            </form>
            
            <!-- Product Features -->
            <div class="product-features">
                <div class="feature">
                    <i class="fas fa-shipping-fast"></i>
                    <div>
                        <h4>Free Shipping</h4>
                        <p>On orders over $50</p>
                    </div>
                </div>
                <div class="feature">
                    <i class="fas fa-leaf"></i>
                    <div>
                        <h4>Eco-Friendly</h4>
                        <p>100% natural ingredients</p>
                    </div>
                </div>
                <div class="feature">
                    <i class="fas fa-undo"></i>
                    <div>
                        <h4>30-Day Returns</h4>
                        <p>Hassle-free returns</p>
                    </div>
                </div>
                <div class="feature">
                    <i class="fas fa-headset"></i>
                    <div>
                        <h4>Support 24/7</h4>
                        <p>Dedicated support</p>
                    </div>
                </div>
            </div>
            
            <!-- Share Product -->
            <div class="product-share">
                <span>Share:</span>
                <div class="social-share">
                    <a href="https://facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . 'product.php?id=' . $product_id); ?>" 
                       target="_blank" class="social-btn facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://pinterest.com/pin/create/button/?url=<?php echo urlencode(SITE_URL . 'product.php?id=' . $product_id); ?>&media=<?php echo urlencode($product['image_url'] ?? ''); ?>&description=<?php echo urlencode($product['name']); ?>" 
                       target="_blank" class="social-btn pinterest">
                        <i class="fab fa-pinterest-p"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Check out ' . $product['name'] . ' at ' . SITE_NAME); ?>&url=<?php echo urlencode(SITE_URL . 'product.php?id=' . $product_id); ?>" 
                       target="_blank" class="social-btn twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="mailto:?subject=<?php echo urlencode($product['name']); ?>&body=<?php echo urlencode('Check out this product: ' . SITE_URL . 'product.php?id=' . $product_id); ?>" 
                       class="social-btn email">
                        <i class="fas fa-envelope"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Tabs -->
    <div class="product-tabs">
        <div class="tab-headers">
            <button class="tab-header active" data-tab="description">Description</button>
            <button class="tab-header" data-tab="specifications">Specifications</button>
            <button class="tab-header" data-tab="reviews" id="reviews">Reviews (<?php echo $review_count; ?>)</button>
            <button class="tab-header" data-tab="shipping">Shipping & Returns</button>
            <button class="tab-header" data-tab="faq">FAQ</button>
        </div>
        
        <div class="tab-contents">
            <!-- Description Tab -->
            <div class="tab-content active" id="tab-description">
                <div class="detailed-description">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    
                    <div class="description-features">
                        <h3>Key Features</h3>
                        <ul>
                            <li><i class="fas fa-check"></i> Hand-poured with premium ingredients</li>
                            <li><i class="fas fa-check"></i> Long-lasting burn time</li>
                            <li><i class="fas fa-check"></i> Even wax pool for optimal fragrance throw</li>
                            <li><i class="fas fa-check"></i> Clean-burning, lead-free wick</li>
                            <li><i class="fas fa-check"></i> Eco-friendly packaging</li>
                        </ul>
                    </div>
                    
                    <div class="candle-care">
                        <h3>Candle Care Tips</h3>
                        <div class="care-tips">
                            <div class="tip">
                                <i class="fas fa-fire"></i>
                                <h4>First Burn</h4>
                                <p>Allow candle to burn until wax melts to edges (3-4 hours)</p>
                            </div>
                            <div class="tip">
                                <i class="fas fa-cut"></i>
                                <h4>Wick Maintenance</h4>
                                <p>Trim wick to ¼ inch before each burn</p>
                            </div>
                            <div class="tip">
                                <i class="fas fa-wind"></i>
                                <h4>Burn Time</h4>
                                <p>Never burn for more than 4 hours at a time</p>
                            </div>
                            <div class="tip">
                                <i class="fas fa-home"></i>
                                <h4>Safety</h4>
                                <p>Keep away from drafts, children, and pets</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Specifications Tab -->
            <div class="tab-content" id="tab-specifications">
                <div class="specifications-table">
                    <table>
                        <tr>
                            <th>Specification</th>
                            <th>Details</th>
                        </tr>
                        <tr>
                            <td>Product Name</td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                        </tr>
                        <tr>
                            <td>Category</td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                        </tr>
                        <tr>
                            <td>Scent</td>
                            <td><?php echo htmlspecialchars($product['scent']); ?></td>
                        </tr>
                        <tr>
                            <td>Burn Time</td>
                            <td><?php echo $product['burn_time_hours']; ?> hours</td>
                        </tr>
                        <tr>
                            <td>Weight</td>
                            <td><?php echo $product['weight_grams']; ?> grams</td>
                        </tr>
                        <tr>
                            <td>Dimensions</td>
                            <td><?php echo htmlspecialchars($product['dimensions'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td>Wax Material</td>
                            <td><?php echo htmlspecialchars($product['material'] ?? '100% Natural Soy Wax'); ?></td>
                        </tr>
                        <tr>
                            <td>Wick Type</td>
                            <td><?php echo htmlspecialchars($product['wick_type'] ?? 'Lead-free Cotton'); ?></td>
                        </tr>
                        <tr>
                            <td>Fragrance Load</td>
                            <td>8-10% premium fragrance oils</td>
                        </tr>
                        <tr>
                            <td>Country of Origin</td>
                            <td>Made in USA</td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Reviews Tab -->
            <div class="tab-content" id="tab-reviews">
                <div class="reviews-container">
                    <!-- Review Summary -->
                    <div class="review-summary">
                        <div class="overall-rating">
                            <div class="average-rating">
                                <span class="rating-number"><?php echo number_format($avg_rating, 1); ?></span>
                                <div class="stars">
                                    <?php for($i = 1; $i <= 5; $i++):
                                        if($i <= $full_stars): ?>
                                            <i class="fas fa-star"></i>
                                        <?php elseif($i == $full_stars + 1 && $has_half_star): ?>
                                            <i class="fas fa-star-half-alt"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif;
                                    endfor; ?>
                                </div>
                                <p><?php echo $review_count; ?> reviews</p>
                            </div>
                            
                            <div class="rating-breakdown">
                                <?php
                                $rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
                                $ratings_sql = "SELECT rating, COUNT(*) as count FROM reviews WHERE product_id = ? AND is_approved = 1 GROUP BY rating";
                                $ratings_stmt = mysqli_prepare($conn, $ratings_sql);
                                mysqli_stmt_bind_param($ratings_stmt, 'i', $product_id);
                                mysqli_stmt_execute($ratings_stmt);
                                $ratings_result = mysqli_stmt_get_result($ratings_stmt);
                                
                                while($row = mysqli_fetch_assoc($ratings_result)) {
                                    $rating_counts[$row['rating']] = $row['count'];
                                }
                                
                                for($i = 5; $i >= 1; $i--):
                                    $count = $rating_counts[$i];
                                    $percentage = $review_count > 0 ? ($count / $review_count) * 100 : 0;
                                ?>
                                <div class="rating-bar">
                                    <span class="stars-small">
                                        <?php for($j = 1; $j <= 5; $j++): ?>
                                            <i class="<?php echo $j <= $i ? 'fas' : 'far'; ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </span>
                                    <div class="bar-container">
                                        <div class="bar" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <span class="rating-count"><?php echo $count; ?></span>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <!-- Write Review Button -->
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <div class="write-review-btn-container" id="write-review">
                                <button class="btn btn-primary" id="writeReviewBtn">
                                    <i class="fas fa-pen"></i> Write a Review
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="login-to-review">
                                <p>Please <a href="login.php?redirect=product.php?id=<?php echo $product_id; ?>#reviews">login</a> to write a review.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Reviews List -->
                    <div class="reviews-list">
                        <?php if(empty($reviews)): ?>
                            <div class="no-reviews">
                                <i class="fas fa-comment"></i>
                                <h4>No reviews yet</h4>
                                <p>Be the first to review this product!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="reviewer-info">
                                        <div class="reviewer-avatar">
                                            <?php echo strtoupper(substr($review['first_name'] ?? 'U', 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h4><?php echo htmlspecialchars(($review['first_name'] ?? 'User') . ' ' . ($review['last_name'] ?? '')); ?></h4>
                                            <div class="review-meta">
                                                <div class="stars">
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                        <i class="<?php echo $i <= $review['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <span class="review-date">
                                                    <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="review-content">
                                    <?php if($review['title']): ?>
                                        <h5><?php echo htmlspecialchars($review['title']); ?></h5>
                                    <?php endif; ?>
                                    <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                </div>
                                
                                <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                                    <div class="review-actions">
                                        <button class="btn-small approve-review" data-id="<?php echo $review['id']; ?>">
                                            <?php echo $review['is_approved'] ? 'Unapprove' : 'Approve'; ?>
                                        </button>
                                        <button class="btn-small btn-danger delete-review" data-id="<?php echo $review['id']; ?>">
                                            Delete
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if($review_count > 5): ?>
                                <div class="view-all-reviews">
                                    <a href="reviews.php?product_id=<?php echo $product_id; ?>" class="btn btn-secondary">
                                        View All Reviews
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Write Review Form (Hidden by default) -->
                    <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="write-review-form" id="writeReviewForm" style="display: none;">
                        <h3>Write Your Review</h3>
                        <form method="POST" action="submit-review.php">
                            <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                            
                            <div class="form-group">
                                <label>Rating *</label>
                                <div class="rating-input">
                                    <div class="stars-select">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="far fa-star" data-rating="<?php echo $i; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <input type="hidden" name="rating" id="selectedRating" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="reviewTitle">Review Title</label>
                                <input type="text" id="reviewTitle" name="title" placeholder="Summarize your experience">
                            </div>
                            
                            <div class="form-group">
                                <label for="reviewComment">Your Review *</label>
                                <textarea id="reviewComment" name="comment" rows="5" 
                                          placeholder="Share your experience with this product..." required></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Submit Review</button>
                                <button type="button" class="btn btn-secondary" id="cancelReviewBtn">Cancel</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Shipping Tab -->
            <div class="tab-content" id="tab-shipping">
                <div class="shipping-info">
                    <h3>Shipping Information</h3>
                    <div class="shipping-details">
                        <?php
                        // Display shipping prices using site currency. Values originally in USD.
                        $std_usd = 5.99;
                        $exp_usd = 14.99;
                        $ov_usd  = 24.99;
                        $free_over_usd = 50;

                        if (defined('USD_TO_INR') && USD_TO_INR > 0) {
                            $std_inr = round($std_usd * USD_TO_INR);
                            $exp_inr = round($exp_usd * USD_TO_INR);
                            $ov_inr  = round($ov_usd * USD_TO_INR);
                            $free_over_inr = round($free_over_usd * USD_TO_INR);
                        } else {
                            // Fallback: treat given values as INR and round
                            $std_inr = round($std_usd);
                            $exp_inr = round($exp_usd);
                            $ov_inr  = round($ov_usd);
                            $free_over_inr = round($free_over_usd);
                        }
                        ?>

                        <div class="shipping-method">
                            <h4><i class="fas fa-shipping-fast"></i> Standard Shipping</h4>
                            <p>3-7 business days • <?php echo CURRENCY . number_format($std_inr); ?></p>
                            <p class="free-shipping-note">Free on orders over <?php echo CURRENCY . number_format($free_over_inr); ?></p>
                        </div>
                        <div class="shipping-method">
                            <h4><i class="fas fa-rocket"></i> Express Shipping</h4>
                            <p>2-3 business days • <?php echo CURRENCY . number_format($exp_inr); ?></p>
                        </div>
                        <div class="shipping-method">
                            <h4><i class="fas fa-plane"></i> Overnight Shipping</h4>
                            <p>1-2 business days • <?php echo CURRENCY . number_format($ov_inr); ?></p>
                        </div>
                    </div>
                    
                    <h3>Return Policy</h3>
                    <div class="return-policy">
                        <p>We offer a 30-day return policy for unused items in original packaging.</p>
                        <ul>
                            <li>Items must be returned within 30 days of delivery</li>
                            <li>Products must be unused and in original condition</li>
                            <li>Original packaging must be intact</li>
                            <li>Return shipping is the responsibility of the customer</li>
                            <li>Refunds will be processed within 5-7 business days</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- FAQ Tab -->
            <div class="tab-content" id="tab-faq">
                <div class="product-faq">
                    <h3>Frequently Asked Questions</h3>
                    <div class="faq-accordion">
                        <div class="faq-item">
                            <button class="faq-question">
                                How long does this candle burn?
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="faq-answer">
                                <p>This candle has a burn time of approximately <?php echo $product['burn_time_hours']; ?> hours, depending on burning conditions.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <button class="faq-question">
                                Is this candle safe for pets?
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="faq-answer">
                                <p>Our candles are made with pet-safe ingredients, but we recommend keeping all candles out of reach of pets and never leaving them unattended.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <button class="faq-question">
                                What wax is used in this candle?
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="faq-answer">
                                <p>This candle is made with <?php echo htmlspecialchars($product['material'] ?? '100% natural soy wax'); ?>, which is clean-burning and eco-friendly.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <button class="faq-question">
                                Can I customize this candle?
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="faq-answer">
                                <p>Yes! Visit our <a href="custom-candle.php">Custom Candle</a> page to create a personalized version of this candle with your preferred scents, colors, and labels.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <button class="faq-question">
                                How should I care for my candle?
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="faq-answer">
                                <p>Trim the wick to ¼ inch before each burn, allow the wax to melt to the edges on first use, and never burn for more than 4 hours at a time. Keep away from drafts and flammable materials.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Related Products -->
    <div class="related-products">
        <h2>You May Also Like</h2>
        <div class="products-grid">
            <?php if(empty($related_products)): ?>
                <p class="no-related">No related products found.</p>
            <?php else: ?>
                <?php foreach($related_products as $related): ?>
                <div class="product-card">
                    <a href="product.php?id=<?php echo $related['id']; ?>" class="product-image">
                        <?php if($related['image_url']): ?>
                            <img src="<?php echo $related['image_url']; ?>" alt="<?php echo htmlspecialchars($related['name']); ?>">
                        <?php else: ?>
                            <div class="no-image">No Image</div>
                        <?php endif; ?>
                    </a>
                    <div class="product-info">
                        <div class="product-category"><?php echo htmlspecialchars($related['category']); ?></div>
                        <h3 class="product-name">
                            <a href="product.php?id=<?php echo $related['id']; ?>"><?php echo htmlspecialchars($related['name']); ?></a>
                        </h3>
                        <div class="product-scent"><?php echo htmlspecialchars($related['scent']); ?></div>
                        <div class="product-price">
                            <span class="current-price"><?php echo format_price($related['price']); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?php echo SITE_URL; ?>js/product.js"></script>
<?php include 'includes/footer.php'; ?>