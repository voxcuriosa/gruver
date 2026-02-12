<?php
// proxy_xml.php - Simple Proxy for WMS GetFeatureInfo (Plain text/XML)
header("Access-Control-Allow-Origin: *");

$url = $_GET['url'] ?? '';
if (!$url) {
    http_response_code(400);
    exit("No URL provided.");
}

// Basic validation: must be Geonorge, Kartverket, NGU, RA, NVE, Nois, ISY or local proxy files
if (!preg_match('/^https?:\/\/.*(geonorge\.no|kartverket\.no|ngu\.no|ra\.no|nve\.no|nois\.no|isy\.no)/i', $url) && !preg_match('/\.php(\?|$)/i', $url)) {
    http_response_code(403);
    exit("Forbidden: Only allowed WMS URLs are permitted.");
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$data = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($code == 200) {
    if ($contentType) {
        header("Content-Type: $contentType");
    }
    echo $data;
} else {
    http_response_code($code ?: 500);
    echo "Error fetching data.";
}
?>