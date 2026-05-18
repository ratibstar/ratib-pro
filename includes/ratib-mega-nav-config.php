<?php
/**
 * Mega navigation structure for public home chrome (RATIB SaaS header).
 * URLs use $baseUrl and $navPrefix (home hash prefix) — resolved at render time.
 *
 * Top row is intentionally short: related product lines are merged (e.g. Sites = web + hosting + mail;
 * Grow = marketing + AI that used to be separate links).
 *
 * @return list<array<string,mixed>>
 */
function ratib_mega_nav_config(): array
{
    return [
        [
            'type' => 'mega',
            'id' => 'company',
            'label' => 'Company',
            'panel_id' => 'ratib-mega-panel-company',
            'columns' => [
                [
                    'heading' => 'Ratib Software Foundation for Information Technology',
                    'items' => [
                        ['icon' => 'fa-building', 'title' => 'Company profile', 'desc' => 'About RATIB, legal entity, markets, and platform scope.', 'href_key' => 'company_profile'],
                        ['icon' => 'fa-shield-halved', 'title' => 'Security & compliance', 'desc' => 'Trust center for procurement, isolation, governance, and reliability.', 'href_key' => 'security_compliance'],
                        ['icon' => 'fa-sitemap', 'title' => 'Platform architecture', 'desc' => 'Platform layers, isolation model, and deployment topology.', 'href_key' => 'architecture'],
                        ['icon' => 'fa-file-contract', 'title' => 'Procurement & legal', 'desc' => 'Company identity, engagement process, and procurement requests.', 'href_key' => 'procurement_legal'],
                        ['icon' => 'fa-diagram-project', 'title' => 'Platform overview', 'desc' => 'Workflow platform, field-operations support, and agency workspace modules.', 'href_key' => 'platform'],
                        ['icon' => 'fa-handshake', 'title' => 'Partners & agencies', 'desc' => 'Sending-country agencies and host-market programs.', 'href_key' => 'agencies'],
                        ['icon' => 'fa-envelope', 'title' => 'Contact leadership', 'desc' => 'Riyadh HQ and program inquiries.', 'href_key' => 'contact'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'mega',
            'id' => 'domains',
            'label' => 'Domains',
            'panel_id' => 'ratib-mega-panel-domains',
            'columns' => [
                [
                    'heading' => 'Find a domain',
                    'items' => [
                        ['icon' => 'fa-magnifying-glass', 'title' => 'Search for Domain Names', 'desc' => 'Availability search + catalog in this page flow.', 'href_key' => 'marketplace_domains'],
                        ['icon' => 'fa-right-left', 'title' => 'Transfer Domain Names', 'desc' => 'Contact solutions to move domains to RATIB.', 'href_key' => 'contact'],
                        ['icon' => 'fa-layer-group', 'title' => 'Domain Extensions', 'desc' => 'Explore TLD options in this page flow.', 'href_key' => 'marketplace_domains'],
                    ],
                ],
                [
                    'heading' => 'Domain investing',
                    'items' => [
                        ['icon' => 'fa-gavel', 'title' => 'Domain Auctions', 'desc' => 'Acquire premium names from the aftermarket.', 'href_key' => 'programs'],
                        ['icon' => 'fa-chart-line', 'title' => 'Appraise Domain Value', 'desc' => 'Estimate resale and portfolio value.', 'href_key' => 'features'],
                        ['icon' => 'fa-gem', 'title' => 'Premium Domains', 'desc' => 'Curated inventory via marketplace catalog.', 'href_key' => 'marketplace_domains'],
                    ],
                ],
                [
                    'heading' => 'Domain tools',
                    'items' => [
                        ['icon' => 'fa-id-card', 'title' => 'Check availability', 'desc' => 'Live registrar-backed lookup (providers must be active).', 'href_key' => 'domain_search'],
                        ['icon' => 'fa-wand-magic-sparkles', 'title' => 'Domain Generator', 'desc' => 'AI-assisted naming ideas.', 'href_key' => 'features'],
                        ['icon' => 'fa-table-list', 'title' => 'Multi-TLD check', 'desc' => 'Search keyword; results include com / net / org.', 'href_key' => 'domain_search'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'mega',
            'id' => 'sites',
            'label' => 'Sites',
            'panel_id' => 'ratib-mega-panel-sites',
            'columns' => [
                [
                    'heading' => 'Build',
                    'items' => [
                        ['icon' => 'fa-pen-ruler', 'title' => 'Website Builder', 'desc' => 'Drag-and-drop layouts with RATIB styling.', 'href_key' => 'features'],
                        ['icon' => 'fa-robot', 'title' => 'AI Website Builder', 'desc' => 'Generate pages and copy from prompts.', 'href_key' => 'features'],
                        ['icon' => 'fa-border-all', 'title' => 'Templates', 'desc' => 'Industry starter kits and sections.', 'href_key' => 'program_previews'],
                    ],
                ],
                [
                    'heading' => 'Host',
                    'items' => [
                        ['icon' => 'fa-cloud', 'title' => 'Shared Hosting', 'desc' => 'Cost-effective sites on resilient clusters.', 'href_key' => 'platform'],
                        ['icon' => 'fa-server', 'title' => 'VPS Hosting', 'desc' => 'Isolated compute with root access.', 'href_key' => 'operational'],
                        ['icon' => 'fa-bolt', 'title' => 'Cloud Hosting', 'desc' => 'Scale-out apps with autoscaling patterns.', 'href_key' => 'api'],
                    ],
                ],
                [
                    'heading' => 'Dedicated & mail',
                    'items' => [
                        ['icon' => 'fa-microchip', 'title' => 'Dedicated Servers', 'desc' => 'Single-tenant hardware for compliance.', 'href_key' => 'operational'],
                        ['icon' => 'fa-wordpress', 'title' => 'WordPress Hosting', 'desc' => 'Optimized stack and staging workflows.', 'href_key' => 'features'],
                        ['icon' => 'fa-envelope', 'title' => 'Professional Email', 'desc' => 'Mailboxes and routing — same contact section.', 'href_key' => 'contact'],
                    ],
                ],
                [
                    'heading' => 'Scale & partners',
                    'items' => [
                        ['icon' => 'fa-handshake', 'title' => 'Reseller Hosting', 'desc' => 'White-label for agencies and partners.', 'href_key' => 'agencies'],
                        ['icon' => 'fa-store', 'title' => 'Online Store', 'desc' => 'Catalog, checkout, and fulfillment hooks.', 'href_key' => 'programs'],
                        ['icon' => 'fa-diagram-project', 'title' => 'CMS Hosting', 'desc' => 'Managed performance for WordPress and headless.', 'href_key' => 'operational'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'mega',
            'id' => 'grow',
            'label' => 'Grow',
            'panel_id' => 'ratib-mega-panel-grow',
            'columns' => [
                [
                    'heading' => 'Marketing',
                    'items' => [
                        ['icon' => 'fa-bullseye', 'title' => 'Landing Pages', 'desc' => 'High-conversion campaign destinations.', 'href_key' => 'solutions'],
                        ['icon' => 'fa-chart-line', 'title' => 'Campaigns & growth', 'desc' => 'Attribution and demand programs.', 'href_key' => 'solutions'],
                        ['icon' => 'fa-users', 'title' => 'Partner programs', 'desc' => 'Agency and host-market ecosystems.', 'href_key' => 'agencies'],
                        ['icon' => 'fa-file-contract', 'title' => 'Plans & signup', 'desc' => 'Compare tiers and registration — same destination as the Pricing pill.', 'href_key' => 'register'],
                    ],
                ],
                [
                    'heading' => 'AI & automation',
                    'items' => [
                        ['icon' => 'fa-wand-magic-sparkles', 'title' => 'AI Builder', 'desc' => 'Prompt-to-page and workflow copilots.', 'href_key' => 'features'],
                        ['icon' => 'fa-comments', 'title' => 'Assistants & chat', 'desc' => 'Embedded help and routing.', 'href_key' => 'features'],
                        ['icon' => 'fa-code', 'title' => 'APIs & integrations', 'desc' => 'Extend flows with webhooks and REST.', 'href_key' => 'api'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'mega',
            'id' => 'security',
            'label' => 'Security',
            'panel_id' => 'ratib-mega-panel-security',
            'columns' => [
                [
                    'heading' => 'Trust & protect',
                    'items' => [
                        ['icon' => 'fa-file-shield', 'title' => 'Security & compliance center', 'desc' => 'Procurement-ready posture: isolation, governance, and reliability.', 'href_key' => 'security_compliance'],
                        ['icon' => 'fa-lock', 'title' => 'SSL Certificates', 'desc' => 'Automated issuance and renewal.', 'href_key' => 'api'],
                        ['icon' => 'fa-shield-halved', 'title' => 'DDoS Protection', 'desc' => 'Edge scrubbing and rate limiting.', 'href_key' => 'operational'],
                        ['icon' => 'fa-fire-flame-curved', 'title' => 'WAF', 'desc' => 'OWASP-aware rules and bot mitigation.', 'href_key' => 'tracking'],
                    ],
                ],
                [
                    'heading' => 'Resilience',
                    'items' => [
                        ['icon' => 'fa-bug', 'title' => 'Malware Scanner', 'desc' => 'Scheduled scans and quarantine.', 'href_key' => 'tracking'],
                        ['icon' => 'fa-database', 'title' => 'Backup Services', 'desc' => 'Immutable snapshots and restore drills.', 'href_key' => 'operational'],
                        ['icon' => 'fa-eye', 'title' => 'Security Monitoring', 'desc' => '24/7 signals with escalation paths.', 'href_key' => 'tracking'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'link',
            'label' => 'Help',
            'href_key' => 'help_center',
            'desc' => 'Docs, status, and support channels',
        ],
        [
            'type' => 'signin',
            'label' => 'Sign In',
        ],
    ];
}
