<?php

declare(strict_types=1);

namespace App\Services\Core;

/**
 * عرض القوالب: قالب المحتوى داخل التخطيط المشترك.
 */
final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $content = self::capture($template, $data);

        if ($layout === null) {
            echo $content;

            return;
        }

        echo self::capture($layout, array_merge($data, ['content' => $content]));
    }

    /** @param array<string,mixed> $data */
    public static function capture(string $template, array $data = []): string
    {
        $file = BASE_PATH . '/app/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('القالب غير موجود: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $data */
    public static function partial(string $template, array $data = []): void
    {
        echo self::capture($template, $data);
    }
}
