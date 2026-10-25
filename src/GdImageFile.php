<?php

declare(strict_types=1);

namespace Blueprint;

use GdImage;

/**
 * An image backed by GD.
 */
class GdImageFile implements Image
{
    /**
     * Encoder speed, 0 (slow, smallest) to 10 (fast).
     */
    private const AVIF_SPEED = 6;

    private function __construct(private GdImage $image)
    {
    }

    public static function open(string $file): self
    {
        $data = file_get_contents($file);
        $image = $data !== false ? @imagecreatefromstring($data) : false;

        if ($image === false) {
            throw new \RuntimeException("Can't decode the image $file");
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return new self(self::orient($image, $file));
    }

    public function width(): int
    {
        return imagesx($this->image);
    }

    public function height(): int
    {
        return imagesy($this->image);
    }

    public function scale(float $factor): void
    {
        $scaled = imagescale(
            $this->image,
            max(1, (int) round($this->width() * $factor)),
            max(1, (int) round($this->height() * $factor)),
            IMG_GENERALIZED_CUBIC,
        );

        if ($scaled === false) {
            throw new \RuntimeException("Can't scale the image");
        }

        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        $this->image = $scaled;
    }

    public function avif(int $quality): string
    {
        ob_start();

        try {
            if (!imageavif($this->image, null, $quality, self::AVIF_SPEED)) {
                throw new \RuntimeException("Can't encode the image as AVIF");
            }

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * GD ignores the EXIF orientation of JPEGs (e.g. portrait photos from phones).
     */
    private static function orient(GdImage $image, string $file): GdImage
    {
        if (!function_exists("exif_read_data") || !in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ["jpg", "jpeg"], true)) {
            return $image;
        }

        $rotated = match ((int) (@exif_read_data($file)["Orientation"] ?? 1)) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated !== false ? $rotated : $image;
    }
}
