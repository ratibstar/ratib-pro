<?php

declare(strict_types=1);

/**
 * Barrel include for offline models (optional). Prefer autoload of individual classes.
 */
require_once __DIR__ . '/OfflineSyncQueueItem.php';
require_once __DIR__ . '/OfflineSyncConflict.php';
require_once __DIR__ . '/OfflineEntityCursor.php';
require_once __DIR__ . '/OfflineDevice.php';
require_once __DIR__ . '/OfflineIdentityAudit.php';
require_once __DIR__ . '/OfflineIdentityNonce.php';
