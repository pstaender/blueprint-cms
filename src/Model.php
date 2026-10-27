<?php

declare(strict_types=1);

namespace Blueprint;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A record of a collection: models are classes in `site/models/` (or a plugin's `models/`) that extend this class,
 * e.g. `class PageRating extends Blueprint\Model` in `site/models/PageRating.php`. The model name is the class
 * name in camelCase (`pageRating`), its blueprint `site/blueprints/collections/pageRating.yml`.
 *
 * The records are stored as a list in a json file in the content folder, by default the plural of the model name
 * (`content/pageRatings.json`, change it with the `COLLECTION` constant). Every record has a `uid`, `createdAt` and
 * `modifiedAt` and is referenced as `pageRating://{uid}`. Writes lock the file, so concurrent requests don't
 * overwrite each other's records.
 *
 * With `softDelete: true` in the blueprint, deleted records are moved to `content/.trash/{collection}/{uid}.json`,
 * their files to `content/.trash/{collection}/files/`.
 * Files of records are stored in `content/.database_files/{collection}/{uid}_{filename}`.
 */
abstract class Model
{
    use HasFields;

    /** The name of the collection file (without `.json`), null = the plural of the model name */
    public const COLLECTION = null;

    public const TRASH_FOLDER = ".trash";
    public const FILES_FOLDER = ".database_files";

    public function __construct(public readonly Data $data, public readonly bool $isTrashed = false)
    {
    }

    /**
     * The class name in camelCase, e.g. "pageRating".
     */
    public static function name(): string
    {
        return lcfirst((new \ReflectionClass(static::class))->getShortName());
    }

    /**
     * The collection name, e.g. "pageRatings".
     */
    public static function collection(): string
    {
        $collection = static::COLLECTION ?? Str::plural(static::name());

        if (!preg_match('/^[\w-]+$/', $collection)) {
            throw new \LogicException("Invalid collection name \"$collection\" of " . static::class);
        }

        return $collection;
    }

    public static function collectionFile(): string
    {
        return App::instance()->contentDir() . "/" . static::collection() . ".json";
    }

    /**
     * site/blueprints/collections/{name}.yml, without one the records have no fields in the panel.
     */
    public static function blueprint(): Blueprint
    {
        $name = "collections/" . static::name();

        return Blueprint::file($name) !== null ? Blueprint::load($name) : new Blueprint($name, []);
    }

    public static function all(): Collection
    {
        return (new Collection(self::read()))->map(fn(array $data) => new static(new Data($data)));
    }

    /**
     * A record by uid or reference (`pageRating://uid`), also from the trash.
     */
    public static function find(string $uidOrReference): ?static
    {
        $uid = preg_replace('#^' . preg_quote(static::name(), "#") . '://#', "", $uidOrReference);

        return static::all()->first(fn(self $record) => $record->uid() === $uid)
            ?? static::trashed()->first(fn(self $record) => $record->uid() === $uid);
    }

    /**
     * The records whose field has the value, e.g. PageRating::where("page", "page://…").
     */
    public static function where(string $field, mixed $value): Collection
    {
        return static::all()->filter(fn(self $record) => ($record->data->toArray()[$field] ?? null) === $value)->values();
    }

    /**
     * Saves a new record with the blueprint's default values and the system fields.
     */
    public static function create(array $data = []): static
    {
        $record = new static(new Data(SystemFields::apply([
            ...static::blueprint()->defaults(),
            ...array_diff_key($data, array_flip(SystemFields::NAMES)),
        ])));
        self::change(fn(array $records) => [...$records, $record->data->toArray()]);

        return $record;
    }

    /**
     * Saves the values, `null` values are removed. The uid and createdAt can't be changed.
     */
    public function update(array $values): static
    {
        $this->requireActive();
        $existing = $this->data->toArray();
        $data = SystemFields::apply(array_filter([...$existing, ...$values], fn($value) => $value !== null), $existing);

        self::change(function (array $records) use ($data) {
            $index = $this->indexIn($records) ?? throw new \RuntimeException("The record " . $this->ref() . " doesn't exist anymore");
            $records[$index] = $data;

            return $records;
        });

        return new static(new Data($data));
    }

    /**
     * Moves the record and its files to the trash with `softDelete: true` in the blueprint, otherwise (or if it's
     * already in the trash) it's deleted with its files.
     */
    public function delete(): void
    {
        if (!$this->isTrashed && static::blueprint()->get("softDelete") === true) {
            ContentFile::write($this->trashFile(), $this->data->toArray());
            $this->moveFiles(self::trashFilesDir());
            $this->removeFromCollection();

            return;
        }

        if ($this->isTrashed) {
            @unlink($this->trashFile());
        } else {
            $this->removeFromCollection();
        }

        foreach ($this->files() as $file) {
            @unlink($file);
        }
    }

    /**
     * The records in the trash, newest first.
     */
    public static function trashed(): Collection
    {
        $dir = App::instance()->contentDir() . "/" . self::TRASH_FOLDER . "/" . static::collection();

        return (new Collection(glob("$dir/*.json") ?: []))
            ->map(fn(string $file) => new static(new Data(ContentFile::readJson($file)), isTrashed: true))
            ->sortByDesc(fn(self $record) => (string) $record->data->modifiedAt)
            ->values();
    }

    /**
     * Deletes all records in the trash with their files.
     *
     * @return int the number of deleted records
     */
    public static function emptyTrash(): int
    {
        $trashed = static::trashed();
        $trashed->each(fn(self $record) => $record->delete());

        return $trashed->count();
    }

    public function restore(): static
    {
        if (!$this->isTrashed) {
            return $this;
        }

        self::change(fn(array $records) => $this->indexIn($records) === null ? [...$records, $this->data->toArray()] : $records);
        $this->moveFiles(self::filesDir());
        @unlink($this->trashFile());

        return new static($this->data);
    }

    /**
     * The files of the record: filename (without the uid prefix) → absolute path.
     *
     * @return array<string, string>
     */
    public function files(): array
    {
        $prefix = $this->uid() . "_";
        $files = [];

        $dir = $this->isTrashed ? self::trashFilesDir() : self::filesDir();

        foreach (glob("$dir/$prefix*") ?: [] as $path) {
            $files[substr(basename($path), strlen($prefix))] = $path;
        }

        return $files;
    }

    public function file(string $filename): ?string
    {
        return $this->files()[$filename] ?? null;
    }

    /**
     * Stores a file with a sanitized filename (see File::sanitizeFilename()), an existing file is replaced.
     *
     * @param resource|string $contents
     * @return string the filename
     */
    public function addFile(string $filename, mixed $contents): string
    {
        $this->requireActive();
        $filename = File::sanitizeFilename($filename);

        if (in_array(pathinfo($filename, PATHINFO_EXTENSION), File::BLOCKED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException("Files of this type are not allowed");
        }

        if (!is_dir(self::filesDir())) {
            mkdir(self::filesDir(), 0775, true);
        }

        $target = fopen(self::filesDir() . "/" . $this->uid() . "_$filename", "wb");
        is_resource($contents) ? stream_copy_to_stream($contents, $target) : fwrite($target, (string) $contents);
        fclose($target);

        return $filename;
    }

    public function deleteFile(string $filename): void
    {
        if ($path = $this->file($filename)) {
            unlink($path);
        }
    }

    private static function filesDir(): string
    {
        return App::instance()->contentDir() . "/" . self::FILES_FOLDER . "/" . static::collection();
    }

    /**
     * Files of trashed records: content/.trash/{collection}/files/{uid}_{filename}
     */
    private static function trashFilesDir(): string
    {
        return App::instance()->contentDir() . "/" . self::TRASH_FOLDER . "/" . static::collection() . "/files";
    }

    private function moveFiles(string $dir): void
    {
        foreach ($this->files() as $filename => $path) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            rename($path, "$dir/" . $this->uid() . "_$filename");
        }
    }

    /**
     * The title in the panel: the `title` or `name` field, the first searchable field with a value or the uid.
     * Models can override it.
     */
    public function panelTitle(): string
    {
        $data = $this->data->toArray();

        foreach (["title", "name", ...(array) static::blueprint()->get("searchableFields", [])] as $field) {
            if (is_scalar($data[$field] ?? null) && (string) $data[$field] !== "") {
                return (string) $data[$field];
            }
        }

        return $this->uid();
    }

    public function uid(): string
    {
        return (string) $this->data->uid;
    }

    /**
     * The reference, e.g. "pageRating://19qiajkmcaw3x8bp".
     */
    public function ref(): string
    {
        return static::name() . "://" . $this->uid();
    }

    public function data(): Data
    {
        return $this->data;
    }

    public function toArray(): array
    {
        return $this->data->toArray();
    }

    protected function fieldDefinition(string $name): ?Field
    {
        return static::blueprint()->field($name);
    }

    private function trashFile(): string
    {
        return App::instance()->contentDir() . "/" . self::TRASH_FOLDER . "/" . static::collection() . "/" . $this->uid() . ".json";
    }

    private function requireActive(): void
    {
        if ($this->isTrashed) {
            throw new \LogicException("The record " . $this->ref() . " is in the trash, restore it first");
        }
    }

    private function removeFromCollection(): void
    {
        self::change(fn(array $records) => array_values(array_filter($records, fn(array $data) => ($data["uid"] ?? null) !== $this->uid())));
    }

    private function indexIn(array $records): ?int
    {
        foreach ($records as $index => $data) {
            if (($data["uid"] ?? null) === $this->uid()) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array[] the data of all records
     */
    private static function read(): array
    {
        $handle = @fopen(static::collectionFile(), "r");

        if ($handle === false) {
            return [];
        }

        try {
            flock($handle, LOCK_SH);

            return self::decode(stream_get_contents($handle));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Reads, changes and writes the collection while it's locked.
     *
     * @param callable(array[]): array[] $change
     */
    private static function change(callable $change): void
    {
        $file = static::collectionFile();

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        $handle = fopen($file, "c+") ?: throw new \RuntimeException("Can't write $file");

        try {
            flock($handle, LOCK_EX);
            $records = $change(self::decode(stream_get_contents($handle)));
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, ContentFile::toJson(array_values($records)));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function decode(string|false $json): array
    {
        return $json === false || trim($json) === "" ? [] : (array) json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
