<section class="rateb-portal-section">
    <div class="container">
        <h1><?php echo Rateb\App\Core\View::escape($title ?? 'Dashboard'); ?></h1>
        <p><?php echo __('welcome') ?: 'Welcome'; ?>, <?php echo Rateb\App\Core\View::escape((string) (($user['full_name'] ?? '') ?: '')); ?></p>
        <div class="rateb-portal-stats">
            <div class="rateb-portal-stat"><span><?php echo __('requests') ?: 'Requests'; ?></span><strong><?php echo count($requests ?? []); ?></strong></div>
            <div class="rateb-portal-stat"><span><?php echo __('outstanding') ?: 'Outstanding'; ?></span><strong><?php echo number_format((float) ($outstanding ?? 0), 2); ?></strong></div>
            <div class="rateb-portal-stat"><span><?php echo __('documents') ?: 'Documents'; ?></span><strong><?php echo count($documents ?? []); ?></strong></div>
            <div class="rateb-portal-stat"><span><?php echo __('tickets') ?: 'Tickets'; ?></span><strong><?php echo count($tickets ?? $opportunities ?? []); ?></strong></div>
        </div>
        <?php if (!empty($shortlists)) { ?>
        <h2><?php echo __('candidate_pipeline') ?: 'Candidate pipeline'; ?></h2>
        <ul class="rateb-portal-list">
            <?php foreach (array_slice($shortlists, 0, 5) as $row) { ?>
            <li><?php echo Rateb\App\Core\View::escape((string) ($row['full_name'] ?? $row['candidate_no'] ?? '')); ?> — <?php echo Rateb\App\Core\View::escape((string) ($row['status'] ?? '')); ?></li>
            <?php } ?>
        </ul>
        <?php } ?>
        <h2><?php echo __('recent_requests') ?: 'Recent requests'; ?></h2>
        <ul class="rateb-portal-list">
            <?php foreach (array_slice($requests ?? [], 0, 5) as $req) { ?>
            <li><strong><?php echo Rateb\App\Core\View::escape((string) ($req['title'] ?? '')); ?></strong> — <?php echo Rateb\App\Core\View::escape((string) ($req['status'] ?? '')); ?></li>
            <?php } ?>
        </ul>
    </div>
</section>
