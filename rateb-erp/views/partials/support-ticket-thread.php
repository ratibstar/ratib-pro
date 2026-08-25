<?php
use Rateb\App\Core\View;

/** @var array<string, mixed>|null $item */
/** @var array{original: array<string, mixed>, replies: list<array<string, mixed>>}|null $conversation */
/** @var string $companyLabel */
$ticketId = is_array($item ?? null) ? (int) ($item['id'] ?? 0) : 0;
if ($ticketId < 1 || empty($conversation) || !is_array($conversation)) {
    return;
}
$original = is_array($conversation['original'] ?? null) ? $conversation['original'] : [];
$replies = is_array($conversation['replies'] ?? null) ? $conversation['replies'] : [];
?>
<div class="rateb-card mb-3 support-ticket-thread">
    <div class="rateb-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><?php echo View::escape(__('support_ticket_conversation')); ?></span>
        <?php if (!empty($companyLabel)) { ?>
        <span class="badge text-bg-secondary"><?php echo View::escape(__('companies')); ?>: <?php echo View::escape($companyLabel); ?></span>
        <?php } ?>
    </div>
    <div class="rateb-card-body support-ticket-thread__body">
        <div class="support-ticket-thread__msg support-ticket-thread__msg--client">
            <div class="support-ticket-thread__meta">
                <strong><?php echo View::escape(__('support_ticket_original_request')); ?></strong>
                <?php if (!empty($original['user_name'])) { ?>
                <span> — <?php echo View::escape((string) $original['user_name']); ?></span>
                <?php } ?>
                <?php if (!empty($original['created_at'])) { ?>
                <span class="text-muted small"><?php echo View::escape(View::formatDate($original['created_at'], 'datetime')); ?></span>
                <?php } ?>
            </div>
            <div class="support-ticket-thread__text"><?php echo nl2br(View::escape((string) ($original['body'] ?? ''))); ?></div>
        </div>
        <?php foreach ($replies as $reply) {
            $isStaff = !empty($reply['is_staff']);
            ?>
        <div class="support-ticket-thread__msg<?php echo $isStaff ? ' support-ticket-thread__msg--staff' : ' support-ticket-thread__msg--client'; ?>">
            <div class="support-ticket-thread__meta">
                <strong><?php echo View::escape($isStaff ? __('support_ticket_reply_staff') : __('support_ticket_reply_client')); ?></strong>
                <?php if (!empty($reply['user_name'])) { ?>
                <span> — <?php echo View::escape((string) $reply['user_name']); ?></span>
                <?php } ?>
                <?php if (!empty($reply['created_at'])) { ?>
                <span class="text-muted small"><?php echo View::escape(View::formatDate((string) $reply['created_at'], 'datetime')); ?></span>
                <?php } ?>
            </div>
            <div class="support-ticket-thread__text"><?php echo nl2br(View::escape((string) ($reply['body'] ?? ''))); ?></div>
        </div>
        <?php } ?>
    </div>
</div>
