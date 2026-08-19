<?php
// nib_proxy.php - Optimized Proxy for WMTS Authentication
header("Access-Control-Allow-Origin: *");
ini_set('display_errors', 0); // Hide in production
error_reporting(E_ALL);

// --- CONFIGURATION ---
$geoid_user = 'uhjalmarjohansen_vigchr';

// PASSORD FOR 'nib-prosjekter' (WMS / Historiske) - Oppdatert 19.02.2026
$pass_wms = 'fT4dKsmcxXdEmQMG';

// PASSORD FOR 'nib' (WMTS / Token API) - Gammelt passord virker fortsatt her!
$pass_token = 'tw2!eFhkkZBAZrt';

$token_url = 'https://backend-api.klienter-prod-k8s2.norgeibilder.no/token/tilecache';
$token_file = sys_get_temp_dir() . '/nib_token_cache_v3.json';

// --- HELPER: GET TOKEN ---
function getToken($user, $pass, $url, $file)
{
    // 1. Check if we have a valid cached token
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (isset($data['token']) && isset($data['expires']) && $data['expires'] > time()) {
            return $data['token'];
        }
    }

    // 2. Generate NEW token
    // Generate token using 'referer' as it is less prone to IP changes
    $fields = [
        'client' => 'referer',
        'referer' => 'voxcuriosa.no',
        'expiration' => 10080, // 1 week
        'f' => 'json'
    ];
    $postData = http_build_query($fields);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_REFERER, 'https://www.norgeibilder.no/');

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200 && !empty($response)) {
        $json = json_decode($response, true);
        if (isset($json['token'])) {
            // Save token with just under 1 week expiry (buffer)
            $cacheData = [
                'token' => $json['token'],
                'expires' => time() + (10000 * 60)
            ];
            @file_put_contents($file, json_encode($cacheData));
            return $json['token'];
        }
    }

    return null; // Failed to get token
}

// --- MAIN PROXY LOGIC ---
$svc = $_GET['svc'] ?? 'nib';

// 1. Get Token (only needed for modern WMTS orthophotos)
// Bruk GAMMELT passord for Token API
$token = null;
if ($svc === 'nib') {
    $token = getToken($geoid_user, $pass_token, $token_url, $token_file);
}

// 2. Determine Service Target
if ($svc === 'nib-prosjekter') {
    // Legacy WMS for historical aerial photo projects (uses new password)
    $baseUrl = 'https://wms.geonorge.no/skwms1/wms.nib-prosjekter';
    $useBasicAuth = true;
} else {
    // Modern WMTS for current orthophotos (requires token)
    $baseUrl = 'https://tilecache.norgeibilder.no/wmts/webmercator';
    $useBasicAuth = false;
}

// 3. Construct Target URL
$qs = $_SERVER['QUERY_STRING'] ?? '';
$qs = preg_replace('/(^|&)(svc|token)=[^&]*/', '', $qs);
$qs = trim($qs, '&');

$targetUrl = $baseUrl . (strpos($baseUrl, '?') === false ? '?' : '&') . $qs;
if ($token) {
    $targetUrl .= '&token=' . $token;
}

// 4. Forward Request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_ENCODING, ""); // Handle gzip/deflate
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1); // Force HTTP/1.1 to avoid stream errors
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

// DNS BYPASS for wms.geonorge.no (Fix for 159.162.23.149 firewall issue 19.02.2026)
if (strpos($targetUrl, 'wms.geonorge.no') !== false) {
    curl_setopt($ch, CURLOPT_RESOLVE, array("wms.geonorge.no:443:159.162.23.149"));
}

if ($useBasicAuth) {
    // Legacy servers often check IP + Basic Auth (NEW PASSWORD)
    curl_setopt($ch, CURLOPT_USERPWD, "$geoid_user:$pass_wms");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_REFERER, 'https://www.norgeibilder.no/');
} else {
    // New server validates token + Referer
    curl_setopt($ch, CURLOPT_REFERER, 'https://voxcuriosa.no/');
}

$data = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);
curl_close($ch);

// 5. Return Response
if ($code == 200) {
    header("Content-Type: $type");
    echo $data;
} else {
    http_response_code($code ?: 500);
    // Silent fail for tiles to avoid broken UI, UNLESS debug is on
    if (isset($_GET['REQUEST']) && $_GET['REQUEST'] === 'GetMap' && !isset($_GET['debug'])) {
        exit;
    }

    header("Content-Type: text/plain");
    echo "Proxy Error: HTTP $code\n";
    echo "CURL Error: $error\n";
    echo "Target URL: $targetUrl\n";
    echo "Service: $svc\n";
}
?>