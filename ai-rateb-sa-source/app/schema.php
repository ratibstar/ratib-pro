<?php
declare(strict_types=1);

function ensure_campaign_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $existing = [];
    foreach ($pdo->query('SHOW COLUMNS FROM campaigns') as $row) {
        $existing[(string) $row['Field']] = (string) $row['Type'];
    }
    $add = [
        'objective' => 'ALTER TABLE campaigns ADD COLUMN objective VARCHAR(500) NULL',
        'start_date' => 'ALTER TABLE campaigns ADD COLUMN start_date DATE NULL',
        'end_date' => 'ALTER TABLE campaigns ADD COLUMN end_date DATE NULL',
        'budget' => 'ALTER TABLE campaigns ADD COLUMN budget DECIMAL(12,2) NULL',
    ];
    foreach ($add as $name => $sql) {
        if (!isset($existing[$name])) {
            $pdo->exec($sql);
        }
    }
    if (isset($existing['status']) && str_starts_with(strtolower($existing['status']), 'enum(') && !str_contains(strtolower($existing['status']), 'in_progress')) {
        $pdo->exec("ALTER TABLE campaigns MODIFY status VARCHAR(32) NOT NULL DEFAULT 'draft'");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS campaign_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        body MEDIUMTEXT NOT NULL,
        item_date DATE NULL,
        planner_status VARCHAR(16) NOT NULL DEFAULT 'draft',
        approval_status VARCHAR(16) NOT NULL DEFAULT 'draft',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY campaign_id (campaign_id),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS campaign_variations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        label VARCHAR(120) NOT NULL,
        output_type VARCHAR(64) NOT NULL,
        content MEDIUMTEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY campaign_id (campaign_id),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS brand_kits (
        user_id BIGINT UNSIGNED NOT NULL,
        brand_name VARCHAR(160) NOT NULL DEFAULT '',
        logo_stored VARCHAR(64) NULL,
        logo_mime VARCHAR(64) NULL,
        primary_color CHAR(7) NOT NULL DEFAULT '#0f766e',
        secondary_color CHAR(7) NOT NULL DEFAULT '#111827',
        preferred_language CHAR(2) NOT NULL DEFAULT 'en',
        tone VARCHAR(160) NOT NULL DEFAULT '',
        contact VARCHAR(255) NOT NULL DEFAULT '',
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function campaign_status_values(): array
{
    return ['draft', 'in_progress', 'ready', 'completed'];
}

function planner_status_values(): array
{
    return ['draft', 'review', 'approved', 'scheduled', 'published'];
}

function approval_status_values(): array
{
    return ['draft', 'approved', 'rejected'];
}

function campaign_one_of(string $value, array $allowed, string $fallback): string
{
    return in_array($value, $allowed, true) ? $value : $fallback;
}

function campaign_date_or_null(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

function campaign_money_or_null(string $value): ?string
{
    $value = trim(str_replace(',', '', $value));
    if ($value === '' || preg_match('/\d+(?:\.\d+)?/', $value, $match) !== 1) {
        return null;
    }
    return $match[0];
}

function campaign_hex_color(string $value, string $fallback): string
{
    $value = strtolower(trim($value));
    return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : $fallback;
}

function campaign_brand_context(PDO $pdo, int $userId, array $campaign): string
{
    $parts = [];
    foreach (['objective' => 'Objective', 'budget' => 'Budget', 'start_date' => 'Start date', 'end_date' => 'End date'] as $field => $label) {
        $value = trim((string) ($campaign[$field] ?? ''));
        if ($value !== '') {
            $parts[] = $label . ': ' . $value;
        }
    }
    $stmt = $pdo->prepare('SELECT brand_name, tone, preferred_language, contact FROM brand_kits WHERE user_id = ?');
    $stmt->execute([$userId]);
    $brand = $stmt->fetch();
    if ($brand) {
        foreach (['brand_name' => 'Brand name', 'tone' => 'Tone of voice', 'preferred_language' => 'Preferred language', 'contact' => 'Contact'] as $field => $label) {
            $value = trim((string) ($brand[$field] ?? ''));
            if ($value !== '') {
                $parts[] = $label . ': ' . $value;
            }
        }
    }
    return $parts === [] ? '' : ' Additional context: ' . implode('. ', $parts) . '.';
}
