import { reactive } from "vue";

/**
 * The REST API client (/panel/api) and the session state.
 */
export const session = reactive({
  user: null,
  site: null,
  csrf: null,
  checked: false,
  // increased after every change, e.g. to reload the page tree in the sidebar
  changes: 0,
});

export class ApiError extends Error {
  constructor(status, body) {
    super(body?.error ?? `Request failed (${status})`);
    this.status = status;
    this.errors = body?.errors ?? {};
    this.body = body;
  }
}

async function request(method, path, data, file) {
  const headers = { Accept: "application/json" };

  if (data !== undefined) {
    headers["Content-Type"] = "application/json";
  }
  if (file) {
    headers["Content-Type"] = file.type || "application/octet-stream";
  }
  if (method !== "GET" && session.csrf) {
    headers["X-CSRF-Token"] = session.csrf;
  }

  const response = await fetch(`/panel/api${path}`, {
    method,
    headers,
    credentials: "same-origin",
    body: file ?? (data === undefined ? undefined : JSON.stringify(data)),
  });

  const body = response.status === 204 ? null : await response.json().catch(() => null);

  if (response.status === 401 && path !== "/session") {
    session.user = null;
  }
  if (!response.ok) {
    throw new ApiError(response.status, body);
  }
  if (method !== "GET" && path !== "/session") {
    session.changes++;
  }

  return body;
}

export const api = {
  get: (path) => request("GET", path),
  post: (path, data) => request("POST", path, data ?? {}),
  patch: (path, data) => request("PATCH", path, data ?? {}),
  put: (path, data) => request("PUT", path, data ?? {}),
  delete: (path) => request("DELETE", path),
  // the request body is the file itself
  upload: (pageId, file, template) => {
    const query = new URLSearchParams({ filename: file.name, ...(template ? { template } : {}) });
    return request("POST", `/pages/${pageId}/~files?${query}`, undefined, file);
  },
  // files of records: POST /models/{name}/{uid}/~files?filename=…
  uploadFile: (path, file) => request("POST", `${path}?${new URLSearchParams({ filename: file.name })}`, undefined, file),
  replace: (path, file) => request("PUT", `${path}?${new URLSearchParams({ filename: file.name })}`, undefined, file),
};

export async function loadSession() {
  try {
    Object.assign(session, await api.get("/session"));
  } catch {
    session.user = null;
  } finally {
    session.checked = true;
  }
}

export async function login(email, password) {
  Object.assign(session, await api.post("/session", { email, password }));
}

export async function logout() {
  await api.delete("/session").catch(() => {});
  Object.assign(session, { user: null, csrf: null });
}
