<script setup>
import { ref, computed, watch } from "vue";
import { filesOf } from "../files.js";
import Dialog from "./Dialog.vue";
import FileThumb from "./FileThumb.vue";

// Selects one file of a page, emits "select" with the file (as listed by the api: filename, ref, type, url, …).
const props = defineProps({
  pageId: { type: String, required: true },
  title: { type: String, default: "Select file" },
});
const open = defineModel("open", { type: Boolean, default: false });
const emit = defineEmits(["select"]);

const store = filesOf(props.pageId);
const search = ref("");
const chosen = ref(null);
const files = computed(() => store.list.filter((file) => file.filename.toLowerCase().includes(search.value.trim().toLowerCase())));

function submit() {
  if (!chosen.value) return;
  emit("select", chosen.value);
  open.value = false;
}

watch(open, (isOpen) => {
  if (!isOpen) return;
  search.value = "";
  chosen.value = null;
});
</script>

<template>
  <Dialog v-model:open="open" :title="title" submit-label="Select" @submit="submit">
    <input v-if="store.list.length > 8" v-model="search" type="search" class="input" placeholder="Search files" aria-label="Search files">
    <ul v-if="files.length" class="choices select-choices">
      <li v-for="file in files" :key="file.filename" class="select-choice">
        <label class="choice">
          <input type="radio" name="file" required :checked="chosen?.filename === file.filename" @change="chosen = file">
          <FileThumb :file="file" />
          <span class="select-choice-title">{{ file.filename }}</span>
        </label>
      </li>
    </ul>
    <p v-else class="empty">{{ store.list.length ? "No matching files" : "This page has no files yet" }}</p>
  </Dialog>
</template>
