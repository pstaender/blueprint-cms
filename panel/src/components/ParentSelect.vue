<script setup>
import { ref, computed, onMounted } from "vue";
import { api } from "../api.js";

// Native select of the pages that pages can be moved into: the site (value "") and every folder page,
// except the moved pages (`exclude`, ids) and their subpages
const props = defineProps({ exclude: { type: Array, required: true } });
const model = defineModel({ type: String, default: "" });
const pages = ref([]);

const options = computed(() => pages.value
  .filter((page) => page.canHaveChildren && !props.exclude.some((id) => page.id === id || page.id.startsWith(`${id}/`)))
  .map((page) => ({ id: page.id, label: `${"  ".repeat(page.id.split("/").length)}${page.title}` })));

onMounted(async () => (pages.value = await api.get("/pages")));
</script>

<template>
  <select v-model="model" class="input">
    <option value="">Site (top level)</option>
    <option v-for="option in options" :key="option.id" :value="option.id">{{ option.label }}</option>
  </select>
</template>
