<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";
import { useEditing } from "../editing.js";
import { reloadFiles } from "../files.js";
import ModelForm from "../components/ModelForm.vue";
import ViewHeader from "../components/ViewHeader.vue";
import PageItems from "../components/PageItems.vue";
import FileList from "../components/fields/FileList.vue";
import Dialog from "../components/Dialog.vue";
import Icon from "../components/Icon.vue";
import StatusDot from "../components/StatusDot.vue";

const props = defineProps({ id: { type: String, required: true } });
const router = useRouter();
const page = ref(null);
const loadError = ref(null);
const dialog = ref(null);
const busy = ref(false);
const dialogError = ref(null);
const settings = ref({ slug: "", type: "", contentFormat: "json" });

const contentFormats = [
  { value: "json", label: "JSON (recommended)", files: "page.json" },
  { value: "md", label: "Markdown with front matter", files: "page.md" },
  { value: "json+md", label: "JSON with the text as Markdown", files: "page.json + page.content.md" },
];
const types = ref([]);

const { values, errors, message, saving, isDirty, reset, submit } = useEditing(async (data) => {
  load(await api.patch(`/pages/${props.id}`, { data }));
  // saving can change which files are used (public)
  await reloadFiles(props.id);
});

function load(data) {
  page.value = data;
  reset(data.data);
}

async function reloadChildren() {
  page.value.children = await api.get(`/pages/${props.id}/children`);
}

const model = computed(() => page.value && { ...page.value, reload: reloadChildren });
const breadcrumb = computed(() => [
  { to: "/site", title: "Site" },
  ...(page.value?.breadcrumb ?? []).map((item) => ({ to: `/pages/${item.id}`, title: item.title })),
]);
const statuses = computed(() => {
  const status = { ...(page.value?.blueprint.status ?? {}) };
  if (page.value?.isHome) delete status.draft;
  return status;
});
const fieldTypes = computed(() => Object.values(page.value?.blueprint.fields ?? {}).map((field) => field.type));

async function changeStatus(event) {
  busy.value = true;
  try {
    const updated = await api.patch(`/pages/${props.id}`, { status: event.target.value });
    if (updated.id !== props.id) router.replace(`/pages/${updated.id}`);
    load(updated);
  } catch (e) {
    message.value = { type: "negative", text: e.message };
    event.target.value = page.value.status;
  } finally {
    busy.value = false;
  }
}

async function openSettings() {
  settings.value = { slug: page.value.path.split("/").pop(), type: page.value.type, contentFormat: page.value.contentFormat };
  dialogError.value = null;
  dialog.value = "settings";
  types.value = await api.get("/blueprints/pages");
  if (!types.value.some((type) => type.type === settings.value.type)) {
    types.value.push({ type: settings.value.type, title: `${settings.value.type} (no blueprint)` });
  }
}

async function submitDialog() {
  busy.value = true;
  dialogError.value = null;
  try {
    if (dialog.value === "delete") {
      await api.delete(`/pages/${props.id}`);
      dialog.value = null;
      const parent = page.value.breadcrumb.at(-1);
      return router.push(parent ? `/pages/${parent.id}` : "/site");
    }
    const updated = await api.patch(`/pages/${props.id}`, settings.value);
    dialog.value = null;
    if (updated.id !== props.id) return router.replace(`/pages/${updated.id}`);
    load(updated);
  } catch (e) {
    dialogError.value = e.message;
  } finally {
    busy.value = false;
  }
}

onMounted(async () => {
  try {
    load(await api.get(`/pages/${props.id}`));
  } catch (e) {
    loadError.value = e.message;
  }
});
</script>

<template>
  <p v-if="loadError" class="notice notice-negative">{{ loadError }}</p>
  <div v-else-if="page" class="view">
    <ViewHeader
      :title="page.title"
      :breadcrumb="breadcrumb"
      :is-dirty="isDirty"
      :saving="saving"
      :message="message"
      @save="submit"
      @discard="reset(page.data)"
    >
      <template #actions>
        <a class="button" :href="page.url" target="_blank"><Icon name="open" /> Open</a>
        <label class="button status-select">
          <StatusDot :status="page.status" />
          <select :value="page.status" :disabled="busy || isDirty" title="Status" @change="changeStatus">
            <option v-for="(status, key) in statuses" :key="key" :value="key">{{ status.label }}</option>
          </select>
        </label>
        <button type="button" class="button" title="Page settings" :disabled="isDirty" @click="openSettings"><Icon name="settings" /></button>
        <button v-if="!page.isHome" type="button" class="button" title="Delete" @click="dialog = 'delete'"><Icon name="trash" /></button>
      </template>
    </ViewHeader>

    <p v-if="!page.isEditable" class="notice notice-info">This page is rendered by a php file and can't be edited.</p>
    <ModelForm v-else :blueprint="page.blueprint" :values="values" :errors="errors" :model="model" />

    <PageItems v-if="!fieldTypes.includes('pagelist') && page.children.length" label="Subpages" :pages="page.children" :parent="model" @sorted="reloadChildren" />
    <FileList v-if="!fieldTypes.includes('filelist') && page.files.length" :field="{ label: 'Files', type: 'filelist' }" :model="model" />

    <Dialog
      :open="dialog !== null"
      :title="dialog === 'delete' ? 'Delete page' : 'Page settings'"
      :submit-label="dialog === 'delete' ? 'Delete' : 'Save'"
      :danger="dialog === 'delete'"
      :busy="busy"
      @update:open="dialog = $event ? dialog : null"
      @submit="submitDialog"
    >
      <p v-if="dialogError" class="notice notice-negative">{{ dialogError }}</p>
      <p v-if="dialog === 'delete'">Do you really want to delete <strong>{{ page.title }}</strong><template v-if="page.hasChildren"> and all its subpages</template>?</p>
      <template v-else>
        <label class="field">
          <span class="field-label">Slug</span>
          <input v-model="settings.slug" class="input" type="text" pattern="[a-z0-9][a-z0-9_\-]*" :disabled="page.isHome" required>
          <small class="field-help">{{ page.url.replace(/[^/]+\/?$/, "") }}{{ settings.slug }}</small>
        </label>
        <label class="field">
          <span class="field-label">Type</span>
          <select v-model="settings.type" class="input">
            <option v-for="type in types" :key="type.type" :value="type.type">{{ type.title }}</option>
          </select>
          <small class="field-help">Chooses the blueprint (pages/{{ settings.type }}.yml) and the template.</small>
        </label>
        <label v-if="page.isEditable" class="field">
          <span class="field-label">Storage format</span>
          <select v-model="settings.contentFormat" class="input">
            <option v-for="format in contentFormats" :key="format.value" :value="format.value">{{ format.label }}</option>
          </select>
          <small class="field-help">
            Stored as {{ contentFormats.find((format) => format.value === settings.contentFormat)?.files }}.
            Changes only the files, not the content.
          </small>
        </label>
      </template>
    </Dialog>
  </div>
</template>
