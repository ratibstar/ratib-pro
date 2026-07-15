<section class="rateb-portal-section">
    <div class="container">
        <h1><?php echo __('requests') ?: 'Requests'; ?></h1>
        <form class="rateb-portal-form rateb-portal-form--inline" method="post" action="<?php echo rateb_url('site/' . ($portalType ?? 'customer') . '/requests'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <label class="rateb-portal-form__field"><span><?php echo __('type') ?: 'Type'; ?></span>
                <select name="request_type">
                    <?php
                    $types = ($portalType ?? '') === 'employer'
                        ? ['recruitment','workforce','visa','contract','replacement']
                        : (($portalType ?? '') === 'partner' ? ['referral','other'] : ['service','contract','other']);
                    foreach ($types as $t) { echo '<option value="' . Rateb\App\Core\View::escape($t) . '">' . Rateb\App\Core\View::escape($t) . '</option>'; }
                    ?>
                </select>
            </label>
            <label class="rateb-portal-form__field"><span><?php echo __('title') ?: 'Title'; ?></span><input type="text" name="title" required></label>
            <label class="rateb-portal-form__field rateb-portal-form__field--full"><span><?php echo __('description') ?: 'Description'; ?></span><textarea name="description" rows="3"></textarea></label>
            <button type="submit" class="rateb-portal-btn"><?php echo __('submit') ?: 'Submit'; ?></button>
        </form>
        <table class="rateb-portal-table">
            <thead><tr><th><?php echo __('title') ?: 'Title'; ?></th><th><?php echo __('type') ?: 'Type'; ?></th><th><?php echo __('status') ?: 'Status'; ?></th><th><?php echo __('date') ?: 'Date'; ?></th></tr></thead>
            <tbody>
            <?php foreach ($requests ?? [] as $req) { ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape((string) ($req['title'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($req['request_type'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($req['status'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($req['created_at'] ?? '')); ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</section>
