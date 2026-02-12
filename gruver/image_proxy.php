<?php
// image_proxy.php - Fetches images securely to bypass Mixed Content warnings + Adds Caching

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Basic security check: Require a 'url' parameter
if (!isset($_GET['url']) || empty($_GET['url'])) {
    header("HTTP/1.1 400 Bad Request");
    die("Error: No URL provided.");
}

$url = $_GET['url'];

// Validate URL (basic check)
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    header("HTTP/1.1 400 Bad Request");
    die("Error: Invalid URL.");
}

// CACHING SETUP
$cacheDir = __DIR__ . '/cache'; // Create a 'cache' folder in the same directory
if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Create a unique filename for the cache based on the URL
$cacheKey = md5($url);
$cacheFile = $cacheDir . '/' . $cacheKey;
$cacheTime = 60 * 60 * 24 * 7; // Cache for 1 week (seconds)

// CHECK CACHE
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    // Serve from cache
    $contentType = mime_content_type($cacheFile);
    header("Content-Type: $contentType");
    header("X-Cache: HIT");
    readfile($cacheFile);
    exit;
}

// IF NOT IN CACHE, FETCH IT
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Handle potential SSL issues with old sources
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$imageData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlError = curl_error($ch);

curl_close($ch);

if ($httpCode == 200 && $imageData) {
    // Save to cache
    file_put_contents($cacheFile, $imageData);

    // Set proper headers and output image
    header("Content-Type: $contentType");
    header("X-Cache: MISS");
    echo $imageData;

    // --- GARBAGE COLLECTION (1% chance) ---
    if (rand(1, 100) === 1) {
        $files = glob($cacheDir . '/*');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) > (60 * 60 * 24 * 30)) { // 30 days
                    unlink($file);
                }
            }
        }
    }
} else {
    // Return a placeholder or error
    header("HTTP/1.1 404 Not Found");
    echo "Error fetching image: HTTP $httpCode. " . ($curlError ? "Curl Error: $curlError" : "");
}
?>