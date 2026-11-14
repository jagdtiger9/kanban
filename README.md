# Kanban

A small self-hosted Trello-style kanban service (sine Trello is out of the game).
About ten users, one shared board per project.

## Stack

- **Backend**: Symfony 8.1, PHP 8.4, Doctrine ORM + PostgreSQL 17, Messenger CQRS via
  `aljerom/symfony-boilerplate` (`QueryBus`/`CommandBus` + `MessageValueResolver`)
- **Frontend**: Vue 3, TypeScript, Vite 7, Tailwind CSS 4, PrimeVue 4 (unstyled + Tailwind
  passthrough), Yarn 4 (pinned via `packageManager`, uses the `node_modules` linker)

## Development environment

The app runs inside the shared Docker env at `../../Docker` (`~/work/Docker`) — there is
no project compose stack. The env provides the caddy edge, angie proxy, php-fpm, and the
`pg` container; each project gets a vhost config and a directory under the hosts data
path. The project's own additions (Centrifugo service, vhost, worker program, secrets)
are reference copies in `docker/` — see [docker/README.md](docker/README.md) for where
each block goes.

Prerequisites:

- the shared env is built and running (`see ../../Docker/README.md`)
- `127.0.0.1 kanban.local` is in `/etc/hosts`
- `../../Docker/data/config/hosts/kanban.local.conf` exists (vhost root
  `/var/www/kanban/public`, SPA served from `public/build`); reload after edits with
  `make -C ../../Docker nginx.reload`

API: https://kanban.local (caddy edge → angie → php-fpm). Database: the base env `pg`
container, database/user `kanban`.

Email: the shared env runs Mailpit in its `main` profile. Confirmation emails are sent
to `smtp://mailpit:1025` and captured there instead of being delivered — open
http://localhost:8025 to read them. No real SMTP transport is configured in dev.

New accounts stay unconfirmed until the user opens the emailed link
(`/confirm/<token>`, valid for 24 hours) or a global admin confirms them from
`/admin/users`. Unconfirmed accounts cannot sign in.

## Configuration

| Variable | Development value | Purpose |
| --- | --- | --- |
| `MAILER_DSN` | `smtp://mailpit:1025` | Mailer transport (`.env.test` overrides with `null://null`) |
| `FRONTEND_URL` | `https://kanban.local` | Base URL for links embedded in emails (confirmation link) |

## Commands

| Command | What it does |
| --- | --- |
| `make fe-install` | Install frontend dependencies (Yarn 4, pinned by corepack) |
| `make fe-dev` | Vite dev server on :5173, `/api` proxied to `https://kanban.local` |
| `make fe-build` | Type-check and build the frontend into `public/build` (served by angie) |
| `make test` | PHPUnit suite in the php-fpm container |
| `make migrate` | Run pending Doctrine migrations |
| `make console ARGS="..."` | Run a Symfony console command in the container |
| `make cc` | Clear the Symfony cache |
| `make db-shell` | Open psql on the dev database |
| `make ping` | Check the API health endpoint |

Open http://localhost:5173 during development, or https://kanban.local for the built app.

## Tests

`make test` runs the full suite: unit tests plus integration tests against the real
PostgreSQL in the container (repository behavior and the route × actor ACL matrix).
Every Domain and Application class has unit tests.

## Deployment

Production deployment is not set up yet — deferred. The app currently runs only in the
shared development env described above.

## Conventions

Architecture, data-model details, timestamp/Doctrine quirks, and the project plan live in
`CLAUDE.md`.
