<?php
declare(strict_types=1);

/**
 * RCC static asset registry — ASCII keys only (avoids RTL/bidi URL corruption).
 *
 * @return array<string, string>
 */
return [
    'inbox-css' => 'css/rcc-agent-inbox.css',
    'softphone-css' => 'css/rcc-softphone.css',
    'copilot-css' => 'css/rcc-ai-copilot.css',
    'realtime-js' => 'js/rcc-realtime-client.js',
    'softphone-js' => 'js/rcc-softphone.js',
    'softphone-ui-js' => 'js/rcc-softphone-ui.js',
    'inbox-js' => 'js/rcc-agent-inbox.js',
    'copilot-js' => 'js/rcc-ai-copilot.js',
    'desktop-js' => 'js/rcc-agent-desktop-ui.js',
    'ops-css' => 'css/rcc-ops-center.css',
    'ops-js' => 'js/rcc-ops-center.js',
    'supervisor-css' => 'css/rcc-supervisor-center.css',
    'supervisor-js' => 'js/rcc-supervisor-center.js',
];
