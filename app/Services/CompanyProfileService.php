<?php
declare(strict_types=1);

namespace App\Services;

use Exception;
use mysqli;

final class CompanyProfileService
{
    public static function resolveCompanyName(?mysqli $primaryConn, ?mysqli $secondaryConn, string $fallback): string
    {
        foreach ([$primaryConn, $secondaryConn] as $conn) {
            if (!($conn instanceof mysqli)) {
                continue;
            }

            try {
                $stmt = $conn->prepare(
                    "SELECT config_value FROM system_config WHERE config_key = 'company_office_name' AND status = 'active' LIMIT 1"
                );
                if (!$stmt) {
                    continue;
                }

                $stmt->execute();
                $result = $stmt->get_result();
                $name = '';
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $name = trim((string) ($row['config_value'] ?? ''));
                }
                $stmt->close();

                if ($name !== '') {
                    return $name;
                }
            } catch (Exception $exception) {
                error_log('CompanyProfileService: ' . $exception->getMessage());
            }
        }

        return $fallback;
    }
}
