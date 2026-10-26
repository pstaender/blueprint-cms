<script setup>
import { ref, computed } from "vue";
import { api } from "../../api.js";
import { filesOf, reloadFiles, uploadFiles, sortFiles, uploadTemplate, acceptOf, FILE_DRAG_TYPE } from "../../files.js";
import { useSortable, move } from "../../sortable.js";
import FileThumb from "../FileThumb.vue";
import ContextMenu from "../ContextMenu.vue";
import Dialog from "../Dialog.vue";
import Icon from "../Icon.vue";

// The files of the model (`filelist` field), optionally filtered by `template`, with uploads, drag & drop sorting
// and a context menu (right click or the ⋯ button).
const props = defineProps({
  field: { type: Object, required: true },
  model: { type: Object, default: null },
});

const errors = ref([]);
const busy = ref(false);
const store = props.model?.id ? filesOf(props.model.id) : null;
const files = computed(() => {
  const list = store?.list ?? [];
  const templates = [props.field.template ?? []].flat();
  return templates.length ? list.filter((file) => templates.includes(file.template)) : list;
});

// a filtered list moves the file to its target's position in the full list
async function moveFile(from, to) {
  const all = store.list.map((file) => file.filename);
  const order = move(all, all.indexOf(files.value[from].filename), all.indexOf(files.value[to].filename));
  try {
    await sortFiles(props.model.id, order);
  } catch (e) {
    errors.value = [e.message];
  }
}

const { bind } = useSortable(moveFile, { dragData: (index) => ({ [FILE_DRAG_TYPE]: JSON.stringify(files.value[index]) }) });

async function upload(event) {
  busy.value = true;
  ({ errors: errors.value } = await uploadFiles(props.model.id, event.target.files, uploadTemplate(props.field)));
  event.target.value = "";
  busy.value = false;
}

// context menu with dialogs for the file at `current`
const menu = ref(null);
const replaceInput = ref(null);
const current = ref(0);
const file = computed(() => files.value[current.value]);
const path = computed(() => `/pages/${props.model.id}/~files/${encodeURIComponent(file.value.filename)}`);
const dialog = ref(null);
const dialogError = ref(null);
const form = ref({ name: "", position: 1 });

const menuItems = computed(() => file.value ? [
  { label: "Open", icon: "open", href: file.value.url },
  "-",
  { label: "Rename", icon: "edit", action: () => openDialog("rename") },
  { label: "Change position", icon: "sort", action: () => openDialog("position"), disabled: files.value.length < 2 },
  "-",
  { label: "Replace", icon: "replace", action: () => replaceInput.value.click() },
  "-",
  { label: "Delete", icon: "trash", action: () => openDialog("delete"), danger: true },
] : []);

const dialogs = {
  rename: { title: "Rename file", submit: "Rename" },
  position: { title: "Change position", submit: "Move" },
  delete: { title: "Delete file", submit: "Delete" },
};

function showMenu(event, index) {
  current.value = index;
  menu.value.show(event);
}

function openDialog(name) {
  form.value = { name: file.value.filename.replace(/\.[^.]+$/, ""), position: current.value + 1 };
  dialogError.value = null;
  dialog.value = name;
}

async function submitDialog() {
  busy.value = true;
  dialogError.value = null;
  try {
    if (dialog.value === "rename") {
      await api.patch(path.value, { name: form.value.name });
      await reloadFiles(props.model.id);
    } else if (dialog.value === "position") {
      await moveFile(current.value, form.value.position - 1);
    } else {
      await api.delete(path.value);
      await reloadFiles(props.model.id);
    }
    dialog.value = null;
  } catch (e) {
    dialogError.value = e.message;
  } finally {
    busy.value = false;
  }
}

async function replace(event) {
  const [upload] = event.target.files;
  event.target.value = "";
  if (!upload) return;
  busy.value = true;
  try {
    await api.replace(path.value, upload);
    errors.value = [];
    await reloadFiles(props.model.id);
  } catch (e) {
    errors.value = [`${upload.name}: ${e.message}`];
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <section class="section">
    <header class="section-header">
      <h3 class="field-label">{{ field.label }}</h3>
      <label v-if="model?.id" class="button button-small" :class="{ 'is-busy': busy }">
        <Icon name="upload" /> Upload
        <input type="file" multiple hidden :accept="acceptOf(field)" @change="upload">
      </label>
    </header>
    <p v-if="!model?.id" class="field-help">Files are only available for pages.</p>
    <p v-for="error in errors" :key="error" class="notice notice-negative">{{ error }}</p>

    <ul v-if="files.length" class="items" :class="`items-${field.layout || 'list'}`">
      <li
        v-for="(item, index) in files"
        :key="item.filename"
        class="item item-with-options"
        v-bind="bind(index)"
        title="Drag to sort or into a textarea, right click for options"
        @contextmenu.prevent="showMenu($event, index)"
      >
        <RouterLink :to="`/pages/${item.page}/~files/${encodeURIComponent(item.filename)}`" class="item-link" draggable="false">
          <FileThumb :file="item" />
          <span class="item-title">{{ item.filename }}</span>
          <span class="item-info">{{ item.template !== "default" ? item.template : "" }}</span>
          <span v-if="!item.isUsed" class="item-info" title="Only used files are public">not used</span>
        </RouterLink>
        <button type="button" class="icon-button item-options" title="Options" aria-haspopup="menu" @click="showMenu($event, index)">
          <Icon name="more" />
        </button>
      </li>
    </ul>
    <p v-else-if="model?.id" class="empty">No files yet</p>

    <template v-if="model?.id">
      <ContextMenu ref="menu" :items="menuItems" />
      <input ref="replaceInput" type="file" hidden :accept="file ? `.${file.extension}` : undefined" @change="replace">

      <Dialog
        :open="dialog !== null"
        :title="dialogs[dialog]?.title"
        :submit-label="dialogs[dialog]?.submit"
        :danger="dialog === 'delete'"
        :busy="busy"
        @update:open="dialog = $event ? dialog : null"
        @submit="submitDialog"
      >
        <template v-if="file">
          <p v-if="dialogError" class="notice notice-negative">{{ dialogError }}</p>
          <p v-if="dialog === 'delete'">Do you really want to delete <strong>{{ file.filename }}</strong>?</p>
          <label v-else-if="dialog === 'rename'" class="field">
            <span class="field-label">Name</span>
            <input v-model="form.name" class="input" type="text" required>
            <small class="field-help">The extension .{{ file.extension }} stays.</small>
          </label>
          <label v-else-if="dialog === 'position'" class="field">
            <span class="field-label">Position of {{ file.filename }}</span>
            <select v-model.number="form.position" class="input">
              <option v-for="(item, index) in files" :key="item.filename" :value="index + 1">
                {{ index + 1 }}{{ index === current ? "" : ` – ${index < current ? "before" : "after"} ${item.filename}` }}
              </option>
            </select>
          </label>
        </template>
      </Dialog>
    </template>
  </section>
</template>
