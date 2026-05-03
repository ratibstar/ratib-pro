<?php
/**
 * Partner portal — read-only worker CV (same visual layout as staff Worker CV preview).
 */
require_once __DIR__ . '/../includes/config.php';

if (!ratib_partner_portal_session_is_valid()) {
    header('Location: ' . pageUrl('partner-portal-login.php'));
    exit;
}

$cvWorkerId = (int) ($_GET['worker_id'] ?? 0);
if ($cvWorkerId <= 0) {
    header('Location: ' . pageUrl('partner-portal-documents.php'));
    exit;
}

$pageTitle = 'Worker CV';
$v = time();
$pageCss = [
    asset('css/partnerships.css') . '?v=' . $v,
    asset('css/partner-portal.css') . '?v=' . $v,
];
$pageJs = [asset('js/partnerships/partner-portal-cv.js') . '?v=' . $v];
$partnerPortalMinimal = true;
$ppPortalDocs = htmlspecialchars(pageUrl('partner-portal-documents.php'), ENT_QUOTES, 'UTF-8');
include __DIR__ . '/../includes/partner-portal-header.php';
?>

<div class="partner-portal-wrap partner-portal-cv-page" dir="ltr" lang="en">
    <div id="partnerCvToolbar" class="glass-card partner-portal-cv-toolbar">
        <a class="muted-btn" href="<?php echo $ppPortalDocs; ?>">← Documents &amp; CVs</a>
        <button type="button" class="neon-btn" id="partnerCvPrint" disabled>Print CV</button>
    </div>
    <div id="partnerCvError" class="partner-portal-error glass-card" hidden></div>
    <div id="partnerCvSheet" class="partner-portal-cv-sheet" aria-live="polite"><p class="partner-portal-docs-subline">Loading CV…</p></div>
</div>

<?php include __DIR__ . '/../includes/partner-portal-footer.php'; ?>
