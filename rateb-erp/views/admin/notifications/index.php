<?php Rateb\App\Core\View::partial('crud-index', ['title' => $title, 'items' => $items ?? [], 'csrf' => $csrf, 'routePrefix' => 'admin/notifications']); ?>
