<?php
require_once 'includes/config.php';
$page_title = "All Products";
include 'includes/header.php';

// Get filter parameters
$category = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$scent = isset($_GET['scent']) ? sanitize_input($_GET['scent']) : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 1000;
$sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'newest';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 12; // Products per page
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = ["p.is_active = 1"];
$params = [];
$param_types = '';

if (!empty($category)) {
    $where_conditions[] = "(p.category = ? OR c.name = ?)";
    $params[] = $category;
    $params[] = $category;
    $param_types .= 'ss';
}

if (!empty($scent)) {
    $where_conditions[] = "p.scent LIKE ?";
    $params[] = "%$scent%";
    $param_types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ? OR p.scent LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= 'ssss';
}

$where_conditions[] = "p.price BETWEEN ? AND ?";
$params[] = $min_price;
$params[] = $max_price;
$param_types .= 'dd';

$where_clause = implode(' AND ', $where_conditions);

// Sort options
$sort_options = [
    'newest' => 'p.created_at DESC',
    'price_low' => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'name' => 'p.name ASC',
    'popular' => 'p.rating DESC, p.review_count DESC'
];
$order_by = $sort_options[$sort] ?? 'p.created_at DESC';

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT p.id) as total 
              FROM products p
              LEFT JOIN product_categories pc ON p.id = pc.product_id
              LEFT JOIN categories c ON pc.category_id = c.id
              WHERE $where_clause";

$stmt = mysqli_prepare($conn, $count_sql);
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_row = mysqli_fetch_assoc($result);
    $total_products = $total_row['total'];
    $total_pages = ceil($total_products / $limit);
} else {
    $total_products = 0;
    $total_pages = 1;
}

// Get products
$sql = "SELECT DISTINCT p.*, 
        (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
        FROM products p
        LEFT JOIN product_categories pc ON p.id = pc.product_id
        LEFT JOIN categories c ON pc.category_id = c.id
        WHERE $where_clause
        ORDER BY $order_by
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $products = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $products = [];
}

// Get filter data
$categories = [];
$scents = [];
$price_range = [];

// Get categories
$cat_sql = "SELECT DISTINCT p.category 
            FROM products p 
            WHERE p.is_active = 1 AND p.category IS NOT NULL AND p.category != ''
            ORDER BY p.category";
$cat_result = mysqli_query($conn, $cat_sql);
if ($cat_result) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $categories[] = $row['category'];
    }
}

// Get scents
$scent_sql = "SELECT DISTINCT p.scent 
              FROM products p 
              WHERE p.is_active = 1 AND p.scent IS NOT NULL AND p.scent != ''
              ORDER BY p.scent";
$scent_result = mysqli_query($conn, $scent_sql);
if ($scent_result) {
    while ($row = mysqli_fetch_assoc($scent_result)) {
        $scents[] = $row['scent'];
    }
}

// Get price range
$price_sql = "SELECT MIN(price) as min_price, MAX(price) as max_price 
              FROM products 
              WHERE is_active = 1";
$price_result = mysqli_query($conn, $price_sql);
if ($price_result) {
    $price_range = mysqli_fetch_assoc($price_result);
}
?>

<div class="container">
    <div class="shop-header">
        <h1 class="page-title">All Candles</h1>
        <p class="shop-subtitle">Discover our complete collection of handcrafted candles</p>
        
        <!-- Search Bar -->
        <div class="shop-search">
            <form method="GET" action="" class="search-form">
                <div class="search-input-group">
                    <input type="text" name="search" placeholder="Search candles..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <?php if(!empty($category)): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <?php endif; ?>
                <?php if(!empty($scent)): ?>
                    <input type="hidden" name="scent" value="<?php echo htmlspecialchars($scent); ?>">
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <div class="shop-container">
        <!-- Filters Sidebar -->
        <aside class="shop-filters">
            <div class="filters-header">
                <h3>Filters</h3>
                <a href="shop.php" class="clear-filters">Clear All</a>
            </div>
            
            <!-- Categories Filter -->
            <div class="filter-section">
                <h4>Categories</h4>
                <ul class="filter-list">
                    <li>
                        <a href="?<?php echo build_query_string(['category' => '']); ?>" 
                           class="<?php echo empty($category) ? 'active' : ''; ?>">
                           All Categories
                        </a>
                    </li>
                    <?php foreach($categories as $cat): ?>
                    <li>
                        <a href="?<?php echo build_query_string(['category' => $cat]); ?>" 
                           class="<?php echo $category == $cat ? 'active' : ''; ?>">
                           <?php echo htmlspecialchars($cat); ?>
                           <span class="filter-count"><?php echo get_category_count($cat); ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Scents Filter -->
            <div class="filter-section">
                <h4>Scents</h4>
                <ul class="filter-list">
                    <li>
                        <a href="?<?php echo build_query_string(['scent' => '']); ?>" 
                           class="<?php echo empty($scent) ? 'active' : ''; ?>">
                           All Scents
                        </a>
                    </li>
                    <?php foreach($scents as $scent_option): ?>
                    <li>
                        <a href="?<?php echo build_query_string(['scent' => $scent_option]); ?>" 
                           class="<?php echo $scent == $scent_option ? 'active' : ''; ?>">
                           <?php echo htmlspecialchars($scent_option); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Price Filter -->
            <div class="filter-section">
                <h4>Price Range</h4>
                <div class="price-filter">
                    <div class="price-inputs">
                        <div class="price-input">
                            <label>Min: $</label>
                            <input type="number" id="minPrice" value="<?php echo $min_price; ?>" min="0" max="<?php echo $price_range['max_price'] ?? 1000; ?>">
                        </div>
                        <div class="price-input">
                            <label>Max: $</label>
                            <input type="number" id="maxPrice" value="<?php echo $max_price; ?>" min="0" max="<?php echo $price_range['max_price'] ?? 1000; ?>">
                        </div>
                    </div>
                    <div class="price-slider">
                        <div class="slider-track"></div>
                        <input type="range" id="minPriceSlider" min="0" max="<?php echo $price_range['max_price'] ?? 1000; ?>" 
                               value="<?php echo $min_price; ?>" class="range-min">
                        <input type="range" id="maxPriceSlider" min="0" max="<?php echo $price_range['max_price'] ?? 1000; ?>" 
                               value="<?php echo $max_price; ?>" class="range-max">
                    </div>
                    <button type="button" id="applyPriceFilter" class="btn btn-small">Apply</button>
                </div>
            </div>
            
            <!-- Availability Filter -->
            <div class="filter-section">
                <h4>Availability</h4>
                <div class="availability-filter">
                    <label class="checkbox-option">
                        <input type="checkbox" id="inStockOnly" <?php echo isset($_GET['instock']) ? 'checked' : ''; ?>>
                        <span class="checkmark"></span>
                        In Stock Only
                    </label>
                </div>
            </div>
            
            <!-- Featured Products -->
            <div class="featured-products-sidebar">
                <h4>Featured Candles</h4>
                <?php
                $featured_sql = "SELECT * FROM products WHERE is_featured = 1 AND is_active = 1 LIMIT 3";
                $featured_result = mysqli_query($conn, $featured_sql);
                if(mysqli_num_rows($featured_result) > 0):
                    while($featured = mysqli_fetch_assoc($featured_result)):
                ?>
                <div class="featured-product">
                    <a href="product.php?id=<?php echo $featured['id']; ?>" class="featured-product-image">
                        <?php if($featured['image_url']): ?>
                            <img src="<?php echo $featured['image_url']; ?>" alt="<?php echo htmlspecialchars($featured['name']); ?>">
                        <?php else: ?>
                            <div class="no-image-small">No Image</div>
                        <?php endif; ?>
                    </a>
                    <div class="featured-product-info">
                        <h5><a href="product.php?id=<?php echo $featured['id']; ?>"><?php echo htmlspecialchars($featured['name']); ?></a></h5>
                        <div class="featured-price"><?php echo format_price($featured['price']); ?></div>
                    </div>
                </div>
                <?php endwhile; endif; ?>
            </div>
        </aside>
        
        <!-- Products Grid -->
        <main class="shop-main">
            <!-- Shop Controls -->
            <div class="shop-controls">
                <div class="results-count">
                    <p>Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $limit, $total_products); ?> of <?php echo $total_products; ?> products</p>
                </div>
                
                <div class="sort-controls">
                    <label for="sortSelect">Sort by:</label>
                    <select id="sortSelect" class="sort-select">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest</option>
                        <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Name: A-Z</option>
                        <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                    </select>
                    
                    <div class="view-toggle">
                        <button class="view-btn active" data-view="grid">
                            <i class="fas fa-th"></i>
                        </button>
                        <button class="view-btn" data-view="list">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Products Grid/List -->
            <div class="products-view" id="productsView">
                <?php if(empty($products)): ?>
                    <div class="no-products">
                        <i class="fas fa-search"></i>
                        <h3>No products found</h3>
                        <p>Try adjusting your search or filter to find what you're looking for.</p>
                        <a href="shop.php" class="btn">View All Products</a>
                    </div>
                <?php else: ?>
                    <div class="products-grid" id="productsGrid">
                        <?php foreach($products as $product): ?>
                        <div class="product-card">
                            <div class="product-badges">
                                <?php if($product['is_featured']): ?>
                                    <span class="badge featured">Featured</span>
                                <?php endif; ?>
                                <?php if($product['in_stock'] <= 10 && $product['in_stock'] > 0): ?>
                                    <span class="badge low-stock">Low Stock</span>
                                <?php elseif($product['in_stock'] == 0): ?>
                                    <span class="badge out-of-stock">Out of Stock</span>
                                <?php endif; ?>
                            </div>
                            
                            <a href="product.php?id=<?php echo $product['id']; ?>" class="product-image">
                                <?php if($product['primary_image']): ?>
                                    <img src="<?php echo $product['primary_image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php elseif($product['image_url']): ?>
                                    <img src="<?php echo $product['image_url']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <div class="no-image">No Image</div>
                                <?php endif; ?>
                                <?php if($product['in_stock'] == 0): ?>
                                    <div class="out-of-stock-overlay">Out of Stock</div>
                                <?php endif; ?>
                            </a>
                            
                            <div class="product-info">
                                <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                                <h3 class="product-name">
                                    <a href="product.php?id=<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a>
                                </h3>
                                
                                <div class="product-scent">
                                    <i class="fas fa-wind"></i>
                                    <span><?php echo htmlspecialchars($product['scent']); ?></span>
                                </div>
                                
                                <div class="product-specs">
                                    <div class="spec">
                                        <i class="fas fa-fire"></i>
                                        <span><?php echo $product['burn_time_hours']; ?>h burn</span>
                                    </div>
                                    <div class="spec">
                                        <i class="fas fa-weight"></i>
                                        <span><?php echo $product['weight_grams']; ?>g</span>
                                    </div>
                                </div>
                                
                                <div class="product-rating">
                                    <div class="stars">
                                        <?php
                                        $rating = $product['rating'] ?? 0;
                                        $full_stars = floor($rating);
                                        $has_half_star = ($rating - $full_stars) >= 0.5;
                                        
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
                                    <span class="rating-count">(<?php echo $product['review_count'] ?? 0; ?>)</span>
                                </div>
                                
                                <div class="product-price">
                                    <span class="current-price"><?php echo format_price($product['price']); ?></span>
                                    <?php if($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                                        <span class="compare-price"><?php echo format_price($product['compare_price']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-actions">
                                    <?php if($product['in_stock'] > 0): ?>
                                        <button class="btn-add-to-cart" 
                                            data-id="<?php echo $product['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                            data-price="<?php echo $product['price']; ?>"
                                            data-price-inr="<?php echo htmlspecialchars(format_price($product['price'])); ?>">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-out-of-stock" disabled>
                                            <i class="fas fa-bell"></i> Notify When Available
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-wishlist" data-id="<?php echo $product['id']; ?>">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <a href="product.php?id=<?php echo $product['id']; ?>" class="btn-quick-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if($page > 1): ?>
                            <a href="?<?php echo build_query_string(['page' => $page - 1]); ?>" class="pagination-prev">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <div class="pagination-numbers">
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if($start_page > 1):
                                echo '<a href="?' . build_query_string(['page' => 1]) . '">1</a>';
                                if($start_page > 2) echo '<span class="pagination-dots">...</span>';
                            endif;
                            
                            for($i = $start_page; $i <= $end_page; $i++):
                                if($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?<?php echo build_query_string(['page' => $i]); ?>"><?php echo $i; ?></a>
                                <?php endif;
                            endfor;
                            
                            if($end_page < $total_pages):
                                if($end_page < $total_pages - 1) echo '<span class="pagination-dots">...</span>';
                                echo '<a href="?' . build_query_string(['page' => $total_pages]) . '">' . $total_pages . '</a>';
                            endif;
                            ?>
                        </div>
                        
                        <?php if($page < $total_pages): ?>
                            <a href="?<?php echo build_query_string(['page' => $page + 1]); ?>" class="pagination-next">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Shop Categories -->
    <div class="shop-categories">
        <h2>Shop by Category</h2>
        <div class="categories-grid">
            <?php
            $popular_categories = ['Scented', 'Aromatherapy', 'Seasonal', 'Unscented', 'Best Sellers'];
            foreach($popular_categories as $cat):
                $cat_count = get_category_count($cat);
                if($cat_count > 0):
            ?>
            <a href="?category=<?php echo urlencode($cat); ?>" class="category-card">
                <div class="category-icon">
                    <?php
                    $icons = [
                        'Scented' => 'fas fa-wind',
                        'Aromatherapy' => 'fas fa-spa',
                        'Seasonal' => 'fas fa-snowflake',
                        'Unscented' => 'fas fa-leaf',
                        'Best Sellers' => 'fas fa-crown'
                    ];
                    ?>
                    <i class="<?php echo $icons[$cat] ?? 'fas fa-candle-holder'; ?>"></i>
                </div>
                <h3><?php echo $cat; ?></h3>
                <p><?php echo $cat_count; ?> products</p>
            </a>
            <?php endif; endforeach; ?>
        </div>
    </div>
</div>

<script src="<?php echo SITE_URL; ?>js/shop.js"></script>
<?php include 'includes/footer.php'; ?>

<?php
// Helper functions
function build_query_string($new_params) {
    $params = $_GET;
    foreach($new_params as $key => $value) {
        if($value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    return http_build_query($params);
}

function get_category_count($category) {
    global $conn;
    $sql = "SELECT COUNT(*) as count FROM products WHERE category = ? AND is_active = 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $category);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}
?>