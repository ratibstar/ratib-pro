<section class="rateb-portal-section">
    <div class="container">
        <h1><?php echo __('approvals') ?: 'Approvals'; ?></h1>
        <table class="rateb-portal-table">
            <thead><tr><th>ID</th><th><?php echo __('entity') ?: 'Entity'; ?></th><th><?php echo __('status') ?: 'Status'; ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($approvals ?? [] as $a) { ?>
            <tr>
                <td><?php echo (int) ($a['id'] ?? 0); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($a['entity_type'] ?? '') . ' #' . (string) ($a['entity_id'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($a['status'] ?? '')); ?></td>
                <td>
                    <form method="post" action="<?php echo rateb_url('site/' . ($portalType ?? 'customer') . '/approvals'); ?>" class="rateb-portal-inline-form">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
                        <input type="hidden" name="instance_id" value="<?php echo (int) ($a['id'] ?? 0); ?>">
                        <button name="action" value="approve" class="rateb-portal-btn"><?php echo __('approve') ?: 'Approve'; ?></button>
                        <button name="action" value="reject" class="rateb-portal-btn rateb-portal-btn--ghost"><?php echo __('reject') ?: 'Reject'; ?></button>
                    </form>
                </td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</section>
