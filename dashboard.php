<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userRole = $_SESSION['role'] ?? 'staff';
$username = $_SESSION['username'] ?? 'User';
$canManageProducts = in_array($userRole, ['admin', 'manager'], true);
$canDeleteProducts = $userRole === 'admin';

$notice = $_GET['notice'] ?? '';
$error = $_GET['error'] ?? '';
$search = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';
$stockFilter = $_GET['stock'] ?? '';

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.category LIKE ? OR s.name LIKE ?)';
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($categoryFilter !== '') {
    $where[] = 'p.category = ?';
    $params[] = $categoryFilter;
}

$sql = "
    SELECT p.id, p.name, p.sku, p.category, p.price, p.image,
           s.name AS supplier_name,
           COALESCE(SUM(CASE WHEN st.type = 'IN' THEN st.quantity ELSE -st.quantity END), 0) AS stock
    FROM products p
    LEFT JOIN stock_transactions st ON st.product_id = p.id
    LEFT JOIN suppliers s ON s.id = p.supplier_id
";

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' GROUP BY p.id, p.name, p.sku, p.category, p.price, p.image, s.name';

if ($stockFilter === 'low') {
    $sql .= ' HAVING stock <= 5';
} elseif ($stockFilter === 'out') {
    $sql .= ' HAVING stock <= 0';
} elseif ($stockFilter === 'in_stock') {
    $sql .= ' HAVING stock > 0';
}

$sql .= ' ORDER BY p.name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
$categoryList = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

function uploadProductPhoto(array $file): ?string {
    if (!isset($file['name']) || $file['name'] === '') {
        return null;
    }

    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowed, true)) {
        return null;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('prod_', true) . '.' . strtolower($ext);
    $target = $uploadDir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        return $filename;
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add_product') {
            if (!$canManageProducts) {
                header('Location: inventory.php?error=not_allowed');
                exit();
            }

            $name = trim($_POST['name'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $price = trim($_POST['price'] ?? '0');
            $supplierId = $_POST['supplier_id'] ?? null;
            $photoName = uploadProductPhoto($_FILES['photo'] ?? ['name' => '']);

            if ($name === '' || $sku === '') {
                header('Location: inventory.php?error=missing_fields');
                exit();
            }

            try {
                $insert = $pdo->prepare('INSERT INTO products (name, sku, category, price, supplier_id, image) VALUES (?, ?, ?, ?, ?, ?)');
                $insert->execute([$name, $sku, $category, $price, $supplierId !== '' ? $supplierId : null, $photoName]);
                header('Location: inventory.php?notice=product_added');
                exit();
            } catch (PDOException $e) {
                header('Location: inventory.php?error=duplicate_sku');
                exit();
            }
        }

        if ($action === 'update_product') {
            if (!$canManageProducts) {
                header('Location: inventory.php?error=not_allowed');
                exit();
            }

            $productId = (int)($_POST['product_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $price = trim($_POST['price'] ?? '0');
            $supplierId = $_POST['supplier_id'] ?? null;
            $photoName = uploadProductPhoto($_FILES['photo'] ?? ['name' => '']);

            if ($productId <= 0 || $name === '' || $sku === '') {
                header('Location: inventory.php?error=invalid_update');
                exit();
            }

            $current = $pdo->prepare('SELECT image FROM products WHERE id = ?');
            $current->execute([$productId]);
            $existingPhoto = $current->fetchColumn();

            if ($photoName !== null) {
                $update = $pdo->prepare('UPDATE products SET name = ?, sku = ?, category = ?, price = ?, supplier_id = ?, image = ? WHERE id = ?');
                $update->execute([$name, $sku, $category, $price, $supplierId !== '' ? $supplierId : null, $photoName, $productId]);
                if ($existingPhoto && $existingPhoto !== $photoName && file_exists(__DIR__ . '/uploads/' . $existingPhoto)) {
                    unlink(__DIR__ . '/uploads/' . $existingPhoto);
                }
            } else {
                $update = $pdo->prepare('UPDATE products SET name = ?, sku = ?, category = ?, price = ?, supplier_id = ? WHERE id = ?');
                $update->execute([$name, $sku, $category, $price, $supplierId !== '' ? $supplierId : null, $productId]);
            }

            header('Location: inventory.php?notice=product_updated');
            exit();
        }

        if ($action === 'delete_product') {
            if (!$canDeleteProducts) {
                header('Location: inventory.php?error=not_allowed');
                exit();
            }

            $productId = (int)($_POST['product_id'] ?? 0);
            if ($productId > 0) {
                $productInfo = $pdo->prepare('SELECT image FROM products WHERE id = ?');
                $productInfo->execute([$productId]);
                $photo = $productInfo->fetchColumn();
                if ($photo && file_exists(__DIR__ . '/uploads/' . $photo)) {
                    unlink(__DIR__ . '/uploads/' . $photo);
                }
                $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$productId]);
                header('Location: inventory.php?notice=product_deleted');
                exit();
            }
        }

        if ($action === 'add_transaction') {
            $product_id = (int)($_POST['product_id'] ?? 0);
            $type = $_POST['type'] ?? 'IN';
            $quantity = (int)($_POST['quantity'] ?? 0);
            $reference_no = trim($_POST['reference_no'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($product_id <= 0 || $quantity <= 0) {
                header('Location: inventory.php?error=invalid_transaction');
                exit();
            }

            $stockCheck = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type = 'IN' THEN quantity ELSE -quantity END), 0) AS stock FROM stock_transactions WHERE product_id = ?");
            $stockCheck->execute([$product_id]);
            $currentStock = (int)$stockCheck->fetchColumn();

            if ($type === 'OUT' && $currentStock < $quantity) {
                header('Location: inventory.php?error=insufficient_stock');
                exit();
            }

            $insert = $pdo->prepare('INSERT INTO stock_transactions (product_id, type, quantity, reference_no, notes) VALUES (?, ?, ?, ?, ?)');
            $insert->execute([$product_id, $type, $quantity, $reference_no, $notes]);
            header('Location: inventory.php?notice=transaction_saved');
            exit();
        }
    }
}

$transactions = $pdo->query("SELECT st.*, p.name AS product_name FROM stock_transactions st JOIN products p ON p.id = st.product_id ORDER BY st.created_at DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory | Modern Dashboard</title>
    <style>
        :root {
            --bg: #f4f7fb;
            --card: #ffffff;
            --card-soft: #eef5ff;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --muted: #6b7280;
            --text: #0f172a;
            --line: #e5e7eb;
            --shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #eef4ff 0%, #f8fafc 100%);
            color: var(--text);
        }

        a { text-decoration: none; }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--line);
            padding: 18px 36px;
        }

        .topbar-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand { font-size: 1.3rem; font-weight: 700; color: var(--text); }
        .nav { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .nav a {
            padding: 10px 14px; border-radius: 10px; color: var(--muted); font-weight: 600;
        }
        .nav a.active, .nav a:hover { background: var(--card-soft); color: var(--primary); }
        .user-pill {
            padding: 8px 12px; border-radius: 999px; background: #e0f2fe; color: #0369a1; font-weight: 700; text-transform: capitalize;
        }
        .page { max-width: 1400px; margin: 32px auto; padding: 0 24px 60px; }
        .notice {
            border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; font-weight: 600;
        }
        .notice.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .notice.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; }
        .card {
            background: var(--card); border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 18px; box-shadow: var(--shadow); padding: 22px;
        }
        .card h2 { margin: 0 0 18px; font-size: 1.2rem; }
        .form-grid { display: grid; gap: 14px; }
        .input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        input, select, textarea, button { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--line); background: #fff; font-size: 14px; color: var(--text); }
        textarea { resize: vertical; min-height: 80px; }
        button { border: none; cursor: pointer; font-weight: 700; transition: 0.2s ease; }
        .primary-btn { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; }
        .secondary-btn { background: #e2e8f0; color: var(--text); }
        .danger-btn { background: var(--danger); color: white; }
        .action-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
        .action-row a, .action-row button { display: inline-block; padding: 10px 14px; border-radius: 12px; font-size: 13px; }
        .export-btn { background: #0f766e; color: white; text-decoration: none; font-weight: 700; }
        .supplier-btn { background: #e0f2fe; color: #075985; text-decoration: none; font-weight: 700; }
        .filter-bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; margin-bottom: 18px; }
        .filter-group { flex: 1 1 180px; }
        .filter-group label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 7px; font-weight: 600; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid var(--line); vertical-align: middle; }
        th { background: #f8fafc; color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; }
        td { font-size: 14px; }
        .stock-badge { display: inline-block; min-width: 80px; text-align: center; padding: 6px 10px; border-radius: 999px; font-weight: 700; }
        .stock-ok { background: #dcfce7; color: #166534; }
        .stock-low { background: #fef3c7; color: #92400e; }
        .stock-zero { background: #fee2e2; color: #991b1b; }
        .product-thumb { width: 42px; height: 42px; object-fit: cover; border-radius: 10px; border: 1px solid var(--line); background: #f8fafc; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .small-btn { padding: 8px 10px; border-radius: 10px; font-size: 12px; border: none; cursor: pointer; font-weight: bold; }
        .small-btn.edit { background: #dbeafe; color: #1d4ed8; }
        .small-btn.delete { background: #fee2e2; color: #b91c1c; }
        .muted { color: var(--muted); }
        @media (max-width: 980px) { .grid { grid-template-columns: 1fr; } .input-row { grid-template-columns: 1fr; } .topbar-inner { flex-direction: column; gap: 12px; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand">Inventory Pro</div>
            <nav class="nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="inventory.php" class="active">Inventory</a>
                <a href="reports.php">Reports</a>
            </nav>
            <div class="user-pill"><?php echo htmlspecialchars($username); ?> • <?php echo htmlspecialchars($userRole); ?></div>
        </div>
    </header>

    <main class="page">
        <?php if ($notice !== ''): ?>
            <div class="notice success"><?php echo htmlspecialchars(str_replace('_', ' ', $notice)); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="notice error"><?php echo htmlspecialchars(str_replace('_', ' ', $error)); ?></div>
        <?php endif; ?>

        <div class="action-row">
            <a href="suppliers.php" class="supplier-btn">Manage Suppliers</a>
            <a href="export_csv.php" class="export-btn">Export CSV</a>
            <a href="export_pdf.php" class="export-btn" target="_blank">Export PDF</a>
        </div>

        <div class="grid">
            <section class="card">
                <h2>Add New Product</h2>
                <form method="POST" class="form-grid" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_product">
                    <input type="text" name="name" placeholder="Product name" required>
                    <div class="input-row">
                        <input type="text" name="sku" placeholder="SKU" required>
                        <input type="text" name="category" placeholder="Category">
                    </div>
                    <div class="input-row">
                        <input type="number" name="price" step="0.01" min="0" placeholder="Unit price">
                        <select name="supplier_id">
                            <option value="">Select supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo (int)$supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="file" name="photo" accept="image/*">
                    <?php if ($canManageProducts): ?>
                        <button type="submit" class="primary-btn">Add Product</button>
                    <?php else: ?>
                        <button type="button" class="secondary-btn" disabled>Restricted</button>
                    <?php endif; ?>
                </form>
            </section>

            <section class="card">
                <h2>New Stock Movement</h2>
                <form method="POST" class="form-grid">
                    <input type="hidden" name="action" value="add_transaction">
                    <select name="product_id" required>
                        <option value="">Select product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo (int)$product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?> (<?php echo htmlspecialchars($product['sku']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-row">
                        <select name="type" required>
                            <option value="IN">Incoming</option>
                            <option value="OUT">Outgoing</option>
                        </select>
                        <input type="number" name="quantity" min="1" placeholder="Quantity" required>
                    </div>
                    <input type="text" name="reference_no" placeholder="Reference no.">
                    <textarea name="notes" placeholder="Notes"></textarea>
                    <button type="submit" class="primary-btn">Save Transaction</button>
                </form>
            </section>
        </div>

        <section class="card" style="margin-top: 24px;">
            <h2>Inventory List</h2>

            <form method="GET" class="filter-bar">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search product or SKU">
                </div>
                <div class="filter-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="">All categories</option>
                        <?php foreach ($categoryList as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($categoryFilter === $cat) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Stock</label>
                    <select name="stock">
                        <option value="">All</option>
                        <option value="in_stock" <?php echo ($stockFilter === 'in_stock') ? 'selected' : ''; ?>>In stock</option>
                        <option value="low" <?php echo ($stockFilter === 'low') ? 'selected' : ''; ?>>Low stock</option>
                        <option value="out" <?php echo ($stockFilter === 'out') ? 'selected' : ''; ?>>Out of stock</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="primary-btn">Apply</button>
                </div>
                <div class="filter-group">
                    <a href="inventory.php" class="small-btn secondary-btn" style="display:inline-block; text-align:center;">Reset</a>
                </div>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Supplier</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $product): ?>
                                <?php
                                    $stock = (int)$product['stock'];
                                    $stockClass = 'stock-ok';
                                    if ($stock <= 0) $stockClass = 'stock-zero';
                                    elseif ($stock <= 5) $stockClass = 'stock-low';
                                ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($product['image']) && file_exists(__DIR__ . '/uploads/' . $product['image'])): ?>
                                            <img class="product-thumb" src="uploads/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <div class="product-thumb" style="display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:10px;">N/A</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                    <td><?php echo htmlspecialchars($product['supplier_name'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($product['category'] ?: '-'); ?></td>
                                    <td>$<?php echo number_format((float)$product['price'], 2); ?></td>
                                    <td><span class="stock-badge <?php echo $stockClass; ?>"><?php echo $stock; ?></span></td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($canManageProducts): ?>
                                                <button type="button" class="small-btn edit" onclick="loadEditForm(<?php echo (int)$product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'], ENT_QUOTES)); ?>', '<?php echo addslashes(htmlspecialchars($product['sku'], ENT_QUOTES)); ?>', '<?php echo addslashes(htmlspecialchars($product['category'] ?: '', ENT_QUOTES)); ?>', '<?php echo number_format((float)$product['price'], 2, '.', ''); ?>', '<?php echo (int)($product['id']); ?>')">Edit</button>
                                            <?php endif; ?>
                                            <?php if ($canDeleteProducts): ?>
                                                <form method="POST" onsubmit="return confirm('Delete this product?');" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete_product">
                                                    <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                                    <button type="submit" class="small-btn delete">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="muted">No products match your search.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.45); display:flex; align-items:center; justify-content:center; z-index:50;">
        <div style="background:#fff; width:min(520px, 92vw); border-radius:18px; padding:24px; box-shadow:var(--shadow);">
            <h3 style="margin-top:0;">Edit Product</h3>
            <form method="POST" class="form-grid" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_product">
                <input type="hidden" name="product_id" id="edit_product_id">
                <input type="text" name="name" id="edit_name" required>
                <div class="input-row">
                    <input type="text" name="sku" id="edit_sku" required>
                    <input type="text" name="category" id="edit_category">
                </div>
                <div class="input-row">
                    <input type="number" name="price" step="0.01" min="0" id="edit_price">
                    <select name="supplier_id" id="edit_supplier_id">
                        <option value="">Select supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo (int)$supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="file" name="photo" accept="image/*">
                <div class="actions">
                    <button type="submit" class="primary-btn">Save Changes</button>
                    <button type="button" class="secondary-btn" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function loadEditForm(id, name, sku, category, price, supplierId) {
            document.getElementById('edit_product_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_sku').value = sku;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_supplier_id').value = supplierId;
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
    </script>
</body>
</html>
