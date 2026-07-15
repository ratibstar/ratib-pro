<section class="rateb-portal-section">
    <div class="container">
        <h1><?php echo __('documents') ?: 'Documents'; ?></h1>
        <form class="rateb-portal-form rateb-portal-form--inline" method="post" action="<?php echo rateb_url('site/' . ($portalType ?? 'customer') . '/documents'); ?>" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <label class="rateb-portal-form__field"><span><?php echo __('title') ?: 'Title'; ?></span><input type="text" name="title"></label>
            <label class="rateb-portal-form__field"><span><?php echo __('category') ?: 'Category'; ?></span>
                <select name="doc_category">
                    <?php foreach (['contract','invoice','visa','passport','cv','certificate','letter','attachment'] as $c) { ?>
                    <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                    <?php } ?>
                </select>
            </label>
            <label class="rateb-portal-form__field"><span><?php echo __('file') ?: 'File'; ?></span><input type="file" name="document" required></label>
            <button type="submit" class="rateb-portal-btn"><?php echo __('upload') ?: 'Upload'; ?></button>
        </form>
        <table class="rateb-portal-table">
            <thead><tr><th><?php echo __('title') ?: 'Title'; ?></th><th><?php echo __('category') ?: 'Category'; ?></th><th><?php echo __('version') ?: 'Ver'; ?></th><th><?php echo __('date') ?: 'Date'; ?></th></tr></thead>
            <tbody>
            <?php foreach ($documents ?? [] as $doc) { ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape((string) ($doc['title'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($doc['doc_category'] ?? '')); ?></td>
                <td><?php echo (int) ($doc['version_no'] ?? 1); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($doc['created_at'] ?? '')); ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</section>
