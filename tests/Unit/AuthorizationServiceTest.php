<?php
declare(strict_types=1);

use App\Services\AuthorizationService;

return [
    'AuthorizationService rejects empty permission' => static function (): void {
        $pdo = t_db();
        $service = new AuthorizationService($pdo);
        t_assert_same(false, $service->can(['id' => 1], ''), 'Empty permission must be denied.');
    },
    'AuthorizationService rejects invalid user id' => static function (): void {
        $pdo = t_db();
        $service = new AuthorizationService($pdo);
        t_assert_same(false, $service->can(['id' => 0], 'workers.create'), 'Invalid user id must be denied.');
    },
];
