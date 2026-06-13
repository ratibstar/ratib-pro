<?php /** @var array<int, array<string, mixed>> $notifications */ ?>
<div class="rateb-portal-page">
    <div class="container py-4">
        <div class="mb-4">
            <a href="<?php echo rateb_url('site/portal'); ?>" class="text-decoration-none small"><i class="fas fa-arrow-right ms-1"></i><?php echo __('portal_back'); ?></a>
            <h1 class="h3 mt-2"><?php echo __('notifications'); ?></h1>
            <p class="text-muted mb-0"><?php echo __('portal_notifications_hint'); ?></p>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($notifications === []) { ?>
                <div class="card shadow-sm">
                    <div class="card-body text-center text-muted py-5">
                        <i class="fas fa-bell-slash fa-2x mb-3 opacity-50"></i>
                        <p class="mb-0"><?php echo __('portal_no_notifications'); ?></p>
                    </div>
                </div>
                <?php } else { ?>
                <div class="list-group shadow-sm">
                    <?php foreach ($notifications as $item) {
                        $isRead = (int) ($item['is_read'] ?? 0) === 1;
                        ?>
                    <div class="list-group-item<?php echo $isRead ? '' : ' list-group-item-primary'; ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold"><?php echo Rateb\App\Core\View::escape((string) ($item['title'] ?? '')); ?></div>
                                <p class="mb-1 small"><?php echo Rateb\App\Core\View::escape((string) ($item['message'] ?? '')); ?></p>
                                <span class="text-muted small rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($item['created_at'] ?? '')); ?></span>
                            </div>
                            <?php if (!$isRead) { ?>
                            <span class="badge text-bg-primary"><?php echo __('portal_unread'); ?></span>
                            <?php } ?>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
