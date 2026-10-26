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

    public const DEFAULT_ROLE = "user";
    public const ADMIN_ROLE = "admin";

    /**
     * The available roles: `user` and every user blueprint (site/blueprints/users/{role}.yml) except `default`.
     *
     * @return string[]
     */
    public static function roles(): array
    {
        $roles = array_map("basename", Blueprint::names("users"));
        $roles = array_diff($roles, ["default", self::DEFAULT_ROLE]);
        sort($roles);

        return [self::DEFAULT_ROLE, ...$roles];
    }

    private static function validateRole(string $role): void
    {
        if (!in_array($role, self::roles(), true)) {
            throw new \InvalidArgumentException("The role \"$role\" doesn't exist (" . implode(", ", self::roles()) . ")");
        }
    }

    /**
     * The first user becomes an admin by default. The only user is an admin anyway (see role()), so it keeps
     * the admin role when a second user is created.
     */
    public static function create(string $email, string $password, ?string $role = null, array $data = []): self
    {
        if (trim($email) === "" || self::find($email) !== null) {
            throw new \InvalidArgumentException("The email is empty or already taken");
        }

        $users = self::all();
        $role ??= $users->isEmpty() ? self::ADMIN_ROLE : self::DEFAULT_ROLE;
        self::validateRole($role);

        if ($users->count() === 1 && $users->first()->data->role !== self::ADMIN_ROLE) {
            $users = $users->map(fn(self $user) => new self(new Data([...$user->data->toArray(), "role" => self::ADMIN_ROLE])));
        }

        $user = new self(new Data(SystemFields::apply([
            ...array_diff_key($data, array_flip(SystemFields::NAMES)),
            "email" => $email,
            "role" => $role,
            "password" => self::hashPassword($password),
        ])));

        self::saveAll([...$users->all(), $user]);

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

    /**
     * Users without uid (written by hand) are identified by their email.
     */
    public function isSame(self $user): bool
    {
        return $this->uid() !== "" ? $user->uid() === $this->uid() : $user->email() === $this->email();
    }

    public function verifyPassword(string $password): bool
    {
        $hash = $this->data->password;

        return is_string($hash) && password_get_info($hash)["algo"] !== null && password_verify($password, $hash);
    }

    public function isAdmin(): bool
    {
        return $this->role() === self::ADMIN_ROLE;
    }

    public function changePassword(string $password): self
    {
        return $this->update(["password" => self::hashPassword($password)]);
    }

    /**
     * Saves the values to users.json, `null` values are removed. The uid and createdAt can't be changed.
     */
    public function update(array $values): self
    {
        // the current role stays valid, even if its blueprint was removed
        if (isset($values["role"]) && $values["role"] !== $this->role()) {
            self::validateRole((string) $values["role"]);
        }

        $existing = $this->data->toArray();
        $data = SystemFields::apply(array_filter([...$existing, ...$values], fn($value) => $value !== null), $existing);
        $email = (string) ($data["email"] ?? "");

        if ($email === "" || (($other = self::find($email)) !== null && $other->uid() !== $this->uid())) {
            throw new \InvalidArgumentException("The email is empty or already taken");
        }

        $updated = new self(new Data($data));
        self::saveAll(self::all()->map(fn(self $user) => $this->isSame($user) ? $updated : $user)->all());

        return $updated;
    }

    public function delete(): void
    {
        self::saveAll(self::all()->reject(fn(self $user) => $this->isSame($user))->all());
    }

    /**
     * The data without the password hash, with the effective role.
     */
    public function toArray(): array
    {
        $data = $this->data->toArray();
        unset($data["password"]);

        return [...$data, "role" => $this->role()];
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

    /**
     * The only user is always an admin, so there is someone who can manage roles.
     */
    public function role(): string
    {
        if (self::all()->count() === 1 && $this->isSame(self::all()->first())) {
            return self::ADMIN_ROLE;
        }

        return (string) ($this->data->role ?? self::DEFAULT_ROLE);
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
