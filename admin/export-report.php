<?php
require_once '../includes/config.php';

// Check if user is admin
if(!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// Get filter parameters
$filter_start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : '';
$filter_end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : '';
$filter_status = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : '';
$filter_min_amount = isset($_GET['min_amount']) && !empty($_GET['min_amount']) ? floatval($_GET['min_amount']) : 0;
$filter_max_amount = isset($_GET['max_amount']) && !empty($_GET['max_amount']) ? floatval($_GET['max_amount']) : 999999;
$filter_section = isset($_GET['section']) ? $_GET['section'] : 'all'; // all, orders, users, daily

// Build WHERE clause for orders
$orders_where = "1=1";
if (!empty($filter_start_date)) {
    $orders_where .= " AND DATE(o.created_at) >= '" . mysqli_real_escape_string($conn, $filter_start_date) . "'";
}
if (!empty($filter_end_date)) {
    $orders_where .= " AND DATE(o.created_at) <= '" . mysqli_real_escape_string($conn, $filter_end_date) . "'";
}
if (!empty($filter_status)) {
    $orders_where .= " AND o.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}
if ($filter_min_amount > 0) {
    $orders_where .= " AND o.total_amount >= $filter_min_amount";
}
if ($filter_max_amount < 999999) {
    $orders_where .= " AND o.total_amount <= $filter_max_amount";
}

// Build WHERE clause for users
$users_where = "1=1";

// Get statistics (filtered)
$sql = "SELECT COUNT(*) as total FROM products";
$result = mysqli_query($conn, $sql);
$total_products = mysqli_fetch_assoc($result)['total'];

if ($filter_section == 'all' || $filter_section == 'orders') {
    $sql = "SELECT COUNT(*) as total FROM orders o WHERE $orders_where";
    $result = mysqli_query($conn, $sql);
    $total_orders = mysqli_fetch_assoc($result)['total'];
} else {
    $total_orders = 0;
}

if ($filter_section == 'all' || $filter_section == 'users') {
    $sql = "SELECT COUNT(*) as total FROM users u WHERE $users_where";
    $result = mysqli_query($conn, $sql);
    $total_customers = mysqli_fetch_assoc($result)['total'];
} else {
    $total_customers = 0;
}

if ($filter_section == 'all' || $filter_section == 'orders') {
    $sql = "SELECT SUM(o.total_amount) as total FROM orders o WHERE $orders_where AND o.status = 'delivered'";
    $result = mysqli_query($conn, $sql);
    $total_revenue = mysqli_fetch_assoc($result)['total'] ?: 0;
} else {
    $total_revenue = 0;
}

// Get Users (filtered)
$users = [];
if ($filter_section == 'all' || $filter_section == 'users') {
    $sql = "SELECT id, first_name, last_name, email, phone, city 
           FROM users 
           WHERE $users_where 
           ORDER BY id DESC";
    $users_result = mysqli_query($conn, $sql);
    while($user = mysqli_fetch_assoc($users_result)) {
        $users[] = $user;
    }
}

// Get Orders (filtered)
$orders = [];
$total_order_amount = 0;
if ($filter_section == 'all' || $filter_section == 'orders') {
    $sql = "SELECT o.id, o.total_amount, o.status, o.created_at, u.first_name, u.last_name, u.email 
           FROM orders o 
           LEFT JOIN users u ON o.user_id = u.id 
           WHERE $orders_where 
           ORDER BY o.created_at DESC";
    $orders_result = mysqli_query($conn, $sql);
    while($order = mysqli_fetch_assoc($orders_result)) {
        $total_order_amount += $order['total_amount'];
        $orders[] = $order;
    }
}

// Get Daily Orders (filtered)
$daily_orders = [];
$grand_total = 0;
if ($filter_section == 'all' || $filter_section == 'daily') {
    $sql = "SELECT 
               DATE(o.created_at) as order_date,
               COUNT(*) as total_orders,
               SUM(o.total_amount) as daily_total,
               SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
               SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_count
           FROM orders o
           WHERE $orders_where
           GROUP BY DATE(o.created_at) 
           ORDER BY order_date DESC
           LIMIT 30";
    $daily_result = mysqli_query($conn, $sql);
    while($daily = mysqli_fetch_assoc($daily_result)) {
        $grand_total += $daily['daily_total'];
        $daily_orders[] = $daily;
    }
}

// Get Status Summary (filtered)
$status_summary = [];
$total_status_count = 0;
if ($filter_section == 'all' || $filter_section == 'orders') {
    $sql = "SELECT 
               status,
               COUNT(*) as count,
               SUM(total_amount) as total
           FROM orders 
           WHERE $orders_where
           GROUP BY status";
    $status_result = mysqli_query($conn, $sql);
    while($row = mysqli_fetch_assoc($status_result)) {
        $status_summary[] = $row;
        $total_status_count += $row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Report - <?php echo date('Y-m-d H:i:s'); ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            color: #333;
            background: #f5f5f5;
            padding: 20px;
            font-size: 12px;
        }
        
        #report-content {
            background: white;
            padding: 20px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #8B7355;
            padding-bottom: 20px;
        }
        
        .report-header h1 {
            color: #8B7355;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .report-header p {
            color: #666;
            font-size: 12px;
        }
        
        .report-section {
            margin: 30px 0;
            page-break-inside: avoid;
        }
        
        .report-section h2 {
            color: #8B7355;
            font-size: 16px;
            margin-bottom: 15px;
            border-bottom: 2px solid #D4A574;
            padding-bottom: 8px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: #F5E6D3;
            border: 1px solid #D4A574;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        
        .stat-box h3 {
            font-size: 24px;
            color: #8B7355;
            font-weight: bold;
        }
        
        .stat-box p {
            color: #666;
            font-size: 11px;
            margin-top: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        
        table thead {
            background: #8B7355;
            color: white;
        }
        
        table th {
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        
        table td {
            padding: 8px;
            border-bottom: 1px solid #E0E0E0;
        }
        
        table tbody tr:nth-child(even) {
            background: #F9F9F9;
        }
        
        .total-row {
            background: #D4A574;
            color: white;
            font-weight: bold;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #E0E0E0;
            color: #999;
            font-size: 10px;
        }
        
        .action-buttons {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            background: #8B7355;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #6d5a43;
        }
        
        @media print {
            .action-buttons {
                display: none;
            }
            
            body {
                background: white;
                padding: 0;
            }
            
            #report-content {
                padding: 0;
                box-shadow: none;
                margin: 0;
            }
        }

        /* Filter Section Styles */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .filter-section h3 {
            color: #8B7355;
            margin-bottom: 15px;
            font-size: 16px;
            border-bottom: 2px solid #D4A574;
            padding-bottom: 10px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 12px;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px;
            border: 1px solid #D4A574;
            border-radius: 4px;
            font-family: Arial, sans-serif;
            font-size: 12px;
            background: white;
            color: #333;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #8B7355;
            box-shadow: 0 0 5px rgba(139, 115, 85, 0.3);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s;
        }

        .filter-btn-apply {
            background: #8B7355;
            color: white;
        }

        .filter-btn-apply:hover {
            background: #6d5a43;
        }

        .filter-btn-reset {
            background: #E8E8E8;
            color: #333;
            border: 1px solid #D4A574;
        }

        .filter-btn-reset:hover {
            background: #D0D0D0;
        }

        .filter-status-badge {
            display: inline-block;
            padding: 5px 10px;
            background: #E8F4F8;
            border: 1px solid #8B7355;
            border-radius: 4px;
            font-size: 12px;
            color: #8B7355;
            margin-right: 10px;
            margin-bottom: 10px;
        }

        .filter-status-badge .remove {
            cursor: pointer;
            margin-left: 5px;
            font-weight: bold;
        }

        @media print {
            .filter-section {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Filter Section -->
    <div class="filter-section">
        <h3>🔍 Filter Report Data</h3>
        <form method="GET" id="filterForm">
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="section">Report Section:</label>
                    <select name="section" id="section">
                        <option value="all" <?php echo $filter_section == 'all' ? 'selected' : ''; ?>>All Sections</option>
                        <option value="orders" <?php echo $filter_section == 'orders' ? 'selected' : ''; ?>>Orders Only</option>
                        <option value="users" <?php echo $filter_section == 'users' ? 'selected' : ''; ?>>Users Only</option>
                        <option value="daily" <?php echo $filter_section == 'daily' ? 'selected' : ''; ?>>Daily Orders Only</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                </div>

                <div class="filter-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                </div>

                <div class="filter-group">
                    <label for="status">Order Status:</label>
                    <select name="status" id="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="shipped" <?php echo $filter_status == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $filter_status == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="min_amount">Min Amount (₹):</label>
                    <input type="number" name="min_amount" id="min_amount" min="0" step="0.01" placeholder="0" value="<?php echo $filter_min_amount > 0 ? htmlspecialchars($filter_min_amount) : ''; ?>">
                </div>

                <div class="filter-group">
                    <label for="max_amount">Max Amount (₹):</label>
                    <input type="number" name="max_amount" id="max_amount" min="0" step="0.01" placeholder="999999" value="<?php echo $filter_max_amount < 999999 ? htmlspecialchars($filter_max_amount) : ''; ?>">
                </div>
            </div>

            <div class="filter-buttons">
                <button type="submit" class="filter-btn filter-btn-apply">
                    ✓ Apply Filters
                </button>
                <button type="button" class="filter-btn filter-btn-reset" onclick="resetFilters()">
                    ⟲ Reset Filters
                </button>
            </div>

            <!-- Active Filters Display -->
            <?php 
            $active_filters = [];
            if (!empty($filter_start_date)) $active_filters[] = "Start: " . $filter_start_date;
            if (!empty($filter_end_date)) $active_filters[] = "End: " . $filter_end_date;
            if (!empty($filter_status)) $active_filters[] = "Status: " . ucfirst($filter_status);
            if ($filter_min_amount > 0) $active_filters[] = "Min: ₹" . $filter_min_amount;
            if ($filter_max_amount < 999999) $active_filters[] = "Max: ₹" . $filter_max_amount;
            
            if (!empty($active_filters)): 
            ?>
            <div style="margin-top: 10px;">
                <strong>Active Filters:</strong><br>
                <?php foreach($active_filters as $filter): ?>
                    <span class="filter-status-badge"><?php echo $filter; ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="action-buttons">
        <button class="btn" onclick="downloadPDF()">
            📥 Download as PDF
        </button>
        <button class="btn" onclick="window.print()">
            🖨️ Print Report
        </button>
        <a href="index.php" class="btn" style="text-decoration: none;">← Back to Dashboard</a>
    </div>

    <div id="report-content">
        <!-- Report Header -->
        <div class="report-header">
            <h1>🕯️ Candle Shop - Admin Report</h1>
            <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <?php if (!empty($active_filters)): ?>
            <p style="color: #D4A574; font-size: 11px; margin-top: 10px;">
                📊 Filtered Report - <?php echo count($active_filters); ?> filter(s) applied
            </p>
            <?php endif; ?>
        </div>
        
        <!-- Dashboard Summary -->
        <div class="report-section">
            <h2>Dashboard Summary <?php echo !empty($active_filters) ? '(Filtered)' : ''; ?></h2>
            <div class="stats-grid">
                <div class="stat-box">
                    <h3><?php echo $total_products; ?></h3>
                    <p>Total Products</p>
                </div>
                
                <div class="stat-box">
                    <h3><?php echo $total_orders; ?></h3>
                    <p>Total Orders<?php echo !empty($active_filters) && ($filter_section == 'all' || $filter_section == 'orders') ? ' (Filtered)' : ''; ?></p>
                </div>
                
                <div class="stat-box">
                    <h3><?php echo $total_customers; ?></h3>
                    <p>Total Customers<?php echo !empty($active_filters) && ($filter_section == 'all' || $filter_section == 'users') ? ' (Filtered)' : ''; ?></p>
                </div>
                
                <div class="stat-box">
                    <h3>₹<?php echo number_format($total_revenue, 2); ?></h3>
                    <p>Total Revenue<?php echo !empty($active_filters) && ($filter_section == 'all' || $filter_section == 'orders') ? ' (Filtered)' : ''; ?></p>
                </div>
            </div>
        </div>
        
        <!-- User Information -->
        <?php if ($filter_section == 'all' || $filter_section == 'users'): ?>
        <div class="report-section">
            <h2>User Information</h2>
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>City</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($users) > 0): 
                        foreach($users as $user): ?>
                    <tr>
                        <td>#<?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo $user['phone'] ?: 'N/A'; ?></td>
                        <td><?php echo htmlspecialchars($user['city'] ?: 'N/A'); ?></td>
                    </tr>
                    <?php endforeach;
                    else: ?>
                    <tr><td colspan="5" style="text-align: center;">No users found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Orders Summary -->
        <?php if ($filter_section == 'all' || $filter_section == 'orders'): ?>
        <div class="report-section">
            <h2>Orders Summary</h2>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Order Total</th>
                        <th>Status</th>
                        <th>Order Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($orders) > 0): 
                        foreach($orders as $order): 
                            $status_badge = ucfirst($order['status']);
                    ?>
                    <tr>
                        <td>#<?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars($order['email'] ?? ''); ?></td>
                        <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td><?php echo $status_badge; ?></td>
                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; 
                        echo '<tr class="total-row"><td colspan="3">TOTAL</td><td>₹' . number_format($total_order_amount, 2) . '</td><td colspan="2"></td></tr>';
                    else: ?>
                    <tr><td colspan="6" style="text-align: center;">No orders found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Daily Order Values -->
        <?php if ($filter_section == 'all' || $filter_section == 'daily'): ?>
        <div class="report-section">
            <h2>Daily Order Values</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Orders Count</th>
                        <th>Total Amount</th>
                        <th>Delivered Orders</th>
                        <th>Pending Orders</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($daily_orders) > 0): 
                        foreach($daily_orders as $daily): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($daily['order_date'])); ?></td>
                        <td><?php echo $daily['total_orders']; ?></td>
                        <td>₹<?php echo number_format($daily['daily_total'], 2); ?></td>
                        <td><?php echo $daily['delivered_count']; ?></td>
                        <td><?php echo $daily['pending_count']; ?></td>
                    </tr>
                    <?php endforeach; 
                        echo '<tr class="total-row"><td colspan="2">TOTAL (Last 30 Days)</td><td>₹' . number_format($grand_total, 2) . '</td><td colspan="2"></td></tr>';
                    else: ?>
                    <tr><td colspan="5" style="text-align: center;">No order data found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Order Status Summary -->
        <?php if ($filter_section == 'all' || $filter_section == 'orders'): ?>
        <div class="report-section">
            <h2>Order Status Summary</h2>
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                        <th>Total Amount</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($status_summary as $status): 
                        $percentage = ($status['count'] / $total_status_count * 100);
                    ?>
                    <tr>
                        <td><?php echo ucfirst($status['status']); ?></td>
                        <td><?php echo $status['count']; ?></td>
                        <td>₹<?php echo number_format($status['total'], 2); ?></td>
                        <td><?php echo number_format($percentage, 1); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Report Footer -->
        <div class="footer">
            <p>This is an automated report generated from Candle Shop Admin Dashboard</p>
            <p>Report Generated: <?php echo date('F j, Y \a\t g:i A'); ?></p>
        </div>
    </div>

    <script>
        function downloadPDF() {
            const element = document.getElementById('report-content');
            const opt = {
                margin: 10,
                filename: 'admin_report_<?php echo date('Y-m-d_H-i-s'); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { orientation: 'portrait', unit: 'mm', format: 'a4' }
            };
            html2pdf().set(opt).from(element).save();
        }

        function resetFilters() {
            // Clear all form fields
            document.getElementById('start_date').value = '';
            document.getElementById('end_date').value = '';
            document.getElementById('status').value = '';
            document.getElementById('min_amount').value = '';
            document.getElementById('max_amount').value = '';
            document.getElementById('section').value = 'all';
            
            // Submit the form to reset filters
            document.getElementById('filterForm').submit();
        }

        // Validate date ranges
        document.getElementById('end_date').addEventListener('change', function() {
            const startDate = document.getElementById('start_date').value;
            const endDate = this.value;
            
            if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
                alert('End date must be after start date');
                this.value = '';
            }
        });

        // Validate amount ranges
        document.getElementById('max_amount').addEventListener('change', function() {
            const minAmount = parseFloat(document.getElementById('min_amount').value) || 0;
            const maxAmount = parseFloat(this.value) || 999999;
            
            if (minAmount > maxAmount) {
                alert('Max amount must be greater than min amount');
                this.value = '';
            }
        });
    </script>
</body>
</html>
