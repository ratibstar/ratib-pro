<?php
/**
 * Partner-only read-only CV summary (no staff login). Shown in iframe from Documents & CVs.
 */
require_once __DIR__ . '/../includes/config.php';

if (!function_exists('rateb_partner_portal_session_is_valid') || !rateb_partner_portal_session_is_valid()) {
    header('Location: ' . pageUrl('partner-portal-login.php'));
    exit;
}

$workerId = (int) ($_GET['worker_id'] ?? 0);
$partnerAgencyId = function_exists('rateb_partner_portal_agency_id') ? (int) rateb_partner_portal_agency_id() : 0;
if ($workerId <= 0 || $partnerAgencyId <= 0) {
    http_response_code(400);
    echo 'Bad request';
    exit;
}

require_once __DIR__ . '/../api/core/Database.php';
require_once __DIR__ . '/../api/core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/../api/partnerships/PartnerAgencyWorkerDocSharesController.php';

$db = Database::getInstance();
$conn = $db->getConnection();
ratebEnsureGlobalPartnershipsSchema($conn);
$sh = new PartnerAgencyWorkerDocSharesController($conn);

if (!$sh->partnerHasShareForWorker($partnerAgencyId, $workerId)) {
    http_response_code(403);
    echo 'Not available for this worker.';
    exit;
}

$worker = $sh->fetchWorkerRow($workerId);
if (!$worker || !is_array($worker)) {
    http_response_code(404);
    echo 'Worker not found.';
    exit;
}

/**
 * @param mixed $v
 */
function pp_cv_h($v): string
{
    if ($v === null || $v === '') {
        return '—';
    }
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$name = trim((string) ($worker['worker_name'] ?? ''));
if ($name === '') {
    $name = trim((string) ($worker['full_name'] ?? ''));
}
if ($name === '') {
    $name = 'Worker #' . $workerId;
}

$jobRaw = $worker['job_title'] ?? $worker['job_titles'] ?? '';
if (is_string($jobRaw) && $jobRaw !== '' && ($jobRaw[0] === '[' || $jobRaw[0] === '{')) {
    $decoded = json_decode($jobRaw, true);
    if (is_array($decoded)) {
        $jobRaw = implode(', ', array_map('strval', $decoded));
    }
}
$jobs = is_string($jobRaw) ? $jobRaw : (string) $jobRaw;

$photo = trim((string) ($worker['personal_photo_url'] ?? ''));
$photoSrc = '';
if ($photo !== '') {
    if (preg_match('#^https?://#i', $photo) || strncmp($photo, 'data:', 5) === 0) {
        $photoSrc = $photo;
    } elseif ($photo[0] === '/') {
        $photoSrc = $photo;
    } else {
        $photoSrc = '../' . ltrim($photo, '/');
    }
}

header('X-Frame-Options: SAMEORIGIN');

$pageTitle = 'Worker CV';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo pp_cv_h($pageTitle); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #0f1419;
            color: #e8edf4;
            padding: 16px;
            line-height: 1.45;
        }
        .pp-cv-wrap { max-width: 900px; margin: 0 auto; }
        .pp-cv-brand {
            text-align: center;
            font-size: 12px;
            letter-spacing: 0.12em;
            color: #8b9aaf;
            margin-bottom: 12px;
        }
        .pp-cv-head {
            display: flex;
            gap: 20px;
            align-items: center;
            background: linear-gradient(135deg, #1e3a5f 0%, #0d2847 100%);
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 16px;
        }
        .pp-cv-head-text { flex: 1; min-width: 0; }
        .pp-cv-name { font-size: 1.75rem; font-weight: 700; color: #fff; margin: 0 0 8px; word-break: break-word; }
        .pp-cv-jobs { font-size: 0.95rem; color: #7ec8e3; margin: 0; }
        .pp-cv-photo {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.2);
            flex-shrink: 0;
            background: #1a2744;
        }
        .pp-cv-grid {
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            gap: 16px;
        }
        @media (max-width: 720px) {
            .pp-cv-grid { grid-template-columns: 1fr; }
        }
        .pp-cv-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 14px 16px;
        }
        .pp-cv-card h3 {
            margin: 0 0 10px;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #7ec8e3;
        }
        .pp-cv-row { margin-bottom: 8px; font-size: 14px; }
        .pp-cv-row:last-child { margin-bottom: 0; }
        .pp-cv-k { color: #8b9aaf; font-size: 12px; display: block; margin-bottom: 2px; }
        .pp-cv-v { color: #e8edf4; }
        .pp-cv-exp {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .pp-cv-pill {
            background: rgba(126, 200, 227, 0.12);
            border: 1px solid rgba(126, 200, 227, 0.25);
            border-radius: 8px;
            padding: 10px 14px;
            text-align: center;
            min-width: 90px;
        }
        .pp-cv-pill strong { display: block; font-size: 1.25rem; color: #7ec8e3; }
        .pp-cv-pill span { font-size: 11px; color: #8b9aaf; text-transform: uppercase; }
    </style>
</head>
<body>
<div class="pp-cv-wrap">
    <div class="pp-cv-brand">RATEB</div>
    <div class="pp-cv-head">
        <div class="pp-cv-head-text">
            <h1 class="pp-cv-name"><?php echo pp_cv_h($name); ?></h1>
            <p class="pp-cv-jobs"><?php echo pp_cv_h($jobs !== '' ? $jobs : '—'); ?></p>
        </div>
        <?php if ($photoSrc !== '') : ?>
            <img class="pp-cv-photo" src="<?php echo pp_cv_h($photoSrc); ?>" alt="">
        <?php else : ?>
            <div class="pp-cv-photo" aria-hidden="true"></div>
        <?php endif; ?>
    </div>

    <div class="pp-cv-grid">
        <div class="pp-cv-card">
            <h3>Contact</h3>
            <div class="pp-cv-row"><span class="pp-cv-k">Phone</span><span class="pp-cv-v"><?php echo pp_cv_h($worker['phone'] ?? ''); ?></span></div>
            <div class="pp-cv-row"><span class="pp-cv-k">Email</span><span class="pp-cv-v"><?php echo pp_cv_h($worker['email'] ?? ''); ?></span></div>
            <div class="pp-cv-row"><span class="pp-cv-k">Address</span><span class="pp-cv-v"><?php echo pp_cv_h($worker['address'] ?? ($worker['city'] ?? '')); ?></span></div>
        </div>
        <div class="pp-cv-card">
            <h3>Profile</h3>
            <div class="pp-cv-row"><span class="pp-cv-k">Date of birth</span><span class="pp-cv-v"><?php echo pp_cv_h($worker['date_of_birth'] ?? ''); ?></span></div>
            <div class="pp-cv-row"><span class="pp-cv-k">Place of birth</span><span class="pp-cv-v"><?php echo pp_cv_h($worker['place_of_birth'] ?? ''); ?></span></div>
            <div class="pp-cv-row"><span class="pp-cv-k">Nationality</span><span class="pp-cv-v"><?php echo pp_cv_h($worker['nationality'] ?? ''); ?></span></div>
            <div class="pp-cv-row"><span class="pp-cv-k">Gender</span><span class="pp-cv-v"><?php echo pp_cv_h($worker['gender'] ?? ''); ?></span></div>
        </div>
    </div>

    <div class="pp-cv-grid" style="margin-top: 16px;">
        <div class="pp-cv-card" style="grid-column: 1 / -1;">
            <h3>Summary</h3>
            <div class="pp-cv-row"><span class="pp-cv-k">Qualification</span><span class="pp-cv-v"><?php echo pp_cv_h($worker['qualification'] ?? $worker['education_level'] ?? ''); ?></span></div>
            <div class="pp-cv-row"><span class="pp-cv-k">Skills</span><span class="pp-cv-v"><?php echo pp_cv_h($worker['skills'] ?? ''); ?></span></div>
        </div>
    </div>

    <div class="pp-cv-card" style="margin-top: 16px;">
        <h3>Experience</h3>
        <div class="pp-cv-exp">
            <div class="pp-cv-pill"><strong><?php echo pp_cv_h($worker['work_experience'] ?? ''); ?></strong><span>Years total</span></div>
            <div class="pp-cv-pill"><strong><?php echo pp_cv_h($worker['local_experience'] ?? ''); ?></strong><span>Local</span></div>
            <div class="pp-cv-pill"><strong><?php echo pp_cv_h($worker['abroad_experience'] ?? ''); ?></strong><span>Abroad</span></div>
        </div>
    </div>

    <div class="pp-cv-card" style="margin-top: 16px;">
        <h3>Employment</h3>
        <div class="pp-cv-row"><span class="pp-cv-k">Training &amp; duties</span><span class="pp-cv-v"><?php echo pp_cv_h($worker['training_notes'] ?? $worker['training'] ?? ''); ?></span></div>
        <div class="pp-cv-row"><span class="pp-cv-k">Contract</span><span class="pp-cv-v"><?php echo pp_cv_h($worker['contract_duration'] ?? ''); ?></span></div>
    </div>
</div>
</body>
</html>
