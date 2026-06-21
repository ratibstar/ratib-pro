<?php
declare(strict_types=1);

/**
 * RCC static asset registry — ASCII keys only (avoids RTL/bidi URL corruption).
 *
 * @return array<string, string>
 */
return [
    'inbox-css' => 'css/rcc-agent-inbox.css',
    'softphone-css' => 'css/rcc-sp.css',
    'copilot-css' => 'css/rcc-ai-copilot.css',
    'realtime-js' => 'js/rcc-realtime-client.js',
    'softphone-js' => 'js/rcc-sp.js',
    'softphone-ui-js' => 'js/rcc-sp-ui.js',
    'inbox-js' => 'js/rcc-agent-inbox.js',
    'copilot-js' => 'js/rcc-ai-copilot.js',
    'desktop-js' => 'js/rcc-agent-desktop-ui.js',
];
