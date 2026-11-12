# Canban

A small self-hosted Trello-style kanban service (Trello is blocked at our location).

## Stack

- **Backend**: Symfony 8.1, PHP 8.4, Doctrine ORM + PostgreSQL 17, Messenger CQRS
  via `aljerom/symfony-boilerplate` (`QueryBus`/`CommandBus` + `MessageValueResolver`)
- **Frontend**: Vue 3, TypeScript, Vite 7, Tailwind CSS 4, PrimeVue 5 (unstyled + Tailwind pt)
- **Deploy**: Docker Compose — postgres, php-fpm, nginx

## Run it

```bash
make up          # build + start db, php-fpm, nginx  → API on :8080
make fe-install  # first time only
make fe-dev      # Vite dev server on :5173, /api proxied to :8080
```

Open http://localhost:5173. API health: `make ping` or http://localhost:8080/api/ping.

Useful: `make console ARGS="debug:router"`, `make migrate`, `make db-shell`, `make cc`.
Host PostgreSQL port is mapped to **5433** (5432 is taken on the host).

## Development

- PHP deps are managed on the host: `composer require ...`
- Backend unit tests: `vendor/bin/phpunit`
- Conventions and the full plan: see `CLAUDE.md`
