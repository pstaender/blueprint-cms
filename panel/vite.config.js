import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";

// `npm run dev` serves the panel on http://localhost:5173/panel/ and proxies the API
// to the php dev server (`composer serve`). `npm run build` writes to public/panel/.
export default defineConfig({
  plugins: [vue()],
  base: "/panel/",
  build: {
    outDir: "../public/panel",
    emptyOutDir: true,
  },
  server: {
    proxy: {
      "/panel/api": "http://localhost:8000",
    },
  },
});
