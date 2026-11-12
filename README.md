# Task Assignment Optimizer

Assigns tasks to employees (skill rule: `employee.skillLevel >= task.skillLevel`) in two variants:
**minimum total cost** and **minimum makespan**, with a dashboard that shows and compares them.

| Part | Tech | Docs |
|---|---|---|
| `backend/` | Symfony 7.4 LTS, PHP 8.5 — HTTP API + CLI, PHPUnit | [backend/README.md](backend/README.md) |
| `frontend/` | Nuxt 4, Tailwind CSS 4, Vitest | [frontend/README.md](frontend/README.md) |

## Prerequisites (Windows, Chocolatey)

In an **Administrator** terminal:

```cmd
wsl --install
choco install docker-desktop git -y
```

Restart Windows, start Docker Desktop and wait for **Engine running**.

## Run

```cmd
git clone https://github.com/agabrielx21/task-optimizer.git
cd task-optimizer
docker compose up --build
```

Dashboard: <http://localhost:3000> · API: <http://localhost:8000> · stop with Ctrl+C.

## Testing

Both suites run in Docker, from the project root. The test services are only started by these commands,
not by `docker compose up`.

### Backend (PHPUnit)

```cmd
docker compose run --rm backend-tests
```

109 tests, about 1.5 s:

| What | Covered by |
|---|---|
| Infeasible task (no eligible employee) | both variants, the API (200 + task ids) and the CLI |
| Widely varying hourly rates | 0.5/h vs 1,000,000/h; cheaper over-qualified employee wins; equal rates → lowest id |
| Highly skewed durations | one 100 h task + fifty 1 h tasks; 1–3 h mixed with 40–100 h tasks |
| Performance (50–200 tasks, 5–30 employees) | 5/15/30 × 50/120/200, uniform and skewed, each under 3 s |
| Determinism | same input twice and shuffled input → identical JSON |
| Correctness | provided data (17,460 / 51 h and 11 h); both variants against brute force on 65 small instances |
| API and saving data | both optimize endpoints, invalid input → 400, `GET`/`PUT /api/data` |

The tests use their own copy of the provided data (`backend/tests/fixtures`), so they keep passing after
you save different default data from the dashboard.

### Frontend (Vitest)

```cmd
docker compose run --rm frontend-tests
```

28 tests:

| What | Covered by |
|---|---|
| Data join + cost calculation | every task joined once, cost = hourly rate × hours, totals equal the backend's `totalPrice` |
| Derived data | employee filter, workload bars (critical / idle), Gantt blocks, comparison, axis ticks |
| API contract | real backend output and the default data pass the runtime checks; malformed responses are rejected |
| Input editor | JSON parsing errors, error messages from the backend |

## Results for the provided data

| Variant | Total price | Makespan |
|---|---|---|
| Min Cost | 17,460 | 51 h |
| Min Makespan | 26,400 | 11 h (proven optimal) |

## Credits

Developed with AI assistance from **Claude Opus 5.5** (Anthropic), used for implementation and documentation. Architecture, algorithm and design decisions were proposed, reviewed and approved by me.
