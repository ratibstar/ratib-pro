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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
    <link rel="stylesheet" href="/public/assets/css/theme.css">
    <link rel="stylesheet" href="/public/assets/css/app.css">
</head>
<body class="studio home">
<header class="home-bar">
    <a class="logo" href="/">RATEB <span>AI</span></a>
    <div class="actions">
        <button type="button" id="lang" class="icon">عربي</button>
        <button type="button" id="theme" class="icon">☾</button>
        <a class="login" href="/login.php" data-i18n="login">Login</a>
        <a class="register" href="/register.php" data-i18n="create_account">Create Account</a>
    </div>
</header>
<main>
    <section class="landing-hero">
        <div class="landing-copy">
            <p class="eyebrow" data-i18n="workspace">AI CAMPAIGN WORKSPACE</p>
            <h1 data-i18n="studio_headline">Turn your idea into a complete marketing campaign with AI</h1>
            <p class="studio-lead" data-i18n="studio_lead">Strategy, ad copy, social posts, images, Saudi Arabic voice, and a content plan — in one creative studio.</p>
            <div class="landing-actions">
                <a class="button studio-cta" href="/register.php" data-i18n="studio_cta">Start a new campaign</a>
                <a class="button quiet" href="#workflow" data-i18n="home_how">See how it works</a>
            </div>
        </div>
        <div class="collage">
            <article class="piece piece-ad float-a demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/product.svg">
                <span class="demo-tag" data-i18n="demo_example">Example</span>
                <div class="product-art"></div>
                <strong data-i18n="sample_ad">A quiet evening fragrance, with a soft oud note.</strong>
            </article>
            <article class="piece piece-social float-b demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/social.svg">
                <span class="demo-tag" data-i18n="demo_example">Example</span>
                <div class="social-top"><i></i><i></i><i></i></div>
                <div class="social-photo"></div>
                <p data-i18n="sample_social">Hosting is easier now. Order today, delivery in Riyadh.</p>
            </article>
            <article class="piece piece-phone float-c demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/story.svg">
                <span class="demo-tag" data-i18n="demo_example">Example</span>
                <div class="phone-screen">
                    <b data-i18n="sample_story">Today’s offer</b>
                    <span data-i18n="sample_product">Hospitality set</span>
                </div>
            </article>
            <article class="piece piece-card float-d demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/strategy.svg">
                <span class="demo-tag" data-i18n="demo_example">Example</span>
                <b data-i18n="sample_card">Campaign brief</b>
                <span class="line"></span>
                <span class="line short"></span>
            </article>
            <article class="piece piece-video float-e demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/demo-video.mp4">
                <span class="demo-tag" data-i18n="demo_example">Example</span>
                <span class="mini-play"></span>
            </article>
        </div>
    </section>

    <section class="home-section" id="create">
        <div class="section-head"><h2 data-i18n="home_create_title">What can RATEB AI create?</h2></div>
        <div class="showcase home-showcase">
            <article class="showcase-card show-strategy demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/strategy.svg">
                <div class="preview preview-strategy"><span></span><span></span><span></span></div>
                <span class="show-mark">01</span>
                <h3 data-i18n="show_strategy_title">AI campaign strategy</h3>
                <p data-i18n="show_strategy_body">A clear plan for the offer, audience, and message.</p>
            </article>
            <article class="showcase-card show-copy demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/product.svg">
                <div class="preview preview-copy"><b data-i18n="sample_ad">A quiet evening fragrance, with a soft oud note.</b><i></i></div>
                <span class="show-mark">02</span>
                <h3 data-i18n="show_copy_title">Ad copy</h3>
                <p data-i18n="show_copy_body">Ready lines for ads, landing pages, and offers.</p>
            </article>
            <article class="showcase-card show-social demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/social.svg">
                <div class="preview preview-post"><span></span><em data-i18n="sample_social">Hosting is easier now. Order today, delivery in Riyadh.</em></div>
                <span class="show-mark">03</span>
                <h3 data-i18n="show_social_title">Social posts</h3>
                <p data-i18n="show_social_body">Posts shaped for the platforms your customers use.</p>
            </article>
            <article class="showcase-card show-images demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/poster.svg">
                <div class="preview preview-image"><span class="orb"></span><span class="orb small"></span></div>
                <span class="show-mark">04</span>
                <h3 data-i18n="show_images_title">AI images</h3>
                <p data-i18n="show_images_body">Campaign visuals generated for this brand.</p>
            </article>
            <article class="showcase-card show-voice demo-hit" tabindex="0" role="button" data-kind="audio" data-src="/public/assets/demo/demo-voice.mp3">
                <div class="preview preview-wave"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
                <span class="show-mark">05</span>
                <h3 data-i18n="show_voice_title">Saudi Arabic voice</h3>
                <p data-i18n="show_voice_body">A natural Saudi Arabic voice-over from your script.</p>
            </article>
            <article class="showcase-card show-video demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/demo-video.mp4">
                <div class="preview preview-reel"><span class="mini-play"></span></div>
                <span class="show-mark">06</span>
                <h3 data-i18n="show_video_title">Video ideas</h3>
                <p data-i18n="show_video_body">Shot ideas and scripts. Video rendering stays unavailable.</p>
            </article>
            <article class="showcase-card show-planner demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/planner.svg">
                <div class="preview preview-cal"><i></i><i></i><i class="on"></i><i></i><i></i><i></i><i></i></div>
                <span class="show-mark">07</span>
                <h3 data-i18n="show_planner_title">Content planner</h3>
                <p data-i18n="show_planner_body">Dates, drafts, and approvals for what goes live next.</p>
            </article>
        </div>
    </section>

    <section class="home-section" id="gallery">
        <div class="section-head">
            <div>
                <h2 data-i18n="gallery_title">See what RATEB AI can make</h2>
                <p class="muted" data-i18n="gallery_note">Visual examples only. Video generation is not available yet.</p>
            </div>
        </div>
        <div class="gallery-layout">
            <div class="video-stage demo-hit" id="video-stage" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/demo-video.mp4">
                <div class="reel-frame"></div>
                <span class="play-demo">
                    <span class="mini-play"></span>
                    <span data-i18n="play_example">Play example</span>
                </span>
                <p class="demo-tag" data-i18n="demo_example">Example</p>
            </div>
            <div class="creative-grid">
                <article class="creative tile-poster demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/poster.svg"><span class="demo-tag" data-i18n="demo_example">Example</span><b data-i18n="sample_product">Hospitality set</b></article>
                <article class="creative tile-social demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/social.svg"><span class="demo-tag" data-i18n="demo_example">Example</span><b data-i18n="format_instagram">Instagram</b></article>
                <article class="creative tile-arabic demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/story.svg"><span class="demo-tag" data-i18n="demo_example">Example</span><b data-i18n="sample_story">Today’s offer</b></article>
                <article class="creative tile-pack demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/product.svg"><span class="demo-tag" data-i18n="demo_example">Example</span><b data-i18n="format_product">Product ad</b></article>
            </div>
        </div>
    </section>

    <section class="home-section" id="workflow">
        <div class="section-head"><h2 data-i18n="home_how">See how it works</h2></div>
        <ol class="flow-board">
            <li class="demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/strategy.svg"><span class="node">1</span><b data-i18n="flow_idea">Idea</b></li>
            <li class="demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/strategy.svg"><span class="node">2</span><b data-i18n="flow_strategy">Strategy</b></li>
            <li class="demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/product.svg"><span class="node">3</span><b data-i18n="flow_copy">Copy</b></li>
            <li class="demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/poster.svg"><span class="node">4</span><b data-i18n="flow_images">Images</b></li>
            <li class="demo-hit" tabindex="0" role="button" data-kind="audio" data-src="/public/assets/demo/demo-voice.mp3"><span class="node">5</span><b data-i18n="flow_voice">Voice</b></li>
            <li class="demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/demo-video.mp4"><span class="node">6</span><b data-i18n="flow_video">Video</b></li>
            <li class="demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/planner.svg"><span class="node">7</span><b data-i18n="flow_ready">Ready campaign</b></li>
        </ol>
    </section>

    <section class="home-section" id="workspace">
        <div class="section-head"><h2 data-i18n="workspace_title">The real campaign workspace</h2></div>
        <div class="mock-grid">
            <article class="mock-screen"><span data-i18n="tab_overview">Overview</span><b data-i18n="campaign_details">CAMPAIGN DETAILS</b><i></i><i class="short"></i></article>
            <article class="mock-screen"><span data-i18n="tab_ai">AI Content</span><b data-i18n="type_ad_copy">Ad Copy</b><i></i><i></i></article>
            <article class="mock-screen"><span data-i18n="tab_media">Media</span><div class="mock-media"></div></article>
            <article class="mock-screen"><span data-i18n="tab_planner">Planner</span><div class="preview-cal"><i></i><i class="on"></i><i></i><i></i><i></i></div></article>
            <article class="mock-screen"><span data-i18n="tab_variations">Variations</span><b data-i18n="variations">Content variations</b><i></i></article>
            <article class="mock-screen"><span data-i18n="tab_brand">Brand</span><b data-i18n="brand_kit_title">Brand kit</b><em class="swatch"></em><em class="swatch alt"></em></article>
        </div>
    </section>

    <section class="home-section" id="formats">
        <div class="section-head"><h2 data-i18n="formats_title">Formats your campaign can speak in</h2></div>
        <div class="format-grid">
            <article class="format format-ig demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/social.svg"><span data-i18n="format_instagram">Instagram</span><p data-i18n="sample_social">Hosting is easier now. Order today, delivery in Riyadh.</p></article>
            <article class="format format-tt demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/demo-video.mp4"><span data-i18n="format_tiktok">TikTok</span><span class="mini-play"></span><small data-i18n="demo_example">Example</small></article>
            <article class="format format-wa demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/whatsapp.svg"><span data-i18n="format_whatsapp">WhatsApp</span><p data-i18n="sample_wa">Hello. This weekend’s hosting bundle is on offer. Shall I send the details?</p></article>
            <article class="format format-story demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/story.svg"><span data-i18n="format_stories">Stories</span><b data-i18n="sample_story">Today’s offer</b></article>
            <article class="format format-feed demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/product.svg"><span data-i18n="format_feed">Feed ad</span><p data-i18n="sample_ad">A quiet evening fragrance, with a soft oud note.</p></article>
            <article class="format format-product demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/poster.svg"><span data-i18n="format_product">Product ad</span><div class="product-art"></div></article>
            <article class="format format-short demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/demo-video.mp4"><span data-i18n="format_short">Short video</span><small data-i18n="gallery_note">Visual examples only. Video generation is not available yet.</small></article>
        </div>
    </section>

    <section class="landing-close">
        <h2 data-i18n="studio_headline">Turn your idea into a complete marketing campaign with AI</h2>
        <a class="button studio-cta" href="/register.php" data-i18n="studio_cta">Start a new campaign</a>
    </section>
</main>
<dialog class="demo-viewer" id="demo-viewer">
    <form method="dialog" class="demo-bar">
        <p data-i18n="demo_example">Example</p>
        <button type="submit" class="icon" data-i18n="demo_close">Close</button>
    </form>
    <div id="demo-stage"></div>
</dialog>
<script src="/public/assets/js/app.js"></script>
</body>
</html>
