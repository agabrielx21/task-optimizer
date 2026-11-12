# Task Assignment Optimizer — frontend

Nuxt 4 dashboard that shows and compares both optimization variants (Min Cost, Min Makespan)
from the Symfony backend. Tailwind CSS only, no UI or chart libraries.

## Run

Together with the backend, from the project root:

```cmd
docker compose up --build
```

Frontend alone in **mock mode** (no backend needed), from `frontend/`:

```cmd
docker build -t task-optimizer-frontend .
docker run --rm -p 3000:3000 -e NUXT_USE_MOCK=true task-optimizer-frontend
```

Tests (Vitest), from the project root (runs in the image's build stage, which has the dev dependencies):

```cmd
docker compose run --rm frontend-tests
```

### Configuration (runtimeConfig, server-only)

| Env variable | Default | Meaning |
|---|---|---|
| `NUXT_BACKEND_URL` | `http://127.0.0.1:8000` | where the Nuxt server reaches the PHP API (`http://backend:8000` in docker-compose) |
| `NUXT_USE_MOCK` | `false` | `true` → serve `server/mock/mock-results.json`, never call the backend |

## Architecture

```
Browser ──POST /api/optimize──▶ Nuxt server route ──POST /api/optimize/cost──────▶ Symfony
        ◀── {source, input,      (server/api/        ──POST /api/optimize/makespan──▶
             results{cost,         optimize.post.ts)     (in parallel)
             makespan}} ─────                         or mock-results.json (mock mode)
```

- **The browser never calls PHP.** `runtimeConfig.backendUrl` is not under `public`, so the
  URL is not even shipped to the browser. The backend needs no CORS.
- **One request returns both variants.** The tabs and the comparison then switch locally
  without new requests; the server route calls the two backend endpoints in parallel.
- **The response includes the input** the results belong to. The table joins results with
  that input (employee skill, rate, task hours), so a result is never joined with other data.
- **Default data comes from the backend** (`GET /api/data`), so "Reset" and the first load show
  the backend's `data/*.json`. **Save as default data** sends `PUT /api/data`; the backend validates
  and overwrites the files. The bundled `shared/data/` copy is used only in mock mode.
- **Errors** are mapped in the server routes (`server/utils/backend.ts`): backend 400 → 400 with the validation details;
  no connection / timeout → 502 "Backend unreachable"; response not matching the contract → 502.

```
app/
  pages/index.vue            state (URL variant, input), useFetch, layout of the states
  composables/useDashboard   all derived data as computed (join, filter, totals, bars, lanes, comparison)
  utils/dashboard.ts         the pure functions behind it (tested with Vitest)
  utils/errors.ts            error payload → panel text
  utils/inputEditor.ts       JSON text → input (client-side syntax check)
  components/                VariantTabs, SummaryCards, AssignmentsTable, WorkloadChart,
                             GanttTimeline, ComparisonPanel, InfeasibleState, InputEditor,
                             ErrorState, LoadingState
server/
  api/optimize.post.ts       both variants (parallel) or mock results
  api/data.get.ts            default data from the backend (bundled copy in mock mode)
  api/data.put.ts            save default data (disabled in mock mode)
  utils/backend.ts           backend URL, mock flag, error mapping shared by the routes
  mock/mock-results.json     real backend output for the default data
shared/                      used by app and server
  types/api.ts               API contract types (mirror of the PHP OptimizationResult)
  utils/contract.ts          runtime check of a backend response against the contract
  data/                      bundled copy of the default data, used only in mock mode
tests/                       Vitest
```

## How each requirement is met

| Requirement | Where |
|---|---|
| Configurable backend URL, mock mode | `nuxt.config.ts` runtimeConfig, `server/api/optimize.post.ts` |
| useFetch, loading and error states, backend unreachable | `pages/index.vue` (`useFetch`), `LoadingState`, `ErrorState` |
| Variant switcher | `VariantTabs` (Min Cost / Min Makespan / Compare) |
| Summary cards | `SummaryCards`: total price, makespan, tasks, employees with work (+ optimality note) |
| Assignments table + employee filter | `AssignmentsTable` (filtered rows and totals come from the composable) |
| Workload bars, makespan highlighted, idle muted | `WorkloadChart`: width = hours / makespan |
| Infeasible state | `InfeasibleState` replaces cards, chart, timeline and table |
| Components, derived data in computed/composable | `components/`, `useDashboard` + `utils/dashboard.ts` |
| **Nice to have** | |
| Shared TS types kept in sync | `shared/types/api.ts` + `isOptimizationResult` (checked on every backend response and in a test over real backend output) |
| Variant in URL | `?variant=cost\|makespan\|compare` |
| Side-by-side comparison | `ComparisonPanel`: price and makespan difference |
| Gantt timeline | `GanttTimeline`: tasks back to back from t = 0, in task-id order (order does not change the makespan) |
| Upload / edit JSON and re-run | `InputEditor`: textareas, file upload, reset, infeasible example; changing the input re-runs `useFetch`. **Save as default data** (with confirmation) writes the backend's `data/*.json` |
| Vitest | `tests/`: data join + cost calculation, workload, Gantt, comparison, contract, input parsing |
| docker-compose | `../docker-compose.yml` |

Numbers are formatted with a fixed `en-US` locale so server-rendered HTML and the browser
produce the same text (no hydration mismatch).
