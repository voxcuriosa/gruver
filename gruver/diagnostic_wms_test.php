<?php
header("Content-Type: text/plain");
echo "--- PROBING KARTVERKET WMS (MARCH 2026) ---\n";

$geoid_user = 'uhjalmarjohansen_vigchr';
$pass_wms = 'fT4dKsmcxXdEmQMG';

$probes = [
    'https://wms.geonorge.no/skwms1/wms.nib', // Main service
    'https://wms.geonorge.no/skwms1/wms.nib-prosjekter',
    'https://wms.geonorge.no/skwms1/wms.nib_prosjekter',
    'https://wms.geonorge.no/skwms1/wms.nib-prosjekt',
    'https://wms.geonorge.no/skwms1/wms.nib_prosjekt',
    'https://wms.geonorge.no/skwms1/wms.nib_historisk',
];

foreach ($probes as $baseUrl) {
    echo "Probing: $baseUrl\n";
    $targetUrl = $baseUrl . "?SERVICE=WMS&REQUEST=GetCapabilities";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$geoid_user:$pass_wms");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_ENCODING, "");
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_RESOLVE, array("wms.geonorge.no:443:159.162.23.149"));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";
    if ($httpCode == 200) {
        if (strpos($response, 'WMS_Capabilities') !== false) {
            echo "SUCCESS!\n";
            if ($baseUrl === 'https://wms.geonorge.no/skwms1/wms.nib') {
                // Search for specific Skien layers in the main capabilities
                if (strpos($response, 'Skien') !== false) {
                    echo "FOUND 'Skien' in main nib WMS!\n";
                } else {
                    echo "No 'Skien' in main nib WMS.\n";
                }
            }
        } else {
            echo "Snippet: " . substr(strip_tags($response), 0, 100) . "...\n";
        }
    }
    echo "-----------------------------------\n";
}
?>