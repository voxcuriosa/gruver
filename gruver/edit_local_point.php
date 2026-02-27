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
    echo json_encode(['success' => false, 'message' => 'Kan bare redigere lokale punkter (ID >= 50000)']);
    exit;
}

// Allowed fields to update
$allowedFields = ['name', 'cleanDesc', 'catKey', 'lat', 'lng', 'links', 'credit', 'creditUrl', 'hidden', 'localImages', 'imageUrl'];

// Category → style mapping
$catStyles = [
    'GRUVE' => '#icon-1899-E65100-labelson',
    'HULE' => '#icon-1899-01579B-labelson',
    'BYGDEBORG' => '#icon-1899-9C27B0-labelson',
    'GAPAHUK' => '#icon-1899-795548-labelson',
    'VANN' => '#icon-1899-BDBDBD-labelson',
    'DIVERSE' => '#icon-1899-0288D1-labelson',
    'UTSIKT' => '#icon-1899-FFEA00-labelson',
    'HUSTUFT' => '#icon-1899-AFB42B-labelson',
    'GRENSESTEIN' => '#icon-1899-097138-labelson',
    'GRAVHAUG' => '#icon-1899-000000-labelson',
];

$local = [];
if (file_exists($LOCAL_FILE)) {
    $local = json_decode(file_get_contents($LOCAL_FILE), true);
    if (!is_array($local))
        $local = [];
}

$found = false;
foreach ($local as &$point) {
    if (($point['properties']['id'] ?? 0) === $pointId) {
        $props = &$point['properties'];

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $props[$field] = $input[$field];
            }
        }

        // Update geometry if coords changed
        if (isset($input['lat']) && isset($input['lng'])) {
            $point['geometry']['coordinates'] = [floatval($input['lng']), floatval($input['lat'])];
        }

        // Update styleUrl if catKey changed
        if (isset($input['catKey']) && isset($catStyles[$input['catKey']])) {
            $props['styleUrl'] = $catStyles[$input['catKey']];
        }

        $found = true;
        break;
    }
}

if (!$found) {
    echo json_encode(['success' => false, 'message' => 'Punkt med ID ' . $pointId . ' ikke funnet']);
    exit;
}

if (file_put_contents($LOCAL_FILE, json_encode($local, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
    echo json_encode(['success' => true, 'message' => 'Punkt oppdatert']);
} else {
    echo json_encode(['success' => false, 'message' => 'Feil ved lagring']);
}
?>