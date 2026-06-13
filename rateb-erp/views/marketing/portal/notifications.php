<?php /** @var array<int, array<string, mixed>> $notifications */ ?>
<div class="rateb-portal-page">
    <div class="container py-4">
        <a href="<?php echo rateb_url('site/portal'); ?>" class="text-decoration-none small text-muted d-inline-block mb-3">
            <i class="fas fa-arrow-right ms-1"></i><?php echo __('portal_back'); ?>
        </a>
        <div class="rateb-portal-card">
            <div class="rateb-portal-card-head"><?php echo __('notifications'); ?></div>
            <div class="rateb-portal-card-body">
                <?php if ($notifications === []) { ?>
                <p class="text-muted text-center py-4 mb-0">
                    <i class="fas fa-bell-slash fa-2x d-block mb-2 opacity-50"></i>
                    <?php echo __('portal_no_notifications'); ?>
                </p>
                <?php } else { ?>
                <div class="rateb-portal-kv">
                    <?php foreach ($notifications as $item) {
                        $isRead = (int) ($item['is_read'] ?? 0) === 1;
                        ?>
                    <div class="rateb-portal-kv-row flex-column align-items-stretch<?php echo $isRead ? '' : ' border-primary'; ?>" style="padding: 1rem 0;">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                            <strong><?php echo Rateb\App\Core\View::escape((string) ($item['title'] ?? '')); ?></strong>
                            <?php if (!$isRead) { ?>
                            <span class="badge text-bg-primary"><?php echo __('portal_unread'); ?></span>
                            <?php } ?>
                        </div>
                        <p class="mb-1 small opacity-90"><?php echo Rateb\App\Core\View::escape((string) ($item['message'] ?? '')); ?></p>
                        <span class="small text-muted rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($item['created_at'] ?? '')); ?></span>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
