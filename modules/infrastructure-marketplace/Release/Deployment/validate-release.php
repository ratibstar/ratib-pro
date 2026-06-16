<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use RATEB\InfrastructureMarketplace\Cli\InfrastructureLaunchVerifier;

exit(InfrastructureLaunchVerifier::main(array_slice($argv, 1)));

