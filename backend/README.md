# Task Assignment Optimizer — backend

Symfony 7.4 (LTS) on PHP 8.3. Assigns tasks to employees under the skill rule
(`employee.skillLevel >= task.skillLevel`) for two objectives:

| Variant | Objective | Algorithm | Exact? |
|---|---|---|---|
| `cost` | minimum Σ hourlyRate × estimation | each task → cheapest eligible employee (tie: lowest id) | always (proof below) |
| `makespan` | minimum max hours of any employee | LPT greedy → local search → lower bound → branch & bound (node budget) | when `meta.isOptimal = true` |

Results for the bundled data: **cost** 17,460 / makespan 51h; **makespan** 11h (= lower bound, proven optimal), cost 26,400.

---

## 1. Run with plain PHP (no Docker)

### 1.1 Install PHP 8.3 and Composer

| OS | Commands |
|---|---|
| Ubuntu/Debian | `sudo apt install php8.3-cli php8.3-xml php8.3-mbstring unzip` then Composer from <https://getcomposer.org/download/> |
| macOS | `brew install php@8.3 composer` |
| Windows | PHP 8.3 "NTS x64" zip from <https://windows.php.net/download>, add it to `PATH`, enable `extension=mbstring` in `php.ini`, then `Composer-Setup.exe` |

Check: `php -v` (8.3.x) and `composer -V`.

### 1.2 Install dependencies

```bash
cd backend
composer install        # creates vendor/ and composer.lock
```

### 1.3 CLI

```bash
php bin/console app:optimize --variant=cost
php bin/console app:optimize --variant=makespan
php bin/console app:optimize --variant=makespan path/to/employees.json path/to/tasks.json
```

Prints the JSON result. Exit codes: `0` feasible, `1` invalid input / unreadable file,
`2` bad `--variant`, `3` infeasible (the JSON is still printed).

### 1.4 HTTP

```bash
php -S 127.0.0.1:8000 -t public          # PHP's built-in server (development only)
```

```bash
# bundled data/*.json (empty body or GET)
curl -X POST http://127.0.0.1:8000/api/optimize/cost
curl -X POST http://127.0.0.1:8000/api/optimize/makespan

# own payload
curl -X POST http://127.0.0.1:8000/api/optimize/makespan \
  -H 'Content-Type: application/json' \
  -d '{"employees":[{"employeeId":1,"skillLevel":2,"hourlyRate":100}],
       "tasks":[{"taskId":1,"skillLevel":1,"estimation":3}]}'
```

`200` = the optimization ran (also when `status` is `infeasible` — that is a valid answer).
`400` = broken input: `{"error": "invalid_input", "details": ["tasks[3].estimation must be an integer >= 1", ...]}`.

### 1.5 Tests

```bash
php vendor/bin/phpunit                     # everything (~1 s)
php vendor/bin/phpunit --testsuite unit
php vendor/bin/phpunit --filter Performance
```

## 2. Run with Docker

```bash
cd backend
docker build -t task-optimizer-backend .
docker run --rm -p 8000:8000 task-optimizer-backend                     # HTTP on :8000
docker run --rm task-optimizer-backend php bin/console app:optimize --variant=cost
docker run --rm task-optimizer-backend php vendor/bin/phpunit
```

The image is `php:8.3-cli` + Composer; dependencies are installed in their own layer so code
changes don't re-download them. It uses PHP's built-in server, which is fine for this case; a
production setup would use PHP-FPM + nginx or FrankenPHP.

---

## 3. Response contract

```json
{
  "variant": "makespan",
  "status": "feasible",
  "assignments": [{ "taskId": 1, "employeeId": 10 }],
  "totalPrice": 26400,
  "makespan": 11,
  "employeeSummary": [{ "employeeId": 1, "totalAssignedHours": 6 }],
  "unassignableTaskIds": [],
  "meta": { "isOptimal": true, "lowerBound": 11, "searchNodes": 0 }
}
```

- `assignments` sorted by `taskId`; `employeeSummary` sorted by `employeeId` and **includes idle
  employees at 0h** (the dashboard shows them muted).
- Infeasible: `status: "infeasible"`, empty `assignments`/`employeeSummary`, `unassignableTaskIds` filled.
- `meta`: cost → `{isOptimal: true}`; makespan → `isOptimal`, `lowerBound`, `searchNodes`
  (branch & bound nodes used; 0 = the heuristic already met the lower bound).

## 4. Input rules (validation)

Both inputs are JSON arrays. Ids unique integers; `skillLevel` integer ≥ 0; `estimation` integer ≥ 1
(whole hours — the lower bound and the exact search rely on integers); `hourlyRate` number ≥ 0.
All errors are collected and returned at once. Input order does not matter: everything is sorted by
id first.

## 5. Algorithms

### Variant A — minimum cost (exact, O(T·E))
No capacity limit and no deadline, so the cost of a task depends only on who does that task. The
total is a sum of independent terms, so minimizing each term (cheapest eligible employee) minimizes
the sum. Note that the cheapest *eligible* employee may be over-qualified: in the data, employee 4
(skill 3, 300/h) is cheaper than every skill-2 employee, so it gets all skill-2 and skill-3 work
(51h) while employee 8 (80/h) gets the skill-1 work (27h) → 17,460.

### Variant B — minimum makespan (NP-hard in general)
1. **LPT greedy**: tasks sorted by skill desc, duration desc, id asc; each to the least-loaded
   eligible employee (tie: lowest id). Constrained tasks first, long tasks before short ones.
2. **Local search** on a busiest employee: *move* a task to the least-loaded eligible employee, or
   *swap* it with a shorter task of another employee, whenever the busiest employee and the receiver
   both end strictly below the current makespan. Each step reduces (makespan, #busiest employees),
   so it terminates.
3. **Lower bound** (skill levels are nested: a skill ≥ k task can only go to a skill ≥ k employee):
   `LB = max( longest task, ceil(H_k / N_k), d_k[N_k−1] + d_k[N_k] )` over task levels k, where H_k
   = hours needing skill ≥ k, N_k = employees with skill ≥ k, d_k = those durations sorted desc
   (pigeonhole: with more tasks than employees, two of the N_k+1 longest must share someone).
   If the heuristic reaches LB it is optimal — this happens for the bundled data (11h).
4. **Branch & bound** otherwise: assign in LPT order, least-loaded first; prune when a load reaches
   the incumbent, when two candidates have the same skill and load (symmetry), or when the per-level
   remaining-work bound cannot beat the incumbent. Exhausting the tree proves optimality even if the
   result is above LB.

The search is capped by a **node budget** (250,000 ≈ 0.5 s), not a wall-clock limit: a time limit
would make the output depend on machine speed and break the determinism requirement. If the budget
runs out, the best schedule found is returned with `isOptimal: false` and the `lowerBound`, so the
caller knows the maximum possible gap.

Among equally short schedules the variant does **not** minimize cost (the spec asks for makespan
only); the returned one is fixed by the id ordering.

### Determinism
Inputs sorted by id; every tie broken by id; stable sorts; no randomness, no clock in decisions.
Tested: same input twice and shuffled input give identical JSON.

## 6. Tests

| Area | What |
|---|---|
| bundled data | cost 17,460 / 51h with the exact per-employee hours; makespan 11h = LB, proven |
| infeasible | task above every skill level; no employees; ids reported sorted |
| varying rates | 0.5/h vs 1,000,000/h; over-qualified but cheaper employee wins; equal rates → lowest id |
| skewed durations | one 100h task + fifty 1h tasks; 1h–3h mixed with 40h–100h at scale |
| correctness | 65 random small instances vs exhaustive brute force (both variants) |
| exact search | B&B fixes a bad start, proves optimality above LB, honours the node budget |
| performance | 5/15/30 employees × 50/120/200 tasks, uniform and skewed, plus a hard instance that exhausts the budget — each < 3 s (≈ 0.5 s worst on a laptop) |
| HTTP / CLI | both endpoints, payload, infeasible 200, invalid 400, CLI exit codes, file arguments |

## 7. Structure

```
src/Domain/       Employee, Task (readonly), Variant (enum), OptimizationResult (derived metrics + JSON)
src/Optimizer/    InputValidator, Feasibility, CostOptimizer, MakespanOptimizer, LowerBound,
                  BranchAndBound, OptimizationService (load → validate → dispatch)
src/Controller/   OptimizeController  (thin HTTP adapter)
src/Command/      OptimizeCommand     (thin CLI adapter)
data/             employees.json, tasks.json
tests/Unit, tests/Functional, tests/Support/Instances.php (generator, brute force, validity check)
```

The optimizer classes don't depend on Symfony; the controller and the command only translate
HTTP/CLI to `OptimizationService::run()`.
