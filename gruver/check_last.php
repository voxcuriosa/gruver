<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('full_data.json'), true);
$features = $data['features'];
$last = end($features);
echo json_encode($last, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>