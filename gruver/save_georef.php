<?php
/**
 * save_georef.php - Saves georeferencing metadata and publishes to main map
 */

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
// Logge rå-input for feilsøking
file_put_contents('debug_save.log', date('Y-m-d H:i:s') . " - Received: " . $rawInput . "\n", FILE_APPEND);

$input = json_decode($rawInput, true);
if (!$input) {
    die(json_encode(['success' => false, 'error' => 'Invalid input']));
}

$action = isset($input['action']) ? $input['action'] : 'save';
$name = isset($input['name']) ? $input['name'] : 'Uten navn';
if ($action === 'update_layers') {
    $layers = isset($input['layers']) ? $input['layers'] : null;
    if (!$layers || !is_array($layers)) {
        die(json_encode(['success' => false, 'error' => 'Missing layers array']));
    }

    $publishedFile = 'published_maps.json';
    if (file_put_contents($publishedFile, json_encode($layers, JSON_PRETTY_PRINT))) {
        file_put_contents('debug_save.log', date('Y-m-d H:i:s') . " - Bulk updated $publishedFile\n", FILE_APPEND);
        echo json_encode(['success' => true]);
        exit;
    } else {
        die(json_encode(['success' => false, 'error' => 'FAILED to update published_maps.json']));
    }
}

$imagePath = isset($input['imagePath']) ? $input['imagePath'] : null;
$corners = isset($input['corners']) ? $input['corners'] : null;

if (!$imagePath || !$corners) {
    die(json_encode(['success' => false, 'error' => 'Missing data (imagePath or corners)']));
}

// Ensure metadata file exists for this individual image
$metaFile = $imagePath . ".json";
if (file_put_contents($metaFile, json_encode($input, JSON_PRETTY_PRINT))) {
    file_put_contents('debug_save.log', date('Y-m-d H:i:s') . " - Wrote meta file: $metaFile\n", FILE_APPEND);
} else {
    file_put_contents('debug_save.log', date('Y-m-d H:i:s') . " - FAILED to write meta file: $metaFile\n", FILE_APPEND);
}

if ($action === 'publish') {
    $publishedFile = 'published_maps.json';
    $publishedData = [];

    if (file_exists($publishedFile)) {
        $publishedData = json_decode(file_get_contents($publishedFile), true);
        if (!$publishedData)
            $publishedData = [];
    }

    // Check if already exists (update) or append
    $found = false;
    foreach ($publishedData as &$map) {
        if ($map['imagePath'] === $imagePath) {
            $map['name'] = $name;
            $map['attributionName'] = isset($input['attributionName']) ? $input['attributionName'] : '';
            $map['attributionLink'] = isset($input['attributionLink']) ? $input['attributionLink'] : '';
            $map['isAdminOnly'] = isset($input['isAdminOnly']) ? (bool) $input['isAdminOnly'] : false;
            $map['corners'] = $corners;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $publishedData[] = [
            'name' => $name,
            'attributionName' => isset($input['attributionName']) ? $input['attributionName'] : '',
            'attributionLink' => isset($input['attributionLink']) ? $input['attributionLink'] : '',
            'isAdminOnly' => isset($input['isAdminOnly']) ? (bool) $input['isAdminOnly'] : false,
            'imagePath' => $imagePath,
            'corners' => $corners,
            'timestamp' => time()
        ];
    }

    if (file_put_contents($publishedFile, json_encode($publishedData, JSON_PRETTY_PRINT))) {
        file_put_contents('debug_save.log', date('Y-m-d H:i:s') . " - Updated $publishedFile\n", FILE_APPEND);
    } else {
        file_put_contents('debug_save.log', date('Y-m-d H:i:s') . " - FAILED to update $publishedFile\n", FILE_APPEND);
    }
}

echo json_encode(['success' => true]);
?>