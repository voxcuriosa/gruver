<?php
if (!defined('VX_ADMIN_ENTRY')) {
    http_response_code(403);
    exit('Forbidden');
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/voxlex/admin',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$PBKDF2_SALT = 'VoxLex-Admin-PBKDF2-Salt-2026-v2#9184';
$PBKDF2_ITERS = 120000;
// Pre-computed PBKDF2-HMAC-SHA256 (120 000 iterations, 64 hex chars) – aldri klartekstpassord i kildekoden
$VALID_PBKDF2_HASHES = [
    'b5c57aed6c87911ef344c1082d612008ed52836b2a567a981618e9dd9dd32652',
    'e45d2eed42ba5bc4e093f6bd2bb2e5c807f0c9977f12675aee66c2ba7f9eb50a',
];

$MAX_ATTEMPTS = 5;
$LOCKOUT_SECONDS = 1800; // 30 minutter utestengelse etter 5 feilforsøk fra samme IP
$SESSION_IDLE_MAX = 1800; // 30 minutter automatisk utlogging ved inaktivitet

$DATA_DIR = __DIR__ . '/../../voxlex_private_store';
if (!is_dir($DATA_DIR)) {
    @mkdir($DATA_DIR, 0700, true);
    @file_put_contents($DATA_DIR . '/.htaccess', "Deny from all\n");
}
$RL_FILE = $DATA_DIR . '/admin_ratelimit.json';

function vx_load_rl(string $file, int $now, int $window): array {
    $raw = file_exists($file) ? @file_get_contents($file) : false;
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data)) $data = ['ips' => [], 'global' => []];
    foreach ($data['ips'] as $k => $tsList) {
        $filtered = array_values(array_filter((array)$tsList, fn($t) => ($now - (int)$t) < $window));
        if ($filtered) $data['ips'][$k] = $filtered; else unset($data['ips'][$k]);
    }
    $data['global'] = array_values(array_filter((array)($data['global'] ?? []), fn($t) => ($now - (int)$t) < 900));
    return $data;
}

function vx_save_rl(string $file, array $data): void {
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

$now = time();
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ipKey = hash('sha256', $clientIp . ':' . $PBKDF2_SALT);
$uaHash = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . ':' . $PBKDF2_SALT);

if (empty($_SESSION['vx_csrf'])) {
    $_SESSION['vx_csrf'] = bin2hex(random_bytes(24));
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

// Sjekk sesjonsutløp og User-Agent-binding
if (!empty($_SESSION['vx_admin_ok'])) {
    $lastActive = (int)($_SESSION['vx_last_active'] ?? 0);
    $savedUa = (string)($_SESSION['vx_ua_hash'] ?? '');
    if (($now - $lastActive) > $SESSION_IDLE_MAX || !hash_equals($savedUa, $uaHash)) {
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['vx_csrf'] = bin2hex(random_bytes(24));
    } else {
        $_SESSION['vx_last_active'] = $now;
    }
}

$loginError = '';
$rl = vx_load_rl($RL_FILE, $now, $LOCKOUT_SECONDS);
$ipFailures = count($rl['ips'][$ipKey] ?? []);
$globalFailures = count($rl['global'] ?? []);
// Kun IP-en som faktisk har feilet 5 ganger låses ute (slik at en bot fra andre IP-er aldri kan låse ute deg som eier)
$isLockedOut = ($ipFailures >= $MAX_ATTEMPTS);

if ($isLockedOut && empty($_SESSION['vx_admin_ok'])) {
    $oldest = (int)($rl['ips'][$ipKey][0] ?? $now);
    $minsLeft = max(1, (int)ceil(($LOCKOUT_SECONDS - ($now - $oldest)) / 60));
    http_response_code(429);
    $loginError = "For mange feilforsøk fra denne IP-adressen. Låst i {$minsLeft} minutt(er).";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pass'])) {
    $csrfOk = !empty($_POST['csrf']) && hash_equals($_SESSION['vx_csrf'], (string)$_POST['csrf']);
    if (!$csrfOk) {
        $loginError = 'Ugyldig sesjonsbekreftelse (CSRF). Last siden på nytt.';
    } else {
        $candidate = hash_pbkdf2('sha256', trim((string)$_POST['admin_pass']), $PBKDF2_SALT, $PBKDF2_ITERS, 64);
        $matched = false;
        foreach ($VALID_PBKDF2_HASHES as $validHash) {
            if (hash_equals($validHash, $candidate)) {
                $matched = true;
            }
        }
        if ($matched) {
            unset($rl['ips'][$ipKey]);
            vx_save_rl($RL_FILE, $rl);
            session_regenerate_id(true);
            $_SESSION['vx_admin_ok'] = true;
            $_SESSION['vx_last_active'] = $now;
            $_SESSION['vx_ua_hash'] = $uaHash;
            $_SESSION['vx_csrf'] = bin2hex(random_bytes(24));
            header('Location: index.php');
            exit;
        } else {
            // Ved høy global pågang økes forsinkelsen kun på FEIL passord (riktig passord fra din IP slipper alltid rett inn)
            $delayMicros = $globalFailures >= 15 ? 1800000 : 450000;
            usleep($delayMicros);
            $rl['ips'][$ipKey][] = $now;
            $rl['global'][] = $now;
            vx_save_rl($RL_FILE, $rl);
            $left = max(0, $MAX_ATTEMPTS - count($rl['ips'][$ipKey]));
            $loginError = $left > 0
                ? "Feil admin-passord ({$left} forsøk gjenstår før 30 min låsing)."
                : 'For mange feilforsøk. IP-adressen er nå låst i 30 minutter.';
        }
    }
}

$isAuthed = !empty($_SESSION['vx_admin_ok']);
