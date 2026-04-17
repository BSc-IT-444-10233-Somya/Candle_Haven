<?php
require_once '../includes/config.php';

// Check if user is admin
if(!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

$page_title = "Manage Users";
include 'includes/admin-header.php';

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle POST actions (delete or update)
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle delete via POST (safer than GET)
    if (isset($_POST['delete_user'])) {
        $del_id = intval($_POST['delete_user']);
        if ($del_id != $_SESSION['user_id']) {
            $del_sql = "DELETE FROM users WHERE id = $del_id";
            if (mysqli_query($conn, $del_sql)) {
                // Redirect back to avoid form resubmission
                header('Location: users.php');
                exit();
            } else {
                $error = "Error deleting user: " . mysqli_error($conn);
            }
        } else {
            $error = "You cannot delete your own account!";
        }
    }

    // Handle update when update form posted
    elseif (isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        
        $sql = "UPDATE users SET first_name='$first_name', last_name='$last_name', 
                email='$email', is_admin=$is_admin WHERE id=$user_id";
        
        if(mysqli_query($conn, $sql)) {
            // Redirect back to avoid form resubmission
            header('Location: users.php');
            exit();
        } else {
            $error = "Error updating user: " . mysqli_error($conn);
        }
    }
}

// Delete user
if($action == 'delete' && $id > 0) {
    // Don't allow deleting yourself
    if($id != $_SESSION['user_id']) {
        $sql = "DELETE FROM users WHERE id = $id";
        if(mysqli_query($conn, $sql)) {
            $success = "User deleted successfully!";
        } else {
            $error = "Error deleting user: " . mysqli_error($conn);
        }
    } else {
        $error = "You cannot delete your own account!";
    }
}

// Fetch users
$sql = "SELECT * FROM users ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<div class="admin-container">
    <h1>Manage Users</h1>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if($action == 'edit' && $id > 0): 
        $user_sql = "SELECT * FROM users WHERE id = $id";
        $user_result = mysqli_query($conn, $user_sql);
        $user = mysqli_fetch_assoc($user_result);
    ?>
        <div class="admin-form">
            <h2>Edit User</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" required
                               value="<?php echo htmlspecialchars($user['first_name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required
                               value="<?php echo htmlspecialchars($user['last_name']); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo htmlspecialchars($user['email']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    <small>Username cannot be changed</small>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_admin" value="1" 
                               <?php echo $user['is_admin'] ? 'checked' : ''; ?>>
                        Administrator
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn">Update User</button>
                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Actions</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): 
                        while($row = mysqli_fetch_assoc($result)):
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td>
                            <span class="role-badge <?php echo $row['is_admin'] ? 'admin' : 'customer'; ?>">
                                <?php echo $row['is_admin'] ? 'Admin' : 'Customer'; ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        <td class="actions">
                            <a href="users.php?action=edit&id=<?php echo $row['id']; ?>" class="btn-small">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </td>
                        <td>
                            <?php if($row['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" action="" style="display:inline-block;margin:0;">
                                <input type="hidden" name="delete_user" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn-small btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No users found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/admin-footer.php'; ?>