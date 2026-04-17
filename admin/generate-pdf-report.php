<?php
require_once '../includes/config.php';

// Check if user is admin
if(!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    die('Unauthorized');
}

// Generate report data as JSON
$report_data = [];

// Dashboard Summary
$sql = "SELECT COUNT(*) as total FROM products";
$result = mysqli_query($conn, $sql);
$report_data['total_products'] = mysqli_fetch_assoc($result)['total'];

$sql = "SELECT COUNT(*) as total FROM orders WHERE status != 'cancelled'";
$result = mysqli_query($conn, $sql);
$report_data['total_orders'] = mysqli_fetch_assoc($result)['total'];

$sql = "SELECT COUNT(*) as total FROM users WHERE is_admin = 0";
$result = mysqli_query($conn, $sql);
$report_data['total_customers'] = mysqli_fetch_assoc($result)['total'];

$sql = "SELECT SUM(total_amount) as total FROM orders WHERE status = 'delivered'";
$result = mysqli_query($conn, $sql);
$report_data['total_revenue'] = mysqli_fetch_assoc($result)['total'] ?: 0;

// Users data
$sql = "SELECT id, first_name, last_name, email, phone, city, created_at 
       FROM users 
       WHERE is_admin = 0 
       ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
$report_data['users'] = [];
while($user = mysqli_fetch_assoc($result)) {
    $report_data['users'][] = $user;
}

// Orders data
$sql = "SELECT o.id, o.total_amount, o.status, o.created_at, u.first_name, u.last_name, u.email 
       FROM orders o 
       LEFT JOIN users u ON o.user_id = u.id 
       ORDER BY o.created_at DESC";
$result = mysqli_query($conn, $sql);
$report_data['orders'] = [];
$total_order_amount = 0;
while($order = mysqli_fetch_assoc($result)) {
    $total_order_amount += $order['total_amount'];
    $report_data['orders'][] = $order;
}
$report_data['total_order_amount'] = $total_order_amount;

// Daily order values
$sql = "SELECT 
           DATE(created_at) as order_date,
           COUNT(*) as total_orders,
           SUM(total_amount) as daily_total,
           SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
           SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
       FROM orders 
       GROUP BY DATE(created_at) 
       ORDER BY order_date DESC
       LIMIT 30";
$result = mysqli_query($conn, $sql);
$report_data['daily_orders'] = [];
$grand_total = 0;
while($daily = mysqli_fetch_assoc($result)) {
    $grand_total += $daily['daily_total'];
    $report_data['daily_orders'][] = $daily;
}
$report_data['daily_grand_total'] = $grand_total;

// Order status summary
$sql = "SELECT 
           status,
           COUNT(*) as count,
           SUM(total_amount) as total
       FROM orders 
       GROUP BY status";
$result = mysqli_query($conn, $sql);
$report_data['status_summary'] = [];
$total_status_count = 0;
while($row = mysqli_fetch_assoc($result)) {
    $report_data['status_summary'][] = $row;
    $total_status_count += $row['count'];
}
$report_data['total_status_count'] = $total_status_count;

// Return as JSON
header('Content-Type: application/json');
echo json_encode($report_data);
?>
