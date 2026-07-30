<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $rows */
/** @var array{title:string,icon:string,tone:string,desc:string} $sectionMeta */
$rows = $rows ?? [];
$sectionMeta = $sectionMeta ?? ['title' => 'agent_apps_payments', 'icon' => 'fa-credit-card', 'tone' => 'slate', 'desc' => ''];
$tone = (string) ($sectionMeta['tone'] ?? 'slate');
?>
<div class="raa" data-raa="payments">
    <header class="raa-hero raa-hero--compact">
        <div class="raa-hero__copy">
            <p class="raa-hero__eyebrow"><?php echo Rateb\App\Core\View::escape(__('agent_apps_section')); ?></p>
            <h1 class="raa-hero__title">
                <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($sectionMeta['icon'] ?? 'fa-credit-card')); ?>"></i>
                <?php echo Rateb\App\Core\View::escape(__((string) $sectionMeta['title'])); ?>
            </h1>
            <p class="raa-hero__lead"><?php echo Rateb\App\Core\View::escape(__('agent_apps_payments_matrix_intro')); ?></p>
        </div>
        <a class="raa-hero__cta raa-hero__cta--ghost" href="<?php echo rateb_url('admin/agent-apps'); ?>" data-rateb-href="<?php echo rateb_url('admin/agent-apps'); ?>" data-rateb-soft-nav="1">
            <i class="fas fa-arrow-right"></i>
            <?php echo Rateb\App\Core\View::escape(__('agent_apps_back_dashboard')); ?>
        </a>
    </header>

    <div class="rateb-card" data-tone="<?php echo Rateb\App\Core\View::escape($tone); ?>">
        <div class="rateb-card-body table-responsive p-0">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th><?php echo Rateb\App\Core\View::escape(__('company')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('mobile_apps_app_name')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('status')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('mobile_apps_feature_payroll')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('mobile_apps_feature_payslips')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('mobile_apps_feature_payments')); ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []) { ?>
                <tr>
                    <td colspan="7" class="text-muted text-center py-4">
                        <?php echo Rateb\App\Core\View::escape(__('agent_apps_list_empty')); ?>
                    </td>
                </tr>
                <?php } ?>
                <?php foreach ($rows as $row) {
                    $cid = (int) ($row['company_id'] ?? 0);
                    $active = !empty($row['mobile_active']);
                    ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['company_name'] ?? '—')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['app_name'] ?? '—')); ?></td>
                    <td>
                        <span class="badge <?php echo $active ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                            <?php echo Rateb\App\Core\View::escape($active ? __('active') : __('inactive')); ?>
                        </span>
                    </td>
                    <td><?php echo !empty($row['payroll']) ? '✓' : '—'; ?></td>
                    <td><?php echo !empty($row['payslips']) ? '✓' : '—'; ?></td>
                    <td><?php echo !empty($row['payments']) ? '✓' : '—'; ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?php echo rateb_url('admin/mobile-apps/' . $cid); ?>"
                           data-rateb-href="<?php echo rateb_url('admin/mobile-apps/' . $cid); ?>"
                           data-rateb-soft-nav="1">
                            <?php echo Rateb\App\Core\View::escape(__('edit')); ?>
                        </a>
                    </td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    $paymentMethods = $paymentMethods ?? [];
    $paymentCompanyId = (int) ($paymentCompanyId ?? ($defaultCompanyId ?? 0));
    $companies = $companies ?? [];
    $canManage = !empty($canManage);
    $csrf = (string) ($csrf ?? '');
    if ($canManage && $paymentMethods !== []) {
    ?>
    <div class="rateb-card mt-3">
        <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape(__('agent_apps_payment_methods_title')); ?></div>
        <div class="rateb-card-body">
            <p class="small text-muted"><?php echo Rateb\App\Core\View::escape(__('agent_apps_payment_methods_intro')); ?></p>
            <form method="post" action="<?php echo Rateb\App\Core\View::escape((string) ($savePaymentsUrl ?? '')); ?>">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('company')); ?></label>
                        <select name="company_id" class="form-select" id="raa_pay_company">
                            <?php foreach ($companies as $c) { ?>
                            <option value="<?php echo (int) $c['id']; ?>"<?php echo $paymentCompanyId === (int) $c['id'] ? ' selected' : ''; ?>>
                                <?php echo Rateb\App\Core\View::escape((string) $c['name']); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <a class="btn btn-outline-secondary btn-sm" href="#" id="raa_pay_reload"><?php echo Rateb\App\Core\View::escape(__('filter')); ?></a>
                    </div>
                </div>
                <script>
                (function(){
                    var a=document.getElementById('raa_pay_reload'), s=document.getElementById('raa_pay_company');
                    if(a&&s){a.addEventListener('click',function(e){e.preventDefault();location.href=<?php echo json_encode(rateb_url('admin/agent-apps/payments')); ?>+'?company_id='+encodeURIComponent(s.value);});}
                })();
                </script>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th><?php echo Rateb\App\Core\View::escape(__('agent_apps_payment_code')); ?></th>
                            <th><?php echo Rateb\App\Core\View::escape(__('agent_apps_title_ar')); ?></th>
                            <th><?php echo Rateb\App\Core\View::escape(__('agent_apps_title_en')); ?></th>
                            <th><?php echo Rateb\App\Core\View::escape(__('active')); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($paymentMethods as $m) {
                            $code = (string) ($m['code'] ?? '');
                            ?>
                        <tr>
                            <td class="rateb-ltr-num"><code><?php echo Rateb\App\Core\View::escape($code); ?></code>
                                <input type="hidden" name="methods[<?php echo Rateb\App\Core\View::escape($code); ?>][code]" value="<?php echo Rateb\App\Core\View::escape($code); ?>">
                            </td>
                            <td><input class="form-control form-control-sm" name="methods[<?php echo Rateb\App\Core\View::escape($code); ?>][label_ar]" value="<?php echo Rateb\App\Core\View::escape((string) ($m['label_ar'] ?? '')); ?>"></td>
                            <td><input class="form-control form-control-sm" name="methods[<?php echo Rateb\App\Core\View::escape($code); ?>][label_en]" value="<?php echo Rateb\App\Core\View::escape((string) ($m['label_en'] ?? '')); ?>"></td>
                            <td>
                                <input type="hidden" name="methods[<?php echo Rateb\App\Core\View::escape($code); ?>][enabled]" value="0">
                                <input class="form-check-input" type="checkbox" name="methods[<?php echo Rateb\App\Core\View::escape($code); ?>][enabled]" value="1"<?php echo !empty($m['enabled']) ? ' checked' : ''; ?>>
                            </td>
                        </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo Rateb\App\Core\View::escape(__('save')); ?></button>
            </form>
        </div>
    </div>
    <?php } ?>
</div>
