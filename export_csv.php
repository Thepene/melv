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
$searchTerm = trim($_GET['q'] ?? '');

$filteredProducts = [];
foreach ($products as $product) {
    $supplierName = '';
    foreach ($suppliers as $supplier) {
        if ((int) ($supplier['id'] ?? 0) === (int) ($product['supplier_id'] ?? 0)) {
            $supplierName = (string) ($supplier['name'] ?? '');
            break;
        }
    }

    $product['supplier_name'] = $supplierName;
    $product['stock'] = inventoryStockForProduct((int) ($product['id'] ?? 0), $transactions);

    if ($searchTerm === '') {
        $filteredProducts[] = $product;
        continue;
    }

    $haystack = strtolower(implode(' ', [
        (string) ($product['name'] ?? ''),
        (string) ($product['sku'] ?? ''),
        (string) ($product['category'] ?? ''),
        $supplierName,
    ]));

    if (str_contains($haystack, strtolower($searchTerm))) {
        $filteredProducts[] = $product;
    }
}

$rows = $filteredProducts;
usort($rows, static function ($a, $b) {
    return strcasecmp(($a['name'] ?? ''), ($b['name'] ?? ''));
});

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="inventory_export.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Product', 'SKU', 'Category', 'Supplier', 'Price', 'Stock']);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['name'] ?? '',
        $row['sku'] ?? '',
        $row['category'] ?? '',
        $row['supplier_name'] ?? '',
        number_format((float) ($row['price'] ?? 0), 2, '.', ''),
        (int) ($row['stock'] ?? 0),
    ]);
}

fclose($output);
