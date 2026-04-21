<?php
declare(strict_types=1);

namespace App\View;

final class View
{
    public function render(string $template, array $data = []): string
    {
        $templatePath = dirname(__DIR__, 3) . '/resources/views/' . $template . '.blade.php';
        if (!is_file($templatePath)) {
            throw new \RuntimeException('View not found: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $templatePath;
        return (string) ob_get_clean();
    }
}
