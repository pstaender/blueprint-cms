import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import { clone } from "./fields.js";

/**
 * Shared edit state of a model view: the values, unsaved changes, validation errors and saving (⌘/Ctrl + S).
 * `save` gets the request body: only the changed fields (cleared ones as null) as `data`, so saving doesn't
 * overwrite fields that someone else changed in the meantime, plus `modifiedAt` and the `original` values for the
 * conflict check. If someone else changed the same fields, `conflict` lists them: overwrite() saves anyway,
 * `reload` (from the view) loads their version.
 */
export function useEditing(save, reload) {
  const values = ref({});
  const original = ref("{}");
  const modifiedAt = ref(null);
  const conflict = ref(null);
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

  // `version` is the model's modifiedAt when it was loaded
  function reset(data, version = null) {
    values.value = clone(data ?? {});
    original.value = JSON.stringify(values.value);
    modifiedAt.value = version;
    errors.value = {};
    conflict.value = null;
  }

  async function submit(overwrite = false) {
    if (!isDirty.value || saving.value) return;
    saving.value = true;
    message.value = null;
    try {
      const data = changes();
      const before = JSON.parse(original.value);
      const originalValues = Object.fromEntries(Object.keys(data).map((name) => [name, before[name] ?? null]));
      await save(overwrite || !modifiedAt.value ? { data } : { data, original: originalValues, modifiedAt: modifiedAt.value });
      message.value = { type: "positive", text: "Saved" };
    } catch (e) {
      errors.value = e.errors ?? {};
      conflict.value = e.status === 409 ? (e.body?.conflicts ?? []) : null;
      message.value = { type: "negative", text: e.message };
    } finally {
      saving.value = false;
    }
  }

  const overwrite = () => submit(true);

  async function loadLatest() {
    await reload();
    message.value = null;
  }

  function onKeydown(event) {
    if ((event.metaKey || event.ctrlKey) && event.key === "s") {
      event.preventDefault();
      submit(false);
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

  return { values, errors, message, saving, isDirty, conflict, reset, submit: () => submit(false), overwrite, loadLatest };
}
