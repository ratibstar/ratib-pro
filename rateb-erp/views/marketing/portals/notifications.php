<section class="rateb-portal-section">
    <div class="container">
        <h1><?php echo __('notifications') ?: 'Notifications'; ?></h1>
        <ul class="rateb-portal-list">
            <?php foreach ($notifications ?? [] as $n) { ?>
            <li>
                <strong><?php echo Rateb\App\Core\View::escape((string) ($n['title'] ?? '')); ?></strong>
                <p><?php echo Rateb\App\Core\View::escape((string) ($n['message'] ?? '')); ?></p>
                <small><?php echo Rateb\App\Core\View::escape((string) ($n['created_at'] ?? '')); ?></small>
            </li>
            <?php } ?>
        </ul>
    </div>
</section>
