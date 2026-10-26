import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import { clone } from "./fields.js";

/**
 * Shared edit state of a model view: the values, unsaved changes, validation errors and saving (⌘/Ctrl + S).
 * `save` only gets the changed fields (cleared ones as null), so saving doesn't overwrite fields that someone
 * else changed in the meantime.
 */
export function useEditing(save) {
  const values = ref({});
  const original = ref("{}");
  const errors = ref({});
  const message = ref(null);
  const saving = ref(false);

  const isDirty = computed(() => JSON.stringify(values.value) !== original.value);

  function changes() {
    const before = JSON.parse(original.value);
    const changed = {};
    for (const name of new Set([...Object.keys(before), ...Object.keys(values.value)])) {
      const value = values.value[name] ?? null;
      if (JSON.stringify(value) !== JSON.stringify(before[name] ?? null)) changed[name] = value;
    }
    return changed;
  }

  function reset(data) {
    values.value = clone(data ?? {});
    original.value = JSON.stringify(values.value);
    errors.value = {};
  }

  async function submit() {
    if (!isDirty.value || saving.value) return;
    saving.value = true;
    message.value = null;
    try {
      await save(changes());
      message.value = { type: "positive", text: "Saved" };
    } catch (e) {
      errors.value = e.errors ?? {};
      message.value = { type: "negative", text: e.message };
    } finally {
      saving.value = false;
    }
  }

  function onKeydown(event) {
    if ((event.metaKey || event.ctrlKey) && event.key === "s") {
      event.preventDefault();
      submit();
    }
  }

  function onBeforeUnload(event) {
    if (isDirty.value) event.preventDefault();
  }

  onMounted(() => {
    window.addEventListener("keydown", onKeydown);
    window.addEventListener("beforeunload", onBeforeUnload);
  });
  onBeforeUnmount(() => {
    window.removeEventListener("keydown", onKeydown);
    window.removeEventListener("beforeunload", onBeforeUnload);
  });

  return { values, errors, message, saving, isDirty, reset, submit };
}
