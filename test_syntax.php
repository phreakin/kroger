<?php
$files = ['public/index.php', 'public/assets/js/app.js'];

foreach ($files as $file) {
    $output = [];
    $return = 0;
    exec("php -l \"" . __DIR__ . "/$file\" 2>&1", $output, $return);
    echo $file . ": " . ($return === 0 ? "✓ Valid" : "✗ Error") . "\n";
    if ($return !== 0) {
        echo implode("\n", $output) . "\n";
    }
}
echo "Validation complete.\n";
?>
