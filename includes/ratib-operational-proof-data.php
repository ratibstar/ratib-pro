<?php
/**
 * Operational proof — diagrams, screenshots, workflow walkthroughs (public surfaces).
 */
declare(strict_types=1);

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

        return [
            'disclaimer' => 'Screenshots, diagrams, and metrics on this page use sample operational data or illustrative interfaces. They are not live production dashboards, audited statistics, or evidence of universal government integrations.',
            'section' => [
                'eyebrow' => 'Operational proof',
                'title' => 'How teams run programs on RATIB',
                'sub' => 'Reference flows, interface examples, and separation models—structured for procurement and operations review.',
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
    }
}
