<?php
/** @var array<string, mixed>|null $item */
/** @var array<string, array<int, array{value:string|int,label:string}>> $lookups */
$routePrefix = $routePrefix ?? rateb_app_route('supplier-comms');
$item = $item ?? [];
$supplierOptions = $lookups['supplier_id'] ?? [];
$channelOptions = $lookups['channel'] ?? [];
$isEdit = !empty($item['id']);
?>
<?php if (!empty($moduleCss)) { ?>
<link href="<?php echo Rateb\App\Core\View::escape($moduleCss); ?>" rel="stylesheet">
<?php } ?>

<div class="rateb-sc-page">
    <div class="rateb-sc-page-header">
        <div>
            <nav class="rateb-sc-breadcrumb" aria-label="breadcrumb">
                <a href="<?php echo rateb_app_url('dashboard'); ?>"><?php echo __('dashboard'); ?></a>
                <span class="mx-1">/</span>
                <a href="<?php echo rateb_app_url('suppliers'); ?>"><?php echo __('suppliers'); ?></a>
                <span class="mx-1">/</span>
                <a href="<?php echo rateb_app_url('supplier-comms'); ?>"><?php echo __('supplier_comms'); ?></a>
                <span class="mx-1">/</span>
                <span><?php echo $isEdit ? __('edit') : __('create'); ?></span>
            </nav>
            <h2 class="h4 mb-0"><?php echo Rateb\App\Core\View::escape($title ?? __('supplier_comms')); ?></h2>
        </div>
    </div>

    <div class="rateb-sc-card">
        <div class="rateb-sc-card-header">
            <span><i class="fas fa-edit text-primary"></i> <?php echo $isEdit ? __('edit') : __('create'); ?> <?php echo __('supplier_comms'); ?></span>
        </div>
        <div class="rateb-sc-card-body">
            <form method="post" action="<?php echo $isEdit ? rateb_url($routePrefix . '/' . (int) $item['id']) : rateb_app_url('supplier-comms'); ?>" class="rateb-sc-form-grid">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label rateb-form-label"><?php echo __('suppliers'); ?></label>
                        <select class="form-select rateb-form-control" name="supplier_id" required>
                            <option value=""><?php echo __('select'); ?>…</option>
                            <?php foreach ($supplierOptions as $opt) { ?>
                            <option value="<?php echo Rateb\App\Core\View::escape((string) $opt['value']); ?>"<?php echo (string) ($item['supplier_id'] ?? '') === (string) $opt['value'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($opt['label']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label rateb-form-label"><?php echo __('comm_channel'); ?></label>
                        <select class="form-select rateb-form-control" name="channel" required>
                            <?php foreach ($channelOptions as $opt) { ?>
                            <option value="<?php echo Rateb\App\Core\View::escape($opt['value']); ?>"<?php echo (string) ($item['channel'] ?? 'email') === (string) $opt['value'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($opt['label']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label rateb-form-label"><?php echo __('subject'); ?></label>
                        <input class="form-control rateb-form-control" type="text" name="subject" required maxlength="255" value="<?php echo Rateb\App\Core\View::escape((string) ($item['subject'] ?? '')); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label rateb-form-label"><?php echo __('notes'); ?></label>
                        <textarea class="form-control rateb-form-control" name="body" rows="5"><?php echo Rateb\App\Core\View::escape((string) ($item['body'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="rateb-sc-form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo __('save'); ?></button>
                    <a href="<?php echo rateb_app_url('supplier-comms'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
                </div>
            </form>
        </div>
    </div>
</div>
