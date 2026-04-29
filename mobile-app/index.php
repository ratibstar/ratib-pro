<?php
declare(strict_types=1);
$appCssVersion = @filemtime(__DIR__ . '/assets/css/app.css') ?: time();
$appJsVersion = @filemtime(__DIR__ . '/assets/js/app.js') ?: time();
$qrJsVersion = @filemtime(__DIR__ . '/assets/js/html5-qrcode.min.js') ?: time();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>RATIB Worker Tracker — Recruitment Automation &amp; Tracking Intelligence Base</title>
    <meta name="theme-color" content="#0f172a">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="/mobile-app/assets/icons/icon-192.svg">
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo (int) $appCssVersion; ?>">
</head>
<body>
    <main class="app">
        <header class="card">
            <h1>Worker Tracker</h1>
            <p class="muted">Quick setup + live tracking + emergency</p>
            <div class="top">
                <span class="pill status-badge" id="quickStatus">🔴 Stopped</span>
            </div>
            <div class="row gap top">
                <button id="btnInstallApp" class="btn hidden">📲 Install App</button>
                <span class="pill" id="offlineModePill">Offline Mode: Off</span>
                <span class="pill silent-live" id="silentModePill">🟢 Live Tracking</span>
            </div>
            <p class="small hidden top" id="installReminder">📲 Install the app for better stability. <button id="btnDismissInstall" class="btn ghost">Dismiss</button></p>
        </header>

        <section class="card" id="onboardingSection">
            <h2>Device Setup</h2>
            <p class="muted small">Ask your supervisor for the QR, then tap Scan QR.</p>
            <div class="row gap action-row">
                <button id="btnStartScan" class="btn">Scan QR</button>
                <button id="btnStopScan" class="btn ghost" disabled>Stop Scan</button>
            </div>
            <div id="qrReader" class="qr-reader hidden"></div>
            <p class="muted small top">If camera is blocked, paste onboarding link/code (clipboard auto-detect also supported):</p>
            <div class="row gap action-row top">
                <input id="onboardInput" type="text" placeholder="Paste onboarding URL or code">
                <button id="btnApplyOnboard" class="btn ghost">Apply Code</button>
            </div>

            <div class="pill-wrap">
                <span class="pill" id="cfgStatus">Status: Not ready</span>
            </div>
        </section>

        <section class="card" id="trackingSection">
            <h2>Tracking</h2>
            <div class="row gap top hidden" id="advancedControls">
                <label class="toggle-label">
                    <input id="autoStartToggle" type="checkbox" checked>
                    <span>Auto Start</span>
                </label>
                <label class="toggle-label">
                    <input id="trackingLockToggle" type="checkbox">
                    <span>Tracking Lock Mode</span>
                </label>
                <button id="btnResetDevice" class="btn ghost danger">Reset Device</button>
            </div>
            <div class="row gap action-row">
                <button id="btnStartTracking" class="btn success">🟢 Start Work</button>
                <button id="btnStopTracking" class="btn ghost" disabled>🔴 Stop Work</button>
                <button id="btnFlushNow" class="btn ghost">Sync Now</button>
            </div>
            <p class="muted small" id="workHelp">Tap Start Work when shift begins. Tap Stop Work when done.</p>
            <div class="stats">
                <div><strong>Tracking:</strong> <span id="trackingState">OFF</span></div>
                <div><strong>Tracking quality:</strong> <span id="trackingQuality" class="quality-pill quality-limited">LIMITED</span></div>
                <div><strong>Connection:</strong> <span id="netState">🔴 Offline</span></div>
                <div><strong>Last update:</strong> <span id="lastUpdateAgo">-</span></div>
                <div><strong>Last sync:</strong> <span id="lastSyncAt">-</span></div>
                <div><strong>Battery:</strong> <span id="batteryLevel">-</span></div>
            </div>
            <p class="small hidden" id="trackingWarning">⚠️ Waiting for sync status...</p>
            <p class="small hidden micro-feedback" id="microFeedback"></p>
        </section>

        <section class="card emergency">
            <h2>Emergency</h2>
            <button id="btnSOS" class="btn sos sticky-sos">🆘 Emergency</button>
            <p class="muted small">Sends an immediate emergency alert.</p>
            <p class="small" id="sosStatus"></p>
        </section>

        <section class="card hidden" id="debugSection">
            <details class="manual-box">
                <summary>Debug panel</summary>
                <p class="small" id="debugPrediction">Prediction: NONE</p>
                <p class="small" id="debugRecovery">Recovery: idle</p>
                <pre id="logBox" class="log-box"></pre>
            </details>
        </section>

    </main>

    <script src="assets/js/html5-qrcode.min.js?v=<?php echo (int) $qrJsVersion; ?>"></script>
    <script src="assets/js/app.js?v=<?php echo (int) $appJsVersion; ?>"></script>
</body>
</html>

