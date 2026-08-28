<?php
/** @var array<string, mixed>|null $mailDns */
/** @var bool $mailDnsAsync */
/** @var string $mailDnsUrl */
/** @var string $mailDnsDomain */
$mailDns = $mailDns ?? null;
$mailDnsAsync = !empty($mailDnsAsync);
$mailDnsUrl = (string) ($mailDnsUrl ?? '');
$mailDnsDomain = (string) ($mailDnsDomain ?? 'rateb.sa');

if ($mailDnsAsync && !is_array($mailDns)) {
    ?>
<div class="border rounded p-2 mb-3 small rateb-mail-dns-panel"
     data-mail-dns-async="1"
     data-mail-dns-url="<?php echo Rateb\App\Core\View::escape($mailDnsUrl); ?>"
     data-mail-dns-fail="<?php echo Rateb\App\Core\View::escape(__('mail_dns_check_failed')); ?>">
    <div class="fw-semibold mb-2">
        <i class="fas fa-globe"></i> <?php echo __('mail_dns_check_title'); ?> — <?php echo Rateb\App\Core\View::escape($mailDnsDomain); ?>
    </div>
    <div class="placeholder-glow">
        <span class="placeholder col-7 mb-2 d-block"></span>
        <span class="placeholder col-10 mb-2 d-block"></span>
        <span class="placeholder col-8 mb-2 d-block"></span>
        <span class="placeholder col-9 d-block"></span>
    </div>
    <p class="text-muted mb-0 mt-2"><i class="fas fa-spinner fa-spin"></i> <?php echo __('mail_dns_checking'); ?></p>
</div>
<script>
(function () {
    var host = document.currentScript && document.currentScript.previousElementSibling;
    if (!host || host.getAttribute('data-mail-dns-async') !== '1') {
        return;
    }
    var url = host.getAttribute('data-mail-dns-url') || '';
    if (!url || host.getAttribute('data-mail-dns-booted') === '1') {
        return;
    }
    host.setAttribute('data-mail-dns-booted', '1');
    var fail = host.getAttribute('data-mail-dns-fail') || 'DNS check failed';
    var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timer = setTimeout(function () {
        if (ctrl) {
            try { ctrl.abort(); } catch (e) { /* ignore */ }
        }
    }, 10000);
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: ctrl ? ctrl.signal : undefined })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data && data.ok && data.html) {
                host.outerHTML = data.html;
                if (typeof window.ratebMailDnsBoot === 'function') {
                    window.ratebMailDnsBoot({ immediate: true });
                }
                return;
            }
            host.innerHTML = '<p class="text-warning small mb-0">' + fail + '</p>';
        })
        .catch(function () {
            host.innerHTML = '<p class="text-warning small mb-0">' + fail + '</p>';
        })
        .finally(function () {
            clearTimeout(timer);
        });
})();
</script>
    <?php
    return;
}

if (!is_array($mailDns)) {
    return;
}
$dnsBadge = static function (bool $ok): string {
    return $ok ? 'bg-success' : 'bg-danger';
};
$recs = is_array($mailDns['recommendations'] ?? null) ? $mailDns['recommendations'] : [];
$panelDnsUrl = $mailDnsUrl !== '' ? $mailDnsUrl : '';
?>
<div class="border rounded p-2 mb-3 small rateb-mail-dns-panel"
     <?php if ($panelDnsUrl !== '') { ?>
     data-mail-dns-url="<?php echo Rateb\App\Core\View::escape($panelDnsUrl); ?>"
     data-mail-dns-fail="<?php echo Rateb\App\Core\View::escape(__('mail_dns_check_failed')); ?>"
     <?php } ?>>
    <div class="fw-semibold mb-2 d-flex justify-content-between align-items-center">
        <span><i class="fas fa-globe"></i> <?php echo __('mail_dns_check_title'); ?> — <?php echo Rateb\App\Core\View::escape((string) ($mailDns['domain'] ?? 'rateb.sa')); ?></span>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-mail-dns-refresh="1"><?php echo __('refresh'); ?></button>
    </div>
    <ul class="list-unstyled mb-2">
        <li><span class="badge <?php echo $dnsBadge(!empty($mailDns['spf']['ok'])); ?>"><?php echo !empty($mailDns['spf']['ok']) ? 'SPF ✓' : 'SPF ✗'; ?></span>
            <?php echo Rateb\App\Core\View::escape((string) ($mailDns['spf']['detail'] ?? '')); ?></li>
        <li class="mt-1"><span class="badge <?php echo $dnsBadge(!empty($mailDns['dkim']['ok'])); ?>"><?php echo !empty($mailDns['dkim']['ok']) ? 'DKIM ✓' : 'DKIM ✗'; ?></span>
            <?php echo Rateb\App\Core\View::escape((string) ($mailDns['dkim']['detail'] ?? '')); ?></li>
        <li class="mt-1"><span class="badge <?php echo $dnsBadge(!empty($mailDns['dmarc']['ok'])); ?>"><?php echo !empty($mailDns['dmarc']['ok']) ? 'DMARC ✓' : 'DMARC ○'; ?></span>
            <?php echo Rateb\App\Core\View::escape((string) ($mailDns['dmarc']['detail'] ?? '')); ?></li>
        <li class="mt-1"><span class="badge <?php echo $dnsBadge(!empty($mailDns['mx']['ok'])); ?>">MX</span>
            <?php echo Rateb\App\Core\View::escape((string) ($mailDns['mx']['detail'] ?? '')); ?></li>
        <li class="mt-1"><span class="badge <?php echo $dnsBadge(!empty($mailDns['ptr']['ok'])); ?>">PTR</span>
            <?php echo Rateb\App\Core\View::escape((string) ($mailDns['ptr']['detail'] ?? '')); ?></li>
        <?php if (!empty($mailDns['port25'])) { ?>
        <li class="mt-1"><span class="badge <?php echo $dnsBadge(!empty($mailDns['port25']['ok'])); ?>">Port 25</span>
            <?php echo Rateb\App\Core\View::escape((string) ($mailDns['port25']['detail'] ?? '')); ?></li>
        <?php } ?>
    </ul>
    <?php
    $dnsWarnings = is_array($mailDns['warnings'] ?? null) ? $mailDns['warnings'] : [];
    if ($dnsWarnings !== []) { ?>
    <ul class="text-warning mb-2 ps-3">
        <?php foreach ($dnsWarnings as $warn) { ?>
        <li><?php echo Rateb\App\Core\View::escape((string) $warn); ?></li>
        <?php } ?>
    </ul>
    <?php } ?>
    <?php if (empty($mailDns['ready_for_external'])) { ?>
    <p class="text-danger mb-2"><?php echo __('mail_dns_not_ready'); ?></p>
    <?php if (!empty($mailDns['port25']) && empty($mailDns['port25']['ok']) && empty($mailDns['port25']['skipped'])) { ?>
    <p class="text-danger mb-2"><strong><?php echo __('mail_port25_blocked_hint'); ?></strong></p>
    <p class="text-muted mb-2"><?php echo __('mail_hetzner_unblock_steps'); ?></p>
    <div class="mb-2">
        <label class="form-label mb-1"><?php echo __('mail_hetzner_ticket'); ?></label>
        <div class="input-group input-group-sm">
            <textarea class="form-control font-monospace small" dir="ltr" rows="6" readonly id="rateb-hetzner-ticket"><?php echo Rateb\App\Core\View::escape(__('mail_hetzner_ticket_body')); ?></textarea>
            <button type="button" class="btn btn-outline-secondary align-self-start" data-copy-target="rateb-hetzner-ticket"><?php echo __('copy'); ?></button>
        </div>
    </div>
    <p class="text-success mb-2"><?php echo __('mail_hetzner_ready_checklist'); ?></p>
    <?php } else { ?>
    <p class="text-muted mb-2"><?php echo __('mail_dns_directadmin_steps'); ?></p>
    <?php } ?>
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
