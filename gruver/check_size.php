<?php
$dir = 'assets/';
$files = [];
if (is_dir($dir)) {
    $it = new DirectoryIterator($dir);
    foreach ($it as $fileinfo) {
        if (!$fileinfo->isDot() && $fileinfo->isFile()) {
            $ext = strtolower($fileinfo->getExtension());
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                $path = $dir . $fileinfo->getFilename();
                $files[] = [
                    'name' => $fileinfo->getFilename(),
                    'size_mb' => round($fileinfo->getSize() / 1024 / 1024, 2),
                    'dimensions' => getimagesize($path)
                ];
            }
        }
    }
}
echo json_encode([
    'files' => $files,
    'extensions' => [
        'gd' => extension_loaded('gd'),
        'imagick' => extension_loaded('imagick')
    ]
]);
?>