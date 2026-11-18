# Kanban

Self-hosted Trello-style kanban: one board per project, about ten users. The column is
the status — a card moves between columns and carries no separate status field.

## Features

- Projects with members and project/global admin roles
- One board per project: columns, cards, drag-and-drop reordering
- Labels, due dates, assignees, markdown comments, and an activity log
- File attachments on cards and comments, with image thumbnails and text previews
- Email-confirmed accounts, self-service password change, and profile photos
- Dark/light theme, dark by default
- Realtime board invalidation over Centrifugo

## Stack

Symfony 8.1 API on PHP 8.4 with PostgreSQL 17 and Messenger CQRS
(`aljerom/symfony-boilerplate` + `aljerom/simple-hydrator`); Vue 3 SPA with TypeScript,
Vite, Tailwind CSS 4, and PrimeVue 4 with a Nora-based styled theme; Centrifugo for
realtime; RabbitMQ worker for async side effects.

## Documentation

| Document | What it covers |
| --- | --- |
| [AGENTS.md](AGENTS.md) | Coding-agent contract and project principles |
| [docs/conventions.md](docs/conventions.md) | Project conventions, package usage, cookbook |
| [docs/plans/](docs/plans/) | Implementation decision records (outbox, realtime, attachments) |

## Media storage

Uploads live outside the document root, under `MEDIA_DIR` (`var/media` by default), and
are served only through authenticated API routes. Include that directory in backups and
give the web user access with `make permissions`.

`app:media:sweep --dry-run` reports stored files with no database reference and
referenced files missing from storage. Without `--dry-run` it deletes unreferenced files
older than the grace period (`--older-than`, default `24 hours`); run it from cron after
project deletions or crashes.

## Status

The app runs only in the shared development environment. Production deployment and
SaaS/multi-tenancy are deferred.
