<section class="rateb-portal-section">
    <div class="container">
        <h1><?php echo Rateb\App\Core\View::escape($title ?? 'Customer Workspace'); ?></h1>
        <p><?php echo __('welcome') ?: 'Welcome'; ?>, <?php echo Rateb\App\Core\View::escape((string) ($user['full_name'] ?? '')); ?></p>

        <div class="rateb-portal-stats" data-workspace-kpis>
            <?php $kpis = $kpis ?? []; ?>
            <div class="rateb-portal-stat"><span><?php echo __('active_contracts') ?: 'Active contracts'; ?></span><strong><?php echo (int) ($kpis['active_contracts'] ?? 0); ?></strong></div>
            <div class="rateb-portal-stat"><span><?php echo __('open_requests') ?: 'Open requests'; ?></span><strong><?php echo (int) ($kpis['open_requests'] ?? 0); ?></strong></div>
            <div class="rateb-portal-stat"><span><?php echo __('outstanding') ?: 'Outstanding'; ?></span><strong><?php echo number_format((float) ($kpis['outstanding_balance'] ?? 0), 2); ?></strong></div>
            <div class="rateb-portal-stat"><span><?php echo __('pending_approvals') ?: 'Approvals'; ?></span><strong><?php echo (int) ($kpis['pending_approvals'] ?? 0); ?></strong></div>
            <div class="rateb-portal-stat"><span><?php echo __('pipeline') ?: 'Pipeline'; ?></span><strong><?php echo (int) ($kpis['pipeline_total'] ?? 0); ?></strong></div>
            <div class="rateb-portal-stat"><span><?php echo __('notifications') ?: 'Notifications'; ?></span><strong><?php echo (int) ($kpis['unread_notifications'] ?? 0); ?></strong></div>
        </div>

        <div class="rateb-portal-quick-actions">
            <a class="rateb-portal-btn" href="<?php echo rateb_url('site/customer/requests'); ?>"><?php echo __('new_request') ?: 'Requests'; ?></a>
            <a class="rateb-portal-btn rateb-portal-btn--ghost" href="<?php echo rateb_url('site/customer/contracts'); ?>"><?php echo __('contracts') ?: 'Contracts'; ?></a>
            <a class="rateb-portal-btn rateb-portal-btn--ghost" href="<?php echo rateb_url('site/customer/pipeline'); ?>"><?php echo __('pipeline') ?: 'Pipeline'; ?></a>
            <a class="rateb-portal-btn rateb-portal-btn--ghost" href="<?php echo rateb_url('site/customer/finance'); ?>"><?php echo __('invoices') ?: 'Invoices'; ?></a>
            <a class="rateb-portal-btn rateb-portal-btn--ghost" href="<?php echo rateb_url('site/customer/support'); ?>"><?php echo __('support') ?: 'Support'; ?></a>
        </div>

        <div class="rateb-portal-workspace-grid">
            <section>
                <h2><?php echo __('active_contracts') ?: 'Active contracts'; ?></h2>
                <ul class="rateb-portal-list">
                    <?php foreach (array_slice($contracts ?? [], 0, 5) as $c) { ?>
                    <li><strong><?php echo Rateb\App\Core\View::escape((string) ($c['contract_no'] ?? $c['title'] ?? '')); ?></strong> — <?php echo Rateb\App\Core\View::escape((string) ($c['status'] ?? '')); ?></li>
                    <?php } ?>
                </ul>
            </section>
            <section>
                <h2><?php echo __('recent_requests') ?: 'Recent requests'; ?></h2>
                <ul class="rateb-portal-list">
                    <?php foreach (array_slice($requests ?? [], 0, 5) as $req) { ?>
                    <li><strong><?php echo Rateb\App\Core\View::escape((string) ($req['title'] ?? '')); ?></strong> — <?php echo Rateb\App\Core\View::escape((string) ($req['status'] ?? '')); ?></li>
                    <?php } ?>
                </ul>
            </section>
            <section>
                <h2><?php echo __('pending_approvals') ?: 'Pending approvals'; ?></h2>
                <ul class="rateb-portal-list">
                    <?php foreach (array_slice($approvals ?? [], 0, 5) as $a) { ?>
                    <li>#<?php echo (int) ($a['id'] ?? 0); ?> <?php echo Rateb\App\Core\View::escape((string) ($a['entity_type'] ?? '')); ?></li>
                    <?php } ?>
                </ul>
            </section>
            <section>
                <h2><?php echo __('notifications') ?: 'Notifications'; ?></h2>
                <ul class="rateb-portal-list">
                    <?php foreach (array_slice($notifications ?? [], 0, 5) as $n) { ?>
                    <li><?php echo Rateb\App\Core\View::escape((string) ($n['title'] ?? '')); ?></li>
                    <?php } ?>
                </ul>
            </section>
        </div>
    </div>
</section>
