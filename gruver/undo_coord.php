<?php
session_start();
header('Content-Type: application/json');

// 1. Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 2. Load History
$logFile = 'changelog.json';
if (!file_exists($logFile)) {
    echo json_encode(['success' => false, 'message' => 'Ingen endringer å angre (logg mangler)']);
    exit;
}

$history = json_decode(file_get_contents($logFile), true);
if (empty($history) || !is_array($history)) {
    echo json_encode(['success' => false, 'message' => 'Ingen endringer å angre']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$targetTimestamp = isset($input['timestamp']) ? intval($input['timestamp']) : null;
$targetName = isset($input['name']) ? $input['name'] : null;

// 3. Find and Remove Entry
$lastChange = null;
$newHistory = [];
$found = false;

if ($targetTimestamp && $targetName) {
    // Specific match (robust)
    foreach ($history as $h) {
        if (!$found && $h['name'] === $targetName && $h['timestamp'] === $targetTimestamp) {
            $lastChange = $h;
            $found = true;
            continue;
        }
        $newHistory[] = $h;
    }
} else {
    // Legacy behavior: Pop Last Entry
    $lastChange = array_pop($history);
    $newHistory = $history;
    $found = ($lastChange !== null);
}

if (!$found || !$lastChange) {
    echo json_encode(['success' => false, 'message' => 'Kunne ikke finne endringen som skal angres']);
    exit;
}

$history = $newHistory; // Update history with entry removed
$targetName = $lastChange['name'];
$restoreLat = $lastChange['oldLat'];
$restoreLng = $lastChange['oldLng'];

// 4. Load Data to Revert
$jsonFile = 'full_data.json';
$data = json_decode(file_get_contents($jsonFile), true);

if (!isset($data['features']) || !is_array($data['features'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid GeoJSON structure']);
    exit;
}

$features = &$data['features'];
$reverted = false;

foreach ($features as &$feature) {
    if (!isset($feature['properties']))
        continue;
    $props = &$feature['properties'];

    if (isset($props['name']) && $props['name'] === $targetName) {

        // Secondary check: Current Location matches 'newLat'/'newLng' from log
        // (Because we are undoing FROM that state)
        if (isset($lastChange['newLat']) && isset($lastChange['newLng'])) {
            $curLat = isset($props['lat']) ? floatval($props['lat']) : 0;
            $curLng = isset($props['lng']) ? floatval($props['lng']) : 0;
            $targetLat = floatval($lastChange['newLat']);
            $targetLng = floatval($lastChange['newLng']);

            if (abs($curLat - $targetLat) > 0.0001 || abs($curLng - $targetLng) > 0.0001) {
                continue; // Location mismatch
            }
        }

        // Revert Properties
        $props['lat'] = $restoreLat;
        $props['lng'] = $restoreLng;

        // Revert Geometry
        if (!isset($feature['geometry'])) {
            $feature['geometry'] = ['type' => 'Point', 'coordinates' => []];
        }
        $feature['geometry']['coordinates'] = [$restoreLng, $restoreLat];

        $reverted = true;
        break;
    }
}

if ($reverted) {
    // 5. Save both files
    if (file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        file_put_contents($logFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo json_encode([
            'success' => true,
            'message' => "Angret endring på '$targetName'",
            'name' => $targetName,
            'lat' => $restoreLat,
            'lng' => $restoreLng,
            'remaining' => count($history)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Kunne ikke lagre tilbakedata']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Fant ikke punktet som skulle angres i databasen']);
}
?>