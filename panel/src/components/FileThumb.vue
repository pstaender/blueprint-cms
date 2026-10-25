<script setup>
import Icon from "./Icon.vue";

defineProps({ file: { type: Object, required: true } });
</script>

<template>
  <span class="thumb">
    <img v-if="file.type === 'image'" :src="file.url" alt="" loading="lazy">
    <Icon v-else name="file" />
  </span>
</template>
