<?php
// includes/auth.php

/**
 * User Authentication and Authorization Functions
 * For Candle Shop Project
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'functions.php';

/**
 * Check if user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is an admin
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }

    global $pdo;
    try {
        // First check new admins table (supports separate admin accounts)
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT id, user_id, email, is_active FROM admins WHERE user_id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($admin && (!isset($admin['is_active']) || $admin['is_active'])) {
                return true;
            }
        }

        // Fallback: check admins table by email (if session stores user email)
        if (isset($_SESSION['user_email']) && !empty($_SESSION['user_email'])) {
            $stmt = $pdo->prepare("SELECT id, user_id, email, is_active FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_email']]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($admin && (!isset($admin['is_active']) || $admin['is_active'])) {
                return true;
            }
        }

        // Final fallback: check legacy users.role/is_admin for backwards compatibility
        $stmt = $pdo->prepare("SELECT role, is_admin FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) return false;

        $isAdminDb = (isset($user['role']) && $user['role'] === 'admin') || (!empty($user['is_admin']) && $user['is_admin']);
        return $isAdminDb;
    } catch (PDOException $e) {
        error_log('isAdmin check failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * User Registration
 * @param string $name User's full name
 * @param string $email User's email address
 * @param string $password User's password
 * @param string $confirm_password Password confirmation
 * @return array Result with status and message
 */
function registerUser($name, $email, $password, $confirm_password) {
    global $pdo;
    
    $errors = [];
    
    // Validate inputs
    if (empty($name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email address is already registered";
        }
    }
    
    // If no errors, create user
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, role, created_at) 
                VALUES (?, ?, ?, 'customer', NOW())
            ");
            
            if ($stmt->execute([$name, $email, $hashed_password])) {
                $user_id = $pdo->lastInsertId();
                
                // Set session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = 'customer';
                
                // Create a welcome cart for the user
                createWelcomeCart($user_id);
                
                return [
                    'success' => true,
                    'message' => 'Registration successful! Welcome to our candle shop.'
                ];
            }
        } catch (PDOException $e) {
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
    
    return [
        'success' => false,
        'errors' => $errors
    ];
}

/**
 * User Login
 * @param string $email User's email
 * @param string $password User's password
 * @param bool $remember Remember me option
 * @return array Result with status and message
 */
function loginUser($email, $password, $remember = false) {
    global $pdo;
    
    $errors = [];
    
    // Validate inputs
    if (empty($email)) {
        $errors[] = "Email address is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if (empty($errors)) {
        // Get user from database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check if account is active
                if (isset($user['status']) && $user['status'] === 'inactive') {
                    $errors[] = "Your account has been deactivated. Please contact support.";
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    // Set remember me cookie if requested (only when enabled in config)
                    if ($remember && defined('ENABLE_REMEMBER') && ENABLE_REMEMBER === true) {
                        $token = bin2hex(random_bytes(32));
                        $token_hash = hash_hmac('sha256', $token, SITE_NAME);
                        $expiry = time() + (30 * 24 * 60 * 60); // 30 days

                        // Cookie security flags
                        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                        $cookie_opts = [
                            'expires' => $expiry,
                            'path' => '/',
                            'secure' => $secure,
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ];
                        setcookie('remember_token', $token, $cookie_opts);

                        // Store hashed token in database (avoid storing raw token)
                        $stmt = $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                        $stmt->execute([$user['id'], $token_hash, date('Y-m-d H:i:s', $expiry)]);
                    }
                    
                    // Update last login
                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    return [
                        'success' => true,
                        'message' => 'Login successful!',
                        'user' => $user
                    ];
                }
            } else {
                $errors[] = "Invalid email or password";
            }
        } else {
            $errors[] = "Invalid email or password";
        }
    }
    
    return [
        'success' => false,
        'errors' => $errors
    ];
}

/**
 * Auto-login using remember token
 */
function autoLogin() {
    global $pdo;
    
    if (isLoggedIn()) {
        return;
    }
    // If persistent remember is disabled, skip auto-login entirely
    if (!defined('ENABLE_REMEMBER') || ENABLE_REMEMBER !== true) {
        return;
    }

    if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $token_hash = hash_hmac('sha256', $token, SITE_NAME);

            $stmt = $pdo->prepare("SELECT u.* FROM users u 
                JOIN user_tokens ut ON u.id = ut.user_id 
                WHERE ut.token = ? AND ut.expires_at > NOW()");
            $stmt->execute([$token_hash]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Do NOT auto-login admin accounts via remember-token. Require explicit admin login.
            $isAdminAccount = (isset($user['role']) && $user['role'] === 'admin') || (!empty($user['is_admin']) && $user['is_admin']);
            if ($isAdminAccount) {
                // Security: ignore admin auto-login tokens
                error_log('Auto-login skipped for admin account id=' . ($user['id'] ?? 'unknown'));
                return;
            }

            // Regenerate session id to avoid session fixation
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            // Also set legacy boolean for other parts of the app
            $_SESSION['is_admin'] = false;
            
            // Refresh token (optional)
            refreshRememberToken($user['id']);
        }
    }
}

/**
 * Refresh remember token
 * @param int $user_id User ID
 */
function refreshRememberToken($user_id) {
    global $pdo;
    // Only refresh if remember feature is enabled
    if (!defined('ENABLE_REMEMBER') || ENABLE_REMEMBER !== true) {
        return;
    }

    if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $token_hash = hash_hmac('sha256', $token, SITE_NAME);
            $expiry = time() + (30 * 24 * 60 * 60); // 30 days

            // Update token expiry (match stored hashed token)
            $stmt = $pdo->prepare("UPDATE user_tokens SET expires_at = ? WHERE user_id = ? AND token = ?");
            $stmt->execute([date('Y-m-d H:i:s', $expiry), $user_id, $token_hash]);

            // Refresh cookie with secure flags
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $cookie_opts = [
                'expires' => $expiry,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ];
            setcookie('remember_token', $token, $cookie_opts);
    }
}

/**
 * Logout user
 */
function logoutUser() {
    global $pdo;
    
    // Clear remember token from database if exists
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $token_hash = hash_hmac('sha256', $token, SITE_NAME);
        $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE token = ?");
        $stmt->execute([$token_hash]);

        // Clear cookie (use secure flags when possible)
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    
    // Clear all session variables
    $_SESSION = [];
    
    // Destroy session
    if (session_id() != "" || isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
        session_destroy();
    }
}

/**
 * Require login - redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit();
    }
}

/**
 * Require admin access - redirect if not admin
 */
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        $_SESSION['error'] = "Access denied. Admin privileges required.";
        header('Location: index.php');
        exit();
    }
}

/**
 * Get current user information
 * @return array User data or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update user profile
 * @param int $user_id User ID
 * @param array $data User data (name, email, phone, address)
 * @return array Result with status and message
 */
function updateProfile($user_id, $data) {
    global $pdo;
    
    $errors = [];
    
    // Validate inputs
    if (empty($data['name'])) {
        $errors[] = "Full name is required";
    }
    
    if (empty($data['email'])) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email is already taken by another user
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$data['email'], $user_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email address is already in use by another account";
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = ?, email = ?, phone = ?, address = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['phone'] ?? '',
                $data['address'] ?? '',
                $user_id
            ]);
            
            // Update session
            $_SESSION['user_name'] = $data['name'];
            $_SESSION['user_email'] = $data['email'];
            
            return [
                'success' => true,
                'message' => 'Profile updated successfully'
            ];
        } catch (PDOException $e) {
            $errors[] = "Failed to update profile: " . $e->getMessage();
        }
    }
    
    return [
        'success' => false,
        'errors' => $errors
    ];
}

/**
 * Change user password
 * @param int $user_id User ID
 * @param string $current_password Current password
 * @param string $new_password New password
 * @param string $confirm_password Confirm new password
 * @return array Result with status and message
 */
function changePassword($user_id, $current_password, $new_password, $confirm_password) {
    global $pdo;
    
    $errors = [];
    
    // Get current password hash
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $errors[] = "User not found";
    } else {
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
        
        // Validate new password
        if (empty($new_password)) {
            $errors[] = "New password is required";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters long";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            return [
                'success' => true,
                'message' => 'Password changed successfully'
            ];
        } catch (PDOException $e) {
            $errors[] = "Failed to change password: " . $e->getMessage();
        }
    }
    
    return [
        'success' => false,
        'errors' => $errors
    ];
}

/**
 * Request password reset
 * @param string $email User's email address
 * @return array Result with status and message
 */
function requestPasswordReset($email) {
    global $pdo;
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Don't reveal that email doesn't exist for security
        return [
            'success' => true,
            'message' => 'If your email exists in our system, you will receive a password reset link.'
        ];
    }
    
    // Generate reset token
    $token = bin2hex(random_bytes(32));
    $expires = time() + (60 * 60); // 1 hour
    
    // Store token in database
    $stmt = $pdo->prepare("
        INSERT INTO password_resets (user_id, token, expires_at) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['id'], $token, date('Y-m-d H:i:s', $expires)]);
    
    // Create reset link
    $reset_link = SITE_URL . '/reset-password.php?token=' . $token;
    
    // Send email (in a real application)
    // For now, we'll just store it for demonstration
    $_SESSION['reset_token'] = $token;
    $_SESSION['reset_email'] = $email;
    
    return [
        'success' => true,
        'message' => 'Password reset link has been sent to your email.',
        'debug_link' => $reset_link // Remove this in production
    ];
}

/**
 * Reset password using token
 * @param string $token Reset token
 * @param string $new_password New password
 * @return array Result with status and message
 */
function resetPassword($token, $new_password) {
    global $pdo;
    
    $errors = [];
    
    // Validate token
    $stmt = $pdo->prepare("
        SELECT pr.*, u.email 
        FROM password_resets pr 
        JOIN users u ON pr.user_id = u.id 
        WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reset) {
        $errors[] = "Invalid or expired reset token";
    }
    
    // Validate new password
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters long";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        try {
            // Update user password
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $reset['user_id']]);
            
            // Mark token as used
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            return [
                'success' => true,
                'message' => 'Password reset successfully. You can now login with your new password.'
            ];
        } catch (PDOException $e) {
            $errors[] = "Failed to reset password: " . $e->getMessage();
        }
    }
    
    return [
        'success' => false,
        'errors' => $errors
    ];
}

/**
 * Create a welcome cart for new users
 * @param int $user_id New user ID
 */
function createWelcomeCart($user_id) {
    global $pdo;
    
    // You can add sample items to cart or create a welcome discount
    // For now, we'll just create an empty cart session
    $_SESSION['cart'] = [];
    
    // Or add a welcome coupon
    $coupon_code = 'WELCOME10';
    $stmt = $pdo->prepare("
        INSERT INTO user_coupons (user_id, coupon_code, expires_at) 
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
    ");
    $stmt->execute([$user_id, $coupon_code]);
}

/**
 * Check if user has permission for a specific action
 * @param string $permission Permission name
 * @return bool True if user has permission
 */
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_role = $_SESSION['user_role'];
    
    // Define role permissions
    $permissions = [
        'admin' => [
            'manage_products',
            'manage_orders',
            'manage_users',
            'view_reports',
            'manage_categories'
        ],
        'customer' => [
            'view_products',
            'place_orders',
            'view_orders',
            'add_reviews',
            'manage_favorites'
        ]
    ];
    
    return isset($permissions[$user_role]) && in_array($permission, $permissions[$user_role]);
}

/**
 * Redirect user based on their role after login
 */
function redirectBasedOnRole() {
    if (isLoggedIn()) {
        $role = $_SESSION['user_role'];
        
        if ($role === 'admin') {
            header('Location: admin/index.php');
        } else {
            // Check if there's a redirect URL stored
            if (isset($_SESSION['redirect_url'])) {
                $redirect_url = $_SESSION['redirect_url'];
                unset($_SESSION['redirect_url']);
                header('Location: ' . $redirect_url);
            } else {
                header('Location: profile.php');
            }
        }
        exit();
    }
}

/**
 * Get user's cart items
 * @param int $user_id User ID
 * @return array Cart items
 */
function getUserCart($user_id) {
    global $pdo;
    
    // This depends on your cart implementation
    // If using session-based cart, return from session
    if (isset($_SESSION['cart'])) {
        return $_SESSION['cart'];
    }
    
    // If using database-based cart
    $stmt = $pdo->prepare("
        SELECT c.*, p.name, p.price, p.image 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Auto-login on page load
autoLogin();

/**
 * Ensure session role flags match the database for the logged-in user.
 * This prevents stale or tampered session data from elevating a user to admin.
 */
function syncSessionWithDb() {
    global $pdo;

    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, email, name, role, is_admin FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Normalize role value
            $role = isset($user['role']) && $user['role'] !== '' ? $user['role'] : (($user['is_admin']) ? 'admin' : 'customer');

            // Update session authoritative values from DB
            $_SESSION['user_role'] = $role;
            $_SESSION['is_admin'] = (!empty($user['is_admin']) && $user['is_admin']) || ($role === 'admin');

            // Also keep display fields in sync
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
        } else {
            // If the user no longer exists, clear session
            $_SESSION = [];
            if (session_id() != "" || isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
                session_destroy();
            }
        }
    } catch (PDOException $e) {
        error_log('syncSessionWithDb error: ' . $e->getMessage());
    }
}

// Keep session flags synchronized with DB on every request
syncSessionWithDb();