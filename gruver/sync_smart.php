<?php
/**
 * sync_smart.php
 * 
 * Automates the synchronization of mining data from Google My Maps (KML).
 * - Downloads KML
 * - Parses data to GeoJSON
 * - Identifies new images and downloads/optimizes them (1920px max, JPG 80%)
 * - Generates unique filenames: Name_ID_Index.jpg (Matches local Python script)
 * - Updates full_data.json
 * 
 * Usage: Visit this script in browser or via cron.
 */

// Configuration
$kmlDataUrl = "https://www.google.com/maps/d/u/0/kml?forcekml=1&mid=1GNd6tZb_il76nsonvH7Oe0SKrX2Qp8B2";
$jsonOutputFile = 'full_data.json';
$imagesDir = 'bilder';
$maxWidth = 1920;
$jpegQuality = 80;
$secretKey = "vox_cron_auto_7734"; // Security key for Cron

// Security Check
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    die("Access denied: Invalid or missing key.");
}

// Increase execution time for image processing
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

header('Content-Type: text/plain; charset=utf-8');

function logMsg($msg)
{
    echo "[" . date('H:i:s') . "] " . $msg . "\n";
    flush();
}

// 1. Prepare Directories
if (!file_exists($imagesDir)) {
    mkdir($imagesDir, 0755, true);
    logMsg("Created directory: $imagesDir");
}

// 2. Download KML
logMsg("Downloading KML...");
$kmlContent = @file_get_contents($kmlDataUrl);
if ($kmlContent === false) {
    die("Error: Failed to download KML from Google.");
}

// 3. Parse KML
$xml = simplexml_load_string($kmlContent);
if ($xml === false) {
    die("Error: Failed to parse KML XML.");
}
$xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');
$placemarks = $xml->xpath('//kml:Placemark');

logMsg("Found " . count($placemarks) . " placemarks. Processing...");

$geojson = [
    'type' => 'FeatureCollection',
    'features' => []
];

$stats = [
    'processed' => 0,
    'images_downloaded' => 0,
    'images_skipped' => 0,
    'errors' => 0
];

foreach ($placemarks as $idx => $pm) {
    // Extract Basic Data
    $name = (string) $pm->name;
    $description = (string) $pm->description;
    $styleUrl = (string) $pm->styleUrl;

    // safeName logic (Matches Python: Name_ID_Index.jpg)
    // Python: re.sub(r'[^a-zA-Z0-9æøåÆØÅ]+', '_', name)
    $safeName = preg_replace('/[^a-zA-Z0-9æøåÆØÅ]/u', '_', $name);
    $safeName = preg_replace('/_+/', '_', $safeName);
    $safeName = trim($safeName, '_');

    // Extract ExtendedData
    $extendedData = [];
    if (isset($pm->ExtendedData->Data)) {
        foreach ($pm->ExtendedData->Data as $data) {
            $attr = $data->attributes();
            $extendedData[(string) $attr['name']] = (string) $data->value;
        }
    }

    // Identify Images
    $imageUrlList = [];

    // A. From ExtendedData
    if (isset($extendedData['gx_media_links'])) {
        $urls = preg_split('/\s+/', trim($extendedData['gx_media_links']));
        foreach ($urls as $u)
            if ($u)
                $imageUrlList[] = $u;
    }

    // B. From Description
    preg_match_all('/<img[^>]+src="([^">]+)"/i', $description, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $u) {
            if (!in_array($u, $imageUrlList))
                $imageUrlList[] = $u;
        }
    }

    // Filter valid images (ignore assets/ for download, handle google/hosted)
    $candidateUrls = [];
    foreach ($imageUrlList as $url) {
        if (strpos($url, 'assets/') !== false || strpos($url, 'mining_map') !== false) {
            continue; // Skip assets, we only download remote content
        }
        // Normalize Google URLs
        if (strpos($url, 'googleusercontent.com') !== false && strpos($url, 'fife=s') === false) {
            $parts = explode('?', $url);
            $url = $parts[0] . '?fife=s16383';
        }
        $candidateUrls[] = $url;
    }
    $candidateUrls = array_values(array_unique($candidateUrls));

    // Process Images
    $localImages = [];

    foreach ($candidateUrls as $imgIndex => $remoteUrl) {
        // ID is $idx (0-based from loop)
        // Filename: Name_ID_Index.jpg (Index is 1-based)
        $filename = "{$safeName}_{$idx}_" . ($imgIndex + 1) . ".jpg";
        $localPath = "$imagesDir/$filename"; // Relative for JSON
        $fsPath = __DIR__ . "/$localPath";   // Absolute for file ops

        if (file_exists($fsPath)) {
            $localImages[] = $localPath;
            $stats['images_skipped']++;
        } else {
            // DOWNLOAD AND OPTIMIZE
            // logMsg("Downloading new image: $filename");
            try {
                $imgData = @file_get_contents($remoteUrl);
                if ($imgData) {
                    $srcImg = @imagecreatefromstring($imgData);
                    if ($srcImg) {
                        $width = imagesx($srcImg);
                        $height = imagesy($srcImg);

                        // Resize if needed
                        if ($width > $maxWidth) {
                            $ratio = $maxWidth / $width;
                            $newHeight = intval($height * $ratio);
                            $newImg = imagescale($srcImg, $maxWidth, $newHeight);
                            imagedestroy($srcImg);
                            $srcImg = $newImg;
                        }

                        // Save as JPG
                        imagejpeg($srcImg, $fsPath, $jpegQuality);
                        imagedestroy($srcImg);

                        $localImages[] = $localPath;
                        $stats['images_downloaded']++;
                    } else {
                        logMsg("Error: Invalid image data for $filename");
                        $stats['errors']++;
                    }
                } else {
                    logMsg("Error: Failed to fetch $remoteUrl");
                    $stats['errors']++;
                }
            } catch (Exception $e) {
                logMsg("Exception: " . $e->getMessage());
                $stats['errors']++;
            }
        }
    }

    // Re-inject assets into localImages list if they exist in original links
    // (To keep viewer working with assets)
    // Simplified: Scan original links again for assets
    foreach ($imageUrlList as $url) {
        if (strpos($url, 'assets/') !== false || strpos($url, 'mining_map') !== false) {
            $localImages[] = $url;
        }
    }

    // Geometry & Properties Construction
    $geometry = null;
    $isLine = false;
    $lat = 0;
    $lng = 0;

    if (isset($pm->Point)) {
        $coords = explode(',', (string) $pm->Point->coordinates);
        if (count($coords) >= 2) {
            $lng = (float) $coords[0];
            $lat = (float) $coords[1];
            $geometry = ['type' => 'Point', 'coordinates' => [$lng, $lat]];
        }
    } elseif (isset($pm->LineString)) {
        $isLine = true;
        $coordsRaw = trim((string) $pm->LineString->coordinates);
        $lines = preg_split('/\s+/', $coordsRaw);
        $points = [];
        foreach ($lines as $line) {
            $p = explode(',', $line);
            if (count($p) >= 2)
                $points[] = [(float) $p[0], (float) $p[1]];
        }
        if (!empty($points)) {
            $geometry = ['type' => 'LineString', 'coordinates' => $points];
            $lat = $points[0][1];
            $lng = $points[0][0];
        }
    }

    if ($geometry) {
        // Clean Description
        $cleanDesc = $description;
        $cleanDesc = preg_replace('/<img[^>]+>/i', '', $cleanDesc);
        $cleanDesc = preg_replace('/<br\s*\/?>/i', "\n", $cleanDesc);
        $cleanDesc = preg_replace('/Nettside:?[\s\xa0]*(\n|$)/iu', '$1', $cleanDesc);
        $cleanDesc = preg_replace('/Posisjon:?[\s\xa0]*(\n|$)/iu', '$1', $cleanDesc);
        $cleanDesc = preg_replace('/Informasjon:?[\s\xa0]*(\n|$)/iu', '$1', $cleanDesc);
        $cleanDesc = preg_replace('/<a\s+[^>]*href=""[^>]*>.*?<\/a>/i', '', $cleanDesc);
        $cleanDesc = trim($cleanDesc);

        // Links
        $links = [];
        preg_match_all('/href="([^"]+)"|((?:https?:\/\/|www\.)[^\s<"\']+)/i', $description, $m);
        $rawLinks = array_merge($m[1], $m[2]);
        foreach ($rawLinks as $l)
            if ($l && !in_array($l, $links))
                $links[] = $l;

        // Category Logic (Simplified Port)
        $catKey = 'DEFAULT';
        $fullText = mb_strtolower($name . " " . $description);
        if (strpos($styleUrl, 'E65100') !== false || strpos($styleUrl, 'C2185B') !== false)
            $catKey = 'GRUVE';
        elseif (strpos($styleUrl, '01579B') !== false)
            $catKey = 'BYGDEBORG';
        elseif (strpos($styleUrl, '4E342E') !== false)
            $catKey = 'GAPAHUK';
        elseif (strpos($styleUrl, 'BDBDBD') !== false)
            $catKey = 'VANN';
        elseif (strpos($styleUrl, 'FFEA00') !== false)
            $catKey = 'UTSIKT';
        elseif (strpos($styleUrl, 'AFB42B') !== false)
            $catKey = 'HUSTUFT';
        elseif (strpos($styleUrl, '097138') !== false)
            $catKey = 'GRENSESTEIN';
        elseif (strpos($styleUrl, '1A237E') !== false)
            $catKey = 'HULE';
        else {
            // Keyword fallback
            if (preg_match('/gruve|skjerp|stoll|synk/', $fullText))
                $catKey = 'GRUVE';
            elseif (strpos($fullText, 'bygdeborg') !== false)
                $catKey = 'BYGDEBORG';
            elseif (strpos($fullText, 'hustuft') !== false)
                $catKey = 'HUSTUFT';
            elseif (strpos($fullText, 'hule') !== false)
                $catKey = 'HULE';
            elseif (strpos($fullText, 'utsikt') !== false)
                $catKey = 'UTSIKT';
            elseif (preg_match('/vei|stier/', $fullText))
                $catKey = 'VEI';
        }

        $geojson['features'][] = [
            'type' => 'Feature',
            'geometry' => $geometry,
            'properties' => [
                'id' => $idx,
                'name' => $name,
                'cleanDesc' => $cleanDesc,
                'styleUrl' => $styleUrl,
                'catKey' => $catKey,
                'images' => $imageUrlList, // Original URLs
                'localImages' => $localImages,
                'imageUrl' => isset($localImages[0]) ? $localImages[0] : null,
                'remoteImageUrl' => isset($imageUrlList[0]) ? $imageUrlList[0] : null,
                'links' => $links,
                'isLine' => $isLine,
                'lat' => $lat,
                'lng' => $lng
            ]
        ];
        $stats['processed']++;
    }
}

// 4. Save JSON
$jsonStr = json_encode($geojson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($jsonOutputFile, $jsonStr)) {
    logMsg("Success! Saved $jsonOutputFile.");
} else {
    logMsg("Error: Could not write $jsonOutputFile");
}

logMsg("Done.");
logMsg("Stats: Processed: {$stats['processed']}, New Images Downloaded: {$stats['images_downloaded']}, Skipped (Existing): {$stats['images_skipped']}, Errors: {$stats['errors']}");
?>