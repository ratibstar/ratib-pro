<?php $result = $result ?? ['items' => [], 'total' => 0]; ?>
<section class="rateb-career-hero rateb-career-hero--compact">
    <div class="container">
        <h1><?php echo Rateb\App\Core\View::escape($categoryLabel ?? $title ?? ''); ?></h1>
        <a class="rateb-career-back" href="<?php echo rateb_url('site/careers'); ?>"><?php echo __('back_to_careers') ?: '← All careers'; ?></a>
    </div>
</section>
<section class="rateb-career-section">
    <div class="container">
        <div class="rateb-career-grid">
            <?php foreach ($result['items'] ?? [] as $job) { $jobCard = $job; require RATEB_ROOT . '/views/marketing/careers/partials/job-card.php'; } ?>
        </div>
    </div>
</section>
