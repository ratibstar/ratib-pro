<?php
declare(strict_types=1);

namespace Rateb\App\Website;

use Rateb\App\Core\Database;

/**
 * Phase WEBSITE-04 — Theme tokens (colors, typography, buttons, cards, radius, shadows, RTL/LTR).
 */
final class WebsiteThemeEditorService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return array<string, mixed> */
    public function defaultTokens(): array
    {
        return [
            'colors' => [
                'primary' => '#1a5fb4',
                'secondary' => '#3584e4',
                'accent' => '#26a269',
                'background' => '#ffffff',
                'surface' => '#f6f5f4',
                'text' => '#241f31',
                'muted' => '#77767b',
            ],
            'typography' => [
                'font_family' => 'Tajawal',
                'heading_weight' => 700,
                'body_size' => '16px',
                'line_height' => 1.6,
            ],
            'buttons' => [
                'radius' => '8px',
                'padding_y' => '0.65rem',
                'padding_x' => '1.25rem',
            ],
            'cards' => [
                'radius' => '12px',
                'padding' => '1.25rem',
                'border' => '1px solid rgba(0,0,0,.08)',
            ],
            'radius' => ['sm' => '4px', 'md' => '8px', 'lg' => '16px'],
            'shadows' => [
                'sm' => '0 1px 2px rgba(0,0,0,.06)',
                'md' => '0 8px 24px rgba(0,0,0,.08)',
            ],
            'icons' => ['style' => 'fontawesome', 'size' => '1.25rem'],
            'header' => ['sticky' => true, 'height' => '72px'],
            'footer' => ['columns' => 3],
            'layout' => ['max_width' => '1140px', 'gutter' => '1.5rem'],
            'direction' => 'auto',
        ];
    }

    /** @return array<string, mixed> */
    public function tokens(): array
    {
        $theme = (new TenantThemeService($this->repo))->theme();
        $raw = $theme['tokens_json'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_replace_recursive($this->defaultTokens(), $decoded);
            }
        }
        if (is_array($raw)) {
            return array_replace_recursive($this->defaultTokens(), $raw);
        }
        $defaults = $this->defaultTokens();
        $defaults['colors']['primary'] = (string) ($theme['primary_color'] ?? $defaults['colors']['primary']);
        $defaults['colors']['secondary'] = (string) ($theme['secondary_color'] ?? $defaults['colors']['secondary']);
        $defaults['typography']['font_family'] = (string) ($theme['font_family'] ?? $defaults['typography']['font_family']);

        return $defaults;
    }

    /**
     * @param array<string, mixed> $tokens
     * @param array<string, mixed> $themeFields
     */
    public function save(array $tokens, array $themeFields = []): void
    {
        $cid = $this->repo->companyId();
        $merged = array_replace_recursive($this->defaultTokens(), $tokens);
        $primary = (string) ($themeFields['primary_color'] ?? $merged['colors']['primary'] ?? '#1a5fb4');
        $secondary = (string) ($themeFields['secondary_color'] ?? $merged['colors']['secondary'] ?? '#3584e4');
        $font = (string) ($themeFields['font_family'] ?? $merged['typography']['font_family'] ?? 'Tajawal');
        $logo = (string) ($themeFields['logo_path'] ?? '');
        $favicon = (string) ($themeFields['favicon_path'] ?? '');
        $json = json_encode($merged, JSON_UNESCAPED_UNICODE);
        [$where, $params] = $this->repo->companyWhere();
        $row = $this->repo->fetchOne(
            "SELECT id FROM rateb_cms_theme WHERE {$where} ORDER BY id ASC LIMIT 1",
            $params
        );
        $db = Database::connection();
        if ($row) {
            $db->prepare(
                'UPDATE rateb_cms_theme SET primary_color=:p, secondary_color=:s, font_family=:f,
                 logo_path=COALESCE(NULLIF(:logo, \'\'), logo_path), favicon_path=COALESCE(NULLIF(:fav, \'\'), favicon_path),
                 tokens_json=:tok WHERE id=:id AND company_id=:cid'
            )->execute([
                'p' => $primary,
                's' => $secondary,
                'f' => $font,
                'logo' => $logo,
                'fav' => $favicon,
                'tok' => $json,
                'id' => (int) $row['id'],
                'cid' => $cid,
            ]);
        } else {
            $db->prepare(
                'INSERT INTO rateb_cms_theme (company_id, primary_color, secondary_color, font_family, logo_path, favicon_path, tokens_json)
                 VALUES (:cid, :p, :s, :f, :logo, :fav, :tok)'
            )->execute([
                'cid' => $cid,
                'p' => $primary,
                's' => $secondary,
                'f' => $font,
                'logo' => $logo !== '' ? $logo : null,
                'fav' => $favicon !== '' ? $favicon : null,
                'tok' => $json,
            ]);
        }
        TenantThemeService::clearCache();
    }

    public function cssVariables(): string
    {
        $t = $this->tokens();
        $c = $t['colors'] ?? [];
        $ty = $t['typography'] ?? [];
        $btn = $t['buttons'] ?? [];
        $card = $t['cards'] ?? [];
        $r = $t['radius'] ?? [];
        $sh = $t['shadows'] ?? [];
        $layout = $t['layout'] ?? [];
        $lines = [
            '--wb-color-primary:' . ($c['primary'] ?? '#1a5fb4'),
            '--wb-color-secondary:' . ($c['secondary'] ?? '#3584e4'),
            '--wb-color-accent:' . ($c['accent'] ?? '#26a269'),
            '--wb-color-bg:' . ($c['background'] ?? '#fff'),
            '--wb-color-surface:' . ($c['surface'] ?? '#f6f5f4'),
            '--wb-color-text:' . ($c['text'] ?? '#241f31'),
            '--wb-color-muted:' . ($c['muted'] ?? '#77767b'),
            '--wb-font-family:' . ($ty['font_family'] ?? 'Tajawal') . ', sans-serif',
            '--wb-body-size:' . ($ty['body_size'] ?? '16px'),
            '--wb-line-height:' . ($ty['line_height'] ?? '1.6'),
            '--wb-btn-radius:' . ($btn['radius'] ?? '8px'),
            '--wb-card-radius:' . ($card['radius'] ?? '12px'),
            '--wb-radius-sm:' . ($r['sm'] ?? '4px'),
            '--wb-radius-md:' . ($r['md'] ?? '8px'),
            '--wb-radius-lg:' . ($r['lg'] ?? '16px'),
            '--wb-shadow-sm:' . ($sh['sm'] ?? 'none'),
            '--wb-shadow-md:' . ($sh['md'] ?? 'none'),
            '--wb-max-width:' . ($layout['max_width'] ?? '1140px'),
        ];

        return ':root{' . implode(';', $lines) . ';}';
    }
}
