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

$input = json_decode(file_get_contents('php://input'), true);
$pointId = intval($input['id'] ?? 0);

if ($pointId < 60000) {
    echo json_encode(['success' => false, 'message' => 'Ugyldig punkt-ID']);
    exit;
}

$pending = [];
if (file_exists($PENDING_FILE)) {
    $pending = json_decode(file_get_contents($PENDING_FILE), true);
    if (!is_array($pending))
        $pending = [];
}

$foundIdx = -1;
foreach ($pending as $idx => $p) {
    if (($p['properties']['id'] ?? 0) === $pointId) {
        $foundIdx = $idx;
        break;
    }
}

if ($foundIdx === -1) {
    echo json_encode(['success' => false, 'message' => 'Punkt ikke funnet']);
    exit;
}

array_splice($pending, $foundIdx, 1);

if (file_put_contents($PENDING_FILE, json_encode($pending, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
    echo json_encode(['success' => true, 'message' => 'Punkt avvist og fjernet']);
} else {
    echo json_encode(['success' => false, 'message' => 'Feil ved lagring']);
}
?>