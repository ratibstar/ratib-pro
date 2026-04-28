<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/core/ControlCenterAccess.php';

if (!ControlCenterAccess::canAccessControlCenter()) {
    http_response_code(403);
    echo '<h1>403 Forbidden</h1><p>Control center access required.</p>';
    exit;
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>SOC Real-time Control Dashboard</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <link rel="stylesheet" href="assets/dashboard.css?v=3">
</head>
<body>
<div class="soc-shell">
    <header class="soc-header panel">
        <div>
            <h1>SOC Real-time Control Dashboard</h1>
            <p class="sub">Frontend-only monitoring · local telemetry driven</p>
        </div>
        <div class="head-controls">
            <input id="workerSearch" class="input" type="search" placeholder="Search worker by name/id">
            <select id="statusFilter" class="input">
                <option value="ALL">All status</option>
                <option value="GOOD">GOOD</option>
                <option value="LIMITED">LIMITED</option>
                <option value="POOR">POOR</option>
            </select>
            <button id="platformAll" class="btn" type="button" onclick="if(window.SOCDashboardControls){window.SOCDashboardControls.setPlatform('ALL');}">All</button>
            <button id="platformAndroid" class="btn" type="button" onclick="if(window.SOCDashboardControls){window.SOCDashboardControls.setPlatform('ANDROID');}">Android</button>
            <button id="platformIOS" class="btn" type="button" onclick="if(window.SOCDashboardControls){window.SOCDashboardControls.setPlatform('IOS');}">iOS</button>
            <button id="focusToggle" class="btn" type="button" onclick="if(window.SOCDashboardControls){window.SOCDashboardControls.toggleFocus();}">Focus: OFF</button>
        </div>
    </header>

    <section class="top-stats" id="topStats">
        <article class="stat panel"><span>Total workers</span><strong id="sTotal">0</strong></article>
        <article class="stat panel"><span>% GOOD</span><strong id="sGood">0%</strong></article>
        <article class="stat panel"><span>% LIMITED</span><strong id="sLimited">0%</strong></article>
        <article class="stat panel"><span>% POOR</span><strong id="sPoor">0%</strong></article>
        <article class="stat panel"><span>Recoveries</span><strong id="sRecoveries">0</strong></article>
        <article class="stat panel"><span>Prediction triggers</span><strong id="sPredictions">0</strong></article>
        <article class="stat panel"><span>Threat indicator</span><strong id="sThreat" class="risk-low">LOW</strong></article>
    </section>

    <section class="soc-main">
        <aside class="panel workers-panel">
            <div class="panel-head">
                <h2>Workers</h2>
                <span class="small" id="workersStamp">-</span>
            </div>
            <div class="worker-list" id="workersList"></div>
        </aside>

        <div class="panel map-panel">
            <div id="map"></div>
            <div class="details" id="workerDetails">
                <div class="small">Select worker from map/list.</div>
            </div>
        </div>
    </section>

    <section class="panel alerts-panel">
        <div class="panel-head">
            <h2>Live Alerts Stream</h2>
            <span class="small">INFO / WARNING / CRITICAL</span>
        </div>
        <ul class="alerts-list" id="alertsList"></ul>
    </section>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="assets/dashboard.js?v=8"></script>
</body>
</html>
