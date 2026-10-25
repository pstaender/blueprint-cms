<script setup>
import { computed } from "vue";
import PageItems from "../PageItems.vue";

// The subpages of the model, optionally filtered by `template`.
const props = defineProps({
  field: { type: Object, required: true },
  model: { type: Object, default: null },
});

const templates = computed(() => [props.field.template ?? props.field.templates ?? []].flat());
const pages = computed(() => {
  const children = props.model?.children ?? [];
  const filtered = templates.value.length ? children.filter((page) => templates.value.includes(page.template)) : children;
  const status = props.field.status;
  return status && status !== "all" ? filtered.filter((page) => page.status === status) : filtered;
});
</script>

<template>
  <PageItems
    :label="field.label"
    :pages="pages"
    :layout="field.layout"
    :parent="model"
    :templates="templates"
  />
</template>
