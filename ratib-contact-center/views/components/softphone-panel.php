<?php
declare(strict_types=1);

/**
 * Reusable softphone shell — external CSS/JS only (no inline scripts).
 *
 * @var int $tenantId
 * @var int $agentId
 * @var int|null $userId
 * @var string $apiBase
 * @var string $wsUrl
 * @var string $assetsBase
 */
$tenantId = (int) ($tenantId ?? 1);
$agentId = (int) ($agentId ?? 1);
$userId = isset($userId) ? (int) $userId : null;
$apiBase = (string) ($apiBase ?? '/ratib-contact-center/public/api/v1/softphone.php');
$wsUrl = (string) ($wsUrl ?? 'ws://127.0.0.1:9702');
$assetsBase = (string) ($assetsBase ?? '/ratib-contact-center/public/assets');

if (!function_exists('__')) {
    function __(string $s): string { return $s; }
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($assetsBase . '/css/rcc-softphone.css', ENT_QUOTES, 'UTF-8'); ?>">

<div class="rcc-softphone" id="rcc-softphone-panel"
     data-tenant="<?php echo $tenantId; ?>"
     data-agent="<?php echo $agentId; ?>"
     data-user="<?php echo $userId !== null ? (string) $userId : ''; ?>"
     data-api="<?php echo htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8'); ?>"
     data-ws="<?php echo htmlspecialchars($wsUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="rcc-softphone__header">
        <span><span class="rcc-softphone__status-dot rcc-softphone__status-dot--offline" id="rcc-sp-status-dot"></span><span id="rcc-sp-status-label"><?php echo htmlspecialchars(__('Agent Offline'), ENT_QUOTES, 'UTF-8'); ?></span></span>
        <span id="rcc-sp-queue-name" class="rcc-softphone__meta"></span>
    </div>
    <div class="rcc-softphone__body">
        <div class="rcc-softphone__number" id="rcc-sp-number">—</div>
        <div class="rcc-softphone__meta" id="rcc-sp-direction"></div>
        <div class="rcc-softphone__timer" id="rcc-sp-timer">00:00</div>
        <div class="rcc-softphone__erp rcc-softphone__hidden" id="rcc-sp-erp"></div>
        <div class="rcc-softphone__actions">
            <button type="button" class="rcc-softphone__btn rcc-softphone__btn--answer" id="rcc-sp-answer" disabled><?php echo htmlspecialchars(__('Answer'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="rcc-softphone__btn" id="rcc-sp-hold" disabled><?php echo htmlspecialchars(__('Hold'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="rcc-softphone__btn rcc-softphone__btn--hangup" id="rcc-sp-hangup" disabled><?php echo htmlspecialchars(__('Hang up'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="rcc-softphone__btn" id="rcc-sp-mute" disabled><?php echo htmlspecialchars(__('Mute'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="rcc-softphone__btn" id="rcc-sp-transfer" disabled><?php echo htmlspecialchars(__('Transfer'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="rcc-softphone__btn" id="rcc-sp-dial" disabled><?php echo htmlspecialchars(__('Dial'), ENT_QUOTES, 'UTF-8'); ?></button>
        </div>
    </div>
</div>

<audio id="rcc-sp-remote-audio" autoplay playsinline class="rcc-softphone__hidden"></audio>

<div class="rcc-softphone__popup d-none" id="rcc-sp-incoming-popup">
    <div class="rcc-softphone__popup-card">
        <div class="rcc-softphone__meta"><?php echo htmlspecialchars(__('Incoming call'), ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="rcc-softphone__number" id="rcc-sp-popup-number">—</div>
        <button type="button" class="rcc-softphone__btn rcc-softphone__btn--answer" id="rcc-sp-popup-answer"><?php echo htmlspecialchars(__('Answer'), ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
</div>

<script src="<?php echo htmlspecialchars($assetsBase . '/js/rcc-realtime-client.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sip.js@0.21.2/dist/sip.min.js"></script>
<script src="<?php echo htmlspecialchars($assetsBase . '/js/rcc-softphone.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($assetsBase . '/js/rcc-softphone-ui.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
