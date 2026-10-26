<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * Passkeys (WebAuthn) for the panel login, stored in the user's `passkeys` in users.json:
 * `[{id, name, publicKey, algorithm, signCount, createdAt, lastUsedAt}]` (binary values base64url encoded).
 * The browser sends the public key of a new passkey (SPKI, `getPublicKey()`), no attestation is requested,
 * so no CBOR has to be parsed. Passkeys need user verification (pin, biometrics), they replace email + password.
 */
class Passkey
{
    public const CHALLENGE_KEY = "blueprint.passkey.challenge";
    private const CHALLENGE_LIFETIME = 300;
    // COSE algorithm ids: ES256, RS256 (both SHA-256)
    public const ALGORITHMS = [-7, -257];
    private const FLAG_USER_PRESENT = 0x01;
    private const FLAG_USER_VERIFIED = 0x04;

    /**
     * Options for `navigator.credentials.create()` (binary values base64url encoded).
     */
    public static function creationOptions(User $user, string $host): array
    {
        // the uid is the passkey's user handle
        $user = $user->uid() === "" ? $user->update([]) : $user;

        return [
            "challenge" => self::newChallenge(),
            "rp" => ["id" => $host, "name" => (string) (site()->data()->name ?? $host)],
            "user" => ["id" => self::encode($user->uid()), "name" => $user->email(), "displayName" => (string) ($user->data->name ?? $user->email())],
            "pubKeyCredParams" => array_map(fn(int $alg) => ["type" => "public-key", "alg" => $alg], self::ALGORITHMS),
            "excludeCredentials" => array_map(fn(array $passkey) => ["type" => "public-key", "id" => $passkey["id"]], self::of($user)),
            "authenticatorSelection" => ["residentKey" => "required", "userVerification" => "required"],
            "attestation" => "none",
            "timeout" => self::CHALLENGE_LIFETIME * 1000,
        ];
    }

    /**
     * Options for `navigator.credentials.get()`, without allowed credentials: the browser offers all passkeys of the site.
     */
    public static function requestOptions(string $host): array
    {
        return [
            "challenge" => self::newChallenge(),
            "rpId" => $host,
            "userVerification" => "required",
            "timeout" => self::CHALLENGE_LIFETIME * 1000,
        ];
    }

    /**
     * Adds a new passkey to the user. Input: {id, publicKey, algorithm, clientDataJSON, authenticatorData}.
     */
    public static function register(User $user, array $input, string $host, string $name = ""): User
    {
        self::verifyClientData($input, "webauthn.create", $host);
        $authData = self::decode((string) ($input["authenticatorData"] ?? ""));
        $signCount = self::verifyAuthData($authData, $host);
        $id = (string) ($input["id"] ?? "");
        $algorithm = (int) ($input["algorithm"] ?? 0);
        $publicKey = (string) ($input["publicKey"] ?? "");

        if (!in_array($algorithm, self::ALGORITHMS, true) || openssl_pkey_get_public(self::pem(self::decode($publicKey))) === false) {
            throw new ApiError("Unsupported passkey", 400);
        }

        if ($id === "" || self::find($id) !== null) {
            throw new ApiError("The passkey is already registered", 400);
        }

        return $user->update(["passkeys" => [...self::of($user), [
            "id" => $id,
            "name" => mb_substr(trim($name), 0, 64) ?: "Passkey",
            "publicKey" => $publicKey,
            "algorithm" => $algorithm,
            "signCount" => $signCount,
            "createdAt" => SystemFields::now(),
            "lastUsedAt" => null,
        ]]]);
    }

    /**
     * The user of a passkey login. Input: {id, clientDataJSON, authenticatorData, signature, userHandle}.
     */
    public static function authenticate(array $input, string $host): User
    {
        self::verifyClientData($input, "webauthn.get", $host);
        [$user, $passkey] = self::find((string) ($input["id"] ?? "")) ?? throw new ApiError("Unknown passkey", 401);

        if (($input["userHandle"] ?? null) !== null && self::decode((string) $input["userHandle"]) !== $user->uid()) {
            throw new ApiError("Unknown passkey", 401);
        }

        $authData = self::decode((string) ($input["authenticatorData"] ?? ""));
        $signCount = self::verifyAuthData($authData, $host);
        $signed = $authData . hash("sha256", self::decode((string) ($input["clientDataJSON"] ?? "")), true);
        $signature = self::decode((string) ($input["signature"] ?? ""));

        if (openssl_verify($signed, $signature, self::pem(self::decode($passkey["publicKey"])), OPENSSL_ALGO_SHA256) !== 1) {
            throw new ApiError("Invalid passkey signature", 401);
        }

        // a counter that doesn't increase hints at a cloned authenticator (most passkeys always send 0)
        if (($signCount !== 0 || $passkey["signCount"] !== 0) && $signCount <= $passkey["signCount"]) {
            throw new ApiError("Invalid passkey counter", 401);
        }

        return self::replace($user, $passkey["id"], [...$passkey, "signCount" => $signCount, "lastUsedAt" => SystemFields::now()]);
    }

    public static function remove(User $user, string $id): User
    {
        $passkeys = array_values(array_filter(self::of($user), fn(array $passkey) => $passkey["id"] !== $id));

        if (count($passkeys) === count(self::of($user))) {
            throw new ApiError("Passkey not found", 404);
        }

        return $user->update(["passkeys" => $passkeys ?: null]);
    }

    /**
     * The passkeys of a user as shown in the panel (without keys).
     */
    public static function summaries(User $user): array
    {
        return array_map(fn(array $passkey) => [
            "id" => $passkey["id"],
            "name" => $passkey["name"],
            "createdAt" => $passkey["createdAt"],
            "lastUsedAt" => $passkey["lastUsedAt"],
        ], self::of($user));
    }

    /**
     * @return array<int, array{id: string, name: string, publicKey: string, algorithm: int, signCount: int, createdAt: ?string, lastUsedAt: ?string}>
     */
    private static function of(User $user): array
    {
        $passkeys = $user->data->toArray()["passkeys"] ?? [];

        return array_values(array_map(fn(array $passkey) => [
            "id" => (string) ($passkey["id"] ?? ""),
            "name" => (string) ($passkey["name"] ?? "Passkey"),
            "publicKey" => (string) ($passkey["publicKey"] ?? ""),
            "algorithm" => (int) ($passkey["algorithm"] ?? 0),
            "signCount" => (int) ($passkey["signCount"] ?? 0),
            "createdAt" => $passkey["createdAt"] ?? null,
            "lastUsedAt" => $passkey["lastUsedAt"] ?? null,
        ], array_filter(is_array($passkeys) ? $passkeys : [], "is_array")));
    }

    /**
     * @return array{0: User, 1: array}|null
     */
    private static function find(string $id): ?array
    {
        foreach (User::all() as $user) {
            foreach (self::of($user) as $passkey) {
                if ($id !== "" && hash_equals($passkey["id"], $id)) {
                    return [$user, $passkey];
                }
            }
        }

        return null;
    }

    private static function replace(User $user, string $id, array $replacement): User
    {
        return $user->update(["passkeys" => array_map(fn(array $passkey) => $passkey["id"] === $id ? $replacement : $passkey, self::of($user))]);
    }

    private static function newChallenge(): string
    {
        Session::start();
        $challenge = self::encode(random_bytes(32));
        $_SESSION[self::CHALLENGE_KEY] = ["value" => $challenge, "time" => time()];

        return $challenge;
    }

    /**
     * The challenge can only be used once. The origin must be the panel's host (https, except on localhost).
     */
    private static function verifyClientData(array $input, string $type, string $host): void
    {
        Session::resume();
        $challenge = Session::get(self::CHALLENGE_KEY);
        unset($_SESSION[self::CHALLENGE_KEY]);
        $clientData = json_decode(self::decode((string) ($input["clientDataJSON"] ?? "")), true);
        $origin = parse_url((string) ($clientData["origin"] ?? ""));
        $isLocal = $host === "localhost" || str_ends_with($host, ".localhost");

        $isValid = is_array($challenge) && is_array($clientData)
            && time() - (int) $challenge["time"] <= self::CHALLENGE_LIFETIME
            && ($clientData["type"] ?? null) === $type
            && hash_equals((string) $challenge["value"], (string) ($clientData["challenge"] ?? ""))
            && ($origin["host"] ?? null) === $host
            && (($origin["scheme"] ?? null) === "https" || ($isLocal && ($origin["scheme"] ?? null) === "http"));

        if (!$isValid) {
            throw new ApiError("Invalid or expired passkey request, please try again", 400);
        }
    }

    /**
     * Checks the relying party and the flags of the authenticator data, returns its signature counter.
     */
    private static function verifyAuthData(string $authData, string $host): int
    {
        if (strlen($authData) < 37 || !hash_equals(hash("sha256", $host, true), substr($authData, 0, 32))) {
            throw new ApiError("The passkey belongs to another site", 400);
        }

        $flags = ord($authData[32]);

        if (($flags & self::FLAG_USER_PRESENT) === 0 || ($flags & self::FLAG_USER_VERIFIED) === 0) {
            throw new ApiError("The passkey wasn't verified", 400);
        }

        return unpack("N", substr($authData, 33, 4))[1];
    }

    private static function pem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    public static function encode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), "+/", "-_"), "=");
    }

    public static function decode(string $base64url): string
    {
        return (string) base64_decode(strtr($base64url, "-_", "+/"), true);
    }
}
