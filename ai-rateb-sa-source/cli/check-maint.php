<?php
require dirname(__DIR__) . "/config/bootstrap.php";
echo "maint=" . (platform_setting_bool($pdo, "maintenance_mode", false) ? "1" : "0") . PHP_EOL;
echo "public=" . (platform_setting_bool($pdo, "public_site_enabled", true) ? "1" : "0") . PHP_EOL;
