<?php
/**
 * Operational proof — diagrams, screenshots, workflow walkthroughs (public surfaces).
 */
declare(strict_types=1);

require_once __DIR__ . '/site-content-profile-data.php';
require_once __DIR__ . '/ratib-public-cms.php';
if (!function_exists('ratib_site_content_asset_url')) {
    require_once __DIR__ . '/site-content.php';
}

if (!function_exists('ratib_operational_proof_gov_image_url')) {
    function ratib_operational_proof_gov_image_url(string $baseUrl, string $cmsKey, string $fallbackFile): string
    {
        $govFlat = [
            'government-control.png' => 'public/cms-bundle-gov-control-v2.png',
            'government-inspections.png' => 'public/cms-bundle-gov-inspections.png',
            'tracking-map.png' => 'public/cms-bundle-gov-tracking.png',
            'worker-mobile-onboarding.png' => 'public/cms-bundle-gov-onboarding.png',
        ];
        $fallbackRel = $govFlat[ltrim($fallbackFile, '/')] ?? ('public/cms-bundle-gov-control-v2.png');

        return ratib_public_cms_image($baseUrl, $cmsKey, $fallbackRel);
    }
}

if (!function_exists('ratib_operational_proof_config')) {
    /**
     * @return array<string, mixed>
     */
    function ratib_operational_proof_config(string $baseUrl): array
    {
        $diagram = static function (string $cmsKey, string $file) use ($baseUrl): string {
            $diagramFlat = [
                'workflow-lifecycle.svg' => 'public/cms-bundle-diagram-workflow.svg',
                'onboarding-flow.svg' => 'public/cms-bundle-diagram-onboarding.svg',
                'deployment-lifecycle.svg' => 'public/cms-bundle-diagram-deployment.svg',
                'tenant-isolation.svg' => 'public/cms-bundle-diagram-tenant.svg',
                'event-processing.svg' => 'public/cms-bundle-diagram-events.svg',
            ];
            $fb = $diagramFlat[ltrim($file, '/')] ?? 'public/cms-bundle-diagram-workflow.svg';

            return ratib_public_cms_image($baseUrl, $cmsKey, $fb);
        };
        $govImg = static function (string $cmsKey, string $file) use ($baseUrl): string {
            return ratib_operational_proof_gov_image_url($baseUrl, $cmsKey, $file);
        };

        $cfg = [
            'disclaimer' => ratib_public_cms('profile.opproof.disclaimer', 'Screenshots, diagrams, and metrics on this page use sample operational data or illustrative interfaces. They are not live production dashboards, audited statistics, or evidence of universal government integrations.'),
            'section' => [
                'eyebrow' => ratib_public_cms('profile.opproof.eyebrow', 'Operational proof'),
                'title' => ratib_public_cms('profile.opproof.title', 'How teams run programs on RATEB'),
                'sub' => ratib_public_cms('profile.opproof.sub', 'Government oversight, workforce tracking, and mobile mobilization—then agency workspace, diagrams, and reference flows for procurement review.'),
            ],
            'government' => [
                'id' => 'government-oversight',
                'eyebrow' => ratib_public_cms('profile.gov.eyebrow', 'Government & labor oversight'),
                'title' => ratib_public_cms('profile.gov.title', 'Inspections, violations, geospatial telemetry, and worker mobilization'),
                'lead' => ratib_public_cms('profile.gov.lead', 'RATEB includes a government-aligned control surface for labor oversight demonstrations.'),
                'points' => ratib_public_cms_lines('profile.gov.points', [
                    'Government Control consolidates inspections, violations, blacklist, worker alerts, and monitoring tabs behind one dashboard.',
                    'Inspection workflows capture worker and agency context, inspector identity, and status.',
                    'Tracking map filters by tenant, agency, country, and session status; supports geofences and playback.',
                    'Worker Mobile Onboarding issues QR credentials for controlled mobile mobilization.',
                ]),
                'note' => 'Sample operational data · demonstration interfaces · not a claim of live government integration unless contracted separately.',
                'screenshots' => [
                    [
                        'title' => 'Government Control',
                        'caption' => ratib_public_cms('profile.gov.caption.control', 'Labor monitoring console—violations, blacklist, worker alerts, and inspection tabs in one place.'),
                        'label' => 'Sample operational data',
                        'featured' => true,
                        'src' => $govImg('profile.gov.image.control', 'government-control.png'),
                        'alt' => 'Government Control dashboard with summary cards and inspection form',
                    ],
                    [
                        'title' => 'Inspection records',
                        'caption' => ratib_public_cms('profile.gov.caption.inspections', 'Inspection history with status badges, inspector attribution, and agency-scoped rows.'),
                        'label' => 'Sample operational data',
                        'src' => $govImg('profile.gov.image.inspections', 'government-inspections.png'),
                        'alt' => 'Inspection table showing pending, passed, and failed statuses',
                    ],
                    [
                        'title' => 'Tracking Map',
                        'caption' => ratib_public_cms('profile.gov.caption.tracking', 'Tracking map—geofences, playback, and filters for tenant, agency, and country.'),
                        'label' => 'Sample operational data',
                        'src' => $govImg('profile.gov.image.tracking', 'tracking-map.png'),
                        'alt' => 'Tracking map with geofence controls and workforce location marker',
                    ],
                    [
                        'title' => 'Worker Mobile Onboarding',
                        'caption' => ratib_public_cms('profile.gov.caption.onboarding', 'QR-based credentials for worker mobile app mobilization.'),
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
                    'src' => $diagram('profile.diagram.workflow', 'workflow-lifecycle.svg'),
                ],
                [
                    'id' => 'onboarding-flow',
                    'title' => 'Agency onboarding',
                    'caption' => 'From qualification to production workspace.',
                    'src' => $diagram('profile.diagram.onboarding', 'onboarding-flow.svg'),
                ],
                [
                    'id' => 'deployment-lifecycle',
                    'title' => 'Deployment lifecycle',
                    'caption' => 'Program setup, field coordination, host handover.',
                    'src' => $diagram('profile.diagram.deployment', 'deployment-lifecycle.svg'),
                ],
                [
                    'id' => 'tenant-isolation',
                    'title' => 'Tenant separation',
                    'caption' => 'Shared platform core; separate agency databases.',
                    'src' => $diagram('profile.diagram.tenant', 'tenant-isolation.svg'),
                ],
                [
                    'id' => 'event-processing',
                    'title' => 'Event processing',
                    'caption' => 'Emit, route, verify, and commit with replay safety.',
                    'src' => $diagram('profile.diagram.events', 'event-processing.svg'),
                ],
            ],
            'screenshots' => [
                [
                    'title' => 'Workforce pipeline',
                    'label' => 'Illustrative interface',
                    'src' => ratib_public_cms_image_or($baseUrl, 'opproof.image.pipeline', 'profile.image.pipeline', 'public/cms-bundle-pipeline.svg'),
                    'alt' => ratib_public_cms('profile.image.pipeline.alt', 'Sample workforce pipeline board with stages and SLA column'),
                ],
                [
                    'title' => 'Dashboard',
                    'label' => 'Illustrative interface',
                    'src' => ratib_public_cms_image_or($baseUrl, 'opproof.image.ops', 'profile.image.ops', 'public/cms-bundle-about.png'),
                    'alt' => ratib_public_cms('profile.image.ops.alt', 'Sample agency dashboard with queues and summaries'),
                ],
                [
                    'title' => 'Finance ledger',
                    'label' => 'Illustrative interface',
                    'src' => ratib_public_cms_image_or($baseUrl, 'opproof.image.finance', 'profile.image.accounting', 'public/cms-bundle-finance.svg'),
                    'alt' => ratib_public_cms('profile.image.accounting.alt', 'Sample ledger and invoicing screen'),
                ],
                [
                    'title' => 'Audit history',
                    'label' => 'Sample operational data',
                    'src' => ratib_public_cms_image_or($baseUrl, 'opproof.image.audit', 'profile.gov.image.inspections', 'public/cms-bundle-gov-inspections.png'),
                    'alt' => ratib_public_cms('profile.image.control.alt', 'Sample administration screen with settings and history context'),
                ],
                [
                    'title' => 'Tracking Map',
                    'label' => 'Sample operational data',
                    'src' => ratib_public_cms_image_or($baseUrl, 'opproof.image.map', 'profile.image.telemetry', 'public/cms-bundle-gov-tracking.png'),
                    'alt' => ratib_public_cms('profile.image.telemetry.alt', 'Sample tracking map with checkpoints and corridor context'),
                ],
                [
                    'title' => 'Partner portal',
                    'label' => 'Illustrative interface',
                    'src' => ratib_public_cms_image_or($baseUrl, 'opproof.image.partner', 'profile.image.partners', 'public/cms-bundle-about.png'),
                    'alt' => ratib_public_cms('profile.image.partners.alt', 'Sample partner portal with deployments and documents'),
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
                    'outcome' => 'Worker records are ready for deployment queue with full history.',
                ],
                [
                    'id' => 'compliance-review',
                    'title' => 'Compliance review',
                    'icon' => 'fa-clipboard-check',
                    'steps' => [
                        'Inspector or reviewer opens worker records and corridor context.',
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

        return $cfg;
    }
}
