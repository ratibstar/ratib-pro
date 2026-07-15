<?php $stages = ($pipeline['stages'] ?? []); ?>
<section class="rateb-portal-section">
    <div class="container">
        <h1><?php echo __('candidate_pipeline') ?: 'Candidate Pipeline'; ?></h1>
        <p class="rateb-portal-lead"><?php echo __('pipeline_readonly_hint') ?: 'Read-only ATS pipeline.'; ?> (<?php echo (int) ($pipeline['total'] ?? 0); ?>)</p>
        <div class="rateb-portal-pipeline" data-lazy-pipeline>
            <?php foreach (['shortlisted','interview','medical','visa','ready','deployed'] as $stage) { ?>
            <div class="rateb-portal-pipeline__col">
                <h3><?php echo Rateb\App\Core\View::escape(ucfirst($stage)); ?></h3>
                <ul class="rateb-portal-list">
                    <?php foreach ($stages[$stage] ?? [] as $cand) { ?>
                    <li>
                        <strong><?php echo Rateb\App\Core\View::escape((string) ($cand['full_name'] ?? '')); ?></strong>
                        <small><?php echo Rateb\App\Core\View::escape((string) ($cand['candidate_no'] ?? '')); ?></small>
                    </li>
                    <?php } ?>
                </ul>
            </div>
            <?php } ?>
        </div>
    </div>
</section>
