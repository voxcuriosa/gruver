<?php
header('Content-Type: text/plain');

$oldDir = "/home/cpjvfkip/public_html/portal/gruver/bilder";
$newDir = "/home/cpjvfkip/public_html/gruver/bilder";

if (!file_exists($newDir)) {
    if (mkdir($newDir, 0755, true)) {
        echo "Created directory: $newDir\n";
    } else {
        die("Failed to create directory: $newDir\n");
    }
}

if (file_exists($oldDir) && is_dir($oldDir)) {
    $files = scandir($oldDir);
    $moved = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($files as $file) {
        if ($file != "." && $file != ".." && !is_dir($oldDir . "/" . $file)) {
            $oldPath = $oldDir . "/" . $file;
            $newPath = $newDir . "/" . $file;

            if (!file_exists($newPath)) {
                if (rename($oldPath, $newPath)) {
                    $moved++;
                } else {
                    $errors++;
                }
            } else {
                $skipped++;
            }
        }
    }

    echo "Migration Complete:\n";
    echo "- Moved: $moved\n";
    echo "- Skipped (already exist): $skipped\n";
    echo "- Errors: $errors\n";
} else {
    echo "Old directory does not exist or is not a directory: $oldDir\n";
}
?>