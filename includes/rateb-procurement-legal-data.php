<?php
/**
 * Procurement & legal enterprise page — content for pages/procurement-legal.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/rateb-public-cms.php';

if (!function_exists('rateb_procurement_legal_config')) {
    /**
     * @return array<string, mixed>
     */
    function rateb_procurement_legal_config(string $baseUrl): array
    {
        $enterpriseMail = 'info@rateb.sa';
        $mailto = static function (string $subject) use ($enterpriseMail): string {
            return 'mailto:' . $enterpriseMail . '?subject=' . rawurlencode($subject);
        };
        $root = rtrim($baseUrl, '/');

        return [
            'meta' => [
                'title' => rateb_public_cms('proc.meta.title', 'Procurement & Legal — RATEB Enterprise'),
                'description' => rateb_public_cms('proc.meta.description', 'Legal identity, enterprise engagement process, security governance references, tenant boundaries, and procurement contact for government buyers, enterprise procurement, and international partners.'),
            ],
            'hero' => [
                'eyebrow' => rateb_public_cms('proc.hero.eyebrow', 'Procurement & legal'),
                'title' => rateb_public_cms('proc.hero.title', 'Enterprise procurement and compliance review'),
                'lead' => rateb_public_cms('proc.hero.lead', 'Formal reference for government buyers, enterprise procurement teams, international partners, and compliance reviewers evaluating RATEB as workforce program orchestration infrastructure.'),
                'notice' => rateb_public_cms('proc.hero.notice', 'This page states verifiable company and platform facts. RATEB does not claim government partnerships, regulatory licenses, or third-party certifications unless separately documented in a signed agreement.'),
            ],
            'identity' => [
                'id' => 'company-identity',
                'eyebrow' => 'Company identity',
                'title' => 'Legal entity and official contact',
                'sub' => 'Registered Saudi technology company operating enterprise workforce program infrastructure.',
                'fields' => [
                    ['label' => 'Operating company', 'value' => rateb_public_cms('proc.identity.legal_name', rateb_public_cms('profile.company.legal_name', 'RATEB Company')), 'icon' => 'fa-building'],
                    ['label' => 'Trade name', 'value' => rateb_public_cms('proc.identity.trade_name', rateb_public_cms('profile.company.trade_name', 'RATEB')), 'icon' => 'fa-tag'],
                    ['label' => 'Headquarters', 'value' => rateb_public_cms('proc.identity.hq', rateb_public_cms('profile.company.hq', 'Riyadh, Kingdom of Saudi Arabia')), 'icon' => 'fa-location-dot'],
                    ['label' => 'Commercial registration', 'value' => rateb_public_cms('proc.identity.cr', rateb_public_cms('profile.company.cr_value', 'On file — available to enterprise procurement under NDA upon request')), 'icon' => 'fa-file-contract'],
                    ['label' => 'VAT', 'value' => rateb_public_cms('proc.identity.vat', rateb_public_cms('profile.company.vat_value', 'Available on invoice or upon formal request during procurement')), 'icon' => 'fa-receipt'],
                    ['label' => 'Official email', 'value' => rateb_public_cms('public.contact.email', 'info@rateb.sa'), 'icon' => 'fa-envelope', 'href' => 'mailto:' . rateb_public_cms('public.contact.email', 'info@rateb.sa')],
                    ['label' => 'Business phone', 'value' => rateb_public_cms('public.contact.phone', '+966 599 863 868'), 'icon' => 'fa-phone', 'href' => 'tel:+966599863868'],
                    ['label' => 'Public website', 'value' => 'rateb.sa', 'icon' => 'fa-globe', 'href' => 'https://rateb.sa'],
                ],
            ],
            'engagement' => [
                'id' => 'enterprise-engagement',
                'eyebrow' => 'Enterprise engagement',
                'title' => 'Structured onboarding for regulated programs',
                'sub' => 'Procurement and technical teams follow a defined path from initial review through corridor scoping.',
                'steps' => [
                    ['title' => 'Onboarding process', 'body' => 'Commercial qualification, data-processing scope, and tenant provisioning aligned to agency structure and sending-market rules.', 'icon' => 'fa-clipboard-list'],
                    ['title' => 'Enterprise review', 'body' => 'Security questionnaire responses, organizational fit, and program volume expectations documented before production cutover.', 'icon' => 'fa-magnifying-glass'],
                    ['title' => 'Architecture discussions', 'body' => 'Technical walkthrough of platform layers, isolation model, and integration boundaries with your IT and integration teams.', 'icon' => 'fa-diagram-project'],
                    ['title' => 'Operational scoping', 'body' => 'Branch structure, operator roles, corridor policies, and module enablement mapped to your operating model.', 'icon' => 'fa-sliders'],
                    ['title' => 'Corridor onboarding', 'body' => 'Country profiles, stage graphs, and partner workflows configured per sending-market and host-market requirements.', 'icon' => 'fa-route'],
                ],
            ],
            'security_governance' => [
                'id' => 'security-governance',
                'eyebrow' => 'Security & governance',
                'title' => 'Procurement-oriented security summary',
                'sub' => 'Detailed posture documentation is maintained on dedicated trust and architecture pages.',
                'summary' => 'RATEB architecture includes TLS-protected edges, role-based access, audit-oriented lifecycle history, tenant-isolated datastores, webhook integrity controls, and operational logging designed for enterprise review. Third-party certification claims (e.g. SOC 2, ISO) are not stated on public pages unless covered in a signed enterprise agreement.',
                'links' => [
                    [
                        'title' => 'Security & compliance center',
                        'desc' => 'Isolation, governance, authentication, reliability, and procurement CTAs.',
                        'href' => $root . '/security-compliance/',
                        'icon' => 'fa-shield-halved',
                    ],
                    [
                        'title' => 'Platform architecture',
                        'desc' => 'Platform layers, event delivery, field operations, finance, and deployment topology.',
                        'href' => $root . '/architecture/',
                        'icon' => 'fa-sitemap',
                    ],
                ],
            ],
            'data_boundaries' => [
                'id' => 'data-tenant-boundaries',
                'eyebrow' => 'Data & tenant boundaries',
                'title' => 'Operational separation model',
                'sub' => 'Shared orchestration does not imply shared program data between agencies.',
                'points' => [
                    ['title' => 'Platform core', 'body' => 'Platform metadata, tenant routing, identity, and workflow configuration reside in shared platform stores.'],
                    ['title' => 'Tenant datastores', 'body' => 'Agency workforce records, documents, and operational state are persisted in tenant-scoped databases.'],
                    ['title' => 'Policy scope', 'body' => 'Country profiles and RBAC limit operator and API visibility across corridors and branches.'],
                    ['title' => 'Integration boundaries', 'body' => 'Webhooks and API keys are issued per tenant context; cross-tenant reads are not part of the standard model.'],
                ],
            ],
            'legal_notes' => [
                'id' => 'legal-operational-notes',
                'eyebrow' => 'Legal & operational notes',
                'title' => 'Scope and platform role',
                'sub' => 'Precise positioning for vendor assessment and program governance.',
                'items' => [
                    [
                        'title' => 'Service scope',
                        'body' => 'RATEB provides software for workforce program workflows, field-operations support, commercial settlement, and operational governance—not placement brokerage or immigration legal services unless separately contracted.',
                    ],
                    [
                        'title' => 'Platform role',
                        'body' => 'The platform coordinates lifecycle stages, documents, finance events, and field signals; agencies and programs retain operational accountability for decisions made within configured policies.',
                    ],
                    [
                        'title' => 'Infrastructure positioning',
                        'body' => 'Deployed as managed cloud infrastructure with edge protection and observability patterns suitable for multi-agency, multi-corridor operations.',
                    ],
                    [
                        'title' => 'Operational governance approach',
                        'body' => 'Policy enforcement, immutable stage history where configured, and labor-oversight-oriented modules support audit-ready programs without replacing statutory authority of regulators.',
                    ],
                ],
            ],
            'requests' => [
                'id' => 'procurement-requests',
                'eyebrow' => 'Procurement requests',
                'title' => 'Request documentation or sessions',
                'sub' => 'All requests are handled by the enterprise team at the official business contact below.',
                'ctas' => [
                    [
                        'title' => 'Request Company Deck',
                        'body' => 'Corporate overview, markets served, and platform scope for vendor files.',
                        'href' => $mailto('RATEB — Request Company Deck'),
                        'icon' => 'fa-file-lines',
                        'variant' => 'primary',
                    ],
                    [
                        'title' => 'Request Enterprise Demo',
                        'body' => 'Guided walkthrough of agency workspace, corridors, and governance modules.',
                        'href' => $mailto('RATEB — Request Enterprise Demo'),
                        'icon' => 'fa-display',
                        'variant' => 'outline',
                    ],
                    [
                        'title' => 'Request Architecture Brief',
                        'body' => 'Technical summary for IT architecture and integration planning.',
                        'href' => $mailto('RATEB — Request Architecture Brief'),
                        'icon' => 'fa-diagram-project',
                        'variant' => 'outline',
                    ],
                    [
                        'title' => 'Contact Enterprise Team',
                        'body' => 'Procurement, legal, and program inquiries — info@rateb.sa',
                        'href' => $mailto('RATEB — Enterprise Team Inquiry'),
                        'icon' => 'fa-envelope',
                        'variant' => 'ghost',
                    ],
                ],
            ],
            'contact' => [
                'id' => 'contact-escalation',
                'eyebrow' => 'Contact & escalation',
                'title' => 'Official business contact',
                'sub' => 'Use the enterprise mailbox for procurement, legal, security questionnaires, and partner onboarding.',
                'email' => $enterpriseMail,
                'phone' => '+966 599 863 868',
                'phone_href' => 'tel:+966599863868',
                'whatsapp' => 'https://wa.me/966599863868',
                'hq' => 'Riyadh, Kingdom of Saudi Arabia',
            ],
        ];
    }
}
