<?php
declare(strict_types=1);

namespace Apollon\Services;

/**
 * Resizes embedded cover art and re-encodes it as a small WebP thumbnail.
 */
final class ImageProcessor
{
    public static function toWebp(?string $imageData, int $targetWidth = 300, int $quality = 70): ?string
    {
        if (!$imageData || !function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            return null;
        }
        $sourceImage = @imagecreatefromstring($imageData);
        if (!$sourceImage) {
            return null;
        }

        $resizedImage = imagecreatetruecolor($targetWidth, $targetWidth);
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetWidth, imagesx($sourceImage), imagesy($sourceImage));

        ob_start();
        imagewebp($resizedImage, null, $quality);
        $webpData = ob_get_clean();
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        return $webpData === false ? null : $webpData;
    }

    /**
     * Resizes a poster image to a 2:3 box (scale-to-cover + center crop)
     * and re-encodes it as WebP.
     */
    public static function toWebpPoster(?string $imageData, int $targetWidth = 300, int $targetHeight = 450, int $quality = 70): ?string
    {
        if (!$imageData || !function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            return null;
        }
        $sourceImage = @imagecreatefromstring($imageData);
        if (!$sourceImage) {
            return null;
        }
        $srcW = imagesx($sourceImage);
        $srcH = imagesy($sourceImage);

        $scale = max($targetWidth / $srcW, $targetHeight / $srcH);
        $resizedW = max(1, (int)round($srcW * $scale));
        $resizedH = max(1, (int)round($srcH * $scale));

        $resizedImage = imagecreatetruecolor($resizedW, $resizedH);
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $resizedW, $resizedH, $srcW, $srcH);

        $poster = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($poster, false);
        imagesavealpha($poster, true);
        $srcX = (int)round(($resizedW - $targetWidth) / 2);
        $srcY = (int)round(($resizedH - $targetHeight) / 2);
        imagecopy($poster, $resizedImage, 0, 0, $srcX, $srcY, $targetWidth, $targetHeight);

        ob_start();
        imagewebp($poster, null, $quality);
        $webpData = ob_get_clean();
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        imagedestroy($poster);
        return $webpData === false ? null : $webpData;
    }
}
