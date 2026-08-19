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
ini_set('memory_limit', '512M');
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    $formatted = "[" . date('H:i:s') . "] " . $msg . "\n";
    echo $formatted;
    file_put_contents('sync_log.txt', $formatted, FILE_APPEND);
    flush();
}

// Clear log at start
file_put_contents('sync_log.txt', "--- Sync Start: " . date('Y-m-d H:i:s') . " ---\n");

// 1. Prepare Directories
if (!file_exists($imagesDir)) {
    mkdir($imagesDir, 0755, true);
    logMsg("Created directory: $imagesDir");
}

// 2. Download KML
logMsg("Downloading KML via cURL...");
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $kmlDataUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$kmlContent = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($kmlContent === false || $httpCode !== 200) {
    die("Error: Failed to download KML. HTTP Code: $httpCode, Error: $curlError");
}
logMsg("KML Downloaded successfully (" . strlen($kmlContent) . " bytes)");

// 3. Parse KML
$xml = simplexml_load_string($kmlContent);
if (!$xml) {
    $err = libxml_get_last_error();
    die("Error: Failed to parse KML. " . ($err ? $err->message : ""));
}
$xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');

logMsg("Locating relevant folders recursively...");
$dom = new DOMDocument();
libxml_use_internal_errors(true);
if (!$dom->loadXML($kmlContent)) {
    die("Error: Failed to load KML into DOM.");
}
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('kml', 'http://www.opengis.net/kml/2.2');

// Try with namespace first, then without
$folderNodes = $xpath->query('//kml:Folder');
if ($folderNodes->length === 0) {
    logMsg("XPath '//kml:Folder' returned 0 results. Trying local-name fallback...");
    $folderNodes = $xpath->query("//*[local-name()='Folder']");
}

logMsg("Found " . $folderNodes->length . " folders in KML.");

$placemarks = [];

foreach ($folderNodes as $folderNode) {
    // Robust name extraction
    $nameNode = $xpath->query("kml:name", $folderNode)->item(0)
        ?: $xpath->query("*[local-name()='name']", $folderNode)->item(0);

    $folderName = $nameNode ? $nameNode->nodeValue : "Unknown Folder";
    logMsg("Inspecting folder: '$folderName'");

    $is_match = false;
    $admin_cat = null;

    if (stripos($folderName, 'verifiserte') !== false) {
        $is_match = true;
        $admin_cat = 'IKKE_VERIFISERT';
        logMsg(" -> Matched hidden admin layer: Ikke verifiserte");
    } else if (stripos($folderName, 'ikke funn') !== false) {
        $is_match = true;
        $admin_cat = 'SJEKKET_IKKE_FUNN';
        logMsg(" -> Matched hidden admin layer: Sjekket ut men ikke funn");
    } else if (stripos($folderName, 'Gulset') !== false) {
        $is_match = true;
        $admin_cat = null;
        logMsg(" -> Matched standard layer: Gulsetmarka");
    }

    if ($is_match) {
        $pmNodes = $xpath->query("kml:Placemark", $folderNode)
            ?: $xpath->query("*[local-name()='Placemark']", $folderNode);

        if ($pmNodes->length === 0) {
            // Fallback to searching all children for nodes with local-name 'Placemark'
            $pmNodes = $xpath->query(".//*[local-name()='Placemark']", $folderNode);
        }

        logMsg("    -> Found " . $pmNodes->length . " placemarks in this folder.");
        foreach ($pmNodes as $pmNode) {
            $pmXml = simplexml_import_dom($pmNode);
            if ($pmXml) {
                $placemarks[] = ['pm' => $pmXml, 'category' => $admin_cat];
            }
        }
    }
}

logMsg("Total placemarks identified for processing: " . count($placemarks));

if (empty($placemarks)) {
    die("Error: No valid folders found in KML.");
}

logMsg("Found " . count($placemarks) . " placemarks total across layers. Processing...");

$geojson = [
    'type' => 'FeatureCollection',
    'features' => []
];

$features = [];
$idCounter = 0;

$stats = [
    'processed' => 0,
    'images_downloaded' => 0,
    'images_skipped' => 0,
    'errors' => 0
];

foreach ($placemarks as $item) {
    $pm = $item['pm'];
    $forcedCategory = $item['category'];
    $idx = $idCounter;
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
            // DOWNLOAD AND OPTIMIZE via cURL (file_get_contents blocked by Google)
            try {
                $ch = curl_init($remoteUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    CURLOPT_HTTPHEADER => ['Referer: https://www.google.com/maps/d/'],
                ]);
                $imgData = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($imgData && $httpCode === 200) {
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
                    logMsg("Error: Failed to fetch $remoteUrl (HTTP $httpCode)");
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

    // Try direct access first (most placemarks), then namespace-aware fallback
    $kmlNs = 'http://www.opengis.net/kml/2.2';
    $pmPoint = isset($pm->Point) ? $pm->Point : null;
    $pmLineString = isset($pm->LineString) ? $pm->LineString : null;

    // Namespace-aware fallback using children()
    if (!$pmPoint && !$pmLineString) {
        $children = $pm->children($kmlNs);
        if (isset($children->Point)) {
            $pmPoint = $children->Point;
        } elseif (isset($children->LineString)) {
            $pmLineString = $children->LineString;
        }
    }

    if ($pmPoint) {
        $coordStr = (string) $pmPoint->coordinates;
        if (!$coordStr) {
            // Try namespace-aware coordinates
            $c = $pmPoint->children($kmlNs);
            $coordStr = isset($c->coordinates) ? (string) $c->coordinates : '';
        }
        $coords = explode(',', trim($coordStr));
        if (count($coords) >= 2) {
            $lng = (float) $coords[0];
            $lat = (float) $coords[1];
            $geometry = ['type' => 'Point', 'coordinates' => [$lng, $lat]];
        }
    } elseif ($pmLineString) {
        $isLine = true;
        $coordsRaw = trim((string) $pmLineString->coordinates);
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
        // Blacklisted domains for filtering links and descriptions
        $blacklistedDomains = [
            'googleusercontent.com',
            'usercontent.google.com',
            'photos.app.goo.gl',
            'photos.google.com',
            'google.com/share'
        ];

        // Clean Description
        $cleanDesc = $description;
        // Remove KML CDATA tags
        $cleanDesc = str_replace(['<![CDATA[', ']]>'], '', $cleanDesc);
        $cleanDesc = preg_replace('/<img[^>]+>/i', '', $cleanDesc);
        $cleanDesc = preg_replace('/<br\s*\/?>/i', "\n", $cleanDesc);

        // Links extraction for both array and text cleaning
        preg_match_all('/href="([^"]+)"|((?:https?:\/\/|www\.)[^\s<"\']+)/i', $description, $m);
        $rawLinks = array_unique(array_merge($m[1], $m[2]));

        foreach ($rawLinks as $l) {
            if (!$l)
                continue;
            $cleanL = rtrim($l, ']> ');

            $isBlacklisted = false;
            foreach ($blacklistedDomains as $domain) {
                if (strpos($cleanL, $domain) !== false) {
                    $isBlacklisted = true;
                    break;
                }
            }

            if ($isBlacklisted) {
                // Safety: NEVER strip local assets or mining maps
                if (strpos($cleanL, 'assets/') !== false || strpos($cleanL, 'mining_map') !== false) {
                    continue;
                }

                // Remove the link (and its containing <a> tag if it exists) from cleanDesc
                // 1. Remove <a> tags containing this link
                $cleanDesc = preg_replace('/<a\s+[^>]*href="' . preg_quote($l, '/') . '"[^>]*>.*?<\/a>/i', '', $cleanDesc);
                // 2. Remove the literal URL if it's still there
                $cleanDesc = str_replace($l, '', $cleanDesc);
                $cleanDesc = str_replace($cleanL, '', $cleanDesc);
            }
        }

        // Final cleanup of description labels and empty lines
        $cleanDesc = preg_replace('/\s+og\s*$/mu', '', $cleanDesc); // Remove trailing 'og'
        $cleanDesc = preg_replace('/\s+og\s+/iu', ' ', $cleanDesc); // Remove internal 'og'
        $cleanDesc = preg_replace('/(Nettside|Posisjon|Informasjon):?\s*$/mu', '', $cleanDesc); // Remove labels at end of lines
        $cleanDesc = strip_tags($cleanDesc); // Remove any remaining HTML tags
        $cleanDesc = trim($cleanDesc);
        $cleanDesc = preg_replace('/\n\n+/', "\n", $cleanDesc); // Collapse multiple newlines

        // Links Array Building
        $links = [];
        foreach ($rawLinks as $l) {
            if (!$l)
                continue;
            $cleanL = rtrim($l, ']> ');

            $isBlacklisted = false;
            foreach ($blacklistedDomains as $domain) {
                if (strpos($cleanL, $domain) !== false) {
                    $isBlacklisted = true;
                    break;
                }
            }
            if ($isBlacklisted) {
                // Safety: NEVER strip local assets or mining maps
                if (strpos($cleanL, 'assets/') !== false || strpos($cleanL, 'mining_map') !== false) {
                    // fall through to allowed
                } else {
                    continue;
                }
            }
            if (in_array($cleanL, $imageUrlList))
                continue;

            if (!in_array($cleanL, $links))
                $links[] = $cleanL;
        }

        // Category Logic (Refined to match Gulsetmarka styles)
        $catKey = 'DEFAULT';

        if ($forcedCategory !== null) {
            $catKey = $forcedCategory;
        } else {
            $fullText = mb_strtolower($name . " " . $description);

            if (strpos($styleUrl, 'E65100') !== false || strpos($styleUrl, 'C2185B') !== false)
                $catKey = 'GRUVE';
            elseif (strpos($styleUrl, '01579B') !== false || strpos($styleUrl, '1A237E') !== false || strpos($styleUrl, '3949AB') !== false)
                $catKey = 'HULE';
            elseif (strpos($styleUrl, '9C27B0') !== false)
                $catKey = 'BYGDEBORG';
            elseif (strpos($styleUrl, '795548') !== false || strpos($styleUrl, '4E342E') !== false)
                $catKey = 'GAPAHUK';
            elseif (strpos($styleUrl, 'BDBDBD') !== false)
                $catKey = 'VANN';
            elseif (strpos($styleUrl, '0288D1') !== false)
                $catKey = 'DIVERSE';
            elseif (strpos($styleUrl, 'FFEA00') !== false || strpos($styleUrl, 'FFD600') !== false)
                $catKey = 'UTSIKT';
            elseif (strpos($styleUrl, 'AFB42B') !== false)
                $catKey = 'HUSTUFT';
            elseif (strpos($styleUrl, '097138') !== false)
                $catKey = 'GRENSESTEIN';
            elseif (strpos($styleUrl, '000000') !== false)
                $catKey = 'GRAVHAUG';
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
                elseif (strpos($fullText, 'gapahuk') !== false)
                    $catKey = 'GAPAHUK';
                elseif (preg_match('/vei|stier/', $fullText))
                    $catKey = 'VEI';
            }
        }

        // Local Overrides (User requested: Replace external canal link with local file)
        if ($idCounter == 76 || $idCounter == 77) {
            $extLink = "http://kanaler.arnholm.nu/skandinavien/norge/fossums.shtml";
            $localFile = "kanalanlegg_utf8.html";
            $cleanDesc = str_replace($extLink, "", $cleanDesc);
            $cleanDesc = trim($cleanDesc);
            // Remove from links if present
            if (($key = array_search($extLink, $links)) !== false) {
                unset($links[$key]);
            }
            // Add local file to links for "Les mer" button
            if (!in_array($localFile, $links)) {
                $links[] = $localFile;
            }
        }

        $geojson['features'][] = [
            'type' => 'Feature',
            'geometry' => $geometry,
            'properties' => [
                'id' => $idCounter,
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
    $idCounter++;
}

// 3b. Apply Local Overrides from Changelog
// This ensures that Admin Tool moves are NOT overwritten by Google My Maps
$changelogFile = 'changelog.json';
if (file_exists($changelogFile)) {
    $changelog = json_decode(file_get_contents($changelogFile), true);
    if (is_array($changelog) && count($changelog) > 0) {
        logMsg("Applying " . count($changelog) . " local overrides from changelog...");

        $overriddenCount = 0;
        foreach ($geojson['features'] as &$feature) {
            $p = &$feature['properties'];
            $currentLat = $p['lat'];
            $currentLng = $p['lng'];
            $hasChanged = false;

            // Process changelog entries in order to handle sequential moves
            foreach ($changelog as $entry) {
                // Match by name and current coordinates (rounded to 6 decimals to handle precision)
                if (
                    $entry['name'] === $p['name'] &&
                    round($entry['oldLat'], 6) === round($currentLat, 6) &&
                    round($entry['oldLng'], 6) === round($currentLng, 6)
                ) {

                    $currentLat = floatval($entry['newLat']);
                    $currentLng = floatval($entry['newLng']);
                    $hasChanged = true;
                }
            }

            if ($hasChanged) {
                $p['lat'] = $currentLat;
                $p['lng'] = $currentLng;
                $p['local_override'] = true;

                if ($feature['geometry']['type'] === 'Point') {
                    $feature['geometry']['coordinates'] = [$currentLng, $currentLat];
                    $overriddenCount++;
                }
            }
        }
        logMsg("Applied overrides to $overriddenCount features.");
    }
}

// 3c. Apply Persistent Overrides (Visibility, Manual Images)
// This handles "Hide", "Delete" and manual image additions from Admin Tool
$overridesFile = 'overrides.json';
if (file_exists($overridesFile)) {
    $overrides = json_decode(file_get_contents($overridesFile), true);
    if (is_array($overrides) && count($overrides) > 0) {
        logMsg("Applying cumulative persistent overrides from overrides.json...");

        // Map overrides by name + coordinates
        // We collect ALL overrides for each feature to apply them cumulatively
        $overM = [];
        foreach ($overrides as $o) {
            $key = $o['name'] . '|' . round($o['lat'], 4) . '|' . round($o['lng'], 4);
            if (!isset($overM[$key]))
                $overM[$key] = [];
            $overM[$key][] = $o;
        }

        $newFeatures = [];
        foreach ($geojson['features'] as &$feature) {
            $p = &$feature['properties'];
            $key = $p['name'] . '|' . round($p['lat'] ?? 0, 4) . '|' . round($p['lng'] ?? 0, 4);

            $featureOverrides = null;
            if (isset($overM[$key])) {
                $featureOverrides = $overM[$key];
            } else {
                // Fuzzy matching fallback: same name + distance < 0.0001 (~11 meters)
                // This handles cases where Google My Maps shifted the point slightly.
                foreach ($overrides as $o) {
                    if ($o['name'] === $p['name']) {
                        $pLat = $p['lat'] ?? 0;
                        $pLng = $p['lng'] ?? 0;
                        $dist = sqrt(pow($o['lat'] - $pLat, 2) + pow($o['lng'] - $pLng, 2));
                        if ($dist < 0.00015) { // ~15 meters tolerance
                            // Collect matching overrides
                            if ($featureOverrides === null)
                                $featureOverrides = [];
                            $featureOverrides[] = $o;
                        }
                    }
                }
                if ($featureOverrides) {
                    logMsg("Fuzzy match found for '{$p['name']}' using vicinity search (" . count($featureOverrides) . " overrides)");
                }
            }

            if ($featureOverrides) {

                // 1. Check for Deletion (if any action is delete, we skip this feature)
                $isDeleted = false;
                foreach ($featureOverrides as $o) {
                    if ($o['action'] === 'delete') {
                        $isDeleted = true;
                        break;
                    }
                }
                if ($isDeleted) {
                    logMsg("Skipping deleted feature: " . $p['name']);
                    continue;
                }

                // 2. Apply other overrides cumulatively
                foreach ($featureOverrides as $o) {
                    if ($o['action'] === 'hide') {
                        $p['hidden'] = true;
                    }
                    if ($o['action'] === 'add_image' && !empty($o['value'])) {
                        $img = $o['value'];
                        if (!isset($p['images']))
                            $p['images'] = [];
                        if (!isset($p['localImages']))
                            $p['localImages'] = [];

                        if (!in_array($img, $p['images']))
                            $p['images'][] = $img;
                        if (!in_array($img, $p['localImages']))
                            $p['localImages'][] = $img;
                        if (!$p['imageUrl'])
                            $p['imageUrl'] = $img;
                    }
                    if ($o['action'] === 'rename' && is_array($o['value'])) {
                        $p['displayName'] = !empty($o['value']['displayName']) ? $o['value']['displayName'] : null;
                        $p['displayDesc'] = !empty($o['value']['displayDesc']) ? $o['value']['displayDesc'] : null;
                        if (!empty($o['value']['category'])) {
                            $p['catKey'] = $o['value']['category'];
                            $p['category'] = $o['value']['category'];
                        }
                    }
                }
            }

            // --- HARDCODED LINE VISIBILITY RULE ---
            // Hide all LineStrings except Åsmund Nordgårds vannledning
            if ($feature['geometry']['type'] === 'LineString') {
                if (stripos($p['name'], 'Nordgård') === false) {
                    $p['hidden'] = true;
                    // logMsg("Hiding non-Nordgård line: " . $p['name']);
                }
            }

            $newFeatures[] = $feature;
        }
        $geojson['features'] = $newFeatures;
    }
}

// 4. Save JSON
$jsonStr = json_encode($geojson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($jsonOutputFile, $jsonStr)) {
    logMsg("Success! Saved $jsonOutputFile.");
    file_put_contents('last_sync.txt', time());
} else {
    logMsg("Error: Could not write $jsonOutputFile");
}

logMsg("Done.");
logMsg("Stats: Processed: {$stats['processed']}, New Images Downloaded: {$stats['images_downloaded']}, Skipped (Existing): {$stats['images_skipped']}, Errors: {$stats['errors']}");
?>