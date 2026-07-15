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
}
