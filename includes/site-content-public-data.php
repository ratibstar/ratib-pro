<?php
/**
 * All public marketing pages — CMS defaults + editor groups (Public site content).
 * Pages: / · /profile · /architecture · /security-compliance · /procurement-legal
 */
declare(strict_types=1);

require_once __DIR__ . '/site-content-profile-data.php';

if (!function_exists('ratib_site_content_public_page_links')) {
    /**
     * @return list<array{label:string,path:string}>
     */
    function ratib_site_content_public_page_links(): array
    {
        $root = '';
        if (function_exists('ratib_public_site_base_url')) {
            $root = rtrim(ratib_public_site_base_url(), '/');
        }

        return [
            ['label' => 'Marketing home', 'path' => $root . '/'],
            ['label' => 'Company profile', 'path' => $root . '/profile/'],
            ['label' => 'Architecture', 'path' => $root . '/architecture/'],
            ['label' => 'Security & compliance', 'path' => $root . '/security-compliance/'],
            ['label' => 'Procurement & legal', 'path' => $root . '/procurement-legal/'],
        ];
    }
}

if (!function_exists('ratib_site_content_defaults_public_pages')) {
    /**
     * Keys for public pages other than home.* (home stays in site-content-home-data.php).
     *
     * @return array<string, string>
     */
    function ratib_site_content_defaults_public_pages(): array
    {
        return [
            'public.contact.phone' => '+966 599 863 868',
            'public.contact.email' => 'info@out.ratib.sa',
            'public.contact.whatsapp' => 'https://wa.me/966599863868',
            'public.contact.website' => 'https://out.ratib.sa',

            'profile.meta.title' => 'RATIB — Company profile',
            'profile.meta.description' => 'Legal identity, platform scope, corridors, and operational capabilities of Ratib Software Foundation for Information Technology.',
            'profile.company.founded' => '2018',
            'profile.company.hq' => 'Riyadh, Kingdom of Saudi Arabia',
            'profile.company.address' => 'Riyadh, Saudi Arabia',
            'profile.company.industry' => 'Workforce program software · Cross-border recruitment operations',
            'profile.company.employees_band' => '51–200 (operations & engineering)',
            'profile.company.cr_value' => 'On file · available under NDA for enterprise procurement',
            'profile.company.vat_value' => 'Available on invoice / registration documents',
            'profile.company.services' => implode("\n", [
                'Workforce lifecycle workflows',
                'Agency operations workspace (multi-level agents)',
                'Worker records & document handling',
                'Field location support & SLA visibility',
                'RBAC, audit history, and labor-oversight modules',
                'Ledger, AR/AP, and payment checkout',
                'Partner & host-market portals',
                'Domain, SSL, and branded agency sites',
            ]),
            'profile.platform.eyebrow' => 'Platform overview',
            'profile.platform.title' => 'RATIB operations platform',
            'profile.platform.lead' => 'How sending-country agencies and host-market programs coordinate recruitment, field operations, compliance, and finance in one workspace.',
            'profile.what.title' => 'What RATIB is — and what it is not',
            'profile.what.sub' => 'RATIB is workforce program infrastructure for regulated, high-volume international recruitment—not a lightweight CRM.',
            'profile.what.not_crm' => implode("\n", [
                'A lightweight CRM with a basic map plugin',
                'Spreadsheet replacement with a login screen',
                'One shared database for every agency',
                'Marketing site with static dashboard images only',
            ]),
            'profile.what.is_infra' => implode("\n", [
                'Workforce program workflow platform',
                'Multi-country agency operations workspace',
                'Field-operations support with location checkpoints',
                'Policy, audit history, and oversight-oriented modules',
                'Finance module linked to placements and fees',
            ]),
            'profile.opproof.eyebrow' => 'Operational proof',
            'profile.opproof.title' => 'How teams run programs on RATIB',
            'profile.opproof.sub' => 'Government oversight, field tracking, and mobile mobilization—then agency workspace, diagrams, and reference flows for procurement review.',
            'profile.opproof.disclaimer' => 'Screenshots, diagrams, and metrics on this page use sample operational data or illustrative interfaces. They are not live production dashboards, audited statistics, or evidence of universal government integrations.',
            'profile.section.arch.eyebrow' => 'Platform architecture',
            'profile.section.arch.title' => 'Platform layers',
            'profile.section.arch.sub' => 'Seven layers—from user-facing pages to separate agency databases.',
            'profile.section.ops.eyebrow' => 'Agency workspace',
            'profile.section.ops.title' => 'Agency workspace',
            'profile.section.ops.sub' => 'Day-to-day screens for agents, workforce records, finance, HR, and partner coordination.',
            'profile.section.tel.eyebrow' => 'Field operations',
            'profile.section.tel.title' => 'Field operations support',
            'profile.section.tel.sub' => 'Location-assisted workforce coordination with offline sync, geofence rules, and audit-friendly event history—not a standalone tracking app.',
            'profile.section.gov.eyebrow' => 'Policy & oversight',
            'profile.section.gov.title' => 'Compliance & governance',
            'profile.section.gov.sub' => 'Recorded workflow history, labor-oversight tools, and separate agency data for regulated programs.',
            'profile.section.fin.eyebrow' => 'Controllers & CFOs',
            'profile.section.fin.title' => 'Operational finance infrastructure',
            'profile.section.fin.sub' => 'Integrated ledger and invoicing—linked to workers and placements, not a bolt-on spreadsheet.',
            'profile.section.cor.eyebrow' => 'Multi-corridor fabric',
            'profile.section.cor.title' => 'Multi-country operations',
            'profile.section.cor.sub' => 'Worker-sending markets on one orchestration core—with country-scoped governance and expanding corridors.',
            'profile.image.hero' => 'public/profile-media/about-ratib-command.png',
            'profile.image.ops' => 'public/profile-media/about-ratib-command.png',
            'profile.image.workers' => 'public/profile-media/program-preview-workers.svg',
            'profile.image.telemetry' => 'public/profile-media/government/tracking-map.png',
            'profile.image.accounting' => 'public/profile-media/program-preview-finance.svg',
            'profile.image.control' => 'public/profile-media/government/government-control.png',
            'profile.image.partners' => 'public/profile-media/about-ratib-command.png',
            'profile.image.pipeline' => 'public/profile-media/program-preview-pipeline.svg',
            'profile.image.hero.alt' => 'RATIB agency workspace — workforce records and SLA views',
            'profile.image.ops.alt' => 'Agency operations workspace with workforce and agent views',
            'profile.image.workers.alt' => 'Worker records — lifecycle and documents',
            'profile.image.telemetry.alt' => 'Operations map — field checkpoints and corridor context',
            'profile.image.accounting.alt' => 'Accounting and invoicing module',
            'profile.image.control.alt' => 'Administration — multi-country agency settings',
            'profile.image.partners.alt' => 'Partner portal — deployments and documents',
            'profile.image.pipeline.alt' => 'Workflow pipeline across recruitment stages',
            'opproof.image.pipeline' => '',
            'opproof.image.ops' => '',
            'opproof.image.finance' => '',
            'opproof.image.audit' => '',
            'opproof.image.map' => '',
            'opproof.image.partner' => '',

            'arch.meta.title' => 'Platform Architecture — RATIB',
            'arch.meta.description' => 'Layered workforce program platform: shared core, tenant isolation, event delivery, field operations, finance module, and deployment topology for enterprise technical review.',
            'arch.hero.eyebrow' => 'Platform architecture',
            'arch.hero.title' => 'Multi-agency workforce program platform',
            'arch.hero.lead' => 'RATIB is organized in platform layers that connect agency workspaces, partner portals, and regulated corridors—with separate agency databases, event-driven workflows, and policy controls in the stack.',
            'arch.overview.eyebrow' => 'Architecture overview',
            'arch.overview.title' => 'Orchestration infrastructure, not a single application',
            'arch.overview.sub' => 'Programs, corridors, and agencies share a common workflow core while program data stays in separate agency databases. The platform separates user interfaces, workflow execution, field operations, finance, and storage.',

            'trust.meta.title' => 'Security & Compliance — RATIB Trust Center',
            'trust.meta.description' => 'RATIB security architecture, compliance governance, tenant isolation, and operational reliability — designed for regulated workforce program infrastructure and enterprise procurement review.',
            'trust.hero.eyebrow' => 'Trust center',
            'trust.hero.title' => 'Security & compliance for regulated workforce operations',
            'trust.hero.lead' => 'RATIB is enterprise workforce program infrastructure designed for agencies and government-aligned programs that require auditable operations, tenant isolation, and procurement-ready governance — without overstating certifications.',
            'trust.disclaimer' => 'This trust center describes platform architecture and operational design. RATIB does not claim third-party certifications (such as SOC 2 or ISO) unless separately documented in a signed enterprise agreement.',

            'proc.meta.title' => 'Procurement & Legal — RATIB Enterprise',
            'proc.meta.description' => 'Legal identity, enterprise engagement process, security governance references, tenant boundaries, and procurement contact for government buyers, enterprise procurement, and international partners.',
            'proc.hero.eyebrow' => 'Procurement & legal',
            'proc.hero.title' => 'Enterprise procurement and compliance review',
            'proc.hero.lead' => 'Formal reference for government buyers, enterprise procurement teams, international partners, and compliance reviewers evaluating RATIB as workforce program orchestration infrastructure.',
            'proc.hero.notice' => 'This page states verifiable company and platform facts. RATIB does not claim government partnerships, regulatory licenses, or third-party certifications unless separately documented in a signed agreement.',
            'proc.identity.legal_name' => 'Ratib Software Foundation for Information Technology',
            'proc.identity.trade_name' => 'RATIB',
            'proc.identity.hq' => 'Riyadh, Kingdom of Saudi Arabia',
            'proc.identity.cr' => 'On file — available to enterprise procurement under NDA upon request',
            'proc.identity.vat' => 'Available on invoice or upon formal request during procurement',
        ];
    }
}

if (!function_exists('ratib_site_content_public_editor_groups')) {
    /**
     * Full Public site content editor — all public pages, homepage last.
     *
     * @return list<array<string, mixed>>
     */
    function ratib_site_content_public_editor_groups(): array
    {
        $links = ratib_site_content_public_page_links();
        $mapHtml = '<ul class="mb-0 ps-3">';
        foreach ($links as $l) {
            $mapHtml .= '<li><a href="' . htmlspecialchars($l['path'], ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($l['label'], ENT_QUOTES, 'UTF-8') . '</a></li>';
        }
        $mapHtml .= '</ul>';

        $mapGroup = [
            [
                'id' => 'public-site-map',
                'title' => 'Public site — pages you can edit here',
                'intro' => '<strong>One editor for the entire public site.</strong> Marketing home, company profile, architecture, security, and procurement pages all read from these keys. After saving, hard-refresh the live page (or wait for CDN). ' . $mapHtml,
                'fields' => [],
            ],
        ];

        $profileGroups = function_exists('ratib_site_content_profile_editor_groups')
            ? ratib_site_content_profile_editor_groups()
            : [];

        $publicOnly = [
            [
                'id' => 'public-shared-contact',
                'title' => 'Shared contact (profile & procurement)',
                'intro' => 'Used on company profile and procurement pages. <strong>Homepage top bar phone</strong> is separate: <em>Homepage · Top bar</em> → <code>home.topbar.phone_display</code>. Procurement legal name falls back to <code>profile.company.legal_name</code> when empty.',
                'fields' => [
                    ['key' => 'public.contact.phone', 'label' => 'Phone (display)', 'type' => 'text'],
                    ['key' => 'public.contact.email', 'label' => 'Email', 'type' => 'text'],
                    ['key' => 'public.contact.whatsapp', 'label' => 'WhatsApp URL', 'type' => 'text'],
                    ['key' => 'public.contact.website', 'label' => 'Website URL', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'profile-screenshots',
                'title' => 'Company profile (/profile) — workspace screenshots',
                'intro' => 'Upload PNG/WebP for each block. Defaults use <code>about-ratib-command.png</code> until you upload replacements.',
                'fields' => [
                    ['key' => 'profile.image.hero', 'label' => 'Platform overview image', 'type' => 'media_image'],
                    ['key' => 'profile.image.hero.alt', 'label' => 'Alt · platform overview', 'type' => 'text'],
                    ['key' => 'profile.image.ops', 'label' => 'Operations section image', 'type' => 'media_image'],
                    ['key' => 'profile.image.ops.alt', 'label' => 'Alt · operations', 'type' => 'text'],
                    ['key' => 'profile.image.workers', 'label' => 'Workers row image', 'type' => 'media_image'],
                    ['key' => 'profile.image.workers.alt', 'label' => 'Alt · workers', 'type' => 'text'],
                    ['key' => 'profile.image.pipeline', 'label' => 'Pipeline row image', 'type' => 'media_image'],
                    ['key' => 'profile.image.pipeline.alt', 'label' => 'Alt · pipeline', 'type' => 'text'],
                    ['key' => 'profile.image.control', 'label' => 'Control row image', 'type' => 'media_image'],
                    ['key' => 'profile.image.control.alt', 'label' => 'Alt · control', 'type' => 'text'],
                    ['key' => 'profile.image.telemetry', 'label' => 'Telemetry section image', 'type' => 'media_image'],
                    ['key' => 'profile.image.telemetry.alt', 'label' => 'Alt · telemetry', 'type' => 'text'],
                    ['key' => 'profile.image.accounting', 'label' => 'Finance section image', 'type' => 'media_image'],
                    ['key' => 'profile.image.accounting.alt', 'label' => 'Alt · finance', 'type' => 'text'],
                    ['key' => 'profile.image.partners', 'label' => 'Partners image (if used)', 'type' => 'media_image'],
                    ['key' => 'profile.image.partners.alt', 'label' => 'Alt · partners', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'opproof-platform-screens',
                'title' => 'Operational proof — platform screenshots (below government)',
                'intro' => 'Optional overrides for the six cards below government on /profile. <strong>Leave empty</strong> to reuse the distinct workspace screenshots above. Clear any field that still shows the old generic <code>about-ratib-command.png</code> for every card.',
                'fields' => [
                    ['key' => 'opproof.image.pipeline', 'label' => 'Workforce pipeline', 'type' => 'media_image'],
                    ['key' => 'opproof.image.ops', 'label' => 'Operations dashboard', 'type' => 'media_image'],
                    ['key' => 'opproof.image.finance', 'label' => 'Finance ledger', 'type' => 'media_image'],
                    ['key' => 'opproof.image.audit', 'label' => 'Audit history', 'type' => 'media_image'],
                    ['key' => 'opproof.image.map', 'label' => 'Field operations map', 'type' => 'media_image'],
                    ['key' => 'opproof.image.partner', 'label' => 'Partner portal', 'type' => 'media_image'],
                ],
            ],
            [
                'id' => 'architecture-page',
                'title' => 'Architecture page (/architecture)',
                'intro' => 'Hero and overview copy. Layer detail cards remain structured in code; contact us if you need more fields added.',
                'fields' => [
                    ['key' => 'arch.meta.title', 'label' => 'Browser title', 'type' => 'text'],
                    ['key' => 'arch.meta.description', 'label' => 'Meta description', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'arch.hero.eyebrow', 'label' => 'Hero · eyebrow', 'type' => 'text'],
                    ['key' => 'arch.hero.title', 'label' => 'Hero · title', 'type' => 'text'],
                    ['key' => 'arch.hero.lead', 'label' => 'Hero · lead', 'type' => 'textarea', 'rows' => 3],
                    ['key' => 'arch.overview.eyebrow', 'label' => 'Overview · eyebrow', 'type' => 'text'],
                    ['key' => 'arch.overview.title', 'label' => 'Overview · title', 'type' => 'text'],
                    ['key' => 'arch.overview.sub', 'label' => 'Overview · subtitle', 'type' => 'textarea', 'rows' => 3],
                ],
            ],
            [
                'id' => 'trust-page',
                'title' => 'Security & compliance (/security-compliance)',
                'fields' => [
                    ['key' => 'trust.meta.title', 'label' => 'Browser title', 'type' => 'text'],
                    ['key' => 'trust.meta.description', 'label' => 'Meta description', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'trust.hero.eyebrow', 'label' => 'Hero · eyebrow', 'type' => 'text'],
                    ['key' => 'trust.hero.title', 'label' => 'Hero · title', 'type' => 'text'],
                    ['key' => 'trust.hero.lead', 'label' => 'Hero · lead', 'type' => 'textarea', 'rows' => 3],
                    ['key' => 'trust.disclaimer', 'label' => 'Trust disclaimer', 'type' => 'textarea', 'rows' => 3],
                ],
            ],
            [
                'id' => 'procurement-page',
                'title' => 'Procurement & legal (/procurement-legal)',
                'fields' => [
                    ['key' => 'proc.meta.title', 'label' => 'Browser title', 'type' => 'text'],
                    ['key' => 'proc.meta.description', 'label' => 'Meta description', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'proc.hero.eyebrow', 'label' => 'Hero · eyebrow', 'type' => 'text'],
                    ['key' => 'proc.hero.title', 'label' => 'Hero · title', 'type' => 'text'],
                    ['key' => 'proc.hero.lead', 'label' => 'Hero · lead', 'type' => 'textarea', 'rows' => 3],
                    ['key' => 'proc.hero.notice', 'label' => 'Hero · notice', 'type' => 'textarea', 'rows' => 3],
                    ['key' => 'proc.identity.legal_name', 'label' => 'Legal company name', 'type' => 'text'],
                    ['key' => 'proc.identity.trade_name', 'label' => 'Trade name', 'type' => 'text'],
                    ['key' => 'proc.identity.hq', 'label' => 'Headquarters', 'type' => 'text'],
                    ['key' => 'proc.identity.cr', 'label' => 'Commercial registration text', 'type' => 'text'],
                    ['key' => 'proc.identity.vat', 'label' => 'VAT text', 'type' => 'text'],
                ],
            ],
        ];

        $homeGroups = [];
        if (function_exists('ratib_site_content_home_editor_groups_core')) {
            $homeGroups = ratib_site_content_home_editor_groups_core();
        }
        foreach ($homeGroups as &$hg) {
            $t = (string) ($hg['title'] ?? '');
            if ($t !== '' && !str_starts_with($t, 'Homepage ·')) {
                $hg['title'] = 'Homepage · ' . $t;
            }
        }
        unset($hg);

        return array_merge($mapGroup, $profileGroups, $publicOnly, $homeGroups);
    }
}
