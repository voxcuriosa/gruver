<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Admin only
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$PENDING_FILE = 'pending_points.json';
$LOCAL_FILE = 'local_points.json';

$input = json_decode(file_get_contents('php://input'), true);
$pointId = intval($input['id'] ?? 0);

if ($pointId < 60000) {
    echo json_encode(['success' => false, 'message' => 'Ugyldig punkt-ID']);
    exit;
}

// Load pending
$pending = [];
if (file_exists($PENDING_FILE)) {
    $pending = json_decode(file_get_contents($PENDING_FILE), true);
    if (!is_array($pending))
        $pending = [];
}

// Find point
$found = null;
$foundIdx = -1;
foreach ($pending as $idx => $p) {
    if (($p['properties']['id'] ?? 0) === $pointId) {
        $found = $p;
        $foundIdx = $idx;
        break;
    }
}

if (!$found) {
    echo json_encode(['success' => false, 'message' => 'Punkt ikke funnet i ventekø']);
    exit;
}

// Remove pendingGuest flag, keep credit info from guest name
$guestInfo = $found['properties']['pendingGuest'] ?? null;
unset($found['properties']['pendingGuest']);

// If no credit set, use guest name
if (empty($found['properties']['credit']) && $guestInfo) {
    $found['properties']['credit'] = $guestInfo['name'];
}

// Reassign ID to admin range (50000+) 
$local = [];
if (file_exists($LOCAL_FILE)) {
    $local = json_decode(file_get_contents($LOCAL_FILE), true);
    if (!is_array($local))
        $local = [];
}

$maxId = 49999;
foreach ($local as $lp) {
    $lid = $lp['properties']['id'] ?? 0;
    if ($lid > $maxId)
        $maxId = $lid;
}
$found['properties']['id'] = $maxId + 1;

// Add to local
$local[] = $found;

// Remove from pending
array_splice($pending, $foundIdx, 1);

// Save both
$ok1 = file_put_contents($LOCAL_FILE, json_encode($local, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$ok2 = file_put_contents($PENDING_FILE, json_encode($pending, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

if ($ok1 && $ok2) {
    echo json_encode(['success' => true, 'message' => 'Punkt godkjent!', 'newId' => $found['properties']['id']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Feil ved lagring']);
}
?>