<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";
import { useEditing } from "../editing.js";
import { reloadFiles, formatSize } from "../files.js";
import ModelForm from "../components/ModelForm.vue";
import ViewHeader from "../components/ViewHeader.vue";
import Dialog from "../components/Dialog.vue";
import Icon from "../components/Icon.vue";

const props = defineProps({
  id: { type: String, required: true },
  filename: { type: String, required: true },
});
const router = useRouter();
const file = ref(null);
const loadError = ref(null);
const dialog = ref(null);
const busy = ref(false);
const dialogError = ref(null);
const name = ref("");

const path = computed(() => `/pages/${props.id}/files/${encodeURIComponent(props.filename)}`);
const { values, errors, message, saving, isDirty, reset, submit } = useEditing(async (data) => {
  load(await api.patch(path.value, { data }));
});

function load(data) {
  file.value = data;
  reset(data.data);
}

const breadcrumb = computed(() => [
  { to: "/site", title: "Site" },
  ...(file.value?.breadcrumb ?? []).map((item) => ({ to: `/pages/${item.id}`, title: item.title })),
]);
const model = computed(() => file.value && { id: props.id });

function openRename() {
  name.value = file.value.filename.replace(/\.[^.]+$/, "");
  dialogError.value = null;
  dialog.value = "rename";
}

async function submitDialog() {
  busy.value = true;
  dialogError.value = null;
  try {
    if (dialog.value === "delete") {
      await api.delete(path.value);
      await reloadFiles(props.id);
      dialog.value = null;
      return router.push(`/pages/${props.id}`);
    }
    const updated = await api.patch(path.value, { name: name.value });
    await reloadFiles(props.id);
    dialog.value = null;
    router.replace(`/pages/${props.id}/files/${encodeURIComponent(updated.filename)}`);
  } catch (e) {
    dialogError.value = e.message;
  } finally {
    busy.value = false;
  }
}

onMounted(async () => {
  try {
    load(await api.get(path.value));
  } catch (e) {
    loadError.value = e.message;
  }
});
</script>

<template>
  <p v-if="loadError" class="notice notice-negative">{{ loadError }}</p>
  <div v-else-if="file" class="view">
    <ViewHeader :title="file.filename" :breadcrumb="breadcrumb" :is-dirty="isDirty" :saving="saving" :message="message" @save="submit" @discard="reset(file.data)">
      <template #actions>
        <a class="button" :href="file.url" target="_blank"><Icon name="open" /> Open</a>
        <button type="button" class="button" :disabled="isDirty" @click="openRename">Rename</button>
        <button type="button" class="button" title="Delete" @click="dialog = 'delete'"><Icon name="trash" /></button>
      </template>
    </ViewHeader>

    <div class="file-overview">
      <figure class="file-preview">
        <img v-if="file.type === 'image'" :src="file.url" :alt="file.filename">
        <video v-else-if="file.type === 'video'" :src="file.url" controls></video>
        <audio v-else-if="file.type === 'audio'" :src="file.url" controls></audio>
        <Icon v-else name="file" />
      </figure>
      <dl class="file-info">
        <dt>Type</dt><dd>{{ file.mime }}</dd>
        <dt>Size</dt><dd>{{ formatSize(file.size) }}</dd>
        <template v-if="file.dimensions"><dt>Dimensions</dt><dd>{{ file.dimensions.width }} × {{ file.dimensions.height }}</dd></template>
        <dt>Template</dt><dd>{{ file.template }}</dd>
        <dt>Url</dt><dd><a :href="file.url" target="_blank">{{ file.url }}</a></dd>
        <dt>Public</dt><dd>{{ file.isUsed ? "yes" : "no, only used files are public (link it in a textarea or pick it in a field)" }}</dd>
      </dl>
    </div>

    <ModelForm :blueprint="file.blueprint" :values="values" :errors="errors" :model="model" />

    <Dialog
      :open="dialog !== null"
      :title="dialog === 'delete' ? 'Delete file' : 'Rename file'"
      :submit-label="dialog === 'delete' ? 'Delete' : 'Rename'"
      :danger="dialog === 'delete'"
      :busy="busy"
      @update:open="dialog = $event ? dialog : null"
      @submit="submitDialog"
    >
      <p v-if="dialogError" class="notice notice-negative">{{ dialogError }}</p>
      <p v-if="dialog === 'delete'">Do you really want to delete <strong>{{ file.filename }}</strong>?</p>
      <label v-else class="field">
        <span class="field-label">Name</span>
        <input v-model="name" class="input" type="text" required>
        <small class="field-help">The extension .{{ file.extension }} stays.</small>
      </label>
    </Dialog>
  </div>
</template>
