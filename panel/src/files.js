import { reactive } from "vue";
import { api } from "./api.js";

/**
 * The files of pages, shared by all file fields: `filesOf(pageId).list` (call it in setup, not in computeds).
 */
const store = reactive({});

export function filesOf(pageId) {
  if (!store[pageId]) {
    store[pageId] = { list: [], loaded: false };
    reloadFiles(pageId);
  }
  return store[pageId];
}

export async function reloadFiles(pageId) {
  const files = await api.get(`/pages/${pageId}/files`);
  store[pageId] ??= { list: [], loaded: false };
  store[pageId].list = files;
  store[pageId].loaded = true;
}

// stores a new order (filenames) and updates the shared list
export async function sortFiles(pageId, filenames) {
  store[pageId].list = await api.patch(`/pages/${pageId}/files`, { sort: filenames });
}

export async function uploadFiles(pageId, fileList, template) {
  const uploaded = [];
  const errors = [];
  for (const file of fileList) {
    try {
      uploaded.push(await api.upload(pageId, file, template));
    } catch (e) {
      errors.push(`${file.name}: ${e.message}`);
    }
  }
  await reloadFiles(pageId);
  return { uploaded, errors };
}

// `uploads: image` or `uploads: {template: image}`, falls back to the field's `template`
export function uploadTemplate(field) {
  const uploads = field.uploads;
  if (typeof uploads === "string") return uploads;
  if (uploads && typeof uploads === "object") return uploads.template ?? null;
  return field.template ?? null;
}

// `accept: image/*` of a field (or of its uploads) for <input type=file accept>
export function acceptOf(field) {
  const accept = field.accept;
  if (typeof accept === "string") return accept;
  return accept?.mime ?? (field.type === "filepicker" && field.image ? "image/*" : undefined);
}

export function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
