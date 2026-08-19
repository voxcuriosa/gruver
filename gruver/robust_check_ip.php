<?php
header("Content-Type: text/plain");
echo "--- IP DIAGNOSTICS ---\n";

// Try multiple services to get the public IP
$services = [
    'https://api.ipify.org',
    'https://ifconfig.me/ip',
    'https://icanhazip.com',
    'http://checkip.dyndns.org'
];

foreach ($services as $url) {
    echo "Testing $url... ";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $ip = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200 && $ip) {
        echo "SUCCESS: " . trim(strip_tags($ip)) . "\n";
    } else {
        echo "FAILED (HTTP $httpCode)\n";
    }
}

echo "\n--- SERVER ENVIRONMENT ---\n";
echo "SERVER_ADDR: " . ($_SERVER['SERVER_ADDR'] ?? 'Unknown') . "\n";
echo "REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
echo "HTTP_X_FORWARDED_FOR: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'None') . "\n";
?>