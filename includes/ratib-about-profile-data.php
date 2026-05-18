<?php
/**
 * Enterprise company profile — content, screenshots, and section config for pages/about.php.
 */
declare(strict_types=1);

if (!function_exists('ratib_about_profile_config')) {
    /**
     * @return array<string, mixed>
     */
    function ratib_about_profile_config(string $baseUrl): array
    {
        $img = static function (string $file) use ($baseUrl): string {
            return $baseUrl . '/assets/images/' . rawurlencode($file);
        };

        return [
            'meta' => [
                'title' => 'About Ratib Company — Company profile',
                'description' => 'Official Ratib Company profile: who we are, platform architecture, operations modules, and contact — separate from the public marketing homepage.',
            ],
            'screenshots' => [
                'hero' => ['src' => $img('about-ratib-command.png'), 'alt' => 'RATIB operations control plane — live recruitment records, SLA adherence, and workforce telemetry'],
                'ops' => ['src' => $img('1.jpg'), 'alt' => 'Agency operations control plane with workforce and agent metrics'],
                'workers' => ['src' => $img('2.jpg'), 'alt' => 'Workforce system of record — worker lifecycle and document management'],
                'telemetry' => ['src' => $img('3.jpg'), 'alt' => 'Geospatial operations console — live workforce telemetry map'],
                'accounting' => ['src' => $img('4.jpg'), 'alt' => 'Finance-grade ledger subsystem — accounting and invoicing'],
                'control' => ['src' => $img('5.jpg'), 'alt' => 'Platform control plane — multi-country agency governance'],
                'partners' => ['src' => $img('6.jpg'), 'alt' => 'Host-market partner collaboration surface'],
                'pipeline' => ['src' => $img('7.jpg'), 'alt' => 'End-to-end recruitment orchestration pipeline'],
            ],
            'hero_metrics' => [
                ['label' => 'Active lifecycle workload', 'value' => '2.8k', 'delta' => 'workers in motion', 'tone' => 'blue'],
                ['label' => 'SLA adherence', 'value' => '94.6%', 'delta' => 'within policy', 'tone' => 'green'],
                ['label' => 'Stage commits (24h)', 'value' => '412', 'delta' => 'event_log', 'tone' => 'violet'],
                ['label' => 'Orchestrator p95', 'value' => '238ms', 'delta' => 'edge sync OK', 'tone' => 'cyan'],
            ],
            'status_chips' => [
                ['label' => 'TLS 1.3', 'ok' => true],
                ['label' => 'SLA policy OK', 'ok' => true],
                ['label' => 'KYC queue stable', 'ok' => true],
                ['label' => 'Multi-tenant isolation', 'ok' => true],
            ],
            'architecture_layers' => [
                ['id' => 'experience', 'title' => 'Experience Layer', 'body' => 'Public edge, agency operations workspace, platform control plane, partner & client surfaces.', 'icon' => 'fa-layer-group'],
                ['id' => 'orchestration', 'title' => 'Orchestration Layer', 'body' => 'Lifecycle engine, country stage policies, onboarding workflows, event bus, webhooks, SLA escalation.', 'icon' => 'fa-diagram-project'],
                ['id' => 'telemetry', 'title' => 'Telemetry Layer', 'body' => 'Online GPS, offline batch sync, geofence intelligence, anti-spoof, SOS, geospatial operations console.', 'icon' => 'fa-location-crosshairs'],
                ['id' => 'business', 'title' => 'Business Modules', 'body' => 'Workforce SOR, agents, partnerships, visa, HR, ledger, reports, operational signaling.', 'icon' => 'fa-cubes'],
                ['id' => 'governance', 'title' => 'Governance Layer', 'body' => 'RBAC, country scope, government labor oversight, audit trails, tenant rollout flags.', 'icon' => 'fa-shield-halved'],
                ['id' => 'commercial', 'title' => 'Commercial Layer', 'body' => 'Agency provisioning, N-Genius checkout, subscriptions, infrastructure marketplace.', 'icon' => 'fa-credit-card'],
                ['id' => 'data', 'title' => 'Data Layer', 'body' => 'Control database + isolated agency program datastores per tenant.', 'icon' => 'fa-database'],
            ],
            'ops_modules' => [
                ['title' => 'Agent hierarchy', 'body' => 'Multi-level agents and sub-agents with quotas, branch RBAC, and consolidated reporting.', 'icon' => 'fa-sitemap'],
                ['title' => 'Workforce system of record', 'body' => 'Longitudinal worker files, documents, Musaned integration, deployment readiness.', 'icon' => 'fa-users'],
                ['title' => 'Deployment programs', 'body' => 'Host-market placements, returned workers, exception tracking across corridors.', 'icon' => 'fa-plane-departure'],
                ['title' => 'Finance-grade ledger', 'body' => 'Double-entry accounting, AR/AP, banking, multi-currency, entity-linked transactions.', 'icon' => 'fa-calculator'],
                ['title' => 'Human capital module', 'body' => 'Employees, attendance, payroll, advances, fleet and HR documents.', 'icon' => 'fa-id-badge'],
                ['title' => 'Operational signaling', 'body' => 'Event-driven alerts, module notifications, escalation before SLA breach.', 'icon' => 'fa-bell'],
            ],
            'telemetry_features' => [
                ['title' => 'Live workforce telemetry', 'body' => 'Real-time GPS with accuracy, speed, battery, and session state in the control plane.', 'icon' => 'fa-satellite-dish'],
                ['title' => 'Offline batch sync', 'body' => 'Field clients queue locations locally; exponential backoff and oldest-first replay on reconnect.', 'icon' => 'fa-cloud-arrow-up'],
                ['title' => 'Geofence intelligence', 'body' => 'Corridor and handover geofences with breach routing—not passive map pins.', 'icon' => 'fa-draw-polygon'],
                ['title' => 'Anomaly & anti-spoof', 'body' => 'Ingest pipeline: anti-spoof, escape prediction, threat fusion, orchestrated response.', 'icon' => 'fa-triangle-exclamation'],
                ['title' => 'Emergency signaling', 'body' => 'SOS endpoint with location payload for critical field events.', 'icon' => 'fa-life-ring'],
                ['title' => 'Device onboarding', 'body' => 'QR pairing, per-device API tokens, optional single-device enforcement.', 'icon' => 'fa-qrcode'],
            ],
            'governance_features' => [
                ['title' => 'Immutable workflow history', 'body' => 'Append-only stage commits with actor, correlation ID, and policy version.', 'icon' => 'fa-clock-rotate-left'],
                ['title' => 'Government labor oversight', 'body' => 'Inspections, violations, blacklist, deploy blocks tied to worker files.', 'icon' => 'fa-landmark'],
                ['title' => 'Country policy profiles', 'body' => 'Per-corridor rules enforced across onboarding and deployment.', 'icon' => 'fa-globe'],
                ['title' => 'Tenant isolation', 'body' => 'Isolated agency program datastores—control metadata in platform DB.', 'icon' => 'fa-lock'],
                ['title' => 'RBAC & country scope', 'body' => 'Granular permissions with country-scoped operator workspaces.', 'icon' => 'fa-user-shield'],
                ['title' => 'Replay-safe operations', 'body' => 'Idempotent workflows, webhook HMAC, audit-ready event logs.', 'icon' => 'fa-fingerprint'],
            ],
            'finance_features' => [
                ['title' => 'Double-entry ledger', 'body' => 'Journal entries, chart of accounts, trial balance, P&L, balance sheet.', 'icon' => 'fa-book'],
                ['title' => 'AR / AP & banking', 'body' => 'Invoices, bills, vouchers, bank reconciliation, payment allocations.', 'icon' => 'fa-building-columns'],
                ['title' => 'E-invoicing rails', 'body' => 'Issuance when verified lifecycle events align—auditable downstream.', 'icon' => 'fa-file-invoice'],
                ['title' => 'Entity-linked finance', 'body' => 'Transactions tied to agents, workers, and HR—not disconnected spreadsheets.', 'icon' => 'fa-link'],
                ['title' => 'Registration revenue sync', 'body' => 'Payment-gated agency provisioning with idempotent ledger mirror.', 'icon' => 'fa-receipt'],
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
                ['title' => 'TLS 1.3', 'body' => 'Encrypted transit to the edge on all public surfaces.', 'icon' => 'fa-lock'],
                ['title' => 'WebAuthn & biometric', 'body' => 'High-assurance operator authentication options.', 'icon' => 'fa-fingerprint'],
                ['title' => 'Event fabric', 'body' => 'Typed events, SSE streams, outbound webhooks with HMAC.', 'icon' => 'fa-bolt'],
                ['title' => 'Replay-safe workflows', 'body' => 'Idempotency locks, resume-from-step, stall detection.', 'icon' => 'fa-rotate'],
                ['title' => 'Multi-tenant isolation', 'body' => 'Per-agency databases on unified orchestration core.', 'icon' => 'fa-server'],
                ['title' => 'Government execution mode', 'body' => 'Strict policy path for sovereign workforce programs.', 'icon' => 'fa-scale-balanced'],
            ],
            'partner_features' => [
                ['title' => 'Host-market portal', 'body' => 'Scoped dashboard for deployments, documents, and account statements.', 'icon' => 'fa-handshake'],
                ['title' => 'CV & document exchange', 'body' => 'Secure sharing pipeline—not email attachments.', 'icon' => 'fa-file-contract'],
                ['title' => 'REST API v1', 'body' => 'Bearer-token endpoints for workers, workflows, tracking, alerts.', 'icon' => 'fa-code'],
                ['title' => 'Webhook integrations', 'body' => 'HMAC-signed delivery with retry for ERP and government systems.', 'icon' => 'fa-plug'],
            ],
            'platform_services' => [
                ['title' => 'Domain commerce', 'body' => 'Search, transfer, and provisioning through registrar integrations.', 'icon' => 'fa-globe'],
                ['title' => 'SSL & security edge', 'body' => 'Certificate lifecycle and edge protection patterns.', 'icon' => 'fa-shield'],
                ['title' => 'Hosting pipeline', 'body' => 'Registrar → DNS → SSL → hosting orchestration for agency edges.', 'icon' => 'fa-cloud'],
                ['title' => 'Branded agency domains', 'body' => 'White-label edges on shared control plane—not separate stacks.', 'icon' => 'fa-palette'],
            ],
            'not_crm' => [
                'Recruitment CRM with a GPS plugin',
                'Spreadsheet replacement with a login screen',
                'Shared-table SaaS where every agency shares one database',
                'Marketing website with dashboard screenshots',
            ],
            'is_infrastructure' => [
                'Workforce program orchestration infrastructure',
                'Operations control plane for multi-country agencies',
                'Telemetry system with intelligence at ingest',
                'Compliance and audit-ready governance layer',
                'Finance-grade operational stack linked to placements',
            ],
        ];
    }
}
