<?php
// log_interaction.php
// Takes JSON payload from the Vox Portal map or Notebooks and logs it to `map_interactions`
header('Content-Type: application/json');

// Enable error reporting for debugging if needed, but return JSON on fatal errors
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once __DIR__ . '/../history/db.php'; // Use the main historyquiz db connection

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get the raw POST data
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$actionType = isset($input['action_type']) ? trim($input['action_type']) : null;
$itemName = isset($input['item_name']) ? trim($input['item_name']) : null;
$lat = isset($input['lat']) ? (float)$input['lat'] : null;
$lng = isset($input['lng']) ? (float)$input['lng'] : null;

if (!$actionType || !$itemName) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing action_type or item_name']);
    exit;
}

// Basic sanitization
$actionType = substr(strip_tags($actionType), 0, 50);
$itemName = substr(strip_tags($itemName), 0, 255);

// Create a session hash to anonymously group clicks per user session. 
// Uses IP address + User Agent, hashed, to avoid storing PII.
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
// Change salt daily so we can't track users across days
$salt = date('Y-m-d');

$sessionHash = hash('sha256', $ip . $userAgent . $salt);

try {
    // Check if table exists, if not, create it quietly
    $conn->exec("CREATE TABLE IF NOT EXISTS `map_interactions` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `session_hash` VARCHAR(64) NOT NULL,
        `action_type` VARCHAR(50) NOT NULL COMMENT 'layer_toggle, point_click, notebook_click',
        `item_name` VARCHAR(255) NOT NULL,
        `lat` DECIMAL(10,8) DEFAULT NULL,
        `lng` DECIMAL(11,8) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // AUTO-MIGRATION: Ensure lat/lng columns exist if table was already there
    try {
        $checkCol = $conn->query("SHOW COLUMNS FROM `map_interactions` LIKE 'lat'");
        if ($checkCol->rowCount() == 0) {
            $conn->exec("ALTER TABLE `map_interactions` ADD COLUMN `lat` DECIMAL(10,8) DEFAULT NULL, ADD COLUMN `lng` DECIMAL(11,8) DEFAULT NULL");
        }
    } catch (Exception $e) {}

    // Insert interaction
    $stmt = $conn->prepare("INSERT INTO map_interactions (session_hash, action_type, item_name, lat, lng) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$sessionHash, $actionType, $itemName, $lat, $lng]);

    echo json_encode(['success' => true]);

}
catch (PDOException $e) {
    // Fail silently so we don't spam the console or break the frontend
    // In a real debug scenario, you could log $e->getMessage() to a file
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
