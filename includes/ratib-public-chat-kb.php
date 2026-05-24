<?php
/**
 * Public marketing site knowledge for the floating chat assistant.
 * Built from CMS keys (ratib_site_content) + canonical public URLs.
 *
 * @return list<array{keywords:list<string>,answer:string,category:string,synonyms?:list<string>}>
 */
declare(strict_types=1);

if (!function_exists('ratib_public_chat_kb_entries')) {
    function ratib_public_chat_kb_entries(string $baseUrl, array $home = []): array
    {
        $root = rtrim($baseUrl, '/');
        $homeUrl = function_exists('ratib_public_marketing_home_url')
            ? ratib_public_marketing_home_url($baseUrl)
            : $root . '/home';
        $registerUrl = function_exists('ratib_public_marketing_home_register_url')
            ? ratib_public_marketing_home_register_url($baseUrl, 'gold', 1)
            : $homeUrl . '?open=register#register';
        $profileUrl = $root . '/profile/';
        $loginUrl = $root . '/pages/login.php';
        $partnerUrl = $root . '/pages/partner-portal-login.php';
        $archUrl = $root . '/architecture/';
        $securityUrl = $root . '/security-compliance/';
        $procUrl = $root . '/procurement-legal/';
        $trustUrl = $root . '/enterprise-trust/';

        $registerIntro = trim(strip_tags((string) ($home['home.register.info.intro'] ?? '')));
        if ($registerIntro === '') {
            $registerIntro = 'RATEB is enterprise workforce program infrastructure for agencies and organizations in worker-sending countries.';
        }
        $registerTitle = trim((string) ($home['home.register.form.title'] ?? 'Register Your Agency'));
        $footerBrand = trim(strip_tags((string) ($home['home.footer.brand'] ?? '')));
        $goldFeatures = trim((string) ($home['home.pricing.gold.features'] ?? ''));
        $platFeatures = trim((string) ($home['home.pricing.platinum.features'] ?? ''));

        $phone = trim((string) ($home['home.topbar.phone_display'] ?? '+966 59 986 3868'));

        return [
            [
                'keywords' => [
                    'domain', 'domains', 'custom domain', 'get a domain', 'buy domain', 'agency domain',
                    'branded domain', 'hostname', 'subdomain', 'find a domain', 'domain search', 'my domain',
                ],
                'answer' => "**Get a domain for your agency portal:**\n"
                    . "1. Open the marketing home **Domains** section (nav → Domains or [Domains →]({$homeUrl}#domains))\n"
                    . "2. Search available names in the domain marketplace embed\n"
                    . "3. **Gold (Business)** and **Platinum (Enterprise)** include a branded agency portal with per-agency domain edges\n"
                    . "4. After registration is approved, our team helps connect DNS and SSL\n\n"
                    . "Need a custom hostname? Register first, then use **Talk to support** in this chat.",
                'category' => 'public_domains',
            ],
            [
                'keywords' => [
                    'register', 'registration', 'sign up', 'signup', 'create account', 'agency registration',
                    'apply', 'join', 'onboard', 'onboarding', 'submit request',
                ],
                'answer' => "**Register your agency:**\n"
                    . "1. **Open the registration form:** [Register your agency →]({$registerUrl})\n"
                    . "2. Fill agency name, country, contact email, and required details\n"
                    . "3. Choose **Gold (Business)** or **Platinum (Enterprise)** — the payment summary updates on the form\n"
                    . "4. Click **Submit Request** — we review and contact you about payment and onboarding\n\n"
                    . "{$registerIntro}\n\n"
                    . "Shortcut: nav **Grow** → register, or scroll to **{$registerTitle}** on the home page.",
                'category' => 'public_register',
            ],
            [
                'keywords' => ['price', 'pricing', 'cost', 'fee', 'plan', 'gold', 'platinum', 'business', 'enterprise', 'how much'],
                'answer' => "**Plans & pricing (public site):**\n"
                    . "• **Gold / Business** — branded agency portal, candidate & document management, e-invoice, managed SSL\n"
                    . "• **Platinum / Enterprise** — unlimited users, priority support, advanced analytics, custom integrations\n"
                    . "• **Pro** — contact solutions for custom scope\n\n"
                    . ($goldFeatures !== '' ? "**Business includes:**\n{$goldFeatures}\n\n" : '')
                    . "[View pricing cards →]({$homeUrl}#programs) · [Register →]({$registerUrl})",
                'category' => 'public_pricing',
            ],
            [
                'keywords' => [
                    'demo', 'enterprise demo', 'walkthrough', 'tour', 'platform walkthrough', 'video', 'how it works',
                    'request demo', 'see the platform',
                ],
                'answer' => "**See the platform:**\n"
                    . "• **Request Enterprise Demo** on the home hero — solutions team follow-up\n"
                    . "• **Platform walkthrough** / **Tour** in the nav — product tour and video band\n"
                    . "• **How it works** section explains the agency workflow end-to-end\n\n"
                    . "[Marketing home →]({$homeUrl}) · [Company profile / screenshots →]({$profileUrl})",
                'category' => 'public_demo',
            ],
            [
                'keywords' => [
                    'login', 'sign in', 'sign-in', 'client login', 'agency login', 'log in', 'portal login',
                ],
                'answer' => "**Sign in to your agency workspace:**\n"
                    . "• **Client login** / **Sign In** in the top nav → [Login →]({$loginUrl})\n"
                    . "• Forgot password? Use **Forgot Password** on the login page\n"
                    . "• Not registered yet? [Register your agency →]({$registerUrl}) first",
                'category' => 'public_login',
            ],
            [
                'keywords' => ['partner login', 'partner portal', 'partner sign in', 'partner access'],
                'answer' => "**Partner login:**\n"
                    . "Use **Partner Login** (gold button, top nav) for overseas partner agencies.\n\n"
                    . "[Open partner portal login →]({$partnerUrl})",
                'category' => 'public_partner',
            ],
            [
                'keywords' => [
                    'architecture', 'infrastructure', 'multi-tenant', 'tenant isolation', 'technical', 'system design',
                ],
                'answer' => "**Platform architecture:**\n"
                    . "Dedicated public page covers layered control plane, multi-tenant isolation, event-driven sync, telemetry, and deployment models.\n\n"
                    . "[Architecture documentation →]({$archUrl}) · [Security →]({$securityUrl})",
                'category' => 'public_architecture',
            ],
            [
                'keywords' => ['security', 'compliance', 'sla', 'audit', 'gdpr', 'data protection', 'encryption'],
                'answer' => "**Security & compliance:**\n"
                    . "Public trust documentation covers security posture, compliance framing, and operational governance.\n\n"
                    . "[Security & Compliance →]({$securityUrl}) · [Enterprise Trust →]({$trustUrl})",
                'category' => 'public_security',
            ],
            [
                'keywords' => ['procurement', 'legal', 'contract', 'vendor', 'rfp', 'due diligence'],
                'answer' => "**Procurement & legal:**\n"
                    . "Enterprise buyers can review procurement pack, legal framing, and corridor scoping on the dedicated page.\n\n"
                    . "[Procurement & Legal →]({$procUrl})",
                'category' => 'public_procurement',
            ],
            [
                'keywords' => [
                    'about', 'company profile', 'who is rateb', 'what is rateb', 'about rateb', 'company',
                ],
                'answer' => "**About RATEB:**\n"
                    . ($footerBrand !== '' ? "{$footerBrand}\n\n" : '')
                    . "Full company profile with platform identity, mission, services, and government screenshots:\n"
                    . "[Company profile →]({$profileUrl}) · [Marketing home →]({$homeUrl})",
                'category' => 'public_about',
            ],
            [
                'keywords' => [
                    'contact', 'phone', 'whatsapp', 'email', 'call', 'reach', 'support phone',
                ],
                'answer' => "**Contact RATEB:**\n"
                    . "• Phone: {$phone}\n"
                    . "• WhatsApp: green button / **Live via WhatsApp** in the header\n"
                    . "• Email: info@out.ratib.sa\n"
                    . "• Live agent: type **Talk to support** in this chat (connects to our control panel team)",
                'category' => 'public_contact',
            ],
            [
                'keywords' => ['hosting', 'ssl', 'server', 'cloud', 'managed infrastructure'],
                'answer' => "**Hosting & infrastructure:**\n"
                    . "Plans include managed cloud hosting, TLS/SSL, isolated tenants, and compliance-oriented audit trails.\n\n"
                    . "[Pricing & plans →]({$homeUrl}#programs) · [Architecture →]({$archUrl})",
                'category' => 'public_hosting',
            ],
            [
                'keywords' => ['payment', 'pay', 'bank transfer', 'how to pay', 'ngenius', 'checkout'],
                'answer' => "**Payment after registration:**\n"
                    . "1. Submit the registration request (Gold or Platinum)\n"
                    . "2. Our team reviews and contacts you with payment options (bank transfer and approved methods)\n"
                    . "3. Online checkout may appear on the registration form when a plan is selected\n\n"
                    . "[Open registration →]({$registerUrl})",
                'category' => 'public_payment',
            ],
        ];
    }
}
