<?php
declare(strict_types=1);

/**
 * Unified agent desktop — inbox + softphone shell (external assets only).
 *
 * @var int $tenantId
 * @var int $agentId
 * @var string $inboxApiBase
 * @var string $softphoneApiBase
 * @var string $wsUrl
 * @var string $assetsBase
 */
$tenantId = (int) ($tenantId ?? 0);
$agentId = (int) ($agentId ?? 0);
$inboxApiBase = (string) ($inboxApiBase ?? '/ratib-contact-center/public/api/v1/inbox.php');
$softphoneApiBase = (string) ($softphoneApiBase ?? '/ratib-contact-center/public/api/v1/softphone.php');
$wsUrl = (string) ($wsUrl ?? 'ws://127.0.0.1:9702');
$assetsBase = (string) ($assetsBase ?? '/ratib-contact-center/public/assets');

$apiBase = $softphoneApiBase;
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($assetsBase . '/css/rcc-agent-inbox.css', ENT_QUOTES, 'UTF-8'); ?>">

<div class="rcc-agent-desktop" id="rcc-agent-desktop"
     data-tenant="<?php echo $tenantId; ?>"
     data-agent="<?php echo $agentId; ?>"
     data-inbox-api="<?php echo htmlspecialchars($inboxApiBase, ENT_QUOTES, 'UTF-8'); ?>"
     data-ws="<?php echo htmlspecialchars($wsUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="rcc-agent-desktop__top">
        <?php include __DIR__ . '/softphone-panel.php'; ?>
    </div>
    <div class="rcc-agent-desktop__col rcc-agent-desktop__col--list">
        <div class="rcc-inbox__list" id="rcc-inbox-list"></div>
    </div>
    <div class="rcc-agent-desktop__col">
        <div class="rcc-inbox__thread" id="rcc-inbox-thread"></div>
        <div class="rcc-inbox__composer">
            <input type="text" id="rcc-inbox-reply" placeholder="Reply…" autocomplete="off">
            <button type="button" id="rcc-inbox-send">Send</button>
        </div>
    </div>
    <div class="rcc-agent-desktop__col rcc-agent-desktop__col--erp" id="rcc-inbox-erp">
        <p class="rcc-inbox__empty">Select a conversation</p>
    </div>
</div>

<script src="<?php echo htmlspecialchars($assetsBase . '/js/rcc-agent-inbox.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($assetsBase . '/js/rcc-agent-desktop-ui.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
