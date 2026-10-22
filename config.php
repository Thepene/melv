<?php
$dbDir = __DIR__ . '/database';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}

function jsonStorePath(string $name): string
{
    return __DIR__ . '/database/' . $name . '.json';
}

function loadJsonStore(string $name, array $default = []): array
{
    $file = jsonStorePath($name);

    if (!file_exists($file)) {
        saveJsonStore($name, $default);
        return $default;
    }

    $contents = @file_get_contents($file);
    if ($contents === false || trim($contents) === '') {
        saveJsonStore($name, $default);
        return $default;
    }

    $data = json_decode($contents, true);
    return is_array($data) ? $data : $default;
}

function saveJsonStore(string $name, array $data): bool
{
    $file = jsonStorePath($name);
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return false;
    }

    return file_put_contents($file, $encoded . PHP_EOL) !== false;
}

function nextJsonId(array $items): int
{
    $maxId = 0;
    foreach ($items as $item) {
        $id = isset($item['id']) ? (int) $item['id'] : 0;
        if ($id > $maxId) {
            $maxId = $id;
        }
    }

    return $maxId + 1;
}

function inventoryStockForProduct(int $productId, array $transactions): int
{
    $stock = 0;
    foreach ($transactions as $transaction) {
        if ((int) ($transaction['product_id'] ?? 0) !== $productId) {
            continue;
        }

        $quantity = (int) ($transaction['quantity'] ?? 0);
        $stock += (($transaction['type'] ?? 'IN') === 'IN') ? $quantity : -$quantity;
    }

    return $stock;
}

function ensureJsonDatabase(): void
{
    $users = loadJsonStore('users', []);
    if (empty($users)) {
        $defaultPassword = password_hash('Admin123!', PASSWORD_DEFAULT);
        $users[] = [
            'id' => 1,
            'full_name' => 'System Administrator',
            'username' => 'admin',
            'email' => 'admin@inventory.local',
            'password' => $defaultPassword,
            'role' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        saveJsonStore('users', $users);
    }

    loadJsonStore('suppliers', []);
    loadJsonStore('products', []);
    loadJsonStore('stock_transactions', []);
}

ensureJsonDatabase();
?>
