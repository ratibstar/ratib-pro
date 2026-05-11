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
<li><a href="/api/infrastructure-marketplace/health.php" class="sidebar-item" target="_blank" rel="noopener" data-permission="control_system_settings"><i class="fas fa-cloud"></i><span>Infrastructure (health)</span></a></li>
-->
TXT;
