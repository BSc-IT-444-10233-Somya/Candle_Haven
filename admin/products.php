<?php
require_once '../includes/config.php';

// Check if user is admin
if(!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

$page_title = "Manage Products";
include 'includes/admin-header.php';

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Add/Edit product
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    // Generate a URL-friendly slug from the product name and ensure uniqueness
    function generate_unique_slug($conn, $name, $exclude_id = null) {
        $base = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
        $slug = $base ?: 'product';
        $i = 1;
        while (true) {
            if ($exclude_id) {
                $check_sql = "SELECT id FROM products WHERE slug = ? AND id != ? LIMIT 1";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                if ($check_stmt) {
                    mysqli_stmt_bind_param($check_stmt, 'si', $slug, $exclude_id);
                    mysqli_stmt_execute($check_stmt);
                    $res = mysqli_stmt_get_result($check_stmt);
                    $count = $res ? mysqli_num_rows($res) : 0;
                    mysqli_stmt_close($check_stmt);
                } else {
                    $count = 0;
                }
            } else {
                $check_sql = "SELECT id FROM products WHERE slug = ? LIMIT 1";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                if ($check_stmt) {
                    mysqli_stmt_bind_param($check_stmt, 's', $slug);
                    mysqli_stmt_execute($check_stmt);
                    $res = mysqli_stmt_get_result($check_stmt);
                    $count = $res ? mysqli_num_rows($res) : 0;
                    mysqli_stmt_close($check_stmt);
                } else {
                    $count = 0;
                }
            }

            if ($count == 0) break;
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = floatval($_POST['price']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $scent = mysqli_real_escape_string($conn, $_POST['scent']);
    $burn_time = intval($_POST['burn_time']);
    $weight = intval($_POST['weight']);
    $in_stock = intval($_POST['in_stock']);
    // create slug (will regenerate for updates with product id)
    $slug = generate_unique_slug($conn, $name);
    
    // Handle image upload
    $image_url = '';
    if (isset($_FILES['image'])) {
        // If PHP reported an upload error (other than no file), surface it so admin can see why upload failed
        if ($_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['image']['error'] !== 0) {
            $uploadErrCode = intval($_FILES['image']['error']);
            switch ($uploadErrCode) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $msg = 'Image exceeds server or form maximum upload size.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $msg = 'Image was only partially uploaded.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $msg = 'Missing temporary folder on server.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $msg = 'Failed to write temp file to disk.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $msg = 'A PHP extension stopped the file upload.';
                    break;
                default:
                    $msg = 'Unknown upload error (code ' . $uploadErrCode . ').';
            }
            $error = 'Image upload error: ' . $msg;
            error_log('products.php: ' . $error . ' tmp=' . ($_FILES['image']['tmp_name'] ?? '')); 
        }
    }

    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];

        // Use server-side MIME detection rather than trusting client-provided type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = $finfo ? finfo_file($finfo, $_FILES['image']['tmp_name']) : $_FILES['image']['type'];
        if ($finfo) finfo_close($finfo);

        if(!in_array($file_type, $allowed_types)) {
            $error = 'Invalid image format. Accepted: JPG, PNG, GIF, WEBP, AVIF.';
        } else {
            // limit size to 2MB
            $max_size = 2 * 1024 * 1024;
            if ($_FILES['image']['size'] > $max_size) {
                $error = 'Image exceeds maximum size of 2MB.';
            } else {
                // Prefer relative project images/products path which is web-accessible
                $upload_dir = __DIR__ . '/../images/products/';

                // Normalize path
                $upload_dir = rtrim($upload_dir, '/\\') . DIRECTORY_SEPARATOR;

                // Ensure directory exists and is writable
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $error = 'Failed to create upload directory: ' . $upload_dir;
                        error_log('products.php: ' . $error);
                    }
                }

                if (!isset($error) && !is_writable($upload_dir)) {
                    $error = 'Upload directory is not writable: ' . $upload_dir;
                    error_log('products.php: ' . $error);
                }

                if (!isset($error)) {
                    // Sanitize filename
                    $orig_name = $_FILES['image']['name'];
                    $safe_name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($orig_name));
                    $filename = uniqid() . '_' . $safe_name;
                    $target_path = $upload_dir . $filename;

                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                        // Ensure file is readable
                        @chmod($target_path, 0644);
                        // Store web-accessible path (relative to project root)
                        $image_url = 'images/products/' . $filename;
                    } else {
                        $lastErr = error_get_last();
                        $errMsg = isset($lastErr['message']) ? $lastErr['message'] : 'unknown';
                        $error = 'Failed to move uploaded file to destination: ' . $target_path . ' (' . $errMsg . ')';
                        error_log('products.php: ' . $error . ' tmp=' . ($_FILES['image']['tmp_name'] ?? '')); 
                    }
                }
            }
        }
    } elseif(isset($_POST['existing_image'])) {
        $image_url = $_POST['existing_image'];
    }
    
    // If any upload/validation error occurred, stop before writing to DB
    if (isset($error)) {
        // Do not proceed with DB update/insert when there's an upload or validation error
    } elseif (isset($_POST['product_id'])) {
        // Update existing product
        $product_id = intval($_POST['product_id']);
        // regenerate slug excluding current product id to avoid conflicts
        $slug = generate_unique_slug($conn, $name, $product_id);

        if(!empty($image_url)) {
            $stmt = mysqli_prepare($conn, "UPDATE products SET name=?, slug=?, description=?, price=?, image_url=?, category=?, scent=?, burn_time_hours=?, weight_grams=?, in_stock=? WHERE id=?");
            if ($stmt) {
                // types: s (name), s (slug), s (description), d (price), s (image_url), s (category), s (scent), i (burn_time), i (weight), i (in_stock), i (id)
                mysqli_stmt_bind_param($stmt, 'sssdsssiiii', $name, $slug, $description, $price, $image_url, $category, $scent, $burn_time, $weight, $in_stock, $product_id);
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Product updated successfully!";

                    // If a new image was uploaded, store it in product_images
                    if (!empty($image_url)) {
                        // Determine if there is already a primary image for this product
                        $check_sql = "SELECT COUNT(*) AS cnt FROM product_images WHERE product_id = ? AND is_primary = 1";
                        $check_stmt = mysqli_prepare($conn, $check_sql);
                        if ($check_stmt) {
                            mysqli_stmt_bind_param($check_stmt, 'i', $product_id);
                            mysqli_stmt_execute($check_stmt);
                            $check_res = mysqli_stmt_get_result($check_stmt);
                            $row = mysqli_fetch_assoc($check_res);
                            $has_primary = ($row && intval($row['cnt']) > 0) ? 1 : 0;
                            mysqli_stmt_close($check_stmt);
                        } else {
                            $has_primary = 0;
                        }

                        $pi_stmt = mysqli_prepare($conn, "INSERT INTO product_images (product_id, image_url, alt_text, is_primary, display_order, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
                        if ($pi_stmt) {
                            $alt = '';
                            $is_primary = $has_primary ? 0 : 1;
                            mysqli_stmt_bind_param($pi_stmt, 'issi', $product_id, $image_url, $alt, $is_primary);
                            if (!mysqli_stmt_execute($pi_stmt)) {
                                error_log('products.php: failed to insert into product_images: ' . mysqli_stmt_error($pi_stmt));
                            }
                            mysqli_stmt_close($pi_stmt);
                        } else {
                            error_log('products.php: failed to prepare product_images insert: ' . mysqli_error($conn));
                        }
                    }
                } else {
                    $error = "Error updating product: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = "Failed to prepare update statement: " . mysqli_error($conn);
            }
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE products SET name=?, slug=?, description=?, price=?, category=?, scent=?, burn_time_hours=?, weight_grams=?, in_stock=? WHERE id=?");
            if ($stmt) {
                // types: s, s, s, d, s, s, i, i, i, i
                mysqli_stmt_bind_param($stmt, 'sssdssiiii', $name, $slug, $description, $price, $category, $scent, $burn_time, $weight, $in_stock, $product_id);
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Product updated successfully!";
                } else {
                    $error = "Error updating product: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = "Failed to prepare update statement: " . mysqli_error($conn);
            }
        }
    } else {
        // Insert new product
        $stmt = mysqli_prepare($conn, "INSERT INTO products (name, slug, description, price, image_url, category, scent, burn_time_hours, weight_grams, in_stock, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            // types: s, s, d, s, s, s, i, i, i
            mysqli_stmt_bind_param($stmt, 'sssdsssiii', $name, $slug, $description, $price, $image_url, $category, $scent, $burn_time, $weight, $in_stock);
            if (mysqli_stmt_execute($stmt)) {
                // Get the new product id
                $new_product_id = mysqli_insert_id($conn);
                $success = "Product added successfully!";

                // If an image was uploaded, insert into product_images
                if (!empty($image_url)) {
                    // No existing images for new product, so mark as primary
                    $pi_stmt = mysqli_prepare($conn, "INSERT INTO product_images (product_id, image_url, alt_text, is_primary, display_order, created_at) VALUES (?, ?, ?, 1, 0, NOW())");
                    if ($pi_stmt) {
                        $alt = '';
                        mysqli_stmt_bind_param($pi_stmt, 'iss', $new_product_id, $image_url, $alt);
                        if (!mysqli_stmt_execute($pi_stmt)) {
                            error_log('products.php: failed to insert into product_images after insert: ' . mysqli_stmt_error($pi_stmt));
                        }
                        mysqli_stmt_close($pi_stmt);
                    } else {
                        error_log('products.php: failed to prepare product_images insert after insert: ' . mysqli_error($conn));
                    }
                }
            } else {
                $error = "Error adding product: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Failed to prepare insert statement: " . mysqli_error($conn);
        }
    }
}

// Delete product
if($action == 'delete' && $id > 0) {
    $sql = "DELETE FROM products WHERE id = $id";
    if(mysqli_query($conn, $sql)) {
        $success = "Product deleted successfully!";
    } else {
        $error = "Error deleting product: " . mysqli_error($conn);
    }
}

// Fetch product for editing
$product = null;
if($action == 'edit' && $id > 0) {
    $sql = "SELECT * FROM products WHERE id = $id";
    $result = mysqli_query($conn, $sql);
    $product = mysqli_fetch_assoc($result);
}
?>

<div class="admin-container">
    <div class="container">
    <h1>Manage Products</h1>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if($action == 'add' || $action == 'edit'): ?>
        <div class="admin-form">
            <h2><?php echo $action == 'add' ? 'Add New Product' : 'Edit Product'; ?></h2>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <?php if($action == 'edit'): ?>
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" required
                               value="<?php echo $product ? htmlspecialchars($product['name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Price (<?php echo CURRENCY; ?>) *</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" required
                               value="<?php echo $product ? $product['price'] : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" rows="4" required><?php echo $product ? htmlspecialchars($product['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" required>
                            <option value="">Select Category</option>
                            <option value="Scented" <?php echo ($product && $product['category'] == 'Scented') ? 'selected' : ''; ?>>Scented</option>
                            <option value="Unscented" <?php echo ($product && $product['category'] == 'Unscented') ? 'selected' : ''; ?>>Unscented</option>
                            <option value="Aromatherapy" <?php echo ($product && $product['category'] == 'Aromatherapy') ? 'selected' : ''; ?>>Aromatherapy</option>
                            <option value="Seasonal" <?php echo ($product && $product['category'] == 'Seasonal') ? 'selected' : ''; ?>>Seasonal</option>
                            <option value="Decorative" <?php echo ($product && $product['category'] == 'Decorative') ? 'selected' : ''; ?>>Decorative</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="scent">Scent</label>
                        <input type="text" id="scent" name="scent"
                               value="<?php echo $product ? htmlspecialchars($product['scent']) : ''; ?>"
                               placeholder="e.g., Vanilla, Lavender, Ocean">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="burn_time">Burn Time (hours) *</label>
                        <input type="number" id="burn_time" name="burn_time" min="1" required
                               value="<?php echo $product ? $product['burn_time_hours'] : '40'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="weight">Weight (grams) *</label>
                        <input type="number" id="weight" name="weight" min="1" required
                               value="<?php echo $product ? $product['weight_grams'] : '300'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="in_stock">Stock Quantity *</label>
                        <input type="number" id="in_stock" name="in_stock" min="0" required
                               value="<?php echo $product ? $product['in_stock'] : '0'; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="image">Product Image</label>
                    <?php if($product && $product['image_url']): ?>
                        <div class="current-image">
                            <img src="../<?php echo $product['image_url']; ?>" alt="Current Image" style="max-width: 200px; margin-bottom: 10px;">
                            <input type="hidden" name="existing_image" value="<?php echo $product['image_url']; ?>">
                        </div>
                    <?php endif; ?>
                    <input type="file" id="image" name="image" accept="image/*">
                    <small>Accepted formats: JPG, PNG, GIF. Max size: 2MB</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn"><?php echo $action == 'add' ? 'Add Product' : 'Update Product'; ?></button>
                    <a href="products.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="admin-actions">
            <a href="products.php?action=add" class="btn"><i class="fas fa-plus"></i> Add New Product</a>
        </div>
        
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT * FROM products ORDER BY created_at DESC";
                    $result = mysqli_query($conn, $sql);
                    
                    if(mysqli_num_rows($result) > 0):
                        while($row = mysqli_fetch_assoc($result)):
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td>
                            <?php if($row['image_url']): ?>
                                <img src="../<?php echo $row['image_url']; ?>" alt="<?php echo $row['name']; ?>" class="table-image">
                            <?php else: ?>
                                <div class="no-image">No Image</div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo $row['category']; ?></td>
                        <td><?php echo format_price($row['price']); ?></td>
                        <td>
                            <span class="stock-badge <?php echo $row['in_stock'] > 10 ? 'in-stock' : ($row['in_stock'] > 0 ? 'low-stock' : 'out-of-stock'); ?>">
                                <?php echo $row['in_stock']; ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="products.php?action=edit&id=<?php echo $row['id']; ?>" class="btn-small">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="products.php?action=delete&id=<?php echo $row['id']; ?>" 
                               class="btn-small btn-danger" 
                               onclick="return confirm('Are you sure you want to delete this product?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="7" class="text-center">No products found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>