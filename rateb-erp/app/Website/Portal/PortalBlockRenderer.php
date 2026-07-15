<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Core\View;

/**
 * Phase WEBSITE-07 — Builder portal summary blocks (presentation only).
 */
final class PortalBlockRenderer
{
    /** @param array<string, mixed> $settings */
    public function renderDashboard(string $cls, string $title, string $type, array $settings): string
    {
        $url = rateb_url('site/' . $type);
        $html = '<div class="' . $cls . ' wb-portal-panel">';
        if ($title !== '') {
            $html .= '<h3>' . View::escape($title) . '</h3>';
        }
        $html .= '<p>' . View::escape(__('portal_dashboard_hint') ?: 'Open your self-service portal.') . '</p>';
        $html .= '<a class="wb-btn wb-btn--primary" href="' . View::escape($url) . '">' . View::escape(ucfirst($type) . ' portal') . '</a>';
        $html .= '</div>';

        return $html;
    }

    public function renderLinkPanel(string $cls, string $title, string $path, string $label): string
    {
        $html = '<div class="' . $cls . ' wb-portal-panel">';
        if ($title !== '') {
            $html .= '<h3>' . View::escape($title) . '</h3>';
        }
        $html .= '<a class="wb-btn wb-btn--primary" href="' . View::escape(rateb_url($path)) . '">' . View::escape($label) . '</a>';
        $html .= '</div>';

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function renderSummaryList(string $cls, string $title, array $items): string
    {
        $html = '<div class="' . $cls . ' wb-portal-panel">';
        if ($title !== '') {
            $html .= '<h3>' . View::escape($title) . '</h3>';
        }
        $html .= '<ul>';
        foreach ($items as $item) {
            $html .= '<li>' . View::escape((string) $item) . '</li>';
        }
        if ($items === []) {
            $html .= '<li>' . View::escape(__('no_data') ?: 'No data') . '</li>';
        }
        $html .= '</ul></div>';

        return $html;
    }

    /** Phase WEBSITE-09 — Online service marketing blocks (presentation → customer portal). */
    /** @param array<string, mixed> $settings */
    public function renderServicePackages(string $cls, string $title, array $settings): string
    {
        $packages = [
            ['code' => 'recruitment_basic', 'label' => __('pkg_recruitment_basic') ?: 'Recruitment Basic'],
            ['code' => 'domestic_standard', 'label' => __('pkg_domestic') ?: 'Domestic Worker'],
            ['code' => 'workforce_team', 'label' => __('pkg_workforce') ?: 'Company Workforce'],
        ];
        if (!empty($settings['packages']) && is_array($settings['packages'])) {
            $packages = $settings['packages'];
        }
        $html = '<div class="' . $cls . ' wb-svc-packages">';
        if ($title !== '') {
            $html .= '<h3>' . View::escape($title) . '</h3>';
        }
        $html .= '<div class="wb-svc-packages__grid">';
        foreach ($packages as $pkg) {
            $label = is_array($pkg) ? (string) ($pkg['label'] ?? $pkg['code'] ?? '') : (string) $pkg;
            $code = is_array($pkg) ? (string) ($pkg['code'] ?? '') : '';
            $url = rateb_url('site/customer/services') . ($code !== '' ? '?package=' . rawurlencode($code) : '');
            $html .= '<a class="wb-svc-card" href="' . View::escape($url) . '"><strong>' . View::escape($label) . '</strong></a>';
        }
        $html .= '</div></div>';

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function renderOnlineBooking(string $cls, string $title, array $settings): string
    {
        return $this->renderLinkPanel(
            $cls . ' wb-svc-booking',
            $title !== '' ? $title : ((__('online_booking') ?: 'Online Booking')),
            'site/customer/services/book',
            __('book_now') ?: 'Book now'
        );
    }

    /** @param array<string, mixed> $settings */
    public function renderRecruitmentWizard(string $cls, string $title, array $settings): string
    {
        return $this->renderLinkPanel(
            $cls . ' wb-svc-wizard',
            $title !== '' ? $title : ((__('recruitment_wizard') ?: 'Recruitment Wizard')),
            'site/customer/services/new?type=recruitment',
            __('start_request') ?: 'Start request'
        );
    }

    /** @param array<string, mixed> $settings */
    public function renderPricingCards(string $cls, string $title, array $settings): string
    {
        return $this->renderServicePackages($cls . ' wb-svc-pricing', $title, $settings);
    }

    /** @param array<string, mixed> $settings */
    public function renderServiceTimeline(string $cls, string $title, array $settings): string
    {
        return $this->renderLinkPanel(
            $cls,
            $title !== '' ? $title : ((__('service_timeline') ?: 'Service Timeline')),
            'site/customer/services',
            __('track_request') ?: 'Track request'
        );
    }

    /** @param array<string, mixed> $settings */
    public function renderAppointmentCalendar(string $cls, string $title, array $settings): string
    {
        return $this->renderLinkPanel(
            $cls,
            $title !== '' ? $title : ((__('appointment_calendar') ?: 'Appointment Calendar')),
            'site/customer/services/book',
            __('view_calendar') ?: 'Appointments'
        );
    }

    /** @param array<string, mixed> $settings */
    public function renderCustomerReviews(string $cls, string $title, string $content, array $settings): string
    {
        $html = '<div class="' . $cls . ' wb-svc-reviews">';
        if ($title !== '') {
            $html .= '<h3>' . View::escape($title) . '</h3>';
        }
        if ($content !== '') {
            $html .= '<div class="wb-svc-reviews__body">' . nl2br(View::escape($content)) . '</div>';
        } else {
            $html .= '<p>' . View::escape(__('reviews_hint') ?: 'Customer reviews appear here.') . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function renderCtaBanner(string $cls, string $title, string $content, string $link, array $settings): string
    {
        $href = $link !== '' ? $link : rateb_url('site/customer/services');
        $html = '<div class="' . $cls . ' wb-svc-cta">';
        if ($title !== '') {
            $html .= '<h3>' . View::escape($title) . '</h3>';
        }
        if ($content !== '') {
            $html .= '<p>' . View::escape($content) . '</p>';
        }
        $html .= '<a class="wb-btn wb-btn--primary" href="' . View::escape($href) . '">'
            . View::escape((string) ($settings['cta_label'] ?? __('get_started') ?: 'Get started'))
            . '</a></div>';

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function renderOnlineContactForm(string $cls, string $title, array $settings): string
    {
        return $this->renderLinkPanel(
            $cls,
            $title !== '' ? $title : ((__('contact') ?: 'Contact')),
            'site/customer/support',
            __('contact_us') ?: 'Contact us'
        );
    }
}
