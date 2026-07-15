<section class="rateb-career-section">
    <div class="container">
        <h1><?php echo __('saved_jobs') ?: 'Saved Jobs'; ?></h1>
        <div class="rateb-career-grid">
            <?php if (empty($savedJobs)) { ?>
            <p><?php echo __('no_saved_jobs') ?: 'No saved jobs yet.'; ?></p>
            <?php } else {
                foreach ($savedJobs as $job) {
                    $jobCard = $job;
                    require RATEB_ROOT . '/views/marketing/careers/partials/job-card.php';
                }
            } ?>
        </div>
    </div>
</section>
