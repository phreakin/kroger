<?php
declare(strict_types=1);

$config = require __DIR__ . '/../bootstrap/app.php';
$legacy = $config['legacy'];
$db = new PDO($legacy['db']['dsn'], $legacy['db']['user'], $legacy['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

foreach (glob(__DIR__ . '/../database/migrations/*.sql') as $migration) {
    $sql = file_get_contents($migration);
    if ($sql !== false) {
        $db->exec($sql);
        echo 'Applied migration: ' . basename($migration) . PHP_EOL;
    }
}
