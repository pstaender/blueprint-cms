<script setup>
import Icon from "./Icon.vue";

// Breadcrumb, title, actions and the save buttons of a model view.
defineProps({
  title: String,
  breadcrumb: { type: Array, default: () => [] },
  isDirty: Boolean,
  saving: Boolean,
  message: { type: Object, default: null },
  // field names that someone else has changed in the meantime (409 on save)
  conflict: { type: Array, default: null },
});
defineEmits(["save", "discard", "overwrite", "reload"]);
</script>

<template>
  <header class="view-header">
    <nav class="breadcrumb">
      <RouterLink v-for="item in breadcrumb" :key="item.to" :to="item.to">{{ item.title }}</RouterLink>
    </nav>
    <div class="view-title-row">
      <h1 class="view-title">{{ title }}</h1>
      <div class="view-actions">
        <slot name="actions" />
        <template v-if="isDirty">
          <button type="button" class="button" @click="$emit('discard')">Discard</button>
          <button type="button" class="button button-primary" :disabled="saving" @click="$emit('save')"><Icon name="check" /> Save</button>
        </template>
        <span v-else-if="message" class="message" :class="`message-${message.type}`">{{ message.text }}</span>
      </div>
    </div>
    <div v-if="conflict" class="notice notice-negative conflict">
      <span>Someone else has changed {{ conflict.length ? conflict.join(", ") : "this" }} in the meantime.</span>
      <button type="button" class="button button-small" @click="$emit('reload')">Load their version</button>
      <button type="button" class="button button-small" @click="$emit('overwrite')">Overwrite with mine</button>
    </div>
    <p v-else-if="message?.type === 'negative' && isDirty" class="notice notice-negative">{{ message.text }}</p>
  </header>
</template>
