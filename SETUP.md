# Local hosting — setup checklist (temporary, kept up to date while building)

How to get the whole app (Symfony backend + Nuxt frontend) running on a fresh machine.
Items marked **(planned)** don't exist yet; this file is updated as each part lands.

| Part | Status |
|---|---|
| Backend (Symfony 7.4, PHP 8.3) | done — 101 tests green |
| Backend Docker image | written, **not yet built on a real machine** |
| Frontend (Nuxt + Tailwind) | **(planned)** |
| docker-compose (backend + frontend) | **(planned)** |

---

## 1. Prerequisites

Pick **one** route. Route A needs only Docker; route B runs everything natively.

### Route A — Docker only (simplest for someone who just wants to run it)

| Tool | Version | Check |
|---|---|---|
| Docker Desktop (Windows/macOS) or Docker Engine + Compose plugin (Linux) | recent | `docker --version`, `docker compose version` |
| Git (to clone) | any | `git --version` |

Windows: Docker Desktop needs **WSL 2** enabled (Docker Desktop's installer offers it) and Docker
Desktop must be **running** before any `docker` command.

### Route B — native (needed for development)

| Tool | Version | Check |
|---|---|---|
| PHP | **8.3.x** (8.4 also works) | `php -v` |
| PHP extensions | ctype, iconv, mbstring, dom, xml, xmlwriter, tokenizer, openssl | `php -m` |
| Composer | 2.x | `composer -V` |
| Node.js **(planned, frontend)** | 22 or 24 LTS | `node -v` |
| npm (ships with Node) **(planned)** | 10+ | `npm -v` |
| Git | any | `git --version` |

Install hints:

- **Windows**: PHP "VS16 x64 Non Thread Safe" zip from <https://windows.php.net/download>, unzip to
  e.g. `C:\php`, add it to `PATH`. Copy `php.ini-development` to `php.ini` and uncomment:
  `extension_dir = "ext"`, `extension=mbstring`, `extension=openssl` (Composer needs it for HTTPS).
  Then run `Composer-Setup.exe` from <https://getcomposer.org/download/>. Node: installer from
  <https://nodejs.org>.
- **macOS**: `brew install php@8.3 composer node`
- **Ubuntu/Debian**: `sudo apt install php8.3-cli php8.3-xml php8.3-mbstring unzip`, Composer from
  getcomposer.org, Node from nodejs.org or `nvm`.

---

## 2. Get the code

```bash
git clone <repo-url> task-optimizer      # (planned: repo not created yet)
cd task-optimizer
```

Layout:

```
task-optimizer/
  backend/     Symfony API + CLI   (see backend/README.md for algorithms and API contract)
  frontend/    Nuxt dashboard      (planned)
  SETUP.md     this file
```

---

## 3. Backend

### 3.1 Native (route B)

```bash
cd backend
composer install                                   # creates vendor/ and composer.lock
php vendor/bin/phpunit                             # expect: OK (101 tests ...)
php bin/console app:optimize --variant=makespan    # expect: "makespan": 11
php -S 127.0.0.1:8000 -t public                    # API on http://127.0.0.1:8000
```

Smoke test (second terminal; on Windows use `curl.exe`):

```bash
curl -X POST http://127.0.0.1:8000/api/optimize/cost       # totalPrice 17460, makespan 51
curl -X POST http://127.0.0.1:8000/api/optimize/makespan   # makespan 11, isOptimal true
```

### 3.2 Docker (route A)

```bash
cd backend
docker build -t task-optimizer-backend .
docker run --rm -p 8000:8000 task-optimizer-backend
docker run --rm task-optimizer-backend php vendor/bin/phpunit     # optional: tests in the container
```

Same smoke test as above.

---

## 4. Frontend **(planned)**

```bash
cd frontend
npm install
cp .env.example .env        # then set the variables below
npm run dev                 # http://localhost:3000
```

The browser only talks to Nuxt; Nuxt's server route calls the PHP API. So the backend must be
running first (or use mock mode).

---

## 5. Configuration (environment variables)

There are **no `.env` files in the backend**: values come from the real environment, with defaults.

| Variable | Used by | Default | Notes |
|---|---|---|---|
| `APP_SECRET` | backend | `local-dev-secret-change-me` | set a real value if ever exposed beyond localhost |
| `APP_ENV` | backend | `dev` | `test` is set automatically by PHPUnit |
| `NUXT_BACKEND_URL` **(planned)** | frontend server | `http://127.0.0.1:8000` | in docker-compose: `http://backend:8000` |
| `NUXT_USE_MOCK` **(planned)** | frontend server | `false` | `true` → serves `mock-results.json`, no backend needed |

Setting a variable for one command:
PowerShell `$env:APP_SECRET="..."`, bash `export APP_SECRET=...`.

---

## 6. Everything with one command **(planned)**

```bash
docker compose up --build      # backend :8000, frontend :3000
```

Open <http://localhost:3000>.

---

## 7. Ports

| Port | Service |
|---|---|
| 8000 | backend API |
| 3000 | frontend (planned) |

If a port is taken, change it: `php -S 127.0.0.1:8080 -t public`, `docker run -p 8080:8000 ...`
(and point `NUXT_BACKEND_URL` at the new port).

---

## 8. Troubleshooting

| Symptom | Fix |
|---|---|
| `composer install`: "The openssl extension is required" (Windows) | uncomment `extension=openssl` in `php.ini` |
| `Call to undefined function mb_...` / ctype errors | enable `mbstring` / `ctype` in `php.ini` (`php --ini` shows which file is used) |
| PHPUnit: "DOMDocument not found" | install/enable the `dom` / `xml` extension (`php8.3-xml` on Ubuntu) |
| "Symfony Runtime is missing" | run `composer install` (it generates `vendor/autoload_runtime.php`); as root also set `COMPOSER_ALLOW_SUPERUSER=1` |
| `docker: cannot connect to the Docker daemon` | start Docker Desktop and wait until it says "running" |
| `bind: address already in use` | see section 7 |
| Changes to PHP files not visible in Docker | the image copies the code at build time: rebuild (`docker build ...`) |

---

## 9. Done checklist

- [ ] `php vendor/bin/phpunit` → all green
- [ ] `POST /api/optimize/cost` → 17,460 / 51h
- [ ] `POST /api/optimize/makespan` → 11h, `isOptimal: true`
- [ ] Docker image builds and answers on :8000
- [ ] (planned) frontend loads, both tabs show results, mock mode works
- [ ] (planned) `docker compose up --build` brings up both
