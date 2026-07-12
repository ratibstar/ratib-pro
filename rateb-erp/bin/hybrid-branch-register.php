<?php
declare(strict_types=1);

/** Phase D — Branch registration payload generator (offline). */
$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\BranchRegistration;

$reg = new BranchRegistration();
if (in_array('--approve', $argv, true)) {
    echo json_encode($reg->markApproved('local-approve'), JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}
$out = $reg->generateRegistrationPayload();
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(!empty($out['ok']) ? 0 : 1);
