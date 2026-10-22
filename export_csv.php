<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$rows = $pdo->query("SELECT p.name, p.sku, p.category, s.name AS supplier_name, p.price,
    COALESCE(SUM(CASE WHEN st.type = 'IN' THEN st.quantity ELSE -st.quantity END), 0) AS stock
    FROM products p
    LEFT JOIN suppliers s ON s.id = p.supplier_id
    LEFT JOIN stock_transactions st ON st.product_id = p.id
    GROUP BY p.id, p.name, p.sku, p.category, s.name, p.price
    ORDER BY p.name ASC")->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="inventory_export.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Product', 'SKU', 'Category', 'Supplier', 'Price', 'Stock']);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['name'],
        $row['sku'],
        $row['category'] ?: '',
        $row['supplier_name'] ?: '',
        number_format((float)$row['price'], 2, '.', ''),
        (int)$row['stock'],
    ]);
}

fclose($output);
