<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/session.php';
require __DIR__ . '/auth.php';

admin_staff_logout_session();
header('Location: /admin');
exit;
