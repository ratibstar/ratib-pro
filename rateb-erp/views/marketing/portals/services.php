<section class="rateb-portal-section rateb-svc-section" data-online-services>
    <div class="container">
        <h1><?php echo Rateb\App\Core\View::escape($title ?? 'Online Services'); ?></h1>
        <p class="rateb-portal-lead"><?php echo __('online_services_lead') ?: 'Request recruitment, domestic workers, workforce packages, and track status online.'; ?></p>
        <div class="rateb-portal-quick-actions">
            <a class="rateb-portal-btn" href="<?php echo rateb_url('site/customer/services/new'); ?>"><?php echo __('new_service_request') ?: 'New request'; ?></a>
            <a class="rateb-portal-btn rateb-portal-btn--ghost" href="<?php echo rateb_url('site/customer/services/book'); ?>"><?php echo __('book_appointment') ?: 'Book appointment'; ?></a>
        </div>

        <h2><?php echo __('packages') ?: 'Packages'; ?></h2>
        <div class="rateb-svc-packages">
            <?php foreach (($packages ?? []) as $code => $pkg) { ?>
            <a class="rateb-svc-package" href="<?php echo rateb_url('site/customer/services/new?package=' . rawurlencode((string) $code)); ?>">
                <strong><?php echo Rateb\App\Core\View::escape((string) ($pkg['label'] ?? $code)); ?></strong>
                <span><?php echo number_format((float) ($pkg['amount'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape((string) ($pkg['currency'] ?? 'SAR')); ?></span>
            </a>
            <?php } ?>
        </div>

        <h2><?php echo __('my_service_requests') ?: 'My service requests'; ?></h2>
        <table class="rateb-portal-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?php echo __('title') ?: 'Title'; ?></th>
                    <th><?php echo __('type') ?: 'Type'; ?></th>
                    <th><?php echo __('status') ?: 'Status'; ?></th>
                    <th><?php echo __('payment') ?: 'Payment'; ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($services ?? []) as $row) { ?>
                <tr>
                    <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['title'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['service_type'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['status'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['payment_status'] ?? '')); ?></td>
                    <td><a href="<?php echo rateb_url('site/customer/services/track?id=' . (int) ($row['id'] ?? 0)); ?>"><?php echo __('track') ?: 'Track'; ?></a></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php if (($page ?? 1) > 1) { ?>
        <p><a href="<?php echo rateb_url('site/customer/services?page=' . ((int) $page - 1)); ?>"><?php echo __('prev') ?: 'Previous'; ?></a></p>
        <?php } ?>
        <p><a href="<?php echo rateb_url('site/customer/services?page=' . ((int) ($page ?? 1) + 1)); ?>"><?php echo __('next') ?: 'Next'; ?></a></p>
    </div>
</section>
