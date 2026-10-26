<script setup>
import { computed } from "vue";
import Icon from "./Icon.vue";
import { icons } from "../icons.js";

// A page type's icon: the name of a panel icon or any text, e.g. an emoji
const props = defineProps({ icon: { type: String, default: null } });
const isNamed = computed(() => props.icon !== null && Object.hasOwn(icons, props.icon));
</script>

<template>
  <Icon v-if="isNamed" :name="icon" />
  <span v-else-if="icon" class="text-icon">{{ icon }}</span>
</template>
