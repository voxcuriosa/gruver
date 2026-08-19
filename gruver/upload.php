<?php
/**
 * upload.php - Handles image uploads for the georeferencing tool
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

$targetDir = "assets/";
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
}

if (!isset($_FILES['image'])) {
    die(json_encode(['success' => false, 'error' => 'No file uploaded']));
}

$file = $_FILES['image'];
$fileName = basename($file['name']);
$targetFilePath = $targetDir . $fileName;
$fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

// Allow certain file formats
$allowTypes = array('jpg', 'png', 'jpeg', 'gif', 'webp');
if (in_array(strtolower($fileType), $allowTypes)) {
    // Upload file to server
    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        echo json_encode(['success' => true, 'filePath' => $targetFilePath]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Only JPG, JPEG, PNG, WEBP & GIF files are allowed.']);
}
?>