<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { api, session } from "../api.js";
import { useSortable } from "../sortable.js";
import Icon from "./Icon.vue";
import PageIcon from "./PageIcon.vue";

// The page tree of the sidebar: the home page first, listed pages (sortable), then unlisted pages and drafts in their
// own groups.
// Pages are expandable, the state is remembered in localStorage.
const props = defineProps({
  parentId: { type: String, default: null },
  depth: { type: Number, default: 0 },
});

const STORAGE_KEY = "blueprint.panel.expanded";
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
  { status: "home", label: null },
  { status: "listed", label: null },
  { status: "unlisted", label: "Unlisted" },
  { status: "draft", label: "Drafts" },
]
  .map((group) => ({ ...group, pages: pages.value.filter((page) => (page.isHome ? "home" : page.status) === group.status) }))
  .filter((group) => group.pages.length));

const { bind } = useSortable(async (from, to) => {
  const listed = pages.value.filter((page) => page.status === "listed" && !page.isHome);
  await api.patch(`/pages/${listed[from].id}`, { num: listed[to].num });
});

onMounted(load);
watch(() => session.changes, load);
</script>

<template>
  <div class="tree" :class="{ 'tree-nested': depth > 0 }">
    <template v-for="group in groups" :key="group.status">
      <h4 v-if="group.label" class="tree-group" :style="{ paddingLeft: `${depth * 14 + 24}px` }">{{ group.label }}</h4>
      <ul class="tree-list">
        <li v-for="(page, index) in group.pages" :key="page.id" class="tree-item" v-bind="bind(index, group.status === 'listed')">
          <div class="tree-row" :style="{ paddingLeft: `${depth * 14}px` }">
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
          </div>
          <PageTree v-if="page.hasChildren && expanded.includes(page.id)" :parent-id="page.id" :depth="depth + 1" />
        </li>
      </ul>
    </template>
  </div>
</template>
