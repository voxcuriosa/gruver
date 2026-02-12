<?php
// kultur_proxy.php - Dedicated Proxy for Kulturminner WMS (Geonorge)
header("Access-Control-Allow-Origin: *");

// Use Riksantikvaren directly as Geonorge is responding with 500 Error
$baseUrl = 'https://kart.ra.no/wms/kulturminner';

// Construct target URL using raw query string
$targetUrl = $baseUrl . '?' . $_SERVER['QUERY_STRING'];

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
// Use a real browser User-Agent
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

// Execute and get content
$data = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);
curl_close($ch);

// Output data with proper Content-Type
if ($code == 200 && !$error) {
    if ($contentType) {
        header("Content-Type: $contentType");
    }
    echo $data;
} else {
    http_response_code($code ?: 500);
    header('Content-Type: text/plain');
    echo "Proxy Error: $code\n";
    echo "cURL Error: $error\n";
    echo "Target URL: $targetUrl\n";
}
?>