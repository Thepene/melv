<?php
$dbDir = __DIR__ . '/database';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}

$dbPath = $dbDir . '/inventory.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON;');

    $schema = file_get_contents(__DIR__ . '/db.sql');
    if ($schema !== false && trim($schema) !== '') {
        foreach (explode(';', $schema) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement . ';');
            }
        }
    }

    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount === 0) {
        $defaultPassword = password_hash('Admin123!', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (full_name, username, email, password, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            'System Administrator',
            'admin',
            'admin@inventory.local',
            $defaultPassword,
            'admin'
        ]);
    }
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>
