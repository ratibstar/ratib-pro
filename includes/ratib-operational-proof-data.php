<?php
/**
 * Operational proof — diagrams, screenshots, workflow walkthroughs (public surfaces).
 */
declare(strict_types=1);

require_once __DIR__ . '/site-content-profile-data.php';
if (!function_exists('ratib_site_content_asset_url')) {
    require_once __DIR__ . '/site-content.php';
}

if (!function_exists('ratib_operational_proof_gov_image_url')) {
    function ratib_operational_proof_gov_image_url(string $baseUrl, string $cmsKey, string $fallbackFile): string
    {
        $stored = '';
        if (function_exists('ratib_site_content_profile_flat')) {
            $flat = ratib_site_content_profile_flat();
            $stored = trim((string) ($flat[$cmsKey] ?? ''));
        }
        $fallbackRel = 'assets/images/government/' . ltrim($fallbackFile, '/');
        $fallbackFs = dirname(__DIR__) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $fallbackRel);
        if (function_exists('ratib_site_content_asset_url')) {
            return ratib_site_content_asset_url($baseUrl, $stored, $fallbackRel, $fallbackFs);
        }

        return rtrim($baseUrl, '/') . '/' . $fallbackRel;
    }
}

if (!function_exists('ratib_operational_proof_config')) {
    /**
     * @return array<string, mixed>
     */
    function ratib_operational_proof_config(string $baseUrl): array
    {
        $root = rtrim($baseUrl, '/');
        $img = static function (string $file) use ($root): string {
            return $root . '/assets/images/' . rawurlencode($file);
        };
        $diagram = static function (string $file) use ($root): string {
            return $root . '/assets/images/diagrams/' . rawurlencode($file);
        };
        $govImg = static function (string $cmsKey, string $file) use ($baseUrl): string {
            return ratib_operational_proof_gov_image_url($baseUrl, $cmsKey, $file);
        };

        $cfg = [
            'disclaimer' => 'Screenshots, diagrams, and metrics on this page use sample operational data or illustrative interfaces. They are not live production dashboards, audited statistics, or evidence of universal government integrations.',
            'section' => [
                'eyebrow' => 'Operational proof',
                'title' => 'How teams run programs on RATIB',
                'sub' => 'Government oversight, field tracking, and mobile mobilization—then agency workspace, diagrams, and reference flows for procurement review.',
            ],
            'government' => [
                'id' => 'government-oversight',
                'eyebrow' => 'Government & labor oversight',
                'title' => 'Inspections, violations, live tracking, and worker mobilization',
                'lead' => 'RATIB includes a government-aligned control surface for labor monitoring demonstrations: inspectors record findings, violations and blacklist rules gate deployments, supervisors open a live tracking map with geofences, and field teams onboard workers to the mobile app via QR credentials—all scoped to the active agency database with role-based access.',
                'points' => [
                    'Government Control consolidates inspections, violations, blacklist, worker alerts, and monitoring tabs behind one console—with optional read-only government view and a link to the live tracking map.',
                    'Inspection workflows capture worker and agency context, inspector identity, status (pending, passed, failed), and hashed credentials where policies require authenticated field access.',
                    'Tracking Map filters by tenant, agency, country, and session status; supports geofence creation, route playback, and latest worker locations on OpenStreetMap.',
                    'Worker Mobile Onboarding issues QR credentials so workers join the mobile program without sharing passwords in chat—device and identity fields stay optional for controlled pilots.',
                ],
                'note' => 'Sample operational data · demonstration interfaces · not a claim of live government integration unless contracted separately.',
                'screenshots' => [
                    [
                        'title' => 'Government Control',
                        'caption' => 'Labor monitoring console—violations, blacklist, worker alerts, and inspection tabs in one place.',
                        'label' => 'Sample operational data',
                        'featured' => true,
                        'src' => $govImg('profile.gov.image.control', 'government-control.png'),
                        'alt' => 'Government Control dashboard with summary cards and inspection form',
                    ],
                    [
                        'title' => 'Inspection records',
                        'caption' => 'Inspection history with status badges, inspector attribution, and agency-scoped rows.',
                        'label' => 'Sample operational data',
                        'src' => $govImg('profile.gov.image.inspections', 'government-inspections.png'),
                        'alt' => 'Inspection table showing pending, passed, and failed statuses',
                    ],
                    [
                        'title' => 'Tracking Map',
                        'caption' => 'Live map, geofences, playback, and filters for tenant, agency, and country.',
                        'label' => 'Sample operational data',
                        'src' => $govImg('profile.gov.image.tracking', 'tracking-map.png'),
                        'alt' => 'Tracking map with geofence controls and worker location marker',
                    ],
                    [
                        'title' => 'Worker Mobile Onboarding',
                        'caption' => 'QR-based credentials for worker mobile app mobilization.',
                        'label' => 'Illustrative interface',
                        'src' => $govImg('profile.gov.image.onboarding', 'worker-mobile-onboarding.png'),
                        'alt' => 'Worker mobile onboarding screen generating a QR code',
                    ],
                ],
            ],
            'diagrams' => [
                [
                    'id' => 'workflow-lifecycle',
                    'title' => 'Worker lifecycle workflow',
                    'caption' => 'Stage graph from intake through deployment and closure.',
                    'src' => $diagram('workflow-lifecycle.svg'),
                ],
                [
                    'id' => 'onboarding-flow',
                    'title' => 'Agency onboarding',
                    'caption' => 'From qualification to production workspace.',
                    'src' => $diagram('onboarding-flow.svg'),
                ],
                [
                    'id' => 'deployment-lifecycle',
                    'title' => 'Deployment lifecycle',
                    'caption' => 'Program setup, field coordination, host handover.',
                    'src' => $diagram('deployment-lifecycle.svg'),
                ],
                [
                    'id' => 'tenant-isolation',
                    'title' => 'Tenant separation',
                    'caption' => 'Shared platform core; separate agency databases.',
                    'src' => $diagram('tenant-isolation.svg'),
                ],
                [
                    'id' => 'event-processing',
                    'title' => 'Event processing',
                    'caption' => 'Emit, route, verify, and commit with replay safety.',
                    'src' => $diagram('event-processing.svg'),
                ],
            ],
            'screenshots' => [
                [
                    'title' => 'Workforce pipeline',
                    'label' => 'Illustrative interface',
                    'src' => $img('7.jpg'),
                    'alt' => 'Sample workforce pipeline board with stages and SLA column',
                ],
                [
                    'title' => 'Operations dashboard',
                    'label' => 'Illustrative interface',
                    'src' => $img('1.jpg'),
                    'alt' => 'Sample agency operations dashboard with queues and summaries',
                ],
                [
                    'title' => 'Finance ledger',
                    'label' => 'Illustrative interface',
                    'src' => $img('4.jpg'),
                    'alt' => 'Sample ledger and invoicing screen',
                ],
                [
                    'title' => 'Audit history',
                    'label' => 'Sample operational data',
                    'src' => $img('5.jpg'),
                    'alt' => 'Sample administration screen with settings and history context',
                ],
                [
                    'title' => 'Field operations map',
                    'label' => 'Sample operational data',
                    'src' => $img('3.jpg'),
                    'alt' => 'Sample map view with checkpoints and corridor context',
                ],
                [
                    'title' => 'Partner portal',
                    'label' => 'Illustrative interface',
                    'src' => $img('6.jpg'),
                    'alt' => 'Sample partner portal with deployments and documents',
                ],
            ],
            'workflows' => [
                [
                    'id' => 'worker-onboarding',
                    'title' => 'Worker onboarding',
                    'icon' => 'fa-user-plus',
                    'steps' => [
                        'Create worker record and assign sending-market profile.',
                        'Capture documents; dedupe against existing files.',
                        'Run verification bundles (medical, police, embassy as configured).',
                        'Advance stage when policy checks pass; notify owners.',
                    ],
                    'outcome' => 'Worker file is ready for deployment queue with full history.',
                ],
                [
                    'id' => 'compliance-review',
                    'title' => 'Compliance review',
                    'icon' => 'fa-clipboard-check',
                    'steps' => [
                        'Inspector or reviewer opens worker file and corridor context.',
                        'Compare documents and stage state to country policy profile.',
                        'Record finding, violation, or deploy hold if required.',
                        'Release or block next transition with actor attribution.',
                    ],
                    'outcome' => 'Decision is logged and visible to agency and oversight roles.',
                ],
                [
                    'id' => 'deployment-approval',
                    'title' => 'Deployment approval',
                    'icon' => 'fa-plane-departure',
                    'steps' => [
                        'Deployment program selects host market and placement slot.',
                        'Human gate confirms readiness (docs, medical, visa steps).',
                        'Emit deployment event; update field-operations context.',
                        'Partner portal receives scoped deployment package.',
                    ],
                    'outcome' => 'Placement is active with correlated events for finance and reporting.',
                ],
                [
                    'id' => 'finance-reconciliation',
                    'title' => 'Finance reconciliation',
                    'icon' => 'fa-scale-balanced',
                    'steps' => [
                        'Fees and invoices link to worker or placement correlation IDs.',
                        'Payments post to ledger with idempotent handling.',
                        'Controllers match AR/AP lines to stage milestones.',
                        'Export trial balance or corridor report for review.',
                    ],
                    'outcome' => 'Financial lines trace back to program events—not orphan spreadsheets.',
                ],
                [
                    'id' => 'partner-coordination',
                    'title' => 'Partner coordination',
                    'icon' => 'fa-handshake',
                    'steps' => [
                        'Host-market partner receives scoped portal access.',
                        'Exchange deployment documents and status updates.',
                        'Webhook or API notifies partner systems where configured.',
                        'Closure events sync back to agency workspace.',
                    ],
                    'outcome' => 'Partners work inside defined boundaries without cross-tenant visibility.',
                ],
            ],
        ];

        if (function_exists('ratib_site_content_profile_flat')) {
            $pf = ratib_site_content_profile_flat();
            $gov = &$cfg['government'];
            $gov['eyebrow'] = trim((string) ($pf['profile.gov.eyebrow'] ?? $gov['eyebrow']));
            $gov['title'] = trim((string) ($pf['profile.gov.title'] ?? $gov['title']));
            $gov['lead'] = trim((string) ($pf['profile.gov.lead'] ?? $gov['lead']));
            $pointsRaw = trim((string) ($pf['profile.gov.points'] ?? ''));
            if ($pointsRaw !== '') {
                $gov['points'] = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $pointsRaw) ?: [])));
            }
            $capKeys = [
                ['profile.gov.caption.control', 0],
                ['profile.gov.caption.inspections', 1],
                ['profile.gov.caption.tracking', 2],
                ['profile.gov.caption.onboarding', 3],
            ];
            foreach ($capKeys as [$ck, $idx]) {
                $cap = trim((string) ($pf[$ck] ?? ''));
                if ($cap !== '' && isset($gov['screenshots'][$idx])) {
                    $gov['screenshots'][$idx]['caption'] = $cap;
                }
            }
        }

        return $cfg;
    }
}
