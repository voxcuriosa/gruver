<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Helper to send JSON response
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. PIN Verification
if ($action === 'verify_pin') {
    $input = json_decode(file_get_contents('php://input'), true);
    $pin = isset($input['pin']) ? trim($input['pin']) : '';
    if ($pin === VALID_PIN) {
        jsonResponse(['status' => 'success', 'message' => 'PIN ok']);
    } else {
        jsonResponse(['status' => 'error', 'message' => 'Ugyldig PIN-kode'], 401);
    }
}

// 2. Get Topics and Questions
if ($action === 'get_topics') {
    if (file_exists(QUESTIONS_FILE)) {
        $content = file_get_contents(QUESTIONS_FILE);
        $data = json_decode($content, true);
        jsonResponse($data ? $data : ['topics' => []]);
    } else {
        jsonResponse(['topics' => []]);
    }
}

// 3. Get Highscores
if ($action === 'get_highscores') {
    if (file_exists(HIGHSCORES_FILE)) {
        $content = file_get_contents(HIGHSCORES_FILE);
        $data = json_decode($content, true);
        $scores = isset($data['scores']) ? $data['scores'] : [];
        // Sort descending by score
        usort($scores, function($a, $b) {
            return ($b['score'] ?? 0) - ($a['score'] ?? 0);
        });
        jsonResponse(['scores' => array_slice($scores, 0, 50)]);
    } else {
        jsonResponse(['scores' => []]);
    }
}

// 4. Submit Highscore
if ($action === 'save_highscore') {
    $input = json_decode(file_get_contents('php://input'), true);
    $name = isset($input['name']) ? substr(trim($input['name']), 0, 25) : 'Anonym';
    $score = isset($input['score']) ? intval($input['score']) : 0;
    $character = isset($input['character']) ? trim($input['character']) : 'chicken';
    $topic = isset($input['topic']) ? trim($input['topic']) : 'all';

    if ($name === '') $name = 'Anonym';
    if ($score <= 0) {
        jsonResponse(['status' => 'error', 'message' => 'Ugyldig poengsum'], 400);
    }

    $scores = [];
    if (file_exists(HIGHSCORES_FILE)) {
        $content = file_get_contents(HIGHSCORES_FILE);
        $data = json_decode($content, true);
        if ($data && isset($data['scores'])) {
            $scores = $data['scores'];
        }
    }

    $newEntry = [
        'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        'score' => $score,
        'character' => htmlspecialchars($character, ENT_QUOTES, 'UTF-8'),
        'topic' => htmlspecialchars($topic, ENT_QUOTES, 'UTF-8'),
        'date' => date('Y-m-d H:i')
    ];

    $scores[] = $newEntry;
    usort($scores, function($a, $b) {
        return ($b['score'] ?? 0) - ($a['score'] ?? 0);
    });

    $scores = array_slice($scores, 0, 100);
    file_put_contents(HIGHSCORES_FILE, json_encode(['scores' => $scores], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    jsonResponse(['status' => 'success', 'entry' => $newEntry]);
}

jsonResponse(['error' => 'Ukjent handling'], 400);
?>
