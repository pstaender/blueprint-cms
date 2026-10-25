<script setup>
// A small set of line icons (24×24, stroke based)
const paths = {
  site: "M3 11l9-7 9 7M5 10v10h14V10",
  page: "M6 3h8l4 4v14H6zM14 3v4h4",
  users: "M9 11a4 4 0 100-8 4 4 0 000 8zM2 21v-1a7 7 0 0114 0v1M16 3.5a4 4 0 010 7.5M18 14a6 6 0 014 6v1",
  user: "M12 12a4 4 0 100-8 4 4 0 000 8zM4 21v-1a8 8 0 0116 0v1",
  plus: "M12 5v14M5 12h14",
  open: "M14 4h6v6M20 4l-9 9M18 14v6H4V6h6",
  trash: "M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13",
  logout: "M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10",
  up: "M12 19V5M6 11l6-6 6 6",
  down: "M12 5v14M6 13l6 6 6-6",
  close: "M6 6l12 12M18 6L6 18",
  check: "M5 12l5 5 9-10",
};

defineProps({ name: { type: String, required: true } });
</script>

<template>
  <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
    <path :d="paths[name] ?? paths.page" />
  </svg>
</template>
