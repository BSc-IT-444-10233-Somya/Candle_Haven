<?php
// includes/session.php

/**
 * Session Management for Candle Shop Project
 * Handles session initialization, security, and session-based data
 */

// Session security configuration
class SessionManager {
    
    /**
     * Initialize secure session
     */
    public static function startSecureSession() {
        // Set session cookie parameters
        $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
        $httponly = true;
        
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $cookieParams["lifetime"],
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? $cookieParams["domain"],
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax'
        ]);
        
        // Set session name
        session_name('CANDLE_SHOP_SESSION');
        
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID periodically
        self::regenerateSession();
        
        // Initialize session arrays if not set
        self::initializeSessionArrays();

        // Backwards-compat: synchronize legacy flat session keys used elsewhere
        // with the structured `$_SESSION['user']` used by this manager.
        if (isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
            $_SESSION['user']['id'] = $_SESSION['user_id'];
            $_SESSION['user']['email'] = $_SESSION['user_email'] ?? ($_SESSION['user']['email'] ?? null);
            $_SESSION['user']['name'] = $_SESSION['user_name'] ?? ($_SESSION['user']['name'] ?? null);
            $_SESSION['user']['role'] = $_SESSION['user_role'] ?? ($_SESSION['user']['role'] ?? 'guest');
            $_SESSION['user']['is_logged_in'] = true;
        }

        if (isset($_SESSION['user']['id']) && !isset($_SESSION['user_id'])) {
            $_SESSION['user_id'] = $_SESSION['user']['id'];
            $_SESSION['user_email'] = $_SESSION['user']['email'] ?? null;
            $_SESSION['user_name'] = $_SESSION['user']['name'] ?? null;
            $_SESSION['user_role'] = $_SESSION['user']['role'] ?? 'guest';
        }
        
        // Set session timestamp
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        }
    }
    
    /**
     * Regenerate session ID for security
     */
    private static function regenerateSession() {
        $regenerate_interval = 300; // 5 minutes
        
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } elseif (time() - $_SESSION['created'] > $regenerate_interval) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
    
    /**
     * Initialize session arrays
     */
    private static function initializeSessionArrays() {
        if (!isset($_SESSION['user'])) {
            $_SESSION['user'] = [
                'id' => null,
                'email' => null,
                'name' => null,
                'role' => 'guest',
                'is_logged_in' => false
            ];
        }
        
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        if (!isset($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }
        
        if (!isset($_SESSION['notifications'])) {
            $_SESSION['notifications'] = [
                'success' => [],
                'error' => [],
                'info' => [],
                'warning' => []
            ];
        }
        
        if (!isset($_SESSION['recent_views'])) {
            $_SESSION['recent_views'] = [];
        }
        
        if (!isset($_SESSION['session_data'])) {
            $_SESSION['session_data'] = [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'start_time' => time(),
                'page_views' => 0,
                'last_url' => ''
            ];
        }
    }
    
    /**
     * Set user session data after login
     * @param array $user_data User information
     */
    public static function setUserSession($user_data) {
        $_SESSION['user'] = [
            'id' => $user_data['id'],
            'email' => $user_data['email'],
            'name' => $user_data['name'],
            'role' => $user_data['role'] ?? 'customer',
            'is_logged_in' => true,
            'phone' => $user_data['phone'] ?? null,
            'address' => $user_data['address'] ?? null,
            'last_login' => time()
        ];
        
        // Update session data
        $_SESSION['session_data']['user_id'] = $user_data['id'];

        // Keep legacy flat session keys in sync for other parts of the app
        $_SESSION['user_id'] = $user_data['id'];
        $_SESSION['user_email'] = $user_data['email'];
        $_SESSION['user_name'] = $user_data['name'];
        $_SESSION['user_role'] = $user_data['role'] ?? 'customer';

        // Regenerate session id on login for extra security
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        
        // Create activity log
        self::logActivity('user_login', 'User logged in successfully');
    }
    
    /**
     * Clear user session data on logout
     */
    public static function clearUserSession() {
        $user_id = $_SESSION['user']['id'] ?? null;
        
        // Log activity before clearing
        if ($user_id) {
            self::logActivity('user_logout', 'User logged out');
        }
        
        // Clear user data but keep some session info
        $_SESSION['user'] = [
            'id' => null,
            'email' => null,
            'name' => null,
            'role' => 'guest',
            'is_logged_in' => false
        ];

        // Clear legacy flat session keys as well
        unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_name'], $_SESSION['user_role'], $_SESSION['is_admin']);
        
        // Keep cart for guest users (optional)
        // If you want to clear cart on logout, uncomment below:
        // $_SESSION['cart'] = [];
        
        // Clear wishlist from session (database wishlist remains)
        $_SESSION['wishlist'] = [];
        
        // Remove user ID from session data
        unset($_SESSION['session_data']['user_id']);
    }
    
    /**
     * Destroy session completely
     */
    public static function destroySession() {
        // Log activity
        self::logActivity('session_destroyed', 'Session destroyed');
        
        // Clear all session data
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Add item to session cart
     * @param int $product_id Product ID
     * @param int $quantity Quantity to add
     * @param array $product_data Additional product data
     * @return bool Success status
     */
    public static function addToCart($product_id, $quantity = 1, $product_data = []) {
        $product_id = intval($product_id);
        $quantity = intval($quantity);
        
        if ($product_id <= 0 || $quantity <= 0) {
            return false;
        }
        
        // Check if product already in cart
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $product_id) {
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        
        // Add new item if not found
        if (!$found) {
            $_SESSION['cart'][] = [
                'id' => $product_id,
                'quantity' => $quantity,
                'added_at' => time(),
                'price' => $product_data['price'] ?? 0,
                'name' => $product_data['name'] ?? 'Product',
                'image' => $product_data['image'] ?? 'default.jpg'
            ];
        }
        
        // Log activity
        self::logActivity('cart_add', "Added product $product_id to cart (quantity: $quantity)");
        
        return true;
    }
    
    /**
     * Update cart item quantity
     * @param int $product_id Product ID
     * @param int $quantity New quantity
     * @return bool Success status
     */
    public static function updateCartItem($product_id, $quantity) {
        $product_id = intval($product_id);
        $quantity = intval($quantity);
        
        if ($quantity <= 0) {
            return self::removeFromCart($product_id);
        }
        
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $product_id) {
                $item['quantity'] = $quantity;
                $item['updated_at'] = time();
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Remove item from cart
     * @param int $product_id Product ID
     * @return bool Success status
     */
    public static function removeFromCart($product_id) {
        $product_id = intval($product_id);
        
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['id'] == $product_id) {
                unset($_SESSION['cart'][$key]);
                $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex array
                
                // Log activity
                self::logActivity('cart_remove', "Removed product $product_id from cart");
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get cart total items
     * @return int Total items in cart
     */
    public static function getCartItemCount() {
        $count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
        return $count;
    }
    
    /**
     * Get cart total price
     * @return float Total price
     */
    public static function getCartTotal() {
        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total += ($item['quantity'] * ($item['price'] ?? 0));
        }
        return $total;
    }
    
    /**
     * Clear entire cart
     */
    public static function clearCart() {
        $_SESSION['cart'] = [];
        self::logActivity('cart_clear', 'Cart cleared');
    }
    
    /**
     * Add notification message
     * @param string $type Notification type (success, error, info, warning)
     * @param string $message Notification message
     */
    public static function addNotification($type, $message) {
        if (!in_array($type, ['success', 'error', 'info', 'warning'])) {
            $type = 'info';
        }
        
        $_SESSION['notifications'][$type][] = $message;
    }
    
    /**
     * Get and clear notifications of specific type
     * @param string $type Notification type
     * @return array Array of notifications
     */
    public static function getNotifications($type = null) {
        if ($type) {
            $notifications = $_SESSION['notifications'][$type] ?? [];
            $_SESSION['notifications'][$type] = [];
            return $notifications;
        } else {
            $all_notifications = $_SESSION['notifications'];
            $_SESSION['notifications'] = [
                'success' => [],
                'error' => [],
                'info' => [],
                'warning' => []
            ];
            return $all_notifications;
        }
    }
    
    /**
     * Track recently viewed products
     * @param int $product_id Product ID
     * @param array $product_data Product data
     * @param int $max_items Maximum items to keep
     */
    public static function trackRecentView($product_id, $product_data = [], $max_items = 10) {
        $product_id = intval($product_id);
        
        // Remove if already exists (to move to top)
        foreach ($_SESSION['recent_views'] as $key => $item) {
            if ($item['id'] == $product_id) {
                unset($_SESSION['recent_views'][$key]);
                break;
            }
        }
        
        // Add to beginning
        array_unshift($_SESSION['recent_views'], [
            'id' => $product_id,
            'viewed_at' => time(),
            'name' => $product_data['name'] ?? 'Product',
            'image' => $product_data['image'] ?? 'default.jpg',
            'price' => $product_data['price'] ?? 0
        ]);
        
        // Keep only max items
        $_SESSION['recent_views'] = array_slice($_SESSION['recent_views'], 0, $max_items);
    }
    
    /**
     * Get recently viewed products
     * @param int $limit Maximum number of items to return
     * @return array Recent products
     */
    public static function getRecentViews($limit = 5) {
        return array_slice($_SESSION['recent_views'], 0, $limit);
    }
    
    /**
     * Log user activity
     * @param string $action Action performed
     * @param string $description Action description
     */
    public static function logActivity($action, $description) {
        if (!isset($_SESSION['activity_log'])) {
            $_SESSION['activity_log'] = [];
        }
        
        $_SESSION['activity_log'][] = [
            'timestamp' => time(),
            'action' => $action,
            'description' => $description,
            'user_id' => $_SESSION['user']['id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        // Keep only last 50 activities
        if (count($_SESSION['activity_log']) > 50) {
            array_shift($_SESSION['activity_log']);
        }
    }
    
    /**
     * Get user activity log
     * @return array Activity log
     */
    public static function getActivityLog() {
        return $_SESSION['activity_log'] ?? [];
    }
    
    /**
     * Set session data
     * @param string $key Data key
     * @param mixed $value Data value
     */
    public static function set($key, $value) {
        $_SESSION['session_data'][$key] = $value;
    }
    
    /**
     * Get session data
     * @param string $key Data key
     * @param mixed $default Default value if not found
     * @return mixed Session data
     */
    public static function get($key, $default = null) {
        return $_SESSION['session_data'][$key] ?? $default;
    }
    
    /**
     * Check if session data exists
     * @param string $key Data key
     * @return bool True if exists
     */
    public static function has($key) {
        return isset($_SESSION['session_data'][$key]);
    }
    
    /**
     * Remove session data
     * @param string $key Data key
     */
    public static function remove($key) {
        unset($_SESSION['session_data'][$key]);
    }
    
    /**
     * Validate session security
     * @return bool True if session is secure
     */
    public static function validateSession() {
        // Check IP address
        $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $session_ip = $_SESSION['session_data']['ip_address'] ?? '';
        
        if ($current_ip !== $session_ip) {
            // IP changed - might be session hijacking
            self::addNotification('warning', 'Security alert: Session IP changed');
            return false;
        }
        
        // Check user agent
        $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $session_ua = $_SESSION['session_data']['user_agent'] ?? '';
        
        if ($current_ua !== $session_ua) {
            // User agent changed
            self::addNotification('warning', 'Security alert: Browser changed');
            return false;
        }
        
        // Check session age
        $max_session_age = 24 * 60 * 60; // 24 hours
        if (isset($_SESSION['session_data']['start_time']) && 
            (time() - $_SESSION['session_data']['start_time']) > $max_session_age) {
            self::addNotification('info', 'Session expired due to inactivity');
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Get session statistics
     * @return array Session statistics
     */
    public static function getSessionStats() {
        return [
            'session_id' => session_id(),
            'session_start' => $_SESSION['session_data']['start_time'] ?? null,
            'session_age' => isset($_SESSION['session_data']['start_time']) ? 
                           time() - $_SESSION['session_data']['start_time'] : 0,
            'page_views' => $_SESSION['session_data']['page_views'] ?? 0,
            'cart_items' => self::getCartItemCount(),
            'user_logged_in' => $_SESSION['user']['is_logged_in'] ?? false,
            'user_role' => $_SESSION['user']['role'] ?? 'guest',
            'recent_views_count' => count($_SESSION['recent_views'] ?? []),
            'activity_log_count' => count($_SESSION['activity_log'] ?? [])
        ];
    }
    
    /**
     * Increment page views
     */
    public static function incrementPageViews() {
        if (!isset($_SESSION['session_data']['page_views'])) {
            $_SESSION['session_data']['page_views'] = 0;
        }
        $_SESSION['session_data']['page_views']++;
        $_SESSION['session_data']['last_url'] = $_SERVER['REQUEST_URI'] ?? '';
    }
    
    /**
     * Get user information from session
     * @return array User data
     */
    public static function getUserInfo() {
        return $_SESSION['user'];
    }
    
    /**
     * Check if user is logged in
     * @return bool True if logged in
     */
    public static function isLoggedIn() {
        return ($_SESSION['user']['is_logged_in'] ?? false) === true;
    }
    
    /**
     * Check if user is admin
     * @return bool True if admin
     */
    public static function isAdmin() {
        return self::isLoggedIn() && ($_SESSION['user']['role'] ?? '') === 'admin';
    }
    
    /**
     * Check if user has specific role
     * @param string $role Role to check
     * @return bool True if user has role
     */
    public static function hasRole($role) {
        return self::isLoggedIn() && ($_SESSION['user']['role'] ?? '') === $role;
    }
    
    /**
     * Get cart items
     * @return array Cart items
     */
    public static function getCartItems() {
        return $_SESSION['cart'];
    }
    
    /**
     * Save session data before redirect
     */
    public static function saveBeforeRedirect() {
        // Update session data
        $_SESSION['session_data']['last_redirect'] = time();
        $_SESSION['session_data']['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '';
        
        // Write and close session for redirect
        session_write_close();
    }
    
    /**
     * Restore session after redirect
     */
    public static function restoreAfterRedirect() {
        // Reopen session if needed
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

// Initialize session when this file is included
SessionManager::startSecureSession();

// If an auth auto-login function is available, call it only when remember is enabled
if (function_exists('autoLogin') && defined('ENABLE_REMEMBER') && ENABLE_REMEMBER === true) {
    try {
        autoLogin();
    } catch (Throwable $e) {
        error_log('autoLogin() failed: ' . $e->getMessage());
    }
}

// Global helper functions
function session_add_notification($type, $message) {
    SessionManager::addNotification($type, $message);
}

function session_get_notifications($type = null) {
    return SessionManager::getNotifications($type);
}

function session_add_to_cart($product_id, $quantity = 1, $product_data = []) {
    return SessionManager::addToCart($product_id, $quantity, $product_data);
}

function session_get_cart_total() {
    return SessionManager::getCartTotal();
}

function session_get_cart_item_count() {
    return SessionManager::getCartItemCount();
}

function session_track_recent_view($product_id, $product_data = []) {
    return SessionManager::trackRecentView($product_id, $product_data);
}

function session_is_logged_in() {
    return SessionManager::isLoggedIn();
}

function session_is_admin() {
    // Prefer centralized auth check when available to remain authoritative
    if (function_exists('isAdmin')) {
        return isAdmin();
    }

    return SessionManager::isAdmin();
}

function session_get_user_info() {
    return SessionManager::getUserInfo();
}

function session_validate() {
    return SessionManager::validateSession();
}