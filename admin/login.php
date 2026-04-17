<?php
require_once __DIR__ . '/../includes/config.php';

// If already logged in as admin, redirect to admin dashboard
if (isset($_SESSION['user_id']) && !empty($_SESSION['is_admin'])) {
    header('Location: index.php');
    exit();
}

$error = '';

// Allow admin to login with either email OR username (identifier)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = mysqli_real_escape_string($conn, trim($_POST['identifier'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($identifier === '') {
        $error = 'Please enter your email or username.';
    } else {
        // Prefer authenticating against the dedicated admins table
        $safe_identifier = mysqli_real_escape_string($conn, $identifier);
        $sql = "SELECT * FROM admins WHERE email = '$safe_identifier' OR username = '$safe_identifier' LIMIT 1";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) == 1) {
            $admin = mysqli_fetch_assoc($result);
                if (password_verify($password, $admin['password'])) {
                if (isset($admin['is_active']) && !$admin['is_active']) {
                    $error = 'Admin account is inactive.';
                } else {
                    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
                            // If 'remember me' checked, extend session cookie lifetime only when enabled
                            $remember = isset($_POST['remember']) && $_POST['remember'] == '1';
                            if ($remember && defined('ENABLE_REMEMBER') && ENABLE_REMEMBER === true) {
                                $lifetime = 30 * 24 * 60 * 60; // 30 days
                                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                                setcookie(session_name(), session_id(), [
                                    'expires' => time() + $lifetime,
                                    'path' => '/',
                                    'secure' => $secure,
                                    'httponly' => true,
                                    'samesite' => 'Lax'
                                ]);
                            }

                    // Map admin identity into session so admin pages work with existing checks
                    $_SESSION['user_id'] = !empty($admin['user_id']) ? $admin['user_id'] : $admin['id'];
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['username'] = $admin['username'] ?? ($admin['name'] ?? $admin['email']);
                    $_SESSION['email'] = $admin['email'];
                    $_SESSION['first_name'] = $admin['name'] ?? '';
                    $_SESSION['last_name'] = '';
                    $_SESSION['user_role'] = 'admin';
                    $_SESSION['is_admin'] = true;

                    header('Location: index.php');
                    exit();
                }
            } else {
                $error = 'Invalid password.';
            }
        } else {
            // Fallback to legacy users table (for sites migrating slowly)
            $sql = "SELECT * FROM users WHERE email = '$safe_identifier' OR username = '$safe_identifier' LIMIT 1";
            $result = mysqli_query($conn, $sql);

            if ($result && mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
                if (password_verify($password, $user['password'])) {
                    $is_admin = (!empty($user['is_admin']) && $user['is_admin']) || (isset($user['role']) && $user['role'] === 'admin');
                    if ($is_admin) {
                        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'] ?? ($user['first_name'] ?? $user['email']);
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['first_name'] = $user['first_name'] ?? '';
                        $_SESSION['last_name'] = $user['last_name'] ?? '';
                        $role = isset($user['role']) ? $user['role'] : ($is_admin ? 'admin' : 'customer');
                        $_SESSION['user_role'] = $role;
                        $_SESSION['is_admin'] = $is_admin;

                        header('Location: index.php');
                        exit();
                    } else {
                        $error = 'Admin access required.';
                    }
                } else {
                    $error = 'Invalid password.';
                }
            } else {
                $error = 'No account found with that email or username.';
            }
        }
    }
}

$page_title = 'Admin Login';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h2>Admin Login</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="identifier">Email or Username</label>
                <input type="text" id="identifier" name="identifier" value="<?php echo isset($_GET['prefill']) && $_GET['prefill'] === 'admin' ? 'admin' : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <label style="display:flex;align-items:center;gap:8px;margin:0;">
                        <input type="checkbox" name="remember" value="1"> Remember me
                    </label>
                </div>

                <button type="submit" class="btn" style="width: 100%;">Login</button>
        </form>

        <p style="text-align: center; margin-top: 20px;">
            Not an admin? <a href="<?php echo SITE_URL; ?>login.php">Login as customer</a>
        </p>
        <p style="text-align: center; margin-top: 10px; font-size:0.9rem;">
            <a href="<?php echo SITE_URL; ?>forgot-password.php" style="color: #d9534f;">Forgot Password?</a>
        </p>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
