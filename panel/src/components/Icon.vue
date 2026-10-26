<script setup>
import { computed } from "vue";
import { icons } from "../icons.js";

const props = defineProps({ name: { type: String, required: true } });
const icon = computed(() => {
  const value = Object.hasOwn(icons, props.name) ? icons[props.name] : icons.page;
  return typeof value === "string" ? { d: value } : value;
});
</script>

<template>
  <svg class="icon" :class="`icon-${name}`" viewBox="0 0 24 24" aria-hidden="true">
    <path v-if="icon.fill" class="icon-fill" :d="icon.fill" />
    <path v-if="icon.d" :d="icon.d" />
  </svg>
</template>
