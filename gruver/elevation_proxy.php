<?php
// elevation_proxy.php - Proxy for Kartverket Elevation API (Hoydedata v1)
// Forsikre oss om at flaut-tall (koordinater) ikke blir unødvendig lange i JSON
// Dette hindrer "414 Request-URI Too Large"
ini_set('precision', 10);
ini_set('serialize_precision', 10);

header("Access-Control-Allow-Origin: *");

// Konfigurasjon - Bruker nå hoydedata v1 (punkt) da hoydeprofil v1 gir 403
$baseUrl = 'https://ws.geonorge.no/hoydedata/v1/punkt';

// Sjekk om vi har punkter
if (!isset($_GET['punkter'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'Missing punkter parameter']);
    exit;
}

$inputPunkter = $_GET['punkter']; // Format: lat,lon;lat,lon...

// Transformer punkter til [[lon,lat], [lon,lat]] for den nye APIen
$pointsArray = explode(';', $inputPunkter);
$jsonPoints = [];
foreach ($pointsArray as $p) {
    if (empty(trim($p)))
        continue;
    $coords = explode(',', $p);
    if (count($coords) == 2) {
        // Input fra viewer.js er "lng,lat"
        // Geonorge hoydedata forventer [lon, lat]
        // Vi runder av til 5 desimaler for å holde URL-lengden nede og unngå 414 error
        $jsonPoints[] = [round((float) $coords[0], 5), round((float) $coords[1], 5)];
    }
}

$pointsStr = json_encode($jsonPoints);
$targetUrl = $baseUrl . "?koordsys=4258&punkter=" . urlencode($pointsStr) . "&format=json";

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
// Viktig: Bruk en UA som ikke blir blokkert
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_REFERER, 'https://voxcuriosa.no/');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'X-Requested-With: XMLHttpRequest'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

// Execute and get content
$data = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Debugging: log to a file if it fails
if ($code != 200 || $error) {
    $logMsg = date('[Y-m-d H:i:s] ') . "Error: $code | cURL Error: $error | URL: $targetUrl\n";
    $logMsg .= "Response: " . substr($data, 0, 500) . "\n---\n";
    @file_put_contents('debug_elevation.log', $logMsg, FILE_APPEND);
}

// Output data
header('Content-Type: application/json');
if ($code == 200 && !$error) {
    echo $data;
} else {
    http_response_code($code ?: 500);
    echo json_encode([
        'error' => true,
        'status' => $code,
        'message' => "Proxy Error: $code",
        'curl_error' => $error,
        'url' => $targetUrl,
        'raw_response' => substr($data, 0, 100)
    ]);
}
?>