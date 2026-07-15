<?php $result = $result ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 12]; ?>
<section class="rateb-career-hero rateb-career-hero--compact">
    <div class="container">
        <h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
        <form class="rateb-career-search" method="get" action="<?php echo rateb_url('site/careers/search'); ?>">
            <input type="search" name="q" value="<?php echo Rateb\App\Core\View::escape($query ?? ''); ?>" class="rateb-career-search__input" placeholder="<?php echo __('job_search_placeholder') ?: 'Search jobs…'; ?>">
            <button type="submit" class="rateb-career-search__btn"><?php echo __('search') ?: 'Search'; ?></button>
        </form>
    </div>
</section>
<section class="rateb-career-section">
    <div class="container">
        <p class="rateb-career-results-count"><?php echo (int) ($result['total'] ?? 0); ?> <?php echo __('jobs_found') ?: 'jobs found'; ?></p>
        <div class="rateb-career-grid">
            <?php foreach ($result['items'] ?? [] as $job) { $jobCard = $job; require RATEB_ROOT . '/views/marketing/careers/partials/job-card.php'; } ?>
        </div>
        <?php
        $total = (int) ($result['total'] ?? 0);
        $page = (int) ($result['page'] ?? 1);
        $per = (int) ($result['per_page'] ?? 12);
        $pages = $per > 0 ? (int) ceil($total / $per) : 1;
        if ($pages > 1) {
        ?>
        <nav class="rateb-career-pagination" aria-label="Pagination">
            <?php for ($p = 1; $p <= $pages; $p++) {
                $q = trim((string) ($query ?? ''));
                $href = rateb_url('site/careers/search') . '?page=' . $p . ($q !== '' ? '&q=' . rawurlencode($q) : '');
            ?>
            <a class="rateb-career-pagination__link<?php echo $p === $page ? ' is-active' : ''; ?>" href="<?php echo Rateb\App\Core\View::escape($href); ?>"><?php echo $p; ?></a>
            <?php } ?>
        </nav>
        <?php } ?>
    </div>
</section>
