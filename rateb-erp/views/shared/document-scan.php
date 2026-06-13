<?php /** @var array<string, mixed> $doc */ ?>
<div class="text-center mb-4">
    <div class="rateb-badge-scan-wrap d-inline-block mb-3">
        <?php if (!empty($doc['qr_image_url'])) { ?>
        <img src="<?php echo Rateb\App\Core\View::escape((string) $doc['qr_image_url']); ?>" alt="" width="160" height="160" class="rateb-badge-scan-qr">
        <?php } ?>
    </div>
    <h4 class="mb-1"><?php echo Rateb\App\Core\View::escape((string) ($doc['title'] ?? '')); ?></h4>
    <?php if (!empty($doc['subtitle'])) { ?>
    <p class="text-muted mb-2"><?php echo Rateb\App\Core\View::escape((string) $doc['subtitle']); ?></p>
    <?php } ?>
    <p class="font-monospace small text-muted mb-0"><?php echo Rateb\App\Core\View::escape((string) ($doc['barcode'] ?? '')); ?></p>
</div>

<div class="rateb-card mb-3">
    <div class="rateb-card-body">
        <dl class="row mb-0 small">
            <dt class="col-5 text-muted"><?php echo __('document_type'); ?></dt>
            <dd class="col-7"><?php echo Rateb\App\Core\View::escape((string) ($doc['type_label'] ?? '')); ?></dd>
            <?php foreach (($doc['fields'] ?? []) as $field) { ?>
            <dt class="col-5 text-muted"><?php echo Rateb\App\Core\View::escape((string) ($field['label'] ?? '')); ?></dt>
            <dd class="col-7"><?php echo Rateb\App\Core\View::escape((string) ($field['value'] ?? '')); ?></dd>
            <?php } ?>
        </dl>
    </div>
</div>

<?php if (!empty($editUrl)) { ?>
    <?php if (!empty($loggedIn)) { ?>
    <a href="<?php echo Rateb\App\Core\View::escape((string) $editUrl); ?>" class="btn btn-primary w-100 mb-2">
        <i class="fas fa-arrow-up-right-from-square"></i> <?php echo __('scan_open_in_system'); ?>
    </a>
    <?php } else { ?>
    <a href="<?php echo Rateb\App\Core\View::escape((string) ($loginUrl ?? rateb_url('login'))); ?>" class="btn btn-primary w-100 mb-2">
        <i class="fas fa-right-to-bracket"></i> <?php echo __('scan_login_to_edit'); ?>
    </a>
    <p class="small text-muted text-center mb-0"><?php echo __('scan_login_hint'); ?></p>
    <?php } ?>
<?php } ?>
