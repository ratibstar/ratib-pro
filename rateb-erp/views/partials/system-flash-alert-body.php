<?php
declare(strict_types=1);

/** @var array<string, mixed> $alert */
$previewItems = is_array($alert['preview_items'] ?? null) ? $alert['preview_items'] : [];
$moreCount = (int) ($alert['more_count'] ?? 0);
if ($previewItems === [] && empty($alert['message'])) {
    return;
}
?>
<?php if ($previewItems !== []) { ?>
<ul class="rateb-system-flash-tickets">
    <li class="rateb-system-flash-tickets__head" aria-hidden="true">
        <span class="rateb-system-flash-tickets__no"><?php echo Rateb\App\Core\View::escape(__('ticket_no')); ?></span>
        <span class="rateb-system-flash-tickets__company"><?php echo Rateb\App\Core\View::escape(__('companies')); ?></span>
        <span class="rateb-system-flash-tickets__subject"><?php echo Rateb\App\Core\View::escape(__('subject')); ?></span>
    </li>
    <?php foreach ($previewItems as $row) {
        if (!is_array($row)) {
            continue;
        } ?>
    <li class="rateb-system-flash-tickets__item">
        <span class="rateb-system-flash-tickets__no"><?php echo Rateb\App\Core\View::escape((string) ($row['ticket_no'] ?? '')); ?></span>
        <span class="rateb-system-flash-tickets__company"><?php echo Rateb\App\Core\View::escape((string) ($row['company'] ?? '')); ?></span>
        <span class="rateb-system-flash-tickets__subject"><?php echo Rateb\App\Core\View::escape((string) ($row['subject'] ?? '')); ?></span>
    </li>
    <?php } ?>
    <?php if ($moreCount > 0) { ?>
    <li class="rateb-system-flash-tickets__more"><?php echo Rateb\App\Core\View::escape(__('support_ticket_flash_more', ['count' => (string) $moreCount])); ?></li>
    <?php } ?>
</ul>
<?php } elseif (!empty($alert['message'])) { ?>
<div class="rateb-system-flash-alert__message"><?php echo nl2br(Rateb\App\Core\View::escape((string) $alert['message'])); ?></div>
<?php } ?>
