<?php

use Blueprint\App;
use Blueprint\Passkey;
use Blueprint\User;

beforeEach(function () {
    $this->dir = copyProject(BLUEPRINT_PROJECT);
    $this->app = createApp($this->dir);
    $this->user = User::create("passkey@example.com", "secret-password", "editor");
    // a software authenticator
    $this->key = openssl_pkey_new(["private_key_type" => OPENSSL_KEYTYPE_EC, "curve_name" => "prime256v1"]);
});

afterEach(fn() => removeProject($this->dir));

// the passkey checks need the host of the request (relying party id)
function passkeyApi(App $app, string $method, string $path, ?array $json = null, ?string $csrf = null): array
{
    $response = $app->request("http://localhost/panel/api$path", $method, $json, $csrf === null ? [] : ["X-CSRF-Token" => $csrf]);

    return [$response->getStatusCode(), json_decode(body($response), true)];
}

function passkeyAuthData(int $counter = 0, int $flags = 0x05): string
{
    return hash("sha256", "localhost", true) . chr($flags) . pack("N", $counter);
}

function passkeyClientData(string $type, string $challenge, string $origin = "http://localhost"): string
{
    return json_encode(["type" => $type, "challenge" => $challenge, "origin" => $origin]);
}

function registerPasskey(App $app, OpenSSLAsymmetricKey $key, User $user, string $csrf, array $overrides = []): array
{
    [, $options] = passkeyApi($app, "POST", "/users/{$user->uid()}/~passkey-options", csrf: $csrf);
    $pem = openssl_pkey_get_details($key)["key"];

    return passkeyApi($app, "POST", "/users/{$user->uid()}/~passkeys", [
        "name" => "Laptop",
        "id" => "credential-1",
        "publicKey" => Passkey::encode(base64_decode(preg_replace('/-----[^-]+-----|\s/', "", $pem))),
        "algorithm" => -7,
        "clientDataJSON" => Passkey::encode(passkeyClientData("webauthn.create", $options["challenge"])),
        "authenticatorData" => Passkey::encode(passkeyAuthData()),
        ...$overrides,
    ], $csrf);
}

function passkeyLogin(App $app, OpenSSLAsymmetricKey $key, string $origin = "http://localhost", int $counter = 0): array
{
    [, $options] = passkeyApi($app, "POST", "/session/~passkey-options");
    $clientData = passkeyClientData("webauthn.get", $options["challenge"], $origin);
    $authData = passkeyAuthData($counter);
    openssl_sign($authData . hash("sha256", $clientData, true), $signature, $key, OPENSSL_ALGO_SHA256);

    return passkeyApi($app, "POST", "/session/~passkey", [
        "id" => "credential-1",
        "clientDataJSON" => Passkey::encode($clientData),
        "authenticatorData" => Passkey::encode($authData),
        "signature" => Passkey::encode($signature),
    ]);
}

function passwordLogin(App $app): string
{
    [, $session] = passkeyApi($app, "POST", "/session", ["email" => "passkey@example.com", "password" => "secret-password"]);

    return $session["csrf"];
}

it("registers a passkey and logs in with it", function () {
    $csrf = passwordLogin($this->app);
    [, $options] = passkeyApi($this->app, "POST", "/users/{$this->user->uid()}/~passkey-options", csrf: $csrf);

    expect($options["rp"]["id"])->toBe("localhost")
        ->and(Passkey::decode($options["user"]["id"]))->toBe($this->user->uid());

    [$status, $user] = registerPasskey($this->app, $this->key, $this->user, $csrf);

    expect($status)->toBe(201)
        ->and($user["passkeys"])->toHaveCount(1)
        ->and($user["passkeys"][0])->toHaveKeys(["id", "name", "createdAt", "lastUsedAt"])
        ->and($user["passkeys"][0])->not->toHaveKey("publicKey")
        ->and($user["passkeys"][0]["name"])->toBe("Laptop")
        ->and(User::find("passkey@example.com")->data->passkeys[0]->publicKey)->not->toBeEmpty();

    passkeyApi($this->app, "DELETE", "/session", csrf: $csrf);
    [$status, $session] = passkeyLogin($this->app, $this->key);

    expect($status)->toBe(200)
        ->and($session["user"]["email"])->toBe("passkey@example.com")
        ->and($session["user"]["passkeys"][0]["lastUsedAt"])->not->toBeNull()
        ->and(passkeyApi($this->app, "GET", "/site")[0])->toBe(200);
});

it("rejects invalid passkey logins", function () {
    $csrf = passwordLogin($this->app);
    registerPasskey($this->app, $this->key, $this->user, $csrf);
    passkeyApi($this->app, "DELETE", "/session", csrf: $csrf);
    $otherKey = openssl_pkey_new(["private_key_type" => OPENSSL_KEYTYPE_EC, "curve_name" => "prime256v1"]);

    expect(passkeyLogin($this->app, $otherKey)[0])->toBe(401)
        ->and(passkeyLogin($this->app, $this->key, "https://evil.example")[0])->toBe(400)
        ->and(passkeyApi($this->app, "GET", "/site")[0])->toBe(401);

    // a used challenge can't be replayed
    [, $options] = passkeyApi($this->app, "POST", "/session/~passkey-options");
    $clientData = passkeyClientData("webauthn.get", $options["challenge"]);
    openssl_sign(passkeyAuthData() . hash("sha256", $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);
    $body = ["id" => "credential-1", "clientDataJSON" => Passkey::encode($clientData), "authenticatorData" => Passkey::encode(passkeyAuthData()), "signature" => Passkey::encode($signature)];

    expect(passkeyApi($this->app, "POST", "/session/~passkey", $body)[0])->toBe(200)
        ->and(passkeyApi($this->app, "POST", "/session/~passkey", $body)[0])->toBe(400);
});

it("only registers verified passkeys of the own account", function () {
    $csrf = passwordLogin($this->app);

    expect(registerPasskey($this->app, $this->key, $this->user, $csrf, ["authenticatorData" => Passkey::encode(passkeyAuthData(flags: 0x01))])[0])->toBe(400)
        ->and(registerPasskey($this->app, $this->key, $this->user, $csrf, ["algorithm" => -8])[0])->toBe(400);

    $other = User::create("other@example.com", "secret-password", "editor");

    expect(passkeyApi($this->app, "POST", "/users/{$other->uid()}/~passkey-options", csrf: $csrf)[0])->toBe(403);
});

it("deletes passkeys", function () {
    $csrf = passwordLogin($this->app);
    registerPasskey($this->app, $this->key, $this->user, $csrf);
    [$status, $user] = passkeyApi($this->app, "DELETE", "/users/{$this->user->uid()}/~passkeys/credential-1", csrf: $csrf);

    expect($status)->toBe(200)
        ->and($user)->not->toHaveKey("passkeys")
        ->and(passkeyApi($this->app, "DELETE", "/users/{$this->user->uid()}/~passkeys/credential-1", csrf: $csrf)[0])->toBe(404);
});
