# SyncHub

Keeps a **legacy ERP** and an **online store** in sync: products, prices and
stock flow from the ERP to the store, and orders flow back.

```
legacy ERP (MySQL 5.7)  <──  SyncHub (Laravel)  ──>  store (WooCommerce)
   read/write via SQL         queues, run log,          REST API
                              dashboard
```

It is built around the problems real integrations run into: a schema nobody
can change, no "changed since" API, flaky networks, and the need to know
exactly what failed and replay it.

> **Status:** step 1 of 5 — the full local stack runs with one command.
> Sync engine and dashboard come next (see [Roadmap](#roadmap)).

## Stack

Laravel 13 · PHP 8.5 · Inertia + React 19 · Tailwind + shadcn/ui ·
MySQL 8.4 · Redis + Horizon · Pest · Larastan · Docker Compose · GitHub Actions

## Running locally

Requirements: Docker. Nothing else is installed on the host — Composer and npm
run inside the containers.

```bash
cp .env.example .env
docker compose build
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php artisan key:generate
docker compose run --rm --no-deps vite npm ci
docker compose up -d
docker compose exec app php artisan migrate
```

| Service                  | URL                                                |
| ------------------------ | -------------------------------------------------- |
| SyncHub                  | http://localhost:8100                              |
| Horizon (queues)         | http://localhost:8100/horizon                      |
| Demo store (WooCommerce) | http://localhost:8180/wp-admin — `admin` / `admin` |

The first `up` installs WordPress + WooCommerce and writes the store's API
credentials to `storage/app/private/woocommerce.json` (container `shop-init`).

## What runs in the stack

| Container                             | Role                                                                    |
| ------------------------------------- | ----------------------------------------------------------------------- |
| `app`, `horizon`, `scheduler`, `vite` | SyncHub: web, queue workers, scheduled syncs, assets                    |
| `mysql`                               | SyncHub's own database                                                  |
| `legacy-erp`                          | A fake legacy ERP: MySQL 5.7, latin1, uppercase tables, no foreign keys |
| `redis`                               | Queues and cache                                                        |
| `shop`, `shop-db`, `shop-init`        | WordPress + WooCommerce demo store                                      |

### The legacy ERP

[`docker/legacy-erp`](docker/legacy-erp) creates 300 products across two
warehouses, plus the quirks real legacy data has: inactive products that must
be hidden (never deleted), descriptions with trailing spaces and shouting
case, missing weights, negative stock, and an orders table with no unique key
on the external order number.

The only change signal is a `DT_ALT` timestamp column — which is what the
incremental sync will rely on.

## Quality checks

```bash
docker compose exec app composer ci:check
```

Runs formatting and lint (Pint, Vite+), static analysis (Larastan level 7)
and the Pest suite. The same command runs on every push in GitHub Actions.

## Roadmap

1. ✅ Local stack: Laravel, legacy ERP, WooCommerce, Redis, CI
2. Product sync ERP → store: ID mapping, watermark, batched upserts
3. Run log and dashboard
4. Stock, orders back to the ERP, retries and replay of failures
5. Architecture notes and demo GIF
