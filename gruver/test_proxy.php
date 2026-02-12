<?php
header("Content-Type: text/plain");
$user = 'uhjalmarjohansen_vigchr';
$pass = 'tw2!eFhkkZBAZrt';

$svc = $_GET['svc'] ?? 'wms';
$query = $_SERVER['QUERY_STRING'];

// Vi bruker IP-adressen til gatekeeper.geonorge.no direkte (159.162.102.155)
// for å omgå "Could not resolve host" feilen på serveren.
$host = "159.162.102.155";
$url = "https://{$host}/skwms1/wms.nib-prosjekter?" . $query;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Nødvendig når vi bruker IP i URL
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

// Tvinger forespørselen til å tro at den snakker med riktig domene
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Host: gatekeeper.geonorge.no"));

$res = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

echo "--- DIRECT IP DEBUG ---\n";
echo "Trying IP: $host\n";
echo "HTTP Status: $http_code\n";

if ($curl_error) {
    echo "CURL ERROR: $curl_error\n";
} else {
    echo "\n--- KARTVERKET SVARER ---\n";
    echo substr($res, 0, 500); // Viser de første 500 tegnene av svaret
}
curl_close($ch);
?>