# Kanban

Self-hosted Trello-style kanban: one board per project, about ten users. The column is
the status — a card moves between columns and carries no separate status field.

## Features

- Projects with members and project/global admin roles
- One board per project: columns, cards, drag-and-drop reordering
- Labels, due dates, assignees, markdown comments, and an activity log
- Email-confirmed accounts and self-service password change
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
| [docs/plans/](docs/plans/) | Implementation decision records (outbox, realtime) |

## Status

The app runs only in the shared development environment. Production deployment and
SaaS/multi-tenancy are deferred.
