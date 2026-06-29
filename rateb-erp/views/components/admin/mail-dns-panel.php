<?php
/** @var array<string, mixed>|null $mailDns */
$mailDns = $mailDns ?? null;
if (!is_array($mailDns)) {
    return;
}
$dnsBadge = static function (bool $ok): string {
    return $ok ? 'bg-success' : 'bg-danger';
};
$recs = is_array($mailDns['recommendations'] ?? null) ? $mailDns['recommendations'] : [];
?>
<div class="border rounded p-2 mb-3 small rateb-mail-dns-panel">
    <div class="fw-semibold mb-2"><i class="fas fa-globe"></i> <?php echo __('mail_dns_check_title'); ?> — <?php echo Rateb\App\Core\View::escape((string) ($mailDns['domain'] ?? 'rateb.sa')); ?></div>
    <ul class="list-unstyled mb-2">
        <li><span class="badge <?php echo $dnsBadge(!empty($mailDns['spf']['ok'])); ?>"><?php echo !empty($mailDns['spf']['ok']) ? 'SPF ✓' : 'SPF ✗'; ?></span>
            <?php echo Rateb\App\Core\View::escape((string) ($mailDns['spf']['detail'] ?? '')); ?></li>
        <li class="mt-1"><span class="badge <?php echo $dnsBadge(!empty($mailDns['dkim']['ok'])); ?>"><?php echo !empty($mailDns['dkim']['ok']) ? 'DKIM ✓' : 'DKIM ✗'; ?></span>
            <?php echo Rateb\App\Core\View::escape((string) ($mailDns['dkim']['detail'] ?? '')); ?></li>
        <li class="mt-1"><span class="badge <?php echo $dnsBadge(!empty($mailDns['dmarc']['ok'])); ?>"><?php echo !empty($mailDns['dmarc']['ok']) ? 'DMARC ✓' : 'DMARC ○'; ?></span>
            <?php echo Rateb\App\Core\View::escape((string) ($mailDns['dmarc']['detail'] ?? '')); ?></li>
        <li class="mt-1"><span class="badge <?php echo $dnsBadge(!empty($mailDns['mx']['ok'])); ?>">MX</span>
            <?php echo Rateb\App\Core\View::escape((string) ($mailDns['mx']['detail'] ?? '')); ?></li>
    </ul>
    <?php if (empty($mailDns['ready_for_external'])) { ?>
    <p class="text-danger mb-2"><?php echo __('mail_dns_not_ready'); ?></p>
    <p class="text-muted mb-2"><?php echo __('mail_dns_directadmin_steps'); ?></p>
    <?php if (!empty($recs['spf']['needed']) && !empty($recs['spf']['value'])) { ?>
    <div class="mb-2">
        <label class="form-label mb-1"><?php echo __('mail_dns_spf_record'); ?></label>
        <div class="input-group input-group-sm">
            <input type="text" class="form-control font-monospace" dir="ltr" readonly value="<?php echo Rateb\App\Core\View::escape((string) $recs['spf']['value']); ?>" id="rateb-dns-spf">
            <button type="button" class="btn btn-outline-secondary" data-copy-target="rateb-dns-spf"><?php echo __('copy'); ?></button>
        </div>
    </div>
    <?php } ?>
    <?php if (!empty($recs['dkim_needed'])) { ?>
    <p class="text-muted mb-2"><?php echo Rateb\App\Core\View::escape((string) ($recs['dkim_note'] ?? '')); ?></p>
    <?php } ?>
    <?php if (!empty($recs['dmarc']['value'])) { ?>
    <div class="mb-2">
        <label class="form-label mb-1"><?php echo __('mail_dns_dmarc_record'); ?> <span class="text-muted">(<?php echo __('optional'); ?>)</span></label>
        <div class="input-group input-group-sm">
            <input type="text" class="form-control font-monospace" dir="ltr" readonly value="<?php echo Rateb\App\Core\View::escape((string) $recs['dmarc']['value']); ?>" id="rateb-dns-dmarc">
            <button type="button" class="btn btn-outline-secondary" data-copy-target="rateb-dns-dmarc"><?php echo __('copy'); ?></button>
        </div>
    </div>
    <?php } ?>
    <p class="text-muted mb-0"><?php echo __('mail_dns_hawsabah_hint'); ?></p>
    <?php } else { ?>
    <p class="text-success mb-1"><?php echo __('mail_dns_ready'); ?></p>
  <p class="text-muted mb-0"><?php echo __('mail_check_spam_hint'); ?></p>
    <?php } ?>
</div>
<script>
(function () {
    document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-copy-target');
            var el = id ? document.getElementById(id) : null;
            if (!el) return;
            el.select();
            el.setSelectionRange(0, 99999);
            try { navigator.clipboard.writeText(el.value); } catch (e) { document.execCommand('copy'); }
        });
    });
})();
</script>
