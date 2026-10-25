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
  chevron: "M9 6l6 6-6 6",
  grip: "M9 6h.01M9 12h.01M9 18h.01M15 6h.01M15 12h.01M15 18h.01",
  eye: "M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 15a3 3 0 100-6 3 3 0 000 6z",
  hidden: "M3 3l18 18M10.6 5.1A10 10 0 0112 5c6 0 10 7 10 7a17 17 0 01-3 3.8M6.6 6.6A17 17 0 002 12s4 7 10 7a10 10 0 005.4-1.6M9.9 9.9a3 3 0 004.2 4.2",
  copy: "M8 8h12v12H8zM16 8V4H4v12h4",
  upload: "M12 16V4M7 9l5-5 5 5M4 16v4h16v-4",
  file: "M6 3h8l4 4v14H6zM14 3v4h4M9 13h6M9 17h6",
  image: "M4 5h16v14H4zM4 16l5-5 4 4 3-3 4 4M15 9h.01",
  settings: "M4 7h10M18 7h2M4 17h2M10 17h10M14 5v4M6 15v4",
  blocks: "M4 4h16v6H4zM4 14h16v6H4z",
  more: "M5 12h.01M12 12h.01M19 12h.01",
  edit: "M4 20h4L19 9l-4-4L4 16zM13 7l4 4",
  sort: "M8 4v16M4 8l4-4 4 4M16 20V4M12 16l4 4 4-4",
  replace: "M4 12a8 8 0 0114-5.3L20 9M20 4v5h-5M20 12a8 8 0 01-14 5.3L4 15M4 20v-5h5",
};

defineProps({ name: { type: String, required: true } });
</script>

<template>
  <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
    <path :d="paths[name] ?? paths.page" />
  </svg>
</template>
