<?php
header('Content-Type: application/json');
$file = 'bilder/Test_4_870_1.jpg';
if (file_exists($file)) {
    echo json_encode([
        'exists' => true,
        'size' => filesize($file),
        'mtime' => date('Y-m-d H:i:s', filemtime($file))
    ]);
} else {
    echo json_encode(['exists' => false]);
}
?>