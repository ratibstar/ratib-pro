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

$agencyId = control_rateb_erp_resolve_agency_id();
$agencyLabel = $agencyId > 0 ? control_rateb_erp_agency_label($agencyId) : '';
$agencyDbName = $agencyId > 0 ? control_rateb_erp_agency_db_name($agencyId) : '';
$agencyPdoOk = $agencyId < 1 || (function_exists('control_rateb_erp_pdo_for_agency') && control_rateb_erp_pdo_for_agency($agencyId) instanceof \PDO);
$schemaReady = $agencyId > 0 ? $agencyPdoOk : control_rateb_erp_schema_ready();
$flashOk = '';
$flashErr = '';
$newPortalUrl = '';
$newBranchName = '';
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
                    : ($err === 'branch_name_required'
                        ? 'اسم الفرع مطلوب.'
                        : ($err === 'branch_code_duplicate'
                            ? 'كود الفرع مستخدم مسبقاً لهذه الشركة.'
                            : 'تعذّر إنشاء الفرع: ' . $err));
                $focusCompanyId = $companyId;
            }
        } elseif ($action === 'toggle_branch' && $companyId > 0) {
            $branchId = (int) ($_POST['branch_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? '') === 'active' ? 'active' : 'inactive';
            $result = control_rateb_erp_branch_set_status($companyId, $branchId, $status);
            if (!empty($result['ok'])) {
                if (empty($result['noop'])) {
                    $flashOk = $status === 'active' ? 'تم تفعيل الفرع.' : 'تم إيقاف الفرع.';
                }
                $focusCompanyId = $companyId;
            } else {
                $err = (string) ($result['error'] ?? '');
                $flashErr = $err === 'branch_last_active'
                    ? 'لا يمكن إيقاف آخر فرع نشط لهذه الشركة.'
                    : ($err === 'record_not_found'
                        ? 'الفرع غير موجود أو لا يتبع هذه الشركة.'
                        : 'تعذّر تحديث حالة الفرع.');
                $focusCompanyId = $companyId;
            }
        } elseif ($action === 'update_branch' && $companyId > 0) {
            $branchId = (int) ($_POST['branch_id'] ?? 0);
            $result = control_rateb_erp_branch_update($companyId, $branchId, [
                'name' => (string) ($_POST['branch_name'] ?? ''),
                'code' => (string) ($_POST['branch_code'] ?? ''),
                'phone' => (string) ($_POST['branch_phone'] ?? ''),
                'email' => (string) ($_POST['branch_email'] ?? ''),
                'address' => (string) ($_POST['branch_address'] ?? ''),
                'map_url' => (string) ($_POST['branch_map_url'] ?? ''),
                'status' => (string) ($_POST['branch_status'] ?? ''),
            ]);
            if (!empty($result['ok'])) {
                $flashOk = 'تم تحديث بيانات الفرع.';
                $focusCompanyId = $companyId;
            } else {
                $err = (string) ($result['error'] ?? '');
                $flashErr = $err === 'branch_name_required'
                    ? 'اسم الفرع مطلوب.'
                    : ($err === 'branch_code_duplicate'
                        ? 'كود الفرع مستخدم مسبقاً لهذه الشركة.'
                        : ($err === 'branch_last_active'
                            ? 'لا يمكن إيقاف آخر فرع نشط لهذه الشركة.'
                            : ($err === 'record_not_found'
                                ? 'الفرع غير موجود أو لا يتبع هذه الشركة.'
                                : 'تعذّر تحديث الفرع: ' . $err)));
                $focusCompanyId = $companyId;
            }
        } elseif ($action === 'archive_branch' && $companyId > 0) {
            $branchId = (int) ($_POST['branch_id'] ?? 0);
            $result = control_rateb_erp_branch_archive($companyId, $branchId);
            if (!empty($result['ok']) && empty($result['noop'])) {
                $flashOk = 'تم أرشفة الفرع.';
            }
            $focusCompanyId = $companyId;
            if (empty($result['ok'])) {
                $err = (string) ($result['error'] ?? '');
                $flashErr = $err === 'branch_main_archive_denied'
                    ? 'لا يمكن أرشفة الفرع الرئيسي.'
                    : ($err === 'branch_last_active'
                        ? 'لا يمكن أرشفة آخر فرع نشط.'
                        : 'تعذّر أرشفة الفرع.');
            }
        } elseif ($action === 'restore_branch' && $companyId > 0) {
            $branchId = (int) ($_POST['branch_id'] ?? 0);
            $result = control_rateb_erp_branch_restore($companyId, $branchId);
            if (!empty($result['ok']) && empty($result['noop'])) {
                $flashOk = 'تم استعادة الفرع من الأرشيف.';
            }
            $focusCompanyId = $companyId;
            if (empty($result['ok'])) {
                $flashErr = 'تعذّر استعادة الفرع.';
            }
        } elseif ($action === 'bulk_branch' && $companyId > 0) {
            $bulkAction = (string) ($_POST['bulk_action'] ?? '');
            $ids = isset($_POST['branch_ids']) && is_array($_POST['branch_ids']) ? $_POST['branch_ids'] : [];
            $result = control_rateb_erp_branch_bulk($companyId, $ids, $bulkAction);
            $focusCompanyId = $companyId;
            if (!empty($result['ok'])) {
                $flashOk = 'تم تنفيذ الإجراء الجماعي على ' . (int) ($result['success'] ?? 0) . ' فرع.';
            } else {
                $flashErr = (int) ($result['success'] ?? 0) > 0
                    ? 'اكتمل جزئياً — نجح ' . (int) $result['success'] . ' وفشل ' . (int) ($result['failed'] ?? 0) . '.'
                    : 'تعذّر تنفيذ الإجراء الجماعي.';
            }
        }
    }
}

$branchListOpts = function_exists('control_rateb_erp_branch_list_opts_from_request')
    ? control_rateb_erp_branch_list_opts_from_request($_GET)
    : ['q' => '', 'status' => '', 'branch_type' => '', 'archive' => '', 'sort' => 'name', 'dir' => 'asc', 'page' => 1, 'per_page' => 25];

$companies = $schemaReady ? control_rateb_erp_companies_branch_overview() : [];
$countryId = (int) ($_GET['country_id'] ?? 0);
$agencyDbReady = $agencyId < 1 || ($agencyDbName !== '' && $schemaReady);

if ($agencyId > 0 && $focusCompanyId > 0 && $companies !== []) {
    $focusExists = false;
    foreach ($companies as $coRow) {
        if ((int) ($coRow['id'] ?? 0) === $focusCompanyId) {
            $focusExists = true;
            break;
        }
    }
    if (!$focusExists && function_exists('control_rateb_erp_agency_primary_company_id')) {
        $liveCompanyId = control_rateb_erp_agency_primary_company_id($agencyId);
        if ($liveCompanyId > 0) {
            $focusCompanyId = $liveCompanyId;
        }
    }
}
if ($focusCompanyId < 1 && $agencyId > 0) {
    if (count($companies) === 1) {
        $focusCompanyId = (int) ($companies[0]['id'] ?? 0);
    } elseif ($companies === [] && function_exists('control_rateb_erp_agency_primary_company_id')) {
        $liveCompanyId = control_rateb_erp_agency_primary_company_id($agencyId);
        if ($liveCompanyId > 0) {
            $focusCompanyId = $liveCompanyId;
            $companies = control_rateb_erp_companies_branch_overview();
        }
    }
}
$focusCompanyKnown = $focusCompanyId < 1;
$focusCompanyName = '';
if ($focusCompanyId > 0) {
    foreach ($companies as $coRow) {
        if ((int) ($coRow['id'] ?? 0) === $focusCompanyId) {
            $focusCompanyKnown = true;
            $focusCompanyName = trim((string) ($coRow['name'] ?? ''));
            break;
        }
    }
}
$isPlatformBranchHub = function_exists('control_rateb_erp_is_platform_branch_context')
    && control_rateb_erp_is_platform_branch_context();
if ($focusCompanyId > 0 && $focusCompanyKnown) {
    $companies = array_values(array_filter(
        $companies,
        static fn (array $row): bool => (int) ($row['id'] ?? 0) === $focusCompanyId
    ));
}
$allCompaniesHubUrl = $agencyId > 0 && function_exists('control_rateb_erp_agency_branch_manage_url')
    ? control_rateb_erp_agency_branch_manage_url($agencyId, 0)
    : (function_exists('control_rateb_erp_branch_manage_url')
        ? control_rateb_erp_branch_manage_url(0)
        : (function_exists('control_rateb_erp_branches_hub_page_url')
            ? control_rateb_erp_branches_hub_page_url() . (strpos(control_rateb_erp_branches_hub_page_url(), '?') !== false ? '&' : '?') . 'platform=1'
            : ''));
$agenciesBackUrl = function_exists('control_rateb_erp_agencies_page_url')
    ? control_rateb_erp_agencies_page_url($countryId)
    : '';
$agencyErpAdminUrl = ($agencyId > 0 && function_exists('control_rateb_erp_agency_admin_url'))
    ? control_rateb_erp_agency_admin_url($agencyId)
    : '';

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
<?php } elseif ($isPlatformBranchHub) { ?>
<div class="alert alert-secondary py-2 mb-3">
    <i class="fas fa-globe me-1"></i>
    <strong>منصة rateb.sa</strong> — إدارة فروع الشركات المشتركة (ليست وكالات).
</div>
<?php } ?>
<?php if ($focusCompanyId > 0 && $focusCompanyKnown && $focusCompanyName !== '') { ?>
<div class="alert alert-primary py-2 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="fas fa-building me-1"></i><strong>الشركة:</strong> <?php echo htmlspecialchars($focusCompanyName, ENT_QUOTES, 'UTF-8'); ?> <span class="text-muted">(#<?php echo (int) $focusCompanyId; ?>)</span></span>
    <?php if ($allCompaniesHubUrl !== '') { ?>
    <a href="<?php echo htmlspecialchars($allCompaniesHubUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-list"></i> كل الشركات</a>
    <?php } ?>
</div>
<?php } ?>

<?php if (!$schemaReady) { ?>
<div class="alert alert-warning">
    <?php if ($agencyId > 0) { ?>
    تعذّر الاتصال بقاعدة ERP للوكالة<?php if ($agencyDbName !== '') { ?> (<code><?php echo htmlspecialchars($agencyDbName, ENT_QUOTES, 'UTF-8'); ?></code>)<?php } ?>.
    من صفحة <strong>الوكالات</strong> شغّل <strong>Provision ERP</strong> أو <strong>Re-provision ERP</strong> ثم أعد المحاولة.
    <?php if ($agenciesBackUrl !== '') { ?>
    <div class="mt-2"><a href="<?php echo htmlspecialchars($agenciesBackUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-right"></i> العودة للوكالات</a></div>
    <?php } ?>
    <?php } else { ?>
    شغّل إعداد قاعدة البيانات أولاً من <a href="<?php echo htmlspecialchars(control_rateb_erp_migrate_page_url(), ENT_QUOTES, 'UTF-8'); ?>">هنا</a>.
    <?php } ?>
</div>
<?php } else { ?>

<?php if ($flashOk !== '') { ?>
<div class="alert alert-success"><?php echo htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>
<?php if ($flashErr !== '') { ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>
<?php if ($focusCompanyId > 0 && !$focusCompanyKnown) { ?>
<div class="alert alert-warning">
    <?php if ($agencyId > 0) { ?>
    الشركة رقم <?php echo (int) $focusCompanyId; ?> غير موجودة في قاعدة ERP للوكالة
    <?php if ($agencyDbName !== '') { ?>(<code><?php echo htmlspecialchars($agencyDbName, ENT_QUOTES, 'UTF-8'); ?></code>)<?php } ?>.
    <?php if (!$agencyDbReady) { ?>تأكد من <strong>Provision ERP</strong> للوكالة ثم أعد فتح هذه الصفحة.<?php } else { ?>جرّب <strong>Re-provision ERP</strong> أو ترحيل قاعدة الوكالة.<?php } ?>
    <?php } else { ?>
    الشركة رقم <?php echo (int) $focusCompanyId; ?> غير موجودة في قاعدة ERP الحالية — تأكد أنك تدير فروع <strong>منصة rateb.sa</strong> وليس وكالة أخرى.
    <?php } ?>
</div>
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
    <?php if ($agencyId > 0) { ?>
        <?php if ($agenciesBackUrl !== '') { ?>
    <a href="<?php echo htmlspecialchars($agenciesBackUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-right"></i> العودة للوكالات
    </a>
        <?php } ?>
        <?php if ($agencyErpAdminUrl !== '') { ?>
    <a href="<?php echo htmlspecialchars($agencyErpAdminUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary" target="_blank" rel="noopener">
        <i class="fas fa-hospital"></i> فتح ERP الوكالة
    </a>
        <?php } ?>
    <?php } else { ?>
    <a href="<?php echo htmlspecialchars(control_rateb_erp_public_url('admin/companies'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary" target="_blank" rel="noopener">
        <i class="fas fa-building"></i> إدارة الشركات (ERP)
    </a>
    <a href="<?php echo htmlspecialchars(control_rateb_erp_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary">
        <i class="fas fa-hospital"></i> مركز ERP
    </a>
    <?php } ?>
</div>

<?php if ($companies === []) { ?>
<div class="alert alert-info">
    <?php if ($agencyId > 0) { ?>
    لا توجد شركة في قاعدة ERP للوكالة بعد. من صفحة <strong>الوكالات</strong> شغّل <strong>Provision ERP</strong> (أو Re-provision) ثم ارجع هنا لإنشاء الفروع.
    <?php } else { ?>
    لا توجد شركات مشتركة بعد. أضف شركة من ERP ثم ارجع هنا لمنحها فروعاً.
    <?php } ?>
</div>
<?php } ?>

<?php foreach ($companies as $company) {
    $cid = (int) ($company['id'] ?? 0);
    $branchCount = (int) ($company['branch_count'] ?? 0);
    $limitEff = (int) ($company['branch_limit_effective'] ?? 0);
    $limitSet = (int) ($company['branch_limit'] ?? 0);
    $canAdd = !empty($company['can_add_branch']);
    $branchList = control_rateb_erp_branch_list($cid, $branchListOpts);
    $branches = $branchList['items'];
    $branchListTotal = (int) ($branchList['total'] ?? 0);
    $branchListPage = (int) ($branchList['page'] ?? 1);
    $branchListPerPage = (int) ($branchList['per_page'] ?? 25);
    $branchListPages = (int) ($branchList['pages'] ?? 1);
    $listQueryBase = array_filter([
        'company_id' => $focusCompanyId > 0 ? $focusCompanyId : null,
        'agency_id' => $agencyId > 0 ? $agencyId : null,
        'platform' => $agencyId < 1 ? 1 : null,
        'q' => $branchListOpts['q'] !== '' ? $branchListOpts['q'] : null,
        'status' => $branchListOpts['status'] !== '' ? $branchListOpts['status'] : null,
        'branch_type' => $branchListOpts['branch_type'] !== '' ? $branchListOpts['branch_type'] : null,
        'archive' => $branchListOpts['archive'] !== '' ? $branchListOpts['archive'] : null,
        'sort' => ($branchListOpts['sort'] ?? 'name') !== 'name' ? $branchListOpts['sort'] : null,
        'dir' => ($branchListOpts['dir'] ?? 'asc') !== 'asc' ? $branchListOpts['dir'] : null,
        'per_page' => $branchListPerPage !== 25 ? $branchListPerPage : null,
    ], static fn ($v): bool => $v !== null && $v !== '');
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

    <?php
    $branchListOpts = $branchListOpts ?? control_rateb_erp_branch_list_opts_from_request($_GET);
    require __DIR__ . '/../../includes/control/rateb-erp-branch-list-section.php';
    ?>

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

<script>
(function () {
    var id = window.location.hash ? window.location.hash.replace(/^#/, '') : '';
    if (!id) {
        return;
    }
    var el = document.getElementById(id);
    if (el && typeof el.scrollIntoView === 'function') {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
})();
</script>

<?php endControlLayout(); ?>
