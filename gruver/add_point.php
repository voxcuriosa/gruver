<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$LOCAL_FILE = 'local_points.json';
$PENDING_FILE = 'pending_points.json';
$ADMIN_EMAIL = 'borchgrevink@gmail.com';

// Determine role
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isGuest = isset($_POST['role']) && $_POST['role'] === 'guest';

if (!$isAdmin && !$isGuest) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Read input (form data, not JSON, because of file uploads)
$name = trim($_POST['name'] ?? '');
$lat = floatval($_POST['lat'] ?? 0);
$lng = floatval($_POST['lng'] ?? 0);
$catKey = trim($_POST['catKey'] ?? 'DIVERSE');
$description = trim($_POST['description'] ?? '');
$links = trim($_POST['links'] ?? '');
$credit = trim($_POST['credit'] ?? '');
$creditUrl = trim($_POST['creditUrl'] ?? '');
$guestName = trim($_POST['guestName'] ?? '');
$imageUrls = trim($_POST['imageUrls'] ?? ''); // Comma or newline separated URLs

// Validation
if (!$name) {
    echo json_encode(['success' => false, 'message' => 'Navn er påkrevd']);
    exit;
}
if ($lat == 0 || $lng == 0) {
    echo json_encode(['success' => false, 'message' => 'Ugyldige koordinater']);
    exit;
}
if ($isGuest && !$guestName) {
    echo json_encode(['success' => false, 'message' => 'Ditt navn er påkrevd']);
    exit;
}

// Category → style mapping
$catStyles = [
    'GRUVE' => '#icon-1899-E65100-labelson',
    'HULE' => '#icon-1899-01579B-labelson',
    'BYGDEBORG' => '#icon-1899-9C27B0-labelson',
    'GAPAHUK' => '#icon-1899-795548-labelson',
    'VANN' => '#icon-1899-BDBDBD-labelson',
    'DIVERSE' => '#icon-1899-0288D1-labelson',
    'UTSIKT' => '#icon-1899-FFEA00-labelson',
    'HUSTUFT' => '#icon-1899-AFB42B-labelson',
    'GRENSESTEIN' => '#icon-1899-097138-labelson',
    'GRAVHAUG' => '#icon-1899-000000-labelson',
];
$styleUrl = $catStyles[$catKey] ?? $catStyles['DIVERSE'];

// Determine target file and ID range
$targetFile = $isAdmin ? $LOCAL_FILE : $PENDING_FILE;
$idMin = $isAdmin ? 50000 : 60000;

// Load existing points
$points = [];
if (file_exists($targetFile)) {
    $points = json_decode(file_get_contents($targetFile), true);
    if (!is_array($points))
        $points = [];
}

// Find next ID
$maxId = $idMin - 1;
foreach ($points as $p) {
    $pid = $p['properties']['id'] ?? 0;
    if ($pid > $maxId)
        $maxId = $pid;
}
// Also check the other file to avoid any collision
$otherFile = $isAdmin ? $PENDING_FILE : $LOCAL_FILE;
if (file_exists($otherFile)) {
    $otherPoints = json_decode(file_get_contents($otherFile), true);
    if (is_array($otherPoints)) {
        foreach ($otherPoints as $op) {
            $opid = $op['properties']['id'] ?? 0;
            if ($opid > $maxId)
                $maxId = $opid;
        }
    }
}
$newId = $maxId + 1;

// Safe name for images
$safeName = preg_replace('/[^a-zA-Z0-9æøåÆØÅ]/u', '_', $name);
$safeName = preg_replace('/_+/', '_', $safeName);
$safeName = trim($safeName, '_');

// Process images
$localImages = [];
$imagesDir = 'bilder';
if (!file_exists($imagesDir)) {
    mkdir($imagesDir, 0755, true);
}

// 1. Handle uploaded files
if (isset($_FILES['images'])) {
    $files = $_FILES['images'];
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;

    for ($i = 0; $i < $fileCount; $i++) {
        $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $origName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];

        if ($error !== UPLOAD_ERR_OK || $size > 10 * 1024 * 1024)
            continue; // Skip errors or >10MB

        $imgIndex = count($localImages) + 1;
        $filename = "{$safeName}_{$newId}_{$imgIndex}.jpg";
        $destPath = "$imagesDir/$filename";

        // Convert to JPG with max 1920px width
        $srcImg = @imagecreatefromstring(file_get_contents($tmpName));
        if ($srcImg) {
            $width = imagesx($srcImg);
            $height = imagesy($srcImg);
            if ($width > 1920) {
                $ratio = 1920 / $width;
                $newHeight = intval($height * $ratio);
                $resized = imagescale($srcImg, 1920, $newHeight);
                imagedestroy($srcImg);
                $srcImg = $resized;
            }
            imagejpeg($srcImg, $destPath, 80);
            imagedestroy($srcImg);
            $localImages[] = "$imagesDir/$filename";
        }
    }
}

// 2. Handle image URLs (download and save locally)
if ($imageUrls) {
    $urls = preg_split('/[\r\n,]+/', $imageUrls);
    foreach ($urls as $url) {
        $url = trim($url);
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL))
            continue;

        $imgIndex = count($localImages) + 1;
        $filename = "{$safeName}_{$newId}_{$imgIndex}.jpg";
        $destPath = "$imagesDir/$filename";

        // Download
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
        ]);
        $imgData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($imgData && $httpCode === 200) {
            $srcImg = @imagecreatefromstring($imgData);
            if ($srcImg) {
                $width = imagesx($srcImg);
                if ($width > 1920) {
                    $resized = imagescale($srcImg, 1920);
                    imagedestroy($srcImg);
                    $srcImg = $resized;
                }
                imagejpeg($srcImg, $destPath, 80);
                imagedestroy($srcImg);
                $localImages[] = "$imagesDir/$filename";
            }
        }
    }
}

// Parse links
$linksArray = [];
if ($links) {
    $linksArray = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $links))));
}

// Build GeoJSON feature
$feature = [
    'type' => 'Feature',
    'geometry' => [
        'type' => 'Point',
        'coordinates' => [$lng, $lat]
    ],
    'properties' => [
        'id' => $newId,
        'name' => $name,
        'cleanDesc' => $description,
        'styleUrl' => $styleUrl,
        'catKey' => $catKey,
        'images' => $localImages,
        'localImages' => $localImages,
        'imageUrl' => isset($localImages[0]) ? $localImages[0] : null,
        'remoteImageUrl' => null,
        'links' => $linksArray,
        'credit' => $credit ?: null,
        'creditUrl' => $creditUrl ?: null,
        'isLine' => false,
        'lat' => $lat,
        'lng' => $lng,
        'isLocalPoint' => true
    ]
];

// Add guest info if guest
if ($isGuest) {
    $feature['properties']['pendingGuest'] = [
        'name' => $guestName,
        'submitted' => time()
    ];
}

// Save
$points[] = $feature;
$jsonStr = json_encode($points, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if (!file_put_contents($targetFile, $jsonStr)) {
    echo json_encode(['success' => false, 'message' => 'Kunne ikke lagre filen']);
    exit;
}

// Send email notification for guest submissions
if ($isGuest) {
    $subject = "Nytt gjestepunkt: \"$name\"";
    $body = "Gjest: $guestName\n";
    $body .= "Punkt: $name\n";
    $body .= "Kategori: $catKey\n";
    $body .= "Posisjon: $lat, $lng\n";
    $body .= "Beskrivelse: $description\n";
    $body .= "Tidspunkt: " . date('d.m.Y H:i') . "\n\n";
    $body .= "Logg inn med admin-PIN for å godkjenne/avvise.";

    $headers = "From: noreply@voxcuriosa.no\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    @mail($ADMIN_EMAIL, $subject, $body, $headers);
}

echo json_encode([
    'success' => true,
    'id' => $newId,
    'role' => $isAdmin ? 'admin' : 'guest',
    'message' => $isGuest ? 'Punktet er sendt til godkjenning!' : 'Punktet er opprettet!'
]);
?>