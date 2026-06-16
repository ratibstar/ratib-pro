<?php
/**
 * EN: Handles API endpoint/business logic in `api/settings/currencies-api.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/settings/currencies-api.php`.
 */
/**
 * Currencies API - Fetch currencies list for forms
 * Uses mysqli + env only (no PDO Database class) for maximum server compatibility.
 */
// EN: Currency list endpoint enforces clean JSON responses with logged-only errors.
// AR: نقطة جلب العملات تضمن استجابات JSON نظيفة مع تسجيل الأخطاء فقط.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=UTF-8');

/**
 * @param array $data
 */
// EN: Unified response writer with UTF-8 safe JSON handling.
// AR: دالة استجابة موحّدة مع معالجة آمنة لـ JSON و UTF-8.
function currencies_api_send_json($data, $httpCode = 200)
{
    http_response_code($httpCode);
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($data, $flags);
    if ($json === false) {
        $json = '{"success":false,"message":"JSON encode failed"}';
    }
    echo $json;
    exit;
}

/**
 * Load DB_* constants without pulling in api/core/Database.php (avoids PDO/bootstrap issues).
 */
// EN: Load DB env constants without invoking heavier bootstrap dependencies.
// AR: تحميل ثوابت قاعدة البيانات بدون استدعاء مسارات تهيئة ثقيلة.
function currencies_api_bootstrap_env()
{
    if (defined('DB_NAME') && DB_NAME !== '') {
        return;
    }
    $load = __DIR__ . '/../../config/env/load.php';
    if (is_file($load)) {
        require_once $load;
    }
    if (!defined('DB_NAME') || DB_NAME === '') {
        $def = __DIR__ . '/../../config/env/default.php';
        if (is_file($def)) {
            require_once $def;
        }
    }
}

/**
 * @return array{conn: mysqli|null, owned: bool}
 */
// EN: Prefer tenant-aware app connection; fallback to env mysqli connection.
// AR: تفضيل اتصال التطبيق الخاص بالوكالة/المستأجر ثم الرجوع لاتصال env عند الحاجة.
function currencies_api_connect_mysqli()
{
    $singleUrlMode = defined('SINGLE_URL_MODE') && SINGLE_URL_MODE;
    // 1) Try main app bootstrap first (can select agency-specific DB in multi-DB setups).
    $cfg = __DIR__ . '/../../includes/config.php';
    if (is_file($cfg)) {
        try {
            require_once $cfg;
            if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
                $GLOBALS['conn']->set_charset('utf8mb4');
                return array('conn' => $GLOBALS['conn'], 'owned' => false);
            }
        } catch (Throwable $e) {
            error_log('currencies-api config bootstrap failed: ' . $e->getMessage());
        }
    }

    // 2) Fallback to env constants.
    if ($singleUrlMode) {
        // In single URL tenant mode, never bypass tenant bootstrap with a direct DB_* connection.
        return array('conn' => null, 'owned' => false);
    }
    currencies_api_bootstrap_env();
    if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_NAME')) {
        return array('conn' => null, 'owned' => false);
    }
    $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
    $pass = defined('DB_PASS') ? DB_PASS : '';
    mysqli_report(MYSQLI_REPORT_OFF);
    $m = @new mysqli(DB_HOST, DB_USER, $pass, DB_NAME, $port);
    if ($m->connect_error) {
        error_log('currencies-api mysqli: ' . $m->connect_error);
        return array('conn' => null, 'owned' => false);
    }
    $m->set_charset('utf8mb4');
    return array('conn' => $m, 'owned' => true);
}

/**
 * @param array $row
 * @return array|null
 */
function currencies_api_normalize_row($row)
{
    $code = isset($row['code']) ? (string) $row['code'] : '';
    $name = isset($row['name']) ? (string) $row['name'] : '';
    if ($code === '' && isset($row['currency_code'])) {
        $code = (string) $row['currency_code'];
    }
    if ($name === '' && isset($row['currency_name'])) {
        $name = (string) $row['currency_name'];
    }
    $sym = isset($row['symbol']) ? (string) $row['symbol'] : '';
    if ($code === '' || $name === '') {
        return null;
    }
    return array(
        'code' => $code,
        'name' => $name,
        'symbol' => $sym,
        'label' => $code . ' - ' . $name,
    );
}

// EN: Authorize caller, ensure currencies table/seeds, then return normalized currency list.
// AR: التحقق من هوية المستخدم، ضمان جدول العملات وبذوره، ثم إرجاع قائمة موحدة.
try {
    require_once __DIR__ . '/../core/rateb_api_session.inc.php';
    rateb_api_pick_session_name();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $isControl = !empty($_SESSION['control_logged_in']);
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    $uname = isset($_SESSION['username']) && is_string($_SESSION['username']) ? $_SESSION['username'] : '';
    $isAppUser = !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true
        && $uid >= 1
        && ($uname === '' || strncmp($uname, 'Control:', 8) !== 0);
    if (!$isControl && !$isAppUser) {
        currencies_api_send_json(array('success' => false, 'message' => 'Unauthorized'), 401);
    }

    $connMeta = currencies_api_connect_mysqli();
    $conn = isset($connMeta['conn']) ? $connMeta['conn'] : null;
    $ownedConn = !empty($connMeta['owned']);
    if (!$conn instanceof mysqli) {
        currencies_api_send_json(array('success' => false, 'message' => 'Database connection failed'), 500);
    }

    $tableRes = $conn->query("SHOW TABLES LIKE 'currencies'");
    $tableExists = ($tableRes && $tableRes->num_rows > 0);

    if (!$tableExists) {
        $createTable = "CREATE TABLE IF NOT EXISTS currencies (
            id INT PRIMARY KEY AUTO_INCREMENT,
            code VARCHAR(3) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            symbol VARCHAR(10) NULL,
            is_active TINYINT(1) DEFAULT 1,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_code (code),
            INDEX idx_active (is_active),
            INDEX idx_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$conn->query($createTable)) {
            error_log('currencies-api CREATE: ' . $conn->error);
        }

        $defaultCurrencies = array(
            array('SAR', 'Saudi Riyal', '﷼', 1),
            array('USD', 'US Dollar', '$', 2),
            array('EUR', 'Euro', '€', 3),
            array('GBP', 'British Pound', '£', 4),
            array('CAD', 'Canadian Dollar', 'C$', 5),
            array('AUD', 'Australian Dollar', 'A$', 6),
            array('AED', 'UAE Dirham', 'د.إ', 7),
            array('KWD', 'Kuwaiti Dinar', 'د.ك', 8),
            array('QAR', 'Qatari Riyal', '﷼', 9),
            array('BHD', 'Bahraini Dinar', '.د.ب', 10),
            array('OMR', 'Omani Rial', 'ر.ع.', 11),
            array('JOD', 'Jordanian Dinar', 'د.ا', 12),
            array('EGP', 'Egyptian Pound', '£', 13),
            array('JPY', 'Japanese Yen', '¥', 14),
            array('CNY', 'Chinese Yuan', '¥', 15),
            array('INR', 'Indian Rupee', '₹', 16),
            array('PKR', 'Pakistani Rupee', '₨', 17),
            array('BDT', 'Bangladeshi Taka', '৳', 18),
            array('PHP', 'Philippine Peso', '₱', 19),
            array('IDR', 'Indonesian Rupiah', 'Rp', 20),
            array('THB', 'Thai Baht', '฿', 21),
            array('MYR', 'Malaysian Ringgit', 'RM', 22),
            array('SGD', 'Singapore Dollar', 'S$', 23),
            array('KRW', 'South Korean Won', '₩', 24),
            array('BRL', 'Brazilian Real', 'R$', 25),
            array('MXN', 'Mexican Peso', '$', 26),
            array('TRY', 'Turkish Lira', '₺', 27),
            array('ZAR', 'South African Rand', 'R', 28)
        );
        $ins = $conn->prepare('INSERT IGNORE INTO currencies (code, name, symbol, display_order) VALUES (?, ?, ?, ?)');
        if ($ins) {
            foreach ($defaultCurrencies as $currency) {
                $ins->bind_param('sssi', $currency[0], $currency[1], $currency[2], $currency[3]);
                $ins->execute();
            }
            $ins->close();
        }
    }

    $cols = array();
    $colRes = $conn->query('SHOW COLUMNS FROM `currencies`');
    if (!$colRes) {
        throw new RuntimeException('Cannot read currencies columns: ' . $conn->error);
    }
    while ($r = $colRes->fetch_assoc()) {
        $cols[strtolower($r['Field'])] = true;
    }

    $activeOnly = !isset($_GET['all']) || $_GET['all'] !== 'true';

    $tryQueries = array();

    if (!empty($cols['code']) && !empty($cols['name'])) {
        $select = array('`code`', '`name`');
        if (!empty($cols['symbol'])) {
            $select[] = '`symbol`';
        } else {
            $select[] = "'' AS `symbol`";
        }
        $where = '';
        if ($activeOnly) {
            if (!empty($cols['is_active'])) {
                $where = ' WHERE (`is_active` = 1 OR `is_active` = \'1\') ';
            } elseif (!empty($cols['status'])) {
                $where = " WHERE (LOWER(TRIM(COALESCE(`status`, ''))) = 'active' OR `status` = '1' OR `status` = 1) ";
            }
        }
        $order = !empty($cols['display_order']) ? '`display_order` ASC, `code` ASC' : '`code` ASC';
        $tryQueries[] = 'SELECT ' . implode(', ', $select) . ' FROM `currencies`' . $where . ' ORDER BY ' . $order;
    }

    if (!empty($cols['currency_code']) && !empty($cols['currency_name'])) {
        $selSym = !empty($cols['symbol']) ? '`symbol`' : "'' AS `symbol`";
        $w = '';
        if ($activeOnly && !empty($cols['is_active'])) {
            $w = ' WHERE (`is_active` = 1 OR `is_active` = \'1\') ';
        }
        $tryQueries[] = 'SELECT `currency_code` AS `code`, `currency_name` AS `name`, ' . $selSym . ' FROM `currencies`' . $w . ' ORDER BY `currency_code` ASC';
    }

    // Never fall back to "all currencies" when caller asked for active only — that mislabels dashboards
    // (inactive rows still in the table) and breaks default-currency resolution on the frontend.
    if (!$activeOnly) {
        $tryQueries[] = 'SELECT `code`, `name`, `symbol` FROM `currencies` ORDER BY `code` ASC';
    }

    $currencies = array();
    foreach ($tryQueries as $sql) {
        $stmt = $conn->query($sql);
        if (!$stmt) {
            error_log('currencies-api query err: ' . $conn->error . ' | ' . $sql);
            continue;
        }
        while ($row = $stmt->fetch_assoc()) {
            $n = currencies_api_normalize_row($row);
            if ($n !== null) {
                $currencies[] = $n;
            }
        }
        $stmt->free();
        if (count($currencies) > 0) {
            break;
        }
    }

    if ($ownedConn) {
        $conn->close();
    }

    currencies_api_send_json(array('success' => true, 'currencies' => $currencies));
} catch (Exception $e) {
    error_log('currencies-api: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    currencies_api_send_json(array(
        'success' => false,
        'message' => 'Failed to fetch currencies: ' . $e->getMessage(),
    ), 500);
}
