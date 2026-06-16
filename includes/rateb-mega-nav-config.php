<?php
/**
 * Mega navigation structure for public home chrome (RATEB enterprise infrastructure).
 * URLs use $baseUrl and $navPrefix (home hash prefix) — resolved at render time.
 *
 * Top row: Platform · Solutions · Domains · Pricing · Partners · Company · Demo · Contact (+ Sign In).
 * Profile pages add a second row (banner + on-page jump) below this header only.
 *
 * @return list<array<string,mixed>>
 */
function rateb_mega_nav_config(): array
{
    return [
        [
            'type' => 'mega',
            'id' => 'platform',
            'label' => 'Platform',
            'panel_id' => 'rateb-mega-panel-platform',
            'columns' => [
                [
                    'heading' => 'Platform',
                    'items' => [
                        ['icon' => 'fa-diagram-project', 'title' => 'Platform overview', 'desc' => 'Workflow orchestration, tenant isolation, and agency workspaces.', 'href_key' => 'platform'],
                        ['icon' => 'fa-sitemap', 'title' => 'Architecture', 'desc' => 'Platform layers, isolation model, and deployment topology.', 'href_key' => 'architecture'],
                        ['icon' => 'fa-eye', 'title' => 'Operational proof', 'desc' => 'Field operations, SLA visibility, and execution telemetry.', 'href_key' => 'operational'],
                        ['icon' => 'fa-code', 'title' => 'APIs & integrations', 'desc' => 'Extend flows with webhooks and REST.', 'href_key' => 'api'],
                    ],
                ],
                [
                    'heading' => 'Company & trust',
                    'items' => [
                        ['icon' => 'fa-building', 'title' => 'Company profile', 'desc' => 'About RATEB, legal entity, and platform scope.', 'href_key' => 'company_profile'],
                        ['icon' => 'fa-shield-halved', 'title' => 'Security & compliance', 'desc' => 'Trust center for procurement and governance.', 'href_key' => 'security_compliance'],
                        ['icon' => 'fa-file-contract', 'title' => 'Procurement & legal', 'desc' => 'Engagement process and procurement requests.', 'href_key' => 'procurement_legal'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'mega',
            'id' => 'solutions',
            'label' => 'Solutions',
            'panel_id' => 'rateb-mega-panel-solutions',
            'columns' => [
                [
                    'heading' => 'Workforce operations',
                    'items' => [
                        ['icon' => 'fa-chart-line', 'title' => 'Workforce visibility', 'desc' => 'Corridor sync, stage clocks, and SLA visibility across programs.', 'href_key' => 'tracking'],
                        ['icon' => 'fa-handshake', 'title' => 'Provider coordination', 'desc' => 'Sending agencies, host-market programs, and partner workspaces.', 'href_key' => 'agencies'],
                        ['icon' => 'fa-qrcode', 'title' => 'QR workforce identity', 'desc' => 'Field verification and workforce identity at the edge.', 'href_key' => 'features'],
                        ['icon' => 'fa-route', 'title' => 'Operational workflows', 'desc' => 'Recruitment through deployment with audit-ready history.', 'href_key' => 'how_it_works'],
                    ],
                ],
                [
                    'heading' => 'More capabilities',
                    'items' => [
                        ['icon' => 'fa-globe', 'title' => 'Domains & edges', 'desc' => 'Per-agency domain edges and marketplace catalog.', 'href_key' => 'marketplace_domains'],
                        ['icon' => 'fa-landmark', 'title' => 'Government programs', 'desc' => 'Ministries, labor programs, and regulated corridors.', 'href_key' => 'government_workforce'],
                        ['icon' => 'fa-circle-question', 'title' => 'Help center', 'desc' => 'Support, documentation, and contact.', 'href_key' => 'help_center'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'mega',
            'id' => 'domains',
            'label' => 'Domains',
            'panel_id' => 'rateb-mega-panel-domains',
            'columns' => [
                [
                    'heading' => 'Find & manage',
                    'items' => [
                        ['icon' => 'fa-magnifying-glass', 'title' => 'Search domain names', 'desc' => 'Availability search and catalog.', 'href_key' => 'marketplace_domains'],
                        ['icon' => 'fa-right-left', 'title' => 'Transfer domains', 'desc' => 'Move domains to RATEB.', 'href_key' => 'contact'],
                        ['icon' => 'fa-layer-group', 'title' => 'Domain extensions', 'desc' => 'Explore TLD options.', 'href_key' => 'marketplace_domains'],
                        ['icon' => 'fa-id-card', 'title' => 'Availability check', 'desc' => 'Live registrar-backed lookup.', 'href_key' => 'domain_search'],
                    ],
                ],
                [
                    'heading' => 'Investing & tools',
                    'items' => [
                        ['icon' => 'fa-gem', 'title' => 'Premium domains', 'desc' => 'Curated marketplace inventory.', 'href_key' => 'marketplace_domains'],
                        ['icon' => 'fa-wand-magic-sparkles', 'title' => 'Domain generator', 'desc' => 'AI-assisted naming ideas.', 'href_key' => 'features'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'link',
            'label' => 'Pricing',
            'href_key' => 'programs',
        ],
        [
            'type' => 'link',
            'label' => 'Partners',
            'href_key' => 'agencies',
        ],
        [
            'type' => 'link',
            'label' => 'Company',
            'href_key' => 'company_profile',
        ],
        [
            'type' => 'link',
            'label' => 'Demo',
            'href_key' => 'enterprise_demo',
        ],
        [
            'type' => 'link',
            'label' => 'Contact',
            'href_key' => 'contact',
        ],
        [
            'type' => 'signin',
            'label' => 'Sign In',
        ],
    ];
}
