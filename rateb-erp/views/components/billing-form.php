<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $fields */
/** @var string $routePrefix */
/** @var string $csrf */
/** @var array<int, array<string, mixed>>|null $companies */
/** @var array<int, array<string, mixed>>|null $plans */
/** @var array<int, array<string, mixed>>|null $subscriptions */
$isEdit = is_array($item) && (int) ($item['id'] ?? 0) > 0;
$action = $isEdit ? rateb_url($routePrefix . '/' . (int) $item['id']) : rateb_url($routePrefix);
$companies = $companies ?? [];
$plans = $plans ?? [];
$subscriptions = $subscriptions ?? [];
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <?php if (empty($companies)) { ?>
        <div class="alert alert-warning"><?php echo __('billing_no_companies'); ?></div>
        <?php } ?>
        <form method="post" action="<?php echo $action; ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3">
                <?php foreach ($fields as $field) {
                    $name = $field['name'];
                    $type = $field['type'] ?? 'text';
                    $value = is_array($item) ? ($item[$name] ?? '') : '';
                    ?>
                <div class="col-md-6">
                    <label class="form-label rateb-form-label" for="f_<?php echo Rateb\App\Core\View::escape($name); ?>">
                        <?php echo Rateb\App\Core\View::escape(rateb_label((string) ($field['label'] ?? $name))); ?>
                    </label>
                    <?php if ($type === 'company_select') { ?>
                    <select class="form-select rateb-form-control" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>" required>
                        <option value=""><?php echo __('select_company'); ?></option>
                        <?php foreach ($companies as $company) { ?>
                        <option value="<?php echo (int) $company['id']; ?>"<?php echo (string) $value === (string) $company['id'] ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape($company['name']); ?>
                        </option>
                        <?php } ?>
                    </select>
                    <?php } elseif ($type === 'plan_select') { ?>
                    <select class="form-select rateb-form-control" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>" required>
                        <option value=""><?php echo __('select_plan'); ?></option>
                        <?php foreach ($plans as $plan) { ?>
                        <option value="<?php echo (int) $plan['id']; ?>"<?php echo (string) $value === (string) $plan['id'] ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape($plan['name']); ?>
                        </option>
                        <?php } ?>
                    </select>
                    <?php } elseif ($type === 'subscription_select') { ?>
                    <select class="form-select rateb-form-control" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>">
                        <option value=""><?php echo __('optional'); ?></option>
                        <?php foreach ($subscriptions as $sub) { ?>
                        <option value="<?php echo (int) $sub['id']; ?>"<?php echo (string) $value === (string) $sub['id'] ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape($sub['label'] ?? ('#' . $sub['id'])); ?>
                        </option>
                        <?php } ?>
                    </select>
                    <?php } elseif ($type === 'select') { ?>
                    <select class="form-select rateb-form-control" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>">
                        <?php foreach (($field['options'] ?? []) as $opt) { ?>
                        <option value="<?php echo Rateb\App\Core\View::escape($opt); ?>"<?php echo (string) $value === (string) $opt ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape(__($opt)); ?></option>
                        <?php } ?>
                    </select>
                    <?php } else { ?>
                    <input class="form-control rateb-form-control" type="<?php echo Rateb\App\Core\View::escape($type); ?>" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>" value="<?php echo Rateb\App\Core\View::escape($value); ?>"<?php echo in_array($name, ['company_id', 'plan_id', 'amount', 'invoice_no', 'issued_at'], true) ? ' required' : ''; ?>>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"<?php echo empty($companies) ? ' disabled' : ''; ?>><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
