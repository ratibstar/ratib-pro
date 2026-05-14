<?php
/**
 * Opens client dashboard chrome (inside site content-wrapper).
 * Expects $ratibCpSectionKey (non-empty string).
 */
declare(strict_types=1);

$ratibCpSectionKey = isset($ratibCpSectionKey) ? (string) $ratibCpSectionKey : '';
$ratibCpPageSubheading = isset($ratibCpPageSubheading) ? (string) $ratibCpPageSubheading : '';

$ratibCpUserDisplay = '';
if (!empty($_SESSION['user_id']) && isset($conn) && $conn instanceof mysqli) {
    try {
        $uid = (int) $_SESSION['user_id'];
        $pk = function_exists('ratib_users_primary_key_column')
            ? ratib_users_primary_key_column($conn)
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
                $ratibCpUserDisplay = trim((string) ($u['dn'] ?? ''));
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        $ratibCpUserDisplay = '';
    }
}
if ($ratibCpUserDisplay === '') {
    $ratibCpUserDisplay = trim((string) ($_SESSION['username'] ?? 'Account'));
}

$ratibCpNav = ratib_client_dashboard_nav_sections();
$ratibCpMainUrl = htmlspecialchars(ratib_nav_url('dashboard.php'), ENT_QUOTES, 'UTF-8');
$ratibCpLogoutUrl = htmlspecialchars(ratib_logout_url(), ENT_QUOTES, 'UTF-8');
$ratibCpSkipId = 'ratib-cp-main';
$ratibCpIsControlWrapper = function_exists('ratib_client_dashboard_is_control_wrapper_active')
    && ratib_client_dashboard_is_control_wrapper_active();

?>
<?php if ($ratibCpIsControlWrapper): ?>
<div class="ratib-cp-shell ratib-cp-shell--embedded" data-ratib-client-shell>
    <div class="ratib-cp-main" id="<?php echo htmlspecialchars($ratibCpSkipId, ENT_QUOTES, 'UTF-8'); ?>" tabindex="-1">
        <main class="ratib-cp-body">
<?php return; endif; ?>
<div class="ratib-cp-shell" data-ratib-client-shell>
    <a class="ratib-cp-skip" href="#<?php echo htmlspecialchars($ratibCpSkipId, ENT_QUOTES, 'UTF-8'); ?>">Skip to main content</a>
    <div class="ratib-cp-shell__backdrop" data-ratib-cp-backdrop aria-hidden="true"></div>
    <aside class="ratib-cp-sidebar" id="ratib-cp-sidebar" aria-label="Client platform navigation">
        <div class="ratib-cp-sidebar__brand">
            <span class="ratib-cp-sidebar__logo" aria-hidden="true">R</span>
            <div>
                <div class="ratib-cp-sidebar__title">RATIB</div>
                <div class="ratib-cp-sidebar__subtitle">Client Platform</div>
            </div>
        </div>
        <nav class="ratib-cp-sidebar__nav" role="navigation">
            <?php foreach ($ratibCpNav as $item): ?>
                <?php
                $active = ($item['key'] === $ratibCpSectionKey);
                $cls = 'ratib-cp-navlink' . ($active ? ' is-active' : '');
                $aria = $active ? ' aria-current="page"' : '';
                ?>
                <a class="<?php echo htmlspecialchars($cls, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo $item['href']; ?>"<?php echo $aria; ?>>
                    <i class="fa-solid <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="ratib-cp-sidebar__footer">
            <a class="ratib-cp-navlink ratib-cp-navlink--ghost" href="<?php echo $ratibCpMainUrl; ?>">
                <i class="fa-solid fa-arrow-left-long" aria-hidden="true"></i>
                <span>Main app</span>
            </a>
        </div>
    </aside>
    <div class="ratib-cp-main">
        <header class="ratib-cp-topbar" role="banner">
            <button type="button" class="ratib-cp-iconbtn" data-ratib-cp-open-sidebar aria-expanded="false" aria-controls="ratib-cp-sidebar" aria-label="Open sidebar">
                <i class="fa-solid fa-bars-staggered" aria-hidden="true"></i>
            </button>
            <div class="ratib-cp-topbar__title">
                <h1 class="ratib-cp-page-title"><?php echo isset($ratibCpPageHeading) ? htmlspecialchars((string) $ratibCpPageHeading, ENT_QUOTES, 'UTF-8') : 'Dashboard'; ?></h1>
                <?php if (!empty($ratibCpPageSubheading)): ?>
                    <p class="ratib-cp-page-sub"><?php echo htmlspecialchars((string) $ratibCpPageSubheading, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
            </div>
            <div class="ratib-cp-topbar__actions">
                <a class="ratib-cp-pillbtn" href="<?php echo htmlspecialchars(ratib_nav_url('client/notifications-center.php'), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa-regular fa-bell" aria-hidden="true"></i>
                    <span>Alerts</span>
                </a>
                <a class="ratib-cp-pillbtn" href="<?php echo htmlspecialchars(ratib_nav_url('help-center.php'), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa-regular fa-circle-question" aria-hidden="true"></i>
                    <span>Help</span>
                </a>
                <div class="ratib-cp-userchip" tabindex="0" role="group" aria-label="Signed in">
                    <span class="ratib-cp-userchip__dot" aria-hidden="true"></span>
                    <span class="ratib-cp-userchip__name"><?php echo htmlspecialchars($ratibCpUserDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <a class="ratib-cp-ghost-link" href="<?php echo $ratibCpLogoutUrl; ?>"><span class="visually-hidden">Log out</span><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i></a>
            </div>
        </header>
        <main class="ratib-cp-body" id="<?php echo htmlspecialchars($ratibCpSkipId, ENT_QUOTES, 'UTF-8'); ?>" tabindex="-1">
