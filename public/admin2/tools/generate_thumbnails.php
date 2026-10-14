<?php
$dir = '../uploads/item/';
$thumbDir = '../uploads/thumbnails/';

if (!file_exists($thumbDir)) {
    mkdir($thumbDir, 0755, true);
}

$images = glob($dir . "*.{jpg,jpeg,png}", GLOB_BRACE);

foreach ($images as $image) {
    $thumbPath = $thumbDir . basename($image);
    if (file_exists($thumbPath)) continue;

    $src = imagecreatefromstring(file_get_contents($image));
    $width = imagesx($src);
    $height = imagesy($src);

    $newWidth = 300;
    $newHeight = 300;

    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    imagejpeg($thumb, $thumbPath, 85);
    imagedestroy($src);
    imagedestroy($thumb);

    echo "✅ Thumbnail created: " . basename($thumbPath) . "<br>";
}
