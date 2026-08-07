<?php
declare(strict_types=1);

/**
 * Route module manifest — Phase AA.3 dashboard-minimal bootstrap.
 * loadAll() loads every module below (AA.1-equivalent full table via split files).
 *
 * @return list<array{id:string,file:string,optional?:bool}>
 */
return [
    ['id' => 'auth', 'file' => 'routes/modules/auth.php'],
    ['id' => 'dashboard', 'file' => 'routes/modules/dashboard.php'],
    ['id' => 'subscription', 'file' => 'routes/modules/subscription.php'],
    ['id' => 'platform', 'file' => 'routes/modules/platform.php'],
    ['id' => 'marketing', 'file' => 'routes/modules/marketing.php'],
    ['id' => 'cms', 'file' => 'routes/modules/cms.php'],
    ['id' => 'ops', 'file' => 'routes/modules/ops.php'],
    ['id' => 'api', 'file' => 'routes/modules/api.php'],
    ['id' => 'payment', 'file' => 'routes/modules/payment.php'],
    ['id' => 'pos', 'file' => 'routes/modules/pos.php'],
    ['id' => 'pos_v2', 'file' => 'modules/pos/routes/pos-v2.php', 'optional' => true],
    ['id' => 'guest_menu', 'file' => 'routes/modules/guest-menu.php', 'optional' => true],
    ['id' => 'logistics', 'file' => 'routes/modules/logistics.php', 'optional' => true],
    ['id' => 'marketplace', 'file' => 'routes/modules/marketplace.php', 'optional' => true],
];
