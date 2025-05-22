<?php
// Create the images directory if it doesn't exist
$imagesDir = __DIR__ . '/assets/images';
if (!file_exists($imagesDir)) {
    mkdir($imagesDir, 0777, true);
}

// Create a simple image with text
function createDefaultAvatar($text, $bgColor, $textColor, $filename) {
    $width = 200;
    $height = 200;
    $image = imagecreatetruecolor($width, $height);
    
    // Allocate colors
    $bg = imagecolorallocate($image, $bgColor[0], $bgColor[1], $bgColor[2]);
    $textColor = imagecolorallocate($image, $textColor[0], $textColor[1], $textColor[2]);
    
    // Fill background
    imagefilledrectangle($image, 0, 0, $width, $height, $bg);
    
    // Add text
    $font = 5; // Built-in font (1-5)
    $textWidth = imagefontwidth($font) * strlen($text);
    $textX = ($width - $textWidth) / 2;
    $textY = ($height - imagefontheight($font)) / 2;
    
    imagestring($image, $font, $textX, $textY, $text, $textColor);
    
    // Save the image
    imagepng($image, $filename);
    imagedestroy($image);
}

// Create default doctor avatar (blue)
createDefaultAvatar(
    'DR', 
    [200, 230, 255], // Light blue
    [0, 100, 200],   // Dark blue
    __DIR__ . '/assets/images/default-doctor.png'
);

// Create default patient avatar (green)
createDefaultAvatar(
    'PT', 
    [200, 255, 230], // Light green
    [0, 150, 100],   // Dark green
    __DIR__ . '/assets/images/default-patient.png'
);

echo "Default avatars created successfully!\n";
?>
