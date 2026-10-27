<script setup>
import { computed } from "vue";
import Icon from "./Icon.vue";

// Previous/next arrows, only shown if there is more than one page. `page` starts at 1.
const props = defineProps({
  page: { type: Number, required: true },
  total: { type: Number, required: true },
  perPage: { type: Number, required: true },
});
const emit = defineEmits(["update:page"]);
const pages = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)));
</script>

<template>
  <nav v-if="pages > 1" class="pagination" aria-label="Pages">
    <button type="button" class="icon-button" title="Previous page" :disabled="page <= 1" @click="emit('update:page', page - 1)"><Icon name="angle-left" /></button>
    <span class="pagination-info">{{ page }} / {{ pages }}</span>
    <button type="button" class="icon-button" title="Next page" :disabled="page >= pages" @click="emit('update:page', page + 1)"><Icon name="angle-right" /></button>
  </nav>
</template>
