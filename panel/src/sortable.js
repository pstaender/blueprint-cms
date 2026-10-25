import { ref } from "vue";

/**
 * Native drag & drop sorting. `bind(index)` returns the attributes for an item, `handle(index)` for its
 * drag handle (with `useHandle`, items are only draggable from their handle, so inputs stay selectable).
 * Nested lists work independently, every list only reacts to its own items.
 */
export function useSortable(onMove, { useHandle = false } = {}) {
  const dragging = ref(null);
  const over = ref(null);
  const armed = ref(null);

  function reset() {
    dragging.value = null;
    over.value = null;
    armed.value = null;
  }

  function bind(index, enabled = true) {
    if (!enabled) return {};

    return {
      draggable: useHandle ? String(armed.value === index) : "true",
      class: {
        "is-dragging": dragging.value === index,
        "is-drop-target": over.value === index && dragging.value !== null && dragging.value !== index,
      },
      onDragstart(event) {
        if (useHandle && armed.value !== index) return;
        event.stopPropagation();
        dragging.value = index;
        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.setData("text/plain", String(index));
      },
      onDragover(event) {
        if (dragging.value === null) return;
        event.preventDefault();
        event.stopPropagation();
        over.value = index;
      },
      onDrop(event) {
        if (dragging.value === null) return;
        event.preventDefault();
        event.stopPropagation();
        const from = dragging.value;
        reset();
        if (from !== index) onMove(from, index);
      },
      onDragend: reset,
    };
  }

  function handle(index) {
    return {
      onMousedown: () => (armed.value = index),
      onMouseup: () => (armed.value = null),
    };
  }

  return { bind, handle };
}

// moves an entry of an array copy
export function move(list, from, to) {
  const copy = [...list];
  copy.splice(to, 0, ...copy.splice(from, 1));
  return copy;
}
