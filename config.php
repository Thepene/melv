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
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>
