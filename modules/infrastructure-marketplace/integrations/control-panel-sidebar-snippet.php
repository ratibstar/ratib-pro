<?php
declare(strict_types=1);

/**
 * Optional menu registration (manual merge).
 *
 * Paste inside control-panel/includes/control/sidebar.php inside the <nav> menu when enabling the module.
 * Use pageUrl(...) or equivalent for correct base paths in your deployment.
 */

return <<<'TXT'
<!--
<li><a href="/modules/infrastructure-marketplace/Views/admin/control.php" class="sidebar-item" data-permission="control_system_settings"><i class="fas fa-sliders-h"></i><span>Infrastructure Control</span></a></li>
<li><a href="/modules/infrastructure-marketplace/Views/admin/dashboard.php" class="sidebar-item" data-permission="control_system_settings"><i class="fas fa-chart-line"></i><span>Infrastructure Dashboard</span></a></li>
<li><a href="/modules/infrastructure-marketplace/Views/admin/providers.php" class="sidebar-item" data-permission="control_system_settings"><i class="fas fa-plug"></i><span>Infrastructure Providers</span></a></li>
<li><a href="/api/infrastructure-marketplace/health.php" class="sidebar-item" target="_blank" rel="noopener" data-permission="control_system_settings"><i class="fas fa-heartbeat"></i><span>Infrastructure Health API</span></a></li>
-->
TXT;
