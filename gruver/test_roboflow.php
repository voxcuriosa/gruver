<?php
$tmp_img = 'data/funn/debug_tile.png';

$roboflow_api_key = "JCc7gNw1HfQKaGhiLJRd";
$roboflow_model_id = "voxcuriosa";
$roboflow_version = "6";
$roboflow_url = "https://detect.roboflow.com/{$roboflow_model_id}/{$roboflow_version}?api_key={$roboflow_api_key}";

if (file_exists($tmp_img)) {
    $image_data = file_get_contents($tmp_img);
    $base64_image = base64_encode($image_data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $roboflow_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $base64_image);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $result = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Status: $http_status\n";
    echo $result;
} else {
    echo "Image not found";
}
?>