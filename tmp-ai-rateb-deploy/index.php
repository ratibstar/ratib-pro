<?php
require __DIR__ . '/config/session.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RATEB AI — AI Campaign Generator</title>
    <link rel="stylesheet" href="/public/assets/css/theme.css">
    <link rel="stylesheet" href="/public/assets/css/app.css">
</head>
<body>
<div class="container home">
    <header>
        <div class="logo">RATEB AI</div>
        <div class="actions">
            <button type="button" id="lang" class="icon">عربي</button>
            <button type="button" id="theme" class="icon">☾</button>
            <a class="login" href="/login.php" data-i18n="login">Login</a>
            <a class="register" href="/register.php" data-i18n="create_account">Create Account</a>
        </div>
    </header>
    <main class="home-hero">
        <h1 data-i18n="hero_title">AI Campaign Generator</h1>
        <p data-i18n="hero_lead">Create marketing campaigns, social media content, WhatsApp messages and creative ideas using AI.</p>
        <a class="cta" href="/register.php" data-i18n="start_creating">Start Creating</a>
        <div class="features">
            <div class="card">
                <h3 data-i18n="feature_campaigns_title">AI Campaigns</h3>
                <p data-i18n="feature_campaigns_body">Generate complete marketing campaign ideas from your product or service.</p>
            </div>
            <div class="card">
                <h3 data-i18n="feature_social_title">Social Media</h3>
                <p data-i18n="feature_social_body">Create ready-to-use posts and promotional content for social platforms.</p>
            </div>
            <div class="card">
                <h3 data-i18n="feature_whatsapp_title">WhatsApp</h3>
                <p data-i18n="feature_whatsapp_body">Generate promotional WhatsApp messages designed for your target customers.</p>
            </div>
        </div>
    </main>
</div>
<script src="/public/assets/js/app.js"></script>
</body>
</html>
