<?php

declare(strict_types=1);

namespace Blueprint;

use Illuminate\Support\Collection;

/**
 * Users are stored in content/users.json.
 */
class User
{
    public const SESSION_KEY = "blueprint.user";

    private static ?User $current = null;

    public function __construct(public readonly Data $data)
    {
    }

    public static function all(): Collection
    {
        $file = App::instance()->contentDir() . "/users.json";

        return (new Collection(ContentFile::readJson($file)))
            ->map(fn(array $values) => new self(new Data($values)));
    }

    /**
     * Finds a user by email (or username) or uid.
     */
    public static function find(string $emailOrUid): ?self
    {
        return self::all()->first(
            fn(self $user) => $user->email() === $emailOrUid || $user->uid() === $emailOrUid
        );
    }

    /**
     * The logged in (panel) user. A session is only started if the client already has one.
     */
    public static function current(): ?self
    {
        if (self::$current !== null) {
            return self::$current;
        }

        if (session_status() === PHP_SESSION_NONE && isset($_COOKIE[session_name()]) && !headers_sent()) {
            session_start();
        }

        $uid = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($uid) ? self::find($uid) : null;
    }

    public static function setCurrent(?self $user): void
    {
        self::$current = $user;
    }

    public function role(): string
    {
        return (string) ($this->data->role ?? "default");
    }

    /**
     * site/blueprints/users/{role}.yml, falls back to users/default.yml.
     */
    public function blueprint(): Blueprint
    {
        return Blueprint::load("users/" . $this->role(), "users/default");
    }

    public function email(): string
    {
        return (string) $this->data->email;
    }

    public function uid(): string
    {
        return (string) $this->data->uid;
    }
}
