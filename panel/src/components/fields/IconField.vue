<script setup>
import { ref, computed } from "vue";
import Icon from "../Icon.vue";
import PageIcon from "../PageIcon.vue";
import { icons } from "../../icons.js";

// An icon: a panel icon name or emoji (typed, names are suggested), or an uploaded svg stored inline as data uri.
const props = defineProps({
  id: { type: String, required: true },
  modelValue: { default: null },
  field: { type: Object, required: true },
});
const emit = defineEmits(["update:modelValue"]);

const MAX_SIZE = 50 * 1024;
const names = Object.keys(icons).sort();
const error = ref(null);
const isImage = computed(() => String(props.modelValue ?? "").startsWith("data:"));

function update(value) {
  error.value = null;
  emit("update:modelValue", value ? value : null);
}

function upload(event) {
  const file = event.target.files[0];
  event.target.value = "";
  if (!file) return;
  if (file.type !== "image/svg+xml" && !file.name.toLowerCase().endsWith(".svg")) {
    error.value = "Only svg files can be uploaded";
  } else if (file.size > MAX_SIZE) {
    error.value = "The svg must be smaller than 50 KB";
  } else {
    const reader = new FileReader();
    reader.onload = () => update(reader.result.replace(/^data:[^,]*;base64,/, "data:image/svg+xml;base64,"));
    reader.readAsDataURL(file);
  }
}
</script>

<template>
  <div class="icon-field">
    <span class="icon-field-preview"><PageIcon :icon="modelValue" /></span>
    <span v-if="isImage" class="icon-field-label">Uploaded svg</span>
    <template v-else>
      <input :id="id" class="input" type="text" :list="`${id}-icons`" :value="modelValue ?? ''" :placeholder="field.placeholder ?? 'Icon name or emoji'"
        :disabled="field.disabled" autocomplete="off" @change="update($event.target.value.trim())">
      <datalist :id="`${id}-icons`">
        <option v-for="name in names" :key="name" :value="name"></option>
      </datalist>
    </template>
    <template v-if="!field.disabled">
      <label class="button button-small">
        <Icon name="upload" /> Svg
        <input type="file" accept=".svg,image/svg+xml" hidden @change="upload">
      </label>
      <button v-if="modelValue" type="button" class="icon-button" title="Remove" @click="update(null)"><Icon name="close" /></button>
    </template>
  </div>
  <p v-if="error" class="notice notice-negative">{{ error }}</p>
</template>
