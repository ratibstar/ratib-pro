<?php
declare(strict_types=1);

namespace Rateb\App\Helpers;

use Rateb\App\Core\Auth;

final class ProcurementNotes
{
    /** @return array<int, array<string, mixed>> */
    public static function templates(): array
    {
        return [
            ['key' => 'urgent', 'color' => 'danger', 'text' => __('note_tpl_urgent')],
            ['key' => 'normal', 'color' => 'success', 'text' => __('note_tpl_normal')],
            ['key' => 'new_supplier', 'color' => 'primary', 'text' => __('note_tpl_new_supplier')],
            ['key' => 'annual_contract', 'color' => 'warning', 'text' => __('note_tpl_annual_contract')],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function decodeHistory(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<int, array<string, mixed>> $history */
    public static function encodeHistory(array $history): ?string
    {
        if ($history === []) {
            return null;
        }
        return json_encode($history, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<int, array<string, mixed>> $existing
     * @return array<int, array<string, mixed>>
     */
    public static function appendHistory(array $existing, string $oldNotes, string $newNotes): array
    {
        $old = trim($oldNotes);
        $new = trim($newNotes);
        if ($old === $new) {
            return $existing;
        }
        $user = Auth::user();
        $entry = [
            'at' => date('Y-m-d H:i:s'),
            'by' => (string) ($user['name'] ?? $user['email'] ?? '—'),
            'from' => $old,
            'to' => $new,
        ];
        array_unshift($existing, $entry);
        return array_slice($existing, 0, 50);
    }
}
