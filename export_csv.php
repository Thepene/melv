<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$products = loadJsonStore('products', []);
$transactions = loadJsonStore('stock_transactions', []);
$suppliers = loadJsonStore('suppliers', []);
$rows = [];

foreach ($products as $product) {
    $supplierName = '';
    foreach ($suppliers as $supplier) {
        if ((int) ($supplier['id'] ?? 0) === (int) ($product['supplier_id'] ?? 0)) {
            $supplierName = $supplier['name'] ?? '';
            break;
        }
    }

    $rows[] = [
        'name' => $product['name'] ?? '',
        'sku' => $product['sku'] ?? '',
        'category' => $product['category'] ?? '',
        'supplier_name' => $supplierName,
        'price' => $product['price'] ?? 0,
        'stock' => inventoryStockForProduct((int) ($product['id'] ?? 0), $transactions),
    ];
}

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
        number_format((float) $row['price'], 2, '.', ''),
        (int) $row['stock'],
    ]);
}

fclose($output);
