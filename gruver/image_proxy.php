<?php
/**
 * image_proxy.php - Serves a resized version of an image for mobile performance.
 * Supports caching to minimize CPU usage.
 */

// Basic configuration
$max_dimension = 2560; // Max width or height for mobile
$cache_dir = 'assets/cache/';
$quality = 85;

// Ensure cache directory exists
if (!file_exists($cache_dir)) {
    mkdir($cache_dir, 0777, true);
}

// Get requested image
$img_path = isset($_GET['img']) ? $_GET['img'] : null;
if (!$img_path || !file_exists($img_path)) {
    header("HTTP/1.1 404 Not Found");
    exit("Image not found.");
}

// Security: Ensure the image is in the assets/ directory
$real_path = realpath($img_path);
$allowed_dir = realpath('assets/');
if (strpos($real_path, $allowed_dir) !== 0) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access denied.");
}

// Get image info
$info = getimagesize($img_path);
if (!$info) {
    header("HTTP/1.1 400 Bad Request");
    exit("Invalid image file.");
}

$width = $info[0];
$height = $info[1];
$mime = $info['mime'];

// If image is already small enough, serve it directly
if ($width <= $max_dimension && $height <= $max_dimension) {
    header("Content-Type: " . $mime);
    readfile($img_path);
    exit;
}

// Calculate new dimensions
$ratio = $width / $height;
if ($width > $height) {
    $new_width = $max_dimension;
    $new_height = round($max_dimension / $ratio);
} else {
    $new_height = $max_dimension;
    $new_width = round($max_dimension * $ratio);
}

// Cache file name based on original path and max dimension
$cache_name = md5($img_path . $max_dimension) . (strpos($mime, 'png') !== false ? '.png' : '.jpg');
$cache_file = $cache_dir . $cache_name;

// Serve from cache if it exists and is newer than source
if (file_exists($cache_file) && filemtime($cache_file) > filemtime($img_path)) {
    header("Content-Type: " . ($new_width === $width ? $mime : (strpos($cache_name, '.png') !== false ? 'image/png' : 'image/jpeg')));
    readfile($cache_file);
    exit;
}

// Resize using GD
ini_set('memory_limit', '512M'); // Increase memory limit for giant images

$src = null;
if ($mime == 'image/jpeg') {
    $src = imagecreatefromjpeg($img_path);
} elseif ($mime == 'image/png') {
    $src = imagecreatefrompng($img_path);
} elseif ($mime == 'image/webp') {
    $src = imagecreatefromwebp($img_path);
}

if (!$src) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Failed to process image.");
}

$dst = imagecreatetruecolor($new_width, $new_height);

// Preserve transparency for PNG
if ($mime == 'image/png') {
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
    imagefilledrectangle($dst, 0, 0, $new_width, $new_height, $transparent);
}

imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

// Save and serve
if (strpos($cache_name, '.png') !== false) {
    header("Content-Type: image/png");
    imagepng($dst, $cache_file, 8); // PNG compression 0-9
    imagepng($dst);
} else {
    header("Content-Type: image/jpeg");
    imagejpeg($dst, $cache_file, $quality);
    imagejpeg($dst, null, $quality);
}

imagedestroy($src);
imagedestroy($dst);
?>