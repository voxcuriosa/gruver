<?php
header("Content-Type: text/plain");
echo "--- HYPHENATED WMS TEST ---\n";

$geoid_user = 'uhjalmarjohansen_vigchr';
$pass_wms = 'fT4dKsmcxXdEmQMG';

$url = 'https://wms.geonorge.no/skwms1/wms.nib-prosjekter';
echo "Testing Endpoint: $url\n";

$targetUrl = $url . "?SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap&LAYERS=Skien%201947&STYLES=&SRS=EPSG:3857&BBOX=1060000,8210000,1070000,8220000&WIDTH=256&HEIGHT=256&FORMAT=image/png";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$geoid_user:$pass_wms");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_ENCODING, "");
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_RESOLVE, array("wms.geonorge.no:443:159.162.23.149"));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Content-Type: $contentType\n";
if ($error)
    echo "CURL Error: $error\n";

if ($httpCode == 200 && strpos($contentType, 'image') !== false) {
    echo "SUCCESS: Received image data (" . strlen($response) . " bytes)\n";
} else {
    echo "FAILED.\n";
    echo "Snippet:\n" . substr($response, 0, 500) . "\n";
}
?>