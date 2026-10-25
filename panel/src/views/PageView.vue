<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";
import { useEditing } from "../editing.js";
import ModelForm from "../components/ModelForm.vue";
import ViewHeader from "../components/ViewHeader.vue";
import PageItems from "../components/PageItems.vue";
import Dialog from "../components/Dialog.vue";
import Icon from "../components/Icon.vue";
import StatusDot from "../components/StatusDot.vue";

const props = defineProps({ id: { type: String, required: true } });
const router = useRouter();
const page = ref(null);
const loadError = ref(null);
const deleting = ref(false);
const busy = ref(false);

const { values, errors, message, saving, isDirty, reset, submit } = useEditing(async (data) => {
  load(await api.patch(`/pages/${props.id}`, { data }));
});

function load(data) {
  page.value = data;
  reset(data.data);
}

const breadcrumb = computed(() => [
  { to: "/site", title: "Site" },
  ...(page.value?.breadcrumb ?? []).map((item) => ({ to: `/pages/${item.id}`, title: item.title })),
]);
const statuses = computed(() => {
  const status = { ...(page.value?.blueprint.status ?? {}) };
  if (page.value?.isHome) delete status.draft;
  return status;
});
const hasPageList = computed(() => Object.values(page.value?.blueprint.fields ?? {}).some((field) => field.type === "pagelist"));

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

async function remove() {
  busy.value = true;
  try {
    await api.delete(`/pages/${props.id}`);
    deleting.value = false;
    const parent = page.value.breadcrumb.at(-1);
    router.push(parent ? `/pages/${parent.id}` : "/site");
  } catch (e) {
    message.value = { type: "negative", text: e.message };
    deleting.value = false;
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
        <button v-if="!page.isHome" type="button" class="button" title="Delete" @click="deleting = true"><Icon name="trash" /></button>
      </template>
    </ViewHeader>

    <p v-if="!page.isEditable" class="notice notice-info">This page is rendered by a php file and can't be edited.</p>
    <ModelForm v-else :blueprint="page.blueprint" :values="values" :errors="errors" :model="page" />

    <PageItems v-if="!hasPageList && page.children.length" label="Subpages" :pages="page.children" :parent="page" />

    <Dialog v-model:open="deleting" title="Delete page" submit-label="Delete" danger :busy="busy" @submit="remove">
      <p>Do you really want to delete <strong>{{ page.title }}</strong><template v-if="page.hasChildren"> and all its subpages</template>?</p>
    </Dialog>
  </div>
</template>
