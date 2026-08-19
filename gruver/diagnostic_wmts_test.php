<?php
header("Content-Type: text/plain");
echo "--- INSPECTING MODERN NIB WMTS (MARCH 2026) ---\n";

include 'nib_proxy.php'; // To get the token logic

$geoid_user = 'uhjalmarjohansen_vigchr';
$pass_token = 'tw2!eFhkkZBAZrt'; // Token password
$token_url = 'https://www.norgeibilder.no/Account/GetToken';
$token_file = 'token_cache.json';

echo "Fetching token...\n";
$token = getToken($geoid_user, $pass_token, $token_url, $token_file);

if (!$token) {
    echo "FAILED: Could not get token.\n";
    exit;
}

echo "Token: " . substr($token, 0, 10) . "...\n";

// Now check GetCapabilities for the WORKING WMTS
$capabilitiesUrl = "https://tilecache.norgeibilder.no/wmts/webmercator?SERVICE=WMTS&REQUEST=GetCapabilities&token=" . $token;
echo "URL: $capabilitiesUrl\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $capabilitiesUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_ENCODING, "");
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";

if ($httpCode == 200 && strpos($response, 'Capabilities') !== false) {
    echo "SUCCESS: Received WMTS Capabilities XML.\n";

    // Search for "Skien" or "Layer" in the response to see what's available
    if (strpos($response, 'Skien') !== false) {
        echo "FOUND 'Skien' mentions in WMTS!\n";
        // List all Layer identifiers containing "Skien"
        preg_match_all('/<ows:Identifier>(.*?Skien.*?)<\/ows:Identifier>/i', $response, $matches);
        if (!empty($matches[1])) {
            echo "Available layers matching 'Skien':\n";
            foreach (array_unique($matches[1]) as $m) {
                echo " - $m\n";
            }
        }
    } else {
        echo "No 'Skien' layers found in the main WMTS service.\n";
        // List a few sample layers to see the structure
        preg_match_all('/<ows:Identifier>(.*?)<\/ows:Identifier>/', $response, $matches);
        if (!empty($matches[1])) {
            echo "Sample Layer IDs found:\n";
            for ($i = 0; $i < min(10, count($matches[1])); $i++) {
                echo " - " . $matches[1][$i] . "\n";
            }
        }
    }
} else {
    echo "FAILED: Could not get capabilities or unexpected response.\n";
    echo "Snippet: " . substr(strip_tags($response), 0, 300) . "...\n";
}
?>