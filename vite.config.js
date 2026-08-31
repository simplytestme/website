import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

const theme = "web/themes/simplytest_theme";

// The bundle is loaded as a plain script by Drupal libraries
// (simplytest_theme.libraries.yml), so it builds as a self-contained IIFE:
// dist/app.js, dist/app.css, and the font files it references.
export default defineConfig({
  // Assets are referenced relative to the emitted CSS/JS inside dist/, not
  // from the site root.
  base: "",
  plugins: [react()],
  build: {
    outDir: `${theme}/dist`,
    emptyOutDir: true,
    sourcemap: true,
    // One stylesheet for the whole site; Drupal attaches it as the theme's
    // global library rather than the JS injecting it.
    cssCodeSplit: false,
    rollupOptions: {
      input: `${theme}/lib/app.jsx`,
      output: {
        format: "iife",
        entryFileNames: "app.js",
        assetFileNames: "[name][extname]"
      }
    }
  }
});
