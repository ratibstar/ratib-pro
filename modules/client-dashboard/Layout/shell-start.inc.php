<?php
/**
 * Opens client dashboard chrome (inside site content-wrapper).
 * Expects $ratebCpSectionKey (non-empty string).
 */
declare(strict_types=1);

$ratebCpSectionKey = isset($ratebCpSectionKey) ? (string) $ratebCpSectionKey : '';
$ratebCpPageSubheading = isset($ratebCpPageSubheading) ? (string) $ratebCpPageSubheading : '';

$ratebCpUserDisplay = '';
if (!empty($_SESSION['user_id']) && isset($conn) && $conn instanceof mysqli) {
    try {
        $uid = (int) $_SESSION['user_id'];
        $pk = function_exists('rateb_users_primary_key_column')
            ? rateb_users_primary_key_column($conn)
            : 'user_id';
        if ($pk !== 'user_id' && $pk !== 'id') {
            $pk = 'user_id';
        }
        $sql = 'SELECT COALESCE(full_name, name, username) AS dn FROM users WHERE `' . $pk . '` = ? LIMIT 1';
        $stmt = @$conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($u = $res->fetch_assoc())) {
                $ratebCpUserDisplay = trim((string) ($u['dn'] ?? ''));
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        $ratebCpUserDisplay = '';
    }
}
if ($ratebCpUserDisplay === '') {
    $ratebCpUserDisplay = trim((string) ($_SESSION['username'] ?? 'Account'));
}

$ratebCpNav = rateb_client_dashboard_nav_sections();
$ratebCpMainUrl = htmlspecialchars(rateb_nav_url('dashboard.php'), ENT_QUOTES, 'UTF-8');
$ratebCpLogoutUrl = htmlspecialchars(rateb_logout_url(), ENT_QUOTES, 'UTF-8');
$ratebCpSkipId = 'rateb-cp-main';
$ratebCpIsControlWrapper = function_exists('rateb_client_dashboard_is_control_wrapper_active')
    && rateb_client_dashboard_is_control_wrapper_active();

?>
<?php if ($ratebCpIsControlWrapper): ?>
<div class="rateb-cp-shell rateb-cp-shell--embedded" data-rateb-client-shell>
    <div class="rateb-cp-main" id="<?php echo htmlspecialchars($ratebCpSkipId, ENT_QUOTES, 'UTF-8'); ?>" tabindex="-1">
        <main class="rateb-cp-body">
<?php return; endif; ?>
<div class="rateb-cp-shell" data-rateb-client-shell>
    <a class="rateb-cp-skip" href="#<?php echo htmlspecialchars($ratebCpSkipId, ENT_QUOTES, 'UTF-8'); ?>">Skip to main content</a>
    <div class="rateb-cp-shell__backdrop" data-rateb-cp-backdrop aria-hidden="true"></div>
    <aside class="rateb-cp-sidebar" id="rateb-cp-sidebar" aria-label="Client platform navigation">
        <div class="rateb-cp-sidebar__brand">
            <span class="rateb-cp-sidebar__logo" aria-hidden="true">R</span>
            <div>
                <div class="rateb-cp-sidebar__title">RATEB</div>
                <div class="rateb-cp-sidebar__subtitle">Client Platform</div>
            </div>
        </div>
        <nav class="rateb-cp-sidebar__nav" role="navigation">
            <?php foreach ($ratebCpNav as $item): ?>
                <?php
                $active = ($item['key'] === $ratebCpSectionKey);
                $cls = 'rateb-cp-navlink' . ($active ? ' is-active' : '');
                $aria = $active ? ' aria-current="page"' : '';
                ?>
                <a class="<?php echo htmlspecialchars($cls, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo $item['href']; ?>"<?php echo $aria; ?>>
                    <i class="fa-solid <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="rateb-cp-sidebar__footer">
            <a class="rateb-cp-navlink rateb-cp-navlink--ghost" href="<?php echo $ratebCpMainUrl; ?>">
                <i class="fa-solid fa-arrow-left-long" aria-hidden="true"></i>
                <span>Main app</span>
            </a>
        </div>
    </aside>
    <div class="rateb-cp-main">
        <header class="rateb-cp-topbar" role="banner">
            <button type="button" class="rateb-cp-iconbtn" data-rateb-cp-open-sidebar aria-expanded="false" aria-controls="rateb-cp-sidebar" aria-label="Open sidebar">
                <i class="fa-solid fa-bars-staggered" aria-hidden="true"></i>
            </button>
            <div class="rateb-cp-topbar__title">
                <h1 class="rateb-cp-page-title"><?php echo isset($ratebCpPageHeading) ? htmlspecialchars((string) $ratebCpPageHeading, ENT_QUOTES, 'UTF-8') : 'Dashboard'; ?></h1>
                <?php if (!empty($ratebCpPageSubheading)): ?>
                    <p class="rateb-cp-page-sub"><?php echo htmlspecialchars((string) $ratebCpPageSubheading, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
            </div>
            <div class="rateb-cp-topbar__actions">
                <a class="rateb-cp-pillbtn" href="<?php echo htmlspecialchars(rateb_nav_url('client/notifications-center.php'), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa-regular fa-bell" aria-hidden="true"></i>
                    <span>Alerts</span>
                </a>
                <a class="rateb-cp-pillbtn" href="<?php echo htmlspecialchars(rateb_nav_url('help-center.php'), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa-regular fa-circle-question" aria-hidden="true"></i>
                    <span>Help</span>
                </a>
                <div class="rateb-cp-userchip" tabindex="0" role="group" aria-label="Signed in">
                    <span class="rateb-cp-userchip__dot" aria-hidden="true"></span>
                    <span class="rateb-cp-userchip__name"><?php echo htmlspecialchars($ratebCpUserDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <a class="rateb-cp-ghost-link" href="<?php echo $ratebCpLogoutUrl; ?>"><span class="visually-hidden">Log out</span><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i></a>
            </div>
        </header>
        <main class="rateb-cp-body" id="<?php echo htmlspecialchars($ratebCpSkipId, ENT_QUOTES, 'UTF-8'); ?>" tabindex="-1">
