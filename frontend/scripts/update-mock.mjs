// Regenerates server/mock/mock-results.json from a running backend, so the mock always has the
// real response shape (the Vitest contract test then checks it against the TypeScript types).
//
//   node scripts/update-mock.mjs                      # backend at http://127.0.0.1:8000
//   node scripts/update-mock.mjs http://localhost:8080

import { writeFile } from 'node:fs/promises'

const backendUrl = (process.argv[2] ?? process.env.NUXT_BACKEND_URL ?? 'http://127.0.0.1:8000').replace(/\/+$/, '')
const results = {}

for (const variant of ['cost', 'makespan']) {
  // Empty body -> the backend uses its bundled data/*.json (same files as shared/data/).
  const response = await fetch(`${backendUrl}/api/optimize/${variant}`, { method: 'POST' })
  if (!response.ok) {
    throw new Error(`${variant}: HTTP ${response.status}`)
  }
  results[variant] = await response.json()
}

const target = new URL('../server/mock/mock-results.json', import.meta.url)
await writeFile(target, JSON.stringify(results, null, 2) + '\n')
console.log(`Wrote ${target.pathname} from ${backendUrl}`)
