<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control-support-chats.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control-support-chats.php`.
 */
/**
 * Control Panel: Support Chats - view escalated chats from chat widget, reply to visitors
 */
require_once __DIR__ . '/../includes/config.php';

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
require_once __DIR__ . '/../includes/control-permissions.php';
$canViewSupportChats = hasControlPermission(CONTROL_PERM_SUPPORT) || hasControlPermission('view_control_support');
$canReplySupportChats = hasControlPermission(CONTROL_PERM_SUPPORT) || hasControlPermission('reply_control_support');
$canSupportBulkSelect = hasControlSupportBulkSelect();
$canSupportMarkClosed = hasControlSupportMarkClosed();
$canSupportMarkOpen = hasControlSupportMarkOpen();
$canSupportDeleteChat = hasControlSupportDeleteChat();
// Bulk bar only when user can select rows and at least one bulk action exists (avoids disabled buttons with no checkboxes).
$showSupportChatsBulkBar = $canSupportBulkSelect && ($canSupportMarkClosed || $canSupportMarkOpen || $canSupportDeleteChat);
if (!$canViewSupportChats) {
    http_response_code(403);
    die('Access denied.');
}

// If not embedded, redirect to unified layout version
if (empty($_GET['embedded'])) {
    $queryParams = http_build_query(array_intersect_key($_GET, array_flip(['status', 'page', 'limit', 'country_id'])));
    header('Location: ' . pageUrl('control/support-chats.php') . ($queryParams ? '?' . $queryParams : ''));
    exit;
}

$ctrl = $GLOBALS['control_conn'] ?? $GLOBALS['conn'] ?? null;
if (!$ctrl) die('Control panel database unavailable.');

$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;
$apiBase = $baseUrl . '/api/control';
$registerProUrl = $baseUrl . '/pages/home.php?open=register&plan=gold&years=1';

$chk = @$ctrl->query("SHOW TABLES LIKE 'control_support_chats'");
$tableExists = ($chk && $chk->num_rows > 0);

$status = trim($_GET['status'] ?? 'open');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(10, min(50, (int)($_GET['limit'] ?? 20)));
$countryId = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%236b21a8'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' fill='white' text-anchor='middle'%3ER%3C/text%3E%3C/svg%3E">
    <title>Support Chats - Control Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/control/control-support-chats.css'); ?>?v=<?php echo time(); ?>">
</head>
<body data-api-base="<?php echo htmlspecialchars($apiBase); ?>" data-status="<?php echo htmlspecialchars($status); ?>" data-page="<?php echo (int)$page; ?>" data-limit="<?php echo (int)$limit; ?>" data-country-id="<?php echo (int)$countryId; ?>" data-can-reply="<?php echo $canReplySupportChats ? '1' : '0'; ?>" data-can-bulk-select="<?php echo $canSupportBulkSelect ? '1' : '0'; ?>" data-can-mark-closed="<?php echo $canSupportMarkClosed ? '1' : '0'; ?>" data-can-mark-open="<?php echo $canSupportMarkOpen ? '1' : '0'; ?>" data-can-delete-chat="<?php echo $canSupportDeleteChat ? '1' : '0'; ?>">
    <?php if (empty($_GET['embedded'])): ?>
    <div class="control-header">
        <div class="control-nav">
            <a href="<?php echo htmlspecialchars($registerProUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success btn-sm"><i class="fas fa-external-link-alt me-1"></i> Register Pro</a>
<a href="<?php echo pageUrl('control/dashboard.php'); ?>"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            <a href="<?php echo pageUrl('select-country.php'); ?>"><i class="fas fa-globe me-1"></i> Countries</a>
            <a href="<?php echo pageUrl('control/agencies.php'); ?>"><i class="fas fa-building me-1"></i> Manage Agencies</a>
            <a href="<?php echo pageUrl('control/registration-requests.php'); ?>"><i class="fas fa-user-plus me-1"></i> Registration Requests</a>
            <a href="<?php echo pageUrl('control/support-chats.php'); ?>" class="support-chats"><i class="fas fa-comments me-1"></i> Support Chats <span class="chat-badge d-none" id="chatBadge">0</span></a>
            <a href="<?php echo pageUrl('control/accounting.php'); ?>" class="btn btn-outline-warning btn-sm"><i class="fas fa-calculator me-1"></i> Accounting</a>
            <a href="<?php echo pageUrl('control/dashboard.php'); ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer"><i class="fas fa-briefcase me-1"></i> Recruitment Program</a>
            <a href="<?php echo (defined('RATIB_PRO_URL') ? RATIB_PRO_URL . '?control=1&own=1' : pageUrl('control/dashboard.php')); ?>" class="btn btn-outline-success btn-sm" target="_blank" rel="noopener noreferrer"><i class="fas fa-user me-1"></i> My own Program</a>
            </div>
        <div>
            <span class="text-muted me-3"><?php echo htmlspecialchars($_SESSION['control_username'] ?? ''); ?></span>
            <a href="<?php echo pageUrl('logout.php'); ?>" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="content">
        <div class="chat-list-card">
            <h2 class="mb-3"><i class="fas fa-comments me-2"></i>Support Chats</h2>
            <p class="text-muted small mb-3">Chats from <strong>all countries and agencies</strong> appear here only after a visitor starts <strong>live support</strong> (e.g. &quot;Talk to support&quot;) in Ratib Pro. For each country+agency there is <strong>one open board</strong>: new escalations append to the same thread instead of opening duplicate rows. Close a thread when you are done so a fresh board can start. Regular AI-only widget Q&amp;A stays in the widget. Filter by country below.</p>

            <?php if (!$tableExists): ?>
            <p class="text-warning">Run the migration: <code>config/control_support_chats.sql</code></p>
            <?php else: ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <label class="text-muted small mb-0 me-1">Country</label>
                <select id="supportChatCountryFilter" class="form-select form-select-sm ctrl-input support-country-filter">
                    <option value="0">All countries</option>
                    <option value="-1">Not set / public</option>
                </select>
            </div>
            <div id="supportChatCountryChips" class="d-flex flex-wrap gap-2 mb-3 small"></div>
            <div class="d-flex gap-2 mb-3">
                <?php
                $cq = $countryId !== 0 ? '&country_id=' . (int) $countryId : '';
                ?>
                <a href="?control=1&amp;status=open<?php echo $cq; ?>" class="btn btn-sm <?php echo $status==='open'?'btn-primary':'btn-outline-secondary'; ?>">Open</a>
                <a href="?control=1&amp;status=closed<?php echo $cq; ?>" class="btn btn-sm <?php echo $status==='closed'?'btn-primary':'btn-outline-secondary'; ?>">Closed</a>
            </div>
            <?php if ($showSupportChatsBulkBar): ?>
            <div class="d-flex flex-wrap gap-2 mb-3" id="supportChatsBulkBar">
                <?php if ($canSupportBulkSelect): ?>
                <button type="button" class="btn btn-sm btn-outline-light" id="btnSelectAllChats"><i class="far fa-check-square me-1"></i>Select All</button>
                <?php endif; ?>
                <?php if ($canSupportMarkClosed): ?>
                <button type="button" class="btn btn-sm btn-outline-warning" id="btnBulkCloseChats" disabled><i class="fas fa-lock me-1"></i>Mark Closed</button>
                <?php endif; ?>
                <?php if ($canSupportMarkOpen): ?>
                <button type="button" class="btn btn-sm btn-outline-success" id="btnBulkOpenChats" disabled><i class="fas fa-lock-open me-1"></i>Mark Open</button>
                <?php endif; ?>
                <?php if ($canSupportDeleteChat): ?>
                <button type="button" class="btn btn-sm btn-outline-danger" id="btnBulkDeleteChats" disabled><i class="fas fa-trash me-1"></i>Delete Selected</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div id="chatListContainer">
                <div class="empty-state" id="chatListEmpty"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</div>
                <div id="chatList" class="d-none"></div>
            </div>
            <div id="chatPagination" class="mt-3 d-none"></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reply modal -->
    <div class="modal fade" id="replyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chat <span id="modalChatId"></span> — <span id="modalSource"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="chat-messages-area" id="modalMessages"></div>
                    <div id="replyArea" class="<?php echo $canReplySupportChats ? '' : 'd-none'; ?>">
                        <label class="form-label">Reply</label>
                        <textarea class="form-control ctrl-input" id="replyText" rows="3" placeholder="Type your message..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-warning <?php echo $canSupportMarkClosed ? '' : 'd-none'; ?>" id="btnCloseChat">Mark Closed</button>
                    <button type="button" class="btn btn-outline-danger <?php echo $canSupportDeleteChat ? '' : 'd-none'; ?>" id="btnDeleteChat">Delete</button>
                    <button type="button" class="btn btn-primary <?php echo $canReplySupportChats ? '' : 'd-none'; ?>" id="btnSendReply"><i class="fas fa-paper-plane me-1"></i> Send Reply</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php $scpJs = __DIR__ . '/../js/control/support-chats-page.js'; $scpV = is_file($scpJs) ? filemtime($scpJs) : time(); ?>
    <script src="../js/control/support-chats-page.js?v=<?php echo (int) $scpV; ?>"></script>
<?php require_once __DIR__ . '/../includes/control_pending_reg_alert.php'; ?>
</body>
</html>
