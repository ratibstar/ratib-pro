<section class="rateb-portal-section">
    <div class="container">
        <h1><?php echo __('support') ?: 'Support'; ?></h1>
        <form class="rateb-portal-form" method="post" action="<?php echo rateb_url('site/' . ($portalType ?? 'customer') . '/support'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <label class="rateb-portal-form__field"><span><?php echo __('subject') ?: 'Subject'; ?></span><input type="text" name="subject" required></label>
            <label class="rateb-portal-form__field"><span><?php echo __('priority') ?: 'Priority'; ?></span>
                <select name="priority"><option value="low">low</option><option value="normal" selected>normal</option><option value="high">high</option><option value="urgent">urgent</option></select>
            </label>
            <label class="rateb-portal-form__field rateb-portal-form__field--full"><span><?php echo __('message') ?: 'Message'; ?></span><textarea name="message" rows="4" required></textarea></label>
            <button type="submit" class="rateb-portal-btn"><?php echo __('submit') ?: 'Submit'; ?></button>
        </form>
        <table class="rateb-portal-table">
            <thead><tr><th><?php echo __('ticket') ?: 'Ticket'; ?></th><th><?php echo __('subject') ?: 'Subject'; ?></th><th><?php echo __('status') ?: 'Status'; ?></th><th><?php echo __('priority') ?: 'Priority'; ?></th></tr></thead>
            <tbody>
            <?php foreach ($tickets ?? [] as $t) { ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape((string) ($t['ticket_no'] ?? $t['id'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($t['subject'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($t['status'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($t['priority'] ?? '')); ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php if (($portalType ?? '') === 'customer') { ?>
        <h2><?php echo __('reply') ?: 'Reply'; ?></h2>
        <form class="rateb-portal-form" method="post" action="<?php echo rateb_url('site/customer/support/reply'); ?>" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <label class="rateb-portal-form__field"><span><?php echo __('ticket') ?: 'Ticket ID'; ?></span>
                <select name="ticket_id" required>
                    <?php foreach ($tickets ?? [] as $t) { ?>
                    <option value="<?php echo (int) ($t['id'] ?? 0); ?>" <?php echo ((int) ($activeTicketId ?? 0) === (int) ($t['id'] ?? 0)) ? 'selected' : ''; ?>><?php echo Rateb\App\Core\View::escape((string) ($t['ticket_no'] ?? $t['id'] ?? '')); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label class="rateb-portal-form__field rateb-portal-form__field--full"><span><?php echo __('message') ?: 'Message'; ?></span><textarea name="body" rows="3" required></textarea></label>
            <label class="rateb-portal-form__field"><span><?php echo __('attachment') ?: 'Attachment'; ?></span><input type="file" name="attachment"></label>
            <button type="submit" class="rateb-portal-btn"><?php echo __('send') ?: 'Send'; ?></button>
        </form>
        <?php if (!empty($replies)) { ?>
        <ul class="rateb-portal-list">
            <?php foreach ($replies as $r) { ?>
            <li><?php echo nl2br(Rateb\App\Core\View::escape((string) ($r['body'] ?? ''))); ?> <small><?php echo Rateb\App\Core\View::escape((string) ($r['created_at'] ?? '')); ?></small></li>
            <?php } ?>
        </ul>
        <?php } ?>
        <?php } ?>
    </div>
</section>
