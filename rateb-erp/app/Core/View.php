<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'main'): void
    {
        $viewFile = RATEB_VIEWS_PATH . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
            return;
        }

        extract($data, EXTR_SKIP);
        $content = static function () use ($viewFile, $data): string {
            extract($data, EXTR_SKIP);
            ob_start();
            try {
                include $viewFile;
                return (string) ob_get_clean();
            } catch (\Throwable $e) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                throw $e;
            }
        };

        if ($layout === null) {
            echo $content();
            return;
        }

        $layoutFile = RATEB_VIEWS_PATH . '/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            echo $content();
            return;
        }

        $pageContent = $content();
        include $layoutFile;
    }

    public static function partial(string $partial, array $data = []): void
    {
        $rel = str_replace('.', '/', $partial) . '.php';
        $file = RATEB_VIEWS_PATH . '/components/' . $rel;
        if (!is_file($file)) {
            $file = RATEB_VIEWS_PATH . '/partials/' . $rel;
        }
        if (!is_file($file)) {
            return;
        }
        extract($data, EXTR_SKIP);
        include $file;
    }

    public static function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /** @param mixed $value */
    public static function formatDate($value, string $kind = 'auto'): string
    {
        $raw = (string) $value;
        if ($raw === '—') {
            return self::escape('—');
        }
        if (function_exists('rateb_format_date_value')) {
            return self::escape(rateb_format_date_value($raw, $kind));
        }

        return self::escape($raw);
    }
}
