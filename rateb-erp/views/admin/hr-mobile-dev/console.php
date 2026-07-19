<?php
declare(strict_types=1);

/** @var array<string, mixed> $cfg */
/** @var bool $authSignedIn */
/** @var string $authLabel */
/** @var string $healthUrl */

use Rateb\App\Core\View;

$webUrl = (string) ($cfg['web_url'] ?? '');
$apiBase = (string) ($cfg['api_base'] ?? '');
$build = (string) ($cfg['build'] ?? 'dev');
$environment = (string) ($cfg['environment'] ?? 'unknown');
$hasUrl = $webUrl !== '';
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><?php echo View::escape(__('hr_mobile_dev_console')); ?></h1>
            <p class="text-muted mb-0 small"><?php echo View::escape(__('hr_mobile_dev_intro')); ?></p>
            <p class="mb-0 mt-2">
                <a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_url('admin/mobile-apps'); ?>">
                    <i class="fas fa-mobile-alt"></i> <?php echo View::escape(__('mobile_apps_title')); ?>
                </a>
            </p>
        </div>
        <span class="badge text-bg-warning"><?php echo View::escape(__('hr_mobile_dev_badge')); ?></span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header fw-semibold"><?php echo View::escape(__('hr_mobile_dev_diagnostics')); ?></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5"><?php echo View::escape(__('hr_mobile_dev_build')); ?></dt>
                        <dd class="col-7" id="hr-mobile-build"><?php echo View::escape($build); ?></dd>

                        <dt class="col-5"><?php echo View::escape(__('hr_mobile_dev_environment')); ?></dt>
                        <dd class="col-7" id="hr-mobile-env"><?php echo View::escape($environment); ?></dd>

                        <dt class="col-5"><?php echo View::escape(__('hr_mobile_dev_api_base')); ?></dt>
                        <dd class="col-7 text-break" id="hr-mobile-api"><?php echo View::escape($apiBase !== '' ? $apiBase : '—'); ?></dd>

                        <dt class="col-5"><?php echo View::escape(__('hr_mobile_dev_flutter_url')); ?></dt>
                        <dd class="col-7 text-break" id="hr-mobile-url"><?php echo View::escape($hasUrl ? $webUrl : __('hr_mobile_dev_url_missing')); ?></dd>

                        <dt class="col-5"><?php echo View::escape(__('hr_mobile_dev_connection')); ?></dt>
                        <dd class="col-7" id="hr-mobile-connection"><?php echo View::escape(__('hr_mobile_dev_connection_unknown')); ?></dd>

                        <dt class="col-5"><?php echo View::escape(__('hr_mobile_dev_auth')); ?></dt>
                        <dd class="col-7" id="hr-mobile-auth">
                            <?php if ($authSignedIn) { ?>
                                <span class="text-success"><?php echo View::escape(__('hr_mobile_dev_auth_signed_in')); ?></span>
                                — <?php echo View::escape($authLabel); ?>
                            <?php } else { ?>
                                <span class="text-danger"><?php echo View::escape(__('hr_mobile_dev_auth_none')); ?></span>
                            <?php } ?>
                        </dd>
                    </dl>
                </div>
                <div class="card-footer d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-primary" id="hr-mobile-launch" <?php echo $hasUrl ? '' : 'disabled'; ?>>
                        <i class="fas fa-play"></i> <?php echo View::escape(__('hr_mobile_dev_launch')); ?>
                    </button>
                    <a class="btn btn-sm btn-outline-primary <?php echo $hasUrl ? '' : 'disabled'; ?>"
                       id="hr-mobile-new-tab"
                       href="<?php echo View::escape($hasUrl ? $webUrl : '#'); ?>"
                       target="_blank" rel="noopener noreferrer"
                       <?php echo $hasUrl ? '' : 'aria-disabled="true" tabindex="-1"'; ?>>
                        <i class="fas fa-up-right-from-square"></i> <?php echo View::escape(__('hr_mobile_dev_new_tab')); ?>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="hr-mobile-reload" <?php echo $hasUrl ? '' : 'disabled'; ?>>
                        <i class="fas fa-rotate"></i> <?php echo View::escape(__('hr_mobile_dev_reload')); ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" id="hr-mobile-health"
                            data-health-url="<?php echo View::escape($healthUrl); ?>">
                        <i class="fas fa-heart-pulse"></i> <?php echo View::escape(__('hr_mobile_dev_health')); ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header fw-semibold"><?php echo View::escape(__('hr_mobile_dev_preview')); ?></div>
                <div class="card-body p-0" style="min-height: 70vh;">
                    <?php if ($hasUrl) { ?>
                        <iframe
                            id="hr-mobile-frame"
                            title="<?php echo View::escape(__('hr_mobile_dev_console')); ?>"
                            src="about:blank"
                            style="width:100%;height:70vh;border:0;background:#0b1220;"
                        ></iframe>
                    <?php } else { ?>
                        <div class="p-4 text-muted">
                            <?php echo View::escape(__('hr_mobile_dev_url_missing_help')); ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var frame = document.getElementById('hr-mobile-frame');
    var webUrl = <?php echo json_encode($webUrl, JSON_UNESCAPED_SLASHES); ?>;
    var launchBtn = document.getElementById('hr-mobile-launch');
    var reloadBtn = document.getElementById('hr-mobile-reload');
    var healthBtn = document.getElementById('hr-mobile-health');
    var connEl = document.getElementById('hr-mobile-connection');

    function setConn(text, ok) {
        if (!connEl) return;
        connEl.textContent = text;
        connEl.className = 'col-7 ' + (ok === true ? 'text-success' : (ok === false ? 'text-danger' : ''));
    }

    if (launchBtn && frame && webUrl) {
        launchBtn.addEventListener('click', function () {
            frame.src = webUrl;
            setConn(<?php echo json_encode(__('hr_mobile_dev_connection_loaded')); ?>, null);
        });
    }
    if (reloadBtn && frame) {
        reloadBtn.addEventListener('click', function () {
            if (!frame.src || frame.src === 'about:blank') {
                if (webUrl) frame.src = webUrl;
                return;
            }
            try {
                frame.contentWindow.location.reload();
            } catch (e) {
                frame.src = webUrl;
            }
        });
    }
    if (healthBtn) {
        healthBtn.addEventListener('click', function () {
            var url = healthBtn.getAttribute('data-health-url');
            if (!url) return;
            setConn(<?php echo json_encode(__('hr_mobile_dev_connection_checking')); ?>, null);
            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        setConn(<?php echo json_encode(__('hr_mobile_dev_connection_ok')); ?> + ' (' + (data.status || '') + ')', true);
                    } else {
                        setConn(<?php echo json_encode(__('hr_mobile_dev_connection_fail')); ?> + ': ' + ((data && data.message) || 'error'), false);
                    }
                })
                .catch(function () {
                    setConn(<?php echo json_encode(__('hr_mobile_dev_connection_fail')); ?>, false);
                });
        });
    }
})();
</script>
