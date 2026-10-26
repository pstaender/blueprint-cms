import { api, session } from "./api.js";

/**
 * Passkeys (WebAuthn) for the panel login. The server sends binary values base64url encoded and gets them back
 * like that; new passkeys send their public key (getPublicKey()), so the server doesn't need to parse CBOR.
 */
export const passkeysSupported = typeof window.PublicKeyCredential === "function"
  && typeof window.AuthenticatorAttestationResponse?.prototype.getPublicKey === "function";

const encode = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
const decode = (value) => Uint8Array.from(atob(value.replace(/-/g, "+").replace(/_/g, "/")), (char) => char.charCodeAt(0));

// adds a passkey to the logged in user, returns the updated user
export async function createPasskey(uid, name) {
  const options = await api.post(`/users/${uid}/~passkey-options`);
  const credential = await navigator.credentials.create({
    publicKey: {
      ...options,
      challenge: decode(options.challenge),
      user: { ...options.user, id: decode(options.user.id) },
      excludeCredentials: options.excludeCredentials.map((item) => ({ ...item, id: decode(item.id) })),
    },
  });
  const response = credential.response;

  return api.post(`/users/${uid}/~passkeys`, {
    name,
    id: credential.id,
    publicKey: encode(response.getPublicKey()),
    algorithm: response.getPublicKeyAlgorithm(),
    clientDataJSON: encode(response.clientDataJSON),
    authenticatorData: encode(response.getAuthenticatorData()),
  });
}

export async function loginWithPasskey() {
  const options = await api.post("/session/~passkey-options");
  const credential = await navigator.credentials.get({ publicKey: { ...options, challenge: decode(options.challenge) } });
  const response = credential.response;

  Object.assign(session, await api.post("/session/~passkey", {
    id: credential.id,
    clientDataJSON: encode(response.clientDataJSON),
    authenticatorData: encode(response.authenticatorData),
    signature: encode(response.signature),
    userHandle: response.userHandle ? encode(response.userHandle) : null,
  }));
}

// a cancelled or timed out browser dialog isn't an error worth showing
export const isCancelled = (error) => error?.name === "NotAllowedError" || error?.name === "AbortError";
