<script setup>
import { icons } from "../icons.js";

defineProps({ name: { type: String, required: true } });
</script>

<template>
  <svg class="icon" :class="`icon-${name}`" viewBox="0 0 24 24" aria-hidden="true">
    <path :d="icons[name] ?? icons.page" />
  </svg>
</template>
