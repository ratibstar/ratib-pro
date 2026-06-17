<?php
/**
 * Control panel Help center — renders docs from repository Markdown
 * (operator guide + infra appendix + route ownership appendix).
 */
declare(strict_types=1);

if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_DASHBOARD);

require_once __DIR__ . '/../../includes/control/help-markdown.php';

$repoRoot = dirname(__DIR__, 3);
$guidePath = $repoRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'control-panel-help-center-guide.md';
$infraPath = $repoRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'infrastructure-tabs-operator-checklist.md';
$ownershipPath = $repoRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'CLIENT_HUB_CONTROL_PANEL_ROUTE_OWNERSHIP.md';

$merged = '';
if (is_readable($guidePath)) {
    $merged = (string) file_get_contents($guidePath);
}
if ($merged === '') {
    $merged = "# Help center\n\n" . cp_t('help.missing_content') . "\n";
}

if (is_readable($infraPath)) {
    $infraRaw = (string) file_get_contents($infraPath);
    $infraBody = preg_replace('/^#\s[^\n]+\n+/', '', $infraRaw, 1) ?? $infraRaw;
    $merged .= "\n\n---\n\n## " . cp_t('help.appendix_infra') . "\n\n";
    $merged .= trim($infraBody) . "\n";
}

if (is_readable($ownershipPath)) {
    $ownershipRaw = (string) file_get_contents($ownershipPath);
    $ownershipBody = preg_replace('/^#\s[^\n]+\n+/', '', $ownershipRaw, 1) ?? $ownershipRaw;
    $merged .= "\n\n---\n\n## " . cp_t('help.appendix_ownership') . "\n\n";
    $merged .= trim($ownershipBody) . "\n";
}

[$html, $_ids] = cp_help_render_markdown($merged);
if (function_exists('cp_translate_html')) {
    $html = cp_translate_html($html);
}

$tocParts = [];
foreach (preg_split("/\r\n|\n|\r/", $merged) ?: [] as $line) {
    $t = trim((string) $line);
    if (preg_match('/^## (.+)$/', $t, $m)) {
        $title = (string) $m[1];
        $titlePlain = preg_replace('/\*\*(.+?)\*\*/', '$1', $title) ?? $title;
        if (function_exists('cp_translate_html')) {
            $titlePlain = cp_translate_html($titlePlain);
        }
        $id = cp_help_slug($title);
        $tocParts[] = '<a class="help-toc-link" href="#' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($titlePlain, ENT_QUOTES, 'UTF-8') . '</a>';
    }
}
$tocHtml = $tocParts !== []
    ? '<nav class="help-toc" aria-label="' . htmlspecialchars(cp_t('help.toc_on_page'), ENT_QUOTES, 'UTF-8') . '"><strong>' . htmlspecialchars(cp_t('help.toc_on_page'), ENT_QUOTES, 'UTF-8') . '</strong><div class="help-toc-list">' . implode('', $tocParts) . '</div></nav>'
    : '';

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Help center', ['css/control/help-center.css'], []);
?>

<div class="help-center-wrap">
    <p class="help-center-intro">
        <strong><i class="fas fa-circle-question me-2"></i><?php echo htmlspecialchars(cp_t('help.title'), ENT_QUOTES, 'UTF-8'); ?></strong>
        — <?php echo htmlspecialchars(cp_t('help.intro'), ENT_QUOTES, 'UTF-8'); ?>
        <?php echo htmlspecialchars(cp_t('help.source_note'), ENT_QUOTES, 'UTF-8'); ?>
    </p>
    <?php echo $tocHtml; ?>
    <article class="help-doc">
        <?php echo $html; ?>
    </article>
</div>

<?php endControlLayout(); ?>
