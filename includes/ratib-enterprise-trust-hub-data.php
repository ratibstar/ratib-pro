<?php
/**
 * Enterprise Trust Hub — seven procurement pillars for /enterprise-trust/
 */
declare(strict_types=1);

require_once __DIR__ . '/ratib-public-cms.php';

if (!function_exists('ratib_enterprise_trust_hub_config')) {
    /**
     * @return array<string, mixed>
     */
    function ratib_enterprise_trust_hub_config(string $baseUrl): array
    {
        $root = rtrim($baseUrl, '/');

        return [
            'meta' => [
                'title' => 'Enterprise Trust Center — RATEB',
                'description' => 'Procurement-grade trust posture for RATEB workforce program infrastructure: security, compliance, reliability, auditability, APIs, and deployment models.',
            ],
            'hero' => [
                'eyebrow' => 'Enterprise trust center',
                'title' => 'Procurement-grade infrastructure posture',
                'lead' => 'Security overview, governance, reliability, auditability, integration standards, and deployment models—documented for ministries, labor programs, and enterprise procurement reviewers.',
            ],
            'pillars' => [
                [
                    'id' => 'security-overview',
                    'icon' => 'fa-shield-halved',
                    'title' => 'Security overview',
                    'lead' => 'Defense-in-depth for regulated workforce corridors.',
                    'points' => [
                        'TLS 1.3 to the edge; hardened session cookies and revocation paths',
                        'RBAC with branch, country, and program-scoped operator matrices',
                        'Webhook integrity verification and signed operational callbacks where configured',
                        'Device-aware authentication options including WebAuthn for privileged roles',
                    ],
                    'href' => $root . '/security-compliance/',
                    'href_label' => 'Security & compliance center →',
                ],
                [
                    'id' => 'compliance-governance',
                    'icon' => 'fa-scale-balanced',
                    'title' => 'Compliance & governance',
                    'lead' => 'Policy enforcement before commits—not after exports.',
                    'points' => [
                        'Stage graphs with policy version stamped on each transition',
                        'Embassy, medical, and police bundles as first-class artifacts',
                        'Blacklist and deploy-block rules aligned to program policy',
                        'Reviewer attribution on sensitive approvals',
                    ],
                    'href' => $root . '/procurement-legal/',
                    'href_label' => 'Procurement & legal →',
                ],
                [
                    'id' => 'infrastructure-reliability',
                    'icon' => 'fa-server',
                    'title' => 'Infrastructure & reliability',
                    'lead' => 'Operational resilience for multi-agency programs.',
                    'points' => [
                        'Tenant-isolated databases per agency program where configured',
                        'Backup posture and restore drills documented for enterprise questionnaires',
                        'Synthetic SLA checks with breach watches on stage clocks',
                        'Multi-region expansion paths when procurement requires geographic redundancy',
                    ],
                    'href' => $root . '/architecture/',
                    'href_label' => 'Platform architecture →',
                ],
                [
                    'id' => 'auditability',
                    'icon' => 'fa-clipboard-list',
                    'title' => 'Auditability',
                    'lead' => 'Immutable workflow history for program reviews.',
                    'points' => [
                        'Append-only stage transitions with actor and correlation identifiers',
                        'Replay-safe event processing for finance-linked operations',
                        'Structured operational logs for oversight and internal audit',
                        'Export paths designed for questionnaire responses—not ad-hoc spreadsheets',
                    ],
                    'href' => $root . '/security-compliance/#compliance-governance',
                    'href_label' => 'Governance deep dive →',
                ],
                [
                    'id' => 'operational-continuity',
                    'icon' => 'fa-arrows-rotate',
                    'title' => 'Operational continuity',
                    'lead' => 'Continuity planning for sending-country scale.',
                    'points' => [
                        'Escalation routes before SLA commitments slip',
                        'Operational signaling for exceptions and workforce telemetry alerts',
                        'SSE realtime channels for operator consoles where enabled',
                        'Runbook-oriented recovery for corridor-specific incidents',
                    ],
                    'href' => $root . '/enterprise-trust/#operational-continuity',
                    'href_label' => 'Continuity practices →',
                ],
                [
                    'id' => 'api-integrations',
                    'icon' => 'fa-plug',
                    'title' => 'API & integration standards',
                    'lead' => 'Finance-grade integration boundaries.',
                    'points' => [
                        'Idempotent write patterns for payment and registration hooks',
                        'Rate limits and gateway controls on public integration edges',
                        'Telemetry integrity checks on geospatial workforce signals',
                        'Partner and agency workspace APIs scoped to tenant context',
                    ],
                    'href' => $root . '/enterprise-pack/?pack=api',
                    'href_label' => 'API overview pack →',
                ],
                [
                    'id' => 'deployment-model',
                    'icon' => 'fa-diagram-project',
                    'title' => 'Enterprise deployment model',
                    'lead' => 'How programs go live on RATEB infrastructure.',
                    'points' => [
                        'Sandbox → production promotion with RBAC parity',
                        'Per-agency branded domains and corridor configuration',
                        'Government and commercial execution modes on one control plane',
                        'Dedicated onboarding playbooks for large sending-country agencies',
                    ],
                    'href' => $root . '/profile/#company-profile',
                    'href_label' => 'Company profile →',
                ],
            ],
            'cta' => [
                ['label' => 'Request Security Brief', 'subject' => 'RATEB — Request Security Brief'],
                ['label' => 'Request Architecture Review', 'subject' => 'RATEB — Request Architecture Review'],
                ['label' => 'Enterprise document packs', 'href' => $root . '/enterprise-pack/'],
            ],
        ];
    }
}
