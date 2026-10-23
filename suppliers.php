<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userRole = $_SESSION['role'] ?? 'staff';
if (!in_array($userRole, ['admin', 'manager'], true)) {
    header('Location: dashboard.php');
    exit();
}

$notice = $_GET['notice'] ?? '';
$error = $_GET['error'] ?? '';
$editId = $_GET['edit'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $suppliers = loadJsonStore('suppliers', []);

    if ($action === 'add_supplier') {
        $name = trim($_POST['name'] ?? '');
        $contactName = trim($_POST['contact_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($name === '') {
            header('Location: suppliers.php?error=supplier_name_required');
            exit();
        }

        $suppliers[] = [
            'id' => nextJsonId($suppliers),
            'name' => $name,
            'contact_name' => $contactName,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        saveJsonStore('suppliers', $suppliers);
        header('Location: suppliers.php?notice=supplier_added');
        exit();
    }

    if ($action === 'update_supplier') {
        $id = (int) ($_POST['supplier_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $contactName = trim($_POST['contact_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($id <= 0 || $name === '') {
            header('Location: suppliers.php?error=invalid_supplier');
            exit();
        }

        foreach ($suppliers as $index => $supplier) {
            if ((int) ($supplier['id'] ?? 0) === $id) {
                $suppliers[$index] = [
                    'id' => $id,
                    'name' => $name,
                    'contact_name' => $contactName,
                    'phone' => $phone,
                    'email' => $email,
                    'address' => $address,
                    'created_at' => $supplier['created_at'] ?? date('Y-m-d H:i:s'),
                ];
                break;
            }
        }

        saveJsonStore('suppliers', $suppliers);
        header('Location: suppliers.php?notice=supplier_updated');
        exit();
    }

    if ($action === 'delete_supplier') {
        $id = (int) ($_POST['supplier_id'] ?? 0);
        if ($id > 0) {
            $updatedSuppliers = [];
            foreach ($suppliers as $supplier) {
                if ((int) ($supplier['id'] ?? 0) !== $id) {
                    $updatedSuppliers[] = $supplier;
                }
            }
            saveJsonStore('suppliers', $updatedSuppliers);
            header('Location: suppliers.php?notice=supplier_deleted');
            exit();
        }
    }
}

if ($editId !== null) {
    $suppliers = loadJsonStore('suppliers', []);
    $supplier = null;
    foreach ($suppliers as $row) {
        if ((int) ($row['id'] ?? 0) === (int) $editId) {
            $supplier = $row;
            break;
        }
    }
} else {
    $supplier = null;
}

$suppliers = loadJsonStore('suppliers', []);
usort($suppliers, static function ($a, $b) {
    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #eef4ff 0%, #f8fafc 100%);
            color: #0f172a;
        }
        .page { max-width: 1200px; margin: 32px auto; padding: 0 24px 60px; }
        .topbar { background: rgba(255,255,255,0.9); border-bottom: 1px solid #e5e7eb; padding: 18px 24px; }
        .topbar-inner { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .nav a { color: #475569; text-decoration: none; font-weight: 700; margin-left: 14px; }
        .card { background: white; border-radius: 18px; box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08); padding: 22px; border: 1px solid #e5e7eb; margin-top: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1.3fr; gap: 20px; }
        .form-grid { display: grid; gap: 12px; }
        input, button, textarea { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #d1d5db; font-size: 14px; }
        button { border: none; background: #2563eb; color: white; font-weight: 700; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { color: #64748b; font-size: 12px; text-transform: uppercase; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .mini-btn { padding: 8px 10px; border-radius: 10px; border: none; font-weight: 700; cursor: pointer; }
        .edit { background: #dbeafe; color: #1d4ed8; }
        .delete { background: #fee2e2; color: #991b1b; }
        .notice { border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; font-weight: 600; }
        .notice.success { background: #dcfce7; color: #166534; }
        .notice.error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <div><strong>Inventory Pro</strong></div>
            <div class="nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="inventory.php">Inventory</a>
                <?php if (($_SESSION['role'] ?? 'staff') === 'admin'): ?>
                    <a href="admin_dashboard.php">Admin Monitoring</a>
                <?php endif; ?>
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

        <div class="grid">
            <div class="card">
                <h2><?php echo $supplier ? 'Edit Supplier' : 'Add Supplier'; ?></h2>
                <form method="POST" class="form-grid">
                    <input type="hidden" name="action" value="<?php echo $supplier ? 'update_supplier' : 'add_supplier'; ?>">
                    <?php if ($supplier): ?>
                        <input type="hidden" name="supplier_id" value="<?php echo (int) $supplier['id']; ?>">
                    <?php endif; ?>
                    <input type="text" name="name" placeholder="Supplier name" value="<?php echo htmlspecialchars($supplier['name'] ?? ''); ?>" required>
                    <input type="text" name="contact_name" placeholder="Contact name" value="<?php echo htmlspecialchars($supplier['contact_name'] ?? ''); ?>">
                    <input type="text" name="phone" placeholder="Phone" value="<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>">
                    <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>">
                    <textarea name="address" rows="4" placeholder="Address"><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></textarea>
                    <button type="submit"><?php echo $supplier ? 'Save Supplier' : 'Add Supplier'; ?></button>
                </form>
            </div>

            <div class="card">
                <h2>Supplier List</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($suppliers)): ?>
                            <?php foreach ($suppliers as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['contact_name'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['email'] ?: '-'); ?></td>
                                    <td>
                                        <div class="actions">
                                            <a class="mini-btn edit" href="suppliers.php?edit=<?php echo (int) $row['id']; ?>">Edit</a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete supplier?');">
                                                <input type="hidden" name="action" value="delete_supplier">
                                                <input type="hidden" name="supplier_id" value="<?php echo (int) $row['id']; ?>">
                                                <button type="submit" class="mini-btn delete">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No suppliers yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
