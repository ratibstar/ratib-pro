<?php
declare(strict_types=1);

/**
 * Route module manifest — Phase AA.1 identity order.
 * Must match public/index.php legacy require order exactly:
 * web → marketing → cms → company → api → pos → pos-v2 (optional).
 *
 * @return list<array{id:string,file:string,optional?:bool}>
 */
return [
    ['id' => 'web', 'file' => 'routes/web.php'],
    ['id' => 'marketing', 'file' => 'routes/marketing.php'],
    ['id' => 'cms', 'file' => 'routes/cms.php'],
    ['id' => 'company', 'file' => 'routes/company.php'],
    ['id' => 'api', 'file' => 'routes/api.php'],
    ['id' => 'pos', 'file' => 'modules/pos/routes/pos.php'],
    ['id' => 'pos_v2', 'file' => 'modules/pos/routes/pos-v2.php', 'optional' => true],
];
