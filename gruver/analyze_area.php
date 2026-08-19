<?php
/**
 * analyze_area.php - AI-analyse av LiDAR-data (PHP Port)
 */

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$bbox = $input['bbox'] ?? null;

if (!$bbox) {
    echo json_encode(['success' => false, 'error' => 'Mangler BBOX']);
    exit;
}

/**
 * Projects WGS84 (4326) to Web Mercator (3857)
 */
function project4326to3857($lat, $lng)
{
    $x = $lng * 20037508.34 / 180;
    $y = log(tan((90 + $lat) * M_PI / 360)) / (M_PI / 180);
    $y = $y * 20037508.34 / 180;
    return [$x, $y];
}

function fetchDirect($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['data' => $data, 'code' => $code];
}

// Convert Lat/Lng BBOX to Mercator Meters
$sw = project4326to3857($bbox[0], $bbox[1]);
$ne = project4326to3857($bbox[2], $bbox[3]);

$dx = $ne[0] - $sw[0];
$dy = $ne[1] - $sw[1];

// MAKE IT A PERFECT SQUARE: 
// The AI is trained on circles. If the user draws a wide rectangle 
// and we force it into a 512x512 image, the circles become tall ovals!
$cx = ($sw[0] + $ne[0]) / 2;
$cy = ($sw[1] + $ne[1]) / 2;
$max_d = max($dx, $dy);

$sw[0] = $cx - $max_d / 2;
$ne[0] = $cx + $max_d / 2;
$sw[1] = $cy - $max_d / 2;
$ne[1] = $cy + $max_d / 2;

$wms_bbox = "{$sw[0]},{$sw[1]},{$ne[0]},{$ne[1]}";

$w = 1024;
$h = 1024;
// Use EPSG:3857 for better compatibility and stability
$wms_url = "https://wms.geonorge.no/skwms1/wms.hoyde-dtm?SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap&BBOX=$wms_bbox&SRS=EPSG:3857&WIDTH=$w&HEIGHT=$h&LAYERS=DTM:skyggerelieff&FORMAT=image/png&STYLES=";

$response = fetchDirect($wms_url);
$img_data = $response['data'];

if ($response['code'] !== 200 || !$img_data || strlen($img_data) < 300) {
    echo json_encode(['success' => false, 'error' => 'WMS Error: ' . $response['code'], 'debug' => ['size' => strlen($img_data), 'url' => $wms_url]]);
    exit;
}

$src = imagecreatefromstring($img_data);
if (!$src) {
    echo json_encode(['success' => false, 'error' => 'Source image invalid']);
    exit;
}

// Prepare truecolor image with neutral background
$im = imagecreatetruecolor($w, $h);
$white = imagecolorallocate($im, 255, 255, 255);
imagefill($im, 0, 0, $white);
imagecopy($im, $src, 0, 0, 0, 0, $w, $h); // Overwrite background with WMS data
imagedestroy($src);

// Calculate stats BEFORE grayscale
$total_v = 0;
$min_v = 255;
$max_v = 0;
for ($i = 0; $i < 200; $i++) {
    $rgb = imagecolorat($im, rand(0, $w - 1), rand(0, $h - 1));
    $v = (($rgb >> 16 & 0xFF) + ($rgb >> 8 & 0xFF) + ($rgb & 0xFF)) / 3;
    $total_v += $v;
    if ($v < $min_v)
        $min_v = (int) $v;
    if ($v > $max_v)
        $max_v = (int) $v;
}
$avg_v = $total_v / 200;

// Save image to disk so we can send it to Roboflow
$tmp_img = 'data/funn/debug_tile.png';
imagepng($im, $tmp_img);
imagedestroy($im);

// ------------------------------------------------------------------
// ROBOFLOW API INTEGRATION
// ------------------------------------------------------------------
// Replace these with the actual values when they are provided
$roboflow_api_key = "JCc7gNw1HfQKaGhiLJRd";
$roboflow_model_id = "voxcuriosa";
$roboflow_version = "6";
$roboflow_url = "https://detect.roboflow.com/{$roboflow_model_id}/{$roboflow_version}?api_key={$roboflow_api_key}&confidence=1";

$mapped = [];
$roboflow_raw_hits = 0;
$roboflow_error = "N/A";

// Try to call the API only if credentials are set
if ($roboflow_api_key !== "YOUR_API_KEY_HERE") {
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

    if ($http_status == 200 && $result) {
        $json_response = json_decode($result, true);
        if (isset($json_response['predictions'])) {
            $roboflow_raw_hits = count($json_response['predictions']);

            foreach ($json_response['predictions'] as $pred) {
                // $pred has x, y (center of detection in px) based on the image size
                // We map this pixel coordinate back to our geographic bounding box

                // Since this is a V1 model with 15 images, confidence will be low. 
                // We set the threshold down to 1% (0.01) to see what it finds.
                if ($pred['confidence'] > 0.01) {
                    // Roboflow prediction X and Y are centers of the bounding boxes
                    $px_x = $pred['x'];
                    $px_y = $pred['y'];

                    // Because we modified the BBOX to be a perfect square in Mercator coordinates, 
                    // we must calculate the Lat/Lng based on the new square, not the original $bbox array!
                    // Let's do a reverse projection instead for perfect accuracy!

                    // The Mercator X/Y at the pixel:
                    $merc_px_x = $sw[0] + (($px_x / $w) * $max_d);
                    $merc_px_y = $ne[1] - (($px_y / $h) * $max_d); // Y is flipped in images (0 is top/North)

                    // Inverse projection Mercator -> WGS84
                    $lng = ($merc_px_x * 180) / 20037508.34;
                    // lat = (180/pi) * (2 * atan(exp((y / 20037508.34) * 180 * pi/180)) - pi/2)
                    $lat = (180 / M_PI) * (2 * atan(exp($merc_px_y / 20037508.34 * M_PI)) - M_PI / 2);

                    $raw_cat = strtolower($pred['class']);
                    $final_cat = ($raw_cat == 'kullmile') ? 'kullmiler' : $raw_cat;

                    $mapped[] = [
                        'lat' => $lat,
                        'lng' => $lng,
                        'cat' => $final_cat, // "kullmiler"
                        'prob' => round($pred['confidence'], 2)
                    ];
                }
            }
        }
    } else {
        $roboflow_error = "Feilkode: $http_status. Response: " . substr($result, 0, 100);
    }
} else {
    $roboflow_error = "API-nøkkel mangler. Venter på Roboflow-oppsett.";
}


$file = 'data/funn/ai_funn.json';
$existing = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
if (!is_array($existing))
    $existing = [];

$added = [];
foreach ($mapped as $m) {
    $dup = false;
    foreach ($existing as $ex) {
        if (abs($m['lat'] - $ex['lat']) < 0.0001 && abs($m['lng'] - $ex['lng']) < 0.0001) {
            $dup = true;
            break;
        }
    }
    if (!$dup)
        $added[] = $m;
}

file_put_contents($file, json_encode(array_merge($existing, $added), JSON_PRETTY_PRINT));
imagedestroy($im);

echo json_encode([
    'success' => true,
    'count' => count($added),
    'debug' => [
        'raw_hits' => $roboflow_raw_hits,
        'clustered' => count($mapped), // We no longer cluster, we let Roboflow handle NMS
        'brightness' => round($avg_v, 1),
        'intensity_range' => "$min_v-$max_v",
        'image_saved' => 'data/funn/debug_tile.png',
        'api_error' => $roboflow_error
    ]
]);
?>