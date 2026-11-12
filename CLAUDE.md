# Canban

Self-hosted Trello-style kanban (Trello is blocked at our location). ~10 users, Docker
deploy. Stack: Symfony 8.1 / PHP 8.4 / Doctrine ORM + PostgreSQL 17 / Messenger CQRS
(`aljerom/symfony-boilerplate` buses + `MessageValueResolver`) on the backend; Vue 3 +
TypeScript + Vite + Tailwind CSS 4 + PrimeVue 5 (unstyled, Tailwind passthrough) in
`frontend/`.

## Architecture

Global DDD rules apply (see `~/.claude/CLAUDE.md`): Domain → Application → Infrastructure
→ Interface, dependencies inward only. In addition, for this project:

- CQRS messages live in `<Module>/Application/Message/{Command,Query}/` and implement
  `Aljerom\SymfonyBoilerplate\CQRS\{Command,Query}`; handlers are `#[AsMessageHandler]`
  classes with `__invoke` in `<Module>/Application/MessageHandler/`.
- Controllers take typed message parameters directly (hydrated + validated by
  `MessageValueResolver`) — no `Request`, no `json_decode`, no manual DTO wiring.
  Validation failures throw `ValidationException`, handled by the bundle listener.
- Handlers never touch `EntityManager` — they inject repository interfaces
  (`Domain/Repository/`), implemented in `Infrastructure/`.
- Repositories persist via the command bus' `doctrine_transaction` middleware; no manual
  `flush()` orchestration in handlers.
- Config is the Symfony array format (`App::config([...])`), not YAML. New bundle config
  goes in `config/packages/<bundle>.php`.
- Entity mappings are registered per module in
  `config/packages/doctrine.php` → `orm.mappings` (the `App\Xxx` key + `type: attribute`
  pattern from the shop project).
- Schema changes go through `make:migration` + `doctrine:migrations:migrate`.
- TDD: every Domain and Application class gets unit tests; repositories get integration
  tests against the real Postgres in the Docker stack.

## Running

- `make up` — Docker stack (db, php-fpm, nginx → API on :8080; host pg port **5433**)
- `make console ARGS="..."`, `make migrate`, `make db-shell`, `make ping`
- `make fe-dev` — Vite on :5173, `/api` proxied to :8080; `make fe-build` for production
- PHPUnit: `vendor/bin/phpunit`

## Plan

Approved 2026-11-12. One step at a time; explicit approval after each step. Steps marked
`[x]` are done, `[ ]` pending — status is updated as we go.

### v1 scope (agreed)

- Trello way: the column IS the status — no separate status field on cards.
- Cards: title, description, comments, labels, due dates, assignees, activity log.
- Users: public registration (same flow as the shop project), global admin role +
  per-project admin/member roles. Project admin adds registered users via a picker —
  no email invites in v1.
- Each project has its own board page; new project gets default columns To Do / In
  Progress / Done.
- Card rights: users edit/delete their OWN cards only, but may comment on any card.
  **Assumption to confirm in step 8:** project admin can edit/delete any card.
- Card delete = archive; project delete = archive by project admin, hard delete by
  global admin.
- Comments: markdown text, edit/delete own, no @mentions in v1.
- Theme: dark + light, dark by default.
- Deploy: Docker Compose.

### v2 scope (deferred)

- Card status list = checklist inside a card.

### Steps

1. `[x]` **Scaffold** — Symfony 8.1 skeleton + Docker stack (verified `GET /api/ping`
   through nginx → php-fpm → QueryBus) + frontend (Vue/Vite/Tailwind/PrimeVue unstyled,
   dark-first LoginView + ShellView, API client, dev proxy verified).
2. `[ ]` **Domain model** — User, Project, ProjectMember, Board, Column, Card, Label,
   Comment, CardActivity entities + invariants + domain events; Doctrine mappings +
   migrations; unit tests.
3. `[ ]` **Auth & users** — registration like the shop project (point me at the shop repo
   when we start), login, logout, current-user endpoint, global admin promotion.
4. `[ ]` **Projects** — CRUD, member picker, role management, default columns on create,
   archive/restore + hard delete.
5. `[ ]` **Board & columns** — board page per project, column CRUD + drag reorder.
6. `[ ]` **Cards** — CRUD + archive, labels, due dates, assignees, drag&drop between
   columns (SortableJS), own-card edit rules.
7. `[ ]` **Comments & activity** — markdown comments on any card, activity log entries
   for card events.
8. `[ ]` **Permissions hardening** — voters for every route, confirm the admin-override
   assumption, integration tests for the ACL matrix.
9. `[ ]` **Realtime** — OPEN DECISION: Mercure (user distrusts it) vs Centrifugo vs
   simple polling — discuss options before implementing.
10. `[ ]` **Polish & release** — light theme, empty/error states, deployment docs,
    production build.

After the baseline works: dedicated discussion of SaaS scenarios (multi-tenant flavor)
before any of it is built.
