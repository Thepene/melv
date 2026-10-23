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

$productSummary = [];
foreach ($products as $product) {
    $productId = (int) ($product['id'] ?? 0);
    $supplierName = '';
    foreach ($suppliers as $supplier) {
        if ((int) ($supplier['id'] ?? 0) === (int) ($product['supplier_id'] ?? 0)) {
            $supplierName = (string) ($supplier['name'] ?? '');
            break;
        }
    }

    $productSummary[] = [
        'id' => $productId,
        'name' => $product['name'] ?? '',
        'sku' => $product['sku'] ?? '',
        'category' => $product['category'] ?? '',
        'supplier_name' => $supplierName,
        'price' => $product['price'] ?? 0,
        'stock' => inventoryStockForProduct($productId, $transactions),
    ];
}

$incomingTotal = 0;
$outgoingTotal = 0;
foreach ($transactions as $transaction) {
    $quantity = (int) ($transaction['quantity'] ?? 0);
    if (($transaction['type'] ?? 'IN') === 'IN') {
        $incomingTotal += $quantity;
    } else {
        $outgoingTotal += $quantity;
    }
}

$transactionRows = [];
foreach ($transactions as $transaction) {
    $productId = (int) ($transaction['product_id'] ?? 0);
    $productName = '';

    foreach ($products as $product) {
        if ((int) ($product['id'] ?? 0) === $productId) {
            $productName = $product['name'] ?? '';
            break;
        }
    }

    $transactionRows[] = [
        'id' => $transaction['id'] ?? 0,
        'product_name' => $productName,
        'type' => $transaction['type'] ?? 'IN',
        'quantity' => $transaction['quantity'] ?? 0,
        'reference_no' => $transaction['reference_no'] ?? '-',
        'notes' => $transaction['notes'] ?? '-',
        'created_at' => $transaction['created_at'] ?? '',
    ];
}

usort($transactionRows, static function ($a, $b) {
    return strcmp(($b['created_at'] ?? ''), ($a['created_at'] ?? ''));
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Dashboard</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #0f172a;
        }
        .container { max-width: 1200px; margin: auto; padding: 24px; }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        .nav { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .nav a { color: #475569; text-decoration: none; font-weight: 700; }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin: 20px 0;
        }
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        .stat-box strong { display: block; font-size: 26px; margin-top: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background: #f8fafc; }
        .stock-green { color: green; font-weight: bold; }
        .stock-red { color: red; font-weight: bold; }
        .button-link {
            display: inline-block;
            padding: 10px 14px;
            background: #2563eb;
            color: white;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
        }
        @media (max-width: 760px) {
            .stats { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <h1>Inventory Dashboard</h1>
            <div class="nav">
                <a href="inventory.php">Inventory</a>
                <a href="suppliers.php">Suppliers</a>
                <?php if (($_SESSION['role'] ?? 'staff') === 'admin'): ?>
                    <a href="admin_dashboard.php">Admin Monitoring</a>
                <?php endif; ?>
                <a href="logout.php">Logout</a>
            </div>
        </div>

        <div class="stats">
            <div class="card stat-box">
                <span>Total Products</span>
                <strong><?php echo count($products); ?></strong>
            </div>
            <div class="card stat-box">
                <span>Incoming</span>
                <strong><?php echo $incomingTotal; ?></strong>
            </div>
            <div class="card stat-box">
                <span>Outgoing</span>
                <strong><?php echo $outgoingTotal; ?></strong>
            </div>
            <div class="card stat-box">
                <span>Current Stock</span>
                <strong><?php echo array_sum(array_map(static fn($item) => (int) ($item['stock'] ?? 0), $productSummary)); ?></strong>
            </div>
        </div>

        <?php if (($_SESSION['role'] ?? 'staff') === 'admin'): ?>
            <div class="card" style="margin-bottom: 20px;">
                <a class="button-link" href="admin_dashboard.php">Open Admin Monitoring Dashboard</a>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Current Inventory</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>Price</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($productSummary)): ?>
                        <?php foreach ($productSummary as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                <td><?php echo htmlspecialchars($product['category'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($product['supplier_name'] ?: '-'); ?></td>
                                <td>$<?php echo number_format((float) ($product['price'] ?? 0), 2); ?></td>
                                <td class="<?php echo ((int) ($product['stock'] ?? 0)) > 0 ? 'stock-green' : 'stock-red'; ?>"><?php echo (int) ($product['stock'] ?? 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No products available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h2>Recent Product Movement</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Reference</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactionRows)): ?>
                        <?php foreach (array_slice($transactionRows, 0, 10) as $entry): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($entry['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($entry['product_name'] ?: '-'); ?></td>
                                <td><?php echo (($entry['type'] ?? 'IN') === 'IN') ? 'Incoming' : 'Outgoing'; ?></td>
                                <td><?php echo (int) ($entry['quantity'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($entry['reference_no'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($entry['notes'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No movements recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
