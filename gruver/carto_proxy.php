<?php
// carto_proxy.php - Proxies CARTO basemap requests so the API key remains secret on server
header("Access-Control-Allow-Origin: *");
header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800'); // Cache 7 dager i nettleser

require_once __DIR__ . '/config.php';

$key = defined('CARTO_API_KEY') ? CARTO_API_KEY : '';
$style = $_GET['style'] ?? 'dark_all';
$z = isset($_GET['z']) ? (int)$_GET['z'] : 0;
$x = isset($_GET['x']) ? (int)$_GET['x'] : 0;
$y = isset($_GET['y']) ? (int)$_GET['y'] : 0;
$r = isset($_GET['r']) && $_GET['r'] === '@2x' ? '@2x' : '';

// Validér tillatte kart-stiler fra CARTO
$valid_styles = [
    'dark_all',
    'dark_nolabels',
    'dark_only_labels',
    'light_all',
    'light_nolabels',
    'light_only_labels',
    'voyager',
    'voyager_nolabels',
    'voyager_only_labels',
    'rastertiles/voyager'
];

if (!in_array($style, $valid_styles, true)) {
    http_response_code(400);
    exit('Invalid style');
}

$subdomains = ['a', 'b', 'c', 'd'];
$sub = $subdomains[abs($x + $y) % 4];

$cartoUrl = "https://{$sub}.basemaps.cartocdn.com/{$style}/{$z}/{$x}/{$y}{$r}.png";
if (!empty($key)) {
    $cartoUrl .= '?key=' . urlencode($key);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $cartoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'VoxPortal/1.0');

$data = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($code === 200 && $data) {
    if ($contentType) {
        header("Content-Type: $contentType");
    }
    echo $data;
} else {
    http_response_code($code ?: 502);
}
