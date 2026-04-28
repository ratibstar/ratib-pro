<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/login.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/login.php`.
 */
/**
 * Control Panel - Login (standalone)
 */
require_once __DIR__ . '/../includes/config.php';
$error = '';
$success_message = isset($_GET['message']) && $_GET['message'] === 'logged_out' ? 'You have been successfully logged out.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = $GLOBALS['control_conn'] ?? $GLOBALS['conn'] ?? null;
    if ($db === null) {
        $error = 'Control panel database unavailable.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $user = null;
        $controlUserId = 0;
        $adminsHasCountryId = false;
        $chkAdmins = $db->query("SHOW TABLES LIKE 'control_admins'");
        if ($chkAdmins && $chkAdmins->num_rows > 0) {
            $colCo = $db->query("SHOW COLUMNS FROM control_admins LIKE 'country_id'");
            if ($colCo && $colCo->num_rows > 0) {
                $adminsHasCountryId = true;
            }
        }
        if ($chkAdmins && $chkAdmins->num_rows > 0) {
            $sqlAdm = $adminsHasCountryId
                ? 'SELECT id, username, password, full_name, is_active, country_id FROM control_admins WHERE username = ? LIMIT 1'
                : 'SELECT id, username, password, full_name, is_active FROM control_admins WHERE username = ? LIMIT 1';
            $stmt = $db->prepare($sqlAdm);
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows === 1) {
                    $user = $res->fetch_assoc();
                    $controlUserId = (int)$user['id'];
                    $stmt->close();
                }
            }
        }
        if ($user) {
            $statusOk = isset($user['is_active']) ? !empty($user['is_active']) : true;
            if (!$statusOk) {
                $error = 'Account is inactive. Please contact administrator.';
            } elseif (!password_verify($password, $user['password'])) {
                $error = 'Invalid password.';
            } else {
                $_SESSION['control_logged_in'] = true;
                $_SESSION['control_user_id'] = $controlUserId;
                $_SESSION['control_username'] = $user['username'];
                $_SESSION['control_full_name'] = trim($user['full_name'] ?? $user['username'] ?? '');
                $_SESSION['control_agency_id'] = null;
                $_SESSION['control_agency_name'] = null;
                $_SESSION['control_country_id'] = null;
                $_SESSION['control_country_name'] = null;
                if ($adminsHasCountryId && !empty($user['country_id'])) {
                    $cid = (int) $user['country_id'];
                    if ($cid > 0) {
                        $_SESSION['control_country_id'] = $cid;
                        try {
                            $cnStmt = $db->prepare('SELECT name FROM control_countries WHERE id = ? AND is_active = 1 LIMIT 1');
                            if ($cnStmt) {
                                $cnStmt->bind_param('i', $cid);
                                $cnStmt->execute();
                                $cnRes = $cnStmt->get_result();
                                if ($cnRes && ($cnRow = $cnRes->fetch_assoc())) {
                                    $_SESSION['control_country_name'] = (string) ($cnRow['name'] ?? '');
                                }
                                $cnStmt->close();
                            }
                        } catch (Throwable $e) { /* ignore */ }
                    }
                }
                $userPerms = ['*'];
                try {
                    $chk = $db->query("SHOW TABLES LIKE 'control_admin_permissions'");
                    if ($chk && $chk->num_rows > 0) {
                        $pStmt = $db->prepare("SELECT permissions FROM control_admin_permissions WHERE user_id = ? LIMIT 1");
                        if ($pStmt) {
                            $pStmt->bind_param("i", $controlUserId);
                            $pStmt->execute();
                            $pRes = $pStmt->get_result();
                            $pRow = $pRes ? $pRes->fetch_assoc() : null;
                            if ($pRow && isset($pRow['permissions']) && $pRow['permissions'] !== null) {
                                $decoded = json_decode($pRow['permissions'], true);
                                $userPerms = is_array($decoded) ? $decoded : ['*'];
                                if (count($userPerms) === 0) $userPerms = [];
                            }
                            $pStmt->close();
                        }
                    }
                    if (strtolower(trim($user['username'] ?? '')) === 'admin' && (empty($userPerms) || !in_array('*', $userPerms, true))) {
                        $userPerms = ['*'];
                    }
                } catch (Throwable $e) { /* use default */ }
                $_SESSION['control_permissions'] = $userPerms;
                header('Location: ' . pageUrl('control/dashboard.php'));
                exit;
            }
        }
        if (empty($error)) {
            $error = 'Invalid username or password.';
        }
    }
}

if (!empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('control/dashboard.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Control Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/login.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/control/login-control.css'); ?>?v=<?php echo time(); ?>">
</head>
<body class="cp-login-page dark-mode">
    <div class="hyperdimensional-container">
        <div class="animated-background cp-login-bg" aria-hidden="true">
            <span class="cp-bg-word">WELCOME TO RATIB</span>
            <span class="cp-bg-word">RATIB PRO</span>
            <span class="cp-bg-word">E-INVOICING</span>
            <span class="cp-bg-word">ACCOUNTING</span>
            <span class="cp-bg-word">HR SYSTEM</span>
            <span class="cp-bg-word">CONTROL PANEL</span>
        </div>
        <div class="portal-content active">
            <h2>Control Panel Login</h2>
            <?php if ($error): ?><div class="error-message"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success_message): ?><div class="success-message"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
            <form method="post" action="" class="text-center mt-4">
                <div class="mb-3">
                    <input type="text" name="username" placeholder="Username" required autocomplete="username" class="form-control">
                </div>
                <div class="mb-4">
                    <input type="password" name="password" placeholder="Password" required autocomplete="current-password" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
