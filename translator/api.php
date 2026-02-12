<?php
header('Content-Type: application/json');

// Load configuration
if (!file_exists(__DIR__ . '/config.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration file missing. Please create config.php based on config.example.php']);
    exit;
}

require_once __DIR__ . '/config.php';

// Verify configuration is loaded
if (!defined('VALID_PIN') || !defined('OPENAI_API_KEY')) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration incomplete. Please check config.php']);
    exit;
}

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

// Input Validation
$text = isset($input['text']) ? $input['text'] : '';
$target_lang = isset($input['target_lang']) ? $input['target_lang'] : 'Norwegian (Bokmål)';

if (empty($text)) {
    http_response_code(400);
    echo json_encode(['error' => 'No text provided']);
    exit;
}

// OpenAI Request
$prompt = "Translate the following subtitle segments to " . $target_lang . ".
This is for video subtitles, so prioritize: 
1. Simplicity and readability.
2. Condensing text where possible without losing meaning (shorten sentences).
3. Natural, spoken flow.

The segments are separated by '<SEP>'.
Maintain the exact number of segments.
Do not translate proper names if inappropriate.
Keep the tone neutral and accurate.
Microsoft Stream requires strict VTT, so ensure no special characters break it.
Answer ONLY with the translated segments separated by <SEP>.

Input:
" . $text;

$data = [
    "model" => "gpt-4o-mini",
    "messages" => [
        ["role" => "system", "content" => "You are a professional translator for video subtitles."],
        ["role" => "user", "content" => $prompt]
    ]
];

$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . OPENAI_API_KEY
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);

if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['error' => 'Curl error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

$responseData = json_decode($response, true);

if (isset($responseData['choices'][0]['message']['content'])) {
    echo json_encode(['translated_text' => $responseData['choices'][0]['message']['content']]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'OpenAI API Error', 'details' => $responseData]);
}
?>