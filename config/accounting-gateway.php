<?php
declare(strict_types=1);

return [
    /*
    | Optional unified accounting gateway layer.
    | When false, postAccountingEvent() is a no-op and legacy writes are unchanged.
    */
    'enabled' => (bool) (getenv('ACCOUNTING_GATEWAY_ENABLED') ?: false),
];
