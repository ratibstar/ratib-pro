<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/create_admin.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/create_admin.php`.
 */
/**
 * One-time: create default control panel admin. Run once then DELETE this file.
 * URL: https://out.ratib.sa/control-panel/create_admin.php
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$done = false;
$error = '';

try {
    $envFile = __DIR__ . '/config/env.php';
    if (!is_file($envFile)) {
        throw new Exception('Config not found: config/env.php');
    }
    require_once $envFile;

    if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_NAME')) {
        throw new Exception('DB constants not set. Check config/env.php');
    }

    $dbName = defined('CONTROL_PANEL_DB_NAME') ? CONTROL_PANEL_DB_NAME : DB_NAME;
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS ?? '', $dbName, (int)(DB_PORT ?? 3306));
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error . '. In cPanel go to MySQL® Databases and add user "' . DB_USER . '" to database "' . $dbName . '" with ALL PRIVILEGES.');
    }
    $conn->set_charset("utf8mb4");

    $stmt = $conn->prepare("SELECT id FROM control_admins WHERE username = 'admin' LIMIT 1");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $done = true;
        $msg = 'Admin user already exists. Log in with username: admin';
    } else {
        $hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $fullName = 'Admin';
        $active = 1;
        $ins = $conn->prepare("INSERT INTO control_admins (username, password, full_name, is_active) VALUES ('admin', ?, ?, ?)");
        if (!$ins) {
            throw new Exception('Insert prepare failed: ' . $conn->error);
        }
        $ins->bind_param("ssi", $hash, $fullName, $active);
        $ins->execute();
        $done = true;
        $msg = 'Admin user created. You can now log in with username: <strong>admin</strong>, password: <strong>password</strong> — change it after first login.';
    }
    $conn->close();
} catch (Throwable $e) {
    $error = $e->getMessage();
    if (strpos($error, 'Access denied') !== false) {
        $error .= ' — Fix: In cPanel → MySQL® Databases, add user "' . (defined('DB_USER') ? DB_USER : '') . '" to database "' . (defined('CONTROL_PANEL_DB_NAME') ? CONTROL_PANEL_DB_NAME : 'outratib_control_panel_db') . '" with ALL PRIVILEGES. Or create a new user (e.g. outratib_cp), add it to that database, then set CONTROL_DB_USER and CONTROL_DB_PASS in control-panel/config/env.php on the server.';
    }
}

ob_end_clean();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light p-5">
    <div class="container create-admin-container">
        <div class="card shadow">
            <div class="card-body p-4">
                <h5 class="card-title mb-3">Control Panel – Create Admin</h5>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php elseif ($done): ?>
                    <div class="alert alert-success"><?php echo $msg; ?></div>
                    <?php $loginUrl = (defined('BASE_URL') && BASE_URL !== '') ? rtrim(BASE_URL,'/') . '/pages/login.php' : 'pages/login.php'; ?>
                    <p class="mb-0"><a href="<?php echo htmlspecialchars($loginUrl); ?>">Go to login</a></p>
                <?php endif; ?>
                <p class="text-muted small mt-3 mb-0"><strong>Delete this file (create_admin.php)</strong> from the server after use.</p>
            </div>
        </div>
    </div>
</body>
</html>
