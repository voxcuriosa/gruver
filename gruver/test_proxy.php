<?php
// test_proxy.php - Standalone test for Norge i bilder credentials
header("Content-Type: text/plain");

$geoid_user = 'uhjalmarjohansen_vigchr';

// 1. Test Token API (for modern orthophotos)
$pass_token = 'tw2!eFhkkZBAZrt';
$token_url = 'https://backend-api.klienter-prod-k8s2.norgeibilder.no/token/tilecache';

echo "--- TESTING TOKEN API (Modern Orthophotos) ---\n";
$fields = [
    'client' => 'referer',
    'referer' => 'voxcuriosa.no',
    'expiration' => 60,
    'f' => 'json'
];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$geoid_user:$pass_token");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_REFERER, 'https://www.norgeibilder.no/');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 && !empty($response)) {
    $json = json_decode($response, true);
    if (isset($json['token'])) {
        echo "SUCCESS: Got token " . substr($json['token'], 0, 10) . "...\n";
    } else {
        echo "FAILED: API returned 200 but no token in JSON: " . $response . "\n";
    }
} else {
    echo "FAILED: HTTP $httpCode. Check geoid_user/pass_token.\n";
}

echo "\n--- TESTING WMS ACCESS (Historical Flyfoto) ---\n";
$pass_wms = 'fT4dKsmcxXdEmQMG';
$targetUrl = 'https://wms.geonorge.no/skwms1/wms.nib-prosjekter?request=GetCapabilities&service=WMS';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$geoid_user:$pass_wms");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
// DNS BYPASS for wms.geonorge.no (Firewall fix)
curl_setopt($ch, CURLOPT_RESOLVE, array("wms.geonorge.no:443:159.162.23.149"));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo "SUCCESS: WMS GetCapabilities OK (HTTP 200)\n";
} else {
    echo "FAILED: HTTP $httpCode. Check geoid_user/pass_wms.\n";
    echo "Response snippet: " . substr(strip_tags($response), 0, 100) . "...\n";
}
?>