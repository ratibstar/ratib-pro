<?php
declare(strict_types=1);

if (!function_exists('rateb_worker_column_types')) {
  /**
   * @return array<string, string> column name => lowercase MySQL type
   */
  function rateb_worker_column_types(PDO $conn, string $table = 'workers'): array
  {
    static $cache = [];
    if (isset($cache[$table])) {
      return $cache[$table];
    }

    $cache[$table] = [];
    try {
      $safeTable = str_replace('`', '', $table);
      $stmt = $conn->query("DESCRIBE `{$safeTable}`");
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cache[$table][(string) ($row['Field'] ?? '')] = strtolower((string) ($row['Type'] ?? ''));
      }
    } catch (Throwable $e) {
      error_log('rateb_worker_column_types failed: ' . $e->getMessage());
    }

    return $cache[$table];
  }
}

if (!function_exists('rateb_worker_is_empty_db_value')) {
  function rateb_worker_is_empty_db_value($value): bool
  {
    if ($value === null) {
      return true;
    }
    if (!is_string($value)) {
      return false;
    }
    $trimmed = trim($value);
    return $trimmed === '' || $trimmed === '0000-00-00' || $trimmed === '0000-00-00 00:00:00';
  }
}

if (!function_exists('rateb_worker_should_nullify_empty_for_type')) {
  function rateb_worker_should_nullify_empty_for_type(string $columnType): bool
  {
    if ($columnType === '') {
      return false;
    }
    if (preg_match('/^(tiny|small|medium|big)?int|decimal|float|double|numeric|real|bit/', $columnType)) {
      return true;
    }
    if (preg_match('/^(date|datetime|timestamp|year)/', $columnType)) {
      return true;
    }
    return false;
  }
}

if (!function_exists('rateb_worker_sanitize_empty_db_values')) {
  /**
   * Convert empty strings on numeric/date columns to NULL so INSERT/UPDATE does not fail.
   *
   * @param array<string, mixed> $data
   * @return array<string, mixed>
   */
  function rateb_worker_sanitize_empty_db_values(array $data, PDO $conn, string $table = 'workers'): array
  {
    $types = rateb_worker_column_types($conn, $table);
    foreach ($data as $key => $value) {
      if (!rateb_worker_is_empty_db_value($value)) {
        continue;
      }
      $columnType = $types[$key] ?? '';
      if (rateb_worker_should_nullify_empty_for_type($columnType)) {
        $data[$key] = null;
      }
    }
    return $data;
  }
}
