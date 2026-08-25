<?php
use Rateb\App\Core\View;

$item = $item ?? null;
$isEdit = is_array($item) && (int) ($item['id'] ?? 0) > 0;
$ticketId = $isEdit ? (int) $item['id'] : 0;

if ($isEdit && !empty($conversation)) {
    View::partial('support-ticket-thread', get_defined_vars());
}

View::partial('crud-form', get_defined_vars());

if ($isEdit && !empty($canReply) && !empty($replyAction)) { ?>
<div class="rateb-card mt-3 support-ticket-reply-box">
    <div class="rateb-card-header"><?php echo View::escape(__('support_ticket_reply_heading')); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo View::escape((string) $replyAction); ?>">
            <input type="hidden" name="_csrf" value="<?php echo View::escape($csrf ?? ''); ?>">
            <div class="mb-3">
                <label class="form-label rateb-form-label" for="support_ticket_reply_body"><?php echo View::escape(__('support_ticket_reply_label')); ?></label>
                <textarea class="form-control rateb-form-control" id="support_ticket_reply_body" name="reply_body" rows="4" required placeholder="<?php echo View::escape(__('support_ticket_reply_placeholder')); ?>"></textarea>
                <div class="form-text"><?php echo View::escape(__('support_ticket_reply_notify_hint')); ?></div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane ms-1"></i>
                <?php echo View::escape(__('support_ticket_reply_submit')); ?>
            </button>
        </form>
    </div>
</div>
<?php } ?>
