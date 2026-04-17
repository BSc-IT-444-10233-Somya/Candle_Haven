<?php
require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "SITE_URL: " . SITE_URL . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? '') . "\n";

$files = [
    'orders.php' => __DIR__ . '/orders.php',
    'admin/orders.php' => __DIR__ . '/admin/orders.php',
];

foreach ($files as $k => $path) {
    echo "\nChecking: $k\n";
    echo "Path: $path\n";
    echo "Exists: " . (file_exists($path) ? 'yes' : 'no') . "\n";
    if (file_exists($path)) {
        echo "Realpath: " . realpath($path) . "\n";
        echo "Size: " . filesize($path) . " bytes\n";
    }
}

echo "\nTest link examples:\n";
echo SITE_URL . "orders.php?action=view&id=1\n";
echo SITE_URL . "admin/orders.php?action=view&id=1\n";

?>
