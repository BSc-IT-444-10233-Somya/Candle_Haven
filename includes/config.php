<?php
// Start session with secure cookie parameters
// Determine if connection is secure
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
// Set session cookie params for better security
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If persistent remember-me was disabled, clear any existing remember cookie to avoid confusion
if (defined('ENABLE_REMEMBER') && ENABLE_REMEMBER === false) {
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'candle_shop');

// Create connection
try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if (!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }
    
    // Set charset
    mysqli_set_charset($conn, "utf8mb4");
    
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Create a PDO instance for libraries that use PDO (e.g. includes/auth.php)
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // If PDO fails, we still keep mysqli connection available
    // Log or display error in development
    error_log('PDO connection failed: ' . $e->getMessage());
    $pdo = null;
}

// Site configuration
define('SITE_NAME', 'Candle Haven');

// Dynamically determine SITE_URL so links work across different hosts/ports/paths
if (!defined('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Document root (normalized)
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';

    // Project root: parent directory of the includes folder
    $projectRoot = realpath(__DIR__ . '/..') ?: '';

    $docRoot = str_replace('\\', '/', $docRoot);
    $projectRoot = str_replace('\\', '/', $projectRoot);

    $relativePath = '';
    if ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0) {
        $relativePath = substr($projectRoot, strlen($docRoot));
    }

    if ($relativePath === false) {
        $relativePath = '';
    }

    // Ensure leading slash and trailing slash
    $relativePath = '/' . trim($relativePath, '/') . '/';
    if ($relativePath === '//') {
        $relativePath = '/';
    }

    define('SITE_URL', $protocol . '://' . $host . rtrim($relativePath, '/') . '/');
}

define('SITE_EMAIL', 'info@candlehaven.com');
define('CURRENCY', '₹');
define('CURRENCY_CODE', 'INR');
// Toggle persistent 'remember me' functionality. Set to false to require login after server restart.
define('ENABLE_REMEMBER', false);
// Conversion rate used for display: 1 USD = USD_TO_INR INR
// Update this value if you want a different exchange rate
define('USD_TO_INR', 82.00);

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('America/New_York');

// Core helper functions are defined in includes/functions.php.
// Load helper functions so utility functions like format_price() are available
// across both frontend and admin pages. Use require_once to avoid redeclaration.
require_once __DIR__ . '/functions.php';
// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
?>