<section class="rateb-portal-section rateb-svc-section" data-service-book>
    <div class="container">
        <h1><?php echo __('book_appointment') ?: 'Book appointment'; ?></h1>
        <form class="rateb-portal-form" method="post" action="<?php echo rateb_url('site/customer/services/book'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <div class="rateb-portal-form__field">
                <label for="book_service_id"><?php echo __('service_request') ?: 'Service request'; ?></label>
                <select id="book_service_id" name="service_id" required>
                    <?php
                    $pref = (int) ($prefill_service_id ?? 0);
                    foreach (($services ?? []) as $s) {
                        $id = (int) ($s['id'] ?? 0);
                        $selected = $pref === $id ? ' selected' : '';
                        echo '<option value="' . $id . '"' . $selected . '>'
                            . Rateb\App\Core\View::escape('#' . $id . ' ' . (string) ($s['title'] ?? ''))
                            . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="rateb-portal-form__field">
                <label for="book_title"><?php echo __('title') ?: 'Title'; ?></label>
                <input id="book_title" type="text" name="title" maxlength="255">
            </div>
            <div class="rateb-portal-form__field">
                <label for="book_starts_at"><?php echo __('starts_at') ?: 'Starts at'; ?></label>
                <input id="book_starts_at" type="datetime-local" name="starts_at" required>
            </div>
            <div class="rateb-portal-form__field">
                <label for="book_ends_at"><?php echo __('ends_at') ?: 'Ends at'; ?></label>
                <input id="book_ends_at" type="datetime-local" name="ends_at">
            </div>
            <div class="rateb-portal-form__field">
                <label for="book_location"><?php echo __('location') ?: 'Location'; ?></label>
                <input id="book_location" type="text" name="location" maxlength="255">
            </div>
            <div class="rateb-portal-form__field">
                <label for="book_notes"><?php echo __('notes') ?: 'Notes'; ?></label>
                <textarea id="book_notes" name="notes" rows="3"></textarea>
            </div>
            <button type="submit" class="rateb-portal-btn"><?php echo __('schedule') ?: 'Schedule'; ?></button>
        </form>

        <h2><?php echo __('upcoming') ?: 'Upcoming'; ?></h2>
        <ul class="rateb-portal-list" data-appointment-calendar>
            <?php foreach (($appointments ?? []) as $a) { ?>
            <li><?php echo Rateb\App\Core\View::escape((string) ($a['title'] ?? '')); ?> — <?php echo Rateb\App\Core\View::escape((string) ($a['starts_at'] ?? '')); ?></li>
            <?php } ?>
        </ul>
    </div>
</section>
