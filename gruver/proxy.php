<?php
/**
 * Simple PHP Proxy to bypass X-Frame-Options
 * and allow embedding of most sites in an iframe.
 */

$url = $_GET['url'] ?? '';
if (!$url) {
    header("HTTP/1.1 400 Bad Request");
    exit('No URL provided.');
}

// Basic validation: must be a full URL
if (!preg_match('/^https?:\/\//i', $url)) {
    header("HTTP/1.1 400 Bad Request");
    exit('Invalid URL format.');
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_ENCODING, ""); // Handle gzip

$html = curl_exec($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

if ($html === false) {
    header("HTTP/1.1 502 Bad Gateway");
    exit('Could not fetch the requested page.');
}

// If it's not HTML, just pass it through 
if ($contentType && strpos($contentType, 'text/html') === false) {
    header("Content-Type: $contentType");
    echo $html;
    exit;
}

// Fix relative paths using the <base> tag
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

$injection = "\n    <base href=\"$baseUrl\">\n    <style>body { -webkit-font-smoothing: antialiased; }</style>\n";

if (stripos($html, '<head>') !== false) {
    $html = preg_replace('/<head>/i', "<head>$injection", $html, 1);
} else {
    $html = "<head>$injection</head>" . $html;
}

// Strip potentially harmful or frame-blocking CSP meta tags
$html = preg_replace('/<meta[^>]+http-equiv=["\']Content-Security-Policy["\'][^>]*>/i', '', $html);

// Strip common JS frame busters
$html = preg_replace('/if\s*\(\s*window\.top\s*!==\s*window\.self\s*\)\s*{\s*window\.top\.location\s*=\s*window\.self\.location\s*;\s*}/i', '', $html);
$html = preg_replace('/if\s*\(\s*top\.location\s*!==\s*location\s*\)\s*{\s*top\.location\s*=\s*location\s*;\s*}/i', '', $html);

header("Content-Type: text/html; charset=UTF-8");
header("X-Frame-Options: ALLOWALL");
header("Access-Control-Allow-Origin: *");

echo $html;
