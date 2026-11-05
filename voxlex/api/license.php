<?php
/**
 * VoxLex Zero-Knowledge Lisens-API (100 % uten e-post eller personopplysninger).
 * Lagrer ALDRI e-postadresser, navn eller personopplysninger.
 */
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(204);
    exit;
}

$SERVER_PEPPER = "VoxLex-ZK-Server-Pepper-2026-ChromoAS-991482";
$DATA_DIR = __DIR__ . "/../../voxlex_private_store";
if (!is_dir($DATA_DIR)) {
    @mkdir($DATA_DIR, 0700, true);
    @file_put_contents($DATA_DIR . "/.htaccess", "Deny from all\n");
}
$STORE_FILE = $DATA_DIR . "/zk_licenses.json";

function load_store(string $file): array {
    $default = ["licenses" => [], "trials" => []];
    if (!file_exists($file)) return $default;
    $raw = @file_get_contents($file);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data)) return $default;
    unset($data["recover_lookup"], $data["ratelimit"]);
    if (!isset($data["trials"]) || !is_array($data["trials"])) $data["trials"] = [];
    if (!isset($data["licenses"]) || !is_array($data["licenses"])) $data["licenses"] = [];
    return $data;
}

function save_store(string $file, array $store): void {
    unset($store["recover_lookup"], $store["ratelimit"]);
    @file_put_contents($file, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

$action = $_POST["action"] ?? "";
$store = load_store($STORE_FILE);
$now = time();
$year_Now = date("Y");

if ($action === "trial_sync") {
    $hw_hash = trim($_POST["hw_hash"] ?? "");
    $client_ts = (int)($_POST["client_ts"] ?? 0);
    if ($hw_hash === "" || strlen($hw_hash) < 8) {
        echo json_encode(["ok" => false]);
        exit;
    }
    $min_valid_ts = 1761955200; // 2025-11-01
    $sanitized_client_ts = ($client_ts >= $min_valid_ts && $client_ts <= ($now + 3600)) ? $client_ts : $now;
    $trial_id = hash("sha256", "trial:" . $hw_hash . ":" . $SERVER_PEPPER);

    if (isset($store["trials"][$trial_id]["startedAt"])) {
        $saved_ts = (int)$store["trials"][$trial_id]["startedAt"];
        $earliest_ts = ($saved_ts >= $min_valid_ts) ? min($saved_ts, $sanitized_client_ts) : $sanitized_client_ts;
    } else {
        $earliest_ts = $sanitized_client_ts;
    }
    if (!isset($store["trials"][$trial_id]) || $store["trials"][$trial_id]["startedAt"] !== $earliest_ts) {
        $store["trials"][$trial_id] = ["startedAt" => $earliest_ts, "updatedAt" => $now];
        save_store($STORE_FILE, $store);
    }
    echo json_encode(["ok" => true, "trialStartedAt" => $earliest_ts, "serverNow" => $now]);
    exit;
}

if ($action === "activate") {
    $key = strtoupper(trim($_POST["key"] ?? ""));
    $order_ref = strtoupper(trim($_POST["order_ref"] ?? ""));
    $hw_hash = trim($_POST["hw_hash"] ?? "");

    if ($key === "" || $hw_hash === "") {
        echo json_encode(["ok" => false, "error" => "Mangler lisenskode eller maskin-hash"]);
        exit;
    }

    $lic_hash = hash("sha256", $key . ":" . $SERVER_PEPPER);
    $entry = $store["licenses"][$lic_hash] ?? [
        "orderRef" => $order_ref,
        "machines" => [],
        "year" => $year_Now,
        "activationsThisYear" => 0,
        "emailRegistered" => false,
    ];
    $entry["emailRegistered"] = false;

    if (($entry["year"] ?? "") !== $year_Now) {
        $entry["year"] = $year_Now;
        $entry["activationsThisYear"] = 0;
    }

    $is_site = strpos($order_ref, "SITE") === 0 || strpos($order_ref, "KOMM") === 0;
    if (!$is_site && !in_array($hw_hash, $entry["machines"], true)) {
        if ($entry["activationsThisYear"] >= 5) {
            echo json_encode(["ok" => false, "quotaExceeded" => true]);
            exit;
        }
        $entry["machines"][] = $hw_hash;
        if (count($entry["machines"]) > 3) {
            array_shift($entry["machines"]); // FIFO: nyeste PC erstatter eldste (maks 3 aktive)
        }
        $entry["activationsThisYear"]++;
    }

    $store["licenses"][$lic_hash] = $entry;
    save_store($STORE_FILE, $store);
    echo json_encode(["ok" => true, "quotaExceeded" => false, "emailRegistered" => false]);
    exit;
}

echo json_encode(["ok" => false]);
