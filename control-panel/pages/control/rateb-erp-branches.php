<?php
/**
 * RATEB ERP — branch portal links & management hub (Control Panel only).
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/rateb-erp-nav.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_DASHBOARD, 'control_system_settings', 'view_control_system_settings');

$schemaReady = control_rateb_erp_schema_ready();
$branches = $schemaReady ? control_rateb_erp_branches_catalog() : [];
$byCompany = [];
foreach ($branches as $row) {
    $cid = (int) ($row['company_id'] ?? 0);
    if ($cid < 1) {
        continue;
    }
    if (!isset($byCompany[$cid])) {
        $byCompany[$cid] = [
            'id' => $cid,
            'name' => (string) ($row['company_name'] ?? ''),
            'slug' => (string) ($row['company_slug'] ?? ''),
            'branches' => [],
        ];
    }
    $byCompany[$cid]['branches'][] = $row;
}

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('روابط الفروع — نظام رتب ERP', ['css/system-settings.css', 'css/control/rateb-erp-hub.css'], []);
?>

<p class="control-settings-intro mb-3">
    <strong><i class="fas fa-store me-2"></i>إدارة الفروع وروابط الدخول</strong>
    — أنشئ وعدّل الفروع من لوحة التحكم فقط. أعطِ كل شركة/فرع <strong>رابط دخول خاص</strong> (لا يستخدم رابط الفرع الرئيسي العام).
</p>

<?php if (!$schemaReady) { ?>
<div class="alert alert-warning">شغّل إعداد قاعدة البيانات أولاً من <a href="<?php echo htmlspecialchars(control_rateb_erp_migrate_page_url(), ENT_QUOTES, 'UTF-8'); ?>">هنا</a>.</div>
<?php } else { ?>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="<?php echo htmlspecialchars(control_rateb_erp_branch_manage_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">
        <i class="fas fa-cog"></i> إدارة الفروع (داخل اللوحة)
    </a>
    <a href="<?php echo htmlspecialchars(control_rateb_erp_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary">
        <i class="fas fa-hospital"></i> مركز ERP
    </a>
</div>

<?php if ($byCompany === []) { ?>
<div class="alert alert-info">لا توجد فروع بعد. افتح <strong>إدارة الفروع</strong> واختر الشركة ثم أضف فروعاً.</div>
<?php } ?>

<?php foreach ($byCompany as $company) { ?>
<div class="control-settings-card mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h3 class="h5 mb-0">
            <i class="fas fa-building text-primary"></i>
            <?php echo htmlspecialchars((string) $company['name'], ENT_QUOTES, 'UTF-8'); ?>
        </h3>
        <a href="<?php echo htmlspecialchars(control_rateb_erp_branch_manage_url((int) $company['id']), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-pen"></i> إدارة فروع الشركة
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th>الفرع</th>
                <th>الكود</th>
                <th>النوع</th>
                <th>رابط دخول الفرع</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($company['branches'] as $branch) {
                $bid = (int) ($branch['id'] ?? 0);
                $portalUrl = control_rateb_erp_branch_portal_url($bid, $branch);
                $isMain = !empty($branch['is_main']);
                ?>
            <tr>
                <td><?php echo htmlspecialchars((string) ($branch['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><code><?php echo htmlspecialchars((string) ($branch['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                <td>
                    <?php if ($isMain) { ?>
                    <span class="badge bg-info">رئيسي</span>
                    <?php } else { ?>
                    <span class="badge bg-secondary">فرع</span>
                    <?php } ?>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace" readonly value="<?php echo htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8'); ?>" id="branch-url-<?php echo $bid; ?>">
                        <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('branch-url-<?php echo $bid; ?>').value)"><i class="fas fa-copy"></i></button>
                        <a href="<?php echo htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i></a>
                    </div>
                </td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>

<p class="small text-muted">
    <i class="fas fa-info-circle"></i>
    رابط <code>company/login</code> العام لم يعد مناسباً للفروع — استخدم رابط كل فرع أعلاه. بعد الدخول يرى المستخدم بيانات فرعه فقط.
</p>
<?php } ?>

<div class="control-settings-card mb-4 border-info">
    <h3 class="h5"><i class="fas fa-flask text-info"></i> أمثلة تجريبية (بعد هجرة 124)</h3>
    <p class="small text-muted mb-3">شركة <strong>example-medical</strong> — كلمة المرور للمستخدمين: <code>password</code></p>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>الفرع</th><th>المستخدم</th><th>رابط الدخول</th></tr></thead>
            <tbody>
            <?php
            $examples = [
                ['الفرع الرئيسي MB001', 'hq@example.rateb.sa', 'example-medical', 'MB001'],
                ['فرع جدة BR002', 'branch@example.rateb.sa', 'example-medical', 'BR002'],
            ];
            foreach ($examples as [$label, $email, $co, $br]) {
                $url = control_rateb_erp_public_url('login?company=' . rawurlencode($co) . '&branch=' . rawurlencode($br));
                ?>
            <tr>
                <td><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></td>
                <td><code><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></code></td>
                <td><code class="user-select-all"><?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?></code></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <p class="small text-muted mt-2 mb-0">إدارة الفروع: <code>rateb-erp-app.php?route=admin/ops/branches&amp;company_id=…</code> من زر «إدارة الفروع».</p>
</div>

<?php endControlLayout(); ?>
