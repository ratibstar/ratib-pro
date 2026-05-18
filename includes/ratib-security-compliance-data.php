<?php
/**
 * Security & compliance trust center — content for pages/security-compliance.php.
 */
declare(strict_types=1);

if (!function_exists('ratib_security_compliance_config')) {
    /**
     * @return array<string, mixed>
     */
    function ratib_security_compliance_config(string $baseUrl): array
    {
        $enterpriseMail = 'info@out.ratib.sa';
        $mailto = static function (string $subject) use ($enterpriseMail): string {
            return 'mailto:' . $enterpriseMail . '?subject=' . rawurlencode($subject);
        };

        return [
            'meta' => [
                'title' => 'Security & Compliance — RATIB Trust Center',
                'description' => 'RATIB security architecture, compliance governance, tenant isolation, and operational reliability — designed for regulated workforce program infrastructure and enterprise procurement review.',
            ],
            'hero' => [
                'eyebrow' => 'Trust center',
                'title' => 'Security & compliance for regulated workforce operations',
                'lead' => 'RATIB is enterprise workforce program infrastructure designed for agencies and government-aligned programs that require auditable operations, tenant isolation, and procurement-ready governance — without overstating certifications.',
                'chips' => [
                    ['label' => 'TLS 1.3 to edge', 'ok' => true],
                    ['label' => 'Tenant isolation', 'ok' => true],
                    ['label' => 'Audit-ready history', 'ok' => true],
                    ['label' => 'Replay-safe workflows', 'ok' => true],
                ],
            ],
            'disclaimer' => 'This trust center describes platform architecture and operational design. RATIB does not claim third-party certifications (such as SOC 2 or ISO) unless separately documented in a signed enterprise agreement.',
            'sections' => [
                'security_overview' => [
                    'id' => 'security-overview',
                    'eyebrow' => 'Security overview',
                    'title' => 'Layered security at the edge and platform core',
                    'sub' => 'The architecture includes layered controls for transport security, access governance, workflow integrity, and operational visibility.',
                    'items' => [
                        ['title' => 'TLS 1.3', 'body' => 'Encrypted transit to public edges and API gateways; session cookies scoped with modern transport policies.', 'icon' => 'fa-lock'],
                        ['title' => 'Isolated tenant architecture', 'body' => 'Program data paths are designed for per-agency isolation on a shared orchestration core — not shared-table multi-tenant shortcuts.', 'icon' => 'fa-server'],
                        ['title' => 'RBAC', 'body' => 'Role-based access with country scope, branch segregation, and least-privilege operator workspaces.', 'icon' => 'fa-user-shield'],
                        ['title' => 'Audit trails', 'body' => 'Operational events and stage transitions support reviewer attribution and downstream reconciliation.', 'icon' => 'fa-clock-rotate-left'],
                        ['title' => 'Replay-safe workflows', 'body' => 'Idempotency patterns and correlation identifiers reduce duplicate commits during retries and integration replay.', 'icon' => 'fa-rotate'],
                        ['title' => 'Webhook HMAC signing', 'body' => 'Outbound integration events support HMAC verification so partners can authenticate delivery integrity.', 'icon' => 'fa-fingerprint'],
                        ['title' => 'Session controls', 'body' => 'Session revocation, device-aware policies, and operator session boundaries on shared consoles.', 'icon' => 'fa-id-card'],
                        ['title' => 'Infrastructure hardening', 'body' => 'Edge protection patterns, rate-aware gateways, and hardened provisioning paths for agency deployments.', 'icon' => 'fa-shield-halved'],
                        ['title' => 'Operational logging', 'body' => 'Structured logs and event streams designed for ops review, escalation, and procurement evidence packs.', 'icon' => 'fa-list-check'],
                    ],
                ],
                'compliance_governance' => [
                    'id' => 'compliance-governance',
                    'eyebrow' => 'Compliance & governance',
                    'title' => 'Governance for regulated workforce programs',
                    'sub' => 'Supports regulated corridors with policy enforcement, recorded history, and labor-oversight workflows.',
                    'items' => [
                        ['title' => 'Country-scoped operations', 'body' => 'Corridor policies and operator scope align program rules to sending-market and host-market requirements.', 'icon' => 'fa-globe'],
                        ['title' => 'Workforce governance', 'body' => 'Lifecycle gates, document bundles, and deployment readiness tracked as first-class governance artifacts.', 'icon' => 'fa-users'],
                        ['title' => 'Audit-ready lifecycle history', 'body' => 'Longitudinal worker files with checkpoints operators can defend in inspections and program reviews.', 'icon' => 'fa-file-shield'],
                        ['title' => 'Immutable stage commits', 'body' => 'Append-only stage transitions with actor, policy version, and correlation identifiers where configured.', 'icon' => 'fa-database'],
                        ['title' => 'Operator accountability', 'body' => 'Human-in-the-loop gates retain reviewer attribution — automation does not erase accountability.', 'icon' => 'fa-user-check'],
                        ['title' => 'Policy enforcement layer', 'body' => 'Country profiles and stage graphs enforce rules consistently across tenants and corridors.', 'icon' => 'fa-scale-balanced'],
                        ['title' => 'Government oversight support', 'body' => 'Modules for inspections, violations, deploy blocks, and program visibility aligned to labor oversight use cases.', 'icon' => 'fa-landmark'],
                    ],
                ],
                'data_isolation' => [
                    'id' => 'data-isolation',
                    'eyebrow' => 'Data isolation',
                    'title' => 'Platform core vs program datastores',
                    'sub' => 'Separation model for multi-agency operations without duplicating application stacks per tenant.',
                    'layers' => [
                        ['label' => 'Platform configuration database', 'body' => 'Identity, workflow configuration, tenant routing, and shared governance settings.'],
                        ['label' => 'Isolated tenant databases', 'body' => 'Agency program datastores hold workforce records, documents, and operational state with tenant-scoped boundaries.'],
                        ['label' => 'Segregation model', 'body' => 'Shared orchestration core with strict datastore separation — operational boundaries enforced at connection and policy layers.'],
                        ['label' => 'Operational boundaries', 'body' => 'API keys, RBAC, and country scope limit cross-tenant visibility; finance and telemetry events remain attributable.'],
                    ],
                ],
                'authentication' => [
                    'id' => 'authentication',
                    'eyebrow' => 'Authentication & access',
                    'title' => 'High-assurance operator access',
                    'sub' => 'Supports modern authentication options and scoped access for distributed agency operations.',
                    'items' => [
                        ['title' => 'WebAuthn support', 'body' => 'Architecture includes WebAuthn-ready paths for phishing-resistant operator authentication where deployed.', 'icon' => 'fa-fingerprint'],
                        ['title' => 'Biometric options', 'body' => 'Device biometrics can be used where supported by client platforms and agency policy.', 'icon' => 'fa-face-smile'],
                        ['title' => 'MFA-ready architecture', 'body' => 'Multi-factor patterns can be layered on operator login flows as procurement requirements evolve.', 'icon' => 'fa-mobile-screen'],
                        ['title' => 'Scoped operator access', 'body' => 'Branch-level RBAC, country scope, and API key segregation for integrations and automation.', 'icon' => 'fa-key'],
                    ],
                ],
                'reliability' => [
                    'id' => 'operational-reliability',
                    'eyebrow' => 'Operational reliability',
                    'title' => 'Reliability for high-volume programs',
                    'sub' => 'Queue resilience, retry orchestration, and idempotent operations support continuity during spikes and integration failures.',
                    'items' => [
                        ['title' => 'SLA objectives', 'body' => 'Platform targets operational visibility and synthetic checks; enterprise agreements can define program-specific SLA schedules.', 'icon' => 'fa-gauge-high'],
                        ['title' => 'Queue resilience', 'body' => 'Work queues and verification pipelines designed to absorb backlog without silent data loss.', 'icon' => 'fa-layer-group'],
                        ['title' => 'Retry orchestration', 'body' => 'Exponential backoff and ordered replay for field telemetry and webhook delivery paths.', 'icon' => 'fa-arrows-rotate'],
                        ['title' => 'Idempotent operations', 'body' => 'Write paths support idempotency locks so duplicate submissions do not double-commit finance or lifecycle state.', 'icon' => 'fa-check-double'],
                        ['title' => 'Event replay safety', 'body' => 'Event fabric designed for replayable, attributable streams — integrations can reconcile without corrupting workflow history.', 'icon' => 'fa-bolt'],
                    ],
                ],
                'infrastructure' => [
                    'id' => 'infrastructure',
                    'eyebrow' => 'Infrastructure notes',
                    'title' => 'Managed cloud with observability',
                    'sub' => 'Infrastructure patterns support secure provisioning, edge protection, and telemetry monitoring for operations teams.',
                    'items' => [
                        ['title' => 'Managed cloud', 'body' => 'Deployed on managed cloud infrastructure with operational backups and continuity planning paths.', 'icon' => 'fa-cloud'],
                        ['title' => 'Edge protection', 'body' => 'TLS termination, rate limits, and edge scrubbing patterns for public and API surfaces.', 'icon' => 'fa-shield'],
                        ['title' => 'Observability', 'body' => 'Metrics, structured logs, and event streams for executive and ops reviews.', 'icon' => 'fa-chart-line'],
                        ['title' => 'Field operations monitoring', 'body' => 'Location checkpoints and exception routing for operational visibility—not passive tracking alone.', 'icon' => 'fa-satellite-dish'],
                        ['title' => 'Secure provisioning', 'body' => 'Agency onboarding, domain edges, and SSL lifecycle orchestration with auditable provisioning steps.', 'icon' => 'fa-lock'],
                    ],
                ],
            ],
            'procurement' => [
                'id' => 'procurement',
                'eyebrow' => 'Enterprise review',
                'title' => 'Procurement & enterprise review',
                'sub' => 'Request documentation aligned to your security questionnaire, architecture review, or RFP process.',
                'ctas' => [
                    [
                        'title' => 'Request Security Brief',
                        'body' => 'Receive an architecture-oriented security overview for vendor assessment and InfoSec review.',
                        'href' => $mailto('RATIB — Request Security Brief'),
                        'icon' => 'fa-file-shield',
                        'variant' => 'primary',
                    ],
                    [
                        'title' => 'Request Architecture Review',
                        'body' => 'Schedule a technical walkthrough of tenant isolation, governance, and integration boundaries.',
                        'href' => $mailto('RATIB — Request Architecture Review'),
                        'icon' => 'fa-diagram-project',
                        'variant' => 'outline',
                    ],
                    [
                        'title' => 'Contact Enterprise Team',
                        'body' => 'Riyadh HQ · info@out.ratib.sa · enterprise program and corridor deployments.',
                        'href' => $mailto('RATIB — Enterprise Team Inquiry'),
                        'icon' => 'fa-envelope',
                        'variant' => 'ghost',
                    ],
                ],
            ],
            'contact' => [
                'email' => $enterpriseMail,
                'phone' => '+966 599 863 868',
                'whatsapp' => 'https://wa.me/966599863868',
            ],
        ];
    }
}
