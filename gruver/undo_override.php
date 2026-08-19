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
if (!isset($input['timestamp']) && !isset($input['name'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$targetTimestamp = isset($input['timestamp']) ? intval($input['timestamp']) : null;
$targetName = $input['name'];

// 3. Update overrides.json
$overFile = 'overrides.json';
if (!file_exists($overFile)) {
    echo json_encode(['success' => false, 'message' => 'Overrides file not found']);
    exit;
}

$overrides = json_decode(file_get_contents($overFile), true);
if (!is_array($overrides)) {
    echo json_encode(['success' => false, 'message' => 'Invalid overrides data']);
    exit;
}

$newOverrides = [];
$found = false;
$removedOverride = null;
foreach ($overrides as $o) {
    // Match by name and timestamp (most unique combination)
    if ($o['name'] === $targetName && $o['timestamp'] === $targetTimestamp) {
        $found = true;
        $removedOverride = $o;
        continue; // Skip the one we want to remove
    }
    $newOverrides[] = $o;
}

if ($found) {
    if (file_put_contents($overFile, json_encode($newOverrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {

        // --- PATCH full_data.json immediately ---
        $dataFile = 'full_data.json';
        $patched = false;
        if (file_exists($dataFile)) {
            $data = json_decode(file_get_contents($dataFile), true);
            if ($data && isset($data['features'])) {
                foreach ($data['features'] as &$feature) {
                    $p = &$feature['properties'];
                    // Match by name and approximate coordinates
                    if (
                        $p['name'] === $removedOverride['name'] &&
                        round($p['lat'], 5) === round($removedOverride['lat'], 5) &&
                        round($p['lng'], 5) === round($removedOverride['lng'], 5)
                    ) {

                        if ($removedOverride['action'] === 'hide') {
                            $p['hidden'] = false;
                            $patched = true;
                        } elseif ($removedOverride['action'] === 'add_image') {
                            $val = $removedOverride['value'];
                            if (isset($p['images'])) {
                                $p['images'] = array_values(array_filter($p['images'], function ($v) use ($val) {
                                    return $v !== $val;
                                }));
                            }
                            if (isset($p['localImages'])) {
                                $p['localImages'] = array_values(array_filter($p['localImages'], function ($v) use ($val) {
                                    return $v !== $val;
                                }));
                            }
                            $patched = true;
                        }
                        break;
                    }
                }
                if ($patched) {
                    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
        }

        $msg = "Override for '$targetName' fjernet.";
        if ($removedOverride['action'] === 'delete') {
            $msg .= " Siden punktet ble slettet må du kjøre synkronisering for at det skal dukke opp igjen.";
        } else {
            $msg .= " Kartet er oppdatert.";
        }

        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to write overrides file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Override not found']);
}
?>