<?php
declare(strict_types=1);
/** Video band + program preview strip (ported from pages/home.php hero area). */
if (!empty($ratebShowHomeVideoBand)) {
    ?>
            <div class="rateb-hero__video-band video-section rateb-video rateb-video--hero" id="video">
                <div class="rateb-container">
                    <header class="rateb-hero__video-head rateb-section__head rateb-section__head--left">
                        <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.video.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <h2 class="rateb-section__title rateb-hero__video-title"><?php echo htmlspecialchars($ratebHome['home.video.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="video-caption"><?php echo htmlspecialchars($ratebHome['home.video.caption'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    </header>
                    <?php if (!empty($ratebVideoSources)) { ?>
                    <div class="rateb-cms-media-strip rateb-cms-media-strip--video" role="region" aria-label="<?php echo htmlspecialchars($ratebHome['home.video.title'] ?? 'Videos', ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="rateb-cms-media-strip__track">
                            <?php foreach ($ratebVideoSources as $rvSlot) {
                                $rvSrc = is_array($rvSlot) ? (string) ($rvSlot['url'] ?? '') : (string) $rvSlot;
                                $rvIsImage = is_array($rvSlot) && !empty($rvSlot['is_image']);
                                ?>
                            <div class="rateb-cms-media-strip__item rateb-cms-media-strip__item--video">
                                <div class="video-wrap rateb-cms-media-strip__video-wrap">
                                    <?php if ($rvIsImage) { ?>
                                    <img src="<?php echo htmlspecialchars($rvSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="home-video-player rateb-cms-media-strip__video rateb-cms-media-strip__still" loading="lazy" decoding="async">
                                    <?php } else { ?>
                                    <video controls preload="metadata" class="home-video-player rateb-cms-media-strip__video" playsinline>
                                        <source src="<?php echo htmlspecialchars($rvSrc, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                                    </video>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php } elseif (!$videoExists && !$ratebVideoClearedInCms) { ?>
                    <div class="rateb-video__shell">
                        <div class="video-wrap">
                            <div class="video-fallback-box">
                                <i class="fas fa-video-slash fa-3x mb-3"></i>
                                <p>Video not available on the server.</p>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
    <?php
}
if (!empty($ratebProgSlotsOut)) {
    ?>
            <div class="rateb-hero__photo-strip rateb-hero__program-strip" id="program-previews">
                <div class="rateb-container">
                    <p class="rateb-hero__photo-eyebrow"><?php echo htmlspecialchars($ratebHome['home.program.strip_eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="rateb-cms-media-strip rateb-cms-media-strip--program rateb-program-marquee" data-rateb-program-marquee role="region">
                        <div class="rateb-program-marquee__shell">
                            <div class="rateb-program-marquee__viewport">
                            <div class="rateb-cms-media-strip__track rateb-cms-media-strip__track--program rateb-program-marquee__track">
                                <?php for ($ratebMarqueePass = 0; $ratebMarqueePass < 2; $ratebMarqueePass++) {
                                    foreach ($ratebProgSlotsOut as $ratebProgSlot) {
                                        $ratebProgSrc = (string) $ratebProgSlot['src'];
                                        ?>
                            <div class="rateb-cms-media-strip__item rateb-cms-media-strip__item--program">
                                <figure class="rateb-hero__photo rateb-hero__photo--program">
                                    <button type="button" class="rateb-program-strip__thumb" data-rateb-gallery-open data-rateb-program-open data-full-src="<?php echo htmlspecialchars($ratebProgSrc, ENT_QUOTES, 'UTF-8'); ?>" data-caption="<?php echo htmlspecialchars((string) $ratebProgSlot['caption'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <img src="<?php echo htmlspecialchars($ratebProgSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $ratebProgSlot['alt'], ENT_QUOTES, 'UTF-8'); ?>" width="800" height="500" loading="lazy" decoding="async">
                                    </button>
                                    <figcaption><?php echo htmlspecialchars((string) $ratebProgSlot['caption'], ENT_QUOTES, 'UTF-8'); ?></figcaption>
                                </figure>
                            </div>
                                        <?php
                                    }
                                } ?>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    <?php
}
