<script setup>
import { ref, nextTick, onBeforeUnmount } from "vue";
import Icon from "./Icon.vue";

/**
 * A menu at the mouse position (right click) or below a button: `menu.value.show(event)`.
 * Items: `{label, icon?, href?, danger?, disabled?, action?}`, `"-"` is a separator.
 */
const props = defineProps({ items: { type: Array, required: true } });
const open = ref(false);
const position = ref({ left: 0, top: 0 });
const menu = ref(null);

async function show(event) {
  const anchor = event.type === "contextmenu" ? null : event.currentTarget.getBoundingClientRect();
  position.value = anchor ? { left: anchor.right, top: anchor.bottom + 4 } : { left: event.clientX, top: event.clientY };
  open.value = true;
  document.addEventListener("pointerdown", closeOutside, true);
  window.addEventListener("blur", close);
  window.addEventListener("resize", close);
  window.addEventListener("wheel", closeOutside, true);
  await nextTick();

  // keep it inside the window, below a button it is right aligned
  const rect = menu.value.getBoundingClientRect();
  const left = anchor ? position.value.left - rect.width : position.value.left;
  position.value = {
    left: Math.max(8, Math.min(left, window.innerWidth - rect.width - 8)),
    top: Math.max(8, Math.min(position.value.top, window.innerHeight - rect.height - 8)),
  };
  menu.value.querySelector("[role=menuitem]:not([aria-disabled=true])")?.focus({ preventScroll: true });
}

function close() {
  open.value = false;
  document.removeEventListener("pointerdown", closeOutside, true);
  window.removeEventListener("blur", close);
  window.removeEventListener("resize", close);
  window.removeEventListener("wheel", closeOutside, true);
}

function closeOutside(event) {
  if (!menu.value?.contains(event.target)) close();
}

function select(item) {
  if (item.disabled) return;
  close();
  item.action?.();
}

function keydown(event) {
  const entries = [...menu.value.querySelectorAll("[role=menuitem]:not([aria-disabled=true])")];
  const index = entries.indexOf(document.activeElement);
  if (event.key === "Escape" || event.key === "Tab") {
    event.preventDefault();
    close();
  } else if (event.key === "ArrowDown" || event.key === "ArrowUp") {
    event.preventDefault();
    const step = event.key === "ArrowDown" ? 1 : -1;
    entries[(index + step + entries.length) % entries.length]?.focus({ preventScroll: true });
  }
}

onBeforeUnmount(close);
defineExpose({ show, close });
</script>

<template>
  <Teleport to="body">
    <ul v-if="open" ref="menu" class="menu" role="menu" :style="{ left: `${position.left}px`, top: `${position.top}px` }" @keydown="keydown" @contextmenu.prevent>
      <template v-for="(item, index) in props.items" :key="index">
        <li v-if="item === '-'" class="menu-separator" role="separator"></li>
        <li v-else role="none">
          <a
            v-if="item.href"
            class="menu-item"
            role="menuitem"
            :href="item.href"
            target="_blank"
            @click="close"
          ><Icon v-if="item.icon" :name="item.icon" /><span v-else class="icon"></span> {{ item.label }}</a>
          <button
            v-else
            type="button"
            class="menu-item"
            :class="{ 'is-danger': item.danger }"
            role="menuitem"
            :aria-disabled="item.disabled ? 'true' : undefined"
            @click="select(item)"
          ><Icon v-if="item.icon" :name="item.icon" /><span v-else class="icon"></span> {{ item.label }}</button>
        </li>
      </template>
    </ul>
  </Teleport>
</template>
