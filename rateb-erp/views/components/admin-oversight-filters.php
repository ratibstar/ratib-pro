<?php
/** @var array<int, array<string, mixed>> $companies */
/** @var array{company_id: int, status: string, date_from: string, date_to: string} $filters */
/** @var list<array{value: string, label: string}> $statusOptions */
/** @var string $formAction */
$filters = $filters ?? ['company_id' => 0, 'status' => '', 'date_from' => '', 'date_to' => ''];
$companies = $companies ?? [];
$statusOptions = $statusOptions ?? [];
$formAction = $formAction ?? '';
?>
<div class="col-12">
    <form method="get" action="<?php echo Rateb\App\Core\View::escape($formAction); ?>" class="rateb-card">
        <div class="rateb-card-body">
            <div class="row g-2 align-items-end">
                <?php if ($companies !== []) { ?>
                <div class="col-md-3">
                    <label class="form-label rateb-form-label"><?php echo __('companies'); ?></label>
                    <select class="form-select" name="company_id">
                        <option value=""><?php echo __('all_companies'); ?></option>
                        <?php foreach ($companies as $c) { ?>
                        <option value="<?php echo (int) $c['id']; ?>"<?php echo (int) $filters['company_id'] === (int) $c['id'] ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape($c['name'] ?? ''); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <?php } ?>
                <?php if ($statusOptions !== []) { ?>
                <div class="col-md-3">
                    <label class="form-label rateb-form-label"><?php echo __('status'); ?></label>
                    <select class="form-select" name="status">
                        <option value=""><?php echo __('all_statuses'); ?></option>
                        <?php foreach ($statusOptions as $opt) { ?>
                        <option value="<?php echo Rateb\App\Core\View::escape($opt['value']); ?>"<?php echo (string) $filters['status'] === (string) $opt['value'] ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape($opt['label']); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <?php } ?>
                <div class="col-md-2">
                    <label class="form-label rateb-form-label"><?php echo __('date_from'); ?></label>
                    <input class="form-control" type="date" name="date_from" dir="ltr" lang="en" autocomplete="off" value="<?php echo Rateb\App\Core\View::escape($filters['date_from']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label rateb-form-label"><?php echo __('date_to'); ?></label>
                    <input class="form-control" type="date" name="date_to" dir="ltr" lang="en" autocomplete="off" value="<?php echo Rateb\App\Core\View::escape($filters['date_to']); ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><?php echo __('filter'); ?></button>
                    <a href="<?php echo Rateb\App\Core\View::escape($formAction); ?>" class="btn btn-outline-secondary"><?php echo __('reset'); ?></a>
                </div>
            </div>
        </div>
    </form>
</div>
