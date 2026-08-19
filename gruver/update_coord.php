<?php
session_start();
header('Content-Type: application/json');

// 1. Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 2. Input Validation
$input = json_decode(file_get_contents('php://input'), true);

// Fix: Allow identifying by 'name' OR 'id'
if ((!isset($input['id']) && !isset($input['name'])) || !isset($input['lat']) || !isset($input['lng'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters (id/name, lat, lng)']);
    exit;
}

$lat = floatval($input['lat']);
$lng = floatval($input['lng']);
$targetName = isset($input['name']) ? $input['name'] : null;
$targetId = isset($input['id']) ? $input['id'] : null;
$originalLat = isset($input['originalLat']) ? floatval($input['originalLat']) : null;
$originalLng = isset($input['originalLng']) ? floatval($input['originalLng']) : null;

// 3. Load Data
$jsonFile = 'full_data.json';
if (!file_exists($jsonFile)) {
    echo json_encode(['success' => false, 'message' => 'Data file not found']);
    exit;
}

$data = json_decode(file_get_contents($jsonFile), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// 4. Update Logic & Changelog
$updated = false;
$logEntry = null;

if (!isset($data['features']) || !is_array($data['features'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid GeoJSON structure (missing features)']);
    exit;
}

$features = &$data['features']; // Reference to modifiable array

foreach ($features as &$feature) {
    if (!isset($feature['properties']))
        continue;
    $props = &$feature['properties'];

    // Match by Name (preferred) or ID
    $match = false;
    // Check name
    if ($targetName && isset($props['name']) && $props['name'] === $targetName) {
        $match = true;
        // Secondary check: Location (if provided)
        if ($originalLat !== null && $originalLng !== null) {
            $curLat = isset($props['lat']) ? floatval($props['lat']) : 0;
            $curLng = isset($props['lng']) ? floatval($props['lng']) : 0;
            // Use epsilon for float comparison
            if (abs($curLat - $originalLat) > 0.0001 || abs($curLng - $originalLng) > 0.0001) {
                $match = false; // Location mismatch
            }
        }
    }
    // Check ID (frontend sends name now, but keep fallback)
    elseif ($targetId && isset($props['id']) && $props['id'] == $targetId) {
        $match = true;
    }

    if ($match) {
        // Prepare Log Entry
        $logEntry = [
            'timestamp' => time(),
            'name' => isset($props['name']) ? $props['name'] : 'Unknown',
            'oldLat' => isset($props['lat']) ? $props['lat'] : 0,
            'oldLng' => isset($props['lng']) ? $props['lng'] : 0,
            'newLat' => $lat,
            'newLng' => $lng
        ];

        // Update Properties
        $props['lat'] = $lat;
        $props['lng'] = $lng;

        // Update Geometry (GeoJSON standard: [lng, lat])
        if (!isset($feature['geometry'])) {
            $feature['geometry'] = ['type' => 'Point', 'coordinates' => []];
        }
        $feature['geometry']['coordinates'] = [$lng, $lat];

        $updated = true;
        break;
    }
}

if (!$updated) {
    // Debug logging to a file to see what went wrong
    $debugLog = "debug_admin_error.txt";
    $info = "Failed match. Target: " . ($targetName ? $targetName : "ID:$targetId") . "\n";
    $info .= "First 5 sites in DB (properties.name):\n";
    for ($i = 0; $i < min(5, count($features)); $i++) {
        $f = $features[$i];
        $info .= isset($f['properties']['name']) ? $f['properties']['name'] . "\n" : "No Name\n";
    }
    file_put_contents($debugLog, $info, FILE_APPEND);
}

if ($updated) {
    // 5. Save Data
    if (file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {

        // 6. Write to Changelog
        $logFile = 'changelog.json';
        $history = [];
        if (file_exists($logFile)) {
            $history = json_decode(file_get_contents($logFile), true);
            if (!is_array($history))
                $history = [];
        }
        $history[] = $logEntry;
        file_put_contents($logFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo json_encode(['success' => true, 'message' => 'Coordinates updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to write file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Site not found']);
}
?>