<script setup>
import { ref, computed, watch } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";
import { useSortable } from "../sortable.js";
import Icon from "./Icon.vue";
import PageIcon from "./PageIcon.vue";
import StatusDot from "./StatusDot.vue";
import Dialog from "./Dialog.vue";
import ParentSelect from "./ParentSelect.vue";

// A list of pages: listed pages can be sorted by drag & drop, "Add" creates a draft below the parent.
// Selected pages (checkboxes) can be changed together: status, move, delete.
const props = defineProps({
  label: { type: String, default: "Pages" },
  pages: { type: Array, required: true },
  layout: { type: String, default: "list" },
  parent: { type: Object, default: null },
  types: { type: Array, default: () => [] },
});
const emit = defineEmits(["changed"]);

const router = useRouter();
const open = ref(false);
const busy = ref(false);
const error = ref(null);
const blueprints = ref([]);
const form = ref({ title: "", slug: "", type: "" });
const items = ref(null);

const list = computed(() => items.value ?? props.pages);
const canCreate = computed(() => props.parent && props.parent.canHaveChildren !== false);
const choices = computed(() =>
  props.types.length ? blueprints.value.filter((blueprint) => props.types.includes(blueprint.type)) : blueprints.value,
);

// position among the listed siblings = position in the list, listed pages come first
const { bind } = useSortable(async (from, to) => {
  const page = list.value[from];
  const target = list.value[to];
  if (page.status !== "listed" || target.status !== "listed") return;
  const reordered = [...list.value];
  reordered.splice(to, 0, ...reordered.splice(from, 1));
  items.value = reordered;
  try {
    await api.patch(`/pages/${page.id}`, { num: target.num });
    emit("changed");
  } catch (e) {
    error.value = e.message;
  } finally {
    items.value = null;
  }
});

// selection (ids) and the actions for it
const selected = ref([]);
const action = ref(null);
const parent = ref("");
const allSelected = computed(() => list.value.length > 0 && selected.value.length === list.value.length);
const statuses = computed(() => [...new Set(list.value.flatMap((page) => page.statuses ?? []))]);
const STATUS_LABELS = { draft: "Draft", unlisted: "Unlisted", listed: "Listed" };

watch(() => props.pages, (pages) => {
  selected.value = selected.value.filter((id) => pages.some((page) => page.id === id));
});

function selectAll(event) {
  selected.value = event.target.checked ? list.value.map((page) => page.id) : [];
}

function openAction(name) {
  parent.value = props.parent?.id ?? "";
  error.value = null;
  action.value = name;
}

// one request per page, failures (e.g. the home page can't be deleted) are collected
async function apply(request) {
  busy.value = true;
  error.value = null;
  const failed = [];
  for (const id of [...selected.value]) {
    try {
      await request(id);
    } catch (e) {
      failed.push(`${list.value.find((page) => page.id === id)?.title ?? id}: ${e.message}`);
    }
  }
  busy.value = false;
  action.value = null;
  selected.value = [];
  error.value = failed.length ? failed.join(", ") : null;
  emit("changed");
}

function changeStatus(event) {
  const status = event.target.value;
  event.target.value = "";
  if (status) apply((id) => api.patch(`/pages/${id}`, { status }));
}

const submitAction = () => action.value === "delete"
  ? apply((id) => api.delete(`/pages/${id}`))
  : apply((id) => api.patch(`/pages/${id}`, { parent: parent.value }));

async function start() {
  blueprints.value = await api.get("/blueprints/pages");
  form.value = { title: "", slug: "", type: choices.value[0]?.type ?? "page" };
  error.value = null;
  open.value = true;
}

async function create() {
  busy.value = true;
  try {
    const path = props.parent.id ? `/pages/${props.parent.id}/~children` : "/site/~children";
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
      <h3 class="field-label">
        <input v-if="list.length" type="checkbox" class="item-select" :checked="allSelected" :indeterminate="selected.length > 0 && !allSelected" title="Select all" @change="selectAll">
        {{ label }}
      </h3>
      <div v-if="selected.length" class="bulk-actions">
        <span class="item-info">{{ selected.length }} selected</span>
        <select class="input input-small" :disabled="busy" title="Change status" @change="changeStatus">
          <option value="">Status …</option>
          <option v-for="status in statuses" :key="status" :value="status">{{ STATUS_LABELS[status] }}</option>
        </select>
        <button type="button" class="button button-small" :disabled="busy" @click="openAction('move')"><Icon name="sort" /> Move</button>
        <button type="button" class="button button-small" :disabled="busy" @click="openAction('delete')"><Icon name="trash" /> Delete</button>
      </div>
      <button v-else-if="canCreate" type="button" class="button button-small" @click="start"><Icon name="plus" /> Add</button>
    </header>

    <p v-if="error && !open && !action" class="notice notice-negative">{{ error }}</p>

    <ul v-if="list.length" class="items" :class="`items-${layout || 'list'}`">
      <li v-for="(page, index) in list" :key="page.id" class="item item-selectable" v-bind="bind(index, page.status === 'listed')">
        <input v-model="selected" type="checkbox" class="item-select" :value="page.id" :title="`Select ${page.title}`">
        <RouterLink :to="`/pages/${page.id}`" class="item-link" draggable="false">
          <span class="item-icon">
            <Icon v-if="page.status === 'listed'" name="grip" class="drag-hint" />
            <PageIcon v-else :icon="page.icon" />
          </span>
          <span class="item-title">{{ page.title }}</span>
          <span class="item-info">{{ page.type }}</span>
          <span v-if="page.num" class="item-info">{{ page.num }}</span>
          <StatusDot :status="page.status" />
        </RouterLink>
      </li>
    </ul>
    <p v-else class="empty">No pages yet</p>

    <Dialog
      :open="action !== null"
      :title="action === 'delete' ? 'Delete pages' : 'Move pages'"
      :submit-label="action === 'delete' ? 'Delete' : 'Move'"
      :danger="action === 'delete'"
      :busy="busy"
      @update:open="action = $event ? action : null"
      @submit="submitAction"
    >
      <p v-if="action === 'delete'">Do you really want to delete {{ selected.length }} page(s) and all their subpages?</p>
      <label v-else-if="action === 'move'" class="field">
        <span class="field-label">Parent</span>
        <ParentSelect v-model="parent" :exclude="selected" />
        <small class="field-help">The pages keep their status, subpages and files. Their urls change.</small>
      </label>
    </Dialog>

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
        <span class="field-label">Type</span>
        <select v-model="form.type" class="input">
          <option v-for="blueprint in choices" :key="blueprint.type" :value="blueprint.type">{{ blueprint.title }}</option>
        </select>
      </label>
    </Dialog>
  </section>
</template>
