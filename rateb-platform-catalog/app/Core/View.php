<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Core;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = 'main'): void
    {
        $viewsPath = defined('RATEB_CATALOG_VIEWS_PATH')
            ? (string) RATEB_CATALOG_VIEWS_PATH
            : (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/views' : __DIR__ . '/../../views');

        $viewFile = $viewsPath . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewFile)) {
            http_response_code(404);
            echo 'View not found';

            return;
        }

        extract($data, EXTR_SKIP);

        if ($layout !== null) {
            $layoutFile = $viewsPath . '/layouts/' . $layout . '.php';
            if (is_file($layoutFile)) {
                $contentView = $viewFile;
                require $layoutFile;

                return;
            }
        }

        require $viewFile;
    }
}
