<script setup>
import { ref, computed, inject, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";
import { useEditing } from "../editing.js";
import ModelForm from "../components/ModelForm.vue";
import ViewHeader from "../components/ViewHeader.vue";
import Dialog from "../components/Dialog.vue";
import Icon from "../components/Icon.vue";

// A record of a model, without uid a new one (/models/{name}/new).
const props = defineProps({ name: { type: String, required: true }, uid: { type: String, default: null } });
const router = useRouter();
const collection = inject("collection");
const record = ref(null);
const loadError = ref(null);
const dialog = ref(null);
const busy = ref(false);
const dialogError = ref(null);
const fileInput = ref(null);
const path = computed(() => `/models/${props.name}/${props.uid}`);
const moveToTrash = computed(() => record.value?.model?.softDelete && !record.value.isTrashed);

const form = useEditing(async (body) => {
  if (!props.uid) {
    const created = await api.post(`/models/${props.name}`, { data: body.data });
    form.reset(created.data, created.data.modifiedAt);
    return router.replace(`/models/${props.name}/${created.uid}`);
  }
  load(await api.patch(path.value, body));
}, async () => load(await api.get(path.value)));
const { values, errors } = form;

function load(data) {
  record.value = data;
  form.reset(data.data, data.data.modifiedAt);
}

// a new record starts with the default values of its fields
function startNew() {
  const blueprint = collection.value.blueprint;
  record.value = { title: "New record", blueprint, files: [], isTrashed: false };
  form.reset({});
  for (const [name, field] of Object.entries(blueprint.fields)) {
    if (field.default !== undefined && field.default !== null) values.value[name] = field.default;
  }
}

async function submitDialog() {
  busy.value = true;
  dialogError.value = null;
  try {
    if (dialog.value === "delete") {
      await api.delete(path.value);
      dialog.value = null;
      return router.push(`/models/${props.name}`);
    }
  } catch (e) {
    dialogError.value = e.message;
  } finally {
    busy.value = false;
  }
}

async function restore() {
  load(await api.post(`${path.value}/~restore`));
}

async function upload(event) {
  try {
    for (const file of event.target.files) load(await api.uploadFile(`${path.value}/~files`, file));
  } catch (e) {
    form.message.value = { type: "negative", text: e.message };
  } finally {
    event.target.value = "";
  }
}

async function deleteFile(filename) {
  load(await api.delete(`${path.value}/~files/${encodeURIComponent(filename)}`));
}

const fileUrl = (filename) => `/panel/api${path.value}/~files/${encodeURIComponent(filename)}`;
const formatSize = (bytes) => (bytes < 1024 ? `${bytes} B` : bytes < 1024 ** 2 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1024 ** 2).toFixed(1)} MB`);
const formatDate = (date) => (date ? new Date(date).toLocaleString() : "");

onMounted(async () => {
  if (!props.uid) return startNew();
  try {
    load(await api.get(path.value));
  } catch (e) {
    loadError.value = e.message;
  }
});
</script>

<template>
  <p v-if="loadError" class="notice notice-negative">{{ loadError }}</p>
  <div v-else-if="record" class="view">
    <ViewHeader
      :title="record.title"
      :is-dirty="form.isDirty.value"
      :saving="form.saving.value"
      :message="form.message.value"
      :conflict="form.conflict.value"
      @save="form.submit"
      @discard="uid ? load(record) : startNew()"
      @overwrite="form.overwrite"
      @reload="form.loadLatest"
    >
      <template #actions>
        <button v-if="record.isTrashed" type="button" class="button" @click="restore"><Icon name="undo" /> Restore</button>
        <button v-if="uid" type="button" class="button" :title="moveToTrash ? 'Move to the trash' : 'Delete permanently'" @click="dialog = 'delete'"><Icon name="trash" /></button>
      </template>
    </ViewHeader>

    <p v-if="record.isTrashed" class="notice notice-info">This record is in the trash. Restore it to edit it.</p>
    <p v-if="uid" class="field-help record-meta">
      <code>{{ record.ref }}</code> · created {{ formatDate(record.createdAt) }} · modified {{ formatDate(record.modifiedAt) }}
    </p>

    <fieldset class="record-fields" :disabled="record.isTrashed">
      <ModelForm :blueprint="record.blueprint" :values="values" :errors="errors" />
    </fieldset>

    <section v-if="uid && record.model.files" class="section">
      <header class="section-header">
        <h3 class="field-label">Files</h3>
        <button v-if="!record.isTrashed" type="button" class="button button-small" @click="fileInput.click()"><Icon name="upload" /> Upload</button>
        <input ref="fileInput" type="file" multiple hidden @change="upload">
      </header>
      <ul v-if="record.files.length" class="items">
        <li v-for="file in record.files" :key="file.filename" class="item">
          <div class="item-link">
            <span class="item-icon"><Icon name="file" /></span>
            <a class="item-title" :href="fileUrl(file.filename)" target="_blank">{{ file.filename }}</a>
            <span class="item-info">{{ formatSize(file.size) }}</span>
            <button v-if="!record.isTrashed" type="button" class="icon-button" :title="`Delete ${file.filename}`" @click="deleteFile(file.filename)"><Icon name="trash" /></button>
          </div>
        </li>
      </ul>
      <p v-else class="empty">No files</p>
    </section>

    <Dialog
      :open="dialog !== null"
      :title="moveToTrash ? 'Move to the trash' : 'Delete record'"
      :submit-label="moveToTrash ? 'Move to the trash' : 'Delete'"
      danger
      :busy="busy"
      @update:open="dialog = $event ? dialog : null"
      @submit="submitDialog"
    >
      <p v-if="dialogError" class="notice notice-negative">{{ dialogError }}</p>
      <p v-if="!moveToTrash">Do you really want to delete <strong>{{ record.title }}</strong> and its files permanently?</p>
      <p v-else>Move <strong>{{ record.title }}</strong> to the trash?</p>
    </Dialog>
  </div>
</template>
