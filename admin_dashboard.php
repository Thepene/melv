<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (($_SESSION['role'] ?? 'staff') !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$notice = $_GET['notice'] ?? '';
$error = $_GET['error'] ?? '';
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$searchTerm = trim($_GET['q'] ?? '');

function supplierNameForProduct(int $productId, array $suppliers): string
{
    foreach ($suppliers as $supplier) {
        if ((int) ($supplier['id'] ?? 0) === $productId) {
            return (string) ($supplier['name'] ?? '');
        }
    }

    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_product' || $action === 'update_product') {
        $id = (int) ($_POST['product_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = trim($_POST['price'] ?? '0');
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);

        if ($name === '' || $sku === '') {
            header('Location: admin_dashboard.php?error=product_name_and_sku_required');
            exit();
        }

        $products = loadJsonStore('products', []);

        if ($action === 'add_product') {
            foreach ($products as $product) {
                if (strtolower((string) ($product['sku'] ?? '')) === strtolower($sku)) {
                    header('Location: admin_dashboard.php?error=product_sku_exists');
                    exit();
                }
            }

            $products[] = [
                'id' => nextJsonId($products),
                'name' => $name,
                'sku' => $sku,
                'category' => $category,
                'price' => $price,
                'supplier_id' => $supplierId,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            saveJsonStore('products', $products);
            header('Location: admin_dashboard.php?notice=product_added');
            exit();
        }

        foreach ($products as $index => $product) {
            if ((int) ($product['id'] ?? 0) === $id) {
                $products[$index] = [
                    'id' => $id,
                    'name' => $name,
                    'sku' => $sku,
                    'category' => $category,
                    'price' => $price,
                    'supplier_id' => $supplierId,
                    'created_at' => $product['created_at'] ?? date('Y-m-d H:i:s'),
                ];
                break;
            }
        }

        saveJsonStore('products', $products);
        header('Location: admin_dashboard.php?notice=product_updated');
        exit();
    }

    if ($action === 'delete_product') {
        $id = (int) ($_POST['product_id'] ?? 0);
        if ($id > 0) {
            $products = loadJsonStore('products', []);
            $transactions = loadJsonStore('stock_transactions', []);

            $filteredProducts = [];
            foreach ($products as $product) {
                if ((int) ($product['id'] ?? 0) !== $id) {
                    $filteredProducts[] = $product;
                }
            }

            $filteredTransactions = [];
            foreach ($transactions as $transaction) {
                if ((int) ($transaction['product_id'] ?? 0) !== $id) {
                    $filteredTransactions[] = $transaction;
                }
            }

            saveJsonStore('products', $filteredProducts);
            saveJsonStore('stock_transactions', $filteredTransactions);
        }

        header('Location: admin_dashboard.php?notice=product_deleted');
        exit();
    }

    if ($action === 'add_transaction') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $type = $_POST['type'] ?? 'IN';
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $referenceNo = trim($_POST['reference_no'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($productId <= 0 || $quantity <= 0) {
            header('Location: admin_dashboard.php?error=invalid_transaction');
            exit();
        }

        $products = loadJsonStore('products', []);
        $productExists = false;
        foreach ($products as $product) {
            if ((int) ($product['id'] ?? 0) === $productId) {
                $productExists = true;
                break;
            }
        }

        if (!$productExists) {
            header('Location: admin_dashboard.php?error=product_not_found');
            exit();
        }

        $transactions = loadJsonStore('stock_transactions', []);
        $currentStock = inventoryStockForProduct($productId, $transactions);

        if ($type === 'OUT' && $currentStock < $quantity) {
            header('Location: admin_dashboard.php?error=insufficient_stock');
            exit();
        }

        $transactions[] = [
            'id' => nextJsonId($transactions),
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $quantity,
            'reference_no' => $referenceNo,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        saveJsonStore('stock_transactions', $transactions);
        header('Location: admin_dashboard.php?notice=stock_transaction_saved');
        exit();
    }
}

$suppliers = loadJsonStore('suppliers', []);
$products = loadJsonStore('products', []);
$transactions = loadJsonStore('stock_transactions', []);

$filteredProducts = [];
foreach ($products as $product) {
    $productId = (int) ($product['id'] ?? 0);
    $supplierName = '';
    foreach ($suppliers as $supplier) {
        if ((int) ($supplier['id'] ?? 0) === (int) ($product['supplier_id'] ?? 0)) {
            $supplierName = (string) ($supplier['name'] ?? '');
            break;
        }
    }

    $product['stock'] = inventoryStockForProduct($productId, $transactions);
    $product['supplier_name'] = $supplierName;

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

$products = $filteredProducts;
usort($products, static function ($a, $b) {
    return strcasecmp(($a['name'] ?? ''), ($b['name'] ?? ''));
});

$filteredTransactions = [];
foreach ($transactions as $transaction) {
    $productId = (int) ($transaction['product_id'] ?? 0);
    $productName = '';
    $productSku = '';
    foreach ($products as $product) {
        if ((int) ($product['id'] ?? 0) === $productId) {
            $productName = $product['name'] ?? '';
            $productSku = $product['sku'] ?? '';
            break;
        }
    }

    $record = [
        'id' => $transaction['id'] ?? 0,
        'product_name' => $productName,
        'product_sku' => $productSku,
        'type' => $transaction['type'] ?? 'IN',
        'quantity' => $transaction['quantity'] ?? 0,
        'reference_no' => $transaction['reference_no'] ?? '-',
        'notes' => $transaction['notes'] ?? '-',
        'created_at' => $transaction['created_at'] ?? '',
    ];

    if ($searchTerm === '') {
        $filteredTransactions[] = $record;
        continue;
    }

    $haystack = strtolower(implode(' ', [
        (string) ($record['product_name'] ?? ''),
        (string) ($record['product_sku'] ?? ''),
        (string) ($record['reference_no'] ?? ''),
        (string) ($record['notes'] ?? ''),
        (string) ($record['type'] ?? ''),
    ]));

    if (str_contains($haystack, strtolower($searchTerm))) {
        $filteredTransactions[] = $record;
    }
}

$transactionRows = $filteredTransactions;
usort($transactionRows, static function ($a, $b) {
    return strcmp(($b['created_at'] ?? ''), ($a['created_at'] ?? ''));
});

$productForEdit = null;
if ($editId !== null) {
    foreach ($products as $product) {
        if ((int) ($product['id'] ?? 0) === $editId) {
            $productForEdit = $product;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Monitoring Dashboard</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #eef4ff 0%, #f8fafc 100%);
            color: #0f172a;
        }
        .topbar {
            background: rgba(255,255,255,0.9);
            border-bottom: 1px solid #e5e7eb;
            padding: 18px 24px;
        }
        .topbar-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        .nav { display: flex; gap: 14px; flex-wrap: wrap; }
        .nav a { color: #475569; text-decoration: none; font-weight: 700; }
        .page { max-width: 1200px; margin: 24px auto; padding: 0 24px 60px; }
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        .search-form { display: flex; gap: 10px; flex-wrap: wrap; }
        .search-form input { min-width: 240px; }
        .export-group { display: flex; gap: 8px; flex-wrap: wrap; }
        .export-btn, .btn {
            padding: 10px 14px;
            border-radius: 10px;
            border: none;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }
        .export-btn { background: #0f172a; color: white; }
        .btn { background: #2563eb; color: white; }
        .grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 20px; }
        .card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
            padding: 22px;
            border: 1px solid #e5e7eb;
            margin-top: 20px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        .stat-box {
            background: white;
            border-radius: 16px;
            padding: 18px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }
        .stat-box strong { display: block; font-size: 24px; margin-top: 10px; }
        .notice { border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; font-weight: 600; }
        .notice.success { background: #dcfce7; color: #166534; }
        .notice.error { background: #fee2e2; color: #991b1b; }
        .form-grid { display: grid; gap: 12px; }
        input, select, button, textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #d1d5db;
            font-size: 14px;
        }
        button { border: none; background: #2563eb; color: white; font-weight: 700; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        th { color: #64748b; font-size: 12px; text-transform: uppercase; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .mini-btn { padding: 8px 10px; border-radius: 10px; border: none; font-weight: 700; text-decoration: none; display: inline-block; }
        .edit { background: #dbeafe; color: #1d4ed8; }
        .delete { background: #fee2e2; color: #991b1b; }
        .stock-green { color: green; font-weight: bold; }
        .stock-red { color: red; font-weight: bold; }
        @media (max-width: 920px) {
            .stats, .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <div><strong>Inventory Pro</strong></div>
            <div class="nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="inventory.php">Inventory</a>
                <a href="suppliers.php">Suppliers</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </header>

    <div class="page">
        <?php if ($notice !== ''): ?>
            <div class="notice success"><?php echo htmlspecialchars(str_replace('_', ' ', $notice)); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="notice error"><?php echo htmlspecialchars(str_replace('_', ' ', $error)); ?></div>
        <?php endif; ?>

        <div class="toolbar">
            <h1 style="margin:0;">Admin Monitoring Dashboard</h1>
            <div class="export-group">
                <a class="export-btn" href="export_csv.php<?php echo $searchTerm !== '' ? '?q=' . urlencode($searchTerm) : ''; ?>">Export CSV</a>
                <a class="export-btn" href="export_pdf.php<?php echo $searchTerm !== '' ? '?q=' . urlencode($searchTerm) : ''; ?>">Export PDF</a>
            </div>
        </div>

        <form class="search-form" method="GET" action="admin_dashboard.php">
            <input type="text" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search by product, SKU, category, supplier">
            <button class="btn" type="submit">Search</button>
            <?php if ($searchTerm !== ''): ?>
                <a class="btn" href="admin_dashboard.php" style="display:inline-flex;align-items:center;">Clear</a>
            <?php endif; ?>
        </form>

        <div class="stats">
            <div class="stat-box">
                <span>Total Products</span>
                <strong><?php echo count($products); ?></strong>
            </div>
            <div class="stat-box">
                <span>Incoming</span>
                <strong><?php echo array_sum(array_map(static fn($t) => (($t['type'] ?? 'IN') === 'IN') ? (int) ($t['quantity'] ?? 0) : 0, $transactions)); ?></strong>
            </div>
            <div class="stat-box">
                <span>Outgoing</span>
                <strong><?php echo array_sum(array_map(static fn($t) => (($t['type'] ?? 'IN') === 'OUT') ? (int) ($t['quantity'] ?? 0) : 0, $transactions)); ?></strong>
            </div>
            <div class="stat-box">
                <span>Current Stock</span>
                <strong><?php echo array_sum(array_map(static fn($p) => (int) ($p['stock'] ?? 0), $products)); ?></strong>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h2><?php echo $productForEdit ? 'Edit Product' : 'Create Product'; ?></h2>
                <form method="POST" class="form-grid">
                    <input type="hidden" name="action" value="<?php echo $productForEdit ? 'update_product' : 'add_product'; ?>">
                    <?php if ($productForEdit): ?>
                        <input type="hidden" name="product_id" value="<?php echo (int) ($productForEdit['id'] ?? 0); ?>">
                    <?php endif; ?>
                    <input type="text" name="name" placeholder="Product name" value="<?php echo htmlspecialchars($productForEdit['name'] ?? ''); ?>" required>
                    <input type="text" name="sku" placeholder="SKU" value="<?php echo htmlspecialchars($productForEdit['sku'] ?? ''); ?>" required>
                    <input type="text" name="category" placeholder="Category" value="<?php echo htmlspecialchars($productForEdit['category'] ?? ''); ?>">
                    <select name="supplier_id">
                        <option value="0">No supplier selected</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo (int) ($supplier['id'] ?? 0); ?>" <?php echo (($productForEdit['supplier_id'] ?? 0) == ((int) ($supplier['id'] ?? 0))) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" step="0.01" name="price" placeholder="Price" value="<?php echo htmlspecialchars((string) ($productForEdit['price'] ?? '0')); ?>">
                    <button type="submit"><?php echo $productForEdit ? 'Update Product' : 'Add Product'; ?></button>
                </form>
            </div>

            <div class="card">
                <h2>Record Incoming / Outgoing Product</h2>
                <form method="POST" class="form-grid">
                    <input type="hidden" name="action" value="add_transaction">
                    <select name="product_id" required>
                        <option value="">Select product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo (int) ($product['id'] ?? 0); ?>"><?php echo htmlspecialchars($product['name'] ?? ''); ?> (SKU: <?php echo htmlspecialchars($product['sku'] ?? ''); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <select name="type" required>
                        <option value="IN">Incoming</option>
                        <option value="OUT">Outgoing</option>
                    </select>
                    <input type="number" name="quantity" min="1" placeholder="Quantity" required>
                    <input type="text" name="reference_no" placeholder="Reference number">
                    <textarea name="notes" rows="3" placeholder="Notes"></textarea>
                    <button type="submit">Save Transaction</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2>Product Inventory</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($product['sku'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($product['category'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($product['supplier_name'] ?? '-'); ?></td>
                                <td>$<?php echo number_format((float) ($product['price'] ?? 0), 2); ?></td>
                                <td class="<?php echo ((int) ($product['stock'] ?? 0)) > 0 ? 'stock-green' : 'stock-red'; ?>"><?php echo (int) ($product['stock'] ?? 0); ?></td>
                                <td>
                                    <div class="actions">
                                        <a class="mini-btn edit" href="admin_dashboard.php?edit=<?php echo (int) ($product['id'] ?? 0); ?><?php echo $searchTerm !== '' ? '&q=' . urlencode($searchTerm) : ''; ?>">Edit</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this product and its transaction history?');">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="product_id" value="<?php echo (int) ($product['id'] ?? 0); ?>">
                                            <button type="submit" class="mini-btn delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7">No products found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Incoming / Outgoing Product Movement Log</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Reference</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactionRows)): ?>
                        <?php foreach ($transactionRows as $entry): ?>
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
                        <tr><td colspan="6">No movement logs yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
