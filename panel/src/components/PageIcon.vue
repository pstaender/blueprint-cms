<script setup>
import { computed } from "vue";
import Icon from "./Icon.vue";
import { icons } from "../icons.js";

// A blueprint's icon: the name of a panel icon, an image as data URI (`data:image/svg+xml;base64,…`, drawn in the
// text color as a CSS mask) or any other text, e.g. an emoji
const props = defineProps({ icon: { type: String, default: null } });
const kind = computed(() => {
  if (!props.icon) return null;
  if (Object.hasOwn(icons, props.icon)) return "name";
  if (/^data:image\/[a-z0-9.+-]+[;,]/i.test(props.icon)) return "image";
  return props.icon.startsWith("data:") ? null : "text";
});
// quotes and line breaks would end the css url()
const mask = computed(() => `url("${props.icon.replace(/["\\\n\r]/g, (char) => encodeURIComponent(char))}")`);
</script>

<template>
  <Icon v-if="kind === 'name'" :name="icon" />
  <span v-else-if="kind === 'image'" class="icon image-icon" :style="{ maskImage: mask, webkitMaskImage: mask }"></span>
  <span v-else-if="kind === 'text'" class="text-icon">{{ icon }}</span>
</template>
