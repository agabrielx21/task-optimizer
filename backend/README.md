# Task Assignment Optimizer — backend

Symfony 7.4 LTS on PHP 8.5. Assigns tasks to employees (`employee.skillLevel >= task.skillLevel`)
in two variants:

| Variant | Goal | Result for the bundled data |
|---|---|---|
| `cost` | minimum total price | 17,460 · makespan 51 h |
| `makespan` | minimum completion time | 11 h (proven optimal) · 26,400 |

## Prerequisites (Windows, Chocolatey)

In an **Administrator** terminal:

```cmd
wsl --install
choco install docker-desktop git -y
```

Restart Windows, start Docker Desktop and wait for **Engine running**.

Only needed when you change `composer.json` (to regenerate `composer.lock` with `composer update`):

```cmd
choco install php composer -y
```

## Run

From the project root (backend + frontend):

```cmd
docker compose up --build
```

Dashboard: <http://localhost:3000> · API: <http://localhost:8000>

Backend only:

```cmd
cd backend
docker build -t task-optimizer-backend .
docker run --rm -p 8000:8000 task-optimizer-backend
```

## API

| Endpoint | Body |
|---|---|
| `POST /api/optimize/cost` | `{"employees": [...], "tasks": [...]}`, or empty for `data/*.json` |
| `POST /api/optimize/makespan` | same |
| `GET /api/data` | returns the default data (`data/employees.json`, `data/tasks.json`) |
| `PUT /api/data` | `{"employees": [...], "tasks": [...]}`: validates, then overwrites both files (400 and nothing written if invalid) |

With docker compose, `backend/data` is mounted into the container, so a save changes the files in the project folder.

## Response

```json
{
  "variant": "makespan",
  "status": "feasible",
  "assignments": [{ "taskId": 1, "employeeId": 8 }],
  "totalPrice": 26400,
  "makespan": 11,
  "employeeSummary": [{ "employeeId": 1, "totalAssignedHours": 6 }],
  "unassignableTaskIds": [],
  "meta": { "isOptimal": true, "lowerBound": 11, "searchNodes": 0 }
}
```

| Field | Meaning |
|---|---|
| `assignments` | taskId → employeeId, sorted by taskId |
| `totalPrice` | Σ hourlyRate × estimation |
| `makespan` | hours of the busiest employee |
| `employeeSummary` | hours per employee, idle employees included (0 h) |
| `status` / `unassignableTaskIds` | `infeasible` + the tasks nobody can do (assignments then empty) |
| `meta` | `isOptimal`; for makespan also `lowerBound` and `searchNodes` (0 = no search needed) |

HTTP `200` also for an infeasible result; `400` for invalid input, with the reasons in `details`.

## Algorithms

### Variant A — minimum cost (exact, O(T·E))
No capacity limit and no deadline, so the cost of a task depends only on who does that task. The
total is a sum of independent terms, so giving each task to its cheapest eligible employee (tie:
lowest id) minimizes the sum. The cheapest eligible employee may be over-qualified: employee 4
(skill 3, 300/h) is cheaper than every skill-2 employee, so it gets all skill-2 and skill-3 work
(51 h) while employee 8 (80/h) gets the skill-1 work (27 h).

### Variant B — minimum makespan (NP-hard in general)
1. **LPT greedy**: tasks sorted by skill desc, duration desc, id asc; each goes to the least-loaded
   eligible employee (tie: lowest id).
2. **Local search** on a busiest employee: *move* a task to another employee, or *swap* it with a
   shorter task, whenever both employees end strictly below the current makespan.
3. **Lower bound** (skill levels are nested: a skill ≥ k task can only go to a skill ≥ k employee):
   `LB = max( longest task, ceil(H_k / N_k), d_k[N_k−1] + d_k[N_k] )` over task levels k, where
   H_k = hours needing skill ≥ k, N_k = employees with skill ≥ k, d_k = those durations sorted desc.
   If the heuristic reaches LB, it is optimal. For the bundled data: 51 h of skill ≥ 2 work over
   5 employees → at least 11 h, and 11 h is reached.
4. **Branch & bound** otherwise, pruning on the best makespan so far, symmetric employees and the
   remaining-work bound. Exhausting the search proves optimality.

The search is capped by a **node budget** (250,000 ≈ 0.5 s), not a time limit, because a time limit
would make the output depend on machine speed. If the budget runs out, the best schedule is returned
with `isOptimal: false` and the `lowerBound`.

### Determinism
Inputs sorted by id, every tie broken by id, no randomness, no clock: identical input → identical output.

## Automated tests

From the project root:

```cmd
docker compose run --rm backend-tests
```

109 PHPUnit tests, about 1.5 s:

| Case | Covered by |
|---|---|
| Infeasible task (no eligible employee) | both variants, the endpoint (200 + task ids) and the CLI |
| Widely varying hourly rates | 0.5/h vs 1,000,000/h; cheaper over-qualified employee wins; equal rates → lowest id |
| Highly skewed durations | one 100 h task + fifty 1 h tasks; 1–3 h mixed with 40–100 h tasks |
| Performance (50–200 tasks, 5–30 employees) | 5/15/30 × 50/120/200, uniform and skewed, each < 3 s; a hard instance that hits the node budget |
| Determinism | same input twice and shuffled input → identical JSON |
| Correctness | bundled data (17,460 / 51 h and 11 h); both variants checked against brute force on 65 small random instances |
| API | both endpoints, own payload, invalid input → 400 |
| Saving default data | save writes sorted, validated files; invalid data → 400 and files untouched; optimize uses the saved data (tests write to a temporary folder via `APP_DATA_DIR`) |

The tests read `tests/fixtures/*.json` (a copy of the provided data), not `data/`, so saving new default data never breaks them.

## Structure

```
src/
  Service/
    OptimizationService.php      entry point: validate input → run the chosen variant
    DataStore.php                read / validate / overwrite data/*.json (temp file + rename)
  Optimizer/
    Cost/
      CostOptimizer.php          Variant A: cheapest eligible employee per task
    Makespan/
      MakespanOptimizer.php      Variant B pipeline: LPT greedy → local search → lower bound → B&B
      LowerBound.php             step 3: the lower bound
      BranchAndBound.php         step 4: exact search with node budget
    Input/
      InputValidator.php         JSON → Employee/Task objects, collects all errors
      InvalidInputException.php
    Shared/
      Optimizer.php              interface both variants implement
      Feasibility.php            "is any task above every employee's skill?"
  Domain/                        Employee, Task, Variant, OptimizationResult (totals + JSON output)
  Controller/OptimizeController.php   HTTP: /api/optimize/*
  Controller/DataController.php       HTTP: /api/data (GET, PUT)
  Controller/RequestPayload.php       reads the {employees, tasks} request body
  Command/OptimizeCommand.php         CLI
data/                            employees.json, tasks.json
tests/
  Unit/Cost/                     CostOptimizerTest
  Unit/Makespan/                 MakespanOptimizerTest, LowerBoundAndSearchTest
  Unit/Input/                    InputValidatorTest
  Unit/Service/                  DataStoreTest
  Functional/                    OptimizeEndpointTest (HTTP + CLI), DataEndpointTest
  Support/Instances.php          test helpers: random instances, brute force, validity check
  fixtures/                      the provided employees.json / tasks.json, used by the tests
```

Flow: Controller / Command → `Service/OptimizationService` → `Optimizer/Cost` or `Optimizer/Makespan`.
The optimizer classes don't depend on Symfony.
