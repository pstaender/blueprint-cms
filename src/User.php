<?php

declare(strict_types=1);

namespace Blueprint;

use Illuminate\Support\Collection;

/**
 * Users are stored in content/users.json.
 */
class User
{
    use HasFields;

    public const SESSION_KEY = "blueprint.user";

    private static ?User $current = null;

    public function __construct(public readonly Data $data)
    {
    }

    public static function all(): Collection
    {
        return (new Collection(ContentFile::readJson(self::file())))
            ->map(fn(array $values) => new self(new Data($values)));
    }

    private static function file(): string
    {
        return App::instance()->contentDir() . "/users.json";
    }

    public static function create(string $email, string $password, string $role = "user", array $data = []): self
    {
        if (trim($email) === "" || self::find($email) !== null) {
            throw new \InvalidArgumentException("The email is empty or already taken");
        }

        $user = new self(new Data([
            ...$data,
            "email" => $email,
            "uid" => bin2hex(random_bytes(5)),
            "role" => $role,
            "password" => self::hashPassword($password),
        ]));

        self::saveAll([...self::all()->all(), $user]);

        return $user;
    }

    private static function hashPassword(string $password): string
    {
        if (mb_strlen($password) < 8) {
            throw new \InvalidArgumentException("The password needs at least 8 characters");
        }

        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * @param self[] $users
     */
    private static function saveAll(array $users): void
    {
        ContentFile::write(self::file(), array_map(fn(self $user) => $user->data->toArray(), array_values($users)));
    }

    public function verifyPassword(string $password): bool
    {
        $hash = $this->data->password;

        return is_string($hash) && password_get_info($hash)["algo"] !== null && password_verify($password, $hash);
    }

    public function changePassword(string $password): self
    {
        return $this->update(["password" => self::hashPassword($password)]);
    }

    /**
     * Saves the values to users.json, `null` values are removed. The uid can't be changed.
     */
    public function update(array $values): self
    {
        $data = array_filter([...$this->data->toArray(), ...$values, "uid" => $this->uid()], fn($value) => $value !== null);
        $email = (string) ($data["email"] ?? "");

        if ($email === "" || (($other = self::find($email)) !== null && $other->uid() !== $this->uid())) {
            throw new \InvalidArgumentException("The email is empty or already taken");
        }

        $updated = new self(new Data($data));
        self::saveAll(self::all()->map(fn(self $user) => $user->uid() === $this->uid() ? $updated : $user)->all());

        return $updated;
    }

    public function delete(): void
    {
        self::saveAll(self::all()->reject(fn(self $user) => $user->uid() === $this->uid())->all());
    }

    /**
     * The data without the password hash.
     */
    public function toArray(): array
    {
        $data = $this->data->toArray();
        unset($data["password"]);

        return $data;
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

        Session::resume();
        $uid = Session::get(self::SESSION_KEY);

        return is_string($uid) ? self::find($uid) : null;
    }

    public static function setCurrent(?self $user): void
    {
        self::$current = $user;
    }

    public function data(): Data
    {
        return $this->data;
    }

    protected function fieldDefinition(string $name): ?Field
    {
        return $this->blueprint()->field($name);
    }

    public function role(): string
    {
        return (string) ($this->data->role ?? "user");
    }

    /**
     * site/blueprints/users/{role}.yml, falls back to users/user.yml and then users/default.yml.
     */
    public function blueprint(): Blueprint
    {
        return Blueprint::load("users/" . $this->role(), "users/user", "users/default");
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
