<?php
// tile_proxy.php - Proxies WMS tile requests to bypass CORS/Network issues
header("Access-Control-Allow-Origin: *");
header('Content-Type: image/png');

$url = $_GET['url'] ?? '';

// Whitelist valid WMS hosts
$valid_hosts = [
    'wms.geonorge.no',
    'wms.nib.no',
    'geo.ngu.no',
    'kart.ra.no',
    'waapi.webatlas.no',
    'waas.geonorge.no',
    'gatekeeper.geonorge.no'
];

if (empty($url)) {
    http_response_code(400);
    die('No URL specified');
}

$parsed = parse_url($url);
if (!$parsed || !in_array($parsed['host'], $valid_hosts)) {
    http_response_code(403);
    die('Invalid host');
}

// Fetch and pass through
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_REFERER, 'https://voxcuriosa.no/');
$data = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code == 200) {
    echo $data;
} else {
    http_response_code($code);
}
?>