<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap-api.php';

use Ratib\ContactCenter\App\Controllers\Api\CrmApiController;

(new CrmApiController())->handle();
