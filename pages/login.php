<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/login.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/login.php`.
 */
require_once '../includes/config.php';
$error = '';
$success_message = '';

// Login page authenticates with users table credentials.
// Control SSO (logged-in control panel + ?control=1&agency_id=) is handled in includes/config.php before this file runs.

$conn = $GLOBALS['conn'] ?? null;
if ($conn === null || !isset($GLOBALS['conn'])) {
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $conn->set_charset("utf8mb4");
        $GLOBALS['conn'] = $conn;
    } catch (Exception $e) {
        error_log("Login - Failed to create database connection: " . $e->getMessage());
        $error = 'Database connection failed. Please contact administrator.';
        $conn = null;
    }
}

if (isset($_GET['message']) && $_GET['message'] === 'logged_out') {
    $success_message = 'You have been successfully logged out.';
}

// Fetch all countries for login page (agency login only - country selector)
// In SINGLE_URL_MODE use control panel DB so one source of truth; each country uses its own DB
$loginCountries = [];
$loginAgencyPicklist = []; // SINGLE_URL_MODE: rows agency_id, agency_name, country_id, country_name, country_slug
$loginCountryId = null;
$loginCountryName = null;
$singleCountryFromPath = false; // true when /bangladesh/login, /kenya/login, etc.
$countryListConn = $conn;
if (function_exists('get_control_lookup_conn') && defined('SINGLE_URL_MODE') && SINGLE_URL_MODE) {
    $ctrlLookup = get_control_lookup_conn();
    if ($ctrlLookup instanceof mysqli) {
        $countryListConn = $ctrlLookup;
    }
}
if ($countryListConn instanceof mysqli) {
    try {
        $db = $countryListConn;
        // Path-based country: /bangladesh/login, /kenya/login → show only that country, no dropdown
        $urlCountrySlug = isset($_GET['country_slug']) ? trim(preg_replace('/[^a-z0-9_-]/', '', strtolower($_GET['country_slug']))) : '';
        if ($urlCountrySlug !== '') {
            $chk = @$db->query("SHOW TABLES LIKE 'control_countries'");
            if ($chk && $chk->num_rows > 0) {
                $colActive = @$db->query("SHOW COLUMNS FROM control_countries LIKE 'is_active'");
                $whereSlug = $colActive && $colActive->num_rows > 0 ? "WHERE LOWER(TRIM(slug)) = ? AND is_active = 1" : "WHERE LOWER(TRIM(slug)) = ?";
                $slugAlt = str_replace('-', '_', $urlCountrySlug);
                $stmtSlug = @$db->prepare("SELECT id, name, slug FROM control_countries $whereSlug LIMIT 1");
                if ($stmtSlug) {
                    foreach ([$urlCountrySlug, str_replace('-', '_', $urlCountrySlug), str_replace('_', '-', $urlCountrySlug)] as $trySlug) {
                        $stmtSlug->bind_param("s", $trySlug);
                        $stmtSlug->execute();
                        $resSlug = $stmtSlug->get_result();
                        if ($resSlug && $resSlug->num_rows > 0) {
                            $rowSlug = $resSlug->fetch_assoc();
                            $loginCountryId = (int)$rowSlug['id'];
                            $loginCountryName = trim($rowSlug['name'] ?? '') ?: null;
                            $loginCountries = [['id' => $loginCountryId, 'name' => $loginCountryName, 'slug' => $rowSlug['slug'] ?? '']];
                            $singleCountryFromPath = true;
                            break;
                        }
                    }
                    $stmtSlug->close();
                }
                if ($urlCountrySlug !== '' && !$singleCountryFromPath) {
                    $error = 'Country not found. Please use a valid login link.';
                }
            }
        }
        // Pre-select country from URL (e.g. when redirecting from Ratib Pro logout) — only if not from path
        if (!$singleCountryFromPath) {
        $urlCountryId = isset($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
        if ($urlCountryId > 0) {
            $colActive = @$db->query("SHOW COLUMNS FROM control_countries LIKE 'is_active'");
            $whereUrl = $colActive && $colActive->num_rows > 0 ? "WHERE id = ? AND is_active = 1" : "WHERE id = ?";
            $stmtUrl = @$db->prepare("SELECT id, name FROM control_countries $whereUrl LIMIT 1");
            if ($stmtUrl) {
                $stmtUrl->bind_param("i", $urlCountryId);
                $stmtUrl->execute();
                $resUrl = $stmtUrl->get_result();
                if ($resUrl && $resUrl->num_rows > 0) {
                    $rowUrl = $resUrl->fetch_assoc();
                    $loginCountryId = (int)$rowUrl['id'];
                    $loginCountryName = trim($rowUrl['name'] ?? '') ?: null;
                }
                $stmtUrl->close();
            }
        }
        $chk = @$db->query("SHOW TABLES LIKE 'control_countries'");
        if ($chk && $chk->num_rows > 0) {
            $colActive = @$db->query("SHOW COLUMNS FROM control_countries LIKE 'is_active'");
            $where = $colActive && $colActive->num_rows > 0 ? "WHERE is_active = 1" : "";
            $colSortC = @$db->query("SHOW COLUMNS FROM control_countries LIKE 'sort_order'");
            $order = ($colSortC && $colSortC->num_rows > 0) ? 'ORDER BY sort_order ASC, name ASC' : 'ORDER BY name ASC';
            $res = $db->query("SELECT id, name, slug FROM control_countries $where $order");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $loginCountries[] = ['id' => (int)$row['id'], 'name' => trim($row['name'] ?? ''), 'slug' => $row['slug'] ?? ''];
                }
                $res->free();
            }
        }
        // Pre-select from SITE_URL or TENANT (skip if already set from URL e.g. Ratib Pro logout)
        if (!$loginCountryId && defined('TENANT_ID') && TENANT_ID > 0) {
            $loginCountryId = TENANT_ID;
            $loginCountryName = defined('TENANT_NAME') ? TENANT_NAME : null;
        } elseif (!$loginCountryId) {
            $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
            if ($siteUrl !== '' && $db instanceof mysqli) {
                $chk = @$db->query("SHOW TABLES LIKE 'control_agencies'");
                if ($chk && $chk->num_rows > 0) {
                    $colChk = @$db->query("SHOW COLUMNS FROM control_agencies LIKE 'country_id'");
                    if ($colChk && $colChk->num_rows > 0) {
                        $suspA = ratib_control_agency_active_fragment($db, 'a');
                        $stmtC = $db->prepare("SELECT c.id, c.name FROM control_agencies a LEFT JOIN control_countries c ON a.country_id = c.id WHERE (a.site_url = ? OR a.site_url = ? OR a.site_url LIKE ?) AND a.is_active = 1 AND {$suspA} LIMIT 1");
                        if ($stmtC) {
                            $url1 = $siteUrl;
                            $url2 = $siteUrl . '/';
                            $url3 = $siteUrl . '/%';
                            $stmtC->bind_param("sss", $url1, $url2, $url3);
                            $stmtC->execute();
                            $resC = $stmtC->get_result();
                            if ($resC && $resC->num_rows > 0) {
                                $rowC = $resC->fetch_assoc();
                                $loginCountryId = (int)($rowC['id'] ?? 0);
                                $loginCountryName = trim($rowC['name'] ?? '') ?: null;
                            }
                            $stmtC->close();
                        }
                    }
                }
            }
        }
        // When only one country, pre-select it
        if (count($loginCountries) === 1 && !$loginCountryId) {
            $loginCountryId = (int)$loginCountries[0]['id'];
            $loginCountryName = $loginCountries[0]['name'];
        }
        } // end if (!$singleCountryFromPath)

        if (defined('SINGLE_URL_MODE') && SINGLE_URL_MODE) {
            $chkAg = @$db->query("SHOW TABLES LIKE 'control_agencies'");
            $chkCo = @$db->query("SHOW TABLES LIKE 'control_countries'");
            if ($chkAg && $chkAg->num_rows > 0 && $chkCo && $chkCo->num_rows > 0) {
                $colCActive = @$db->query("SHOW COLUMNS FROM control_countries LIKE 'is_active'");
                $cActiveSql = ($colCActive && $colCActive->num_rows > 0) ? ' AND c.is_active = 1' : '';
                $suspPick = ratib_control_agency_active_fragment($db, 'a');
                $resAg = @$db->query(
                    'SELECT a.id AS agency_id, a.name AS agency_name, a.country_id, c.name AS country_name, c.slug AS country_slug '
                    . 'FROM control_agencies a INNER JOIN control_countries c ON c.id = a.country_id '
                    . 'WHERE a.is_active = 1 AND ' . $suspPick . $cActiveSql
                    . ' ORDER BY c.name ASC, a.name ASC, a.id ASC'
                );
                if ($resAg) {
                    while ($r = $resAg->fetch_assoc()) {
                        $loginAgencyPicklist[] = [
                            'agency_id' => (int)($r['agency_id'] ?? 0),
                            'agency_name' => trim($r['agency_name'] ?? ''),
                            'country_id' => (int)($r['country_id'] ?? 0),
                            'country_name' => trim($r['country_name'] ?? ''),
                            'country_slug' => trim($r['country_slug'] ?? ''),
                        ];
                    }
                    $resAg->free();
                }
            }
        }
    } catch (Throwable $e) { /* ignore */ }
}

// No visible country picker; agency dropdown only when several agencies share one country and none is pre-filled
$formHiddenAgencyId = 0;
$formHiddenCountryId = 0;
$agencySelectOptions = [];
$loginPrefillFromCookie = isset($_GET['message']) && (string)$_GET['message'] === 'logged_out';
$urlAgencyIdPre = isset($_GET['agency_id']) ? (int)$_GET['agency_id'] : 0;
if ($urlAgencyIdPre <= 0 && $loginPrefillFromCookie && !empty($_COOKIE['ratib_last_agency_id']) && ctype_digit((string)$_COOKIE['ratib_last_agency_id'])) {
    $urlAgencyIdPre = (int)$_COOKIE['ratib_last_agency_id'];
}
$urlCountryIdPre = isset($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
if ($urlCountryIdPre <= 0 && $loginPrefillFromCookie && !empty($_COOKIE['ratib_last_country_id']) && ctype_digit((string)$_COOKIE['ratib_last_country_id'])) {
    $urlCountryIdPre = (int)$_COOKIE['ratib_last_country_id'];
}
if ($singleCountryFromPath && $loginCountryId > 0) {
    $formHiddenCountryId = (int)$loginCountryId;
}
if ($urlAgencyIdPre > 0 && !empty($loginAgencyPicklist)) {
    foreach ($loginAgencyPicklist as $ar) {
        if ((int)$ar['agency_id'] !== $urlAgencyIdPre) {
            continue;
        }
        // Control "Open" uses ?agency_id= — that wins over slug/country mismatches (do not skip the row).
        $formHiddenAgencyId = $urlAgencyIdPre;
        if ($formHiddenCountryId <= 0) {
            $formHiddenCountryId = (int)($ar['country_id'] ?? 0);
        }
        if (($ar['country_name'] ?? '') !== '') {
            $loginCountryName = $ar['country_name'];
        }
        break;
    }
}
// Agency in URL but not in picklist (e.g. new row) — resolve from control_agencies so the form + POST use the right tenant
if ($urlAgencyIdPre > 0 && $formHiddenAgencyId <= 0 && $countryListConn instanceof mysqli) {
    $lu = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
    $lookupAg = ($lu instanceof mysqli) ? $lu : $countryListConn;
    $chkA = @$lookupAg->query("SHOW TABLES LIKE 'control_agencies'");
    if ($chkA && $chkA->num_rows > 0 && function_exists('ratib_control_agency_active_fragment')) {
        $suspA = ratib_control_agency_active_fragment($lookupAg, 'a');
        $stA = @$lookupAg->prepare(
            "SELECT a.id, a.country_id, c.name AS country_name FROM control_agencies a "
            . "LEFT JOIN control_countries c ON c.id = a.country_id WHERE a.id = ? AND a.is_active = 1 AND {$suspA} LIMIT 1"
        );
        if ($stA) {
            $stA->bind_param('i', $urlAgencyIdPre);
            $stA->execute();
            $rAg = $stA->get_result();
            $rowAg = $rAg ? $rAg->fetch_assoc() : null;
            $stA->close();
            if ($rowAg) {
                $formHiddenAgencyId = $urlAgencyIdPre;
                if ($formHiddenCountryId <= 0) {
                    $formHiddenCountryId = (int)($rowAg['country_id'] ?? 0);
                }
                $cn = trim((string)($rowAg['country_name'] ?? ''));
                if ($cn !== '') {
                    $loginCountryName = $cn;
                }
            }
        }
    }
}
if ($formHiddenAgencyId <= 0 && $urlCountryIdPre > 0) {
    if (!empty($loginAgencyPicklist)) {
        foreach ($loginAgencyPicklist as $ar) {
            if ((int)$ar['country_id'] === $urlCountryIdPre) {
                $formHiddenCountryId = $urlCountryIdPre;
                break;
            }
        }
    } elseif (!empty($loginCountries)) {
        foreach ($loginCountries as $c) {
            if ((int)$c['id'] === $urlCountryIdPre) {
                $formHiddenCountryId = $urlCountryIdPre;
                break;
            }
        }
    }
}

// Path or ?country_id= with exactly one agency for that country: include agency in form so POST hits the right DB
if ($formHiddenCountryId > 0 && $formHiddenAgencyId <= 0 && !empty($loginAgencyPicklist)) {
    $singleAgId = 0;
    foreach ($loginAgencyPicklist as $ar) {
        if ((int)($ar['country_id'] ?? 0) === $formHiddenCountryId) {
            if ($singleAgId > 0) {
                $singleAgId = 0;
                break;
            }
            $singleAgId = (int)($ar['agency_id'] ?? 0);
        }
    }
    if ($singleAgId > 0) {
        $formHiddenAgencyId = $singleAgId;
    }
}
if (defined('SINGLE_URL_MODE') && SINGLE_URL_MODE && $formHiddenCountryId > 0 && $formHiddenAgencyId <= 0 && !empty($loginAgencyPicklist)) {
    foreach ($loginAgencyPicklist as $ar) {
        if ((int)($ar['country_id'] ?? 0) === $formHiddenCountryId) {
            $agencySelectOptions[] = $ar;
        }
    }
    if (count($agencySelectOptions) <= 1) {
        $agencySelectOptions = [];
    }
}
// Specific ?agency_id= in URL: never force a multi-agency dropdown for that request (avoid empty POST agency_id).
if ($urlAgencyIdPre > 0 && $formHiddenAgencyId > 0) {
    $agencySelectOptions = [];
}

// One line under "Login": "Ratib Pro — {name} — sign in..." for path URLs, /pages/login.php + cookies, tenant, etc.
$loginSubtitleName = null;
if ($singleCountryFromPath && $loginCountryName) {
    $loginSubtitleName = trim($loginCountryName);
} elseif ($formHiddenAgencyId > 0 && !empty($loginAgencyPicklist)) {
    foreach ($loginAgencyPicklist as $ar) {
        if ((int)$ar['agency_id'] === $formHiddenAgencyId) {
            $an = trim($ar['agency_name'] ?? '');
            $cn = trim($ar['country_name'] ?? '');
            $loginSubtitleName = $an !== '' ? $an : ($cn !== '' ? $cn : null);
            break;
        }
    }
}
if ($loginSubtitleName === null && $loginCountryName) {
    $loginSubtitleName = trim($loginCountryName);
}
if ($loginSubtitleName === null && $formHiddenCountryId > 0 && !empty($loginCountries)) {
    foreach ($loginCountries as $c) {
        if ((int)$c['id'] === $formHiddenCountryId && trim($c['name'] ?? '') !== '') {
            $loginSubtitleName = trim($c['name']);
            break;
        }
    }
}
if ($loginSubtitleName === null && $formHiddenCountryId > 0 && !empty($loginAgencyPicklist)) {
    foreach ($loginAgencyPicklist as $ar) {
        if ((int)$ar['country_id'] === $formHiddenCountryId) {
            $cn = trim($ar['country_name'] ?? '');
            $an = trim($ar['agency_name'] ?? '');
            $loginSubtitleName = $cn !== '' ? $cn : ($an !== '' ? $an : null);
            if ($loginSubtitleName !== null && $loginSubtitleName !== '') {
                break;
            }
        }
    }
}

// Ratib Pro login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $conn !== null) {
    try {
    $verifyUserPassword = function(mysqli $dbConn, array $userRow, string $plainPassword): bool {
        // Some deployments still have both `password` and legacy `pass` columns.
        // Try all non-empty stored candidates before failing.
        $storedCandidates = [];
        foreach (['password', 'pass'] as $k) {
            if (isset($userRow[$k])) {
                $v = trim((string)$userRow[$k]);
                if ($v !== '' && !in_array($v, $storedCandidates, true)) {
                    $storedCandidates[] = $v;
                }
            }
        }
        if (empty($storedCandidates)) {
            return false;
        }

        $matched = false;
        $stored = $storedCandidates[0];
        foreach ($storedCandidates as $cand) {
            if (password_verify($plainPassword, $cand)) {
                $matched = true;
                $stored = $cand;
                break;
            }
            if (hash_equals($cand, $plainPassword)) {
                // Legacy plaintext password row.
                $matched = true;
                $stored = $cand;
                break;
            }
            if (preg_match('/^[a-f0-9]{32}$/i', $cand) && hash_equals(strtolower($cand), md5($plainPassword))) {
                // Legacy md5 row.
                $matched = true;
                $stored = $cand;
                break;
            }
            if (preg_match('/^[a-f0-9]{40}$/i', $cand) && hash_equals(strtolower($cand), sha1($plainPassword))) {
                // Legacy sha1 row.
                $matched = true;
                $stored = $cand;
                break;
            }
        }

        // Auto-upgrade legacy/plain hashes to PASSWORD_DEFAULT after successful login.
        if ($matched && !preg_match('/^\$(2y|2a|argon2)/', $stored)) {
            try {
                $uid = (int)($userRow['user_id'] ?? ($userRow['id'] ?? 0));
                if ($uid > 0) {
                    $passCol = null;
                    $col1 = @$dbConn->query("SHOW COLUMNS FROM users LIKE 'password'");
                    if ($col1 && $col1->num_rows > 0) {
                        $passCol = 'password';
                    } else {
                        $col2 = @$dbConn->query("SHOW COLUMNS FROM users LIKE 'pass'");
                        if ($col2 && $col2->num_rows > 0) {
                            $passCol = 'pass';
                        }
                    }
                    if ($passCol !== null) {
                        $idCol = 'user_id';
                        $idChk = @$dbConn->query("SHOW COLUMNS FROM users LIKE 'user_id'");
                        if (!$idChk || $idChk->num_rows === 0) {
                            $idCol = 'id';
                        }
                        $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
                        $up = $dbConn->prepare("UPDATE users SET {$passCol} = ? WHERE {$idCol} = ? LIMIT 1");
                        if ($up) {
                            $up->bind_param('si', $newHash, $uid);
                            $up->execute();
                            $up->close();
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('Login password auto-upgrade skipped: ' . $e->getMessage());
            }
        }

        return $matched;
    };

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Single URL mode: connect to selected country's DB before validating (use control panel for lookup)
    $loginConn = $conn;
    $singleUrlMode = defined('SINGLE_URL_MODE') && SINGLE_URL_MODE;
    $postedCountryId = isset($_POST['country_id']) ? (int)$_POST['country_id'] : 0;
    // Path-based login: if country_id missing from POST, resolve from country_slug (POST first — survives POST to /pages/login.php without query string)
    if ($singleUrlMode && $postedCountryId <= 0 && $conn instanceof mysqli) {
        $rawSlug = $_POST['country_slug'] ?? $_GET['country_slug'] ?? '';
        $slug = is_string($rawSlug) ? trim(preg_replace('/[^a-z0-9_-]/', '', strtolower($rawSlug))) : '';
        if ($slug !== '') {
            $lookup = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : $conn;
            if ($lookup instanceof mysqli) {
                $chk = @$lookup->query("SHOW TABLES LIKE 'control_countries'");
                if ($chk && $chk->num_rows > 0) {
                    $colActSlug = @$lookup->query("SHOW COLUMNS FROM control_countries LIKE 'is_active'");
                    $whereSlugPost = ($colActSlug && $colActSlug->num_rows > 0)
                        ? 'WHERE LOWER(TRIM(slug)) = ? AND is_active = 1'
                        : 'WHERE LOWER(TRIM(slug)) = ?';
                    foreach ([$slug, str_replace('-', '_', $slug), str_replace('_', '-', $slug)] as $trySlug) {
                        $st = @$lookup->prepare("SELECT id FROM control_countries {$whereSlugPost} LIMIT 1");
                        if ($st) {
                            $st->bind_param("s", $trySlug);
                            $st->execute();
                            $res = $st->get_result();
                            if ($res && $res->num_rows > 0 && ($row = $res->fetch_assoc())) {
                                $postedCountryId = (int)$row['id'];
                                $st->close();
                                break;
                            }
                            $st->close();
                        }
                    }
                }
            }
        }
    }
    $postedAgencyId = isset($_POST['agency_id']) ? (int)$_POST['agency_id'] : 0;
    // Form may POST without query string; GET ?agency_id= + session from config still identify the tenant.
    if ($postedAgencyId <= 0 && !empty($_GET['agency_id']) && ctype_digit((string)$_GET['agency_id'])) {
        $postedAgencyId = (int)$_GET['agency_id'];
    }
    if ($postedAgencyId <= 0 && !empty($_SESSION['agency_id']) && (int)$_SESSION['agency_id'] > 0) {
        $postedAgencyId = (int)$_SESSION['agency_id'];
    }
    // Country in POST but no agency (e.g. /kenya/login without hidden agency): cookies/GET may name the agency;
    // if only one agency serves that country, bind it so we do not use ORDER BY id LIMIT 1 on the wrong tenant.
    if ($singleUrlMode && $postedAgencyId <= 0 && $postedCountryId > 0 && !empty($loginAgencyPicklist)) {
        $candAgencies = [];
        if (!empty($_GET['agency_id']) && ctype_digit((string)$_GET['agency_id'])) {
            $candAgencies[] = (int)$_GET['agency_id'];
        }
        if (!empty($_COOKIE['ratib_last_agency_id']) && ctype_digit((string)$_COOKIE['ratib_last_agency_id'])) {
            $candAgencies[] = (int)$_COOKIE['ratib_last_agency_id'];
        }
        foreach (array_unique(array_filter($candAgencies)) as $cand) {
            if ($cand <= 0) {
                continue;
            }
            foreach ($loginAgencyPicklist as $ar) {
                if ((int)$ar['agency_id'] === $cand && (int)$ar['country_id'] === $postedCountryId) {
                    $postedAgencyId = $cand;
                    break 2;
                }
            }
        }
        if ($postedAgencyId <= 0) {
            $oneAg = 0;
            foreach ($loginAgencyPicklist as $ar) {
                if ((int)$ar['country_id'] === $postedCountryId) {
                    if ($oneAg > 0) {
                        $oneAg = 0;
                        break;
                    }
                    $oneAg = (int)$ar['agency_id'];
                }
            }
            if ($oneAg > 0) {
                $postedAgencyId = $oneAg;
            }
        }
    }
    // No country/agency on the form: still need the correct tenant DB under SINGLE_URL_MODE.
    if ($singleUrlMode && $postedAgencyId <= 0 && $postedCountryId <= 0) {
        if (!empty($_COOKIE['ratib_last_agency_id']) && ctype_digit((string)$_COOKIE['ratib_last_agency_id'])) {
            $postedAgencyId = (int)$_COOKIE['ratib_last_agency_id'];
        }
        if ($postedAgencyId <= 0 && !empty($_COOKIE['ratib_last_country_id']) && ctype_digit((string)$_COOKIE['ratib_last_country_id'])) {
            $postedCountryId = (int)$_COOKIE['ratib_last_country_id'];
        }
    }
    if ($singleUrlMode && $postedAgencyId <= 0 && $postedCountryId <= 0) {
        $siteUrlRes = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        $lcPost = (function_exists('get_control_lookup_conn') && get_control_lookup_conn()) ? get_control_lookup_conn() : $conn;
        if ($siteUrlRes !== '' && $lcPost instanceof mysqli) {
            try {
                $chkAg2 = @$lcPost->query("SHOW TABLES LIKE 'control_agencies'");
                $chkCo2 = @$lcPost->query("SHOW COLUMNS FROM control_agencies LIKE 'country_id'");
                if ($chkAg2 && $chkAg2->num_rows > 0 && $chkCo2 && $chkCo2->num_rows > 0) {
                    $suspR = ratib_control_agency_active_fragment($lcPost, 'a');
                    $stR = $lcPost->prepare("SELECT a.id AS ag_id, a.country_id AS cid FROM control_agencies a WHERE (a.site_url = ? OR a.site_url = ? OR a.site_url LIKE ?) AND a.is_active = 1 AND {$suspR} ORDER BY a.id ASC LIMIT 1");
                    if ($stR) {
                        $u1 = $siteUrlRes;
                        $u2 = $siteUrlRes . '/';
                        $u3 = $siteUrlRes . '/%';
                        $stR->bind_param('sss', $u1, $u2, $u3);
                        $stR->execute();
                        $rr = $stR->get_result();
                        if ($rr && $rr->num_rows > 0 && ($rw = $rr->fetch_assoc())) {
                            $postedAgencyId = (int)($rw['ag_id'] ?? 0);
                            $postedCountryId = (int)($rw['cid'] ?? 0);
                        }
                        $stR->close();
                    }
                }
            } catch (Throwable $e) {
                error_log('Login POST: SITE_URL agency resolve failed: ' . $e->getMessage());
            }
        }
    }
    // Strict tenant auth: only the tenant `users` table — no main-DB password fallback.
    // After SITE_URL/cookies resolve agency/country so strict mode applies even when constants are missing.
    $strictTenantAuth = $singleUrlMode || $postedCountryId > 0 || $postedAgencyId > 0 || $singleCountryFromPath;
    /** Reject control-bridge / synthetic sessions; only real users table rows may log in here. */
    $ratibLoginRequireRealUserRow = function (array $u) use ($strictTenantAuth): bool {
        if (!$strictTenantAuth) {
            return true;
        }
        $uid = (int)($u['user_id'] ?? 0);
        if ($uid <= 0) {
            return false;
        }
        $un = trim((string)($u['username'] ?? ''));
        if ($un === '' || strncmp($un, 'Control:', 8) === 0) {
            return false;
        }
        return true;
    };
    $loginAgencyId = null;
    $loginAgencyName = null;
    if ($singleUrlMode && $postedAgencyId > 0 && $conn instanceof mysqli) {
        $lookupConn = (function_exists('get_control_lookup_conn') && get_control_lookup_conn()) ? get_control_lookup_conn() : $conn;
        $chk = @$lookupConn->query("SHOW TABLES LIKE 'control_agencies'");
        if ($chk && $chk->num_rows > 0) {
            $suspL = ratib_control_agency_active_fragment($lookupConn, 'a');
            $stmtA = $lookupConn->prepare(
                "SELECT a.id, a.name, a.country_id, a.db_host, a.db_port, a.db_user, a.db_pass, a.db_name, c.slug AS country_slug "
                . "FROM control_agencies a LEFT JOIN control_countries c ON c.id = a.country_id "
                . "WHERE a.id = ? AND a.is_active = 1 AND {$suspL} LIMIT 1"
            );
            if ($stmtA) {
                $stmtA->bind_param("i", $postedAgencyId);
                $stmtA->execute();
                $resA = $stmtA->get_result();
                if ($resA && $resA->num_rows > 0) {
                    $rowA = $resA->fetch_assoc();
                    $stmtA->close();
                    $postedCountryId = (int)($rowA['country_id'] ?? $postedCountryId);
                    $loginAgencyId = isset($rowA['id']) ? (int)$rowA['id'] : null;
                    $loginAgencyName = trim($rowA['name'] ?? '') ?: null;
                    $agencyDbName = trim($rowA['db_name'] ?? '');
                    $mainDbName = defined('DB_NAME') ? trim(DB_NAME) : '';
                    if ($agencyDbName !== '' && $agencyDbName === $mainDbName) {
                        $loginConn = $conn;
                    } else {
                        require_once __DIR__ . '/../control-panel/api/control/agency-db-helper.php';
                        $countryIdForHelper = (int)($rowA['country_id'] ?? 0);
                        $acct = getAgencyDbConnection($rowA, $countryIdForHelper);
                        if ($acct && isset($acct['conn']) && $acct['conn'] instanceof mysqli) {
                            $loginConn = $acct['conn'];
                        } else {
                            $detail = function_exists('getAgencyDbConnectionLastError') ? getAgencyDbConnectionLastError() : '';
                            error_log('Login single URL: agency DB connection failed (agency_id=' . (int)$postedAgencyId . '): ' . $detail);
                            $error = 'Cannot connect to country database. Please contact administrator.';
                        }
                    }
                } else {
                    $stmtA->close();
                    $error = 'Invalid agency or login is not configured. Please contact administrator.';
                    error_log("Login: no agency row for agency_id={$postedAgencyId} in control_agencies.");
                }
            }
        }
    } elseif ($singleUrlMode && $postedCountryId > 0 && $conn instanceof mysqli) {
        $lookupConn = (function_exists('get_control_lookup_conn') && get_control_lookup_conn()) ? get_control_lookup_conn() : $conn;
        $chk = @$lookupConn->query("SHOW TABLES LIKE 'control_agencies'");
        if ($chk && $chk->num_rows > 0) {
            $suspL2 = ratib_control_agency_active_fragment($lookupConn, 'a');
            $stmtA = $lookupConn->prepare(
                "SELECT a.id, a.name, a.country_id, a.db_host, a.db_port, a.db_user, a.db_pass, a.db_name, c.slug AS country_slug "
                . "FROM control_agencies a LEFT JOIN control_countries c ON c.id = a.country_id "
                . "WHERE a.country_id = ? AND a.is_active = 1 AND {$suspL2} ORDER BY a.id ASC LIMIT 1"
            );
            if ($stmtA) {
                $stmtA->bind_param("i", $postedCountryId);
                $stmtA->execute();
                $resA = $stmtA->get_result();
                if ($resA && $resA->num_rows > 0) {
                    $rowA = $resA->fetch_assoc();
                    $stmtA->close();
                    $loginAgencyId = isset($rowA['id']) ? (int)$rowA['id'] : null;
                    $loginAgencyName = trim($rowA['name'] ?? '') ?: null;
                    $agencyDbName = trim($rowA['db_name'] ?? '');
                    $mainDbName = defined('DB_NAME') ? trim(DB_NAME) : '';
                    // When agency uses same DB as main, use existing connection (avoids connection failures)
                    if ($agencyDbName !== '' && $agencyDbName === $mainDbName) {
                        $loginConn = $conn;
                    } else {
                        require_once __DIR__ . '/../control-panel/api/control/agency-db-helper.php';
                        $countryIdForHelper = (int)($rowA['country_id'] ?? $postedCountryId);
                        $acct = getAgencyDbConnection($rowA, $countryIdForHelper);
                        if ($acct && isset($acct['conn']) && $acct['conn'] instanceof mysqli) {
                            $loginConn = $acct['conn'];
                        } else {
                            $detail = function_exists('getAgencyDbConnectionLastError') ? getAgencyDbConnectionLastError() : '';
                            error_log('Login single URL: country DB connection failed (country_id=' . (int)$postedCountryId . '): ' . $detail);
                            $error = 'Cannot connect to country database. Please contact administrator.';
                        }
                    }
                } else {
                    $stmtA->close();
                    // Country selected but no agency/DB configured — don't try main DB; show clear message
                    $error = 'Your country\'s login is not configured. Please contact administrator.';
                    error_log("Login: no agency/DB found for country_id={$postedCountryId}. Ensure control_agencies has a row for this country (with db_host, db_name, etc.) in the control panel DB or main DB.");
                }
            }
        }
    }

    // Multi-tenant subdomain mode: use Auth::login (tenant isolation)
    if (defined('MULTI_TENANT_SUBDOMAIN_ENABLED') && MULTI_TENANT_SUBDOMAIN_ENABLED && defined('TENANT_ID')) {
        $authResult = Auth::login($username, $password);
        if ($authResult['success']) {
            $user = $authResult['user'];
            $_SESSION['role_id'] = (int)($user['role_id'] ?? 1);
            $_SESSION['country_id'] = defined('TENANT_ID') ? TENANT_ID : ($user['country_id'] ?? null);
            $_SESSION['country_name'] = defined('TENANT_NAME') ? TENANT_NAME : null;
            // In multi-tenant mode, agency is effectively the tenant; set a friendly label if available
            $_SESSION['agency_name'] = defined('TENANT_NAME') ? TENANT_NAME : null;
            require_once '../includes/permissions.php';
            $_SESSION['user_permissions'] = getUserPermissions();
            try {
                $loginPk = ratib_users_primary_key_column($conn);
                $permStmt = $conn->prepare("SELECT permissions FROM users WHERE `{$loginPk}` = ?");
                if ($permStmt) {
                    $uid = (int)$user['user_id'];
                    $permStmt->bind_param("i", $uid);
                    $permStmt->execute();
                    $permResult = $permStmt->get_result();
                    if ($permResult && $permRow = $permResult->fetch_assoc()) {
                        $_SESSION['user_specific_permissions'] = !empty($permRow['permissions']) ? json_decode($permRow['permissions'], true) : null;
                    }
                    $permStmt->close();
                }
            } catch (Exception $e) { $_SESSION['user_specific_permissions'] = null; }
            $loginDesc = 'User logged in';
            $tid = defined('TENANT_ID') ? TENANT_ID : 1;
            $chk = $conn->query("SHOW COLUMNS FROM activity_logs LIKE 'country_id'");
            $sql = ($chk && $chk->num_rows > 0)
                ? "INSERT INTO activity_logs (user_id, action, description, country_id) VALUES (?, 'login', ?, ?)"
                : "INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'login', ?)";
            $logStmt = $conn->prepare($sql);
            if ($logStmt) {
                if ($chk && $chk->num_rows > 0) {
                    $logStmt->bind_param("isi", $user['user_id'], $loginDesc, $tid);
                } else {
                    $logStmt->bind_param("is", $user['user_id'], $loginDesc);
                }
                $logStmt->execute();
                $logStmt->close();
            }
            try {
                $conn->query("UPDATE users SET last_login = NOW() WHERE user_id = " . (int)$user['user_id']);
            } catch (Exception $e) { /* ignore */ }
            if (function_exists('ratib_set_login_context_cookies')) {
                ratib_set_login_context_cookies(defined('TENANT_ID') ? (int)TENANT_ID : 0, 0);
            }
            header('Location: ' . ratib_country_dashboard_url((int)($_SESSION['agency_id'] ?? 0)));
            exit;
        }
        $error = $authResult['error'];
    } elseif (empty($error)) {
    // Legacy login (no multi-tenant subdomain) — country isolation when shared DB
    error_log("Login attempt for username: " . $username);

    // Resolve agency and country: from form (country_id) first, else from SITE_URL
    $agencyCountryId = null;
    $agencyCountryName = null;
    $agencyId = null;
    $agencyName = null;
    if ($postedCountryId > 0) {
        $agencyCountryId = $postedCountryId;
        // Use the specific agency we connected to (from DB lookup above) so session has correct agency_id/name
        if ($loginAgencyId !== null && $loginAgencyId > 0) {
            $agencyId = $loginAgencyId;
            $agencyName = $loginAgencyName;
        }
        $ctrlLookup = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
        $lookupConn = $ctrlLookup !== null ? $ctrlLookup : $conn;
        try {
            $stmtC = $lookupConn->prepare("SELECT id, name FROM control_countries WHERE id = ? LIMIT 1");
            if ($stmtC) {
                $stmtC->bind_param("i", $agencyCountryId);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                if ($resC && $resC->num_rows > 0) {
                    $rowC = $resC->fetch_assoc();
                    $agencyCountryName = trim($rowC['name'] ?? '') ?: null;
                }
                $stmtC->close();
            }
            // If we didn't get agency from login lookup, resolve by country
            if ($agencyCountryId > 0 && ($agencyName === null || $agencyName === '')) {
                foreach ([$ctrlLookup, $conn] as $tryConn) {
                    if ($agencyName !== null && $agencyName !== '') break;
                    if (!$tryConn || !($tryConn instanceof mysqli)) continue;
                    try {
                        $chk = @$tryConn->query("SHOW TABLES LIKE 'control_agencies'");
                        if (!$chk || $chk->num_rows === 0) continue;
                        $suspT = ratib_control_agency_active_fragment($tryConn, null);
                        $stmtA = $tryConn->prepare("SELECT id, name FROM control_agencies WHERE country_id = ? AND is_active = 1 AND {$suspT} ORDER BY id ASC LIMIT 1");
                        if (!$stmtA) continue;
                        $stmtA->bind_param("i", $agencyCountryId);
                        $stmtA->execute();
                        $resA = $stmtA->get_result();
                        if ($resA && $resA->num_rows > 0 && ($rowA = $resA->fetch_assoc())) {
                            $agencyId = isset($rowA['id']) ? (int)$rowA['id'] : null;
                            $agencyName = trim($rowA['name'] ?? '') ?: null;
                        }
                        $stmtA->close();
                        if ($agencyName !== null && $agencyName !== '') break;
                    } catch (Throwable $e) { /* try next conn */ }
                }
            }
        } catch (Throwable $e) { /* ignore */ }
    } else {
        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        if ($siteUrl !== '' && $conn instanceof mysqli) {
            $chk = @$conn->query("SHOW TABLES LIKE 'control_agencies'");
            if ($chk && $chk->num_rows > 0) {
                $colChk = @$conn->query("SHOW COLUMNS FROM control_agencies LIKE 'country_id'");
                if ($colChk && $colChk->num_rows > 0) {
                    $suspSite = ratib_control_agency_active_fragment($conn, 'a');
                    $stmtC = $conn->prepare("SELECT c.id, c.name, a.id AS agency_id, a.name AS agency_name FROM control_agencies a LEFT JOIN control_countries c ON a.country_id = c.id WHERE (a.site_url = ? OR a.site_url = ? OR a.site_url LIKE ?) AND a.is_active = 1 AND {$suspSite} LIMIT 1");
                    if ($stmtC) {
                        $url1 = $siteUrl;
                        $url2 = $siteUrl . '/';
                        $url3 = $siteUrl . '/%';
                        $stmtC->bind_param("sss", $url1, $url2, $url3);
                        $stmtC->execute();
                        $resC = $stmtC->get_result();
                        if ($resC && $resC->num_rows > 0) {
                            $rowC = $resC->fetch_assoc();
                            $agencyCountryId = (int)($rowC['id'] ?? 0);
                            $agencyCountryName = trim($rowC['name'] ?? '') ?: null;
                            $agencyId = isset($rowC['agency_id']) ? (int)$rowC['agency_id'] : null;
                            $agencyName = trim($rowC['agency_name'] ?? '') ?: null;
                        }
                        $stmtC->close();
                    }
                }
            }
        }
    }
    if ($agencyCountryId === null) {
        try {
            $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
            if ($siteUrl !== '' && $conn instanceof mysqli) {
                $chk = @$conn->query("SHOW TABLES LIKE 'control_agencies'");
                if ($chk && $chk->num_rows > 0) {
                    $colChk = @$conn->query("SHOW COLUMNS FROM control_agencies LIKE 'country_id'");
                    if ($colChk && $colChk->num_rows > 0) {
                        $suspSite2 = ratib_control_agency_active_fragment($conn, 'a');
                        $stmtC = $conn->prepare("SELECT c.id, c.name, a.id AS agency_id, a.name AS agency_name FROM control_agencies a LEFT JOIN control_countries c ON a.country_id = c.id WHERE (a.site_url = ? OR a.site_url = ? OR a.site_url LIKE ?) AND a.is_active = 1 AND {$suspSite2} LIMIT 1");
                        if ($stmtC) {
                            $url1 = $siteUrl;
                            $url2 = $siteUrl . '/';
                            $url3 = $siteUrl . '/%';
                            $stmtC->bind_param("sss", $url1, $url2, $url3);
                            $stmtC->execute();
                            $resC = $stmtC->get_result();
                            if ($resC && $resC->num_rows > 0) {
                                $rowC = $resC->fetch_assoc();
                                $agencyCountryId = (int)($rowC['id'] ?? 0);
                                $agencyCountryName = trim($rowC['name'] ?? '') ?: null;
                                $agencyId = isset($rowC['agency_id']) ? (int)$rowC['agency_id'] : null;
                                $agencyName = trim($rowC['agency_name'] ?? '') ?: null;
                            }
                            $stmtC->close();
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            error_log("Login - Failed to resolve agency country: " . $e->getMessage());
        }
    }

    // Session may already have agency_id from config (?control=1&agency_id=) before POST login.
    if (($agencyId === null || (int)$agencyId <= 0) && isset($_SESSION['agency_id']) && (int)$_SESSION['agency_id'] > 0) {
        $agencyId = (int)$_SESSION['agency_id'];
    }

    /**
     * User row must match this login's country when users.country_id is set.
     * No implicit "global admin" (role_id=1 + NULL country) — only tenant_role super_admin may cross countries.
     */
    $ratibLoginUserAllowedForAgencyCountry = function (array $u) use ($agencyCountryId): bool {
        $hasCountryColumn = array_key_exists('country_id', $u);
        if ($agencyCountryId === null || (int)$agencyCountryId <= 0 || !$hasCountryColumn) {
            return true;
        }
        $raw = $u['country_id'] ?? null;
        $userCountryId = ($raw !== null && $raw !== '' && (int)$raw > 0) ? (int)$raw : null;
        $tenantRole = $u['tenant_role'] ?? null;
        if ($tenantRole === 'super_admin') {
            return true;
        }
        if ($userCountryId !== null && $userCountryId !== (int)$agencyCountryId) {
            return false;
        }
        return true;
    };

    /** When users.agency_id is set, it must match the agency context (shared DB, multiple agencies). */
    $ratibLoginUserAllowedForProgramAgency = function (array $u) use ($agencyId): bool {
        if (!array_key_exists('agency_id', $u)) {
            return true;
        }
        if ($agencyId === null || (int)$agencyId <= 0) {
            return true;
        }
        $raw = $u['agency_id'] ?? null;
        $ua = ($raw !== null && $raw !== '' && (int)$raw > 0) ? (int)$raw : 0;
        if ($ua > 0 && $ua !== (int)$agencyId) {
            return false;
        }
        return true;
    };

    // Build user column list safely (handles schema variants: user_id/id, password/pass)
    $buildUserSelectCols = function(mysqli $dbConn): string {
        $cols = [];
        $colRes = @$dbConn->query("SHOW COLUMNS FROM users");
        if ($colRes) {
            while ($r = $colRes->fetch_assoc()) {
                $cols[] = (string)($r['Field'] ?? '');
            }
        }
        $idCol = in_array('user_id', $cols, true) ? 'user_id' : (in_array('id', $cols, true) ? 'id' : 'user_id');
        $hasPasswordCol = in_array('password', $cols, true);
        $hasPassCol = in_array('pass', $cols, true);
        $passCol = $hasPasswordCol ? 'password' : ($hasPassCol ? 'pass' : 'password');
        $roleCol = in_array('role_id', $cols, true) ? 'role_id' : '1';
        $statusCol = in_array('status', $cols, true) ? 'status' : "'active'";

        $select = "{$idCol} AS user_id, username, {$passCol} AS password, {$roleCol} AS role_id, {$statusCol} AS status";
        if ($hasPassCol && $passCol !== 'pass') {
            $select .= ', pass';
        }
        if (in_array('country_id', $cols, true)) {
            $select .= ', country_id';
        }
        if (in_array('tenant_role', $cols, true)) {
            $select .= ', tenant_role';
        }
        if (in_array('is_active', $cols, true)) {
            $select .= ', is_active';
        }
        if (in_array('agency_id', $cols, true)) {
            $select .= ', agency_id';
        }
        return $select;
    };

    $hasUsersTable = $loginConn->query("SHOW TABLES LIKE 'users'");
    if (!$hasUsersTable || $hasUsersTable->num_rows === 0) {
        // Fallback to main DB only when not strict tenant (never use main `users` for country/agency login).
        if (!$strictTenantAuth && $loginConn !== $conn && $conn instanceof mysqli) {
            $loginConn = $conn;
            $hasUsersTable = $loginConn->query("SHOW TABLES LIKE 'users'");
        }
    }
    if (!$hasUsersTable || $hasUsersTable->num_rows === 0) {
        $error = 'User table not found. Please contact administrator.';
        error_log("Login error: users table not found in current connection for username {$username}");
    }
    $userCols = empty($error) ? $buildUserSelectCols($loginConn) : '';
    $stmt = empty($error) ? $loginConn->prepare("SELECT {$userCols} FROM users WHERE username = ?") : null;
    if (empty($error) && !$stmt) {
        $error = 'Database error: ' . $loginConn->error;
        error_log("Database prepare error: " . $loginConn->error);
    } elseif (empty($error)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            error_log("User found: " . $user['username'] . ", Status: " . ($user['status'] ?? ''));
            if (!$ratibLoginRequireRealUserRow($user)) {
                $error = 'Invalid username or password.';
                error_log('Login denied: non-user-table session row blocked in strict tenant context (user_id=' . (int)($user['user_id'] ?? 0) . ')');
            } else {
                // Country isolation (same rules as sibling-agency and "user not in first DB" paths).
                if (!$ratibLoginUserAllowedForAgencyCountry($user)) {
                    $error = 'Access denied. This account is not valid for this country. Please use the correct login page.';
                    error_log('Login denied: user country_id does not match agency country_id=' . (int)$agencyCountryId);
                }
                if (empty($error) && !$ratibLoginUserAllowedForProgramAgency($user)) {
                    $error = 'Access denied. This account is not valid for this agency. Open Ratib Pro from Manage Agencies for the correct agency.';
                    error_log('Login denied: user agency_id does not match agency context agency_id=' . (int)($agencyId ?? 0));
                }

                $passwordVerified = false;
                if (empty($error)) {
                    $passwordVerified = $verifyUserPassword($loginConn, $user, $password);
                }
                // If the same username exists in another DB with a different password,
                // try main DB before rejecting the login.
                if (!$passwordVerified && empty($error) && !$strictTenantAuth && $singleUrlMode && $loginConn !== $conn && $conn instanceof mysqli) {
                    $altUserCols = $buildUserSelectCols($conn);
                    $stmtAlt = $conn->prepare("SELECT {$altUserCols} FROM users WHERE username = ? LIMIT 1");
                    if ($stmtAlt) {
                        $stmtAlt->bind_param("s", $username);
                        $stmtAlt->execute();
                        $resAlt = $stmtAlt->get_result();
                        if ($resAlt && $resAlt->num_rows === 1) {
                            $altUser = $resAlt->fetch_assoc();
                            if ($verifyUserPassword($conn, $altUser, $password)) {
                                $passwordVerified = true;
                                $user = $altUser;
                                $loginConn = $conn;
                                error_log("Login fallback: password verified in main DB for username {$username}");
                            }
                        }
                        $stmtAlt->close();
                    }
                }
                // Final fallback: same country may have multiple active agencies/DBs.
                // Try sibling agencies for this country before rejecting credentials.
                $countrySearchId = $postedCountryId > 0 ? $postedCountryId : (int)($loginCountryId ?? 0);
                if (
                    !$passwordVerified
                    && empty($error)
                    && $strictTenantAuth
                    && $countrySearchId > 0
                    && function_exists('get_control_lookup_conn')
                ) {
                    $lookupConn = get_control_lookup_conn();
                    if ($lookupConn instanceof mysqli) {
                        $chkAgTbl = @$lookupConn->query("SHOW TABLES LIKE 'control_agencies'");
                        if ($chkAgTbl && $chkAgTbl->num_rows > 0) {
                            $suspAg = ratib_control_agency_active_fragment($lookupConn, 'a');
                            $sqlAgTry = "SELECT a.id, a.name, a.country_id, a.db_host, a.db_port, a.db_user, a.db_pass, a.db_name, c.slug AS country_slug "
                                . "FROM control_agencies a "
                                . "LEFT JOIN control_countries c ON c.id = a.country_id "
                                . "WHERE a.country_id = ? AND a.is_active = 1 AND {$suspAg} ORDER BY a.id ASC";
                            $stAgTry = $lookupConn->prepare($sqlAgTry);
                            if ($stAgTry) {
                                $stAgTry->bind_param('i', $countrySearchId);
                                $stAgTry->execute();
                                $rsAgTry = $stAgTry->get_result();
                                while (!$passwordVerified && $rsAgTry && ($agTry = $rsAgTry->fetch_assoc())) {
                                    $agTryId = (int)($agTry['id'] ?? 0);
                                    if ($agTryId > 0 && $agencyId !== null && $agTryId === (int)$agencyId) {
                                        continue;
                                    }
                                    require_once __DIR__ . '/../control-panel/api/control/agency-db-helper.php';
                                    $acctTry = getAgencyDbConnection($agTry, $countrySearchId);
                                    if (!$acctTry || !isset($acctTry['conn']) || !($acctTry['conn'] instanceof mysqli)) {
                                        continue;
                                    }
                                    $tryConn = $acctTry['conn'];
                                    $tryCols = $buildUserSelectCols($tryConn);
                                    $stTryUser = $tryConn->prepare("SELECT {$tryCols} FROM users WHERE username = ? LIMIT 1");
                                    if (!$stTryUser) {
                                        continue;
                                    }
                                    $stTryUser->bind_param('s', $username);
                                    $stTryUser->execute();
                                    $rsTryUser = $stTryUser->get_result();
                                    if ($rsTryUser && $rsTryUser->num_rows === 1) {
                                        $tryUser = $rsTryUser->fetch_assoc();
                                        if (!$ratibLoginUserAllowedForAgencyCountry($tryUser) || !$ratibLoginUserAllowedForProgramAgency($tryUser)) {
                                            $stTryUser->close();
                                            continue;
                                        }
                                        if ($verifyUserPassword($tryConn, $tryUser, $password) && $ratibLoginRequireRealUserRow($tryUser)) {
                                            $passwordVerified = true;
                                            $user = $tryUser;
                                            $loginConn = $tryConn;
                                            $agencyId = $agTryId;
                                            $agencyName = trim((string)($agTry['name'] ?? '')) ?: $agencyName;
                                            error_log("Login sibling-agency fallback: authenticated username {$username} via agency_id={$agTryId}");
                                        }
                                    }
                                    $stTryUser->close();
                                }
                                $stAgTry->close();
                            }
                        }
                    }
                }

                if ($passwordVerified) {
                    error_log("Password verified successfully");
                    if ($strictTenantAuth && !$ratibLoginRequireRealUserRow($user)) {
                        $passwordVerified = false;
                        $error = 'Invalid username or password.';
                        error_log('Login denied after verify: blocked non-user row in strict tenant context');
                    }
                }
                if ($passwordVerified) {
                    $st = strtolower(trim((string)($user['status'] ?? '')));
                    $statusOk = ($st === 'active' || $st === '1' || $st === 'enabled');
                    if (!$statusOk && array_key_exists('is_active', $user)) {
                        $statusOk = !empty((int)($user['is_active'] ?? 0));
                    }
                    if ($statusOk) {
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role_id'] = $user['role_id'];
                        $_SESSION['logged_in'] = true;
                        if (function_exists('ratib_partner_portal_clear')) {
                            ratib_partner_portal_clear();
                        }

                        // Set role name for display (Admin, User, etc.) — use same DB as user (country DB)
                        $_SESSION['role'] = 'User';
                        try {
                            $rStmt = $loginConn->prepare("SELECT role_name FROM roles WHERE role_id = ? LIMIT 1");
                            if ($rStmt) {
                                $rStmt->bind_param("i", $user['role_id']);
                                $rStmt->execute();
                                $rRes = $rStmt->get_result();
                                if ($rRes && $rRes->num_rows > 0 && ($rRow = $rRes->fetch_assoc())) {
                                    $_SESSION['role'] = trim($rRow['role_name'] ?? '') ?: 'User';
                                }
                                $rStmt->close();
                            }
                        } catch (Throwable $e) { /* ignore */ }

                        // Set country & agency in session (from agency lookup above); clear stale values so they always match
                        unset($_SESSION['country_name'], $_SESSION['agency_name'], $_SESSION['agency_id']);
                        $_SESSION['country_id'] = $agencyCountryId ?: null;
                        $_SESSION['country_name'] = $agencyCountryName;
                        $_SESSION['agency_name'] = $agencyName;
                        if ($agencyId !== null && $agencyId > 0) {
                            $_SESSION['agency_id'] = $agencyId;
                        }

                        try {
                            $loginConn->query("UPDATE users SET last_login = NOW() WHERE user_id = " . (int)$user['user_id']);
                            $logStmt = $loginConn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'login', ?)");
                            if ($logStmt) {
                                $loginDesc = 'User logged in';
                                $logStmt->bind_param("is", $user['user_id'], $loginDesc);
                                $logStmt->execute();
                                $logStmt->close();
                            }
                        } catch (Exception $e) {
                            error_log("Login - Failed to update last_login or log activity: " . $e->getMessage());
                        }

                        require_once '../includes/permissions.php';
                        $_SESSION['user_permissions'] = getUserPermissions();

                        try {
                            $loginPk2 = ratib_users_primary_key_column($loginConn);
                            $permStmt = $loginConn->prepare("SELECT permissions FROM users WHERE `{$loginPk2}` = ?");
                            $permStmt->bind_param("i", $user['user_id']);
                            $permStmt->execute();
                            $permResult = $permStmt->get_result();
                            if ($permRow = $permResult->fetch_assoc()) {
                                if (!empty($permRow['permissions'])) {
                                    $_SESSION['user_specific_permissions'] = json_decode($permRow['permissions'], true);
                                } else {
                                    $_SESSION['user_specific_permissions'] = null;
                                }
                            }
                            $permStmt->close();
                        } catch (Exception $e) {
                            error_log("Error loading user-specific permissions: " . $e->getMessage());
                            $_SESSION['user_specific_permissions'] = null;
                        }

                        error_log("Session created for user: " . $user['username'] . " with role_id: " . $user['role_id']);
                        if (function_exists('ratib_set_login_context_cookies')) {
                            ratib_set_login_context_cookies((int)($_SESSION['country_id'] ?? 0), (int)($_SESSION['agency_id'] ?? 0));
                        }
                        header('Location: ' . ratib_country_dashboard_url((int)($_SESSION['agency_id'] ?? 0)));
                        exit();
                    }
                    $error = 'Account is inactive.';
                    error_log("Login failed: Account inactive for user: " . $username);
                } else {
                    $error = 'Invalid username or password.';
                    error_log("Login failed: Invalid password for user: " . $username);
                }
            }
        } else {
            // Username not found in the first selected DB: try all active agencies in the same country.
            $countrySearchId = $postedCountryId > 0 ? $postedCountryId : (int)($loginCountryId ?? 0);
            if ($strictTenantAuth && $countrySearchId > 0 && function_exists('get_control_lookup_conn')) {
                $lookupConn = get_control_lookup_conn();
                if ($lookupConn instanceof mysqli) {
                    $chkAgTbl = @$lookupConn->query("SHOW TABLES LIKE 'control_agencies'");
                    if ($chkAgTbl && $chkAgTbl->num_rows > 0) {
                        $suspAg = ratib_control_agency_active_fragment($lookupConn, 'a');
                        $sqlAgTry = "SELECT a.id, a.name, a.country_id, a.db_host, a.db_port, a.db_user, a.db_pass, a.db_name, c.slug AS country_slug "
                            . "FROM control_agencies a "
                            . "LEFT JOIN control_countries c ON c.id = a.country_id "
                            . "WHERE a.country_id = ? AND a.is_active = 1 AND {$suspAg} ORDER BY a.id ASC";
                        $stAgTry = $lookupConn->prepare($sqlAgTry);
                        if ($stAgTry) {
                            $stAgTry->bind_param('i', $countrySearchId);
                            $stAgTry->execute();
                            $rsAgTry = $stAgTry->get_result();
                            while ($rsAgTry && ($agTry = $rsAgTry->fetch_assoc())) {
                                require_once __DIR__ . '/../control-panel/api/control/agency-db-helper.php';
                                $acctTry = getAgencyDbConnection($agTry, $countrySearchId);
                                if (!$acctTry || !isset($acctTry['conn']) || !($acctTry['conn'] instanceof mysqli)) {
                                    continue;
                                }
                                $tryConn = $acctTry['conn'];
                                $tryCols = $buildUserSelectCols($tryConn);
                                $stTryUser = $tryConn->prepare("SELECT {$tryCols} FROM users WHERE username = ? LIMIT 1");
                                if (!$stTryUser) {
                                    continue;
                                }
                                $stTryUser->bind_param('s', $username);
                                $stTryUser->execute();
                                $rsTryUser = $stTryUser->get_result();
                                if ($rsTryUser && $rsTryUser->num_rows === 1) {
                                    $tryUser = $rsTryUser->fetch_assoc();
                                    if (!$ratibLoginUserAllowedForAgencyCountry($tryUser) || !$ratibLoginUserAllowedForProgramAgency($tryUser)) {
                                        $stTryUser->close();
                                        continue;
                                    }
                                    if ($verifyUserPassword($tryConn, $tryUser, $password) && $ratibLoginRequireRealUserRow($tryUser)) {
                                        $tryRoleId = (int)($tryUser['role_id'] ?? 1);
                                        $_SESSION['user_id'] = (int)($tryUser['user_id'] ?? 0);
                                        $_SESSION['username'] = (string)($tryUser['username'] ?? '');
                                        $_SESSION['role_id'] = $tryRoleId;
                                        $_SESSION['logged_in'] = true;
                                        if (function_exists('ratib_partner_portal_clear')) {
                                            ratib_partner_portal_clear();
                                        }
                                        $_SESSION['role'] = 'User';
                                        try {
                                            $rStmt = $tryConn->prepare("SELECT role_name FROM roles WHERE role_id = ? LIMIT 1");
                                            if ($rStmt) {
                                                $rStmt->bind_param("i", $tryRoleId);
                                                $rStmt->execute();
                                                $rRes = $rStmt->get_result();
                                                if ($rRes && $rRes->num_rows > 0 && ($rRow = $rRes->fetch_assoc())) {
                                                    $_SESSION['role'] = trim($rRow['role_name'] ?? '') ?: 'User';
                                                }
                                                $rStmt->close();
                                            }
                                        } catch (Throwable $e) { /* ignore */ }
                                        unset($_SESSION['country_name'], $_SESSION['agency_name'], $_SESSION['agency_id']);
                                        $_SESSION['country_id'] = (int)($agTry['country_id'] ?? $postedCountryId);
                                        $_SESSION['country_name'] = $agencyCountryName;
                                        $_SESSION['agency_name'] = trim((string)($agTry['name'] ?? '')) ?: $agencyName;
                                        $_SESSION['agency_id'] = (int)($agTry['id'] ?? 0);
                                        if (function_exists('ratib_set_login_context_cookies')) {
                                            ratib_set_login_context_cookies((int)($_SESSION['country_id'] ?? 0), (int)($_SESSION['agency_id'] ?? 0));
                                        }
                                        header('Location: ' . ratib_country_dashboard_url((int)($_SESSION['agency_id'] ?? 0)));
                                        exit();
                                    }
                                }
                                $stTryUser->close();
                            }
                            $stAgTry->close();
                        }
                    }
                }
            }
            if (!$strictTenantAuth && $singleUrlMode && $postedCountryId > 0 && $loginConn !== $conn && $conn instanceof mysqli && $username !== '') {
                $hintStmt = @$conn->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
                if ($hintStmt) {
                    $hintStmt->bind_param('s', $username);
                    $hintStmt->execute();
                    $hintRes = $hintStmt->get_result();
                    if ($hintRes && $hintRes->num_rows > 0) {
                        $mainDb = defined('DB_NAME') ? DB_NAME : 'main';
                        error_log("Ratib login: user exists in main DB ({$mainDb}) but not in selected country DB (country_id={$postedCountryId}). Fix: set control_agencies.db_name/db_user to that main DB for each country, or create this user in the per-country database. See config/migrations/ratib_pro_use_main_db.sql");
                    }
                    $hintStmt->close();
                }
            }
            if (!$strictTenantAuth && $loginConn !== $conn && $conn instanceof mysqli) {
                // Safety fallback: user may still live in main DB while agency DB is used for app data.
                // Keep country/agency session context from selected tenant after successful login.
                $loginConn = $conn;
                $userCols = 'user_id, username, password, role_id, status';
                $userCols = $buildUserSelectCols($loginConn);
                $stmt2 = $loginConn->prepare("SELECT {$userCols} FROM users WHERE username = ?");
                if ($stmt2) {
                    $stmt2->bind_param("s", $username);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();
                    if ($result2->num_rows === 1) {
                        $user = $result2->fetch_assoc();
                        error_log("Login fallback: user found in main DB for username {$username}");
                        if ($verifyUserPassword($loginConn, $user, $password)) {
                            $st2 = strtolower(trim((string)($user['status'] ?? '')));
                            $statusOk = ($st2 === 'active' || $st2 === '1' || $st2 === 'enabled');
                            if (!$statusOk && array_key_exists('is_active', $user)) {
                                $statusOk = !empty((int)($user['is_active'] ?? 0));
                            }
                            if ($statusOk && $ratibLoginRequireRealUserRow($user)) {
                                $_SESSION['user_id'] = $user['user_id'];
                                $_SESSION['username'] = $user['username'];
                                $_SESSION['role_id'] = $user['role_id'];
                                $_SESSION['logged_in'] = true;
                                if (function_exists('ratib_partner_portal_clear')) {
                                    ratib_partner_portal_clear();
                                }
                                $_SESSION['role'] = 'User';
                                try {
                                    $rStmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ? LIMIT 1");
                                    if ($rStmt) {
                                        $rStmt->bind_param("i", $user['role_id']);
                                        $rStmt->execute();
                                        $rRes = $rStmt->get_result();
                                        if ($rRes && $rRes->num_rows > 0 && ($rRow = $rRes->fetch_assoc())) {
                                            $_SESSION['role'] = trim($rRow['role_name'] ?? '') ?: 'User';
                                        }
                                        $rStmt->close();
                                    }
                                } catch (Throwable $e) { /* ignore */ }
                                unset($_SESSION['country_name'], $_SESSION['agency_name'], $_SESSION['agency_id']);
                                $_SESSION['country_id'] = $agencyCountryId ?: null;
                                $_SESSION['country_name'] = $agencyCountryName;
                                $_SESSION['agency_name'] = $agencyName;
                                if ($agencyId !== null && $agencyId > 0) {
                                    $_SESSION['agency_id'] = $agencyId;
                                }
                                try {
                                    $loginConn->query("UPDATE users SET last_login = NOW() WHERE user_id = " . (int)$user['user_id']);
                                } catch (Throwable $e) { /* ignore */ }
                                require_once '../includes/permissions.php';
                                $_SESSION['user_permissions'] = getUserPermissions();
                                if (function_exists('ratib_set_login_context_cookies')) {
                                    ratib_set_login_context_cookies((int)($agencyCountryId ?? 0), (int)($agencyId ?? 0));
                                }
                                header('Location: ' . ratib_country_dashboard_url((int)($_SESSION['agency_id'] ?? 0)));
                                exit();
                            } else {
                                $error = 'Account is inactive.';
                            }
                        } else {
                            $error = 'Invalid username or password.';
                        }
                    } else {
                        $error = 'Invalid username or password.';
                        error_log("Login failed: User not found in any users table: " . $username);
                    }
                    $stmt2->close();
                } else {
                    $error = 'Invalid username or password.';
                    error_log("Login fallback prepare failed for username: " . $username . ' error: ' . $loginConn->error);
                }
            } else {
                $error = 'Invalid username or password.';
                if ($singleUrlMode && $postedCountryId > 0) {
                    error_log("Login failed: User not found in country DB (country_id={$postedCountryId}) and no main DB fallback connection: " . $username);
                } else {
                    error_log("Login failed: User not found in country users: " . $username);
                }
            }
        }
        $stmt->close();
    }
    } // end legacy login else
    } catch (Throwable $e) {
        error_log("Normal login error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        $debugHost = $_SERVER['HTTP_HOST'] ?? '';
        $showDebug = (strpos($debugHost, 'out.ratib.sa') !== false || strpos($debugHost, 'bangladesh.out.ratib.sa') !== false);
        $error = 'Login error. Please try again or contact administrator.';
        if ($showDebug) {
            $error .= ' [Debug: ' . htmlspecialchars($e->getMessage()) . ']';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && $conn === null) {
    $error = 'Database connection failed. Please contact administrator.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $loginSubtitleName ? htmlspecialchars($loginSubtitleName) . ' - ' : ''; ?>Login - Ratib Pro</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%236b21a8'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' fill='white' text-anchor='middle'%3ER%3C/text%3E%3C/svg%3E">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Login Page CSS -->
    <?php
    $loginCssVersion = file_exists(__DIR__ . '/../css/login.css')
        ? filemtime(__DIR__ . '/../css/login.css')
        : time();
    ?>
    <link rel="stylesheet" href="../css/login.css?v=<?php echo $loginCssVersion; ?>">
    <?php require_once __DIR__ . '/../includes/chat-widget-standalone-head.php'; ?>
</head>
<body>
    <!-- Dark Mode Toggle -->
    <button class="dark-mode-toggle" id="dark-mode-toggle" aria-label="Toggle dark mode">
        <i class="fas fa-moon" id="theme-icon"></i>
    </button>
    
    <div class="hyperdimensional-container">
        <!-- Animated Background -->
        <div class="animated-background" id="animated-background">
            <!-- Text will be dynamically generated by JavaScript -->
        </div>
        
        <div class="portal-content active">
            <?php if ($loginSubtitleName !== null && $loginSubtitleName !== ''): ?>
            <div class="mb-2 text-center">
                <span class="badge bg-primary fs-6 px-3 py-2"><?php echo htmlspecialchars($loginSubtitleName); ?></span>
            </div>
            <?php endif; ?>
            <h2>Login</h2>
            <?php
            $loginIntroLine = $loginSubtitleName
                ? 'Ratib Pro — ' . htmlspecialchars($loginSubtitleName) . ' — sign in with your username and password.'
                : 'Ratib Pro — sign in with your username and password.';
            ?>
            <p class="text-muted small mb-2"><?php echo $loginIntroLine; ?></p>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <div class="text-center mt-4">
                <!-- Login Method Selector -->
                <div class="mb-3">
                    <label for="login-method" class="text-muted me-2 small">Choose Login Method:</label>
                    <select id="login-method" class="form-select form-select-sm d-inline-block w-auto">
                        <option value="password">Username & Password</option>
                        <option value="fingerprint">Fingerprint</option>
                    </select>
                </div>
                
                <!-- Username/Password Form (Default) -->
                <div id="password-form" class="d-block">
                    <form method="post" action="" class="text-center">
                        <?php if (!empty($_GET['control']) && (string)$_GET['control'] === '1'): ?>
                        <input type="hidden" name="control" value="1">
                        <?php endif; ?>
                        <?php if ($formHiddenCountryId > 0): ?>
                        <input type="hidden" name="country_id" value="<?php echo (int)$formHiddenCountryId; ?>">
                        <?php if ($singleCountryFromPath && !empty($loginCountries[0]['slug'])): ?>
                        <input type="hidden" name="country_slug" value="<?php echo htmlspecialchars((string)$loginCountries[0]['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($agencySelectOptions)): ?>
                        <div class="mb-3 text-start">
                            <label for="login-agency-id" class="form-label small text-muted mb-1">Agency</label>
                            <select name="agency_id" id="login-agency-id" class="form-select" required>
                                <option value="" selected disabled>Select your agency…</option>
                                <?php foreach ($agencySelectOptions as $ar): ?>
                                <option value="<?php echo (int)$ar['agency_id']; ?>"<?php echo ($urlAgencyIdPre > 0 && (int)$ar['agency_id'] === $urlAgencyIdPre) ? ' selected' : ''; ?>><?php echo htmlspecialchars($ar['agency_name'] !== '' ? $ar['agency_name'] : ('Agency #' . (int)$ar['agency_id'])); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php elseif ($formHiddenAgencyId > 0): ?>
                        <input type="hidden" name="agency_id" value="<?php echo (int)$formHiddenAgencyId; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <input type="text" name="username" placeholder="Username" required 
                                   autocomplete="username" class="form-control">
                        </div>
                        <div class="mb-4">
                            <input type="password" name="password" placeholder="Password" required 
                                   autocomplete="current-password" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            Login
                        </button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="forgot-password.php" class="link-primary small">Forgot Password?</a>
                    </div>
                </div>
                
                <!-- Fingerprint Form -->
                <div id="fingerprint-form" class="text-center d-none">
                    <div class="mb-4">
                        <i class="fas fa-fingerprint text-success icon-3em mb-2"></i>
                        <h3 class="mb-2">Fingerprint Login</h3>
                        <p class="text-muted mb-4">Place your finger on the scanner to login</p>
                    </div>
                    <div id="fingerprint-status" class="mb-3 d-none"></div>
                    <div class="spinner-border text-success d-none" role="status" id="fingerprint-spinner">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php
    $chatWidgetPlaceholder = 'Ask about login, Workers… or: I need to talk to support';
    require_once __DIR__ . '/../includes/chat-widget-standalone-body.php';
    ?>

    <!-- Login Page JavaScript -->
    <?php
    $loginJsVersion = file_exists(__DIR__ . '/../js/login.js')
        ? filemtime(__DIR__ . '/../js/login.js')
        : time();
    ?>
    <script src="../js/login.js?v=<?php echo $loginJsVersion; ?>"></script>
</body>
</html>
