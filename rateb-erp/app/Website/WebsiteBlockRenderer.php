<?php
declare(strict_types=1);

namespace Rateb\App\Website;

use Rateb\App\Core\View;
use Rateb\App\Services\CmsService;

/**
 * Phase WEBSITE-04 — Single public/preview renderer for builder blocks (no duplicated logic).
 */
final class WebsiteBlockRenderer
{
    private TenantWebsiteRepository $repo;
    private TenantBlockService $blocks;
    private TenantThemeService $theme;
    private WebsiteFormService $forms;
    private ?Career\CareerBlockRenderer $careerBlocks = null;
    private ?Portal\PortalBlockRenderer $portalBlocks = null;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
        $this->blocks = new TenantBlockService($this->repo);
        $this->theme = new TenantThemeService($this->repo);
        $this->forms = new WebsiteFormService($this->repo);
    }

    public function renderPage(string $pageSlug, bool $draft = false): string
    {
        $content = $this->blocks->pageContent($pageSlug);
        if ($content === [] && !$draft) {
            return '';
        }
        $html = '';
        foreach ($content as $key => $pack) {
            $section = $pack['section'] ?? [];
            $sectionBlocks = $pack['blocks'] ?? [];
            if (!is_array($section) || !is_array($sectionBlocks)) {
                continue;
            }
            $html .= $this->renderSection($section, $sectionBlocks, (string) $key);
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $section
     * @param list<array<string, mixed>> $blocks
     */
    public function renderSection(array $section, array $blocks, string $key = ''): string
    {
        $key = $key !== '' ? $key : (string) ($section['section_key'] ?? 'section');
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '', $key) ?: 'section';
        $title = $this->loc($section, 'title');
        $body = $this->loc($section, 'body');
        $out = '<section class="wb-section wb-section--' . View::escape($safeKey) . '" data-section="' . View::escape($safeKey) . '">';
        if ($title !== '') {
            $out .= '<header class="wb-section__header"><h2 class="wb-section__title">' . View::escape($title) . '</h2>';
            if ($body !== '') {
                $out .= '<p class="wb-section__lead">' . View::escape($body) . '</p>';
            }
            $out .= '</header>';
        }
        $out .= '<div class="wb-section__blocks">';
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $out .= $this->renderBlock($block);
        }
        $out .= '</div></section>';

        return $out;
    }

    /** @param array<string, mixed> $block */
    public function renderBlock(array $block): string
    {
        $type = (string) ($block['block_type'] ?? 'text');
        if (!WebsiteBlockRegistry::isValid($type)) {
            $type = 'text';
        }
        $title = $this->loc($block, 'title');
        $content = $this->loc($block, 'content');
        $settings = $this->decodeSettings($block);
        $image = trim((string) ($block['image_path'] ?? ''));
        $link = trim((string) ($block['link_url'] ?? ''));
        $icon = trim((string) ($block['icon'] ?? ''));
        $cls = 'wb-block wb-block--' . View::escape($type);

        return match ($type) {
            'hero' => $this->renderHero($cls, $title, $content, $image, $link, $settings),
            'spacer' => $this->renderSpacer($cls, $settings),
            'divider' => '<hr class="' . $cls . ' wb-divider">',
            'image' => $this->renderImage($cls, $title, $image, $link),
            'video' => $this->renderVideo($cls, $title, $settings),
            'custom_html' => $this->renderCustomHtml($cls, $content, $settings),
            'whatsapp' => $this->renderWhatsapp($cls, $title, $settings),
            'map' => $this->renderMap($cls, $title, $settings),
            'forms' => $this->renderFormBlock($cls, $title, $settings),
            'cta' => $this->renderCta($cls, $title, $content, $link, $settings),
            'jobs' => $this->careerBlocks()->renderJobs($cls, $title, $settings),
            'featured_jobs' => $this->careerBlocks()->renderFeatured($cls, $title, $settings),
            'job_categories' => $this->careerBlocks()->renderCategories($cls, $title),
            'job_search' => $this->careerBlocks()->renderSearchWidget($cls, $title, $settings),
            'cta_apply' => $this->careerBlocks()->renderCtaApply($cls, $title, $content, $settings),
            'recruiter_team' => $this->careerBlocks()->renderRecruiterTeam($cls, $title, $settings),
            'employer_dashboard' => $this->portalBlocks()->renderDashboard($cls, $title, 'employer', $settings),
            'customer_dashboard' => $this->portalBlocks()->renderDashboard($cls, $title, 'customer', $settings),
            'outstanding_invoices' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer/finance', __('invoices') ?: 'Invoices'),
            'active_contracts' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer/requests', __('contracts') ?: 'Contracts'),
            'recent_requests' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/employer/requests', __('requests') ?: 'Requests'),
            'recruitment_status' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/employer/recruitment', __('recruitment') ?: 'Recruitment'),
            'candidate_pipeline' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/employer/recruitment', __('candidate_pipeline') ?: 'Pipeline'),
            'portal_documents' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer/documents', __('documents') ?: 'Documents'),
            'portal_payments' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer/finance', __('payments') ?: 'Payments'),
            'portal_support_tickets' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer/support', __('support') ?: 'Support'),
            'portal_notifications' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer/notifications', __('notifications') ?: 'Notifications'),
            'portal_calendar' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/employer/appointments', __('calendar') ?: 'Calendar'),
            'invoice_summary' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer/finance', __('invoices') ?: 'Invoices'),
            'contract_summary' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer/contracts', __('contracts') ?: 'Contracts'),
            'recruitment_progress' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer/pipeline', __('pipeline') ?: 'Pipeline'),
            'recent_candidates' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer/pipeline', __('candidates') ?: 'Candidates'),
            'pending_approvals' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer/approvals', __('approvals') ?: 'Approvals'),
            'payment_status' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer/finance', __('payments') ?: 'Payments'),
            'support_widget' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer/support', __('support') ?: 'Support'),
            'documents_widget' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer/documents', __('documents') ?: 'Documents'),
            'statistics_cards' => $this->portalBlocks()->renderDashboard($cls, $title, 'customer', $settings),
            'timeline' => $this->portalBlocks()->renderLinkPanel($cls, $title, 'site/customer', __('timeline') ?: 'Timeline'),
            'quick_actions' => $this->portalBlocks()->renderDashboard($cls, $title, 'customer', $settings),
            'service_packages' => $this->portalBlocks()->renderServicePackages($cls, $title, $settings),
            'online_booking' => $this->portalBlocks()->renderOnlineBooking($cls, $title, $settings),
            'recruitment_wizard' => $this->portalBlocks()->renderRecruitmentWizard($cls, $title, $settings),
            'pricing_cards' => $this->portalBlocks()->renderPricingCards($cls, $title, $settings),
            'service_timeline' => $this->portalBlocks()->renderServiceTimeline($cls, $title, $settings),
            'appointment_calendar' => $this->portalBlocks()->renderAppointmentCalendar($cls, $title, $settings),
            'customer_reviews' => $this->portalBlocks()->renderCustomerReviews($cls, $title, $content, $settings),
            'cta_banner' => $this->portalBlocks()->renderCtaBanner($cls, $title, $content, $link, $settings),
            'online_contact_form' => $this->portalBlocks()->renderOnlineContactForm($cls, $title, $settings),
            default => $this->renderGeneric($cls, $type, $title, $content, $image, $link, $icon, $settings),
        };
    }

    /** @param array<string, mixed> $row */
    private function loc(array $row, string $base): string
    {
        $key = CmsService::localeField($base);

        return trim((string) ($row[$key] ?? $row[$base . '_en'] ?? ''));
    }

    /** @param array<string, mixed> $block @return array<string, mixed> */
    private function decodeSettings(array $block): array
    {
        $raw = $block['settings_json'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $settings */
    private function renderSpacer(string $cls, array $settings): string
    {
        $h = (int) ($settings['height'] ?? 48);
        $size = $h <= 24 ? 'sm' : ($h <= 64 ? 'md' : ($h <= 120 ? 'lg' : 'xl'));

        return '<div class="' . $cls . ' wb-spacer wb-spacer--' . $size . '" aria-hidden="true"></div>';
    }

    /** @param array<string, mixed> $settings */
    private function renderHero(string $cls, string $title, string $content, string $image, string $link, array $settings): string
    {
        $ctaEn = (string) ($settings['cta_label_en'] ?? 'Get started');
        $ctaAr = (string) ($settings['cta_label_ar'] ?? 'ابدأ الآن');
        $cta = rateb_locale() === 'ar' ? $ctaAr : $ctaEn;
        $ctaUrl = (string) ($settings['cta_url'] ?? $link);
        $html = '<div class="' . $cls . '">';
        if ($image !== '') {
            $html .= '<img class="wb-hero__bg" src="' . View::escape($image) . '" alt="" loading="eager" decoding="async">';
        }
        $html .= '<div class="wb-hero__inner">';
        if ($title !== '') {
            $html .= '<h1 class="wb-hero__title">' . View::escape($title) . '</h1>';
        }
        if ($content !== '') {
            $html .= '<p class="wb-hero__text">' . View::escape($content) . '</p>';
        }
        if ($ctaUrl !== '' && $cta !== '') {
            $html .= '<a class="wb-btn wb-btn--primary" href="' . View::escape($ctaUrl) . '">' . View::escape($cta) . '</a>';
        }
        $html .= '</div></div>';

        return $html;
    }

    private function renderImage(string $cls, string $title, string $image, string $link): string
    {
        if ($image === '') {
            return '';
        }
        $img = '<img class="wb-img" src="' . View::escape($image) . '" alt="' . View::escape($title) . '" loading="lazy" decoding="async">';
        if ($link !== '') {
            $img = '<a href="' . View::escape($link) . '">' . $img . '</a>';
        }

        return '<figure class="' . $cls . '">' . $img . ($title !== '' ? '<figcaption>' . View::escape($title) . '</figcaption>' : '') . '</figure>';
    }

    /** @param array<string, mixed> $settings */
    private function renderVideo(string $cls, string $title, array $settings): string
    {
        $src = trim((string) ($settings['src'] ?? ''));
        if ($src === '') {
            return '';
        }
        $poster = trim((string) ($settings['poster'] ?? ''));
        $attrs = ' controls playsinline' . (!empty($settings['autoplay']) ? ' autoplay muted' : '');
        $posterAttr = $poster !== '' ? ' poster="' . View::escape($poster) . '"' : '';

        return '<div class="' . $cls . '"><video class="wb-video" src="' . View::escape($src) . '"' . $posterAttr . $attrs . '></video>'
            . ($title !== '' ? '<p class="wb-video__caption">' . View::escape($title) . '</p>' : '') . '</div>';
    }

    /** @param array<string, mixed> $settings */
    private function renderCustomHtml(string $cls, string $content, array $settings): string
    {
        // Never allow scripts from builder HTML.
        $safe = preg_replace('#<\s*script\b[^>]*>.*?<\s*/\s*script\s*>#is', '', $content) ?? '';
        $safe = preg_replace('/\son\w+\s*=\s*("|\')[^"\']*\1/i', '', $safe) ?? '';

        return '<div class="' . $cls . '">' . $safe . '</div>';
    }

    /** @param array<string, mixed> $settings */
    private function renderWhatsapp(string $cls, string $title, array $settings): string
    {
        $phone = preg_replace('/\D+/', '', (string) ($settings['phone'] ?? $this->theme->whatsapp())) ?? '';
        if ($phone === '') {
            return '';
        }
        $msg = rateb_locale() === 'ar'
            ? (string) ($settings['message_ar'] ?? '')
            : (string) ($settings['message_en'] ?? '');
        $url = 'https://wa.me/' . $phone . ($msg !== '' ? '?text=' . rawurlencode($msg) : '');
        $label = $title !== '' ? $title : 'WhatsApp';

        return '<div class="' . $cls . '"><a class="wb-btn wb-btn--whatsapp" href="' . View::escape($url) . '" rel="noopener" target="_blank">'
            . View::escape($label) . '</a></div>';
    }

    /** @param array<string, mixed> $settings */
    private function renderMap(string $cls, string $title, array $settings): string
    {
        $url = trim((string) ($settings['embed_url'] ?? ''));
        if ($url === '' || !preg_match('#^https://(www\.)?(google\.com/maps|maps\.google\.com)/#i', $url)) {
            return '';
        }
        $h = max(200, min(800, (int) ($settings['height'] ?? 360)));

        return '<div class="' . $cls . '">' . ($title !== '' ? '<h3>' . View::escape($title) . '</h3>' : '')
            . '<iframe class="wb-map" src="' . View::escape($url) . '" height="' . $h . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map"></iframe></div>';
    }

    /** @param array<string, mixed> $settings */
    private function renderFormBlock(string $cls, string $title, array $settings): string
    {
        $slug = trim((string) ($settings['form_slug'] ?? 'contact'));
        $form = $this->forms->findBySlug($slug);
        if ($form === null) {
            return '';
        }
        $fields = $this->forms->fieldsForForm((int) $form['id']);
        $action = rateb_url('site/forms/' . rawurlencode($slug));
        $html = '<div class="' . $cls . '">';
        if ($title !== '') {
            $html .= '<h3 class="wb-form__title">' . View::escape($title) . '</h3>';
        }
        $html .= '<form class="wb-form" method="post" action="' . View::escape($action) . '">';
        $html .= '<input type="hidden" name="_csrf" value="' . View::escape(\Rateb\App\Core\Csrf::token()) . '">';
        foreach ($fields as $field) {
            $html .= $this->renderFormField($field);
        }
        $html .= '<button type="submit" class="wb-btn wb-btn--primary">' . View::escape(__('submit') ?: 'Submit') . '</button>';
        $html .= '</form></div>';

        return $html;
    }

    private function careerBlocks(): Career\CareerBlockRenderer
    {
        if ($this->careerBlocks === null) {
            $this->careerBlocks = new Career\CareerBlockRenderer(new Career\CareerJobService($this->repo));
        }

        return $this->careerBlocks;
    }

    private function portalBlocks(): Portal\PortalBlockRenderer
    {
        if ($this->portalBlocks === null) {
            $this->portalBlocks = new Portal\PortalBlockRenderer();
        }

        return $this->portalBlocks;
    }

    /** @param array<string, mixed> $field */
    private function renderFormField(array $field): string
    {
        $key = (string) ($field['field_key'] ?? 'field');
        $type = (string) ($field['field_type'] ?? 'text');
        $label = rateb_locale() === 'ar'
            ? (string) ($field['label_ar'] ?? $field['label_en'] ?? $key)
            : (string) ($field['label_en'] ?? $key);
        $ph = rateb_locale() === 'ar'
            ? (string) ($field['placeholder_ar'] ?? '')
            : (string) ($field['placeholder_en'] ?? '');
        $req = !empty($field['is_required']) ? ' required' : '';
        $name = 'fields[' . View::escape($key) . ']';
        $id = 'wb_f_' . View::escape($key);
        $html = '<div class="wb-form__field"><label for="' . $id . '">' . View::escape($label) . '</label>';
        if ($type === 'textarea') {
            $html .= '<textarea id="' . $id . '" name="' . $name . '" placeholder="' . View::escape($ph) . '"' . $req . '></textarea>';
        } elseif ($type === 'select') {
            $opts = $field['options_json'] ?? [];
            if (is_string($opts)) {
                $opts = json_decode($opts, true) ?: [];
            }
            $html .= '<select id="' . $id . '" name="' . $name . '"' . $req . '>';
            if (is_array($opts)) {
                foreach ($opts as $opt) {
                    $v = is_array($opt) ? (string) ($opt['value'] ?? $opt['label'] ?? '') : (string) $opt;
                    $html .= '<option value="' . View::escape($v) . '">' . View::escape($v) . '</option>';
                }
            }
            $html .= '</select>';
        } else {
            $inputType = in_array($type, ['email', 'tel', 'number', 'url', 'date'], true) ? $type : 'text';
            $html .= '<input id="' . $id . '" type="' . $inputType . '" name="' . $name . '" placeholder="' . View::escape($ph) . '"' . $req . '>';
        }
        $html .= '</div>';

        return $html;
    }

    /** @param array<string, mixed> $settings */
    private function renderCta(string $cls, string $title, string $content, string $link, array $settings): string
    {
        $url = $link !== '' ? $link : (string) ($settings['cta_url'] ?? '#');

        return '<div class="' . $cls . '"><div class="wb-cta">'
            . ($title !== '' ? '<h3>' . View::escape($title) . '</h3>' : '')
            . ($content !== '' ? '<p>' . View::escape($content) . '</p>' : '')
            . '<a class="wb-btn wb-btn--primary" href="' . View::escape($url) . '">' . View::escape($title !== '' ? $title : 'Learn more') . '</a>'
            . '</div></div>';
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function renderGeneric(
        string $cls,
        string $type,
        string $title,
        string $content,
        string $image,
        string $link,
        string $icon,
        array $settings
    ): string {
        $html = '<article class="' . $cls . '">';
        if ($icon !== '') {
            $html .= '<i class="fas ' . View::escape($icon) . ' wb-block__icon" aria-hidden="true"></i>';
        }
        if ($image !== '') {
            $html .= '<img class="wb-img" src="' . View::escape($image) . '" alt="" loading="lazy" decoding="async">';
        }
        if ($title !== '') {
            $html .= '<h3 class="wb-block__title">' . View::escape($title) . '</h3>';
        }
        if ($content !== '') {
            $html .= '<div class="wb-block__body">' . nl2br(View::escape($content)) . '</div>';
        }
        $items = $settings['items'] ?? $settings['plans'] ?? $settings['images'] ?? $settings['logos'] ?? $settings['slides'] ?? null;
        if (is_array($items) && $items !== []) {
            $html .= '<ul class="wb-block__list">';
            foreach ($items as $item) {
                if (!is_array($item)) {
                    $html .= '<li>' . View::escape((string) $item) . '</li>';
                    continue;
                }
                $label = rateb_locale() === 'ar'
                    ? (string) ($item['title_ar'] ?? $item['label_ar'] ?? $item['title_en'] ?? $item['label_en'] ?? '')
                    : (string) ($item['title_en'] ?? $item['label_en'] ?? $item['title_ar'] ?? '');
                $val = (string) ($item['value'] ?? $item['url'] ?? $item['src'] ?? '');
                $html .= '<li><span>' . View::escape($label) . '</span>';
                if ($val !== '' && preg_match('#^https?://|^/#', $val)) {
                    $html .= ' <img class="wb-img wb-img--thumb" src="' . View::escape($val) . '" alt="" loading="lazy">';
                } elseif ($val !== '') {
                    $html .= ' <strong>' . View::escape($val) . '</strong>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        if ($link !== '') {
            $html .= '<a class="wb-block__link" href="' . View::escape($link) . '">' . View::escape(__('read_more') ?: 'Read more') . '</a>';
        }
        $html .= '</article>';

        return $html;
    }
}
