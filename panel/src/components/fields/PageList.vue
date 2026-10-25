<script setup>
import { computed } from "vue";
import PageItems from "../PageItems.vue";

// The subpages of the model, optionally filtered by page type (`template: article` or `template: [a, b]`).
const props = defineProps({
  field: { type: Object, required: true },
  model: { type: Object, default: null },
});
const emit = defineEmits(["reload"]);

const types = computed(() => [props.field.template ?? props.field.templates ?? []].flat());
const pages = computed(() => {
  const children = props.model?.children ?? [];
  const filtered = types.value.length ? children.filter((page) => types.value.includes(page.type)) : children;
  const status = props.field.status;
  return status && status !== "all" ? filtered.filter((page) => page.status === status) : filtered;
});
</script>

<template>
  <PageItems :label="field.label" :pages="pages" :layout="field.layout" :parent="model" :types="types" @sorted="model?.reload?.()" />
</template>
