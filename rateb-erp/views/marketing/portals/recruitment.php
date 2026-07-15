<section class="rateb-portal-section">
    <div class="container">
        <h1><?php echo __('recruitment') ?: 'Recruitment'; ?></h1>
        <form class="rateb-portal-form rateb-portal-form--inline" method="get" action="<?php echo rateb_url('site/employer/recruitment'); ?>">
            <input type="search" name="q" value="<?php echo Rateb\App\Core\View::escape((string) ($search ?? '')); ?>" placeholder="<?php echo __('search_candidates') ?: 'Search candidates'; ?>">
            <button type="submit" class="rateb-portal-btn"><?php echo __('search') ?: 'Search'; ?></button>
        </form>
        <h2><?php echo __('candidates') ?: 'Candidates'; ?></h2>
        <table class="rateb-portal-table">
            <thead><tr><th><?php echo __('name') ?: 'Name'; ?></th><th><?php echo __('status') ?: 'Status'; ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach (($candidates['items'] ?? []) as $c) { ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape((string) ($c['full_name'] ?? '')); ?> (<?php echo Rateb\App\Core\View::escape((string) ($c['candidate_no'] ?? '')); ?>)</td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($c['workflow_status'] ?? '')); ?></td>
                <td>
                    <form method="post" action="<?php echo rateb_url('site/employer/recruitment/shortlist'); ?>" class="rateb-portal-inline-form">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
                        <input type="hidden" name="candidate_id" value="<?php echo (int) ($c['id'] ?? 0); ?>">
                        <button type="submit" class="rateb-portal-btn rateb-portal-btn--ghost"><?php echo __('shortlist') ?: 'Shortlist'; ?></button>
                    </form>
                </td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
        <h2><?php echo __('shortlists') ?: 'Shortlists'; ?></h2>
        <table class="rateb-portal-table">
            <thead><tr><th><?php echo __('candidate') ?: 'Candidate'; ?></th><th><?php echo __('status') ?: 'Status'; ?></th><th><?php echo __('actions') ?: 'Actions'; ?></th></tr></thead>
            <tbody>
            <?php foreach ($shortlists ?? [] as $s) { ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape((string) ($s['full_name'] ?? $s['candidate_no'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($s['status'] ?? '')); ?></td>
                <td>
                    <?php if ((string) ($s['status'] ?? '') === 'shortlisted') { ?>
                    <form method="post" action="<?php echo rateb_url('site/employer/recruitment/decide'); ?>" class="rateb-portal-inline-form">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
                        <input type="hidden" name="shortlist_id" value="<?php echo (int) ($s['id'] ?? 0); ?>">
                        <button name="decision" value="approved" class="rateb-portal-btn"><?php echo __('approve') ?: 'Approve'; ?></button>
                        <button name="decision" value="rejected" class="rateb-portal-btn rateb-portal-btn--ghost"><?php echo __('reject') ?: 'Reject'; ?></button>
                        <button name="decision" value="replacement_requested" class="rateb-portal-btn rateb-portal-btn--ghost"><?php echo __('request_replacement') ?: 'Replacement'; ?></button>
                    </form>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</section>
