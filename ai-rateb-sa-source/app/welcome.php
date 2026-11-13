<?php
declare(strict_types=1);

const RATEB_WELCOME_CREDITS = 50;

/**
 * Email grant hashes are HMAC-SHA-256 over the trimmed, lowercased address.
 * Secret: RATEB_WELCOME_HMAC_SECRET, or the file in RATEB_WELCOME_HMAC_SECRET_FILE.
 * There is no plain SHA-256 fallback.
 *
 * Migration (do not run against production from the application):
 * Rows written before HMAC used SHA-256(normalized email). Lookups match either
 * form, so an old row still blocks a second grant. Backfill only where the
 * user row still exists:
 *   UPDATE welcome_grants g JOIN users u ON u.id = g.user_id
 *   SET g.email_hash = HMAC_SHA256(lower(trim(u.email)))
 *   WHERE g.email_hash = SHA256(lower(trim(u.email)))
 * Rows whose user was deleted stay on SHA-256 and keep matching the legacy
 * comparison. Take a backup of email_hash first. Rollback is restoring that
 * backup. Do not delete unmatched legacy rows.
 */
function rateb_welcome_decision(array $signals): string
{
    if (!empty($signals['email_granted'])) {
        return 'duplicate';
    }
    if (!empty($signals['device_issued'])) {
        return 'review';
    }
    $priorGrants = (int) ($signals['device_grants'] ?? 0);
    $priorSignups = (int) ($signals['device_signups'] ?? 0);
    if ($priorGrants >= 2 || $priorSignups >= 2) {
        return 'review';
    }
    return 'grant';
}

function rateb_welcome_hmac_secret(): string
{
    $secret = getenv('RATEB_WELCOME_HMAC_SECRET');
    if (!is_string($secret) || trim($secret) === '') {
        $path = getenv('RATEB_WELCOME_HMAC_SECRET_FILE');
        if (is_string($path) && $path !== '' && is_file($path)) {
            $secret = (string) file_get_contents($path);
        }
    }
    $secret = trim((string) $secret);
    if ($secret === '') {
        throw new RuntimeException('Welcome HMAC secret is not configured.');
    }
    return $secret;
}

function rateb_welcome_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function rateb_welcome_email_hash(string $email): string
{
    return hash_hmac('sha256', rateb_welcome_normalize_email($email), rateb_welcome_hmac_secret());
}

function rateb_welcome_legacy_email_hash(string $email): string
{
    return hash('sha256', rateb_welcome_normalize_email($email));
}

function rateb_welcome_device(): array
{
    $token = (string) ($_COOKIE['rateb_device'] ?? '');
    $valid = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    if (!$valid) {
        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable) {
            return ['hash' => null, 'issued' => true];
        }
        setcookie('rateb_device', $token, [
            'expires' => time() + 86400 * 400,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['rateb_device'] = $token;
        return ['hash' => hash('sha256', $token), 'issued' => true];
    }
    return ['hash' => hash('sha256', $token), 'issued' => false];
}

function rateb_welcome_apply(PDO $pdo, int $userId, string $email, bool $emailVerified): void
{
    if ($userId < 1) {
        return;
    }
    try {
        $emailHash = rateb_welcome_email_hash($email);
    } catch (RuntimeException $error) {
        rateb_welcome_note_failure($pdo, $userId, hash('sha256', 'welcome-unconfigured:' . $userId), null, $emailVerified);
        throw $error;
    }
    $legacyHash = rateb_welcome_legacy_email_hash($email);
    $device = rateb_welcome_device();
    $deviceHash = $device['hash'];
    $issued = (bool) $device['issued'];
    $lockName = is_string($deviceHash) ? 'welcome-' . substr($deviceHash, 0, 48) : null;
    $lockHeld = false;
    if ($lockName !== null) {
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
        $lock->execute([$lockName]);
        $lockHeld = (int) $lock->fetchColumn() === 1;
    }
    try {
        $pdo->beginTransaction();
        $own = $pdo->prepare('SELECT status FROM welcome_grants WHERE user_id = ? LIMIT 1 FOR UPDATE');
        $own->execute([$userId]);
        $ownStatus = $own->fetchColumn();
        if ($ownStatus === 'granted' || $ownStatus === 'review') {
            $pdo->commit();
            return;
        }
        $previous = $pdo->prepare("SELECT decision FROM signup_audit WHERE user_id = ? AND decision IN ('duplicate', 'review', 'grant') ORDER BY id DESC LIMIT 1");
        $previous->execute([$userId]);
        $previousDecision = $previous->fetchColumn();
        if ($previousDecision !== false) {
            $pdo->commit();
            return;
        }
        $taken = $pdo->prepare('SELECT user_id, status FROM welcome_grants WHERE email_hash IN (?, ?) LIMIT 1 FOR UPDATE');
        $taken->execute([$emailHash, $legacyHash]);
        $emailRow = $taken->fetch();
        if ($emailRow && (int) $emailRow['user_id'] !== $userId) {
            rateb_welcome_insert_audit($pdo, $userId, $emailHash, $deviceHash, $emailVerified, 'duplicate', 'email_already_granted', null);
            $pdo->commit();
            return;
        }
        $deviceGrants = 0;
        $deviceSignups = 0;
        if (is_string($deviceHash)) {
            $grants = $pdo->prepare("SELECT COUNT(*) FROM welcome_grants WHERE device_hash = ? AND status = 'granted' AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)");
            $grants->execute([$deviceHash]);
            $deviceGrants = (int) $grants->fetchColumn();
            $signups = $pdo->prepare('SELECT COUNT(*) FROM signup_audit WHERE device_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
            $signups->execute([$deviceHash]);
            $deviceSignups = (int) $signups->fetchColumn();
        }
        $decision = rateb_welcome_decision([
            'email_granted' => false,
            'device_issued' => $issued || ($lockName !== null && !$lockHeld) || $deviceHash === null,
            'device_grants' => $deviceGrants,
            'device_signups' => $deviceSignups,
        ]);
        $credits = 0;
        if ($decision === 'grant') {
            $pdo->prepare('INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted, idempotency_key) VALUES (?, NULL, \'welcome\', 1, \'completed\', \'welcome\', 0, ?)')->execute([$userId, 'welcome:' . $userId]);
            $pdo->prepare('UPDATE users SET credits_balance = credits_balance + ? WHERE id = ?')->execute([RATEB_WELCOME_CREDITS, $userId]);
            $credits = RATEB_WELCOME_CREDITS;
        }
        $pdo->prepare('INSERT INTO welcome_grants (user_id, email_hash, device_hash, status, credits) VALUES (?, ?, ?, ?, ?)')->execute([
            $userId,
            $emailHash,
            $deviceHash,
            $decision === 'grant' ? 'granted' : 'review',
            $credits,
        ]);
        $detail = $emailVerified ? 'email_verified' : 'email_unverified';
        if ($issued || $deviceHash === null) {
            $detail .= ';device_issued';
        }
        if ($deviceGrants >= 2) {
            $detail .= ';repeated_grant';
        }
        if ($deviceSignups >= 2) {
            $detail .= ';repeated_registration';
        }
        rateb_welcome_insert_audit($pdo, $userId, $emailHash, $deviceHash, $emailVerified, $decision, $detail, null);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = $error->getMessage();
        $duplicate = str_contains($message, 'UNIQUE') || str_contains($message, 'Duplicate') || str_contains($message, 'constraint');
        if ($duplicate) {
            rateb_welcome_note_conflict($pdo, $userId, $emailHash, $deviceHash, $emailVerified);
            return;
        }
        rateb_welcome_note_failure($pdo, $userId, $emailHash, $deviceHash, $emailVerified);
        throw $error;
    } finally {
        if ($lockHeld && $lockName !== null) {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
    }
}

function rateb_welcome_recover(PDO $pdo, int $userId, string $email, bool $emailVerified): void
{
    if ($userId < 1) {
        return;
    }
    $failed = $pdo->prepare("SELECT id FROM signup_audit WHERE user_id = ? AND decision = 'failure' LIMIT 1");
    $failed->execute([$userId]);
    if ($failed->fetchColumn() === false) {
        return;
    }
    $done = $pdo->prepare('SELECT user_id FROM welcome_grants WHERE user_id = ? LIMIT 1');
    $done->execute([$userId]);
    if ($done->fetchColumn() !== false) {
        return;
    }
    rateb_welcome_apply($pdo, $userId, $email, $emailVerified);
}

function rateb_welcome_release(PDO $pdo, int $userId, int $adminId): bool
{
    if ($userId < 1 || $adminId < 1) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        $row = $pdo->prepare('SELECT email_hash, device_hash, status FROM welcome_grants WHERE user_id = ? LIMIT 1 FOR UPDATE');
        $row->execute([$userId]);
        $grant = $row->fetch();
        if (!$grant || (string) $grant['status'] !== 'review') {
            $pdo->commit();
            return false;
        }
        $other = $pdo->prepare("SELECT user_id FROM welcome_grants WHERE email_hash = ? AND user_id <> ? AND status = 'granted' LIMIT 1 FOR UPDATE");
        $other->execute([(string) $grant['email_hash'], $userId]);
        if ($other->fetchColumn() !== false) {
            $pdo->commit();
            return false;
        }
        $pdo->prepare('INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted, idempotency_key) VALUES (?, NULL, \'welcome\', 1, \'completed\', \'welcome\', 0, ?)')->execute([$userId, 'welcome:' . $userId]);
        $pdo->prepare('UPDATE users SET credits_balance = credits_balance + ? WHERE id = ?')->execute([RATEB_WELCOME_CREDITS, $userId]);
        $updated = $pdo->prepare("UPDATE welcome_grants SET status = 'granted', credits = ? WHERE user_id = ? AND status = 'review'");
        $updated->execute([RATEB_WELCOME_CREDITS, $userId]);
        if ($updated->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }
        rateb_welcome_insert_audit($pdo, $userId, (string) $grant['email_hash'], $grant['device_hash'] !== null ? (string) $grant['device_hash'] : null, false, 'grant', 'manual_release', $adminId);
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = $error->getMessage();
        if (str_contains($message, 'UNIQUE') || str_contains($message, 'Duplicate') || str_contains($message, 'constraint')) {
            return false;
        }
        throw $error;
    }
}

function rateb_welcome_insert_audit(PDO $pdo, int $userId, string $emailHash, ?string $deviceHash, bool $emailVerified, string $decision, string $detail, ?int $adminId): void
{
    $pdo->prepare('INSERT INTO signup_audit (user_id, email_hash, device_hash, email_verified, decision, detail, admin_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
        $userId,
        $emailHash,
        $deviceHash,
        $emailVerified ? 1 : 0,
        $decision,
        $detail,
        $adminId,
    ]);
}

function rateb_welcome_note_conflict(PDO $pdo, int $userId, string $emailHash, ?string $deviceHash, bool $emailVerified): void
{
    try {
        $pdo->beginTransaction();
        $own = $pdo->prepare('SELECT status FROM welcome_grants WHERE user_id = ? LIMIT 1');
        $own->execute([$userId]);
        if ($own->fetchColumn() !== false) {
            $pdo->commit();
            return;
        }
        rateb_welcome_insert_audit($pdo, $userId, $emailHash, $deviceHash, $emailVerified, 'duplicate', 'email_already_granted', null);
        $pdo->commit();
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

function rateb_welcome_note_failure(PDO $pdo, int $userId, string $emailHash, ?string $deviceHash, bool $emailVerified): void
{
    try {
        $pdo->beginTransaction();
        rateb_welcome_insert_audit($pdo, $userId, $emailHash, $deviceHash, $emailVerified, 'failure', 'grant_failed', null);
        $pdo->commit();
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
