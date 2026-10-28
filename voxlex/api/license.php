<?php
/**
 * VoxLex Zero-Knowledge Lisens- og Gjenopprettings-API (GDPR Art. 9 & 25-sikker).
 * Lagrer ALDRI e-postadresser, navn eller personopplysninger i klartekst.
 * Bruker PBKDF2-HMAC-SHA256 med server-pepper for oppslag og AES-256-GCM
 * der brukerens egen e-post er dekrypteringsnøkkel.
 */
header('Content-Type: application/json; charset=utf-8');

$SERVER_PEPPER = 'VoxLex-ZK-Server-Pepper-2026-ChromoAS-991482';
$DATA_DIR = __DIR__ . '/../../voxlex_private_store';
if (!is_dir($DATA_DIR)) {
    @mkdir($DATA_DIR, 0700, true);
    @file_put_contents($DATA_DIR . '/.htaccess', "Deny from all\n");
}
$STORE_FILE = $DATA_DIR . '/zk_licenses.json';

function load_store(string $file): array {
    if (!file_exists($file)) return ['licenses' => [], 'recover_lookup' => [], 'ratelimit' => []];
    $raw = @file_get_contents($file);
    $data = $raw ? json_decode($raw, true) : null;
    return is_array($data) ? $data : ['licenses' => [], 'recover_lookup' => [], 'ratelimit' => []];
}

function save_store(string $file, array $store): void {
    @file_put_contents($file, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function zk_email_lookup_id(string $email, string $pepper): string {
    return hash_pbkdf2('sha256', strtolower(trim($email)), $pepper, 60000, 64);
}

function zk_encrypt_code(string $code, string $email, string $pepper): string {
    $key = hash('sha256', strtolower(trim($email)) . ':' . $pepper, true);
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($code, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $cipher);
}

function zk_decrypt_code(string $blob, string $email, string $pepper): ?string {
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 28) return null;
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $key = hash('sha256', strtolower(trim($email)) . ':' . $pepper, true);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $plain !== false ? $plain : null;
}

$action = $_POST['action'] ?? '';
$store = load_store($STORE_FILE);
$now = time();
$year_Now = date('Y');

if ($action === 'activate') {
    $key = strtoupper(trim($_POST['key'] ?? ''));
    $order_ref = strtoupper(trim($_POST['order_ref'] ?? ''));
    $hw_hash = trim($_POST['hw_hash'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));

    if ($key === '' || $hw_hash === '') {
        echo json_encode(['ok' => false, 'error' => 'Mangler lisenskode eller maskin-hash']);
        exit;
    }

    $lic_hash = hash('sha256', $key . ':' . $SERVER_PEPPER);
    $entry = $store['licenses'][$lic_hash] ?? [
        'orderRef' => $order_ref,
        'machines' => [],
        'year' => $year_Now,
        'activationsThisYear' => 0,
        'emailRegistered' => false,
    ];

    if (($entry['year'] ?? '') !== $year_Now) {
        $entry['year'] = $year_Now;
        $entry['activationsThisYear'] = 0;
    }

    $is_site = strpos($order_ref, 'SITE') === 0 || strpos($order_ref, 'KOMM') === 0;
    if (!$is_site && !in_array($hw_hash, $entry['machines'], true)) {
        if ($entry['activationsThisYear'] >= 5) {
            echo json_encode(['ok' => false, 'quotaExceeded' => true]);
            exit;
        }
        $entry['machines'][] = $hw_hash;
        if (count($entry['machines']) > 3) {
            array_shift($entry['machines']); // FIFO: nyeste PC erstatter eldste (maks 3 aktive)
        }
        $entry['activationsThisYear']++;
    }

    if ($email !== '' && strpos($email, '@') !== false) {
        $lookup_id = zk_email_lookup_id($email, $SERVER_PEPPER);
        $store['recover_lookup'][$lookup_id] = [
            'encryptedCode' => zk_encrypt_code($key, $email, $SERVER_PEPPER),
            'lastSentAt' => 0,
        ];
        $entry['emailRegistered'] = true;
        $subject = 'Din VoxLex-lisenskode (ta vare på ved PC-bytte)';
        $body = "Hei!\n\nDin VoxLex-lisens er nå aktivert.\n\nDin lisenskode er: {$key}\n\nHvis du bytter PC senere, kan du enten skrive inn denne koden direkte eller klikke 'Glemt kode ved PC-bytte?' inne i VoxLex.\n\nVennlig hilsen\nVoxLex / Chromo AS (https://voxcuriosa.no/voxlex/)";
        @mail($email, $subject, $body, "From: VoxLex <post@chromo.no>\r\nContent-Type: text/plain; charset=UTF-8");
    }

    $store['licenses'][$lic_hash] = $entry;
    save_store($STORE_FILE, $store);
    echo json_encode(['ok' => true, 'quotaExceeded' => false, 'emailRegistered' => $entry['emailRegistered']]);
    exit;
}

if ($action === 'recover') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $ip_hash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . ':' . date('Y-m-d-H'));
    $tries = $store['ratelimit'][$ip_hash] ?? 0;
    if ($tries < 3 && $email !== '' && strpos($email, '@') !== false) {
        $store['ratelimit'][$ip_hash] = $tries + 1;
        $lookup_id = zk_email_lookup_id($email, $SERVER_PEPPER);
        if (isset($store['recover_lookup'][$lookup_id])) {
            $rec = $store['recover_lookup'][$lookup_id];
            if ($now - ($rec['lastSentAt'] ?? 0) >= 3600) {
                $code = zk_decrypt_code($rec['encryptedCode'], $email, $SERVER_PEPPER);
                if ($code !== null) {
                    $store['recover_lookup'][$lookup_id]['lastSentAt'] = $now;
                    $subject = 'Din VoxLex-lisenskode for PC-bytte';
                    $body = "Hei!\n\nHer er lisenskoden som er knyttet til denne e-postadressen:\n\n{$code}\n\nLim den inn under Innstillinger -> Om VoxLex på den nye maskinen din.\n\nVennlig hilsen\nVoxLex / Chromo AS";
                    @mail($email, $subject, $body, "From: VoxLex <post@chromo.no>\r\nContent-Type: text/plain; charset=UTF-8");
                }
            }
        }
        save_store($STORE_FILE, $store);
    }
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false]);
