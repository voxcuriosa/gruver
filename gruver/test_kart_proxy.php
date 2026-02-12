<?php
// Proxy for Kartverket Historical Maps
header("Content-Type: text/plain"); // Set to plain text for debugging
$user = 'uhjalmarjohansen_vigchr';
$pass = 'tw2!eFhkkZBAZrt';

$query = $_SERVER['QUERY_STRING'];
// URL for historical map scans
$url = "https://gatekeeper.geonorge.no/skwms1/wms.historiskekart?" . $query;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

$res = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

if ($curl_error || $http_code !== 200) {
    echo "--- MAP PROXY ERROR ---\n";
    echo "HTTP Status: $http_code\n";
    echo "CURL Error: $curl_error\n";
    echo "Response Snippet: " . substr($res, 0, 500);
} else {
    header("Content-Type: $content_type");
    echo $res;
}
curl_close($ch);
?>