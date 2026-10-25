<script setup>
import { ref, watch, onMounted } from "vue";

// A native <dialog>; v-model:open controls it, submitting the form emits "submit".
const props = defineProps({
  open: Boolean,
  title: String,
  submitLabel: { type: String, default: "Save" },
  danger: Boolean,
  busy: Boolean,
});
const emit = defineEmits(["update:open", "submit"]);
const dialog = ref(null);

function sync() {
  if (!dialog.value) return;
  if (props.open && !dialog.value.open) dialog.value.showModal();
  if (!props.open && dialog.value.open) dialog.value.close();
}

watch(() => props.open, sync);
onMounted(sync);
</script>

<template>
  <dialog ref="dialog" class="dialog" @close="emit('update:open', false)">
    <form method="dialog" @submit.prevent="emit('submit')">
      <h2 v-if="title" class="dialog-title">{{ title }}</h2>
      <div class="dialog-body"><slot /></div>
      <footer class="dialog-footer">
        <button type="button" class="button" @click="emit('update:open', false)">Cancel</button>
        <button type="submit" class="button" :class="danger ? 'button-danger' : 'button-primary'" :disabled="busy">
          {{ submitLabel }}
        </button>
      </footer>
    </form>
  </dialog>
</template>
