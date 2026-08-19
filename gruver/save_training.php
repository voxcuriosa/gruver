<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['category']) || !isset($input['geojson'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$category = $input['category'];
$geojson = $input['geojson'];

// Path safe check
$allowed_categories = ['kullmiler', 'gruver'];
if (!in_array($category, $allowed_categories)) {
    echo json_encode(['success' => false, 'error' => 'Invalid category']);
    exit;
}

$filename = 'data/trening/' . $category . '/' . time() . '_' . uniqid() . '.json';
$dir = dirname($filename);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

if (file_put_contents($filename, json_encode($geojson, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true, 'file' => $filename]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to write file']);
}
?>