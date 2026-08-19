<?php
header('Content-Type: text/plain');
echo "--- DIRECT SYNC RUN ---\n";

$_GET['key'] = 'vox_cron_auto_7734'; // Force key for local execution
include('sync_smart.php');

echo "\n--- VERIFYING JSON ---\n";
$json = file_get_contents('full_data.json');
$data = json_decode($json, true);
$counts = [];
foreach ($data['features'] as $f) {
    $cat = $f['properties']['catKey'] ?? 'MISSING';
    $counts[$cat] = ($counts[$cat] ?? 0) + 1;
}
echo "CATEGORY COUNTS:\n";
foreach ($counts as $cat => $count) {
    echo " - $cat: $count\n";
}
?>