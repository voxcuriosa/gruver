<?php
header('Content-Type: application/json');

// Load configuration
if (!file_exists(__DIR__ . '/config.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration file missing.']);
    exit;
}

require_once __DIR__ . '/config.php';

// Get JSON input
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

// PIN Verification
$pin = isset($input['pin']) ? $input['pin'] : '';

if ($pin !== VALID_PIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid PIN']);
    exit;
}

echo json_encode(['status' => 'success']);
?>