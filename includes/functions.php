<?php
// Function to sanitize input data
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function is_admin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
}

// Function to format price
function format_price($price) {
    // Ensure numeric
    $price = floatval($price);
    // Prices in the database are stored in the site's currency (INR when configured).
    $symbol = defined('CURRENCY') ? CURRENCY : '$';
    return $symbol . number_format($price, 2);
}

// Function to get cart total
function get_cart_total() {
    global $conn;
    $total = 0;
    
    if(isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        foreach($_SESSION['cart'] as $product_id => $item) {
            // Support custom cart items stored with 'type' => 'custom'
            if (is_array($item) && isset($item['type']) && $item['type'] === 'custom') {
                $price = isset($item['price']) ? floatval($item['price']) : 0;
                $qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
                $total += $price * $qty;
                continue;
            }

            // Regular product IDs (numeric)
            $pid = intval($product_id);
            if ($pid <= 0) continue;

            $sql = "SELECT price FROM products WHERE id = $pid";
            $result = mysqli_query($conn, $sql);
            $product = mysqli_fetch_assoc($result);

            if($product) {
                $total += $product['price'] * $item['quantity'];
            }
        }
    }
    
    return $total;
}

// Function to get cart item count
function get_cart_count() {
    $count = 0;
    
    if(isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        foreach($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
    }
    
    return $count;
}

// Function to get product by ID
function get_product($id) {
    global $conn;
    $id = intval($id);
    
    $sql = "SELECT * FROM products WHERE id = $id";
    $result = mysqli_query($conn, $sql);
    
    if(mysqli_num_rows($result) == 1) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

// Function to get user by ID
function get_user($id) {
    global $conn;
    $id = intval($id);
    
    $sql = "SELECT * FROM users WHERE id = $id";
    $result = mysqli_query($conn, $sql);
    
    if(mysqli_num_rows($result) == 1) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

// Function to get user orders
function get_user_orders($user_id) {
    global $conn;
    $user_id = intval($user_id);
    
    $sql = "SELECT * FROM orders WHERE user_id = $user_id ORDER BY created_at DESC";
    $result = mysqli_query($conn, $sql);
    
    $orders = [];
    while($row = mysqli_fetch_assoc($result)) {
        $orders[] = $row;
    }
    
    return $orders;
}

// Function to generate random string for order numbers
function generate_order_number() {
    return 'ORD-' . strtoupper(uniqid());
}

// Function to send email
function send_email($to, $subject, $message, $from = 'noreply@candlehaven.com') {
    $headers = "From: $from\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Function to get order status badge
function get_status_badge($status) {
    $statuses = [
        'pending' => '<span class="status-badge status-pending">Pending</span>',
        'processing' => '<span class="status-badge status-processing">Processing</span>',
        'shipped' => '<span class="status-badge status-shipped">Shipped</span>',
        'delivered' => '<span class="status-badge status-delivered">Delivered</span>',
        'cancelled' => '<span class="status-badge status-cancelled">Cancelled</span>'
    ];
    
    return isset($statuses[$status]) ? $statuses[$status] : $statuses['pending'];
}

/**
 * Return a root-relative URL for a given project path.
 * Example: site_path('admin/orders.php') -> '/dashboard/new/admin/orders.php'
 */
function site_path($path) {
    // Determine document root and project root
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $projRoot = realpath(__DIR__ . '/..') ?: '';

    $docRoot = str_replace('\\', '/', $docRoot);
    $projRoot = str_replace('\\', '/', $projRoot);

    $relative = '';
    if ($docRoot !== '' && strpos($projRoot, $docRoot) === 0) {
        $relative = substr($projRoot, strlen($docRoot));
    }

    $relative = '/' . trim($relative, '/') . '/';
    if ($relative === '//') $relative = '/';

    return $relative . ltrim($path, '/');
}

// Function to validate image upload
function validate_image($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if($file['error'] !== UPLOAD_ERR_OK) {
        return "File upload error.";
    }
    
    if(!in_array($file['type'], $allowed_types)) {
        return "Only JPG, PNG, and GIF files are allowed.";
    }
    
    if($file['size'] > $max_size) {
        return "File size must be less than 2MB.";
    }
    
    return true;
}
?>