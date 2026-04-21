<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'App\\' => __DIR__ . '/../src/App/',
        'Infrastructure\\' => __DIR__ . '/../src/Infrastructure/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

$envPath = dirname(__DIR__) . '/.env';
if (is_file($envPath)) {
    $env = parse_ini_file($envPath, false, INI_SCANNER_TYPED) ?: [];
    foreach ($env as $key => $value) {
        putenv(sprintf('%s=%s', $key, (string) $value));
    }
}

$config = [
    'app' => require dirname(__DIR__) . '/config/app.php',
    'legacy' => require dirname(__DIR__) . '/config/config.php',
];

return $config;
