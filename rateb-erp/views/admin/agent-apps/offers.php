<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $rows */
/** @var list<array{id:int,name:string}> $companies */
/** @var array<string,mixed>|null $editRow */
$rows = $rows ?? [];
$companies = $companies ?? [];
$editRow = is_array($editRow ?? null) ? $editRow : null;
$canManage = !empty($canManage);
$csrf = (string) ($csrf ?? '');
$companyFilter = (int) ($companyFilter ?? 0);
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
$tone = (string) (($sectionMeta['tone'] ?? 'teal'));
$formCompany = (int) ($editRow['company_id'] ?? ($companyFilter > 0 ? $companyFilter : $defaultCompanyId));
?>
<div class="raa" data-raa="offers">
    <header class="raa-hero raa-hero--compact">
        <div class="raa-hero__copy">
            <p class="raa-hero__eyebrow"><?php echo Rateb\App\Core\View::escape(__('agent_apps_section')); ?></p>
            <h1 class="raa-hero__title">
                <i class="fas fa-image"></i>
                <?php echo Rateb\App\Core\View::escape(__('agent_apps_offers')); ?>
            </h1>
            <p class="raa-hero__lead"><?php echo Rateb\App\Core\View::escape(__('agent_apps_offers_manage_intro')); ?></p>
        </div>
        <a class="raa-hero__cta raa-hero__cta--ghost" href="<?php echo rateb_url('admin/agent-apps'); ?>" data-rateb-href="<?php echo rateb_url('admin/agent-apps'); ?>" data-rateb-soft-nav="1">
            <i class="fas fa-arrow-right"></i>
            <?php echo Rateb\App\Core\View::escape(__('agent_apps_back_dashboard')); ?>
        </a>
    </header>

    <?php if (count($companies) > 1) { ?>
    <form method="get" class="rateb-card mb-3 p-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small"><?php echo Rateb\App\Core\View::escape(__('company')); ?></label>
                <select name="company_id" class="form-select form-select-sm">
                    <option value="0"><?php echo Rateb\App\Core\View::escape(__('all')); ?></option>
                    <?php foreach ($companies as $c) { ?>
                    <option value="<?php echo (int) $c['id']; ?>"<?php echo $companyFilter === (int) $c['id'] ? ' selected' : ''; ?>>
                        <?php echo Rateb\App\Core\View::escape((string) $c['name']); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary"><?php echo Rateb\App\Core\View::escape(__('filter')); ?></button>
            </div>
        </div>
    </form>
    <?php } ?>

    <?php if ($canManage) { ?>
    <div class="rateb-card mb-3" data-tone="<?php echo Rateb\App\Core\View::escape($tone); ?>">
        <div class="rateb-card-header">
            <?php echo Rateb\App\Core\View::escape($editRow ? __('edit') : __('agent_apps_offer_add')); ?>
        </div>
        <div class="rateb-card-body">
            <form method="post" action="<?php echo Rateb\App\Core\View::escape((string) ($saveUrl ?? '')); ?>">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <input type="hidden" name="id" value="<?php echo (int) ($editRow['id'] ?? 0); ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('company')); ?></label>
                        <select name="company_id" class="form-select" required>
                            <?php foreach ($companies as $c) { ?>
                            <option value="<?php echo (int) $c['id']; ?>"<?php echo $formCompany === (int) $c['id'] ? ' selected' : ''; ?>>
                                <?php echo Rateb\App\Core\View::escape((string) $c['name']); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('agent_apps_discount_label')); ?></label>
                        <input class="form-control" name="discount_label" value="<?php echo Rateb\App\Core\View::escape((string) ($editRow['discount_label'] ?? '')); ?>" placeholder="20%">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('agent_apps_sort_order')); ?></label>
                        <input type="number" class="form-control" name="sort_order" value="<?php echo (int) ($editRow['sort_order'] ?? 0); ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="offer_active"
                                <?php echo !isset($editRow['is_active']) || !empty($editRow['is_active']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="offer_active"><?php echo Rateb\App\Core\View::escape(__('active')); ?></label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('agent_apps_title_ar')); ?></label>
                        <input class="form-control" name="title_ar" value="<?php echo Rateb\App\Core\View::escape((string) ($editRow['title_ar'] ?? '')); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('agent_apps_title_en')); ?></label>
                        <input class="form-control" name="title_en" value="<?php echo Rateb\App\Core\View::escape((string) ($editRow['title_en'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('agent_apps_body_ar')); ?></label>
                        <textarea class="form-control" name="body_ar" rows="4"><?php echo Rateb\App\Core\View::escape((string) ($editRow['body_ar'] ?? '')); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('agent_apps_body_en')); ?></label>
                        <textarea class="form-control" name="body_en" rows="4"><?php echo Rateb\App\Core\View::escape((string) ($editRow['body_en'] ?? '')); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('agent_apps_image_path')); ?></label>
                        <input class="form-control" name="image_path" value="<?php echo Rateb\App\Core\View::escape((string) ($editRow['image_path'] ?? '')); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('agent_apps_starts_at')); ?></label>
                        <input type="date" class="form-control" name="starts_at" value="<?php echo Rateb\App\Core\View::escape((string) ($editRow['starts_at'] ?? '')); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('agent_apps_ends_at')); ?></label>
                        <input type="date" class="form-control" name="ends_at" value="<?php echo Rateb\App\Core\View::escape((string) ($editRow['ends_at'] ?? '')); ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?php echo Rateb\App\Core\View::escape(__('save')); ?></button>
                        <?php if ($editRow) { ?>
                        <a class="btn btn-outline-secondary" href="<?php echo rateb_url('admin/agent-apps/offers'); ?>"><?php echo Rateb\App\Core\View::escape(__('cancel')); ?></a>
                        <?php } ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php } ?>

    <div class="rateb-card" data-tone="<?php echo Rateb\App\Core\View::escape($tone); ?>">
        <div class="rateb-card-body table-responsive p-0">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th><?php echo Rateb\App\Core\View::escape(__('company')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('agent_apps_title_ar')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('agent_apps_discount_label')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('agent_apps_starts_at')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('agent_apps_ends_at')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('status')); ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []) { ?>
                <tr><td colspan="7" class="text-muted text-center py-4"><?php echo Rateb\App\Core\View::escape(__('agent_apps_list_empty')); ?></td></tr>
                <?php } ?>
                <?php foreach ($rows as $row) {
                    $id = (int) ($row['id'] ?? 0);
                    ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['company_name'] ?? '—')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['title_ar'] ?: ($row['title_en'] ?? ''))); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['discount_label'] ?? '—')); ?></td>
                    <td class="rateb-ltr-num small"><?php echo Rateb\App\Core\View::escape((string) ($row['starts_at'] ?? '—')); ?></td>
                    <td class="rateb-ltr-num small"><?php echo Rateb\App\Core\View::escape((string) ($row['ends_at'] ?? '—')); ?></td>
                    <td>
                        <span class="badge <?php echo !empty($row['is_active']) ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                            <?php echo Rateb\App\Core\View::escape(!empty($row['is_active']) ? __('active') : __('inactive')); ?>
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($canManage) { ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_url('admin/agent-apps/offers?edit=' . $id . ($companyFilter ? '&company_id=' . $companyFilter : '')); ?>">
                            <?php echo Rateb\App\Core\View::escape(__('edit')); ?>
                        </a>
                        <form method="post" action="<?php echo Rateb\App\Core\View::escape((string) ($deleteUrl ?? '')); ?>" class="d-inline" onsubmit="return confirm(<?php echo json_encode(__('confirm_delete'), JSON_UNESCAPED_UNICODE); ?>);">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo Rateb\App\Core\View::escape(__('delete')); ?></button>
                        </form>
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
