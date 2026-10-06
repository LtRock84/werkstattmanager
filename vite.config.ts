import { defineConfig, configDefaults } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { runtimeNotices } from './scripts/runtime-notices'
export default defineConfig(({ command }) => ({
  // Library mode preserves process.env by default; the deployed IIFE runs in a browser.
  define: command === 'build' ? {
    'process.env.NODE_ENV': JSON.stringify('production'),
    'process.env.NODE_DEBUG': JSON.stringify(''),
  } : {},
  plugins: [vue(), runtimeNotices()],
  build: {
    outDir: 'js',
    emptyOutDir: true,
    cssCodeSplit: false,
    lib: {
      entry: 'src/main.ts',
      name: 'Werkstattmanager',
      formats: ['iife'],
      fileName: () => 'werkstattmanager-main.js',
    },
    rollupOptions: { output: { assetFileNames: 'werkstattmanager-[name][extname]' } },
  },
  test: { environment: 'jsdom', include: ['tests/frontend/**/*.test.ts'], exclude: [...configDefaults.exclude, '**/._*'] },
}))
