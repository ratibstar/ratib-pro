<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Company;
use PDO;

/** CRUD for rateb_guest_menu_settings. */
final class GuestMenuSettingsService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /** @return array<string, mixed>|null */
    public function getByCompanyId(int $companyId): ?array
    {
        if ($companyId < 1) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM rateb_guest_menu_settings WHERE company_id = :cid LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function getEnabledByPublicSlug(string $slug): ?array
    {
        $slug = self::normalizeSlug($slug);
        if ($slug === '') {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT g.*, c.name AS company_name, c.slug AS company_slug, c.status AS company_status
             FROM rateb_guest_menu_settings g
             INNER JOIN rateb_companies c ON c.id = g.company_id
             WHERE g.public_slug = :slug AND g.is_enabled = 1 AND c.status = \'active\'
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Ensure settings row exists for company (defaults from company slug).
     *
     * @return array<string, mixed>
     */
    public function ensureForCompany(int $companyId): array
    {
        $existing = $this->getByCompanyId($companyId);
        if ($existing !== null) {
            return $existing;
        }

        $company = (new Company())->find($companyId);
        $baseSlug = self::normalizeSlug((string) ($company['slug'] ?? ('menu-' . $companyId)));
        if ($baseSlug === '') {
            $baseSlug = 'menu-' . $companyId;
        }
        $slug = $this->allocateUniqueSlug($baseSlug, $companyId);

        $stmt = $this->db->prepare(
            'INSERT INTO rateb_guest_menu_settings
             (company_id, is_enabled, public_slug, mode, title_ar, title_en, created_at)
             VALUES (:cid, 0, :slug, \'browse\', :title_ar, :title_en, NOW())'
        );
        $companyName = trim((string) ($company['name'] ?? ''));
        $stmt->execute([
            'cid' => $companyId,
            'slug' => $slug,
            'title_ar' => $companyName !== '' ? $companyName : null,
            'title_en' => $companyName !== '' ? $companyName : null,
        ]);

        return $this->getByCompanyId($companyId) ?? [
            'company_id' => $companyId,
            'is_enabled' => 0,
            'public_slug' => $slug,
            'mode' => 'browse',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function save(int $companyId, array $input): array
    {
        $row = $this->ensureForCompany($companyId);

        $slug = self::normalizeSlug((string) ($input['public_slug'] ?? $row['public_slug'] ?? ''));
        if ($slug === '') {
            throw new \InvalidArgumentException('invalid_slug');
        }
        if (!$this->isSlugAvailable($slug, $companyId)) {
            throw new \InvalidArgumentException('slug_taken');
        }

        $mode = (string) ($input['mode'] ?? 'browse');
        if (!in_array($mode, ['browse', 'order'], true)) {
            $mode = 'browse';
        }
        // Order mode not implemented yet — force browse.
        if ($mode === 'order') {
            $mode = 'browse';
        }

        $branchId = isset($input['branch_id']) ? (int) $input['branch_id'] : null;
        if ($branchId !== null && $branchId < 1) {
            $branchId = null;
        }

        $stmt = $this->db->prepare(
            'UPDATE rateb_guest_menu_settings SET
                is_enabled = :enabled,
                public_slug = :slug,
                mode = :mode,
                branch_id = :branch_id,
                title_ar = :title_ar,
                title_en = :title_en,
                welcome_message = :welcome,
                updated_at = NOW()
             WHERE company_id = :cid'
        );
        $stmt->execute([
            'enabled' => !empty($input['is_enabled']) ? 1 : 0,
            'slug' => $slug,
            'mode' => $mode,
            'branch_id' => $branchId,
            'title_ar' => self::nullableString($input['title_ar'] ?? null),
            'title_en' => self::nullableString($input['title_en'] ?? null),
            'welcome' => self::nullableString($input['welcome_message'] ?? null),
            'cid' => $companyId,
        ]);

        return $this->ensureForCompany($companyId);
    }

    public static function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if (strlen($slug) > 64) {
            $slug = substr($slug, 0, 64);
        }

        return strlen($slug) >= 3 ? $slug : '';
    }

    public function publicMenuUrl(string $publicSlug): string
    {
        return function_exists('rateb_public_url')
            ? rateb_public_url('m/' . rawurlencode(self::normalizeSlug($publicSlug)))
            : '/m/' . rawurlencode(self::normalizeSlug($publicSlug));
    }

    private function allocateUniqueSlug(string $base, int $companyId): string
    {
        if ($this->isSlugAvailable($base, $companyId)) {
            return $base;
        }
        for ($i = 2; $i <= 99; ++$i) {
            $candidate = self::normalizeSlug($base . '-' . $i);
            if ($candidate !== '' && $this->isSlugAvailable($candidate, $companyId)) {
                return $candidate;
            }
        }

        return self::normalizeSlug($base . '-' . $companyId) ?: ('menu-' . $companyId);
    }

    private function isSlugAvailable(string $slug, int $companyId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT company_id FROM rateb_guest_menu_settings
             WHERE public_slug = :slug AND company_id != :cid LIMIT 1'
        );
        $stmt->execute(['slug' => $slug, 'cid' => $companyId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) === false;
    }

    private static function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }
}
