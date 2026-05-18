# my-finance

A private, single-user personal finance tracker for getting a bird's-eye view of money across multiple entities — personal finances plus the LLCs I own.

It is a **planning and visibility tool**, not accounting software. The goal is to open it, see where I stand across entities, accounts, and currencies, and make decisions about what to pay, when, and from which pocket.

## What it tracks

- **Entities** — buckets being tracked (Personal, LLC 1, LLC 2, …)
- **Accounts** — real or virtual accounts under each entity, possibly in different currencies
- **Counterparties** — people, banks, vendors, and other entities money moves to or from
- **Planned transactions** — scheduled payments and transfers, including recurring ones
- **Inter-entity transfers** — recorded once as two linked sides so consolidated totals don't double-count

For the longer-form description of the problem, scope, and what is explicitly out of scope, see [`docs/finance-tracker-context.md`](docs/finance-tracker-context.md).

## Tech stack

- PHP 8.5, Laravel 13
- Inertia.js v3 + Vue 3 + TypeScript
- Tailwind CSS v4
- PostgreSQL 18 (via Sail)
- Pest 4 for tests
- Laravel Fortify (auth), Wayfinder (typed routes), Boost (MCP tooling)

## Getting started with Laravel Sail

The project runs entirely inside Sail's Docker containers — no local PHP, Node, or Postgres installation needed beyond Docker.

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or Docker Engine + Compose on Linux)
- A way to run PHP once to bootstrap Composer — easiest path is the one-liner below that runs Composer in a throwaway container

### 1. Clone and enter the project

```bash
git clone <repo-url> my-finance
cd my-finance
```

### 2. Create the `.env` file

```bash
cp .env.example .env
```

### 3. Install Composer dependencies

If you don't have PHP installed locally, run Composer through a one-off Docker container so Sail itself gets installed:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php85-composer:latest \
    composer install --ignore-platform-reqs
```

If you already have PHP and Composer available locally, `composer install` works too.

### 4. Start the Sail containers

```bash
vendor/bin/sail up -d
```

This starts the Laravel app container and a PostgreSQL 18 container. The app is served on `http://localhost` (override with `APP_PORT` in `.env`).

### 5. Generate the app key and run migrations

```bash
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate --seed
```

### 6. Install JS dependencies and start Vite

```bash
vendor/bin/sail npm install
vendor/bin/sail npm run dev
```

Visit `http://localhost` and register a user to get going.

## Day-to-day commands

All commands are prefixed with `vendor/bin/sail` so they run inside the container.

```bash
vendor/bin/sail up -d                       # start containers
vendor/bin/sail stop                        # stop containers
vendor/bin/sail artisan migrate:fresh --seed
vendor/bin/sail artisan test --compact      # run Pest tests
vendor/bin/sail bin pint --dirty --format agent   # format PHP
vendor/bin/sail npm run dev                 # Vite dev server
vendor/bin/sail npm run build               # production assets
```

## Project layout

- `app/Models` — `Entity`, `Account`, `Counterparty`, `PlannedTransaction`, `User`
- `app/Actions` — domain actions, each exposing `execute(...)` and dispatching a `<Subject><Actioned>` event
- `app/Events` — domain events
- `resources/js/pages` — Inertia Vue pages
- `docs/finance-tracker-context.md` — product/scope context
- `CLAUDE.md` — Laravel Boost guidelines and project-specific conventions
