<?php
/**
 * RATEB ERP — الشركات المشتركة وفروعها (Control Panel only — لا إدارة من ERP).
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
$flashOk = '';
$flashErr = '';
$newPortalUrl = '';
$newBranchName = '';
$agencyId = control_rateb_erp_resolve_agency_id();
$agencyLabel = $agencyId > 0 ? control_rateb_erp_agency_label($agencyId) : '';
$focusCompanyId = (int) ($_GET['company_id'] ?? $_POST['company_id'] ?? 0);

if (empty($_SESSION['rateb_erp_branches_csrf'])) {
    $_SESSION['rateb_erp_branches_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['rateb_erp_branches_csrf'];

if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['_csrf'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $flashErr = 'طلب غير صالح — حدّث الصفحة وحاول مجدداً.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $companyId = (int) ($_POST['company_id'] ?? 0);
        if ($action === 'set_branch_limit' && $companyId > 0) {
            $limit = max(0, (int) ($_POST['branch_limit'] ?? 0));
            if (control_rateb_erp_company_set_branch_limit($companyId, $limit)) {
                $flashOk = 'تم تحديث حد الفروع للشركة.';
                $focusCompanyId = $companyId;
            } else {
                $flashErr = 'تعذّر تحديث حد الفروع.';
            }
        } elseif ($action === 'create_branch' && $companyId > 0) {
            $result = control_rateb_erp_branch_create($companyId, [
                'name' => (string) ($_POST['branch_name'] ?? ''),
                'code' => (string) ($_POST['branch_code'] ?? ''),
                'address' => (string) ($_POST['branch_address'] ?? ''),
                'phone' => (string) ($_POST['branch_phone'] ?? ''),
                'email' => (string) ($_POST['branch_email'] ?? ''),
            ]);
            if (!empty($result['ok'])) {
                $newPortalUrl = (string) ($result['portal_url'] ?? '');
                $newBranchName = (string) ($result['branch']['name'] ?? '');
                $flashOk = 'تم إنشاء الفرع «' . $newBranchName . '» — رابط الدخول جاهز أدناه.';
                $focusCompanyId = $companyId;
            } else {
                $err = (string) ($result['error'] ?? '');
                $flashErr = $err === 'branch_limit_reached'
                    ? 'وصلت الشركة للحد الأقصى من الفروع — زِد «حد الفروع» أولاً.'
                    : ($err === 'branch_name_required' ? 'اسم الفرع مطلوب.' : 'تعذّر إنشاء الفرع: ' . $err);
                $focusCompanyId = $companyId;
            }
        } elseif ($action === 'toggle_branch') {
            $branchId = (int) ($_POST['branch_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? '') === 'active' ? 'active' : 'inactive';
            if (control_rateb_erp_branch_set_status($branchId, $status)) {
                $flashOk = $status === 'active' ? 'تم تفعيل الفرع.' : 'تم إيقاف الفرع.';
                $focusCompanyId = $companyId;
            } else {
                $flashErr = 'تعذّر تحديث حالة الفرع.';
            }
        }
    }
}

$companies = $schemaReady ? control_rateb_erp_companies_branch_overview() : [];
if ($focusCompanyId < 1 && $agencyId > 0 && count($companies) === 1) {
    $focusCompanyId = (int) ($companies[0]['id'] ?? 0);
}
$focusCompanyKnown = $focusCompanyId < 1;
if ($focusCompanyId > 0) {
    foreach ($companies as $coRow) {
        if ((int) ($coRow['id'] ?? 0) === $focusCompanyId) {
            $focusCompanyKnown = true;
            break;
        }
    }
}

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('الشركات والفروع — نظام رتب ERP', ['css/system-settings.css', 'css/control/rateb-erp-hub.css'], []);
?>

<p class="control-settings-intro mb-3">
    <strong><i class="fas fa-building me-2"></i>الشركات المشتركة وفروعها</strong>
    — كل شركة مشتركة مستقلة بفروعها. التحكم هنا من <strong>لوحة التحكم فقط</strong> (لا يظهر قسم الفروع داخل ERP للعملاء).
    حدّد <strong>حد الفروع</strong> لكل شركة ثم أضف الفروع — يُنشأ <strong>رابط دخول تلقائي</strong> لكل فرع.
</p>
<?php if ($agencyId > 0) { ?>
<div class="alert alert-primary py-2 mb-3">
    <i class="fas fa-store me-1"></i>
    <strong>وكالة:</strong> <?php echo htmlspecialchars($agencyLabel !== '' ? $agencyLabel : ('#' . $agencyId), ENT_QUOTES, 'UTF-8'); ?>
    <?php if (control_rateb_erp_agency_db_name($agencyId) !== '') { ?>
    · <code><?php echo htmlspecialchars(control_rateb_erp_agency_db_name($agencyId), ENT_QUOTES, 'UTF-8'); ?></code>
    <?php } ?>
</div>
<?php } ?>

<?php if (!$schemaReady) { ?>
<div class="alert alert-warning">شغّل إعداد قاعدة البيانات أولاً من <a href="<?php echo htmlspecialchars(control_rateb_erp_migrate_page_url(), ENT_QUOTES, 'UTF-8'); ?>">هنا</a>.</div>
<?php } else { ?>

<?php if ($flashOk !== '') { ?>
<div class="alert alert-success"><?php echo htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>
<?php if ($flashErr !== '') { ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>
<?php if ($focusCompanyId > 0 && !$focusCompanyKnown) { ?>
<div class="alert alert-warning">الشركة رقم <?php echo (int) $focusCompanyId; ?> غير موجودة في قاعدة ERP الحالية — تأكد أنك تدير فروع <strong>منصة rateb.sa</strong> وليس وكالة أخرى.</div>
<?php } ?>
<?php if ($newPortalUrl !== '') { ?>
<div class="alert alert-info">
    <strong><i class="fas fa-link me-1"></i>رابط دخول الفرع الجديد<?php echo $newBranchName !== '' ? ' — ' . htmlspecialchars($newBranchName, ENT_QUOTES, 'UTF-8') : ''; ?>:</strong>
    <div class="input-group input-group-sm mt-2">
        <input type="text" class="form-control font-monospace user-select-all" readonly value="<?php echo htmlspecialchars($newPortalUrl, ENT_QUOTES, 'UTF-8'); ?>" id="new-branch-portal-url">
        <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('new-branch-portal-url').value)"><i class="fas fa-copy"></i></button>
        <a href="<?php echo htmlspecialchars($newPortalUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i></a>
    </div>
</div>
<?php } ?>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="<?php echo htmlspecialchars(control_rateb_erp_public_url('admin/companies'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary" target="_blank" rel="noopener">
        <i class="fas fa-building"></i> إدارة الشركات (ERP)
    </a>
    <a href="<?php echo htmlspecialchars(control_rateb_erp_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary">
        <i class="fas fa-hospital"></i> مركز ERP
    </a>
</div>

<?php if ($companies === []) { ?>
<div class="alert alert-info">لا توجد شركات مشتركة بعد. أضف شركة من ERP ثم ارجع هنا لمنحها فروعاً.</div>
<?php } ?>

<?php foreach ($companies as $company) {
    $cid = (int) ($company['id'] ?? 0);
    $branchCount = (int) ($company['branch_count'] ?? 0);
    $limitEff = (int) ($company['branch_limit_effective'] ?? 0);
    $limitSet = (int) ($company['branch_limit'] ?? 0);
    $canAdd = !empty($company['can_add_branch']);
    $branches = control_rateb_erp_company_branches($cid);
    $cardId = 'company-branches-' . $cid;
    $expanded = $focusCompanyId > 0 ? ($focusCompanyId === $cid) : false;
    ?>
<div class="control-settings-card mb-4<?php echo $focusCompanyId === $cid ? ' border border-primary border-2' : ''; ?>" id="<?php echo htmlspecialchars($cardId, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
            <h3 class="h5 mb-1">
                <i class="fas fa-building text-primary"></i>
                <?php echo htmlspecialchars((string) ($company['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </h3>
            <div class="small text-muted">
                <code><?php echo htmlspecialchars((string) ($company['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
                · <?php echo htmlspecialchars((string) ($company['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                · الفروع: <strong><?php echo $branchCount; ?></strong> / <?php echo $limitEff; ?>
                <?php if ($limitSet > 0) { ?>(محدّد: <?php echo $limitSet; ?>)<?php } ?>
            </div>
        </div>
        <form method="post" class="d-flex align-items-end gap-2 flex-wrap">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($agencyId > 0) { ?><input type="hidden" name="agency_id" value="<?php echo $agencyId; ?>"><?php } else { ?><input type="hidden" name="platform" value="1"><?php } ?>
            <input type="hidden" name="action" value="set_branch_limit">
            <input type="hidden" name="company_id" value="<?php echo $cid; ?>">
            <div>
                <label class="form-label small mb-0">حد الفروع (صلاحية الإضافة)</label>
                <input type="number" name="branch_limit" class="form-control form-control-sm" style="width:6rem" min="0" max="999" value="<?php echo $limitSet > 0 ? $limitSet : $limitEff; ?>" title="0 = حسب الباقة">
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-check"></i> حفظ الحد</button>
        </form>
    </div>

    <?php if ($branches !== []) { ?>
    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th>الفرع</th>
                <th>الكود</th>
                <th>النوع</th>
                <th>الحالة</th>
                <th>رابط دخول الفرع</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($branches as $branch) {
                $bid = (int) ($branch['id'] ?? 0);
                $portalUrl = control_rateb_erp_branch_portal_url($bid, $branch);
                $isMain = !empty($branch['is_main']);
                $isActive = (string) ($branch['status'] ?? '') === 'active';
                ?>
            <tr>
                <td><?php echo htmlspecialchars((string) ($branch['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><code><?php echo htmlspecialchars((string) ($branch['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                <td><?php echo $isMain ? '<span class="badge bg-info">رئيسي</span>' : '<span class="badge bg-secondary">فرع</span>'; ?></td>
                <td><?php echo $isActive ? '<span class="badge bg-success">نشط</span>' : '<span class="badge bg-warning text-dark">موقوف</span>'; ?></td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace" readonly value="<?php echo htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8'); ?>" id="branch-url-<?php echo $bid; ?>">
                        <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('branch-url-<?php echo $bid; ?>').value)"><i class="fas fa-copy"></i></button>
                        <a href="<?php echo htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i></a>
                    </div>
                </td>
                <td>
                    <?php if (!$isMain) { ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if ($agencyId > 0) { ?><input type="hidden" name="agency_id" value="<?php echo $agencyId; ?>"><?php } else { ?><input type="hidden" name="platform" value="1"><?php } ?>
                        <input type="hidden" name="action" value="toggle_branch">
                        <input type="hidden" name="company_id" value="<?php echo $cid; ?>">
                        <input type="hidden" name="branch_id" value="<?php echo $bid; ?>">
                        <input type="hidden" name="status" value="<?php echo $isActive ? 'inactive' : 'active'; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-<?php echo $isActive ? 'warning' : 'success'; ?>"><?php echo $isActive ? 'إيقاف' : 'تفعيل'; ?></button>
                    </form>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php } else { ?>
    <p class="small text-muted mb-3">لا فروع بعد — سيُنشأ الفرع الرئيسي تلقائياً عند إضافة أول فرع.</p>
    <?php } ?>

    <?php if ($canAdd) { ?>
    <details class="border rounded p-3 bg-light-subtle"<?php echo $focusCompanyId === $cid ? ' open' : ''; ?>>
        <summary class="fw-semibold cursor-pointer"><i class="fas fa-plus-circle text-primary"></i> إضافة فرع لهذه الشركة</summary>
        <form method="post" class="row g-2 mt-3">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($agencyId > 0) { ?><input type="hidden" name="agency_id" value="<?php echo $agencyId; ?>"><?php } else { ?><input type="hidden" name="platform" value="1"><?php } ?>
            <input type="hidden" name="action" value="create_branch">
            <input type="hidden" name="company_id" value="<?php echo $cid; ?>">
            <div class="col-md-4">
                <label class="form-label small">اسم الفرع *</label>
                <input type="text" name="branch_name" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small">الكود</label>
                <input type="text" name="branch_code" class="form-control form-control-sm" placeholder="تلقائي">
            </div>
            <div class="col-md-3">
                <label class="form-label small">الهاتف</label>
                <input type="text" name="branch_phone" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small">البريد</label>
                <input type="email" name="branch_email" class="form-control form-control-sm">
            </div>
            <div class="col-12">
                <label class="form-label small">العنوان</label>
                <input type="text" name="branch_address" class="form-control form-control-sm">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-store"></i> إنشاء الفرع + رابط الدخول</button>
            </div>
        </form>
    </details>
    <?php } else { ?>
    <div class="alert alert-warning py-2 small mb-0">
        <i class="fas fa-lock"></i> لا يمكن إضافة فروع — زِد «حد الفروع» أعلاه أو راجع باقة الشركة.
    </div>
    <?php } ?>
</div>
<?php } ?>

<p class="small text-muted">
    <i class="fas fa-info-circle"></i>
    كل شركة لها فروع مستقلة وبيانات معزولة عند الدخول من رابط الفرع. لا تستخدم <code>company/login</code> للفروع الفرعية.
</p>
<?php } ?>

<?php endControlLayout(); ?>
