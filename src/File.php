<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * A file in a page folder, e.g. content/01_blog/cover.jpg. Its data (meta) lives in cover.jpg.json,
 * the blueprint is chosen by its `template` (files/{template}.yml → files/default.yml).
 *
 * Files are served under the page url: /blog/cover.jpg
 */
class File
{
    use HasFields;

    /**
     * Never stored as files: `.md`/`.php` would become subpages, the others are executable or server config.
     */
    public const BLOCKED_EXTENSIONS = ["md", "php", "phtml", "phar", "php3", "php4", "php5", "php7", "php8", "pht", "htaccess", "htpasswd", "cgi", "exe"];

    public const TYPES = [
        "image" => ["jpg", "jpeg", "png", "gif", "webp", "avif", "svg", "bmp", "ico", "tif", "tiff", "heic"],
        "video" => ["mp4", "webm", "mov", "ogv", "m4v", "avi"],
        "audio" => ["mp3", "wav", "ogg", "m4a", "aac", "flac"],
        "document" => ["pdf", "doc", "docx", "odt", "rtf", "txt", "csv", "xls", "xlsx", "ods", "ppt", "pptx", "odp", "key", "pages", "numbers"],
        "archive" => ["zip", "tar", "gz", "rar", "7z"],
    ];

    /**
     * Served inline, everything else is a download (no html/svg rendering on the site's origin).
     */
    public const INLINE_MIMES = [
        "image/jpeg", "image/png", "image/gif", "image/webp", "image/avif", "image/bmp", "image/x-icon", "image/vnd.microsoft.icon",
        "video/mp4", "video/webm", "video/quicktime", "video/ogg", "audio/mpeg", "audio/wav", "audio/ogg", "audio/mp4", "audio/aac", "audio/flac",
        "application/pdf", "text/plain", "text/csv",
    ];

    private ?Data $data = null;

    public function __construct(public readonly string $root, public readonly Page $page)
    {
    }

    /**
     * All files in a page folder (without content, meta and hidden files), sorted by `sort` and filename.
     *
     * @return File[]
     */
    public static function scan(Page $page): array
    {
        if (!$page->isFolder()) {
            return [];
        }

        $files = [];

        foreach (scandir($page->root) as $name) {
            $path = "$page->root/$name";

            if (self::isFile($path)) {
                $files[] = new self($path, $page);
            }
        }

        usort($files, fn(self $a, self $b) => [$a->sort(), $a->filename()] <=> [$b->sort(), $b->filename()]);

        return $files;
    }

    private static function isFile(string $path): bool
    {
        $name = basename($path);

        return is_file($path)
            && !str_starts_with($name, ".")
            && !in_array($name, Page::CONTENT_FILES, true)
            && !in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::BLOCKED_EXTENSIONS, true)
            && !(str_ends_with($name, ".json") && is_file(substr($path, 0, -5)));
    }

    /**
     * Stores a new file (e.g. an upload) in the page folder.
     *
     * @param resource|string $contents
     */
    public static function create(Page $page, string $filename, mixed $contents, array $data = []): self
    {
        if (!$page->isFolder()) {
            throw new \LogicException("Files can only be added to folder pages");
        }

        $filename = self::sanitizeFilename($filename);
        $path = "$page->root/$filename";

        if (!self::isAllowedFilename($filename)) {
            throw new \InvalidArgumentException("Files of this type are not allowed");
        }

        if (file_exists($path) || (str_ends_with($filename, ".json") && file_exists(substr($path, 0, -5)))) {
            throw new \InvalidArgumentException("The file \"$filename\" already exists");
        }

        $target = fopen($path, "xb");
        is_resource($contents) ? stream_copy_to_stream($contents, $target) : fwrite($target, (string) $contents);
        fclose($target);

        // the meta data first, its template chooses the blueprint with the accept rules
        $file = new self($path, $page);
        $file->update($data);
        $error = $file->acceptError();

        if ($error !== null) {
            $file->delete();
            throw new \InvalidArgumentException($error);
        }

        return $file;
    }

    /**
     * "Mein Foto (1).JPG" → "mein-foto-1.jpg"
     */
    public static function sanitizeFilename(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $name = strtolower(\Illuminate\Support\Str::ascii(pathinfo($filename, PATHINFO_FILENAME)));
        $name = trim((string) preg_replace('/[^a-z0-9_-]+/', "-", $name), "-") ?: "file";

        return $extension === "" ? $name : "$name.$extension";
    }

    private static function isAllowedFilename(string $filename): bool
    {
        return $filename !== ""
            && !str_starts_with($filename, ".")
            && !in_array($filename, Page::CONTENT_FILES, true)
            && !in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::BLOCKED_EXTENSIONS, true);
    }

    public function filename(): string
    {
        return basename($this->root);
    }

    /**
     * The filename without extension.
     */
    public function name(): string
    {
        return pathinfo($this->root, PATHINFO_FILENAME);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->root, PATHINFO_EXTENSION));
    }

    /**
     * image, video, audio, document, archive or other
     */
    public function type(): string
    {
        foreach (self::TYPES as $type => $extensions) {
            if (in_array($this->extension(), $extensions, true)) {
                return $type;
            }
        }

        return "other";
    }

    public function isImage(): bool
    {
        return $this->type() === "image";
    }

    public function mime(): string
    {
        $mime = $this->extension() === "svg" ? "image/svg+xml" : (mime_content_type($this->root) ?: "");

        return $mime !== "" ? $mime : "application/octet-stream";
    }

    public function size(): int
    {
        return (int) filesize($this->root);
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public function dimensions(): ?array
    {
        if (!$this->isImage() || $this->extension() === "svg") {
            return null;
        }

        $size = @getimagesize($this->root);

        return $size === false ? null : ["width" => $size[0], "height" => $size[1]];
    }

    /**
     * "blog/cover.jpg"
     */
    public function id(): string
    {
        return $this->page->id() . "/" . $this->filename();
    }

    public function url(): string
    {
        return "/" . $this->page->id() . "/" . rawurlencode($this->filename());
    }

    public function metaFile(): string
    {
        return "$this->root.json";
    }

    public function data(): Data
    {
        return $this->data ??= new Data(ContentFile::readJson($this->metaFile()));
    }

    public function uid(): ?string
    {
        $uid = $this->data()->uid;

        return is_string($uid) && $uid !== "" ? $uid : null;
    }

    public function template(): string
    {
        return (string) ($this->data()->template ?? "default");
    }

    public function sort(): int
    {
        return (int) ($this->data()->sort ?? PHP_INT_MAX);
    }

    public function blueprint(): Blueprint
    {
        $template = $this->template();

        return Blueprint::load("files/$template", "files/default");
    }

    protected function fieldDefinition(string $name): ?Field
    {
        return $this->blueprint()->field($name);
    }

    /**
     * The error if the file doesn't match the blueprint's `accept` rules, e.g. `accept: image/*` or
     * `accept: {mime: [image/jpeg, image/png], extension: [jpg, png], maxsize: 2000000}`.
     */
    public function acceptError(): ?string
    {
        $accept = $this->blueprint()->get("accept");
        $accept = is_string($accept) ? ["mime" => $accept] : (is_array($accept) ? $accept : []);
        $mimes = self::list($accept["mime"] ?? null);
        $extensions = self::list($accept["extension"] ?? null);

        if ($mimes !== [] && !self::matchesMime($this->mime(), $mimes)) {
            return "The file type " . $this->mime() . " is not accepted (" . implode(", ", $mimes) . ")";
        }

        if ($extensions !== [] && !in_array($this->extension(), array_map("strtolower", $extensions), true)) {
            return "The extension ." . $this->extension() . " is not accepted (" . implode(", ", $extensions) . ")";
        }

        if (isset($accept["maxsize"]) && $this->size() > (int) $accept["maxsize"]) {
            return "The file is too large (max. " . (int) $accept["maxsize"] . " bytes)";
        }

        return null;
    }

    /**
     * `image/*`, `image/png` and `.png`-style lists, separated by commas.
     */
    public static function matchesMime(string $mime, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim($pattern));

            if ($pattern === "*" || $pattern === "*/*" || $pattern === $mime || (str_ends_with($pattern, "/*") && str_starts_with($mime, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : ($value === null ? [] : array_map("trim", explode(",", (string) $value)));
    }

    /**
     * Saves the meta data, `null` values are removed.
     */
    public function update(array $values): void
    {
        $data = array_filter([...$this->data()->toArray(), ...$values], fn($value) => $value !== null);
        $data["uid"] ??= Uid::generate();
        ContentFile::write($this->metaFile(), $data);
        $this->data = null;
    }

    /**
     * Renames the file (the extension stays).
     */
    public function changeName(string $name): self
    {
        $filename = self::sanitizeFilename($name . "." . $this->extension());
        $target = dirname($this->root) . "/$filename";

        if ($target === $this->root) {
            return $this;
        }

        if (file_exists($target)) {
            throw new \InvalidArgumentException("The file \"$filename\" already exists");
        }

        rename($this->root, $target);

        if (is_file($this->metaFile())) {
            rename($this->metaFile(), "$target.json");
        }

        return new self($target, $this->page);
    }

    public function delete(): void
    {
        unlink($this->root);

        if (is_file($this->metaFile())) {
            unlink($this->metaFile());
        }
    }

    /**
     * Only used files are public: their filename appears in the data of their page, or they are
     * referenced from any page or the site (`file://uid` or `blog/cover.jpg`).
     */
    public function isUsed(): bool
    {
        if (preg_match(self::referencePattern($this->filename()), self::dataString($this->page->data()))) {
            return true;
        }

        $patterns = [self::referencePattern($this->id())];

        if ($this->uid() !== null) {
            $patterns[] = self::referencePattern("file://" . $this->uid());
        }

        foreach ([site()->data(), ...site()->index()->map(fn(Page $page) => $page->data())] as $data) {
            $string = self::dataString($data);

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $string)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function referencePattern(string $reference): string
    {
        return '#(?<![\w.\-/])' . preg_quote($reference, "#") . '(?![\w.\-/])#';
    }

    private static function dataString(Data $data): string
    {
        return (string) json_encode($data->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Replaces relative file links in html (e.g. from markdown) with the file urls:
     * `hello.jpg` (a file of the page) → /blog/hello.jpg, `blog/hello.jpg` → /blog/hello.jpg.
     */
    public static function resolveUrls(string $html, ?Page $page): string
    {
        return (string) preg_replace_callback('/\b(src|href)="([^"]+)"/', function (array $matches) use ($page) {
            $reference = rawurldecode(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5));

            if (preg_match('#^([a-z][a-z0-9+.\-]*:|/|\#|\?|\.)#i', $reference)) {
                return $matches[0];
            }

            $file = str_contains($reference, "/")
                ? site()->find(dirname($reference), includeDrafts: true)?->file(basename($reference))
                : $page?->file($reference);

            return $file === null ? $matches[0] : $matches[1] . '="' . esc($file->url()) . '"';
        }, $html);
    }

    public function toArray(): array
    {
        return [
            "filename" => $this->filename(),
            "id" => $this->id(),
            "uid" => $this->uid(),
            "url" => $this->url(),
            "type" => $this->type(),
            "mime" => $this->mime(),
            "extension" => $this->extension(),
            "size" => $this->size(),
            "dimensions" => $this->dimensions(),
            "template" => $this->template(),
            "isUsed" => $this->isUsed(),
        ];
    }
}
