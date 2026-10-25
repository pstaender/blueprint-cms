<script setup>
import { ref, computed, onMounted } from "vue";
import { api } from "../api.js";

// The role as select (roles = user blueprints), just the role's name if there is no other role to choose.
const props = defineProps({ modelValue: { type: String, default: "user" } });
const emit = defineEmits(["update:modelValue"]);

const roles = ref([]);

// the current role stays selectable, even if its blueprint was removed
const options = computed(() =>
  roles.value.some((role) => role.name === props.modelValue) || !props.modelValue
    ? roles.value
    : [...roles.value, { name: props.modelValue, title: props.modelValue }],
);
const current = computed(() => options.value.find((role) => role.name === props.modelValue));

onMounted(async () => {
  roles.value = await api.get("/roles");
});
</script>

<template>
  <div class="field">
    <span class="field-label">Role</span>
    <select v-if="options.length > 1" class="input" :value="modelValue" aria-label="Role" @change="emit('update:modelValue', $event.target.value)">
      <option v-for="role in options" :key="role.name" :value="role.name">{{ role.title }}</option>
    </select>
    <p v-else-if="roles.length" class="field-value">{{ current?.title ?? modelValue }}</p>
  </div>
</template>
