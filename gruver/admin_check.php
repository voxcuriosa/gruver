<?php
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$pin = isset($input['pin']) ? $input['pin'] : '';
$action = isset($input['action']) ? $input['action'] : '';
$ip = $_SERVER['REMOTE_ADDR'];

// Handle logout immediately
if ($action === 'logout') {
    unset($_SESSION['admin_logged_in']);
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// Security Settings
$MAX_ATTEMPTS = 3;
$LOCKOUT_TIME = 3600; // 1 hour
$LOG_FILE = __DIR__ . '/protected/login_log.json';

// Ensure protected directory exists
if (!is_dir(__DIR__ . '/protected')) {
    mkdir(__DIR__ . '/protected', 0750, true);
    file_put_contents(__DIR__ . '/protected/.htaccess', "Deny from all");
}

// 1. Load Login Log
$logData = [];
if (file_exists($LOG_FILE)) {
    $logData = json_decode(file_get_contents($LOG_FILE), true);
    if (!is_array($logData))
        $logData = [];
}

// 2. Check Lockout Status
if (isset($logData[$ip])) {
    $attempts = $logData[$ip]['attempts'];
    $lastAttempt = $logData[$ip]['last_attempt'];

    if ($attempts >= $MAX_ATTEMPTS) {
        $timeLeft = $LOCKOUT_TIME - (time() - $lastAttempt);
        if ($timeLeft > 0) {
            $waitMin = ceil($timeLeft / 60);
            echo json_encode(['success' => false, 'message' => "For mange forsøk. Prøv igjen om $waitMin minutter."]);
            exit;
        } else {
            // Lockout expired, reset
            unset($logData[$ip]);
        }
    }
}

// 3. Verify PIN
if ($pin === '5877') {
    // Success
    if (isset($logData[$ip])) {
        unset($logData[$ip]); // Clear failure log on success
        file_put_contents($LOG_FILE, json_encode($logData));
    }

    $_SESSION['admin_logged_in'] = true;
    echo json_encode(['success' => true, 'token' => hash('sha256', '5877' . time())]);
} else {
    // Failure
    if (!isset($logData[$ip])) {
        $logData[$ip] = ['attempts' => 0, 'last_attempt' => 0];
    }
    $logData[$ip]['attempts']++;
    $logData[$ip]['last_attempt'] = time();

    file_put_contents($LOG_FILE, json_encode($logData));

    $remaining = $MAX_ATTEMPTS - $logData[$ip]['attempts'];
    if ($remaining <= 0) {
        echo json_encode(['success' => false, 'message' => "Feil kode. Du er låst ute i 1 time."]);
    } else {
        echo json_encode(['success' => false, 'message' => "Feil kode. $remaining forsøk igjen."]);
    }
}
?>