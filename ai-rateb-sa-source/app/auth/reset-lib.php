<?php
declare(strict_types=1);

function password_reset_deliver(string $email, string $token): bool
{
    unset($email, $token);
    return false;
}

function password_reset_limited(): bool
{
    $now = time();
    $recent = [];
    foreach ($_SESSION['password_reset_hits'] ?? [] as $hit) {
        if (is_int($hit) && $hit > $now - 900) {
            $recent[] = $hit;
        }
    }
    if (count($recent) >= 5) {
        $_SESSION['password_reset_hits'] = $recent;
        return true;
    }
    $recent[] = $now;
    $_SESSION['password_reset_hits'] = $recent;
    return false;
}

function password_reset_request(PDO $pdo, string $email): void
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || password_reset_limited()) {
        return;
    }
    $user = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $user->execute([$email]);
    $userId = (int) $user->fetchColumn();
    if ($userId < 1) {
        hash('sha256', random_bytes(32));
        return;
    }
    $recent = $pdo->prepare('SELECT id FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1');
    $recent->execute([$userId]);
    if ($recent->fetch()) {
        return;
    }
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);
    $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))')->execute([$userId, hash('sha256', $token)]);
    password_reset_deliver($email, $token);
}

function password_reset_find(PDO $pdo, string $token): ?array
{
    if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function password_reset_apply(PDO $pdo, string $token, string $password): bool
{
    if (strlen($password) < 8 || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        return false;
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1 FOR UPDATE');
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();
        if (!$row) {
            $pdo->rollBack();
            return false;
        }
        $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')->execute([
            password_hash($password, PASSWORD_DEFAULT),
            (int) $row['user_id'],
        ]);
        $used = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
        $used->execute([(int) $row['id']]);
        if ($used->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([(int) $row['user_id']]);
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}
