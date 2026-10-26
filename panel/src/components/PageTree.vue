<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, session } from "../api.js";
import { useSortable } from "../sortable.js";
import Icon from "./Icon.vue";
import PageIcon from "./PageIcon.vue";
import ContextMenu from "./ContextMenu.vue";
import Dialog from "./Dialog.vue";
import ParentSelect from "./ParentSelect.vue";

// The page tree of the sidebar: listed pages (sortable), then unlisted pages and drafts in their own groups.
// Pages are expandable, the state is remembered in localStorage. Right click a page to move it or change its status.
const props = defineProps({
  parentId: { type: String, default: null },
  depth: { type: Number, default: 0 },
});

const STORAGE_KEY = "blueprint.panel.expanded";
const STATUS_LABELS = { draft: "Draft", unlisted: "Unlisted", listed: "Listed" };
const route = useRoute();
const router = useRouter();
const pages = ref([]);
const expanded = ref(readExpanded());

function readExpanded() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? "[]");
  } catch {
    return [];
  }
}

function toggle(id) {
  const current = readExpanded();
  const next = current.includes(id) ? current.filter((item) => item !== id) : [...current, id];
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
  } catch {
    // the tree still works without storage
  }
  expanded.value = next;
}

async function load() {
  pages.value = props.parentId ? await api.get(`/pages/${props.parentId}/children`) : (await api.get("/site")).children;
}

const groups = computed(() => [
  { status: "listed", label: null },
  { status: "unlisted", label: "Unlisted" },
  { status: "draft", label: "Drafts" },
]
  .map((group) => ({ ...group, pages: pages.value.filter((page) => page.status === group.status) }))
  .filter((group) => group.pages.length));

const { bind } = useSortable(async (from, to) => {
  const listed = pages.value.filter((page) => page.status === "listed");
  await api.patch(`/pages/${listed[from].id}`, { num: listed[to].num });
});

// context menu and move dialog for the page at `current`
const menu = ref(null);
const current = ref(null);
const moving = ref(false);
const parent = ref("");
const busy = ref(false);
const error = ref(null);

const menuItems = computed(() => current.value ? [
  { label: "Move to …", icon: "sort", action: openMove, disabled: current.value.isHome },
  "-",
  ...current.value.statuses.map((status) => ({
    label: STATUS_LABELS[status],
    icon: status === current.value.status ? "check" : null,
    disabled: status === current.value.status || (status === "draft" && current.value.isHome),
    action: () => change({ status }),
  })),
] : []);

function showMenu(event, page) {
  current.value = page;
  menu.value.show(event);
}

function openMove() {
  parent.value = props.parentId ?? "";
  error.value = null;
  moving.value = true;
}

async function change(input) {
  busy.value = true;
  error.value = null;
  try {
    const page = current.value;
    const updated = await api.patch(`/pages/${page.id}`, input);
    moving.value = false;
    // follow the open page (or one of its subpages, files) to its new id
    const path = route.path;
    if (updated.id !== page.id && (path === `/pages/${page.id}` || path.startsWith(`/pages/${page.id}/`))) {
      router.replace(`/pages/${updated.id}${path.slice(`/pages/${page.id}`.length)}`);
    }
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = false;
  }
}

onMounted(load);
watch(() => session.changes, load);
</script>

<template>
  <div class="tree" :class="{ 'tree-nested': depth > 0 }">
    <template v-for="group in groups" :key="group.status">
      <h4 v-if="group.label" class="tree-group" :style="{ paddingLeft: `${depth * 14 + 24}px` }">{{ group.label }}</h4>
      <ul class="tree-list">
        <li v-for="(page, index) in group.pages" :key="page.id" class="tree-item" v-bind="bind(index, group.status === 'listed')">
          <div class="tree-row" :style="{ paddingLeft: `${depth * 14}px` }" @contextmenu.prevent.stop="showMenu($event, page)">
            <button
              v-if="page.hasChildren"
              type="button"
              class="tree-toggle"
              :aria-expanded="expanded.includes(page.id)"
              :title="expanded.includes(page.id) ? 'Collapse' : 'Expand'"
              @click="toggle(page.id)"
            >
              <Icon name="chevron" :class="{ 'icon-open': expanded.includes(page.id) }" />
            </button>
            <span v-else class="tree-toggle"></span>
            <RouterLink :to="`/pages/${page.id}`" class="sidebar-link tree-link" draggable="false">
              <PageIcon :icon="page.icon" />
              <span class="tree-title">{{ page.title }}</span>
            </RouterLink>
            <button type="button" class="icon-button tree-options" title="Options" aria-haspopup="menu" @click="showMenu($event, page)">
              <Icon name="more" />
            </button>
          </div>
          <PageTree v-if="page.hasChildren && expanded.includes(page.id)" :parent-id="page.id" :depth="depth + 1" />
        </li>
      </ul>
    </template>

    <ContextMenu ref="menu" :items="menuItems" />
    <Dialog v-model:open="moving" :title="`Move ${current?.title ?? ''}`" submit-label="Move" :busy="busy" @submit="change({ parent })">
      <p v-if="error" class="notice notice-negative">{{ error }}</p>
      <label v-if="moving" class="field">
        <span class="field-label">Parent</span>
        <ParentSelect v-model="parent" :exclude="[current.id]" />
        <small class="field-help">The page keeps its status, subpages and files. Its url changes.</small>
      </label>
    </Dialog>
    <p v-if="error && !moving" class="notice notice-negative tree-error">{{ error }}</p>
  </div>
</template>
