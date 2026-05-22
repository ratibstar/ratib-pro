<?php
/**
 * Company profile (/profile) — CMS keys + control-panel editor (Public site content).
 */
declare(strict_types=1);

if (!function_exists('ratib_site_content_profile_media_default')) {
    function ratib_site_content_profile_media_default(string $contentKey, string $bundledRel): string
    {
        if (function_exists('ratib_site_content_media_default_token')) {
            return ratib_site_content_media_default_token($contentKey, $bundledRel);
        }

        return $bundledRel;
    }
}

if (!function_exists('ratib_site_content_defaults_profile')) {
    /**
     * @return array<string, string>
     */
    function ratib_site_content_defaults_profile(): array
    {
        $govLead = 'RATEB includes a government-aligned control surface for labor oversight demonstrations: inspectors record findings, violations and blacklist rules gate deployments, supervisors open the tracking map with geofences, and field teams onboard workers to the mobile program via QR credentials—all scoped to the active agency database with role-based access.';
        $govPoints = implode("\n", [
            'Government Control consolidates inspections, violations, blacklist, worker alerts, and monitoring tabs behind one dashboard—with optional read-only government view and a link to the tracking map.',
            'Inspection workflows capture worker and agency context, inspector identity, status (pending, passed, failed), and hashed credentials where policies require authenticated field access.',
            'Tracking map filters by tenant, agency, country, and session status; supports geofence creation, route playback, and latest workforce locations on OpenStreetMap.',
            'Worker Mobile Onboarding issues QR credentials so workers join the mobile program without sharing passwords in chat—device and identity fields stay optional for controlled pilots.',
        ]);

        return [
            'profile.company.trade_name' => 'RATEB',
            'profile.company.legal_name' => 'RATEB Company',
            'profile.company.tagline' => 'Enterprise Workforce Program Infrastructure',
            'profile.company.summary' => 'RATEB Company operates the RATEB platform: a multi-agency workflow system with separate program databases, workforce tracking, policy controls, and integrated billing for agencies and oversight-aligned programs.',
            'profile.company.mission' => 'Give sending-country agencies and host-market programs one platform to run regulated workforce corridors—with recruitment orchestration, operational visibility, compliance checkpoints, and finance linked to program events.',
            'profile.company.vision' => 'Cross-border workforce programs run on consistent records and auditable workflows—not disconnected spreadsheets.',
            'profile.company.markets' => 'Saudi Arabia (HQ) · Philippines · Bangladesh · Indonesia · Kenya · Uganda · Ethiopia · Nigeria · Rwanda · Sri Lanka · Nepal · Thailand',
            'profile.gov.eyebrow' => 'Government & labor oversight',
            'profile.gov.title' => 'Inspections, violations, geospatial telemetry, and worker mobilization',
            'profile.gov.lead' => $govLead,
            'profile.gov.points' => $govPoints,
            'profile.gov.image.control' => ratib_site_content_profile_media_default('profile.gov.image.control', 'public/cms-bundle-gov-control-v2.png'),
            'profile.gov.image.inspections' => ratib_site_content_profile_media_default('profile.gov.image.inspections', 'public/cms-bundle-gov-inspections.png'),
            'profile.gov.image.tracking' => ratib_site_content_profile_media_default('profile.gov.image.tracking', 'public/cms-bundle-gov-tracking.png'),
            'profile.gov.image.onboarding' => ratib_site_content_profile_media_default('profile.gov.image.onboarding', 'public/cms-bundle-gov-onboarding.png'),
            'profile.diagram.workflow' => ratib_site_content_profile_media_default('profile.diagram.workflow', 'public/cms-bundle-diagram-workflow.svg'),
            'profile.diagram.onboarding' => ratib_site_content_profile_media_default('profile.diagram.onboarding', 'public/cms-bundle-diagram-onboarding.svg'),
            'profile.diagram.deployment' => ratib_site_content_profile_media_default('profile.diagram.deployment', 'public/cms-bundle-diagram-deployment.svg'),
            'profile.diagram.tenant' => ratib_site_content_profile_media_default('profile.diagram.tenant', 'public/cms-bundle-diagram-tenant.svg'),
            'profile.diagram.events' => ratib_site_content_profile_media_default('profile.diagram.events', 'public/cms-bundle-diagram-events.svg'),
            'profile.gov.caption.control' => 'Labor monitoring console—violations, blacklist, worker alerts, and inspection tabs in one place.',
            'profile.gov.caption.inspections' => 'Inspection history with status badges, inspector attribution, and agency-scoped rows.',
            'profile.gov.caption.tracking' => 'Tracking map—geofences, playback, and filters for tenant, agency, and country.',
            'profile.gov.caption.onboarding' => 'QR-based credentials for worker mobile program mobilization.',
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
                'intro' => 'First block on the profile page. <strong>Upload a file</strong> for each screenshot (or paste <code>scmedia:…</code> from another field). Click <strong>Save all</strong> at the bottom — uploads are stored on the server and appear on /profile immediately.',
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
                'title' => 'Company profile (/profile) — identity, copy & sections',
                'intro' => 'All text for <a href="' . htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') . '</a>. Screenshots are in the sections below.',
                'fields' => [
                    ['key' => 'profile.meta.title', 'label' => 'Browser title', 'type' => 'text'],
                    ['key' => 'profile.meta.description', 'label' => 'Meta description', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'profile.company.trade_name', 'label' => 'Trade name (H1)', 'type' => 'text'],
                    ['key' => 'profile.company.legal_name', 'label' => 'Legal name', 'type' => 'text'],
                    ['key' => 'profile.company.tagline', 'label' => 'Tagline', 'type' => 'text'],
                    ['key' => 'profile.company.summary', 'label' => 'About summary', 'type' => 'textarea', 'rows' => 4],
                    ['key' => 'profile.company.mission', 'label' => 'Mission', 'type' => 'textarea', 'rows' => 3],
                    ['key' => 'profile.company.vision', 'label' => 'Vision', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'profile.company.markets', 'label' => 'Markets & corridors', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'profile.company.founded', 'label' => 'Founded', 'type' => 'text'],
                    ['key' => 'profile.company.hq', 'label' => 'Headquarters', 'type' => 'text'],
                    ['key' => 'profile.company.address', 'label' => 'Address', 'type' => 'text'],
                    ['key' => 'profile.company.industry', 'label' => 'Industry', 'type' => 'text'],
                    ['key' => 'profile.company.employees_band', 'label' => 'Team size band', 'type' => 'text'],
                    ['key' => 'profile.company.cr_value', 'label' => 'CR value text', 'type' => 'text'],
                    ['key' => 'profile.company.vat_value', 'label' => 'VAT value text', 'type' => 'text'],
                    ['key' => 'profile.company.services', 'label' => 'Services list (one per line)', 'type' => 'textarea', 'rows' => 8],
                    ['key' => 'profile.platform.eyebrow', 'label' => 'Platform overview · eyebrow', 'type' => 'text'],
                    ['key' => 'profile.platform.title', 'label' => 'Platform overview · title', 'type' => 'text'],
                    ['key' => 'profile.platform.lead', 'label' => 'Platform overview · lead', 'type' => 'textarea', 'rows' => 3],
                    ['key' => 'profile.what.title', 'label' => 'What RATEB is · title', 'type' => 'text'],
                    ['key' => 'profile.what.sub', 'label' => 'What RATEB is · subtitle', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'profile.what.not_crm', 'label' => '“Not this” bullets (one per line)', 'type' => 'textarea', 'rows' => 5],
                    ['key' => 'profile.what.is_infra', 'label' => '“This is RATEB” bullets (one per line)', 'type' => 'textarea', 'rows' => 5],
                    ['key' => 'profile.opproof.eyebrow', 'label' => 'Operational proof · eyebrow', 'type' => 'text'],
                    ['key' => 'profile.opproof.title', 'label' => 'Operational proof · title', 'type' => 'text'],
                    ['key' => 'profile.opproof.sub', 'label' => 'Operational proof · subtitle', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'profile.opproof.disclaimer', 'label' => 'Operational proof · disclaimer', 'type' => 'textarea', 'rows' => 3],
                    ['key' => 'profile.section.arch.eyebrow', 'label' => 'Architecture section · eyebrow', 'type' => 'text'],
                    ['key' => 'profile.section.arch.title', 'label' => 'Architecture section · title', 'type' => 'text'],
                    ['key' => 'profile.section.arch.sub', 'label' => 'Architecture section · subtitle', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'profile.section.ops.eyebrow', 'label' => 'Operations section · eyebrow', 'type' => 'text'],
                    ['key' => 'profile.section.ops.title', 'label' => 'Operations section · title', 'type' => 'text'],
                    ['key' => 'profile.section.ops.sub', 'label' => 'Operations section · subtitle', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'profile.section.tel.eyebrow', 'label' => 'Telemetry section · eyebrow', 'type' => 'text'],
                    ['key' => 'profile.section.tel.title', 'label' => 'Telemetry section · title', 'type' => 'text'],
                    ['key' => 'profile.section.tel.sub', 'label' => 'Telemetry section · subtitle', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'profile.section.gov.eyebrow', 'label' => 'Governance section · eyebrow', 'type' => 'text'],
                    ['key' => 'profile.section.gov.title', 'label' => 'Governance section · title', 'type' => 'text'],
                    ['key' => 'profile.section.gov.sub', 'label' => 'Governance section · subtitle', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'profile.section.fin.eyebrow', 'label' => 'Finance section · eyebrow', 'type' => 'text'],
                    ['key' => 'profile.section.fin.title', 'label' => 'Finance section · title', 'type' => 'text'],
                    ['key' => 'profile.section.fin.sub', 'label' => 'Finance section · subtitle', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'profile.section.cor.eyebrow', 'label' => 'Corridors section · eyebrow', 'type' => 'text'],
                    ['key' => 'profile.section.cor.title', 'label' => 'Corridors section · title', 'type' => 'text'],
                    ['key' => 'profile.section.cor.sub', 'label' => 'Corridors section · subtitle', 'type' => 'textarea', 'rows' => 2],
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