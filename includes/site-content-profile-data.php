<?php
/**
 * Company profile (/profile) — CMS keys + control-panel editor (Public site content).
 */
declare(strict_types=1);

if (!function_exists('ratib_site_content_defaults_profile')) {
    /**
     * @return array<string, string>
     */
    function ratib_site_content_defaults_profile(): array
    {
        $govLead = 'RATIB includes a government-aligned control surface for labor monitoring demonstrations: inspectors record findings, violations and blacklist rules gate deployments, supervisors open a live tracking map with geofences, and field teams onboard workers to the mobile app via QR credentials—all scoped to the active agency database with role-based access.';
        $govPoints = implode("\n", [
            'Government Control consolidates inspections, violations, blacklist, worker alerts, and monitoring tabs behind one console—with optional read-only government view and a link to the live tracking map.',
            'Inspection workflows capture worker and agency context, inspector identity, status (pending, passed, failed), and hashed credentials where policies require authenticated field access.',
            'Tracking Map filters by tenant, agency, country, and session status; supports geofence creation, route playback, and latest worker locations on OpenStreetMap.',
            'Worker Mobile Onboarding issues QR credentials so workers join the mobile program without sharing passwords in chat—device and identity fields stay optional for controlled pilots.',
        ]);

        return [
            'profile.company.trade_name' => 'RATIB',
            'profile.company.legal_name' => 'Ratib Software Foundation for Information Technology',
            'profile.company.tagline' => 'Enterprise workforce program infrastructure',
            'profile.company.summary' => 'Ratib Software Foundation for Information Technology develops and operates RATIB: a multi-agency workflow platform with separate program databases, field-operations support, policy controls, and integrated billing for agencies and oversight-aligned programs.',
            'profile.company.mission' => 'Give sending-country agencies and host-market programs one workspace to run regulated workforce corridors—with workflow coordination, operational visibility, compliance checkpoints, and finance linked to program events.',
            'profile.company.vision' => 'Cross-border workforce programs run on consistent records and auditable workflows—not disconnected spreadsheets.',
            'profile.company.markets' => 'Saudi Arabia (HQ) · Philippines · Bangladesh · Indonesia · Kenya · Uganda · Ethiopia · Nigeria · Rwanda · Sri Lanka · Nepal · Thailand',
            'profile.gov.eyebrow' => 'Government & labor oversight',
            'profile.gov.title' => 'Inspections, violations, live tracking, and worker mobilization',
            'profile.gov.lead' => $govLead,
            'profile.gov.points' => $govPoints,
            'profile.gov.image.control' => 'scmedia:gov-government-control.png',
            'profile.gov.image.inspections' => 'scmedia:gov-government-inspections.png',
            'profile.gov.image.tracking' => 'scmedia:gov-tracking-map.png',
            'profile.gov.image.onboarding' => 'scmedia:gov-worker-mobile-onboarding.png',
            'profile.gov.caption.control' => 'Labor monitoring console—violations, blacklist, worker alerts, and inspection tabs in one place.',
            'profile.gov.caption.inspections' => 'Inspection history with status badges, inspector attribution, and agency-scoped rows.',
            'profile.gov.caption.tracking' => 'Live map, geofences, playback, and filters for tenant, agency, and country.',
            'profile.gov.caption.onboarding' => 'QR-based credentials for worker mobile app mobilization.',
        ];
    }
}

if (!function_exists('ratib_site_content_profile_editor_groups')) {
    /**
     * Shown first in Public site content editor (company profile + government screenshots).
     *
     * @return list<array<string, mixed>>
     */
    function ratib_site_content_profile_editor_groups(): array
    {
        $profileUrl = '/profile/';
        if (function_exists('ratib_public_site_base_url')) {
            $profileUrl = rtrim(ratib_public_site_base_url(), '/') . '/profile/';
        }

        return [
            [
                'id' => 'profile-government',
                'title' => 'Company profile — Government & mobilization (always on top)',
                'intro' => 'First block on the profile page under Operational proof. Upload PNG/WebP screenshots; leave path empty to keep the bundled default.',
                'fields' => [
                    ['key' => 'profile.gov.eyebrow', 'label' => 'Section eyebrow', 'type' => 'text'],
                    ['key' => 'profile.gov.title', 'label' => 'Section title', 'type' => 'text'],
                    ['key' => 'profile.gov.lead', 'label' => 'Lead paragraph', 'type' => 'textarea', 'rows' => 4],
                    ['key' => 'profile.gov.points', 'label' => 'Bullet points (one per line)', 'type' => 'textarea', 'rows' => 6],
                    ['key' => 'profile.gov.image.control', 'label' => 'Screenshot · Government Control', 'type' => 'media_image'],
                    ['key' => 'profile.gov.caption.control', 'label' => 'Caption · Government Control', 'type' => 'text'],
                    ['key' => 'profile.gov.image.inspections', 'label' => 'Screenshot · Inspection records', 'type' => 'media_image'],
                    ['key' => 'profile.gov.caption.inspections', 'label' => 'Caption · Inspection records', 'type' => 'text'],
                    ['key' => 'profile.gov.image.tracking', 'label' => 'Screenshot · Tracking Map', 'type' => 'media_image'],
                    ['key' => 'profile.gov.caption.tracking', 'label' => 'Caption · Tracking Map', 'type' => 'text'],
                    ['key' => 'profile.gov.image.onboarding', 'label' => 'Screenshot · Worker Mobile Onboarding', 'type' => 'media_image'],
                    ['key' => 'profile.gov.caption.onboarding', 'label' => 'Caption · Worker Mobile Onboarding', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'profile-company',
                'title' => 'Company profile (/profile) — identity & story',
                'intro' => 'Public company profile at <a href="' . htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') . '</a>. Legal/CR fields stay in code unless you add keys later.',
                'fields' => [
                    ['key' => 'profile.company.trade_name', 'label' => 'Trade name (H1)', 'type' => 'text'],
                    ['key' => 'profile.company.legal_name', 'label' => 'Legal name', 'type' => 'text'],
                    ['key' => 'profile.company.tagline', 'label' => 'Tagline', 'type' => 'text'],
                    ['key' => 'profile.company.summary', 'label' => 'About summary', 'type' => 'textarea', 'rows' => 4],
                    ['key' => 'profile.company.mission', 'label' => 'Mission', 'type' => 'textarea', 'rows' => 3],
                    ['key' => 'profile.company.vision', 'label' => 'Vision', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'profile.company.markets', 'label' => 'Markets & corridors', 'type' => 'textarea', 'rows' => 2],
                ],
            ],
        ];
    }
}

if (!function_exists('ratib_site_content_profile_flat')) {
    /**
     * Resolved profile CMS values (DB + defaults).
     *
     * @return array<string, string>
     */
    function ratib_site_content_profile_flat(): array
    {
        if (!function_exists('ratib_site_content_fetch_key_values')) {
            require_once __DIR__ . '/site-content.php';
        }
        $def = ratib_site_content_defaults_profile();
        if (!function_exists('ratib_site_content_fetch_key_values')) {
            return $def;
        }
        $keys = array_keys($def);
        $live = ratib_site_content_fetch_key_values($keys);
        $out = [];
        foreach ($def as $k => $fallback) {
            $out[$k] = array_key_exists($k, $live) ? (string) $live[$k] : (string) $fallback;
        }

        return $out;
    }
}
