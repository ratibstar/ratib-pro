<section class="rateb-portal-section">
    <div class="container">
        <h1><?php echo __('appointments') ?: 'Appointments'; ?></h1>
        <form class="rateb-portal-form rateb-portal-form--inline" method="post" action="<?php echo rateb_url('site/' . ($portalType ?? 'customer') . '/appointments'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <label class="rateb-portal-form__field"><span><?php echo __('type') ?: 'Type'; ?></span>
                <select name="appointment_type"><option value="meeting">meeting</option><option value="interview">interview</option><option value="other">other</option></select>
            </label>
            <label class="rateb-portal-form__field"><span><?php echo __('title') ?: 'Title'; ?></span><input type="text" name="title" required></label>
            <label class="rateb-portal-form__field"><span><?php echo __('starts_at') ?: 'Starts'; ?></span><input type="datetime-local" name="starts_at" required></label>
            <label class="rateb-portal-form__field"><span><?php echo __('location') ?: 'Location'; ?></span><input type="text" name="location"></label>
            <button type="submit" class="rateb-portal-btn"><?php echo __('book') ?: 'Book'; ?></button>
        </form>
        <ul class="rateb-portal-list rateb-portal-calendar">
            <?php foreach ($appointments ?? [] as $a) { ?>
            <li>
                <strong><?php echo Rateb\App\Core\View::escape((string) ($a['title'] ?? '')); ?></strong>
                — <?php echo Rateb\App\Core\View::escape((string) ($a['starts_at'] ?? '')); ?>
                (<?php echo Rateb\App\Core\View::escape((string) ($a['status'] ?? '')); ?>)
            </li>
            <?php } ?>
        </ul>
    </div>
</section>
