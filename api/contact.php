<?php
// ==============================================================================
// ChroMo.no Contact Form API Endpoint
// Hosted on voxcuriosa.no
// ==============================================================================

// 1. CORS Headers
$allowed_origins = [
    'https://chromo.no',
    'https://www.chromo.no',
    'https://chromo-no.web.app',
    'https://chromo-no.firebaseapp.com',
    'http://localhost:5000',
    'http://localhost:3000',
    'http://127.0.0.1:5000'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins) || empty($origin)) {
    if (!empty($origin)) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        header("Access-Control-Allow-Origin: *");
    }
} else {
    header("Access-Control-Allow-Origin: https://chromo.no");
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// 2. Parse Input (Supports both JSON payload and application/x-www-form-urlencoded)
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true);

if (is_array($json_data)) {
    $topic   = trim($json_data['Tema'] ?? $json_data['topic'] ?? '');
    $name    = trim($json_data['Navn'] ?? $json_data['name'] ?? '');
    $email   = trim($json_data['Email'] ?? $json_data['email'] ?? '');
    $message = trim($json_data['Melding'] ?? $json_data['message'] ?? '');
    $gotcha  = trim($json_data['_gotcha'] ?? $json_data['gotcha'] ?? '');
} else {
    $topic   = trim($_POST['Tema'] ?? $_POST['topic'] ?? '');
    $name    = trim($_POST['Navn'] ?? $_POST['name'] ?? '');
    $email   = trim($_POST['Email'] ?? $_POST['email'] ?? '');
    $message = trim($_POST['Melding'] ?? $_POST['message'] ?? '');
    $gotcha  = trim($_POST['_gotcha'] ?? $_POST['gotcha'] ?? '');
}

// 3. Honeypot check (Silent discard for spam bots)
if (!empty($gotcha)) {
    // Pretend success so bots do not retry
    echo json_encode(['success' => true, 'message' => 'Henvendelse mottatt!']);
    exit;
}

// 4. Header Injection Defense & Sanitization
$name    = str_replace(["\r", "\n", "\t"], ' ', $name);
$topic   = str_replace(["\r", "\n", "\t"], ' ', $topic);
$email   = str_replace(["\r", "\n", "\t"], '', $email);

// 5. Validation
if (mb_strlen($name) < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Vennligst oppgi et gyldig navn (minst 2 tegn).']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Vennligst oppgi en gyldig e-postadresse.']);
    exit;
}

if (mb_strlen($message) < 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Meldingen må inneholde minst 5 tegn.']);
    exit;
}

if (empty($topic)) {
    $topic = 'Generell henvendelse';
}

// 6. Backup log to secure local file
$log_dir = __DIR__ . '/../data';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0750, true);
}
$log_file = $log_dir . '/contact_submissions.jsonl';
$log_entry = json_encode([
    'timestamp'  => date('c'),
    'ip'         => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'topic'      => $topic,
    'name'       => $name,
    'email'      => $email,
    'message'    => $message
], JSON_UNESCAPED_UNICODE) . "\n";
@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

// 7. Compose Email
$to = 'post@chromo.no';
$subject = "=?UTF-8?B?" . base64_encode("Ny henvendelse fra ChroMo: [$topic] – $name") . "?=";

$ip = $_SERVER['REMOTE_ADDR'] ?? 'Ukjent';
$now_str = date('d.m.Y H:i:s');

$body = "Ny henvendelse mottatt via kontaktskjemaet på chromo.no:\n\n";
$body .= "------------------------------------------------------------\n";
$body .= "Tema:     $topic\n";
$body .= "Navn:     $name\n";
$body .= "E-post:   $email\n";
$body .= "Tid:      $now_str\n";
$body .= "Avsender: $ip\n";
$body .= "------------------------------------------------------------\n\n";
$body .= "Melding:\n";
$body .= "$message\n\n";
$body .= "------------------------------------------------------------\n";
$body .= "Du kan svare direkte på denne e-posten for å kontakte $name ($email).\n";

$headers  = "From: ChroMo Kontaktskjema <noreply@voxcuriosa.no>\r\n";
$headers .= "Reply-To: $name <$email>\r\n";
$headers .= "Cc: borchgrevink@gmail.com\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";
$headers .= "X-Mailer: ChroMo-Contact/1.0\r\n";

$mail_sent = @mail($to, $subject, $body, $headers);

// Even if PHP mail fails to transport immediately, the submission is saved to the log
echo json_encode([
    'success' => true,
    'message' => 'Tusen takk! Henvendelsen din er mottatt, og vi svarer deg så raskt vi kan.'
]);
?>
