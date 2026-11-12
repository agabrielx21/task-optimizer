import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'

// The tested code is plain TypeScript (no Vue, no Nuxt runtime), so a plain Node environment is
// enough; only Nuxt's path aliases need to be mirrored here.
export default defineConfig({
  resolve: {
    alias: {
      '#shared': fileURLToPath(new URL('./shared', import.meta.url)),
      '~': fileURLToPath(new URL('./app', import.meta.url)),
    },
  },
  test: {
    environment: 'node',
    include: ['tests/**/*.test.ts'],
  },
})
