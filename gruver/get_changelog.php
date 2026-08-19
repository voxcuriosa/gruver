<?php
session_start();
header('Content-Type: application/json');

// 1. Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$history = [];
$logFile = 'changelog.json';
if (file_exists($logFile)) {
    $hist = json_decode(file_get_contents($logFile), true);
    if (is_array($hist)) {
        foreach ($hist as $entry) {
            $entry['type'] = 'coord';
            $history[] = $entry;
        }
    }
}

$overFile = 'overrides.json';
if (file_exists($overFile)) {
    $overs = json_decode(file_get_contents($overFile), true);
    if (is_array($overs)) {
        foreach ($overs as $idx => $entry) {
            $entry['type'] = 'override';
            $entry['overrideIndex'] = $idx;
            $history[] = $entry;
        }
    }
}

// Sort by timestamp (newest first)
usort($history, function ($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

echo json_encode(['success' => true, 'log' => $history]);
?>