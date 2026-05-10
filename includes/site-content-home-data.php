<?php
/**
 * Public homepage (pages/home.php) — default copy keys + control-panel editor layout.
 * Keys are flat strings stored in ratib_site_content.content_key.
 */

if (!function_exists('ratib_site_content_defaults_home')) {
    /**
     * @return array<string, string>
     */
    function ratib_site_content_defaults_home(): array
    {
        $nav = [
            'platform' => 'Platform',
            'how_it_works' => 'How it works',
            'features' => 'Features',
            'solutions' => 'Solutions',
            'programs' => 'Pricing',
            'agencies' => 'Agencies',
            'tracking' => 'Tracking',
            'operational' => 'Visibility',
            'api' => 'API',
            'contact' => 'Contact',
        ];
        $d = [];
        $d['home.meta.page_title'] = 'RATIB — Enterprise Recruitment OS & Workforce Intelligence Platform';

        $d['home.topbar.phone_display'] = '+966 59 986 3868';
        $d['home.topbar.wa_label'] = 'Live on WhatsApp';
        $d['home.topbar.tls_label'] = 'TLS 1.3';
        $d['home.topbar.nodes_count'] = '247';
        $d['home.topbar.nodes_suffix'] = 'nodes';
        $d['home.topbar.client_login'] = 'Client login';

        foreach ($nav as $slug => $label) {
            $d['home.nav.' . $slug] = $label;
        }

        $d['home.nav.cta_partner'] = 'Partner Login';
        $d['home.nav.cta_primary'] = 'Start agency infrastructure';

        $d['home.hero.eyebrow'] = 'Recruitment Automation & Tracking Intelligence Base';
        $d['home.hero.lead'] = 'Production control plane for sending-country agencies and host-market programs: lifecycle orchestration, workforce telemetry, compliance gates, and ledger-linked billing—same surfaces operations teams use daily, not a marketing shell.';
        $d['home.hero.title_before'] = 'Recruitment Automation &';
        $d['home.hero.title_gradient'] = 'Workforce Intelligence';
        $bullets = [
            'Workflow orchestration & stage sync across sending & host markets',
            'Tenant isolation, RBAC, and per-agency domain edges',
            'Field & milestone telemetry with SLA visibility',
            'Event-driven signals, escalations, and operational intelligence',
        ];
        foreach ($bullets as $i => $text) {
            $d['home.hero.bullet.' . ($i + 1)] = $text;
        }
        $d['home.hero.cta_primary'] = 'Launch operations workspace';
        $d['home.hero.cta_secondary'] = 'Platform walkthrough';

        $d['home.video.eyebrow'] = 'Product tour';
        $d['home.video.title'] = 'Walk the surfaces your teams will run';
        $d['home.video.caption'] = 'Recorded walkthrough: pipelines, verification queues, finance hooks, and agency administration.';

        $d['home.program.strip_eyebrow'] = 'Program previews';
        $d['home.program.caption.1'] = 'Pipeline board';
        $d['home.program.caption.2'] = 'Workers registry';
        $d['home.program.caption.3'] = 'Finance & ledger';
        $d['home.program.alt.1'] = 'RATIB pipeline board with stages, SLA, and worker rows';
        $d['home.program.alt.2'] = 'RATIB workers registry with stages, owners, and GPS context';
        $d['home.program.alt.3'] = 'RATIB finance view with invoices, throughput, and connector latency';

        $d['home.platform.title'] = 'Built for regulated, high-volume recruitment operations';
        $d['home.platform.sub'] = 'Deployed as a control plane: tenant-isolated data paths, encrypted transit, immutable workflow history, and finance-grade events organizations can reconcile—not narrative dashboards.';

        $trust = [
            ['RBAC & scoped tenancy', 'Role matrices per agency branch; least-privilege API keys; segregated operator sessions.'],
            ['Audit trails & workflow history', 'Append-only stage transitions with actor, correlation id, and policy version stamped on each commit.'],
            ['Encrypted infrastructure', 'TLS 1.3 to the edge; tenant-scoped storage; session revocation and device-aware policies.'],
            ['SLA visibility', 'Stage clocks, breach watches, and escalation routes before commitments slip—surfaced in ops consoles.'],
            ['Compliance tracking', 'Embassy, medical, and police bundles tracked as first-class artifacts with reviewer attribution.'],
            ['Continuity & multi-region readiness', 'Operational backups, replayable event streams, and expansion paths for secondary regions when procurement requires it.'],
        ];
        foreach ($trust as $i => $row) {
            $n = $i + 1;
            $d['home.trust.' . $n . '.title'] = $row[0];
            $d['home.trust.' . $n . '.body'] = $row[1];
        }

        $d['home.how.eyebrow'] = 'Operational onboarding';
        $d['home.how.title'] = 'How agencies go live on RATIB';
        $d['home.how.sub'] = 'From tenant provisioning to invoicing—one orchestrated spine with explicit human gates, auditable transitions, and connector-backed finance.';
        $howSteps = [
            ['Agency onboarding', 'Tenant creation, RBAC, branded domains, sandbox → production promotion.'],
            ['Workflow configuration', 'Stage graph, owners, SLA clocks, and verification bundles per corridor.'],
            ['Candidate intake', 'Structured records, document capture, and deduped applicant system of record.'],
            ['Stage orchestration', 'Automated hops plus HITL approvals; correlation ids across workers and finance.'],
            ['Tracking & compliance', 'GPS and milestone telemetry with policy-bound exception routing.'],
            ['Arrival & deployment', 'Host-market handover, closure events, and workforce activation signals.'],
            ['Reporting & invoicing', 'Executive telemetry, branch roll-ups, and ledger-linked issuance.'],
        ];
        foreach ($howSteps as $i => $row) {
            $n = $i + 1;
            $d['home.how.step.' . $n . '.title'] = $row[0];
            $d['home.how.step.' . $n . '.desc'] = $row[1];
        }

        $d['home.features.eyebrow'] = 'Platform surface';
        $d['home.features.title'] = 'Twelve capabilities operators touch daily';
        $d['home.features.sub'] = 'Same modules used in production consoles—not vapor features.';
        $features = [
            ['Recruitment lifecycle engine', 'Define stages, owners, policies once—execute across every worker file.'],
            ['Applicant system of record', 'Single longitudinal record: docs, history, readiness for deployment.'],
            ['Stage synchronization', 'Event- and time-driven transitions with explicit human-in-the-loop gates.'],
            ['Field & GPS telemetry', 'Check-ins, corridors, and exception routing for operational visibility.'],
            ['Multi-domain tenancy', 'Agency-branded edges on unified orchestration and identity substrate.'],
            ['Digital contracts', 'Signatures and renewals bound to lifecycle state transitions.'],
            ['Operational finance hooks', 'Placement-to-settlement awareness for controllers and agency billing.'],
            ['E-invoicing rails', 'Issuance when rules and verified events align—auditable downstream.'],
            ['Worker lifecycle trace', 'Immutable checkpoints from intake through arrival and handover.'],
            ['Operational alerting', 'Escalations to ops, agencies, and partners before SLA breach.'],
            ['Telemetry & analytics', 'Funnel integrity, velocity, and cohort quality in one executive surface.'],
            ['Integration & API fabric', 'HRIS, ERP, messaging, and verification feeds via authenticated endpoints.'],
        ];
        foreach ($features as $i => $row) {
            $n = $i + 1;
            $d['home.features.' . $n . '.title'] = $row[0];
            $d['home.features.' . $n . '.body'] = $row[1];
        }

        $d['home.pipeline.eyebrow'] = 'Orchestration graph';
        $d['home.pipeline.title'] = 'End-to-end pipeline, instrumented';
        $d['home.pipeline.sub'] = 'Each hop emits events to the orchestrator: automation runs, manual gates, document verification, and field telemetry in one auditable spine.';
        $pipeSteps = [
            ['Application', 'committed · 09 May 08:14 UTC'],
            ['Verification', 'bundle OK · reviewer svc-bot'],
            ['Medical', 'clearance window · SLA 38h'],
            ['Embassy', 'slot queue · RUH consulate'],
            ['Visa', 'issue pending · workflow hold'],
            ['Ticket', 'carrier manifest · auto'],
            ['Arrival', 'handover GPS · geofence'],
            ['Deployment', 'FIN close · INV emitted'],
        ];
        foreach ($pipeSteps as $i => $row) {
            $n = $i + 1;
            $d['home.pipeline.step.' . $n . '.label'] = $row[0];
            $d['home.pipeline.step.' . $n . '.meta'] = $row[1];
        }

        $d['home.solutions.eyebrow'] = 'Operational scenarios';
        $d['home.solutions.title'] = 'Where RATIB runs in production';
        $d['home.solutions.sub'] = 'Representative B2B programs on the same orchestration core—multi-tenant, audit-visible, connector-backed.';
        $d['home.solutions.1.title'] = 'Recruitment agencies · multi-branch';
        $d['home.solutions.1.body'] = 'Central intake with branch-level RBAC, quota splits, and consolidated reporting for owners—without duplicating worker records across offices.';
        $d['home.solutions.1.demo_row.1'] = 'Tenant';
        $d['home.solutions.1.demo_row.1b'] = 'ACME · branches RUH · JED · DMM · unified pipeline graph';
        $d['home.solutions.1.demo_row.2'] = 'Ops';
        $d['home.solutions.1.demo_row.2b'] = 'stage owners mapped · SLA inherited from policy CL-2024-ME';
        $d['home.solutions.1.demo_row.3'] = 'Emit';
        $d['home.solutions.1.demo_row.3b'] = 'nightly cohort rollup · exec dashboard · no CSV extracts';
        $d['home.solutions.2.title'] = 'Overseas workforce operations';
        $d['home.solutions.2.body'] = 'Corridor programs with sending-country compliance packs, host-market deployment rules, and milestone telemetry tied to billing milestones.';
        $d['home.solutions.3.title'] = 'Multi-office recruitment firms';
        $d['home.solutions.3.body'] = 'Consolidated candidate inventory with segregated finance and placement attribution—one platform, strict tenant edges between brands.';
        $d['home.solutions.4.title'] = 'Enterprise staffing coordination';
        $d['home.solutions.4.body'] = 'Buyer mandates, bulk transitions, and SLA-backed escalations when intake spikes or sponsor deadlines move.';
        $d['home.solutions.5.title'] = 'Embassy processing workflows';
        $d['home.solutions.5.body'] = 'Appointment queues, bundle completeness checks, and status feeds operators defend in audits—linked to worker files, not inboxes.';
        $d['home.solutions.6.title'] = 'Visa pipeline management';
        $d['home.solutions.6.body'] = 'Medical → embassy → visa → ticket orchestration with explicit holds, reviewer attribution, and finance triggers only after verified hops.';

        $d['home.agencies.eyebrow'] = 'Multi-agency ecosystem';
        $d['home.agencies.title'] = 'One RATIB core. Many independent agencies.';
        $d['home.agencies.sub'] = 'Isolated production tenants on one control plane—identity, orchestration, telemetry, and finance connectors without duplicating stacks per agency.';
        $d['home.agencies.core.label'] = 'RATIB Core';
        $d['home.agencies.core.sub'] = 'IAM · Orchestrator · Telemetry · Ledger API';
        $d['home.agencies.spoke.1.label'] = 'Agency A';
        $d['home.agencies.spoke.1.small'] = 'tenant + domain';
        $d['home.agencies.spoke.2.label'] = 'Agency B';
        $d['home.agencies.spoke.2.small'] = 'tenant + domain';
        $d['home.agencies.spoke.3.label'] = 'Agency C';
        $d['home.agencies.spoke.3.small'] = 'tenant + domain';
        $d['home.agencies.spoke.4.label'] = 'Custom domains';
        $d['home.agencies.spoke.4.small'] = 'white-label edges';

        $d['home.analytics.eyebrow'] = 'Telemetry plane';
        $d['home.analytics.title'] = 'Executive & ops signals from live programs';
        $d['home.analytics.sub'] = 'Rolling aggregates from committed lifecycle events—same metrics surfaced in operational reviews.';
        $d['home.analytics.1.stamp'] = 'snapshot · merged shards · UTC';
        $d['home.analytics.1.title'] = 'Checkpoint fidelity';
        $d['home.analytics.1.metric'] = '98.2%';
        $d['home.analytics.1.body'] = 'Completed checkpoints vs policy graph for in-motion cohorts.';
        $d['home.analytics.2.stamp'] = 'queue depth · 15m resolution';
        $d['home.analytics.2.title'] = 'Active lifecycle workload';
        $d['home.analytics.2.metric'] = '2.8k';
        $d['home.analytics.2.body'] = 'Workers in non-terminal stages across connected agencies.';
        $d['home.analytics.3.stamp'] = 'normalized demand index';
        $d['home.analytics.3.title'] = 'Throughput vs baseline';
        $d['home.analytics.3.metric'] = '+31%';
        $d['home.analytics.3.note'] = 'QoQ';
        $d['home.analytics.3.body'] = 'Comparable velocity after seasonal adjustment—not vanity growth.';
        $d['home.analytics.4.stamp'] = 'engine attribution · 7d';
        $d['home.analytics.4.title'] = 'Automated transition ratio';
        $d['home.analytics.4.metric'] = '76%';
        $d['home.analytics.4.note'] = 'engine-led hops';
        $d['home.analytics.4.body'] = 'Remainder explicit HITL—policy requires human gates.';

        $d['home.ops.eyebrow'] = 'Operational visibility';
        $d['home.ops.title'] = 'What mission control actually shows';
        $d['home.ops.sub'] = 'Live-style aggregates you would expect in a deployed ops console: SLA posture, queue depth, automation outcomes, finance connector ACKs, and streamed events.';

        $d['home.ops.band.1.k'] = 'API edge';
        $d['home.ops.band.1.v'] = 'REST · TLS 1.3 · scoped keys';
        $d['home.ops.band.2.k'] = 'Regions';
        $d['home.ops.band.2.v'] = 'ME primary · EU replication optional';
        $d['home.ops.band.3.k'] = 'Data plane';
        $d['home.ops.band.3.v'] = 'encrypted · tenant-scoped · audit trail';
        $d['home.ops.band.4.k'] = 'Identity';
        $d['home.ops.band.4.v'] = 'RBAC · SSO-ready · session revocation';
        $d['home.ops.band.5.k'] = 'Workflow history';
        $d['home.ops.band.5.v'] = 'immutable stage commits · correlation ids';
        $d['home.ops.band.6.k'] = 'Continuity';
        $d['home.ops.band.6.v'] = 'operational backups · replayable events';

        $d['home.api.eyebrow'] = 'Developers';
        $d['home.api.title'] = 'APIs for the recruitment operating system';
        $d['home.api.sub'] = 'Versioned integration endpoints for HRIS, ERP, verification vendors, and internal data lakes—authenticated, rate-aware, observable.';
        $d['home.api.cta'] = 'Request API access';

        $d['home.pricing.eyebrow'] = 'Pricing';
        $d['home.pricing.title'] = 'Plans that scale with your agency footprint';
        $d['home.pricing.sub'] = 'Transparent tiers for evaluation, production, and enterprise procurement teams.';

        $d['home.pricing.starter.badge'] = 'Evaluate';
        $d['home.pricing.starter.plan'] = 'Starter';
        $d['home.pricing.starter.subtitle'] = 'Discovery & Pro onboarding';
        $d['home.pricing.starter.price_line'] = 'Custom scope';
        $d['home.pricing.starter.features'] = "Pro plan consultation\nWorkspace readiness review\nIntegration guidance\nDedicated success touchpoints";
        $d['home.pricing.starter.cta'] = 'Talk to solutions';

        $d['home.pricing.gold.badge'] = 'Popular';
        $d['home.pricing.gold.plan_word'] = 'Business';
        $d['home.pricing.gold.subtitle'] = 'Branded agency portal · Gold tier';
        $d['home.pricing.gold.discount_label'] = '50% Discount';
        $d['home.pricing.gold.features'] = "Candidate & document management\nYour branded portal\n20 users\nE-invoice system\nStandard support\nManaged infrastructure & SSL\nAdmin control panel";
        $d['home.pricing.gold.cta'] = 'Deploy Business workspace';

        $d['home.pricing.platinum.badge'] = '50% Off';
        $d['home.pricing.platinum.plan_word'] = 'Enterprise';
        $d['home.pricing.platinum.subtitle'] = 'Mission-critical programs · Platinum tier';
        $d['home.pricing.platinum.discount_label'] = '50% Discount';
        $d['home.pricing.platinum.features'] = "All Business features\nUnlimited users\nPriority support\nAdvanced analytics\nDedicated setup\nManaged infrastructure & SSL\nAdmin control panel\nCustom integrations";
        $d['home.pricing.platinum.cta'] = 'Deploy Enterprise workspace';

        $d['home.register.info.title'] = 'What is Ratib Program?';
        $d['home.register.info.intro'] = 'Ratib is a professional platform for recruitment agencies and companies in worker-sending countries. Manage candidates, contracts, and compliance in one place.';
        $checks = [
            '<strong>Recruitment management</strong> — Handle workers and candidates efficiently',
            '<strong>Pro plan</strong> — Your own branded agency portal',
            '<strong>Worker-sending countries</strong> — Bangladesh, Uganda, Kenya, Philippines, and more',
            '<strong>Contracts & compliance</strong> — Track documents and meet regulations',
            '<strong>Simple onboarding</strong> — Register your agency and we\'ll set you up',
            '<strong>Document tracking</strong> — Licenses, visas, medical reports in one dashboard',
            '<strong>Reporting & analytics</strong> — Track placements, status, and performance',
        ];
        foreach ($checks as $i => $html) {
            $d['home.register.check.' . ($i + 1)] = $html;
        }
        $d['home.register.form.title'] = 'Register Your Agency';
        $d['home.register.form.plan_hint'] = 'Select <strong>Gold (Business)</strong> or <strong>Platinum (Enterprise)</strong> to see the payment summary for your plan.';
        $d['home.register.payment_placeholder'] = '<strong>Pricing summary</strong> — Select <strong>Business (Gold)</strong> or <strong>Enterprise (Platinum)</strong> at the top of this form to see plan totals here before you submit.';
        $d['home.register.payment_summary.title'] = 'Payment Summary';
        $d['home.register.payment_summary.footer'] = 'Submit your request below. We will contact you about payment after review.';
        $d['home.register.submit'] = 'Submit Request';

        $d['home.final_cta.title'] = 'Put production-grade recruitment infrastructure online';
        $d['home.final_cta.sub'] = 'Event orchestration, workforce telemetry, and ledger-backed billing on one deployed plane—built for agencies already running at scale.';
        $d['home.final_cta.btn_primary'] = 'Start agency infrastructure';
        $d['home.final_cta.btn_secondary'] = 'Book platform demo';

        $d['home.footer.brand'] = 'Enterprise recruitment operating system — multi-agency workforce intelligence, automation, and real-time tracking.';
        $d['home.footer.col.platform'] = 'Platform';
        $d['home.footer.col.company'] = 'Company';
        $d['home.footer.col.support'] = 'Support';
        $d['home.footer.col.legal'] = 'Legal';
        $d['home.footer.col.infra'] = 'Infrastructure';
        $d['home.footer.infra.copy'] = 'Managed cloud, TLS, isolated tenants, and compliance-oriented audit trails.';
        $d['home.footer.newsletter.label'] = 'Updates';
        $d['home.footer.newsletter.placeholder'] = 'Work email';
        $d['home.footer.newsletter.button'] = 'Subscribe';
        $d['home.footer.strip.1'] = 'target 99.95% SLA · synthetic checks';
        $d['home.footer.strip.2'] = 'API gateway · rate limits · idempotent writes';
        $d['home.footer.strip.3'] = 'orchestrator · audit · replay-safe logs';
        $d['home.footer.copyright_suffix'] = 'RATIB — Ratib Software Foundation for Information Technology';
        $d['home.footer.location'] = 'Riyadh, Saudi Arabia';

        $d['home.footer.link.platform.overview'] = 'Overview';
        $d['home.footer.link.platform.ops_visibility'] = 'Operational visibility';
        $d['home.footer.link.platform.apis'] = 'APIs';
        $d['home.footer.link.solutions'] = 'Solutions';
        $d['home.footer.link.demo'] = 'Demo';
        $d['home.footer.link.service_registration'] = 'Service registration';

        $d['home.chat.title'] = 'Ratib Assistant';
        $d['home.chat.subtitle'] = 'Help guides & live support';

        $d['home.program.img1'] = '';
        $d['home.program.img2'] = '';
        $d['home.program.img3'] = '';

        return $d;
    }
}

if (!function_exists('ratib_site_content_home_flat_overlay_live_db')) {
    /**
     * After reading JSON/blob snapshot, merge any rows found in ratib_site_content on top.
     * Prevents stale cache files (written when DB was down or from another path) from masking live CMS data.
     *
     * @param array<string, string> $base
     * @param array<string, string> $defaults
     *
     * @return array<string, string>
     */
    function ratib_site_content_home_flat_overlay_live_db(array $base, array $defaults): array
    {
        if (!function_exists('ratib_site_content_fetch_key_values') || !function_exists('ratib_site_content_db')) {
            return $base;
        }
        ratib_site_content_db(true);
        if (!ratib_site_content_db()) {
            return $base;
        }
        $rows = ratib_site_content_fetch_key_values(array_keys($defaults));
        $out = $base;
        foreach ($defaults as $k => $def) {
            if (array_key_exists($k, $rows)) {
                $out[$k] = (string) $rows[$k];

                continue;
            }
            // Same rule as ratib_site_content_home_flat_from_db: never trust stale cache for a key if the row exists.
            $fb = $base[$k] ?? $def;
            $out[$k] = ratib_site_content_get($k, $fb);
        }

        return $out;
    }
}

if (!function_exists('ratib_site_content_home_flat')) {
    /**
     * Resolved key => value for homepage (DB overrides defaults).
     *
     * @return array<string, string>
     */
    function ratib_site_content_home_flat(): array
    {
        // Do not use a cross-request static cache here: PHP-FPM workers would keep the first resolution
        // (often stale JSON / defaults when MySQL was briefly unreachable) for the worker lifetime even
        // after DB access and GRANTs are fixed — making the homepage appear "stuck" forever until restart.
        $defaults = ratib_site_content_defaults_home();

        $applyHomeCache = static function (array $cached, array $defaults): array {
            $out = $defaults;
            foreach ($cached as $key => $val) {
                if (array_key_exists($key, $defaults)) {
                    $out[$key] = (string) $val;
                }
            }

            return $out;
        };

        // 1) Live database rows (same source as the CMS). Always wins when MySQL is reachable so stale JSON/snapshot
        //    cannot hide fresh saves.
        if (function_exists('ratib_site_content_home_flat_from_db')) {
            $live = ratib_site_content_home_flat_from_db($defaults);
            if ($live !== null) {
                return $live;
            }
        }

        // 2) DB snapshot row (single blob; used when row-level SELECT is not available).
        if (function_exists('ratib_site_content_home_snapshot_db_read')) {
            $rawDb = ratib_site_content_home_snapshot_db_read();
            if ($rawDb !== null && $rawDb !== '') {
                $cached = json_decode($rawDb, true);
                if (is_array($cached)) {
                    $merged = $applyHomeCache($cached, $defaults);

                    return function_exists('ratib_site_content_home_flat_overlay_live_db')
                        ? ratib_site_content_home_flat_overlay_live_db($merged, $defaults)
                        : $merged;
                }
            }
        }

        // 3) JSON file snapshot
        $path = function_exists('ratib_site_content_public_cache_path_for_read')
            ? ratib_site_content_public_cache_path_for_read()
            : null;
        if ($path === null && function_exists('ratib_site_content_public_cache_path')) {
            $legacy = ratib_site_content_public_cache_path();
            $path = is_readable($legacy) ? $legacy : null;
        }
        if ($path !== null && $path !== '') {
            if (is_readable($path)) {
                $raw = @file_get_contents($path);
                if ($raw !== false && $raw !== '') {
                    $cached = json_decode($raw, true);
                    if (is_array($cached)) {
                        $merged = $applyHomeCache($cached, $defaults);

                        return function_exists('ratib_site_content_home_flat_overlay_live_db')
                            ? ratib_site_content_home_flat_overlay_live_db($merged, $defaults)
                            : $merged;
                    }
                }
            }
        }

        $out = [];
        foreach ($defaults as $key => $defaultVal) {
            $out[$key] = ratib_site_content_get($key, $defaultVal);
        }

        return $out;
    }
}

if (!function_exists('ratib_site_content_home_nl_lines')) {
    /**
     * @return list<string>
     */
    function ratib_site_content_home_nl_lines(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = array_map('trim', explode("\n", $text));

        return array_values(array_filter($lines, static function ($l) {
            return $l !== '';
        }));
    }
}

if (!function_exists('ratib_site_content_home_editor_groups')) {
    /**
     * Declarative editor sections for control-panel/pages/control/site-content.php
     *
     * @return list<array<string, mixed>>
     */
    function ratib_site_content_home_editor_groups(): array
    {
        return [
            [
                'id' => 'meta',
                'title' => 'Meta & browser title',
                'fields' => [
                    ['key' => 'home.meta.page_title', 'label' => 'Page title (browser tab)', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'topbar',
                'title' => 'Top bar',
                'fields' => [
                    ['key' => 'home.topbar.phone_display', 'label' => 'Phone (display text)', 'type' => 'text'],
                    ['key' => 'home.topbar.wa_label', 'label' => 'WhatsApp line', 'type' => 'text'],
                    ['key' => 'home.topbar.tls_label', 'label' => 'Ops · TLS label', 'type' => 'text'],
                    ['key' => 'home.topbar.nodes_count', 'label' => 'Ops · nodes count (shown)', 'type' => 'text'],
                    ['key' => 'home.topbar.nodes_suffix', 'label' => 'Ops · after count (e.g. nodes)', 'type' => 'text'],
                    ['key' => 'home.topbar.client_login', 'label' => 'Client login', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'nav',
                'title' => 'Header navigation labels',
                'fields' => [
                    ['key' => 'home.nav.platform', 'label' => 'Platform', 'type' => 'text'],
                    ['key' => 'home.nav.how_it_works', 'label' => 'How it works', 'type' => 'text'],
                    ['key' => 'home.nav.features', 'label' => 'Features', 'type' => 'text'],
                    ['key' => 'home.nav.solutions', 'label' => 'Solutions', 'type' => 'text'],
                    ['key' => 'home.nav.programs', 'label' => 'Pricing', 'type' => 'text'],
                    ['key' => 'home.nav.agencies', 'label' => 'Agencies', 'type' => 'text'],
                    ['key' => 'home.nav.tracking', 'label' => 'Tracking', 'type' => 'text'],
                    ['key' => 'home.nav.operational', 'label' => 'Visibility', 'type' => 'text'],
                    ['key' => 'home.nav.api', 'label' => 'API', 'type' => 'text'],
                    ['key' => 'home.nav.contact', 'label' => 'Contact', 'type' => 'text'],
                    ['key' => 'home.nav.cta_partner', 'label' => 'CTA · Partner Login', 'type' => 'text'],
                    ['key' => 'home.nav.cta_primary', 'label' => 'CTA · Start agency infrastructure', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'hero',
                'title' => 'Hero',
                'fields' => [
                    ['key' => 'home.hero.eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                    ['key' => 'home.hero.title_before', 'label' => 'Title (before gradient)', 'type' => 'text'],
                    ['key' => 'home.hero.title_gradient', 'label' => 'Title (gradient phrase)', 'type' => 'text'],
                    ['key' => 'home.hero.lead', 'label' => 'Lead paragraph', 'type' => 'textarea', 'rows' => 4],
                    ['key' => 'home.hero.bullet.1', 'label' => 'Bullet 1', 'type' => 'text'],
                    ['key' => 'home.hero.bullet.2', 'label' => 'Bullet 2', 'type' => 'text'],
                    ['key' => 'home.hero.bullet.3', 'label' => 'Bullet 3', 'type' => 'text'],
                    ['key' => 'home.hero.bullet.4', 'label' => 'Bullet 4', 'type' => 'text'],
                    ['key' => 'home.hero.cta_primary', 'label' => 'Primary button', 'type' => 'text'],
                    ['key' => 'home.hero.cta_secondary', 'label' => 'Secondary button', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'video',
                'title' => 'Product tour (video band)',
                'fields' => [
                    ['key' => 'home.video.eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                    ['key' => 'home.video.title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'home.video.caption', 'label' => 'Caption', 'type' => 'textarea', 'rows' => 2],
                ],
            ],
            [
                'id' => 'program',
                'title' => 'Program preview strip',
                'fields' => [
                    ['key' => 'home.program.strip_eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                    ['key' => 'home.program.caption.1', 'label' => 'Image 1 caption', 'type' => 'text'],
                    ['key' => 'home.program.caption.2', 'label' => 'Image 2 caption', 'type' => 'text'],
                    ['key' => 'home.program.caption.3', 'label' => 'Image 3 caption', 'type' => 'text'],
                    ['key' => 'home.program.alt.1', 'label' => 'Image 1 alt text', 'type' => 'text'],
                    ['key' => 'home.program.alt.2', 'label' => 'Image 2 alt text', 'type' => 'text'],
                    ['key' => 'home.program.alt.3', 'label' => 'Image 3 alt text', 'type' => 'text'],
                    ['key' => 'home.program.img1', 'label' => 'Image 1 path or URL (optional)', 'type' => 'text', 'class' => 'font-monospace small'],
                    ['key' => 'home.program.img2', 'label' => 'Image 2 path or URL (optional)', 'type' => 'text', 'class' => 'font-monospace small'],
                    ['key' => 'home.program.img3', 'label' => 'Image 3 path or URL (optional)', 'type' => 'text', 'class' => 'font-monospace small'],
                ],
            ],
            [
                'id' => 'platform',
                'title' => 'Platform (#platform)',
                'fields' => [
                    ['key' => 'home.platform.title', 'label' => 'Section title', 'type' => 'text'],
                    ['key' => 'home.platform.sub', 'label' => 'Subtitle', 'type' => 'textarea', 'rows' => 3],
                ],
            ],
            [
                'id' => 'trust',
                'title' => 'Trust cards (6)',
                'repeat' => [
                    'from' => 1,
                    'to' => 6,
                    'fields' => [
                        ['suffix' => '.title', 'label' => 'Card %d title'],
                        ['suffix' => '.body', 'label' => 'Card %d body', 'type' => 'textarea', 'rows' => 2],
                    ],
                    'prefix' => 'home.trust',
                ],
            ],
            [
                'id' => 'how',
                'title' => 'How it works',
                'fields' => [
                    ['key' => 'home.how.eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                    ['key' => 'home.how.title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'home.how.sub', 'label' => 'Subtitle', 'type' => 'textarea', 'rows' => 2],
                ],
                'repeat' => [
                    'from' => 1,
                    'to' => 7,
                    'fields' => [
                        ['suffix' => '.title', 'label' => 'Step %d title'],
                        ['suffix' => '.desc', 'label' => 'Step %d description', 'type' => 'textarea', 'rows' => 2],
                    ],
                    'prefix' => 'home.how.step',
                ],
            ],
            [
                'id' => 'features',
                'title' => 'Features (12)',
                'fields' => [
                    ['key' => 'home.features.eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                    ['key' => 'home.features.title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'home.features.sub', 'label' => 'Subtitle', 'type' => 'text'],
                ],
                'repeat' => [
                    'from' => 1,
                    'to' => 12,
                    'fields' => [
                        ['suffix' => '.title', 'label' => 'Feature %d title'],
                        ['suffix' => '.body', 'label' => 'Feature %d body', 'type' => 'textarea', 'rows' => 2],
                    ],
                    'prefix' => 'home.features',
                ],
            ],
            [
                'id' => 'pipeline',
                'title' => 'Pipeline / tracking',
                'fields' => [
                    ['key' => 'home.pipeline.eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                    ['key' => 'home.pipeline.title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'home.pipeline.sub', 'label' => 'Subtitle', 'type' => 'textarea', 'rows' => 2],
                ],
                'repeat' => [
                    'from' => 1,
                    'to' => 8,
                    'fields' => [
                        ['suffix' => '.label', 'label' => 'Stage %d label'],
                        ['suffix' => '.meta', 'label' => 'Stage %d meta line', 'type' => 'text'],
                    ],
                    'prefix' => 'home.pipeline.step',
                ],
            ],
            [
                'id' => 'solutions',
                'title' => 'Solutions',
                'fields' => [
                    ['key' => 'home.solutions.eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                    ['key' => 'home.solutions.title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'home.solutions.sub', 'label' => 'Subtitle', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.solutions.1.title', 'label' => 'Card 1 title (wide)', 'type' => 'text'],
                    ['key' => 'home.solutions.1.body', 'label' => 'Card 1 body', 'type' => 'textarea', 'rows' => 3],
                    ['key' => 'home.solutions.1.demo_row.1', 'label' => 'Card 1 demo pill A (left)', 'type' => 'text'],
                    ['key' => 'home.solutions.1.demo_row.1b', 'label' => 'Card 1 demo pill A (right)', 'type' => 'text'],
                    ['key' => 'home.solutions.1.demo_row.2', 'label' => 'Card 1 demo pill B (left)', 'type' => 'text'],
                    ['key' => 'home.solutions.1.demo_row.2b', 'label' => 'Card 1 demo pill B (right)', 'type' => 'text'],
                    ['key' => 'home.solutions.1.demo_row.3', 'label' => 'Card 1 demo pill C (left)', 'type' => 'text'],
                    ['key' => 'home.solutions.1.demo_row.3b', 'label' => 'Card 1 demo pill C (right)', 'type' => 'text'],
                    ['key' => 'home.solutions.2.title', 'label' => 'Card 2 title', 'type' => 'text'],
                    ['key' => 'home.solutions.2.body', 'label' => 'Card 2 body', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.solutions.3.title', 'label' => 'Card 3 title', 'type' => 'text'],
                    ['key' => 'home.solutions.3.body', 'label' => 'Card 3 body', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.solutions.4.title', 'label' => 'Card 4 title', 'type' => 'text'],
                    ['key' => 'home.solutions.4.body', 'label' => 'Card 4 body', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.solutions.5.title', 'label' => 'Card 5 title', 'type' => 'text'],
                    ['key' => 'home.solutions.5.body', 'label' => 'Card 5 body', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.solutions.6.title', 'label' => 'Card 6 title', 'type' => 'text'],
                    ['key' => 'home.solutions.6.body', 'label' => 'Card 6 body', 'type' => 'textarea', 'rows' => 2],
                ],
            ],
            [
                'id' => 'agencies',
                'title' => 'Agencies ecosystem',
                'fields' => [
                    ['key' => 'home.agencies.eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                    ['key' => 'home.agencies.title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'home.agencies.sub', 'label' => 'Subtitle', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.agencies.core.label', 'label' => 'Core label', 'type' => 'text'],
                    ['key' => 'home.agencies.core.sub', 'label' => 'Core subtitle', 'type' => 'text'],
                    ['key' => 'home.agencies.spoke.1.label', 'label' => 'Spoke 1 label', 'type' => 'text'],
                    ['key' => 'home.agencies.spoke.1.small', 'label' => 'Spoke 1 small line', 'type' => 'text'],
                    ['key' => 'home.agencies.spoke.2.label', 'label' => 'Spoke 2 label', 'type' => 'text'],
                    ['key' => 'home.agencies.spoke.2.small', 'label' => 'Spoke 2 small line', 'type' => 'text'],
                    ['key' => 'home.agencies.spoke.3.label', 'label' => 'Spoke 3 label', 'type' => 'text'],
                    ['key' => 'home.agencies.spoke.3.small', 'label' => 'Spoke 3 small line', 'type' => 'text'],
                    ['key' => 'home.agencies.spoke.4.label', 'label' => 'Spoke 4 label', 'type' => 'text'],
                    ['key' => 'home.agencies.spoke.4.small', 'label' => 'Spoke 4 small line', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'analytics',
                'title' => 'Analytics cards',
                'fields' => [
                    ['key' => 'home.analytics.eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                    ['key' => 'home.analytics.title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'home.analytics.sub', 'label' => 'Subtitle', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.analytics.1.stamp', 'label' => 'Card 1 stamp', 'type' => 'text'],
                    ['key' => 'home.analytics.1.title', 'label' => 'Card 1 title', 'type' => 'text'],
                    ['key' => 'home.analytics.1.metric', 'label' => 'Card 1 metric', 'type' => 'text'],
                    ['key' => 'home.analytics.1.body', 'label' => 'Card 1 body', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.analytics.2.stamp', 'label' => 'Card 2 stamp', 'type' => 'text'],
                    ['key' => 'home.analytics.2.title', 'label' => 'Card 2 title', 'type' => 'text'],
                    ['key' => 'home.analytics.2.metric', 'label' => 'Card 2 metric', 'type' => 'text'],
                    ['key' => 'home.analytics.2.body', 'label' => 'Card 2 body', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.analytics.3.stamp', 'label' => 'Card 3 stamp', 'type' => 'text'],
                    ['key' => 'home.analytics.3.title', 'label' => 'Card 3 title', 'type' => 'text'],
                    ['key' => 'home.analytics.3.metric', 'label' => 'Card 3 metric', 'type' => 'text'],
                    ['key' => 'home.analytics.3.note', 'label' => 'Card 3 note (e.g. QoQ)', 'type' => 'text'],
                    ['key' => 'home.analytics.3.body', 'label' => 'Card 3 body', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.analytics.4.stamp', 'label' => 'Card 4 stamp', 'type' => 'text'],
                    ['key' => 'home.analytics.4.title', 'label' => 'Card 4 title', 'type' => 'text'],
                    ['key' => 'home.analytics.4.metric', 'label' => 'Card 4 metric', 'type' => 'text'],
                    ['key' => 'home.analytics.4.note', 'label' => 'Card 4 note', 'type' => 'text'],
                    ['key' => 'home.analytics.4.body', 'label' => 'Card 4 body', 'type' => 'textarea', 'rows' => 2],
                ],
            ],
            [
                'id' => 'ops',
                'title' => 'Operational visibility + trust band',
                'fields' => [
                    ['key' => 'home.ops.eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                    ['key' => 'home.ops.title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'home.ops.sub', 'label' => 'Subtitle', 'type' => 'textarea', 'rows' => 3],
                    ['key' => 'home.ops.band.1.k', 'label' => 'Band 1 key', 'type' => 'text'],
                    ['key' => 'home.ops.band.1.v', 'label' => 'Band 1 value', 'type' => 'text'],
                    ['key' => 'home.ops.band.2.k', 'label' => 'Band 2 key', 'type' => 'text'],
                    ['key' => 'home.ops.band.2.v', 'label' => 'Band 2 value', 'type' => 'text'],
                    ['key' => 'home.ops.band.3.k', 'label' => 'Band 3 key', 'type' => 'text'],
                    ['key' => 'home.ops.band.3.v', 'label' => 'Band 3 value', 'type' => 'text'],
                    ['key' => 'home.ops.band.4.k', 'label' => 'Band 4 key', 'type' => 'text'],
                    ['key' => 'home.ops.band.4.v', 'label' => 'Band 4 value', 'type' => 'text'],
                    ['key' => 'home.ops.band.5.k', 'label' => 'Band 5 key', 'type' => 'text'],
                    ['key' => 'home.ops.band.5.v', 'label' => 'Band 5 value', 'type' => 'text'],
                    ['key' => 'home.ops.band.6.k', 'label' => 'Band 6 key', 'type' => 'text'],
                    ['key' => 'home.ops.band.6.v', 'label' => 'Band 6 value', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'api',
                'title' => 'API strip',
                'fields' => [
                    ['key' => 'home.api.eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                    ['key' => 'home.api.title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'home.api.sub', 'label' => 'Subtitle', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.api.cta', 'label' => 'Button', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'pricing',
                'title' => 'Pricing section',
                'fields' => [
                    ['key' => 'home.pricing.eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                    ['key' => 'home.pricing.title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'home.pricing.sub', 'label' => 'Subtitle', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.pricing.starter.badge', 'label' => 'Starter · badge', 'type' => 'text'],
                    ['key' => 'home.pricing.starter.plan', 'label' => 'Starter · plan name', 'type' => 'text'],
                    ['key' => 'home.pricing.starter.subtitle', 'label' => 'Starter · subtitle', 'type' => 'text'],
                    ['key' => 'home.pricing.starter.price_line', 'label' => 'Starter · price line', 'type' => 'text'],
                    ['key' => 'home.pricing.starter.features', 'label' => 'Starter · features (one per line)', 'type' => 'textarea', 'rows' => 5],
                    ['key' => 'home.pricing.starter.cta', 'label' => 'Starter · button', 'type' => 'text'],
                    ['key' => 'home.pricing.gold.badge', 'label' => 'Gold · badge', 'type' => 'text'],
                    ['key' => 'home.pricing.gold.plan_word', 'label' => 'Gold · plan word (before list price)', 'type' => 'text'],
                    ['key' => 'home.pricing.gold.subtitle', 'label' => 'Gold · subtitle', 'type' => 'text'],
                    ['key' => 'home.pricing.gold.discount_label', 'label' => 'Gold · discount label', 'type' => 'text'],
                    ['key' => 'home.pricing.gold.features', 'label' => 'Gold · features (one per line)', 'type' => 'textarea', 'rows' => 8],
                    ['key' => 'home.pricing.gold.cta', 'label' => 'Gold · button', 'type' => 'text'],
                    ['key' => 'home.pricing.platinum.badge', 'label' => 'Platinum · badge', 'type' => 'text'],
                    ['key' => 'home.pricing.platinum.plan_word', 'label' => 'Platinum · plan word', 'type' => 'text'],
                    ['key' => 'home.pricing.platinum.subtitle', 'label' => 'Platinum · subtitle', 'type' => 'text'],
                    ['key' => 'home.pricing.platinum.discount_label', 'label' => 'Platinum · discount label', 'type' => 'text'],
                    ['key' => 'home.pricing.platinum.features', 'label' => 'Platinum · features (one per line)', 'type' => 'textarea', 'rows' => 8],
                    ['key' => 'home.pricing.platinum.cta', 'label' => 'Platinum · button', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'register',
                'title' => 'Registration block',
                'fields' => [
                    ['key' => 'home.register.info.title', 'label' => 'Info panel title', 'type' => 'text'],
                    ['key' => 'home.register.info.intro', 'label' => 'Info intro', 'type' => 'textarea', 'rows' => 3],
                    ['key' => 'home.register.check.1', 'label' => 'Checklist 1 (HTML allowed: &lt;strong&gt;)', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.register.check.2', 'label' => 'Checklist 2', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.register.check.3', 'label' => 'Checklist 3', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.register.check.4', 'label' => 'Checklist 4', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.register.check.5', 'label' => 'Checklist 5', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.register.check.6', 'label' => 'Checklist 6', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.register.check.7', 'label' => 'Checklist 7', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.register.form.title', 'label' => 'Form title', 'type' => 'text'],
                    ['key' => 'home.register.form.plan_hint', 'label' => 'Plan hint (HTML)', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.register.payment_placeholder', 'label' => 'Payment placeholder (HTML)', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.register.payment_summary.title', 'label' => 'Payment summary title', 'type' => 'text'],
                    ['key' => 'home.register.payment_summary.footer', 'label' => 'Payment summary footer', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.register.submit', 'label' => 'Submit button', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'final',
                'title' => 'Final CTA',
                'fields' => [
                    ['key' => 'home.final_cta.title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'home.final_cta.sub', 'label' => 'Subtitle', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.final_cta.btn_primary', 'label' => 'Primary button', 'type' => 'text'],
                    ['key' => 'home.final_cta.btn_secondary', 'label' => 'Secondary button', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'footer',
                'title' => 'Footer',
                'fields' => [
                    ['key' => 'home.footer.brand', 'label' => 'Brand blurb', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.footer.col.platform', 'label' => 'Column · Platform', 'type' => 'text'],
                    ['key' => 'home.footer.col.company', 'label' => 'Column · Company', 'type' => 'text'],
                    ['key' => 'home.footer.col.support', 'label' => 'Column · Support', 'type' => 'text'],
                    ['key' => 'home.footer.col.legal', 'label' => 'Column · Legal', 'type' => 'text'],
                    ['key' => 'home.footer.col.infra', 'label' => 'Column · Infrastructure', 'type' => 'text'],
                    ['key' => 'home.footer.infra.copy', 'label' => 'Infrastructure copy', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'home.footer.newsletter.label', 'label' => 'Newsletter label', 'type' => 'text'],
                    ['key' => 'home.footer.newsletter.placeholder', 'label' => 'Newsletter placeholder', 'type' => 'text'],
                    ['key' => 'home.footer.newsletter.button', 'label' => 'Newsletter button', 'type' => 'text'],
                    ['key' => 'home.footer.strip.1', 'label' => 'System strip line 1 (after mono tag)', 'type' => 'text'],
                    ['key' => 'home.footer.strip.2', 'label' => 'System strip line 2', 'type' => 'text'],
                    ['key' => 'home.footer.strip.3', 'label' => 'System strip line 3', 'type' => 'text'],
                    ['key' => 'home.footer.copyright_suffix', 'label' => 'Copyright line (after year)', 'type' => 'text'],
                    ['key' => 'home.footer.location', 'label' => 'Location line', 'type' => 'text'],
                    ['key' => 'home.footer.link.platform.overview', 'label' => 'Link · Overview', 'type' => 'text'],
                    ['key' => 'home.footer.link.platform.ops_visibility', 'label' => 'Link · Operational visibility', 'type' => 'text'],
                    ['key' => 'home.footer.link.platform.apis', 'label' => 'Link · APIs', 'type' => 'text'],
                    ['key' => 'home.footer.link.solutions', 'label' => 'Link · Solutions', 'type' => 'text'],
                    ['key' => 'home.footer.link.demo', 'label' => 'Link · Demo', 'type' => 'text'],
                    ['key' => 'home.footer.link.service_registration', 'label' => 'Link · Service registration', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'chat',
                'title' => 'Chat widget header',
                'fields' => [
                    ['key' => 'home.chat.title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'home.chat.subtitle', 'label' => 'Subtitle', 'type' => 'text'],
                ],
            ],
        ];
    }
}
