<?php
require __DIR__ . '/config/session.php';
require __DIR__ . '/config/guard.php';
require __DIR__ . '/app/nav.php';
$googleClient = (string) ((require __DIR__ . '/config/google.php')['client_id'] ?? '');

$loggedIn = isset($_SESSION['user_id']);
if ($loggedIn && !isset($_GET['pricing'])) {
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
    <link rel="stylesheet" href="/public/assets/css/app.css?v=nav42">
</head>
<body class="studio home">
<?php rateb_site_header('', $loggedIn, !$loggedIn); ?>
<main>
    <section class="landing-hero">
        <div class="landing-copy">
            <p class="eyebrow" data-i18n="workspace">AI CAMPAIGN WORKSPACE</p>
            <h1 data-i18n="studio_headline">Turn your idea into a complete marketing campaign with AI</h1>
            <p class="studio-lead" data-i18n="studio_lead">Strategy, ad copy, social posts, images, Saudi Arabic voice, and a content plan — in one creative studio.</p>
            <div class="landing-actions">
                <button type="button" class="button studio-cta" data-auth="register" data-i18n="studio_cta">Start a new campaign</button>
                <a class="button quiet" href="#workflow" data-i18n="home_how">See how it works</a>
            </div>
        </div>
        <div class="collage">
            <article class="piece piece-ad float-a demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-perfume.jpg">
                <span class="demo-tag" data-i18n="demo_example">Example</span>
                <img class="demo-thumb" src="/public/assets/demo/demo-perfume.jpg" alt="">
                <strong data-i18n="sample_ad">A quiet evening fragrance, with a soft oud note.</strong>
            </article>
            <article class="piece piece-social float-b demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-social.jpg">
                <span class="demo-tag" data-i18n="demo_example">Example</span>
                <div class="social-top"><i></i><i></i><i></i></div>
                <img class="demo-thumb" src="/public/assets/demo/demo-social.jpg" alt="">
                <p data-i18n="sample_social">Hosting is easier now. Order today, delivery in Riyadh.</p>
            </article>
            <article class="piece piece-phone float-c demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-story.jpg">
                <span class="demo-tag" data-i18n="demo_example">Example</span>
                <div class="phone-screen">
                    <b data-i18n="sample_story">Today’s offer</b>
                    <span data-i18n="sample_product">Hospitality set</span>
                </div>
            </article>
            <article class="piece piece-card float-d demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-campaign.jpg">
                <span class="demo-tag" data-i18n="demo_example">Example</span>
                <b data-i18n="sample_card">Campaign brief</b>
                <span class="line"></span>
                <span class="line short"></span>
            </article>
            <article class="piece piece-video float-e demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/reel-story.mp4">
                <span class="demo-tag" data-i18n="demo_example">Example</span>
                <span class="mini-play"></span>
            </article>
        </div>
    </section>

    <section class="home-section" id="samples">
        <div class="section-head"><h2 data-i18n="sample_title">Examples you can view and hear</h2></div>
        <h3 data-i18n="sample_photos">Pictures</h3>
        <div class="sample-grid">
            <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-perfume.jpg"><img src="/public/assets/demo/demo-perfume.jpg" alt=""><span data-i18n="video_product">Product film</span></article>
            <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-hospitality.jpg"><img src="/public/assets/demo/demo-hospitality.jpg" alt=""><span data-i18n="video_social">Hospitality film</span></article>
            <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-social.jpg"><img src="/public/assets/demo/demo-social.jpg" alt=""><span data-i18n="format_instagram">Instagram</span></article>
            <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-evening.jpg"><img src="/public/assets/demo/demo-evening.jpg" alt=""><span data-i18n="sample_product">Hospitality set</span></article>
            <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-story.jpg"><img src="/public/assets/demo/demo-story.jpg" alt=""><span data-i18n="format_stories">Stories</span></article>
            <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-campaign.jpg"><img src="/public/assets/demo/demo-campaign.jpg" alt=""><span data-i18n="show_strategy_title">AI campaign strategy</span></article>
        </div>
        <h3 data-i18n="sample_videos">Videos</h3>
        <div class="sample-grid sample-videos">
            <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/reel-product.mp4"><img src="/public/assets/demo/demo-perfume.jpg" alt=""><span class="mini-play"></span><span data-i18n="video_product">Product film</span></article>
            <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/reel-social.mp4"><img src="/public/assets/demo/demo-hospitality.jpg" alt=""><span class="mini-play"></span><span data-i18n="video_social">Hospitality film</span></article>
            <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/reel-story.mp4"><img src="/public/assets/demo/demo-story.jpg" alt=""><span class="mini-play"></span><span data-i18n="video_story">Vertical story</span></article>
        </div>
        <h3 data-i18n="sample_voices">Voices</h3>
        <div class="voice-library" data-voice-library>
          <div class="voice-filters">
            <input type="search" data-voice-search placeholder="ابحث بالاسم">
            <select data-voice-country><option value="">اختر الدولة</option></select>
            <select data-voice-dialect disabled><option value="">اختر اللهجة</option></select>
          </div>
          <p class="muted" data-voice-note></p>
          <div class="voice-picks" data-voice-picks></div>
          <audio data-voice-player controls preload="none" hidden></audio>
        </div>
    </section>

    <section class="home-section" id="create">
        <div class="section-head"><h2 data-i18n="home_create_title">What can RATEB AI create?</h2></div>
        <div class="showcase home-showcase">
            <article class="showcase-card show-strategy demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-campaign.jpg">
                <div class="preview preview-strategy"><span></span><span></span><span></span></div>
                <span class="show-mark">01</span>
                <h3 data-i18n="show_strategy_title">AI campaign strategy</h3>
                <p data-i18n="show_strategy_body">A clear plan for the offer, audience, and message.</p>
            </article>
            <article class="showcase-card show-copy demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-perfume.jpg">
                <div class="preview preview-copy"><b data-i18n="sample_ad">A quiet evening fragrance, with a soft oud note.</b><i></i></div>
                <span class="show-mark">02</span>
                <h3 data-i18n="show_copy_title">Ad copy</h3>
                <p data-i18n="show_copy_body">Ready lines for ads, landing pages, and offers.</p>
            </article>
            <article class="showcase-card show-social demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-social.jpg">
                <div class="preview preview-post"><span></span><em data-i18n="sample_social">Hosting is easier now. Order today, delivery in Riyadh.</em></div>
                <span class="show-mark">03</span>
                <h3 data-i18n="show_social_title">Social posts</h3>
                <p data-i18n="show_social_body">Posts shaped for the platforms your customers use.</p>
            </article>
            <article class="showcase-card show-images demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-hospitality.jpg">
                <div class="preview preview-image"><span class="orb"></span><span class="orb small"></span></div>
                <span class="show-mark">04</span>
                <h3 data-i18n="show_images_title">AI images</h3>
                <p data-i18n="show_images_body">Campaign visuals generated for this brand.</p>
            </article>
            <article class="showcase-card show-voice demo-hit" tabindex="0" role="button" data-kind="audio" data-src="/public/assets/demo/voice-calm.mp3">
                <div class="preview preview-wave"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
                <span class="show-mark">05</span>
                <h3 data-i18n="show_voice_title">Saudi Arabic voice</h3>
                <p data-i18n="show_voice_body">A natural Saudi Arabic voice-over from your script.</p>
            </article>
            <article class="showcase-card show-video demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/reel-product.mp4">
                <div class="preview preview-reel"><span class="mini-play"></span></div>
                <span class="show-mark">06</span>
                <h3 data-i18n="show_video_title">Video ideas</h3>
                <p data-i18n="show_video_body">Shot ideas and scripts. Video rendering stays unavailable.</p>
            </article>
            <article class="showcase-card show-planner demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-evening.jpg">
                <div class="preview preview-cal"><i></i><i></i><i class="on"></i><i></i><i></i><i></i><i></i></div>
                <span class="show-mark">07</span>
                <h3 data-i18n="show_planner_title">Content planner</h3>
                <p data-i18n="show_planner_body">Dates, drafts, and approvals for what goes live next.</p>
            </article>
        </div>
    </section>

    <section class="home-section" id="pricing">
        <div class="section-head"><h2 data-i18n="pricing_title">Plans</h2></div>
        <div class="price-grid">
            <article class="price-card">
                <p class="eyebrow" data-i18n="plan_free">Free trial</p>
                <p class="price-amount"><b>0</b> <span>SAR</span></p>
                <p data-i18n="plan_free_credits">50 welcoming credits</p>
                <ul>
                    <li data-i18n="plan_free_text">Text modules</li>
                    <li data-i18n="plan_free_image">Square images 1:1</li>
                    <li><span class="badge" data-i18n="plan_upgrade">Saudi voice and two short videos a month included</span></li>
                </ul>
                <button type="button" class="button" data-auth="register" data-i18n="plan_cta">Subscribe now</button>
            </article>
            <article class="price-card is-featured">
                <p class="eyebrow" data-i18n="plan_starter">Starter</p>
                <p class="price-amount"><b>99</b> <span data-i18n="plan_month">SAR / month</span></p>
                <p data-i18n="plan_starter_credits">500 credits</p>
                <ul>
                    <li data-i18n="plan_starter_platforms">All platforms unlocked</li>
                    <li data-i18n="plan_starter_ratios">Flexible image ratios</li>
                    <li data-i18n="plan_starter_voice">3 voice tones</li>
                </ul>
                <?php if ($loggedIn): ?><a class="button studio-cta" href="/app/payment/checkout.php?package=starter" data-i18n="plan_cta">Subscribe now</a><?php else: ?><button type="button" class="button studio-cta" data-auth="register" data-i18n="plan_cta">Subscribe now</button><?php endif; ?>
            </article>
            <article class="price-card">
                <p class="eyebrow" data-i18n="plan_pro">Pro</p>
                <p class="price-amount"><b>249</b> <span data-i18n="plan_month">SAR / month</span></p>
                <p data-i18n="plan_pro_credits">2500 credits</p>
                <ul>
                    <li data-i18n="plan_pro_text">Unlimited text variations</li>
                    <li data-i18n="plan_pro_board">Full structured storyboard</li>
                    <li data-i18n="plan_pro_priority">Priority generation</li>
                </ul>
                <?php if ($loggedIn): ?><a class="button" href="/app/payment/checkout.php?package=pro" data-i18n="plan_cta">Subscribe now</a><?php else: ?><button type="button" class="button" data-auth="register" data-i18n="plan_cta">Subscribe now</button><?php endif; ?>
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
            <div class="video-stage demo-hit" id="video-stage" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/reel-social.mp4">
                <div class="reel-frame"></div>
                <span class="play-demo">
                    <span class="mini-play"></span>
                    <span data-i18n="play_example">Play example</span>
                </span>
                <p class="demo-tag" data-i18n="demo_example">Example</p>
            </div>
            <div class="creative-grid">
                <article class="creative tile-poster demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-hospitality.jpg"><span class="demo-tag" data-i18n="demo_example">Example</span><b data-i18n="sample_product">Hospitality set</b></article>
                <article class="creative tile-social demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-social.jpg"><span class="demo-tag" data-i18n="demo_example">Example</span><b data-i18n="format_instagram">Instagram</b></article>
                <article class="creative tile-arabic demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-story.jpg"><span class="demo-tag" data-i18n="demo_example">Example</span><b data-i18n="sample_story">Today’s offer</b></article>
                <article class="creative tile-pack demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-perfume.jpg"><span class="demo-tag" data-i18n="demo_example">Example</span><b data-i18n="format_product">Product ad</b></article>
            </div>
        </div>
    </section>

    <section class="home-section" id="workflow">
        <div class="section-head"><h2 data-i18n="home_how">See how it works</h2></div>
        <ol class="flow-board">
            <li class="demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-campaign.jpg"><span class="node">1</span><b data-i18n="flow_idea">Idea</b></li>
            <li class="demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-campaign.jpg"><span class="node">2</span><b data-i18n="flow_strategy">Strategy</b></li>
            <li class="demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-perfume.jpg"><span class="node">3</span><b data-i18n="flow_copy">Copy</b></li>
            <li class="demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-hospitality.jpg"><span class="node">4</span><b data-i18n="flow_images">Images</b></li>
            <li class="demo-hit" tabindex="0" role="button" data-kind="audio" data-src="/public/assets/demo/voice-calm.mp3"><span class="node">5</span><b data-i18n="flow_voice">Voice</b></li>
            <li class="demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/reel-product.mp4"><span class="node">6</span><b data-i18n="flow_video">Video</b></li>
            <li class="demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-evening.jpg"><span class="node">7</span><b data-i18n="flow_ready">Ready campaign</b></li>
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
            <article class="format format-ig demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-social.jpg"><span data-i18n="format_instagram">Instagram</span><p data-i18n="sample_social">Hosting is easier now. Order today, delivery in Riyadh.</p></article>
            <article class="format format-tt demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/reel-story.mp4"><span data-i18n="format_tiktok">TikTok</span><span class="mini-play"></span><small data-i18n="demo_example">Example</small></article>
            <article class="format format-wa demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-hospitality.jpg"><span data-i18n="format_whatsapp">WhatsApp</span><p data-i18n="sample_wa">Hello. This weekend’s hosting bundle is on offer. Shall I send the details?</p></article>
            <article class="format format-story demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-story.jpg"><span data-i18n="format_stories">Stories</span><b data-i18n="sample_story">Today’s offer</b></article>
            <article class="format format-feed demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-perfume.jpg"><span data-i18n="format_feed">Feed ad</span><p data-i18n="sample_ad">A quiet evening fragrance, with a soft oud note.</p></article>
            <article class="format format-product demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-hospitality.jpg"><span data-i18n="format_product">Product ad</span><div class="product-art"></div></article>
            <article class="format format-short demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/reel-social.mp4"><span data-i18n="format_short">Short video</span><small data-i18n="gallery_note">Visual examples only. Video generation is not available yet.</small></article>
        </div>
    </section>

    <section class="landing-close">
        <h2 data-i18n="studio_headline">Turn your idea into a complete marketing campaign with AI</h2>
        <button type="button" class="button studio-cta" data-auth="register" data-i18n="studio_cta">Start a new campaign</button>
    </section>
</main>
<dialog class="demo-viewer" id="demo-viewer">
    <form method="dialog" class="demo-bar">
        <p data-i18n="demo_example">Example</p>
        <button type="submit" class="icon" data-i18n="demo_close">Close</button>
    </form>
    <div id="demo-stage"></div>
</dialog>
<dialog class="auth-modal" id="auth-modal" data-google-client="<?= e($googleClient) ?>">
    <button type="button" class="auth-x" data-auth-close aria-label="Close">×</button>
    <form class="auth-card" id="auth-login" action="/login.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="auth_modal" value="1">
        <input type="hidden" name="next" value="/dashboard.php">
        <h2 data-i18n="welcome_back">Welcome back</h2>
        <p class="muted" data-i18n="login_lead">Login to your RATEB AI Campaign workspace.</p>
        <p class="alert" data-auth-error hidden></p>
        <div class="social-auth">
            <button type="button" class="social-btn" data-provider="google"><span class="social-mark" aria-hidden="true"><svg viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 8 3.1l5.7-5.7C34.2 6.1 29.4 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.2-.1-2.3-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 12 24 12c3.1 0 5.8 1.2 8 3.1l5.7-5.7C34.2 6.1 29.4 4 24 4 16.3 4 9.6 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 10-2 13.6-5.2l-6.3-5.3C29.3 35.1 26.8 36 24 36c-5.3 0-9.7-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1.1 3.2-3.5 5.7-6.6 7.1l6.3 5.3C37.4 38.4 44 33 44 24c0-1.2-.1-2.3-.4-3.5z"/></svg></span><span data-i18n="continue_google">Continue with Google</span><span></span></button>
            <button type="button" class="social-btn" data-provider="github"><span class="social-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.6 2 12.2c0 4.5 2.9 8.3 6.9 9.6.5.1.7-.2.7-.5v-1.7c-2.8.6-3.4-1.2-3.4-1.2-.4-1.1-1.1-1.4-1.1-1.4-.9-.6.1-.6.1-.6 1 .1 1.5 1 1.5 1 .9 1.6 2.4 1.1 3 .9.1-.7.4-1.1.6-1.4-2.2-.3-4.6-1.1-4.6-5 0-1.1.4-2 1-2.7-.1-.3-.4-1.3.1-2.7 0 0 .8-.3 2.8 1a9.4 9.4 0 0 1 5 0c2-1.3 2.8-1 2.8-1 .5 1.4.2 2.4.1 2.7.6.7 1 1.6 1 2.7 0 3.9-2.3 4.7-4.6 5 .4.3.7.9.7 1.9v2.8c0 .3.2.6.7.5 4-1.3 6.9-5.1 6.9-9.6C22 6.6 17.5 2 12 2z"/></svg></span><span data-i18n="continue_github">Continue with GitHub</span><span></span></button>
        </div>
        <p class="auth-or" data-i18n="auth_or">or</p>
        <label data-i18n="email">Email</label>
        <input type="email" name="email" required autocomplete="email">
        <label data-i18n="password">Password</label>
        <input type="password" name="password" required autocomplete="current-password">
        <button type="submit" data-i18n="login_button">Login</button>
        <p class="footer-link"><a href="/forgot-password.php" data-i18n="forgot_password">Forgot password?</a></p>
        <p class="footer-link"><span data-i18n="no_account">Don't have an account?</span> <button type="button" data-auth="register" data-i18n="create_one">Create one</button></p>
    </form>
    <form class="auth-card" id="auth-register" action="/register.php" method="post" hidden>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="auth_modal" value="1">
        <input type="hidden" name="next" value="/dashboard.php">
        <h2 data-i18n="create_title">Create your account</h2>
        <p class="muted" data-i18n="register_lead">Start creating AI-powered marketing campaigns.</p>
        <p class="alert" data-auth-error hidden></p>
        <div class="social-auth">
            <button type="button" class="social-btn" data-provider="google"><span class="social-mark" aria-hidden="true"><svg viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 8 3.1l5.7-5.7C34.2 6.1 29.4 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.2-.1-2.3-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 12 24 12c3.1 0 5.8 1.2 8 3.1l5.7-5.7C34.2 6.1 29.4 4 24 4 16.3 4 9.6 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 10-2 13.6-5.2l-6.3-5.3C29.3 35.1 26.8 36 24 36c-5.3 0-9.7-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1.1 3.2-3.5 5.7-6.6 7.1l6.3 5.3C37.4 38.4 44 33 44 24c0-1.2-.1-2.3-.4-3.5z"/></svg></span><span data-i18n="continue_google">Continue with Google</span><span></span></button>
            <button type="button" class="social-btn" data-provider="github"><span class="social-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.6 2 12.2c0 4.5 2.9 8.3 6.9 9.6.5.1.7-.2.7-.5v-1.7c-2.8.6-3.4-1.2-3.4-1.2-.4-1.1-1.1-1.4-1.1-1.4-.9-.6.1-.6.1-.6 1 .1 1.5 1 1.5 1 .9 1.6 2.4 1.1 3 .9.1-.7.4-1.1.6-1.4-2.2-.3-4.6-1.1-4.6-5 0-1.1.4-2 1-2.7-.1-.3-.4-1.3.1-2.7 0 0 .8-.3 2.8 1a9.4 9.4 0 0 1 5 0c2-1.3 2.8-1 2.8-1 .5 1.4.2 2.4.1 2.7.6.7 1 1.6 1 2.7 0 3.9-2.3 4.7-4.6 5 .4.3.7.9.7 1.9v2.8c0 .3.2.6.7.5 4-1.3 6.9-5.1 6.9-9.6C22 6.6 17.5 2 12 2z"/></svg></span><span data-i18n="continue_github">Continue with GitHub</span><span></span></button>
        </div>
        <p class="auth-or" data-i18n="auth_or">or</p>
        <label data-i18n="name">Name</label>
        <input name="name" required autocomplete="name">
        <label data-i18n="email">Email</label>
        <input type="email" name="email" required autocomplete="email">
        <label data-i18n="password">Password</label>
        <input type="password" name="password" minlength="8" required autocomplete="new-password">
        <button type="submit" data-i18n="create_button">Create Account</button>
        <p class="footer-link"><span data-i18n="have_account">Already have an account?</span> <button type="button" data-auth="login" data-i18n="login">Login</button></p>
    </form>
</dialog>
<?php if ($googleClient !== ''): ?><script src="https://accounts.google.com/gsi/client" async defer></script><?php endif; ?>
<script src="/public/assets/js/app.js?v=nav40"></script>
</body>
</html>
