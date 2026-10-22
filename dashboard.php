<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$productMessage = '';
$transactionMessage = '';
$transactionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = trim($_POST['price'] ?? '');

        if ($name === '' || $sku === '') {
            $productMessage = 'Product name and SKU are required.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO products (name, sku, category, price) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $sku, $category, $price]);
                $productMessage = 'Product added successfully.';
            } catch (PDOException $e) {
                $productMessage = 'Error: ' . $e->getMessage();
            }
        }
    }

    if (isset($_POST['add_transaction'])) {
        $product_id = $_POST['product_id'] ?? '';
        $type = $_POST['type'] ?? 'IN';
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reference_no = trim($_POST['reference_no'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($product_id === '' || $quantity <= 0) {
            $transactionError = 'Please select a product and enter a valid quantity.';
        } else {
            $stockCheck = $pdo->prepare("
                SELECT COALESCE(SUM(CASE WHEN type = 'IN' THEN quantity ELSE -quantity END), 0) AS stock
                FROM stock_transactions
                WHERE product_id = ?
            ");
            $stockCheck->execute([$product_id]);
            $currentStock = (int)$stockCheck->fetchColumn();

            if ($type === 'OUT' && $currentStock < $quantity) {
                $transactionError = 'Not enough stock available for this outgoing transaction.';
            } else {
                try {
                    $stmt = $pdo->prepare('INSERT INTO stock_transactions (product_id, type, quantity, reference_no, notes) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$product_id, $type, $quantity, $reference_no, $notes]);
                    $transactionMessage = 'Stock transaction recorded successfully.';
                } catch (PDOException $e) {
                    $transactionError = 'Error: ' . $e->getMessage();
                }
            }
        }
    }
}

$products = $pdo->query("
    SELECT p.id, p.name, p.sku, p.category, p.price,
           COALESCE(SUM(CASE WHEN st.type = 'IN' THEN st.quantity ELSE -st.quantity END), 0) AS stock
    FROM products p
    LEFT JOIN stock_transactions st ON st.product_id = p.id
    GROUP BY p.id, p.name, p.sku, p.category, p.price
")->fetchAll(PDO::FETCH_ASSOC);

$transactions = $pdo->query("
    SELECT st.*, p.name AS product_name
    FROM stock_transactions st
    JOIN products p ON p.id = st.product_id
    ORDER BY st.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
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
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <h1>Inventory Management System</h1>
            <a class="logout-link" href="logout.php">Logout</a>
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
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?> (SKU: <?php echo htmlspecialchars($p['sku']); ?>)</option>
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
                        <th>Unit Price</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                <td><?php echo htmlspecialchars($product['category'] ?: '-'); ?></td>
                                <td>$<?php echo number_format((float)$product['price'], 2); ?></td>
                                <td class="<?php echo $product['stock'] > 0 ? 'stock-green' : 'stock-red'; ?>"><?php echo (int)$product['stock']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No products available.</td></tr>
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
                    <?php if (count($transactions) > 0): ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($t['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($t['product_name']); ?></td>
                                <td><?php echo $t['type'] === 'IN' ? 'Incoming' : 'Outgoing'; ?></td>
                                <td><?php echo (int)$t['quantity']; ?></td>
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
