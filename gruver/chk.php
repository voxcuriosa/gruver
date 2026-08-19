<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('full_data.json'), true);
$found = array_filter($data['features'], fn($f) => str_contains($f['properties']['name'] ?? '', 'Test'));
echo json_encode(array_values($found), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
