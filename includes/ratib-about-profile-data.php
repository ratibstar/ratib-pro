<?php
/**
 * Enterprise company profile — content, screenshots, and section config for pages/about.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/site-content-profile-data.php';
require_once __DIR__ . '/ratib-public-cms.php';

if (!function_exists('ratib_about_profile_config')) {
    /**
     * @return array<string, mixed>
     */
    function ratib_about_profile_config(string $baseUrl): array
    {
        $shot = static function (string $key, string $fallback, string $altKey, string $defaultAlt) use ($baseUrl): array {
            return [
                'src' => ratib_public_cms_image($baseUrl, $key, $fallback),
                'alt' => ratib_public_cms($altKey, $defaultAlt),
            ];
        };

        return [
            'meta' => [
                'title' => ratib_public_cms('profile.meta.title', 'RATIB — Company profile'),
                'description' => ratib_public_cms('profile.meta.description', 'Legal identity, platform scope, corridors, and operational capabilities of Ratib Software Foundation for Information Technology.'),
            ],
            'platform' => [
                'eyebrow' => ratib_public_cms('profile.platform.eyebrow', 'Platform overview'),
                'title' => ratib_public_cms('profile.platform.title', 'RATIB operations platform'),
                'lead' => ratib_public_cms('profile.platform.lead', 'How sending-country agencies and host-market programs coordinate recruitment, field operations, compliance, and finance in one workspace.'),
            ],
            'what' => [
                'title' => ratib_public_cms('profile.what.title', 'What RATIB is — and what it is not'),
                'sub' => ratib_public_cms('profile.what.sub', 'RATIB is workforce program infrastructure for regulated, high-volume international recruitment—not a lightweight CRM.'),
            ],
            'sections' => [
                'arch' => [
                    'eyebrow' => ratib_public_cms('profile.section.arch.eyebrow', 'Platform architecture'),
                    'title' => ratib_public_cms('profile.section.arch.title', 'Platform layers'),
                    'sub' => ratib_public_cms('profile.section.arch.sub', 'Seven layers—from user-facing pages to separate agency databases.'),
                ],
                'ops' => [
                    'eyebrow' => ratib_public_cms('profile.section.ops.eyebrow', 'Agency workspace'),
                    'title' => ratib_public_cms('profile.section.ops.title', 'Agency workspace'),
                    'sub' => ratib_public_cms('profile.section.ops.sub', 'Day-to-day screens for agents, workforce records, finance, HR, and partner coordination.'),
                ],
                'tel' => [
                    'eyebrow' => ratib_public_cms('profile.section.tel.eyebrow', 'Field operations'),
                    'title' => ratib_public_cms('profile.section.tel.title', 'Field operations support'),
                    'sub' => ratib_public_cms('profile.section.tel.sub', 'Location-assisted workforce coordination with offline sync, geofence rules, and audit-friendly event history—not a standalone tracking app.'),
                ],
                'gov' => [
                    'eyebrow' => ratib_public_cms('profile.section.gov.eyebrow', 'Policy & oversight'),
                    'title' => ratib_public_cms('profile.section.gov.title', 'Compliance & governance'),
                    'sub' => ratib_public_cms('profile.section.gov.sub', 'Recorded workflow history, labor-oversight tools, and separate agency data for regulated programs.'),
                ],
                'fin' => [
                    'eyebrow' => ratib_public_cms('profile.section.fin.eyebrow', 'Controllers & CFOs'),
                    'title' => ratib_public_cms('profile.section.fin.title', 'Operational finance infrastructure'),
                    'sub' => ratib_public_cms('profile.section.fin.sub', 'Integrated ledger and invoicing—linked to workers and placements, not a bolt-on spreadsheet.'),
                ],
                'cor' => [
                    'eyebrow' => ratib_public_cms('profile.section.cor.eyebrow', 'Multi-corridor fabric'),
                    'title' => ratib_public_cms('profile.section.cor.title', 'Multi-country operations'),
                    'sub' => ratib_public_cms('profile.section.cor.sub', 'Worker-sending markets on one orchestration core—with country-scoped governance and expanding corridors.'),
                ],
            ],
            'company' => [
                'trade_name' => ratib_public_cms('profile.company.trade_name', 'RATIB'),
                'legal_name' => ratib_public_cms('profile.company.legal_name', 'Ratib Software Foundation for Information Technology'),
                'tagline' => ratib_public_cms('profile.company.tagline', 'Enterprise workforce program infrastructure'),
                'founded' => ratib_public_cms('profile.company.founded', '2018'),
                'hq' => ratib_public_cms('profile.company.hq', 'Riyadh, Kingdom of Saudi Arabia'),
                'address' => ratib_public_cms('profile.company.address', 'Riyadh, Saudi Arabia'),
                'website' => ratib_public_cms('public.contact.website', 'https://out.ratib.sa'),
                'email' => ratib_public_cms('public.contact.email', 'info@out.ratib.sa'),
                'phone' => ratib_public_cms('public.contact.phone', '+966 599 863 868'),
                'whatsapp' => ratib_public_cms('public.contact.whatsapp', 'https://wa.me/966599863868'),
                'cr_label' => 'Commercial registration',
                'cr_value' => ratib_public_cms('profile.company.cr_value', 'On file · available under NDA for enterprise procurement'),
                'vat_label' => 'VAT',
                'vat_value' => ratib_public_cms('profile.company.vat_value', 'Available on invoice / registration documents'),
                'industry' => ratib_public_cms('profile.company.industry', 'Workforce program software · Cross-border recruitment operations'),
                'employees_band' => ratib_public_cms('profile.company.employees_band', '51–200 (operations & engineering)'),
                'markets' => ratib_public_cms('profile.company.markets', 'Saudi Arabia (HQ) · Philippines · Bangladesh · Indonesia · Kenya · Uganda · Ethiopia · Nigeria · Rwanda · Sri Lanka · Nepal · Thailand'),
                'mission' => ratib_public_cms('profile.company.mission', 'Give sending-country agencies and host-market programs one workspace to run regulated workforce corridors—with workflow coordination, operational visibility, compliance checkpoints, and finance linked to program events.'),
                'vision' => ratib_public_cms('profile.company.vision', 'Cross-border workforce programs run on consistent records and auditable workflows—not disconnected spreadsheets.'),
                'summary' => ratib_public_cms('profile.company.summary', 'Ratib Software Foundation for Information Technology develops and operates RATIB: a multi-agency workflow platform with separate program databases, field-operations support, policy controls, and integrated billing for agencies and oversight-aligned programs.'),
                'services' => ratib_public_cms_lines('profile.company.services', [
                    'Workforce lifecycle workflows',
                    'Agency operations workspace (multi-level agents)',
                    'Worker records & document handling',
                    'Field location support & SLA visibility',
                    'RBAC, audit history, and labor-oversight modules',
                    'Ledger, AR/AP, and payment checkout',
                    'Partner & host-market portals',
                    'Domain, SSL, and branded agency sites',
                ]),
                'highlights' => [
                    ['label' => 'Corridors supported', 'value' => '11+ sending markets'],
                    ['label' => 'Service targets', 'value' => 'Defined per agreement'],
                    ['label' => 'Tenant model', 'value' => 'Separate program DBs'],
                    ['label' => 'Support', 'value' => 'Business-hours + ops channel'],
                ],
            ],
            'screenshots' => [
                'hero' => $shot('profile.image.hero', 'public/cms-bundle-about.png', 'profile.image.hero.alt', 'RATIB agency workspace — workforce records and SLA views'),
                'ops' => $shot('profile.image.ops', 'public/cms-bundle-about.png', 'profile.image.ops.alt', 'Agency operations workspace with workforce and agent views'),
                'workers' => $shot('profile.image.workers', 'public/cms-bundle-workers.svg', 'profile.image.workers.alt', 'Worker records — lifecycle and documents'),
                'telemetry' => $shot('profile.image.telemetry', 'public/cms-bundle-gov-tracking.png', 'profile.image.telemetry.alt', 'Operations map — field checkpoints and corridor context'),
                'accounting' => $shot('profile.image.accounting', 'public/cms-bundle-finance.svg', 'profile.image.accounting.alt', 'Accounting and invoicing module'),
                'control' => $shot('profile.image.control', 'public/cms-bundle-gov-control-v2.png', 'profile.image.control.alt', 'Administration — multi-country agency settings'),
                'partners' => $shot('profile.image.partners', 'public/cms-bundle-about.png', 'profile.image.partners.alt', 'Partner portal — deployments and documents'),
                'pipeline' => $shot('profile.image.pipeline', 'public/cms-bundle-pipeline.svg', 'profile.image.pipeline.alt', 'Workflow pipeline across recruitment stages'),
            ],
            'hero_metrics' => [
                ['label' => 'Sample workload', 'value' => '2.8k', 'delta' => 'illustrative UI', 'tone' => 'blue'],
                ['label' => 'SLA within policy', 'value' => '94.6%', 'delta' => 'sample metric', 'tone' => 'green'],
                ['label' => 'Stage events (24h)', 'value' => '412', 'delta' => 'sample log', 'tone' => 'violet'],
                ['label' => 'API latency p95', 'value' => '238ms', 'delta' => 'sample panel', 'tone' => 'cyan'],
            ],
            'status_chips' => [
                ['label' => 'TLS 1.3', 'ok' => true],
                ['label' => 'SLA policies', 'ok' => true],
                ['label' => 'Verification queue', 'ok' => true],
                ['label' => 'Tenant separation', 'ok' => true],
            ],
            'architecture_layers' => [
                ['id' => 'experience', 'title' => 'Experience Layer', 'body' => 'Public site, agency workspace, partner portals, and client-facing pages.', 'icon' => 'fa-layer-group'],
                ['id' => 'orchestration', 'title' => 'Orchestration Layer', 'body' => 'Lifecycle stages, country rules, onboarding, notifications, webhooks, and SLA reminders.', 'icon' => 'fa-diagram-project'],
                ['id' => 'telemetry', 'title' => 'Field operations layer', 'body' => 'Location checkpoints, offline sync, geofences, signal validation, and SOS where enabled.', 'icon' => 'fa-location-crosshairs'],
                ['id' => 'business', 'title' => 'Business Modules', 'body' => 'Workers, agents, partnerships, visa steps, HR, reports, and alerts.', 'icon' => 'fa-cubes'],
                ['id' => 'governance', 'title' => 'Governance Layer', 'body' => 'Roles, country scope, labor-oversight tools, audit history, tenant settings.', 'icon' => 'fa-shield-halved'],
                ['id' => 'commercial', 'title' => 'Commercial Layer', 'body' => 'Agency signup, payments, subscriptions, hosting add-ons.', 'icon' => 'fa-credit-card'],
                ['id' => 'data', 'title' => 'Data Layer', 'body' => 'Platform configuration database plus separate agency program databases.', 'icon' => 'fa-database'],
            ],
            'ops_modules' => [
                ['title' => 'Agent hierarchy', 'body' => 'Branches, sub-agents, quotas, and consolidated reporting.', 'icon' => 'fa-sitemap'],
                ['title' => 'Workforce records', 'body' => 'Worker files, documents, Musaned integration, deployment readiness.', 'icon' => 'fa-users'],
                ['title' => 'Deployment programs', 'body' => 'Host-market placements, returns, and exceptions by corridor.', 'icon' => 'fa-plane-departure'],
                ['title' => 'Operational finance', 'body' => 'Double-entry ledger, AR/AP, banking, multi-currency postings.', 'icon' => 'fa-calculator'],
                ['title' => 'Human capital module', 'body' => 'Staff HR, attendance, payroll, fleet, and internal documents.', 'icon' => 'fa-id-badge'],
                ['title' => 'Alerts & escalations', 'body' => 'Notifications when queues or SLAs need attention.', 'icon' => 'fa-bell'],
            ],
            'telemetry_features' => [
                ['title' => 'Field checkpoints', 'body' => 'Location updates tied to worker and program context, with session state in the workspace.', 'icon' => 'fa-satellite-dish'],
                ['title' => 'Offline batch sync', 'body' => 'Mobile clients queue updates locally and replay when connectivity returns.', 'icon' => 'fa-cloud-arrow-up'],
                ['title' => 'Geofence rules', 'body' => 'Corridor and handover zones with alerts routed to operator queues.', 'icon' => 'fa-draw-polygon'],
                ['title' => 'Signal validation', 'body' => 'Basic checks for inconsistent location patterns before escalation.', 'icon' => 'fa-triangle-exclamation'],
                ['title' => 'Emergency signaling', 'body' => 'SOS endpoint with location payload for critical field events.', 'icon' => 'fa-life-ring'],
                ['title' => 'Device pairing', 'body' => 'QR pairing, device tokens, optional single-device enforcement.', 'icon' => 'fa-qrcode'],
            ],
            'governance_features' => [
                ['title' => 'Workflow history', 'body' => 'Stage changes recorded with actor, correlation ID, and policy version where enabled.', 'icon' => 'fa-clock-rotate-left'],
                ['title' => 'Labor oversight tools', 'body' => 'Inspections, violations, blocks, and deploy holds on worker files.', 'icon' => 'fa-landmark'],
                ['title' => 'Country policy profiles', 'body' => 'Per-corridor rules applied in onboarding and deployment.', 'icon' => 'fa-globe'],
                ['title' => 'Tenant separation', 'body' => 'Agency program data in separate databases; platform settings centralized.', 'icon' => 'fa-lock'],
                ['title' => 'Roles & country scope', 'body' => 'Permissions limited by branch and sending-market scope.', 'icon' => 'fa-user-shield'],
                ['title' => 'Safe retries', 'body' => 'Idempotent steps, signed webhooks, and searchable event logs.', 'icon' => 'fa-fingerprint'],
            ],
            'finance_features' => [
                ['title' => 'Double-entry ledger', 'body' => 'Journal entries, chart of accounts, trial balance, P&L, balance sheet.', 'icon' => 'fa-book'],
                ['title' => 'AR / AP & banking', 'body' => 'Invoices, bills, vouchers, reconciliation, payment allocation.', 'icon' => 'fa-building-columns'],
                ['title' => 'E-invoicing', 'body' => 'Issuance when verified workflow steps complete—downstream records stay linked.', 'icon' => 'fa-file-invoice'],
                ['title' => 'Linked transactions', 'body' => 'Finance lines tied to agents, workers, and HR—not standalone spreadsheets.', 'icon' => 'fa-link'],
                ['title' => 'Registration payments', 'body' => 'Agency signup payments mirrored to the ledger with idempotent handling.', 'icon' => 'fa-receipt'],
                ['title' => 'Multi-currency', 'body' => 'SAR, USD, EUR, GBP, JOD for international programs.', 'icon' => 'fa-coins'],
            ],
            'corridors' => [
                ['name' => 'Philippines', 'code' => 'PH'],
                ['name' => 'Bangladesh', 'code' => 'BD'],
                ['name' => 'Indonesia', 'code' => 'ID'],
                ['name' => 'Kenya', 'code' => 'KE'],
                ['name' => 'Uganda', 'code' => 'UG'],
                ['name' => 'Ethiopia', 'code' => 'ET'],
                ['name' => 'Nigeria', 'code' => 'NG'],
                ['name' => 'Rwanda', 'code' => 'RW'],
                ['name' => 'Sri Lanka', 'code' => 'LK'],
                ['name' => 'Nepal', 'code' => 'NP'],
                ['name' => 'Thailand', 'code' => 'TH'],
            ],
            'trust_items' => [
                ['title' => 'TLS 1.3', 'body' => 'Encrypted connections to public and API endpoints.', 'icon' => 'fa-lock'],
                ['title' => 'WebAuthn & biometrics', 'body' => 'Stronger sign-in options where agencies enable them.', 'icon' => 'fa-fingerprint'],
                ['title' => 'Event notifications', 'body' => 'In-app streams, outbound webhooks with HMAC where configured.', 'icon' => 'fa-bolt'],
                ['title' => 'Safe retries', 'body' => 'Idempotency on key writes and stall detection on long workflows.', 'icon' => 'fa-rotate'],
                ['title' => 'Tenant separation', 'body' => 'One shared application core; agency data stored separately.', 'icon' => 'fa-server'],
                ['title' => 'Strict policy mode', 'body' => 'Optional tighter rules for programs that require formal oversight.', 'icon' => 'fa-scale-balanced'],
            ],
            'partner_features' => [
                ['title' => 'Host-market portal', 'body' => 'Scoped access to deployments, documents, and statements.', 'icon' => 'fa-handshake'],
                ['title' => 'Document exchange', 'body' => 'Structured sharing—not informal email attachments.', 'icon' => 'fa-file-contract'],
                ['title' => 'REST API v1', 'body' => 'Bearer-token endpoints for workers, workflows, tracking, alerts.', 'icon' => 'fa-code'],
                ['title' => 'Webhooks', 'body' => 'Signed callbacks with retry for ERP and partner systems.', 'icon' => 'fa-plug'],
            ],
            'platform_services' => [
                ['title' => 'Domain services', 'body' => 'Search, transfer, and provisioning through registrar integrations.', 'icon' => 'fa-globe'],
                ['title' => 'SSL & edge security', 'body' => 'Certificate lifecycle and standard edge protection.', 'icon' => 'fa-shield'],
                ['title' => 'Hosting setup', 'body' => 'DNS, SSL, and hosting steps for agency-branded sites.', 'icon' => 'fa-cloud'],
                ['title' => 'Branded agency sites', 'body' => 'White-label fronts on the same platform—not a separate product per agency.', 'icon' => 'fa-palette'],
            ],
            'not_crm' => ratib_public_cms_lines('profile.what.not_crm', [
                'A lightweight CRM with a basic map plugin',
                'Spreadsheet replacement with a login screen',
                'One shared database for every agency',
                'Marketing site with static dashboard images only',
            ]),
            'is_infrastructure' => ratib_public_cms_lines('profile.what.is_infra', [
                'Workforce program workflow platform',
                'Multi-country agency operations workspace',
                'Field-operations support with location checkpoints',
                'Policy, audit history, and oversight-oriented modules',
                'Finance module linked to placements and fees',
            ]),
        ];
    }
}
