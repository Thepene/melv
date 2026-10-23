<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$suppliers = loadJsonStore('suppliers', []);

$productMessage = '';
$transactionMessage = '';
$transactionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = trim($_POST['price'] ?? '0');
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);

        if ($name === '' || $sku === '') {
            $productMessage = 'Product name and SKU are required.';
        } else {
            $products = loadJsonStore('products', []);
            $products[] = [
                'id' => nextJsonId($products),
                'name' => $name,
                'sku' => $sku,
                'category' => $category,
                'price' => $price,
                'supplier_id' => $supplierId,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            if (saveJsonStore('products', $products)) {
                $productMessage = 'Product added successfully.';
            } else {
                $productMessage = 'Unable to save product.';
            }
        }
    }

    if (isset($_POST['add_transaction'])) {
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $type = $_POST['type'] ?? 'IN';
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $reference_no = trim($_POST['reference_no'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($product_id <= 0 || $quantity <= 0) {
            $transactionError = 'Please select a product and enter a valid quantity.';
        } else {
            $transactions = loadJsonStore('stock_transactions', []);
            $currentStock = inventoryStockForProduct($product_id, $transactions);

            if ($type === 'OUT' && $currentStock < $quantity) {
                $transactionError = 'Not enough stock available for this outgoing transaction.';
            } else {
                $transactions[] = [
                    'id' => nextJsonId($transactions),
                    'product_id' => $product_id,
                    'type' => $type,
                    'quantity' => $quantity,
                    'reference_no' => $reference_no,
                    'notes' => $notes,
                    'created_at' => date('Y-m-d H:i:s'),
                ];

                if (saveJsonStore('stock_transactions', $transactions)) {
                    $transactionMessage = 'Stock transaction recorded successfully.';
                } else {
                    $transactionError = 'Unable to save transaction.';
                }
            }
        }
    }
}

$products = loadJsonStore('products', []);
$transactions = loadJsonStore('stock_transactions', []);
$productRows = [];

foreach ($products as $product) {
    $productId = (int) ($product['id'] ?? 0);
    $supplierName = '';
    foreach ($suppliers as $supplier) {
        if ((int) ($supplier['id'] ?? 0) === (int) ($product['supplier_id'] ?? 0)) {
            $supplierName = (string) ($supplier['name'] ?? '');
            break;
        }
    }

    $productRows[] = [
        'id' => $productId,
        'name' => $product['name'] ?? '',
        'sku' => $product['sku'] ?? '',
        'category' => $product['category'] ?? '',
        'supplier_name' => $supplierName,
        'price' => $product['price'] ?? 0,
        'stock' => inventoryStockForProduct($productId, $transactions),
    ];
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
        'reference_no' => $transaction['reference_no'] ?? '',
        'notes' => $transaction['notes'] ?? '',
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
    <title>Inventory Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            margin: 0;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: auto; }
        h1, h2 { color: #1f2937; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        form { display: grid; gap: 12px; }
        input, select, textarea, button { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        button { background: #2563eb; color: white; border: none; cursor: pointer; font-weight: bold; }
        button:hover { background: #1d4ed8; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3f4f6; }
        .success { color: green; background: #ecfdf5; padding: 10px; border-radius: 6px; margin-bottom: 10px; }
        .error { color: #b91c1c; background: #fef2f2; padding: 10px; border-radius: 6px; margin-bottom: 10px; }
        .stock-green { color: green; font-weight: bold; }
        .stock-red { color: red; font-weight: bold; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .logout-link { text-decoration: none; color: #b91c1c; font-weight: bold; }
        .nav-links { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .nav-links a { color: #475569; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <h1>Inventory Management System</h1>
            <div class="nav-links">
                <a href="suppliers.php">Suppliers</a>
                <?php if (($_SESSION['role'] ?? 'staff') === 'admin'): ?>
                    <a href="admin_dashboard.php">Admin Monitoring</a>
                <?php endif; ?>
                <a class="logout-link" href="logout.php">Logout</a>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h2>Add New Product</h2>

                <?php if ($productMessage): ?>
                    <div class="success"><?php echo htmlspecialchars($productMessage); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="text" name="name" placeholder="Product Name" required>
                    <input type="text" name="sku" placeholder="SKU" required>
                    <input type="text" name="category" placeholder="Category">
                    <select name="supplier_id">
                        <option value="0">No supplier selected</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo (int) ($supplier['id'] ?? 0); ?>"><?php echo htmlspecialchars($supplier['name'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" step="0.01" name="price" placeholder="Unit Price">
                    <button type="submit" name="add_product">Add Product</button>
                </form>
            </div>

            <div class="card">
                <h2>Record Stock Movement</h2>

                <?php if ($transactionMessage): ?>
                    <div class="success"><?php echo htmlspecialchars($transactionMessage); ?></div>
                <?php endif; ?>

                <?php if ($transactionError): ?>
                    <div class="error"><?php echo htmlspecialchars($transactionError); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <select name="product_id" required>
                        <option value="">Select Product</option>
                        <?php foreach ($productRows as $p): ?>
                            <option value="<?php echo (int) $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?> (SKU: <?php echo htmlspecialchars($p['sku']); ?>)</option>
                        <?php endforeach; ?>
                    </select>

                    <select name="type" required>
                        <option value="IN">Incoming</option>
                        <option value="OUT">Outgoing</option>
                    </select>

                    <input type="number" name="quantity" min="1" placeholder="Quantity" required>
                    <input type="text" name="reference_no" placeholder="Reference No.">
                    <textarea name="notes" rows="3" placeholder="Notes"></textarea>
                    <button type="submit" name="add_transaction">Save Transaction</button>
                </form>
            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h2>Current Inventory</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>Unit Price</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($productRows) > 0): ?>
                        <?php foreach ($productRows as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                <td><?php echo htmlspecialchars($product['category'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($product['supplier_name'] ?: '-'); ?></td>
                                <td>$<?php echo number_format((float) $product['price'], 2); ?></td>
                                <td class="<?php echo $product['stock'] > 0 ? 'stock-green' : 'stock-red'; ?>"><?php echo (int) $product['stock']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No products available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h2>Recent Stock Transactions</h2>
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
                    <?php if (count($transactionRows) > 0): ?>
                        <?php foreach ($transactionRows as $t): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($t['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($t['product_name']); ?></td>
                                <td><?php echo $t['type'] === 'IN' ? 'Incoming' : 'Outgoing'; ?></td>
                                <td><?php echo (int) $t['quantity']; ?></td>
                                <td><?php echo htmlspecialchars($t['reference_no'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($t['notes'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No transactions recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
