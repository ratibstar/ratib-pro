<?php $service = $service ?? []; ?>
<section class="rateb-portal-section rateb-svc-section" data-service-track>
    <div class="container">
        <h1><?php echo __('request_tracking') ?: 'Request tracking'; ?> #<?php echo (int) ($service['id'] ?? 0); ?></h1>
        <p><strong><?php echo Rateb\App\Core\View::escape((string) ($service['title'] ?? '')); ?></strong>
            — <?php echo Rateb\App\Core\View::escape((string) ($service['status'] ?? '')); ?>
            / <?php echo Rateb\App\Core\View::escape((string) ($service['payment_status'] ?? '')); ?></p>

        <div class="rateb-portal-quick-actions">
            <a class="rateb-portal-btn rateb-portal-btn--ghost" href="<?php echo rateb_url('site/customer/services/book?service_id=' . (int) ($service['id'] ?? 0)); ?>"><?php echo __('book_appointment') ?: 'Book'; ?></a>
            <?php if ((string) ($service['payment_status'] ?? '') !== 'paid' && (float) ($service['amount'] ?? 0) > 0) { ?>
            <form class="rateb-portal-inline-form" method="post" action="<?php echo rateb_url('site/customer/services/pay'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
                <input type="hidden" name="service_id" value="<?php echo (int) ($service['id'] ?? 0); ?>">
                <button type="submit" class="rateb-portal-btn"><?php echo __('pay_online') ?: 'Pay online'; ?></button>
            </form>
            <?php } ?>
        </div>

        <h2><?php echo __('service_timeline') ?: 'Timeline'; ?></h2>
        <ol class="rateb-svc-timeline">
            <?php foreach (($timeline ?? []) as $ev) { ?>
            <li>
                <strong><?php echo Rateb\App\Core\View::escape((string) ($ev['title'] ?? '')); ?></strong>
                <span><?php echo Rateb\App\Core\View::escape((string) ($ev['created_at'] ?? '')); ?></span>
                <?php if (!empty($ev['body'])) { ?>
                <p><?php echo Rateb\App\Core\View::escape((string) $ev['body']); ?></p>
                <?php } ?>
            </li>
            <?php } ?>
        </ol>

        <h2><?php echo __('appointments') ?: 'Appointments'; ?></h2>
        <ul class="rateb-portal-list">
            <?php foreach (($appointments ?? []) as $a) { ?>
            <li><?php echo Rateb\App\Core\View::escape((string) ($a['title'] ?? '')); ?> — <?php echo Rateb\App\Core\View::escape((string) ($a['starts_at'] ?? '')); ?></li>
            <?php } ?>
        </ul>

        <h2><?php echo __('customer_messages') ?: 'Messages'; ?></h2>
        <form class="rateb-portal-form" method="post" action="<?php echo rateb_url('site/customer/services/message'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <input type="hidden" name="service_id" value="<?php echo (int) ($service['id'] ?? 0); ?>">
            <div class="rateb-portal-form__field">
                <textarea name="message" rows="3" required maxlength="4000"></textarea>
            </div>
            <button type="submit" class="rateb-portal-btn"><?php echo __('send') ?: 'Send'; ?></button>
        </form>
    </div>
</section>
