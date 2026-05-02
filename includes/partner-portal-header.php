<?php
/**
 * Minimal shell for partner portal (no main app nav).
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/config.php';
}
$ppTitle = isset($pageTitle) ? (string) $pageTitle : 'Partner portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($ppTitle, ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Ratib', ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <?php
    if (!empty($pageCss) && is_array($pageCss)) {
        foreach ($pageCss as $css) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars((string) $css, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
    }
    ?>
</head>
<body class="partner-portal-body">
<div class="partner-portal-shell">
