<?php
/**
 * Mega navigation structure for public home chrome (RATIB SaaS header).
 * URLs use $baseUrl and $navPrefix (home hash prefix) — resolved at render time.
 *
 * @return list<array<string,mixed>>
 */
function ratib_mega_nav_config(): array
{
    return [
        [
            'type' => 'mega',
            'id' => 'domains',
            'label' => 'Domains',
            'panel_id' => 'ratib-mega-panel-domains',
            'columns' => [
                [
                    'heading' => 'Find a domain',
                    'items' => [
                        ['icon' => 'fa-magnifying-glass', 'title' => 'Search for Domain Names', 'desc' => 'Availability search + catalog on the marketplace.', 'href_key' => 'marketplace_domains'],
                        ['icon' => 'fa-right-left', 'title' => 'Transfer Domain Names', 'desc' => 'Contact solutions to move domains to RATIB.', 'href_key' => 'contact'],
                        ['icon' => 'fa-layer-group', 'title' => 'Domain Extensions', 'desc' => 'Explore TLD options from the marketplace.', 'href_key' => 'marketplace_domains'],
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
            'id' => 'websites',
            'label' => 'Websites',
            'panel_id' => 'ratib-mega-panel-websites',
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
                    'heading' => 'Grow',
                    'items' => [
                        ['icon' => 'fa-bullseye', 'title' => 'Landing Pages', 'desc' => 'High-conversion campaign destinations.', 'href_key' => 'solutions'],
                        ['icon' => 'fa-store', 'title' => 'Online Store', 'desc' => 'Catalog, checkout, and fulfillment hooks.', 'href_key' => 'programs'],
                        ['icon' => 'fa-wordpress', 'title' => 'CMS Hosting', 'desc' => 'Managed performance for WordPress and headless.', 'href_key' => 'operational'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'link',
            'label' => 'Email',
            'href_key' => 'contact',
            'desc' => 'Professional mailboxes and routing',
        ],
        [
            'type' => 'mega',
            'id' => 'hosting',
            'label' => 'Hosting',
            'panel_id' => 'ratib-mega-panel-hosting',
            'columns' => [
                [
                    'heading' => 'Hosting products',
                    'items' => [
                        ['icon' => 'fa-cloud', 'title' => 'Shared Hosting', 'desc' => 'Cost-effective sites on resilient clusters.', 'href_key' => 'platform'],
                        ['icon' => 'fa-server', 'title' => 'VPS Hosting', 'desc' => 'Isolated compute with root access.', 'href_key' => 'operational'],
                        ['icon' => 'fa-bolt', 'title' => 'Cloud Hosting', 'desc' => 'Scale-out apps with autoscaling patterns.', 'href_key' => 'api'],
                    ],
                ],
                [
                    'heading' => 'Power & scale',
                    'items' => [
                        ['icon' => 'fa-microchip', 'title' => 'Dedicated Servers', 'desc' => 'Single-tenant hardware for compliance.', 'href_key' => 'operational'],
                        ['icon' => 'fa-wordpress', 'title' => 'WordPress Hosting', 'desc' => 'Optimized stack and staging workflows.', 'href_key' => 'features'],
                        ['icon' => 'fa-handshake', 'title' => 'Reseller Hosting', 'desc' => 'White-label for agencies and partners.', 'href_key' => 'agencies'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'link',
            'label' => 'Marketing',
            'href_key' => 'solutions',
            'desc' => 'Campaigns, attribution, and growth',
        ],
        [
            'type' => 'mega',
            'id' => 'security',
            'label' => 'Security',
            'panel_id' => 'ratib-mega-panel-security',
            'columns' => [
                [
                    'heading' => 'Protect',
                    'items' => [
                        ['icon' => 'fa-lock', 'title' => 'SSL Certificates', 'desc' => 'Automated issuance and renewal.', 'href_key' => 'api'],
                        ['icon' => 'fa-shield-halved', 'title' => 'DDoS Protection', 'desc' => 'Edge scrubbing and rate intelligence.', 'href_key' => 'operational'],
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
            'label' => 'AI Builder',
            'href_key' => 'features',
            'desc' => 'Prompt-to-page and workflow copilots',
        ],
        [
            'type' => 'link',
            'label' => 'Pricing',
            'href_key' => 'register',
            'desc' => 'Plans and transparent billing',
        ],
        [
            'type' => 'link',
            'label' => 'Contact Us',
            'href_key' => 'contact',
            'desc' => 'Talk to solutions engineering',
        ],
        [
            'type' => 'link',
            'label' => 'Help',
            'href_key' => 'api',
            'desc' => 'Docs, status, and support channels',
        ],
        [
            'type' => 'signin',
            'label' => 'Sign In',
        ],
    ];
}
