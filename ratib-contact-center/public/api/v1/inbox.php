<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/bootstrap.php';

use Ratib\ContactCenter\App\Controllers\Api\InboxApiController;

(new InboxApiController())->handle();
