<script setup>
import { ref, computed, watch, nextTick } from "vue";
import { icons } from "../icons.js";
import Dialog from "./Dialog.vue";
import Icon from "./Icon.vue";

// Selects one of the panel icons, emits "select" with its name; a double click selects directly.
const props = defineProps({ value: { type: String, default: null } });
const open = defineModel("open", { type: Boolean, default: false });
const emit = defineEmits(["select"]);

const names = Object.keys(icons).sort();
const search = ref("");
const chosen = ref(null);
const searchInput = ref(null);
const matches = computed(() => names.filter((name) => name.includes(search.value.trim().toLowerCase())));

function submit() {
  if (!chosen.value) return;
  emit("select", chosen.value);
  open.value = false;
}

watch(open, async (isOpen) => {
  if (!isOpen) return;
  search.value = "";
  chosen.value = Object.hasOwn(icons, props.value ?? "") ? props.value : null;
  await nextTick();
  searchInput.value?.focus();
});
</script>

<template>
  <Dialog v-model:open="open" title="Select icon" submit-label="Select" @submit="submit">
    <input ref="searchInput" v-model="search" type="search" class="input" placeholder="Search icons" aria-label="Search icons">
    <div v-if="matches.length" class="icon-choices">
      <label v-for="name in matches" :key="name" class="icon-choice" :title="name">
        <input type="radio" name="icon" :value="name" required :checked="chosen === name" @change="chosen = name">
        <span class="icon-choice-tile" @dblclick="chosen = name; submit()"><Icon :name="name" /></span>
      </label>
    </div>
    <p v-else class="empty">No matching icons</p>
    <p v-if="chosen" class="icon-choice-name">{{ chosen }}</p>
  </Dialog>
</template>
