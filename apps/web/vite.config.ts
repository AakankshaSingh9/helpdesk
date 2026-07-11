import path from 'node:path'
// defineConfig from vitest/config extends Vite's with the `test` block below;
// the Vite dev server ignores `test`, so this stays a single source of config.
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  test: {
    // Component tests need a DOM; jsdom provides one.
    environment: 'jsdom',
    // Expose describe/it/expect/vi globally and enable Testing Library's
    // automatic DOM cleanup between tests.
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    // Tailwind/PostCSS output is irrelevant to behaviour tests — skip it.
    css: false,
  },
})
