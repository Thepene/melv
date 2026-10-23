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

$rows = [];
foreach ($products as $product) {
    $supplierName = '';
    foreach ($suppliers as $supplier) {
        if ((int) ($supplier['id'] ?? 0) === (int) ($product['supplier_id'] ?? 0)) {
            $supplierName = $supplier['name'] ?? '';
            break;
        }
    }

    $productRow = [
        'name' => $product['name'] ?? '',
        'sku' => $product['sku'] ?? '',
        'category' => $product['category'] ?? '',
        'supplier_name' => $supplierName,
        'price' => $product['price'] ?? 0,
        'stock' => inventoryStockForProduct((int) ($product['id'] ?? 0), $transactions),
    ];

    if ($searchTerm === '') {
        $rows[] = $productRow;
        continue;
    }

    $haystack = strtolower(implode(' ', [
        (string) ($productRow['name'] ?? ''),
        (string) ($productRow['sku'] ?? ''),
        (string) ($productRow['category'] ?? ''),
        (string) ($productRow['supplier_name'] ?? ''),
    ]));

    if (str_contains($haystack, strtolower($searchTerm))) {
        $rows[] = $productRow;
    }
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="inventory_export.pdf"');

$pdf = "%PDF-1.4\n";
$objects = [];
$objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
$objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
$objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>";

$pdfContent = "BT\n/F1 12 Tf\n50 760 Td\n(Inventory Report) Tj\n0 -20 Td\n/F1 10 Tf\n";
$y = 730;
foreach ($rows as $row) {
    $line = sprintf('%s | %s | %s | %s | %.2f | %d', $row['name'], $row['sku'], $row['category'] ?: '', $row['supplier_name'] ?: '', (float) $row['price'], (int) $row['stock']);
    $line = preg_replace('/[\r\n]+/', ' ', $line);
    $pdfContent .= "50 {$y} Td\n(" . str_replace(')', '\\)', str_replace('\\', '\\\\', $line)) . ") Tj\n";
    $y -= 15;
    if ($y < 60) { break; }
}
$pdfContent .= "ET\n";
$objects[] = "<< /Length " . (strlen($pdfContent) + 1) . " >>\nstream\n" . $pdfContent . "endstream";
$objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

$offsets = [];
$pdf .= "\n";
foreach ($objects as $i => $object) {
    $offsets[$i] = strlen($pdf);
    $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
}

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
for ($i = 0; $i < count($objects); $i++) {
    $pdf .= sprintf('%010d 00000 n \n', $offsets[$i]);
}
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

echo $pdf;
