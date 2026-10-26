<script setup>
import { ref, watch } from "vue";
import { api } from "../api.js";
import Dialog from "./Dialog.vue";
import Icon from "./Icon.vue";
import PageIcon from "./PageIcon.vue";

// Selects one page: browse the pages level by level (subpages behind the arrow) or search all pages.
// Emits "select" with the page (as listed by the api: id, ref, title, url, …).
defineProps({ title: { type: String, default: "Select page" } });
const open = defineModel("open", { type: Boolean, default: false });
const emit = defineEmits(["select"]);

const trail = ref([]);
const pages = ref([]);
const search = ref("");
const chosen = ref(null);
let request = 0;

async function load() {
  const current = ++request;
  const query = search.value.trim();
  const parent = trail.value.at(-1);
  const list = query
    ? await api.get(`/pages?search=${encodeURIComponent(query)}`)
    : parent ? await api.get(`/pages/${parent.id}/~children`) : (await api.get("/site")).children;
  // a slower earlier request must not replace the result of a later one
  if (current === request) pages.value = list;
}

function browse(page) {
  trail.value = [...trail.value, page];
  search.value = "";
  load();
}

function back(length) {
  trail.value = trail.value.slice(0, length);
  load();
}

function submit() {
  if (!chosen.value) return;
  emit("select", chosen.value);
  open.value = false;
}

watch(open, (isOpen) => {
  if (!isOpen) return;
  trail.value = [];
  search.value = "";
  chosen.value = null;
  load();
});

watch(search, load);
</script>

<template>
  <Dialog v-model:open="open" :title="title" submit-label="Select" @submit="submit">
    <input v-model="search" type="search" class="input" placeholder="Search all pages" aria-label="Search all pages">
    <nav v-if="!search.trim()" class="breadcrumb select-trail">
      <a href="#" @click.prevent="back(0)">Site</a>
      <a v-for="(page, index) in trail" :key="page.id" href="#" @click.prevent="back(index + 1)">{{ page.title }}</a>
    </nav>
    <ul v-if="pages.length" class="choices select-choices">
      <li v-for="page in pages" :key="page.id" class="select-choice">
        <label class="choice">
          <input type="radio" name="page" required :checked="chosen?.id === page.id" @change="chosen = page">
          <span class="item-icon"><PageIcon :icon="page.icon" /></span>
          <span class="select-choice-title">{{ page.title }}</span>
          <small class="item-info">{{ page.id }}</small>
        </label>
        <button v-if="page.hasChildren" type="button" class="icon-button" :title="`Subpages of ${page.title}`" @click="browse(page)">
          <Icon name="angle-right" />
        </button>
      </li>
    </ul>
    <p v-else class="empty">No pages</p>
  </Dialog>
</template>
