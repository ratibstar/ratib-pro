<?php
use Rateb\App\Core\View;

$item = $item ?? null;
$isEdit = is_array($item) && (int) ($item['id'] ?? 0) > 0;
$ticketId = $isEdit ? (int) $item['id'] : 0;
/** @var list<array{id: string, label: string, body: string}> $replyTemplates */
$replyTemplates = is_array($replyTemplates ?? null) ? $replyTemplates : [];

if ($isEdit && !empty($conversation)) {
    View::partial('support-ticket-thread', get_defined_vars());
}

View::partial('crud-form', get_defined_vars());

if ($isEdit && !empty($canReply) && !empty($replyAction)) { ?>
<div class="rateb-card mt-3 support-ticket-reply-box" data-support-reply-picker="1">
    <div class="rateb-card-header"><?php echo View::escape(__('support_ticket_reply_heading')); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo View::escape((string) $replyAction); ?>" data-rateb-full-nav="1">
            <input type="hidden" name="_csrf" value="<?php echo View::escape($csrf ?? ''); ?>">
            <div class="mb-3">
                <label class="form-label rateb-form-label" for="support_ticket_reply_pick"><?php echo View::escape(__('support_ticket_reply_pick_label')); ?></label>
                <div class="support-ticket-reply-picker">
                    <div class="input-group support-ticket-reply-picker__search">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="search" class="form-control rateb-form-control" id="support_ticket_reply_search"
                               data-reply-search="1"
                               autocomplete="off"
                               placeholder="<?php echo View::escape(__('support_ticket_reply_search_placeholder')); ?>">
                    </div>
                    <select class="form-select rateb-form-control mt-2" id="support_ticket_reply_pick" data-reply-pick="1" required>
                        <option value=""><?php echo View::escape(__('select')); ?></option>
                        <?php foreach ($replyTemplates as $tpl) {
                            $tplId = (string) ($tpl['id'] ?? '');
                            $tplLabel = (string) ($tpl['label'] ?? '');
                            $tplBody = (string) ($tpl['body'] ?? '');
                            $searchHay = mb_strtolower($tplLabel . ' ' . $tplBody, 'UTF-8');
                            ?>
                        <option value="<?php echo View::escape($tplId); ?>"
                                data-search="<?php echo View::escape($searchHay); ?>">
                            <?php echo View::escape($tplLabel); ?>
                        </option>
                        <?php } ?>
                        <option value="__manual__" data-search="<?php echo View::escape(mb_strtolower(__('manual_entry'), 'UTF-8')); ?>">
                            <?php echo View::escape(__('manual_entry')); ?>
                        </option>
                    </select>
                    <script type="application/json" data-reply-templates-json="1"><?php
                        echo json_encode(
                            array_values($replyTemplates),
                            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
                        );
                    ?></script>
                    <div class="form-text"><?php echo View::escape(__('support_ticket_reply_pick_hint')); ?></div>
                </div>
            </div>
            <div class="mb-3" data-reply-body-wrap="1">
                <label class="form-label rateb-form-label" for="support_ticket_reply_body"><?php echo View::escape(__('support_ticket_reply_label')); ?></label>
                <textarea class="form-control rateb-form-control" id="support_ticket_reply_body" name="reply_body" rows="5"
                          data-reply-body="1"
                          required
                          placeholder="<?php echo View::escape(__('support_ticket_reply_placeholder')); ?>"></textarea>
                <div class="form-text"><?php echo View::escape(__('support_ticket_reply_notify_hint')); ?></div>
            </div>
            <button type="submit" class="btn btn-primary" data-reply-submit="1">
                <i class="fas fa-paper-plane ms-1"></i>
                <?php echo View::escape(__('support_ticket_reply_submit')); ?>
            </button>
        </form>
    </div>
</div>
<script src="<?php echo View::escape(rateb_asset('js/support-ticket-ui.js')); ?>" defer></script>
<?php } elseif ($isEdit && !empty($conversation)) { ?>
<script src="<?php echo View::escape(rateb_asset('js/support-ticket-ui.js')); ?>" defer></script>
<?php } ?>
