<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * Converts content images to AVIF and caches them in public/media/images/{hash}/{name}.avif
 * (the original filename, e.g. `/media/images/7abed…/cover.avif` for cover.jpg).
 * Based on the ImageOptimizer of minimaxcms.
 *
 * The hash covers the image's SHA-256, `maxFileSize` and `quality`, so a changed image or other
 * settings get a new file (and url). Without Imagick or GD (with AVIF support) the image's own url
 * is used. Only public (used) files are converted, see File::isUsed().
 */
class ImageOptimizer
{
    public const URL_PATH = "/media/images";

    /**
     * Sources that are converted, anything else (svg, …) is used as is.
     */
    public const CONVERTIBLE = ["avif", "bmp", "gif", "jpeg", "jpg", "png", "webp"];

    /**
     * Target size (KB) in auto mode, also when WebP/AVIF sources are small enough to keep.
     */
    private const AUTO_MAX_FILE_SIZE = 100;

    /**
     * Sizes are rough targets: allow this much above `maxFileSize`.
     */
    private const SIZE_TOLERANCE = 1.1;

    /**
     * Lowest quality used to reach `maxFileSize`, below that the image is scaled down instead.
     */
    private const MIN_QUALITY = 30;

    private const QUALITY_STEP = 8;

    /**
     * The longer side is never scaled below this (px) to reach `maxFileSize`.
     */
    private const MIN_DIMENSION = 640;

    public function __construct(private readonly string $publicDir)
    {
    }

    /**
     * The url of the optimized AVIF of an image file, or the file's own url if it can't or needn't be converted.
     *
     * @param int|null $maxFileSize roughly, in KB (null: auto)
     * @param int|null $quality 0–100 (null: auto, depending on the resolution)
     */
    public function url(File $file, ?int $maxFileSize = null, ?int $quality = null): string
    {
        $source = $file->root;
        $extension = $file->extension();
        $auto = $maxFileSize === null && $quality === null;

        if (!in_array($extension, self::CONVERTIBLE, true) || !$file->isUsed()) {
            return $file->url();
        }

        if ($auto && in_array($extension, ["avif", "webp"], true) && $file->size() <= self::AUTO_MAX_FILE_SIZE * 1024 * self::SIZE_TOLERANCE) {
            return $file->url();
        }

        $quality = $quality === null ? null : max(0, min(100, $quality));
        $maxFileSize = $maxFileSize === null ? null : max(1, $maxFileSize);

        $hash = hash("sha256", hash_file("sha256", $source) . "|$maxFileSize|$quality");
        $name = $file->name() . ".avif";
        $url = self::URL_PATH . "/$hash/" . rawurlencode($name);
        $target = $this->publicDir . self::URL_PATH . "/$hash/$name";

        if (is_file($target)) {
            return $url;
        }

        $avif = $this->convert($source, $auto ? self::AUTO_MAX_FILE_SIZE : $maxFileSize, $quality);

        return $avif !== null && $this->write($target, $avif) ? $url : $file->url();
    }

    /**
     * Points the `src` of every local `<img>` in the html (e.g. `/blog/hello.png`) to the url that
     * `$optimize` returns for it. External images and those whose url doesn't change stay as they are.
     *
     * @param callable(string): string $optimize gets the url path
     */
    public static function replaceImages(string $html, callable $optimize): string
    {
        return (string) preg_replace_callback(
            '~(<img\b[^>]*?\ssrc\s*=\s*)(["\'])(.*?)\2~is',
            static function (array $match) use ($optimize): string {
                $src = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5);

                if (!str_starts_with($src, "/") || str_starts_with($src, "//")) {
                    return $match[0];
                }

                $path = rawurldecode((string) parse_url($src, PHP_URL_PATH));
                $url = $optimize($path);

                return $url === $path ? $match[0] : $match[1] . $match[2] . esc($url) . $match[2];
            },
            $html,
        );
    }

    public static function isSupported(): bool
    {
        return self::imagickSupported() || self::gdSupported();
    }

    /**
     * Encodes the image as AVIF: lowers the quality (down to MIN_QUALITY) and then the
     * resolution until it fits `$maxFileSize` (KB).
     */
    private function convert(string $source, ?int $maxFileSize, ?int $quality): ?string
    {
        try {
            $image = match (true) {
                self::imagickSupported() => new ImagickImage($source),
                self::gdSupported() => GdImageFile::open($source),
                default => null,
            };

            if ($image === null) {
                return null;
            }

            $quality ??= self::autoQuality($image->width() * $image->height());
            $limit = $maxFileSize !== null ? (int) ($maxFileSize * 1024 * self::SIZE_TOLERANCE) : null;

            while (true) {
                $avif = $image->avif($quality);

                if ($limit === null || strlen($avif) <= $limit) {
                    return $avif;
                }

                if ($quality > self::MIN_QUALITY) {
                    $quality = max(self::MIN_QUALITY, $quality - self::QUALITY_STEP);
                } elseif (max($image->width(), $image->height()) * 0.8 >= self::MIN_DIMENSION) {
                    $image->scale(0.8);
                } else {
                    return $avif;
                }
            }
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Small images keep more quality, large ones (where artifacts are less visible per pixel)
     * are compressed more: 75 at ≤ 0.25 MP down to 50 at ≥ 8 MP (log scale).
     */
    private static function autoQuality(int $pixels): int
    {
        [$small, $large] = [250_000, 8_000_000];
        $position = (log(max($small, min($large, $pixels))) - log($small)) / (log($large) - log($small));

        return (int) round(75 - 25 * $position);
    }

    private function write(string $target, string $content): bool
    {
        $dir = dirname($target);

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        // a temporary file first, so a concurrent request never serves a partial file
        $temporary = "$target." . bin2hex(random_bytes(4)) . ".tmp";

        if (@file_put_contents($temporary, $content) === false) {
            return false;
        }

        if (!@rename($temporary, $target)) {
            @unlink($temporary);

            return false;
        }

        return true;
    }

    private static function imagickSupported(): bool
    {
        return extension_loaded("imagick") && \Imagick::queryFormats("AVIF") !== [];
    }

    private static function gdSupported(): bool
    {
        return function_exists("imageavif") && (gd_info()["AVIF Support"] ?? false);
    }
}
