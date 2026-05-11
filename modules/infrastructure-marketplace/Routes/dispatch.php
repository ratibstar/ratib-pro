<?php
declare(strict_types=1);

/**
 * Route map loader for manual inclusion — no rewrite changes required today.
 */

use Ratib\InfrastructureMarketplace\Config\ModuleRegistry;

return ModuleRegistry::httpRoutes();
