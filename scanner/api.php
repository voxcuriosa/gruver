<?php
header('Content-Type: application/json');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// PIN Verification
$pin = isset($input['pin']) ? $input['pin'] : '';

if ($pin !== VALID_PIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid PIN']);
    exit;
}

echo json_encode(['success' => true]);
