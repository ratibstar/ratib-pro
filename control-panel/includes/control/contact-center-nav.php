<?php
declare(strict_types=1);

require_once __DIR__ . '/contact-center-bridge.php';

function control_contact_center_active_key(): string
{
    $self = basename($_SERVER['PHP_SELF'] ?? '');
    if ($self === 'contact-center.php') {
        return 'hub';
    }
    if ($self === 'contact-center-migrate.php') {
        return 'migrate';
    }
    if ($self === 'contact-center-app.php') {
        return trim((string) ($_GET['route'] ?? 'agent-desktop'), '/');
    }
    if ($self === 'contact-center-ops.php') {
        return trim((string) ($_GET['route'] ?? 'health'), '/');
    }
    return '';
}
