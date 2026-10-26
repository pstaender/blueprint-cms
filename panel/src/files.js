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
  const files = await api.get(`/pages/${pageId}/~files`);
  store[pageId] ??= { list: [], loaded: false };
  store[pageId].list = files;
  store[pageId].loaded = true;
}

// stores a new order (filenames) and updates the shared list
export async function sortFiles(pageId, filenames) {
  store[pageId].list = await api.patch(`/pages/${pageId}/~files`, { sort: filenames });
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

// files dragged from file lists carry their data with this type
export const FILE_DRAG_TYPE = "application/x-blueprint-file";

// `![hello](hello.jpg)` for images, `[report](report.pdf)` for other files, other pages' files with their path
export function markdownLink(file, pageId) {
  const target = file.page === pageId ? file.filename : file.id;
  const name = file.filename.replace(/\.[^.]+$/, "");
  return file.type === "image" ? `![${name}](${target})` : `[${name}](${target})`;
}

// files dragged from a file list or (to upload them into the page) from the computer
export function isFileDrag(dataTransfer, pageId) {
  const types = [...dataTransfer.types];
  return types.includes(FILE_DRAG_TYPE) || (types.includes("Files") && Boolean(pageId));
}

// the markdown links of dropped files, uploads files from the computer first
export async function droppedFileLinks(dataTransfer, pageId) {
  const data = dataTransfer.getData(FILE_DRAG_TYPE);
  const files = data ? [JSON.parse(data)] : (await uploadFiles(pageId, dataTransfer.files)).uploaded;
  return files.map((file) => markdownLink(file, pageId)).join("\n");
}

export function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
