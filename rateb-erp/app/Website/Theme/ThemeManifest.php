<?php
declare(strict_types=1);

namespace Rateb\App\Website\Theme;

/**
 * Phase WEBSITE-05 — Theme package manifest (validated shape).
 *
 * @phpstan-type ManifestArray array{
 *   slug:string,name_en:string,name_ar?:string,version:string,engine:string,engine_min?:string,
 *   author?:string,description_en?:string,description_ar?:string,preview_image?:string,
 *   tokens?:array<string,mixed>,layout?:array<string,mixed>,header?:array<string,mixed>,
 *   footer?:array<string,mixed>,fonts?:list<string>,icons?:string|array<string,mixed>,
 *   colors?:array<string,mixed>,typography?:array<string,mixed>,blocks?:list<string>,
 *   assets?:list<array<string,mixed>>,demo?:array<string,mixed>,seo_defaults?:array<string,mixed>
 * }
 */
final class ThemeManifest
{
    public const ENGINE = 'website-05';
    public const ENGINE_MIN = '1.0';

    /** @param array<string, mixed> $data */
    public function __construct(private array $data)
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Invalid theme manifest JSON');
        }

        return new self($decoded);
    }

    public function slug(): string
    {
        return (string) ($this->data['slug'] ?? '');
    }

    public function version(): string
    {
        return (string) ($this->data['version'] ?? '1.0.0');
    }

    public function engine(): string
    {
        return (string) ($this->data['engine'] ?? self::ENGINE);
    }

    public function engineMin(): string
    {
        return (string) ($this->data['engine_min'] ?? self::ENGINE_MIN);
    }

    public function nameEn(): string
    {
        return (string) ($this->data['name_en'] ?? $this->slug());
    }

    public function nameAr(): string
    {
        return (string) ($this->data['name_ar'] ?? '');
    }

    /** @return array<string, mixed> */
    public function tokens(): array
    {
        $tokens = $this->data['tokens'] ?? [];
        if (!is_array($tokens)) {
            $tokens = [];
        }
        if (isset($this->data['colors']) && is_array($this->data['colors'])) {
            $tokens['colors'] = array_replace_recursive($tokens['colors'] ?? [], $this->data['colors']);
        }
        if (isset($this->data['typography']) && is_array($this->data['typography'])) {
            $tokens['typography'] = array_replace_recursive($tokens['typography'] ?? [], $this->data['typography']);
        }
        if (isset($this->data['layout']) && is_array($this->data['layout'])) {
            $tokens['layout'] = array_replace_recursive($tokens['layout'] ?? [], $this->data['layout']);
        }
        if (isset($this->data['header']) && is_array($this->data['header'])) {
            $tokens['header'] = array_replace_recursive($tokens['header'] ?? [], $this->data['header']);
        }
        if (isset($this->data['footer']) && is_array($this->data['footer'])) {
            $tokens['footer'] = array_replace_recursive($tokens['footer'] ?? [], $this->data['footer']);
        }
        if (isset($this->data['icons'])) {
            $tokens['icons'] = is_array($this->data['icons'])
                ? $this->data['icons']
                : ['style' => (string) $this->data['icons']];
        }

        return $tokens;
    }

    /** @return list<string> */
    public function blocks(): array
    {
        $blocks = $this->data['blocks'] ?? [];
        if (!is_array($blocks)) {
            return [];
        }
        $out = [];
        foreach ($blocks as $b) {
            $out[] = (string) $b;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function assets(): array
    {
        $assets = $this->data['assets'] ?? [];
        if (!is_array($assets)) {
            return [];
        }
        $out = [];
        foreach ($assets as $a) {
            if (is_array($a)) {
                $out[] = $a;
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function demo(): array
    {
        $demo = $this->data['demo'] ?? [];

        return is_array($demo) ? $demo : [];
    }

    /** @return array<string, mixed> */
    public function seoDefaults(): array
    {
        $seo = $this->data['seo_defaults'] ?? [];

        return is_array($seo) ? $seo : [];
    }

    /** @return list<string> */
    public function fonts(): array
    {
        $fonts = $this->data['fonts'] ?? [];
        if (!is_array($fonts)) {
            return [];
        }
        $out = [];
        foreach ($fonts as $f) {
            $out[] = (string) $f;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function toJson(): string
    {
        $json = json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return is_string($json) ? $json : '{}';
    }
}
