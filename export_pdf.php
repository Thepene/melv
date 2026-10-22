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

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="inventory_export.pdf"');

$pdf = "%PDF-1.4\n";
$objects = [];
$objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
$objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
$objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>";
$objects[] = "<< /Length " . (strlen("BT\n/F1 12 Tf\n50 760 Td\n(Inventory Report) Tj\n0 -20 Td\n/F1 10 Tf\n") + 1) . " >>\nstream\nBT\n/F1 12 Tf\n50 760 Td\n(Inventory Report) Tj\n0 -20 Td\n/F1 10 Tf\n";
$y = 730;
foreach ($rows as $index => $row) {
    $line = sprintf('%s | %s | %s | %s | %.2f | %d', $row['name'], $row['sku'], $row['category'] ?: '', $row['supplier_name'] ?: '', (float)$row['price'], (int)$row['stock']);
    $line = preg_replace('/[\r\n]+/', ' ', $line);
    $pdf .= "50 {$y} Td\n(" . str_replace(')', '\)', str_replace('\\', '\\\\', $line)) . ") Tj\n";
    $y -= 15;
    if ($y < 60) { break; }
}
$pdf .= "ET\nendstream";
$objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

$offsets = [];
$pdf .= "\n";
foreach ($objects as $i => $object) {
    $offsets[$i] = strlen($pdf);
    $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
}
$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 6\n0000000000 65535 f \n";
for ($i = 0; $i < count($objects); $i++) {
    $pdf .= sprintf('%010d 00000 n \n', $offsets[$i]);
}
$pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

echo $pdf;
