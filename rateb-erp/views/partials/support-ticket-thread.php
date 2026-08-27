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
$status = is_array($item) ? (string) ($item['status'] ?? '') : '';
$priority = is_array($item) ? (string) ($item['priority'] ?? '') : '';
$replyCount = count($replies);
$maxReplyId = 0;
foreach ($replies as $replyRow) {
    $rid = (int) ($replyRow['id'] ?? 0);
    if ($rid > $maxReplyId) {
        $maxReplyId = $rid;
    }
}
// Must match SupportTicketReplyService::liveSnapshot activity_token format.
$activityToken = $ticketId . ':' . $maxReplyId . ':' . $status . ':' . $priority . ':' . $replyCount;

/** @var list<array<string, mixed>> $threadMessages */
$threadMessages = [];
$threadMessages[] = [
    'kind' => 'original',
    'is_staff' => false,
    'body' => (string) ($original['body'] ?? ''),
    'user_name' => (string) ($original['user_name'] ?? ''),
    'created_at' => (string) ($original['created_at'] ?? ''),
    'reply_id' => 0,
];
foreach ($replies as $reply) {
    $threadMessages[] = [
        'kind' => 'reply',
        'is_staff' => !empty($reply['is_staff']),
        'body' => (string) ($reply['body'] ?? ''),
        'user_name' => (string) ($reply['user_name'] ?? ''),
        'created_at' => (string) ($reply['created_at'] ?? ''),
        'reply_id' => (int) ($reply['id'] ?? 0),
    ];
}
$visibleLimit = 3;
$totalMessages = count($threadMessages);
$hiddenCount = max(0, $totalMessages - $visibleLimit);
$startVisible = $hiddenCount;
?>
<div class="rateb-card mb-3 support-ticket-thread"
     data-rateb-ticket-live="1"
     data-ticket-id="<?php echo View::escape((string) $ticketId); ?>"
     data-activity-token="<?php echo View::escape($activityToken); ?>"
     data-status="<?php echo View::escape($status); ?>"
     data-priority="<?php echo View::escape($priority); ?>"
     data-thread-visible-limit="<?php echo (int) $visibleLimit; ?>"
     data-label-more-tpl="<?php echo View::escape(__('support_ticket_thread_show_more', ['count' => ':count'])); ?>"
     data-label-less="<?php echo View::escape(__('support_ticket_thread_show_less')); ?>">
    <div class="rateb-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><?php echo View::escape(__('support_ticket_conversation')); ?></span>
        <span class="d-flex align-items-center gap-2">
            <span class="badge text-bg-success support-ticket-live-badge d-none" data-rateb-live-badge="1">
                <?php echo View::escape(__('support_ticket_live_updated')); ?>
            </span>
            <?php if (!empty($companyLabel)) { ?>
            <span class="badge text-bg-secondary"><?php echo View::escape(__('companies')); ?>: <?php echo View::escape($companyLabel); ?></span>
            <?php } ?>
        </span>
    </div>
    <div class="rateb-card-body support-ticket-thread__body" data-rateb-ticket-thread-body="1">
        <?php if ($hiddenCount > 0) { ?>
        <div class="support-ticket-thread__more-wrap" data-thread-more-wrap="1">
            <button type="button" class="btn btn-sm btn-outline-secondary support-ticket-thread__more-btn" data-thread-more-btn="1"
                    data-label-more="<?php echo View::escape(__('support_ticket_thread_show_more', ['count' => $hiddenCount])); ?>"
                    data-label-less="<?php echo View::escape(__('support_ticket_thread_show_less')); ?>">
                <i class="fas fa-chevron-up ms-1"></i>
                <span data-thread-more-label="1"><?php echo View::escape(__('support_ticket_thread_show_more', ['count' => $hiddenCount])); ?></span>
            </button>
        </div>
        <?php } ?>
        <?php
        $prevStaff = null;
        foreach ($threadMessages as $idx => $msg) {
            $isStaff = !empty($msg['is_staff']);
            $isOriginal = ($msg['kind'] ?? '') === 'original';
            $isOlder = $idx < $startVisible;
            $isContinued = $prevStaff !== null && $prevStaff === $isStaff && !$isOriginal;
            $displayBody = (string) ($msg['body'] ?? '');
            $displayBody = trim((string) preg_replace(
                '/\n*\s*\[rateb_(?:agency|platform)_reply:\d+:\d+\]\s*$/u',
                '',
                $displayBody
            ));
            $isArabic = (bool) preg_match('/\p{Arabic}/u', $displayBody);
            $langClass = $isArabic ? ' support-ticket-thread__msg--lang-ar' : ' support-ticket-thread__msg--lang-en';
            $msgClass = 'support-ticket-thread__msg'
                . ($isStaff ? ' support-ticket-thread__msg--staff' : ' support-ticket-thread__msg--client')
                . ($isContinued ? ' support-ticket-thread__msg--continued' : '')
                . ($isOlder ? ' support-ticket-thread__msg--older is-collapsed' : '')
                . $langClass;
            $title = $isOriginal
                ? __('support_ticket_original_request')
                : ($isStaff ? __('support_ticket_reply_staff') : __('support_ticket_reply_client'));
            ?>
        <div class="<?php echo View::escape($msgClass); ?>"
             data-thread-msg="1"
             data-msg-index="<?php echo (int) $idx; ?>"
             data-is-staff="<?php echo $isStaff ? '1' : '0'; ?>"
             data-msg-lang="<?php echo $isArabic ? 'ar' : 'en'; ?>"
             dir="<?php echo $isArabic ? 'rtl' : 'ltr'; ?>"
             <?php if ((int) ($msg['reply_id'] ?? 0) > 0) { ?>data-reply-id="<?php echo View::escape((string) ((int) $msg['reply_id'])); ?>"<?php } ?>
             <?php if ($isOlder) { ?>hidden<?php } ?>>
            <div class="support-ticket-thread__meta">
                <strong><?php echo View::escape($title); ?></strong>
                <?php if (!empty($msg['user_name'])) { ?>
                <span><?php echo View::escape((string) $msg['user_name']); ?></span>
                <?php } ?>
                <?php if (!empty($msg['created_at'])) { ?>
                <span class="text-muted support-ticket-thread__time"><?php echo View::escape(View::formatDate((string) $msg['created_at'], 'datetime')); ?></span>
                <?php } ?>
            </div>
            <div class="support-ticket-thread__text"><?php echo nl2br(View::escape($displayBody)); ?></div>
        </div>
        <?php
            $prevStaff = $isStaff;
        } ?>
    </div>
</div>
