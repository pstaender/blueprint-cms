<script setup>
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";
import Icon from "./Icon.vue";
import StatusDot from "./StatusDot.vue";
import Dialog from "./Dialog.vue";

// A list of pages with an "Add" dialog that creates a draft below the parent.
const props = defineProps({
  label: { type: String, default: "Pages" },
  pages: { type: Array, required: true },
  layout: { type: String, default: "list" },
  parent: { type: Object, default: null },
  templates: { type: Array, default: () => [] },
});

const router = useRouter();
const open = ref(false);
const busy = ref(false);
const error = ref(null);
const blueprints = ref([]);
const form = ref({ title: "", slug: "", template: "" });

const canCreate = computed(() => props.parent && props.parent.canHaveChildren !== false);
const choices = computed(() =>
  props.templates.length ? blueprints.value.filter((blueprint) => props.templates.includes(blueprint.template)) : blueprints.value,
);

async function start() {
  blueprints.value = await api.get("/blueprints/pages");
  form.value = { title: "", slug: "", template: choices.value[0]?.template ?? "default" };
  error.value = null;
  open.value = true;
}

async function create() {
  busy.value = true;
  try {
    const path = props.parent.id ? `/pages/${props.parent.id}/children` : "/site/children";
    const page = await api.post(path, form.value);
    open.value = false;
    router.push(`/pages/${page.id}`);
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <section class="section">
    <header class="section-header">
      <h3 class="field-label">{{ label }}</h3>
      <button v-if="canCreate" type="button" class="button button-small" @click="start"><Icon name="plus" /> Add</button>
    </header>

    <ul v-if="pages.length" class="items" :class="`items-${layout || 'list'}`">
      <li v-for="page in pages" :key="page.id" class="item">
        <RouterLink :to="`/pages/${page.id}`" class="item-link">
          <span class="item-icon">{{ page.icon || "" }}</span>
          <span class="item-title">{{ page.title }}</span>
          <span class="item-info">{{ page.template }}</span>
          <span v-if="page.num" class="item-info">{{ page.num }}</span>
          <StatusDot :status="page.status" />
        </RouterLink>
      </li>
    </ul>
    <p v-else class="empty">No pages yet</p>

    <Dialog v-model:open="open" title="New page" submit-label="Create draft" :busy="busy" @submit="create">
      <p v-if="error" class="notice notice-negative">{{ error }}</p>
      <label class="field">
        <span class="field-label">Title</span>
        <input v-model="form.title" class="input" type="text" required>
      </label>
      <label class="field">
        <span class="field-label">Slug</span>
        <input v-model="form.slug" class="input" type="text" pattern="[a-z0-9][a-z0-9_\-]*" placeholder="from the title">
      </label>
      <label v-if="choices.length > 1" class="field">
        <span class="field-label">Template</span>
        <select v-model="form.template" class="input">
          <option v-for="blueprint in choices" :key="blueprint.template" :value="blueprint.template">{{ blueprint.title }}</option>
        </select>
      </label>
    </Dialog>
  </section>
</template>
