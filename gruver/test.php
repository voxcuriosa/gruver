<?php
header('Content-Type: text/plain; charset=utf-8');

// Din GeoID-legitimasjon
$user = 'uhjalmarjohansen_vigchr';
$pass = 'tw2!eFhkkZBAZrt';
$apiUrl = 'https://backend-api.klienter-prod-k8s2.norgeibilder.no/projects';

// Søkefilter for Skien kommune (4003) basert på Swagger-definisjon
$payload = json_encode([
    "filters" => [
        "kommunenummer" => ["4003"]
    ]
]);

echo "Søker etter flyfoto-prosjekter i Skien via NIB-API v1...\n";
echo "------------------------------------------------------\n";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . base64_encode("$user:$pass")
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($code === 200) {
    $data = json_decode($response, true);
    // API-et returnerer prosjekter i en liste
    $projects = $data['projects'] ?? $data;

    if (is_array($projects)) {
        foreach ($projects as $project) {
            $name = $project['name'] ?? 'Ukjent';
            $year = $project['captureDate'] ?? 'Mangler år';
            $id   = $project['projectId'] ?? 'Mangler ID';
            
            echo "År: $year | Navn: $name | ProjectID: $id\n";
        }
    } else {
        echo "Ingen prosjekter funnet. Sjekk om kommunenummeret er korrekt.";
    }
} else {
    echo "Feil ved tilkobling til API. Statuskode: $code\n";
    echo "Svar fra server: " . $response;
}

curl_close($ch);
?>