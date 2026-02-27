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
if (!isset($input['name']) || !isset($input['lat']) || !isset($input['lng']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$name = $input['name'];
$lat = floatval($input['lat']);
$lng = floatval($input['lng']);
$action = $input['action']; // 'hide', 'delete', 'add_image'
$value = isset($input['value']) ? $input['value'] : null;

// 3. Save to overrides.json
$overridesFile = 'overrides.json';
$overrides = [];
if (file_exists($overridesFile)) {
    $overrides = json_decode(file_get_contents($overridesFile), true);
    if (!is_array($overrides))
        $overrides = [];
}

$overrides[] = [
    'timestamp' => time(),
    'name' => $name,
    'lat' => $lat,
    'lng' => $lng,
    'action' => $action,
    'value' => $value
];

if (file_put_contents($overridesFile, json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {

    // 4. ALSO Patch full_data.json immediately for instant feedback
    $dataFile = 'full_data.json';
    if (file_exists($dataFile)) {
        $data = json_decode(file_get_contents($dataFile), true);
        if ($data && isset($data['features'])) {
            foreach ($data['features'] as $idx => &$feature) {
                $p = &$feature['properties'];
                if ($p['name'] === $name && abs($p['lat'] - $lat) < 0.0001 && abs($p['lng'] - $lng) < 0.0001) {
                    if ($action === 'hide') {
                        $p['hidden'] = true;
                    } elseif ($action === 'delete') {
                        array_splice($data['features'], $idx, 1);
                    } elseif ($action === 'add_image') {
                        if (!isset($p['images']))
                            $p['images'] = [];
                        if (!in_array($value, $p['images']))
                            $p['images'][] = $value;
                        if (!isset($p['localImages']))
                            $p['localImages'] = [];
                        if (!in_array($value, $p['localImages']))
                            $p['localImages'][] = $value;
                    } elseif ($action === 'rename' && is_array($value)) {
                        // Set or clear local display overrides
                        $p['displayName'] = !empty($value['displayName']) ? $value['displayName'] : null;
                        $p['displayDesc'] = !empty($value['displayDesc']) ? $value['displayDesc'] : null;
                    }
                    break;
                }
            }
            file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    // 5. ALSO Patch local_points.json (guest-added points)
    $localFile = 'local_points.json';
    if (file_exists($localFile)) {
        $localData = json_decode(file_get_contents($localFile), true);
        if (is_array($localData)) {
            $changed = false;
            foreach ($localData as $idx => &$feature) {
                $p = &$feature['properties'];
                if ($p['name'] === $name && abs($p['lat'] - $lat) < 0.0001 && abs($p['lng'] - $lng) < 0.0001) {
                    if ($action === 'hide') {
                        $p['hidden'] = true;
                        $changed = true;
                    } elseif ($action === 'delete') {
                        array_splice($localData, $idx, 1);
                        $changed = true;
                    } elseif ($action === 'rename' && is_array($value)) {
                        $p['displayName'] = !empty($value['displayName']) ? $value['displayName'] : null;
                        $p['displayDesc'] = !empty($value['displayDesc']) ? $value['displayDesc'] : null;
                        $changed = true;
                    }
                    break;
                }
            }
            if ($changed) {
                file_put_contents($localFile, json_encode(array_values($localData), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Override saved and data patched']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to write override file']);
}
?>