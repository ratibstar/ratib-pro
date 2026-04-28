<?php
/**
 * EN: Handles application behavior in `control.php`.
 * AR: يدير سلوك جزء من التطبيق في `control.php`.
 */
/**
 * Control Panel entry point.
 * All control panel files are in the control-panel/ folder. This redirects there.
 */
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$url = ($base === '' ? '' : $base) . '/control-panel/';
header('Location: ' . $url);
exit;
