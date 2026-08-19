<?php
/**
 * clear_funn.php - Sletter alle AI-funn
 */
header('Content-Type: application/json');

$file = 'data/funn/ai_funn.json';
if (file_exists($file)) {
    file_put_contents($file, json_encode([]));
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => true, 'message' => 'Filen fantes ikke']);
}
?>