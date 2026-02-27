<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Admin only
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$LOCAL_FILE = 'local_points.json';

$input = json_decode(file_get_contents('php://input'), true);
$pointId = intval($input['id'] ?? 0);

if ($pointId < 50000) {
    echo json_encode(['success' => false, 'message' => 'Kan bare slette lokale punkter (ID >= 50000)']);
    exit;
}

$local = [];
if (file_exists($LOCAL_FILE)) {
    $local = json_decode(file_get_contents($LOCAL_FILE), true);
    if (!is_array($local))
        $local = [];
}

$foundIdx = -1;
$foundName = '';
foreach ($local as $idx => $p) {
    if (($p['properties']['id'] ?? 0) === $pointId) {
        $foundIdx = $idx;
        $foundName = $p['properties']['name'] ?? 'Ukjent';
        break;
    }
}

if ($foundIdx === -1) {
    echo json_encode(['success' => false, 'message' => 'Punkt ikke funnet']);
    exit;
}

array_splice($local, $foundIdx, 1);

if (file_put_contents($LOCAL_FILE, json_encode($local, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
    echo json_encode(['success' => true, 'message' => "\"$foundName\" er slettet"]);
} else {
    echo json_encode(['success' => false, 'message' => 'Feil ved lagring']);
}
?>