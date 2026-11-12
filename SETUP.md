# Local hosting — setup checklist & Docker alternative

How to get the whole app (Symfony backend + Nuxt frontend) running on a fresh machine.

| Part | Status |
|---|---|
| Backend (Symfony 7.4, PHP 8.5) | PHPUnit green, Docker image built and verified on Windows |
| Frontend (Nuxt 4 + Tailwind 4) | Vitest green, Docker image built and verified against the live backend |

---

## 1. Prerequisites

Pick **one** route. Route A needs only Docker; route B runs everything natively.

### Route A — Docker only (simplest for someone who just wants to run it)

| Tool | Version | Check |
|---|---|---|
| Docker Desktop (Windows/macOS) or Docker Engine + Compose plugin (Linux) | recent | `docker --version`, `docker compose version` |
| Git (to clone) | any | `git --version` |

Windows: Docker Desktop needs **WSL 2** (`wsl --install`) and must be **running** ("Engine running")
before any `docker` command. The Chocolatey package `docker-cli` alone is not enough: install
`docker-desktop`.

### Route B — native (needed for development)

| Tool | Version | Check |
|---|---|---|
| PHP | **8.5.x** (Docker image uses 8.5.12) | `php -v` |
| PHP extensions | ctype, iconv, mbstring, dom, xml, xmlwriter, tokenizer, openssl | `php -m` |
| Composer | 2.x | `composer -V` |
| Node.js | **22.22.3+ or 24.15+** (Nuxt 4.6 requirement; 24 LTS recommended) | `node -v` |
| npm (ships with Node) | 10+ | `npm -v` |
| Git | any | `git --version` |

Install hints:

- **Windows**: PHP "VS17 x64 Non Thread Safe" zip from <https://windows.php.net/download>, unzip to
  e.g. `C:\php`, add it to `PATH`. Copy `php.ini-development` to `php.ini` and uncomment:
  `extension_dir = "ext"`, `extension=mbstring`, `extension=openssl` (Composer needs it for HTTPS).
  Then `Composer-Setup.exe` from <https://getcomposer.org/download/>. Node: LTS installer from
  <https://nodejs.org> (or `choco install nodejs-lts`).
- **macOS**: `brew install php composer node`
- **Ubuntu/Debian**: `sudo apt install php8.5-cli php8.5-xml php8.5-mbstring unzip`, Composer from
  getcomposer.org, Node from nodejs.org or `nvm`.

---

## 2. Get the code

```bash
git clone https://github.com/agabrielx21/task-optimizer.git
cd task-optimizer
```

```
task-optimizer/
  backend/             Symfony API + CLI      
  frontend/            Nuxt dashboard         (frontend/README.md: architecture, requirements map)
  docker-compose.yml   both together
  README.md: algorithms    API contract
  SETUP.md             this file
```

---

## 3. Everything with one command (route A)

```bash
docker compose up --build      # backend :8000, frontend :3000
```

Open <http://localhost:3000>. Stop with Ctrl+C (or `docker compose down`).
After changing code: `docker compose up --build` again (images copy the code at build time).

---

## 4. Backend alone

### 4.1 Native

```bash
cd backend
composer install                                   # installs from composer.lock
php vendor/bin/phpunit                             # expect: OK (109 tests ...)
php bin/console app:optimize --variant=makespan    # expect: "makespan": 11
php -S 127.0.0.1:8000 -t public                    # API on http://127.0.0.1:8000
```

Alternative dev server: Symfony CLI, from inside `backend/`: `symfony server:start --no-tls --port=8000`.

Smoke test (second terminal; on Windows use `curl.exe`):

```bash
curl -X POST http://127.0.0.1:8000/api/optimize/cost       # totalPrice 17460, makespan 51
curl -X POST http://127.0.0.1:8000/api/optimize/makespan   # makespan 11, isOptimal true
```

### 4.2 Docker

```bash
cd backend
docker build -t task-optimizer-backend .           # "." = this folder is the build context
docker run -d --name task-optimizer-backend -p 8000:8000 task-optimizer-backend
docker run --rm task-optimizer-backend php vendor/bin/phpunit     # optional: tests in the container
```

---

## 5. Frontend alone

Needs the backend running on :8000 (section 4), or mock mode.

```bash
cd frontend
npm install
npm run dev              # http://localhost:3000
npm test                 # Vitest
```

Mock mode (no backend needed):
PowerShell `$env:NUXT_USE_MOCK="true"; npm run dev` · cmd `set NUXT_USE_MOCK=true && npm run dev` ·
bash `NUXT_USE_MOCK=true npm run dev`.

Production build: `npm run build`, then `node .output/server/index.mjs`.

The browser only talks to Nuxt; Nuxt's server route calls the PHP API.

---

## 6. Configuration (environment variables)

There are **no `.env` files**: values come from the real environment, with defaults.

| Variable | Used by | Default | Notes |
|---|---|---|---|
| `APP_SECRET` | backend | `local-dev-secret-change-me` | set a real value if ever exposed beyond localhost |
| `APP_ENV` | backend | `dev` | `test` is set automatically by PHPUnit |
| `NUXT_BACKEND_URL` | frontend server | `http://127.0.0.1:8000` | docker-compose sets `http://backend:8000` |
| `NUXT_USE_MOCK` | frontend server | `false` | `true` → serves `server/mock/mock-results.json`, no backend needed |

---

## 7. Ports

| Port | Service |
|---|---|
| 8000 | backend API |
| 3000 | frontend |

If a port is taken: `php -S 127.0.0.1:8080 -t public` and `NUXT_BACKEND_URL=http://127.0.0.1:8080`;
for the frontend `npm run dev -- --port 3001`; in compose change the left side of `"3000:3000"`.

---

## 8. Troubleshooting

| Symptom | Fix |
|---|---|
| `composer install`: "The openssl extension is required" (Windows) | uncomment `extension=openssl` in `php.ini` |
| `Call to undefined function mb_...` / ctype errors | enable `mbstring` / `ctype` in `php.ini` (`php --ini` shows which file is used) |
| PHPUnit: "DOMDocument not found" | install/enable the `dom` / `xml` extension (`php8.5-xml` on Ubuntu) |
| "Symfony Runtime is missing" | run `composer install` (it generates `vendor/autoload_runtime.php`); as root also set `COMPOSER_ALLOW_SUPERUSER=1` |
| Docker build fails at `composer install` (exit code 2) | `composer.lock` was resolved for a different PHP than the image: keep `config.platform.php` in `composer.json` equal to the image's PHP and run `composer update` |
| `docker: failed to connect to the docker API` / `cannot connect to the Docker daemon` | start Docker Desktop and wait for "Engine running" |
| `docker build` can't find the Dockerfile | run it inside `backend/` (or `frontend/`), or pass the folder instead of `.` |
| `bind: address already in use` / `port is already allocated` | stop `php -S` / Symfony server / old container, or see section 7 |
| Dashboard shows "Backend unreachable (502)" | backend not running or wrong `NUXT_BACKEND_URL`; or use `NUXT_USE_MOCK=true` |
| `npm install` fails with an engine error | Node too old: Nuxt 4.6 needs 22.22.3+ or 24.15+ |
| Symfony CLI: every route 404, "unable to detect the front controller" | start it from inside `backend/` (or `--dir=backend`) |
| Code changes not visible in Docker | rebuild the image (`docker compose up --build`) |
