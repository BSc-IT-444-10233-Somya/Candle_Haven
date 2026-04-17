<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration file
require_once __DIR__ . '/config.php';
// Include helper functions (provides format_price)
require_once __DIR__ . '/functions.php';

// Include authentication helpers (uses PDO if available)
require_once __DIR__ . '/auth.php';

// Set default page title if not set
if (!isset($page_title)) {
    $page_title = "Candle Haven";
}

// Compute a robust project root URL based on document root and this project's folder
$__protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$__host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$__docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
$__projRoot = realpath(__DIR__ . '/..') ?: '';
$__projRoot = str_replace('\\', '/', $__projRoot);
$__docRoot = str_replace('\\', '/', $__docRoot);
$__relativePath = '/';
if ($__docRoot !== '' && strpos($__projRoot, $__docRoot) === 0) {
    $__relativePath = substr($__projRoot, strlen($__docRoot));
    if ($__relativePath === false) $__relativePath = '/';
}
$__relativePath = '/' . trim($__relativePath, '/') . '/';
if ($__relativePath === '//') $__relativePath = '/';
$__rootUrl = $__protocol . '://' . $__host . rtrim($__relativePath, '/');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap (grid and utilities used by templates) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Absolute project-root stylesheet (computed) -->
    <link rel="stylesheet" href="<?php echo $__rootUrl; ?>/css/style.css">
    <!-- SITE_URL fallback -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>css/style.css">
    <!-- Relative fallback (in-case) -->
    <link rel="stylesheet" href="css/style.css">
    <!-- JS fallback: try to load stylesheet from likely locations if not applied -->
    <script>
    (function() {
        function loadCss(href) {
            return new Promise(function(resolve, reject) {
                var l = document.createElement('link');
                l.rel = 'stylesheet';
                l.href = href;
                l.onload = function() { resolve(href); };
                l.onerror = function() { reject(href); };
                document.head.appendChild(l);
            });
        }

        function cssApplied() {
            // Basic check: body should have non-empty computed background (set by theme CSS)
            try {
                var bg = window.getComputedStyle(document.body).backgroundColor || '';
                return bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent';
            } catch(e) { return false; }
        }

        if (cssApplied()) return; // already applied

        var tryPaths = [];
        // Try SITE_URL-based path inserted by server
        tryPaths.push('<?php echo rtrim(SITE_URL, "/"); ?>' + '/css/style.css');
        // Try relative to current path
        tryPaths.push('css/style.css');
        // Try one level up (for pages in subfolders)
        tryPaths.push('../css/style.css');
        // Try building from location.pathname (project root)
        var parts = window.location.pathname.split('/');
        if (parts.length > 1) {
            var root = parts.slice(0, parts.length-1).join('/');
            if (root) tryPaths.push(root + '/css/style.css');
        }

        // Attempt to load each path until one succeeds and CSS appears applied
        (function tryNext(i) {
            if (i >= tryPaths.length) return;
            loadCss(tryPaths[i]).then(function(href) {
                    console.info('CSS fallback loaded:', href);
                    // small delay to allow CSS to be applied, then check
                    setTimeout(function() {
                        if (!cssApplied() && i+1 < tryPaths.length) {
                            console.warn('CSS loaded but not applied:', href);
                            tryNext(i+1);
                        } else {
                            console.info('CSS seems applied from:', href);
                        }
                    }, 150);
                }).catch(function(href) {
                    console.warn('Failed to load CSS from:', href);
                    tryNext(i+1);
                });
        })(0);
    })();
    </script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>images/favicon.ico">
    
    <!-- Additional meta tags -->
    <meta name="description" content="Premium handcrafted candles for every occasion">
    <meta name="keywords" content="candles, scented candles, handmade, aromatherapy, home decor">
    <meta name="author" content="Candle Haven">
</head>
<body<?php echo (function_exists('session_is_logged_in') ? (session_is_logged_in() ? ' class="logged-in"' : '') : (isset($_SESSION['user_id']) ? ' class="logged-in"' : '')); ?>>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="<?php echo SITE_URL; ?>index.php">
                        <i class="fas fa-candle-holder"></i>
                        <span><?php echo SITE_NAME; ?></span>
                    </a>
                </div>
                
                <nav class="main-nav">
                    <ul>
                        <li><a href="<?php echo SITE_URL; ?>index.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">Home</a></li>
                        <li><a href="<?php echo SITE_URL; ?>index.php#products">Shop</a></li>
                        <li><a href="<?php echo SITE_URL; ?>about.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'about.php') ? 'active' : ''; ?>">About</a></li>
                        <li><a href="<?php echo SITE_URL; ?>contact.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'contact.php') ? 'active' : ''; ?>">Contact</a></li>
                        <li><a href="<?php echo SITE_URL; ?>shop.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'shop.php') ? 'active' : ''; ?>">All Products</a></li>
                        <li><a href="<?php echo SITE_URL; ?>custom-candle.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'custom-candle.php') ? 'active' : ''; ?>">Custom Candles</a></li>
                    </ul>
                </nav>
                
                <div class="header-icons">
                    <a href="<?php echo SITE_URL; ?>cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <?php 
                        $cart_count = 0;
                        if(isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                            foreach($_SESSION['cart'] as $item) {
                                if(isset($item['quantity'])) {
                                    $cart_count += $item['quantity'];
                                } else {
                                    $cart_count++;
                                }
                            }
                        }
                        if($cart_count > 0): ?>
                            <span class="cart-count"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <?php if(function_exists('session_is_logged_in') ? session_is_logged_in() : isset($_SESSION['user_id'])): ?>
                        <div class="user-dropdown">
                            <a href="#" class="user-icon">
                                <i class="fas fa-user"></i>
                            </a>
                            <div class="dropdown-menu">
                                <?php
                                    // Prefer structured name where available; hide admin-role literal names
                                    $displayName = $_SESSION['first_name'] ?? $_SESSION['username'] ?? ($_SESSION['user']['name'] ?? null) ?? 'User';
                                    if (stripos($displayName, 'admin') !== false) {
                                        $displayName = 'User';
                                    }
                                ?>
                                <p>Hello, <?php echo htmlspecialchars($displayName); ?>!</p>
                                <a href="<?php echo SITE_URL; ?>profile.php">My Profile</a>
                                <a href="<?php echo SITE_URL; ?>orders.php">My Orders</a>
                                <a href="<?php echo SITE_URL; ?>logout.php">Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="auth-buttons">
                            <a href="<?php echo SITE_URL; ?>login.php" class="login-btn">Login</a>
                            <a href="<?php echo SITE_URL; ?>register.php" class="register-btn">Register</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Mobile Navigation -->
    <nav class="mobile-nav" id="mobileNav">
        <ul>
            <li><a href="<?php echo SITE_URL; ?>index.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">Home</a></li>
            <li><a href="<?php echo SITE_URL; ?>index.php#products">Shop</a></li>
            <li><a href="<?php echo SITE_URL; ?>about.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'about.php') ? 'active' : ''; ?>">About</a></li>
            <li><a href="<?php echo SITE_URL; ?>contact.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'contact.php') ? 'active' : ''; ?>">Contact</a></li>
            <li><a href="<?php echo SITE_URL; ?>shop.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'shop.php') ? 'active' : ''; ?>">All Products</a></li>
            
                <?php if(function_exists('session_is_logged_in') ? session_is_logged_in() : isset($_SESSION['user_id'])): ?>
                <li class="mobile-user-info">
                    <p>Hello, <?php echo htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['username'] ?? 'User'); ?>!</p>
                </li>
                <li><a href="<?php echo SITE_URL; ?>profile.php">My Profile</a></li>
                <li><a href="<?php echo SITE_URL; ?>orders.php">My Orders</a></li>
                <!-- Admin Panel access removed from mobile nav -->
                <li><a href="<?php echo SITE_URL; ?>logout.php">Logout</a></li>
            <?php else: ?>
                <li><a href="<?php echo SITE_URL; ?>login.php">Login</a></li>
                <li><a href="<?php echo SITE_URL; ?>register.php">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <!-- Main Content Container -->
    <main class="main-content">