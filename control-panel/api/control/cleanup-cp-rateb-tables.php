<?php
/**
 * Remove legacy rateb_* tables from admin_control_panel_db (ERP belongs on admin_rateb-erp).
 */
declare(strict_types=1);

if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}

try {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/control/check-all-databases-lib.php';

    if (empty($_SESSION['control_logged_in'])) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Login to Control Panel first.\n");
    }

    if (empty($_SESSION['cp_rateb_cleanup_csrf'])) {
        $_SESSION['cp_rateb_cleanup_csrf'] = bin2hex(random_bytes(16));
    }
    $csrf = (string) $_SESSION['cp_rateb_cleanup_csrf'];

    $execute = $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['confirm'])
        && (string) $_POST['confirm'] === 'DROP_CP_RATEB';

    if ($execute) {
        $token = (string) ($_POST['_csrf'] ?? '');
        if (!hash_equals($csrf, $token)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            exit("Invalid CSRF token. Refresh and try again.\n");
        }
    }

    [$report, $dropped] = control_cleanup_cp_rateb_pollution($execute);

    if ($execute) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $report;
        echo "\nNext: https://rateb.sa/control-panel/api/control/check-all-databases.php?control=1\n";
        exit;
    }

    $tables = control_cleanup_cp_rateb_list_tables();
    $db = control_check_all_databases_env('CONTROL_PANEL_DB_NAME', 'admin_control_panel_db');
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Cleanup CP rateb_* tables</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; }
        pre { background: #f4f4f4; padding: 1rem; overflow: auto; font-size: 13px; }
        .warn { background: #fff3cd; border: 1px solid #ffc107; padding: 1rem; border-radius: 6px; }
        button { background: #dc3545; color: #fff; border: 0; padding: 0.75rem 1.25rem; font-size: 1rem; border-radius: 6px; cursor: pointer; }
        button:hover { background: #bb2d3b; }
        a { color: #0d6efd; }
    </style>
</head>
<body>
    <h1>Control Panel DB cleanup</h1>
    <p>Database: <code><?php echo htmlspecialchars($db, ENT_QUOTES, 'UTF-8'); ?></code></p>
    <div class="warn">
        <strong>Backup first!</strong> This removes <strong><?php echo count($tables); ?></strong> legacy
        <code>rateb_*</code> tables from the control panel database only.
        ERP data on <code>admin_rateb-erp</code> is not touched.
    </div>
    <h2>Tables to drop</h2>
    <pre><?php echo htmlspecialchars(implode("\n", $tables) ?: '(none)', ENT_QUOTES, 'UTF-8'); ?></pre>
    <?php if ($tables !== []) { ?>
    <form method="post" onsubmit="return confirm('Backup done? Drop <?php echo count($tables); ?> rateb_* tables from <?php echo htmlspecialchars($db, ENT_QUOTES, 'UTF-8'); ?>?');">
        <input type="hidden" name="confirm" value="DROP_CP_RATEB">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <p><button type="submit">Drop <?php echo count($tables); ?> rateb_* tables</button></p>
    </form>
    <?php } else { ?>
    <p><strong>Nothing to clean — control panel DB is already OK.</strong></p>
    <?php } ?>
    <p><a href="check-all-databases.php?control=1">Re-run all databases check</a></p>
</body>
</html>
    <?php
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'FATAL: ', $e->getMessage(), "\n";
}
