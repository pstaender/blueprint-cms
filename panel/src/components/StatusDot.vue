<script setup>
defineProps({ status: { type: String, default: "unlisted" } });
</script>

<template>
  <span class="status-dot" :class="`status-${status}`" :title="status"></span>
</template>
