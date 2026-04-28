<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/ngenius.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/ngenius.php`.
 */

declare(strict_types=1);

/**
 * N-Genius (Network International) — shared HTTP helpers.
 *
 * Identity token: POST /identity/auth/access-token
 * NI OpenAPI lists realmName; some guides use realm; Paypage samples used {"realmName":"ni"} only.
 * KSA hosted-session sample (NI): POST body {} (empty object), Authorization: Basic <portal key as one string>.
 * We try {} first, then grant_type/realm variants. Identity rejects application/x-www-form-urlencoded with HTTP 415.
 * Authorization: Basic … — with secret: base64(api_key:api_secret). Single key: base64(api_key:) (OAuth), then raw (NI samples), then base64(api_key).
 *
 * Live-only defaults for Saudi (KSA) merchants: api-gateway.ksa.ngenius-payments.com.
 * Identity and orders must use the same live environment.
 */

const NGENIUS_DEFAULT_API_BASE_KSA = 'https://api-gateway.ksa.ngenius-payments.com';

/**
 * Trim BOM/quotes/whitespace from portal copy-paste (.env, secrets file).
 */
function ngenius_sanitize_credential_string(string $value): string
{
    $value = str_replace("\xEF\xBB\xBF", '', $value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = trim($value);
    $value = trim($value, " \t\n\r\0\x0B\"'");
    if (stripos($value, 'basic ') === 0) {
        $value = trim(substr($value, 6));
    }
    // Portal/cPanel copy-paste sometimes stores URL-encoded credentials (%2B, %2F, %3D).
    if (preg_match('/%[0-9A-Fa-f]{2}/', $value)) {
        $decoded = rawurldecode($value);
        if (is_string($decoded) && trim($decoded) !== '') {
            $value = trim($decoded);
        }
    }
    // If key is base64-like, convert accidental spaces back to '+'.
    if (strpos($value, ':') === false && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $value)) {
        $value = str_replace(' ', '+', $value);
    }

    return $value;
}

/**
 * Safe diagnostics about API key shape (never returns secret/value).
 *
 * @return array<string, int|bool|string>
 */
function ngenius_api_key_shape(string $apiKey): array
{
    $trimmed = trim($apiKey);
    $compact = str_replace(["\n", "\r", "\t", ' '], '', $trimmed);
    $isBase64Chars = $compact !== '' && (bool) preg_match('/^[A-Za-z0-9+\/]+=*$/', $compact);
    $decoded = $isBase64Chars ? base64_decode($compact, true) : false;
    $decodedLooksPair = is_string($decoded) && strpos($decoded, ':') !== false;

    return [
        'length' => strlen($trimmed),
        'has_colon' => strpos($trimmed, ':') !== false,
        'has_ampersand' => strpos($trimmed, '&') !== false,
        'has_whitespace' => preg_match('/\s/', $trimmed) === 1,
        'looks_base64' => $isBase64Chars,
        'base64_decodes_to_pair' => $decodedLooksPair,
        'starts_with_basic' => stripos($trimmed, 'basic ') === 0,
    ];
}

/**
 * Short text from identity JSON error body for alerts and logs.
 */
function ngenius_identity_response_hint(string $body): string
{
    $body = trim($body);
    if ($body === '') {
        return '';
    }
    $d = json_decode($body, true);
    if (!is_array($d)) {
        return strlen($body) > 220 ? substr($body, 0, 220) . '…' : $body;
    }
    $chunks = [];
    if (isset($d['message']) && is_string($d['message']) && $d['message'] !== '') {
        $chunks[] = $d['message'];
    }
    if (isset($d['errors']) && is_array($d['errors'])) {
        foreach ($d['errors'] as $err) {
            if (!is_array($err)) {
                continue;
            }
            foreach (['localizedMessage', 'message'] as $k) {
                if (!empty($err[$k]) && is_string($err[$k])) {
                    $chunks[] = $err[$k];
                    break;
                }
            }
            if (!empty($err['errorCode']) && is_string($err['errorCode'])) {
                $chunks[] = '[' . $err['errorCode'] . ']';
            }
            break;
        }
    }
    $chunks = array_values(array_unique(array_filter($chunks)));
    if ($chunks === []) {
        $enc = json_encode($d, JSON_UNESCAPED_SLASHES);
        if (is_string($enc) && $enc !== '') {
            return strlen($enc) > 240 ? substr($enc, 0, 240) . '…' : $enc;
        }

        return strlen($body) > 220 ? substr($body, 0, 220) . '…' : $body;
    }

    return implode(' ', $chunks);
}

/**
 * Credential string after "Authorization: Basic ".
 * With secret: standard OAuth client form base64(api_key:api_secret).
 * Secret empty: raw service-account key only (see network-international/sample-merchant-server-php).
 */
function ngenius_basic_authorization_value(string $apiKey, string $apiSecret): string
{
    $apiKey = trim($apiKey);
    $apiSecret = trim($apiSecret);
    if ($apiSecret !== '') {
        return base64_encode($apiKey . ':' . $apiSecret);
    }

    return $apiKey;
}

/**
 * Values to send after "Authorization: Basic " for identity token (try in order when HTTP 400).
 * With secret: base64(key:secret). Single value from portal: raw first (KSA sample is often a pre-built base64 blob — do not re-encode). Then OAuth-style base64(key:), then base64(whole key). Plaintext "id:secret" → base64 line first, then raw.
 *
 * @return list<string>
 */
function ngenius_identity_basic_credentials(string $apiKey, string $apiSecret): array
{
    $apiKey = trim($apiKey);
    $apiSecret = trim($apiSecret);
    if ($apiSecret !== '') {
        $out = [base64_encode($apiKey . ':' . $apiSecret)];
        if (strpos($apiKey, ':') === false && preg_match('/^[A-Za-z0-9+\/]+=*$/', str_replace(["\n", "\r", ' '], '', $apiKey))) {
            array_unshift($out, $apiKey);
        }

        return array_values(array_unique($out));
    }

    $out = [];
    $compactKey = str_replace(["\n", "\r", ' '], '', $apiKey);
    $decodedPair = base64_decode($compactKey, true);
    if (
        is_string($decodedPair)
        && strpos($decodedPair, ':') !== false
        && preg_match('/^[^\s:]+:[^\s:]+$/s', $decodedPair)
    ) {
        [$did, $dsec] = explode(':', $decodedPair, 2);
        $did = trim($did);
        $dsec = trim($dsec);
        if ($did !== '' && $dsec !== '') {
            $normalized = base64_encode($did . ':' . $dsec);
            $out[] = $compactKey;
            if ($normalized !== $compactKey) {
                $out[] = $normalized;
            }
        }
    }

    if (strpos($apiKey, ':') !== false) {
        $out[] = base64_encode($apiKey);
        $out[] = $apiKey;
    } else {
        $out[] = $apiKey;
        $out[] = base64_encode($apiKey . ':');
        $b64Whole = base64_encode($apiKey);
        if ($b64Whole !== $apiKey) {
            $out[] = $b64Whole;
        }
    }

    return array_values(array_unique($out));
}

/**
 * Headers for POST access-token.
 *
 * @return list<string>
 */
function ngenius_identity_token_headers_for_basic(string $basicPayload): array
{
    return [
        'Accept: application/vnd.ni-identity.v1+json',
        'Content-Type: application/vnd.ni-identity.v1+json',
        'Authorization: Basic ' . $basicPayload,
    ];
}

/**
 * NI KSA PHP sample: only Authorization + Content-Type (no Accept).
 *
 * @return list<list<string>>
 */
function ngenius_identity_token_header_variants(string $basicPayload): array
{
    return [
        [
            'Authorization: Basic ' . $basicPayload,
            'Content-Type: application/vnd.ni-identity.v1+json',
        ],
        [
            'Accept: application/vnd.ni-identity.v1+json',
            'Content-Type: application/vnd.ni-identity.v1+json',
            'Authorization: Basic ' . $basicPayload,
        ],
    ];
}

/**
 * @deprecated Use ngenius_identity_token_headers_for_basic(ngenius_basic_authorization_value(...)) if needed.
 */
function ngenius_identity_token_headers(string $apiKey, string $apiSecret): array
{
    return ngenius_identity_token_headers_for_basic(ngenius_basic_authorization_value($apiKey, $apiSecret));
}

/**
 * @return array{status:int, error:string, body:string}
 */
function ngenius_http_request(string $method, string $url, array $headers, ?string $body = null): array
{
    if (trim($url) === '') {
        return [
            'status' => 0,
            'error' => 'empty URL',
            'body' => '',
        ];
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return [
            'status' => 0,
            'error' => 'curl_init failed',
            'body' => '',
        ];
    }

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        // Keep checkout responsive when NI identity is slow/unreachable.
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if (strtoupper($method) === 'POST') {
        $opts[CURLOPT_POST] = true;
    }
    curl_setopt_array($ch, $opts);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $output = curl_exec($ch);
    $error = curl_errno($ch) ? (string) curl_error($ch) : '';
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $status,
        'error' => $error,
        'body' => $output === false ? '' : (string) $output,
    ];
}

/**
 * JSON bodies for identity access-token (realm-specific variants use $realm).
 *
 * @return list<object|array<string, string>>
 */
function ngenius_identity_token_body_variants(string $realm): array
{
    // KSA: {} then realmName (Paypage) before grant_type-only — grant_type alone often yields badTokenRequest.
    return [
        new stdClass(),
        ['realmName' => $realm],
        ['grant_type' => 'client_credentials', 'realmName' => $realm],
        ['grant_type' => 'client_credentials', 'realm' => $realm],
        ['grant_type' => 'client_credentials'],
        ['grantType' => 'client_credentials', 'realmName' => $realm],
        [
            'grant_type' => 'client_credentials',
            'realmName' => $realm,
            'realm' => $realm,
        ],
    ];
}

/**
 * Realms to try (configured first). Live KSA realm is networkinternational.
 *
 * @return list<string>
 */
function ngenius_identity_realm_candidates(string $configuredRealm): array
{
    $configuredRealm = trim($configuredRealm) !== '' ? trim($configuredRealm) : 'networkinternational';
    $out = [$configuredRealm];
    foreach (['networkinternational'] as $alt) {
        if (!in_array($alt, $out, true)) {
            $out[] = $alt;
        }
    }

    return $out;
}

/**
 * @return array{ok:bool, access_token:string, http_status:int, curl_error:string, body:string}
 */
function ngenius_fetch_access_token(string $identityBase, string $apiKey, string $apiSecret, ?string $tokenUrl = null, string $realm = 'ni'): array
{
    $apiKey = ngenius_sanitize_credential_string($apiKey);
    $apiSecret = ngenius_sanitize_credential_string($apiSecret);

    $url = ($tokenUrl !== null && trim($tokenUrl) !== '')
        ? trim($tokenUrl)
        : (rtrim($identityBase, '/') . '/identity/auth/access-token');
    $realm = trim($realm) !== '' ? trim($realm) : 'ni';

    $parseToken = static function (string $body): string {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return '';
        }

        return (string) ($data['access_token'] ?? $data['accessToken'] ?? '');
    };

    $fail = function (int $status, string $curlErr, string $body) use ($apiKey): array {
        return [
            'ok' => false,
            'access_token' => '',
            'http_status' => $status,
            'curl_error' => $curlErr,
            'body' => $body,
            'api_key' => $apiKey,
        ];
    };

    $realmRetryBody = static function (string $body): bool {
        return stripos($body, 'realmNameNotAvailable') !== false;
    };
    $badTokenBody = static function (string $body): bool {
        return stripos($body, 'badTokenRequest') !== false;
    };

    $lastRes = ['status' => 0, 'error' => '', 'body' => ''];
    $basicPayloads = ngenius_identity_basic_credentials($apiKey, $apiSecret);
    $realmCandidates = ngenius_identity_realm_candidates($realm);

    foreach ($basicPayloads as $basicPayload) {
        foreach (ngenius_identity_token_header_variants($basicPayload) as $headers) {
            foreach ($realmCandidates as $tryRealm) {
                foreach (ngenius_identity_token_body_variants($tryRealm) as $variant) {
                    $payload = json_encode($variant, JSON_UNESCAPED_SLASHES);
                    if ($payload === false) {
                        return $fail(0, 'json_encode failed for token payload', '');
                    }
                    $res = ngenius_http_request('POST', $url, $headers, $payload);
                    $lastRes = $res;

                    if ($res['error'] !== '') {
                        return $fail($res['status'], $res['error'], $res['body']);
                    }

                    if ($res['status'] >= 200 && $res['status'] < 300) {
                        $token = $parseToken($res['body']);
                        if ($token !== '') {
                            return [
                                'ok' => true,
                                'access_token' => $token,
                                'http_status' => $res['status'],
                                'curl_error' => '',
                                'body' => $res['body'],
                            ];
                        }
                        continue;
                    }

                    if ($res['status'] === 400) {
                        // Credentials are invalid/misformatted; retries won't help and only delay UX.
                        if ($badTokenBody($res['body'])) {
                            return $fail($res['status'], '', $res['body']);
                        }
                        continue;
                    }
                    if ($res['status'] === 404 && $realmRetryBody($res['body'])) {
                        continue;
                    }

                    return $fail($res['status'], '', $res['body']);
                }
            }
        }
    }

    return [
        'ok' => false,
        'access_token' => '',
        'http_status' => (int) ($lastRes['status'] ?? 0),
        'curl_error' => (string) ($lastRes['error'] ?? ''),
        'body' => (string) ($lastRes['body'] ?? ''),
        'api_key' => $apiKey,
    ];
}

/**
 * Human-readable JSON for clients when identity token request fails (HTTP 502 from our API).
 *
 * @param array{ok?:bool,http_status?:int,curl_error?:string,body?:string} $tokenRes
 * @return array{message:string,http_status?:int,curl_error?:string,body_excerpt?:string}
 */
function ngenius_token_failure_client_payload(array $tokenRes): array
{
    $http = (int) ($tokenRes['http_status'] ?? 0);
    $curlErr = trim((string) ($tokenRes['curl_error'] ?? ''));
    $msg = 'Failed to authenticate with the N-Genius payment gateway.';
    if ($curlErr !== '') {
        $msg .= ' Connection error: ' . $curlErr;
    } elseif ($http > 0) {
        $msg .= ' HTTP ' . $http . '.';
        if ($http === 401 || $http === 403) {
            $msg .= ' Usually wrong API key/secret for live KSA, or wrong outlet/realm linkage. Match config/env.php NGENIUS_*_BASE and NGENIUS_TOKEN_URL to your live portal environment.';
        } elseif ($http === 404) {
            $msg .= ' Wrong live path/URL, or wrong NGENIUS_REALM for live KSA (expected: networkinternational).';
        } elseif ($http === 415) {
            $msg .= ' Unsupported Content-Type for this token URL (identity expects application/vnd.ni-identity.v1+json). Check NGENIUS_TOKEN_URL points to .../identity/auth/access-token on the same environment as your API key.';
        } elseif ($http === 400) {
            $msg .= ' Check payment_config.token_url below (must be KSA live from NI). If it is wrong, remove stale NGENIUS_* from cPanel Environment Variables — they override config files. Paste API key exactly as NI shows. KSA docs: https://docs.ksa.ngenius-payments.com/';
        } elseif ($http >= 500) {
            $msg .= ' N-Genius may be temporarily unavailable; retry later.';
        }
    } else {
        $msg .= ' No access token returned. Confirm API key and secret; full response is in logs/payment.log.';
    }

    $body = (string) ($tokenRes['body'] ?? '');
    $hint = ngenius_identity_response_hint($body);
    if ($hint !== '') {
        $msg .= ' ' . $hint;
    }

    $out = ['message' => $msg, 'http_status' => $http];
    if ($curlErr !== '') {
        $out['curl_error'] = $curlErr;
    }
    if ($body !== '' && $http >= 400 && $http < 500) {
        $out['identity_error'] = substr($body, 0, 800);
    }
    if (
        $http === 400
        && stripos($body, 'badTokenRequest') !== false
        && isset($tokenRes['api_key'])
        && is_string($tokenRes['api_key'])
    ) {
        $out['credential_hint'] = ngenius_api_key_shape((string) $tokenRes['api_key']);
    }
    $debugOn = getenv('RATIB_PAYMENT_DEBUG') === '1'
        || (isset($_SERVER['RATIB_PAYMENT_DEBUG']) && (string) $_SERVER['RATIB_PAYMENT_DEBUG'] === '1');
    if ($debugOn && $body !== '') {
        $out['body_excerpt'] = substr($body, 0, 400);
    }

    return $out;
}
