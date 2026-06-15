<?php
/**
 * Enterprise architecture page — content for pages/architecture.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/ratib-public-cms.php';

if (!function_exists('ratib_architecture_config')) {
    /**
     * @return array<string, mixed>
     */
    function ratib_architecture_config(string $baseUrl): array
    {
        $enterpriseMail = 'info@rateb.sa';
        $securityUrl = rtrim($baseUrl, '/') . '/security-compliance/';

        return [
            'meta' => [
                'title' => ratib_public_cms('arch.meta.title', 'Platform Architecture — RATEB'),
                'description' => ratib_public_cms('arch.meta.description', 'Layered workforce program platform: shared core, tenant isolation, event delivery, field operations, finance module, and deployment topology for enterprise technical review.'),
            ],
            'hero' => [
                'eyebrow' => ratib_public_cms('arch.hero.eyebrow', 'Platform architecture'),
                'title' => ratib_public_cms('arch.hero.title', 'Multi-agency workforce program platform'),
                'lead' => ratib_public_cms('arch.hero.lead', 'RATEB is organized in platform layers that connect agency workspaces, partner portals, and regulated corridors—with separate agency databases, event-driven workflows, and policy controls in the stack.'),
                'diagram_label' => 'platform layer stack',
                'stack_preview' => [
                    'Experience',
                    'Orchestration',
                    'Telemetry',
                    'Business modules',
                    'Governance',
                    'Commercial',
                    'Data',
                ],
            ],
            'overview' => [
                'id' => 'architecture-overview',
                'eyebrow' => ratib_public_cms('arch.overview.eyebrow', 'Architecture overview'),
                'title' => ratib_public_cms('arch.overview.title', 'Orchestration infrastructure, not a single application'),
                'sub' => ratib_public_cms('arch.overview.sub', 'Programs, corridors, and agencies share a common workflow core while program data stays in separate agency databases. The platform separates user interfaces, workflow execution, field operations, finance, and storage.'),
                'points' => [
                    ['label' => 'Primary role', 'body' => 'Coordinate workforce lifecycle stages, documents, finance events, and field telemetry across sending and host markets.'],
                    ['label' => 'Tenancy model', 'body' => 'Shared orchestration with isolated agency datastores and policy-scoped operator access.'],
                    ['label' => 'Integration surface', 'body' => 'REST APIs, signed webhooks, and server-sent streams for partner and government-aligned systems.'],
                    ['label' => 'Review posture', 'body' => 'Technical documentation for CTO, procurement, and enterprise architecture review — not a product tour.'],
                ],
            ],
            'layers' => [
                'id' => 'layered-control-plane',
                'eyebrow' => 'Platform layers',
                'title' => 'Seven layers with explicit boundaries',
                'sub' => 'Each layer owns a distinct responsibility set. Upper layers consume contracts from lower layers; cross-layer calls flow through orchestration and policy gates.',
                'items' => [
                    [
                        'order' => 7,
                        'key' => 'experience',
                        'title' => 'Experience Layer',
                        'responsibilities' => 'Agency consoles, operator workspaces, partner-facing surfaces, and localized program UIs.',
                        'operational_role' => 'Presents stage graphs, task queues, and corridor context without embedding business rules in the client.',
                        'boundaries' => 'No direct datastore access; all mutations route through orchestration APIs with session and RBAC enforcement.',
                        'icon' => 'fa-desktop',
                    ],
                    [
                        'order' => 6,
                        'key' => 'orchestration',
                        'title' => 'Orchestration Layer',
                        'responsibilities' => 'Stage graphs, workflow engines, assignment queues, verification pipelines, and cross-module coordination.',
                        'operational_role' => 'Single execution authority for lifecycle transitions, retries, and correlation across modules.',
                        'boundaries' => 'Does not own long-term storage of program records; commits outcomes to tenant datastores via the data layer.',
                        'icon' => 'fa-diagram-project',
                    ],
                    [
                        'order' => 5,
                        'key' => 'telemetry',
                        'title' => 'Telemetry Layer',
                        'responsibilities' => 'Geospatial signals, offline sync buffers, geofence evaluation, and escalation routing.',
                        'operational_role' => 'Operational visibility and anomaly routing for field programs — distinct from passive analytics.',
                        'boundaries' => 'Consumes orchestration context; does not mutate finance or governance state without workflow gates.',
                        'icon' => 'fa-satellite-dish',
                    ],
                    [
                        'order' => 4,
                        'key' => 'business',
                        'title' => 'Business Modules',
                        'responsibilities' => 'Recruitment, deployment, documents, inspections, violations, and corridor-specific program modules.',
                        'operational_role' => 'Domain logic packaged as modules invoked by orchestration — not standalone silos.',
                        'boundaries' => 'Module APIs are tenant-scoped; cross-tenant reads are blocked at connection and policy layers.',
                        'icon' => 'fa-cubes',
                    ],
                    [
                        'order' => 3,
                        'key' => 'governance',
                        'title' => 'Governance Layer',
                        'responsibilities' => 'RBAC, country profiles, policy enforcement, audit history, and labor oversight workflows.',
                        'operational_role' => 'Evaluates whether a transition, export, or operator action is permitted before commit.',
                        'boundaries' => 'Policy decisions are logged; governance does not replace orchestration execution.',
                        'icon' => 'fa-scale-balanced',
                    ],
                    [
                        'order' => 2,
                        'key' => 'commercial',
                        'title' => 'Commercial Layer',
                        'responsibilities' => 'Ledger, AR/AP, multi-currency postings, registration fees, and payment synchronization.',
                        'operational_role' => 'Financial truth linked to lifecycle events via transaction correlation identifiers.',
                        'boundaries' => 'No program stage commits without orchestration; finance events are idempotent where configured.',
                        'icon' => 'fa-coins',
                    ],
                    [
                        'order' => 1,
                        'key' => 'data',
                        'title' => 'Data Layer',
                        'responsibilities' => 'Platform configuration, tenant routing, and isolated agency program datastores.',
                        'operational_role' => 'Persistence, backups, and connection scoping for multi-agency operations.',
                        'boundaries' => 'Platform stores hold configuration and routing; agency databases hold program records—not cross-tenant workflow config.',
                        'icon' => 'fa-database',
                    ],
                ],
            ],
            'isolation' => [
                'id' => 'multi-tenant-isolation',
                'eyebrow' => 'Multi-tenant isolation',
                'title' => 'Shared core, isolated program data',
                'sub' => 'Orchestration and governance are centralized; workforce records and operational state remain tenant-bound.',
                'pillars' => [
                    ['title' => 'Shared orchestration core', 'body' => 'One workflow engine and policy graph serve all agencies — reducing duplicated stacks per tenant.', 'icon' => 'fa-share-nodes'],
                    ['title' => 'Isolated tenant datastores', 'body' => 'Agency program databases hold workers, documents, and stage history with connection-level segregation.', 'icon' => 'fa-server'],
                    ['title' => 'Governance boundaries', 'body' => 'Country scope, branch RBAC, and API keys constrain what operators and integrations can observe.', 'icon' => 'fa-shield-halved'],
                    ['title' => 'Scoped operations', 'body' => 'Finance, telemetry, and exports remain attributable to tenant, corridor, and actor context.', 'icon' => 'fa-crosshairs'],
                ],
            ],
            'events' => [
                'id' => 'event-driven',
                'eyebrow' => 'Event-driven infrastructure',
                'title' => 'Event fabric with replay-safe delivery',
                'sub' => 'Lifecycle and integration events flow through a common fabric with ordered replay, signed webhooks, and idempotent consumers.',
                'capabilities' => [
                    ['title' => 'Event fabric', 'body' => 'Normalized event envelopes with correlation IDs, tenant context, and policy version references.', 'icon' => 'fa-bolt'],
                    ['title' => 'Webhooks', 'body' => 'Outbound HMAC-signed delivery for partner systems; verification before side effects.', 'icon' => 'fa-plug'],
                    ['title' => 'SSE streams', 'body' => 'Server-sent streams for operator consoles requiring near-real-time queue and stage updates.', 'icon' => 'fa-signal'],
                    ['title' => 'Orchestrated workflows', 'body' => 'Stage graphs consume events to advance, branch, or hold programs pending verification.', 'icon' => 'fa-sitemap'],
                    ['title' => 'Replay-safe operations', 'body' => 'Consumers designed for at-least-once delivery without duplicate commits to finance or lifecycle state.', 'icon' => 'fa-rotate'],
                    ['title' => 'Idempotency', 'body' => 'Idempotency keys on write paths for API clients, webhooks, and field sync batches.', 'icon' => 'fa-check-double'],
                ],
                'flow' => [
                    ['step' => 'Emit', 'detail' => 'Orchestration publishes lifecycle or integration event'],
                    ['step' => 'Route', 'detail' => 'Fabric fans out to webhooks, SSE subscribers, and internal modules'],
                    ['step' => 'Verify', 'detail' => 'HMAC / session scope validated at edge'],
                    ['step' => 'Commit', 'detail' => 'Idempotent handler persists or advances stage'],
                ],
            ],
            'telemetry' => [
                'id' => 'telemetry-intelligence',
                'eyebrow' => 'Field operations',
                'title' => 'Location-assisted operations for field programs',
                'sub' => 'Location and device signals support deployment oversight—with offline sync and basic consistency checks.',
                'items' => [
                    ['title' => 'Geospatial telemetry', 'body' => 'Location checkpoints tied to worker and program context for corridor operations.', 'icon' => 'fa-location-dot'],
                    ['title' => 'Offline synchronization', 'body' => 'Buffered uploads reconcile when connectivity returns without losing correlation order.', 'icon' => 'fa-wifi'],
                    ['title' => 'Signal validation', 'body' => 'Consistency checks flag implausible location jumps, device mismatches, or stale updates.', 'icon' => 'fa-user-secret'],
                    ['title' => 'Geofence rules', 'body' => 'Program-defined zones trigger holds, alerts, or escalation paths through workflows.', 'icon' => 'fa-draw-polygon'],
                    ['title' => 'Operational escalation', 'body' => 'Anomalies route to operator queues with audit attribution — not silent background logging only.', 'icon' => 'fa-triangle-exclamation'],
                ],
            ],
            'finance' => [
                'id' => 'finance-infrastructure',
                'eyebrow' => 'Finance infrastructure',
                'title' => 'Ledger-linked commercial subsystem',
                'sub' => 'Financial events remain correlated to lifecycle stages and registration flows across currencies.',
                'items' => [
                    ['title' => 'Ledger subsystem', 'body' => 'Double-entry style postings with program and tenant attribution on each line.', 'icon' => 'fa-book'],
                    ['title' => 'AR / AP', 'body' => 'Receivables and payables aligned to agency billing models and corridor fee schedules.', 'icon' => 'fa-file-invoice-dollar'],
                    ['title' => 'Transaction linkage', 'body' => 'Payments and adjustments reference orchestration correlation IDs for reconciliation.', 'icon' => 'fa-link'],
                    ['title' => 'Multi-currency support', 'body' => 'Posting and display currencies separated where corridor rules require FX context.', 'icon' => 'fa-money-bill-transfer'],
                    ['title' => 'Registration / payment sync', 'body' => 'Signup and renewal flows synchronize commercial state with provisioning gates.', 'icon' => 'fa-credit-card'],
                ],
            ],
            'governance' => [
                'id' => 'operational-governance',
                'eyebrow' => 'Operational governance',
                'title' => 'Policy and accountability across the stack',
                'sub' => 'Governance evaluates requests before orchestration commits — preserving audit-ready history.',
                'items' => [
                    ['title' => 'RBAC', 'body' => 'Roles scoped by country, branch, and module with least-privilege defaults.', 'icon' => 'fa-user-shield'],
                    ['title' => 'Policy enforcement', 'body' => 'Country profiles and stage rules applied consistently before transitions.', 'icon' => 'fa-gavel'],
                    ['title' => 'Audit history', 'body' => 'Append-oriented records of actor, policy version, and correlation on sensitive actions.', 'icon' => 'fa-clock-rotate-left'],
                    ['title' => 'Country scopes', 'body' => 'Sending-market and host-market boundaries enforced on data and operator visibility.', 'icon' => 'fa-globe'],
                    ['title' => 'Labor oversight support', 'body' => 'Inspections, violations, deploy blocks, and visibility modules for government-aligned review.', 'icon' => 'fa-landmark'],
                ],
            ],
            'deployment' => [
                'id' => 'deployment-model',
                'eyebrow' => 'Deployment model',
                'title' => 'Topology from edge to tenant datastore',
                'sub' => 'Public surfaces, agency workspaces, and partner integrations converge on the orchestration core and tenant-bound persistence.',
                'nodes' => [
                    ['id' => 'edge', 'label' => 'Public edge', 'tier' => 'edge', 'body' => 'TLS termination, rate limits, and static marketing or trust surfaces.'],
                    ['id' => 'agency', 'label' => 'Agency workspace', 'tier' => 'client', 'body' => 'Tenant-scoped operator console and program administration.'],
                    ['id' => 'partner', 'label' => 'Partner portals', 'tier' => 'client', 'body' => 'Sending-country agency and host-market partner interfaces.'],
                    ['id' => 'api', 'label' => 'APIs', 'tier' => 'gateway', 'body' => 'REST integrations, webhook ingress/egress, scoped API keys.'],
                    ['id' => 'core', 'label' => 'Orchestration core', 'tier' => 'core', 'body' => 'Workflow engine, governance gates, event fabric, module router.'],
                    ['id' => 'tenantdb', 'label' => 'Tenant databases', 'tier' => 'data', 'body' => 'Isolated agency program datastores and document storage paths.'],
                ],
            ],
            'briefing' => [
                'title' => 'Architecture review',
                'body' => 'Request a technical walkthrough of layers, isolation, and deployment topology for procurement or engineering review.',
                'href' => 'mailto:' . $enterpriseMail . '?subject=' . rawurlencode('RATEB — Request Architecture Review'),
                'secondary_label' => 'Security & compliance center',
                'secondary_href' => $securityUrl,
            ],
        ];
    }
}
