<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/hr-api-bootstrap.inc.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/hr-api-bootstrap.inc.php`.
 */
/**
 * Ratib Pro HR: session + host env before any output headers from these endpoints.
 * Include immediately after optional session_name('ratib_control') when ?control=1.
 */
require_once __DIR__ . '/../../config/env/load.php';
