<?php
/**
 * Simple PHP Proxy to bypass X-Frame-Options
 * and allow embedding of most sites in an iframe.
 */

// Better URL extraction for complex WMS strings
$url = $_GET['url'] ?? '';
if ($url) {
    // Reconstruct the full URL by appending all other GET parameters
    // This is necessary because Leaflet appends WMS parameters directly to the proxy URL
    $params = $_GET;
    unset($params['url']);
    unset($params['cb']); // Skip cache-buster from reconstruction

    if (!empty($params)) {
        $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($params);
    }
}

if (!$url) {
    header("HTTP/1.1 400 Bad Request");
    exit('No URL provided.');
}

// Helper function to convert relative URLs to absolute
function make_absolute($url, $base)
{
    if (preg_match('/^https?:\/\//i', $url))
        return $url;
    if (strpos($url, '//') === 0)
        return "http:" . $url;
    if (strpos($url, '/') === 0) {
        $parsed = parse_url($base);
        return $parsed['scheme'] . '://' . $parsed['host'] . $url;
    }
    return rtrim($base, '/') . '/' . ltrim($url, '/');
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_ENCODING, "");

$html = curl_exec($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

if ($html === false) {
    header("HTTP/1.1 502 Bad Gateway");
    exit('Could not fetch the requested page.');
}

$parsed = parse_url($finalUrl);
$baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
if (isset($parsed['path']) && !empty($parsed['path'])) {
    if (strpos(basename($parsed['path']), '.') !== false) {
        $baseUrl .= dirname($parsed['path']) . '/';
    } else {
        $baseUrl .= rtrim($parsed['path'], '/') . '/';
    }
} else {
    $baseUrl .= '/';
}

$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$selfPath = $proto . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/proxy.php";
$cacheBust = Date('YmdH');

// HANDLE CSS
if ($contentType && strpos($contentType, 'text/css') !== false) {
    $html = preg_replace_callback('/url\s*\(\s*["\']?([^"\'\)]+)["\']?\s*\)/i', function ($matches) use ($baseUrl, $selfPath, $cacheBust) {
        $u = $matches[1];
        if (strpos($u, 'data:') === 0 || strpos($u, 'http') === 0)
            return $matches[0];
        $abs = make_absolute($u, $baseUrl);
        return "url('" . $selfPath . "?url=" . urlencode($abs) . "&cb=" . $cacheBust . "')";
    }, $html);
    header("Content-Type: text/css");
    echo $html;
    exit;
}

// HANDLE NON-HTML (IMAGE, PDF, ETC)
if ($contentType && strpos($contentType, 'text/html') === false) {
    header("Content-Type: $contentType");
    echo $html;
    exit;
}

// HANDLE HTML
$injection = "\n    <style>body { -webkit-font-smoothing: antialiased; font-family: sans-serif; }</style>\n";
if (stripos($html, '<head>') !== false) {
    $html = preg_replace('/<head>/i', "<head>$injection", $html, 1);
} else {
    $html = "<head>$injection</head>" . $html;
}

$html = preg_replace('/<meta[^>]+http-equiv=["\']Content-Security-Policy["\'][^>]*>/i', '', $html);
$html = preg_replace('/if\s*\(\s*window\.top\s*!==\s*window\.self\s*\)\s*{\s*window\.top\.location\s*=\s*window\.self\.location\s*;\s*}/i', '', $html);
$html = preg_replace('/if\s*\(\s*top\.location\s*!==\s*location\s*\)\s*{\s*top\.location\s*=\s*location\s*;\s*}/i', '', $html);

// Rewrite attributes that link to assets
$html = preg_replace_callback('/(<(?:link|script|img|source|a)[^>]+(?:src|href)=["\'])([^"\']+\.(?:css|js|jpg|jpeg|png|gif|webp|svg|mp4|webm|pdf)(?:\?.*)?)(["\'])/i', function ($matches) use ($baseUrl, $selfPath, $cacheBust) {
    $tag = $matches[1];
    $u = $matches[2];
    $end = $matches[3];

    if (strpos($u, 'proxy.php') !== false || strpos($u, 'data:') === 0)
        return $matches[0];

    $abs = make_absolute($u, $baseUrl);
    return $tag . $selfPath . "?url=" . urlencode($abs) . "&cb=" . $cacheBust . $end;
}, $html);

// Rewrite url() in HTML (style tags or attributes)
$html = preg_replace_callback('/url\s*\(\s*["\']?([^"\'\)]+)["\']?\s*\)/i', function ($matches) use ($baseUrl, $selfPath, $cacheBust) {
    $u = $matches[1];
    if (strpos($u, 'data:') === 0 || strpos($u, 'http') === 0)
        return $matches[0];
    $abs = make_absolute($u, $baseUrl);
    return "url('" . $selfPath . "?url=" . urlencode($abs) . "&cb=" . $cacheBust . "')";
}, $html);

header("Content-Type: text/html; charset=UTF-8");
header("X-Frame-Options: ALLOWALL");
header("Access-Control-Allow-Origin: *");

echo $html;
