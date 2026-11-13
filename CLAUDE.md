# Canban

Self-hosted Trello-style kanban (Trello is blocked at our location). ~10 users, Docker
deploy. Stack: Symfony 8.1 / PHP 8.4 / Doctrine ORM + PostgreSQL 17 / Messenger CQRS
(`aljerom/symfony-boilerplate` buses + `MessageValueResolver`) on the backend; Vue 3 +
TypeScript + Vite + Tailwind CSS 4 + PrimeVue 4 (unstyled, Tailwind passthrough) in
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
- `actorId` message props carry no validation constraints: the controller assigns them
  from the authenticated user after hydration (the resolver validates before the
  controller runs, so request-time validation of an always-absent field would reject
  every request). Only request-supplied inputs are validated.
- The resolver hydrates bodies for POST/PUT and query strings otherwise — use PUT (not
  PATCH) for JSON-body updates.
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
- Timestamp columns are `timestamptz` with microsecond precision. Two DBAL 4.5
  PostgreSQL quirks force this shape: the platform hardcodes `TIMESTAMP(0)` and ignores
  `precision`, and the stock datetime types format without `u` (truncating to whole
  seconds — same-second ordering becomes a coin flip). Hence: custom type
  `App\Shared\Infrastructure\Doctrine\Type\DatetimeTzMicroType` (registered as
  `datetimetz_micro`) for the PHP↔DB conversion, plus
  `columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE'` on the attributes for the DDL.
  The comparator ignores `columnDefinition` and the type's inherited declaration
  mirrors introspection, so schema diffs stay clean.
- Email confirmation: the raw token goes out in the link (`/confirm/<token>`), only its
  SHA-256 hash is stored (`confirmation_token_hash`, 64 chars) with a 24h expiry.
  Sending is driven by the `UserRegistered` domain event → listener →
  `ConfirmationEmailSender`. Mailer config lives under `framework.mailer` in
  `config/packages/mailer.php` (with `message_bus: false`).
- TDD: every Domain and Application class gets unit tests; repositories get integration
  tests against the real Postgres in the Docker stack.

## Running

- App runs in the shared base Docker env (`~/work/Docker`): API at `https://canban.local`
  (caddy edge → proxy → angie vhost → fpm; DB = base env `pg` container). No project
  compose stack. `127.0.0.1 canban.local` must be in `/etc/hosts`.
- `make console ARGS="..."`, `make cc`, `make migrate`, `make db-shell` (opens psql in
  the base `pg` container), `make ping`, `make test` (PHPUnit in the fpm container)
- Frontend package manager is Yarn 4 (corepack; `packageManager` pins 4.9.2,
  `node_modules` linker). `make fe-dev` — Vite on :5173, `/api` proxied to
  `https://canban.local`; `make fe-build` type-checks and builds into `public/build`,
  which angie serves directly.

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
  **Confirmed in step 8:** project admin (and global admin) can edit/delete any card.
- Card delete = archive; project delete = archive by project admin, hard delete by
  global admin.
- Comments: markdown text, edit own only (admins do NOT override), delete own or any as
  project/global admin, no @mentions in v1.
- Theme: dark + light, dark by default.
- Deploy: Docker Compose — dev runs in the shared base env; dedicated production
  deployment deferred (2026-11-13).

### v2 scope (deferred)

- Card status list = checklist inside a card.

### Accounts round (approved 2026-11-13)

- Self-service change password on a Settings page (current password verified).
- Email confirmation on registration: unconfirmed users cannot sign in; the link opens
  a frontend `/confirm/<token>` page; a global admin can confirm manually. Mail is
  captured by Mailpit in the shared base env (no real SMTP in v1).
- Global-admin users list at `/admin/users`: confirm accounts, promote/demote global
  admins. Project admins do NOT get access (global admin only).

### Steps

1. `[x]` **Scaffold** — Symfony 8.1 skeleton + Docker stack (verified `GET /api/ping`
   through nginx → php-fpm → QueryBus) + frontend (Vue/Vite/Tailwind/PrimeVue unstyled,
   dark-first LoginView + ShellView, API client, dev proxy verified).
2. `[x]` **Domain model** — User, Project, ProjectMember, Board, Column, Card, Label,
   Comment, CardActivity entities + invariants + domain events; Doctrine mappings +
   migrations (manual FKs for plain uuid columns); CardMoveService/ColumnReorderService;
   timestamps timestamptz(6) via custom type + columnDefinition (see Architecture note);
   unit tests (host) + repository integration tests against real Postgres (container).
3. `[x]` **Auth & users** — registration like the shop project (point me at the shop repo
   when we start), login, logout, current-user endpoint, global admin promotion.
4. `[x]` **Projects** — CRUD, member picker, role management, default columns on create,
   archive/restore + hard delete.
5. `[x]` **Board & columns** — board page per project, column CRUD + drag reorder.
6. `[x]` **Cards** — CRUD + archive, labels, due dates, assignees, drag&drop between
   columns (SortableJS), own-card edit rules.
7. `[x]` **Comments & activity** — markdown comments on any card, activity log entries
   for card events.
8. `[x]` **Permissions hardening** — voters for every route, confirm the admin-override
   assumption, integration tests for the ACL matrix.
9. `[ ]` **Realtime** — OPEN DECISION: Mercure (user distrusts it) vs Centrifugo vs
   simple polling — discuss options before implementing. Notes and criteria:
   `docs/plans/realtime.md`.
10. `[x]` **Polish & release** — theme foundation (light + dark), empty/error states,
    built frontend served directly by angie, Yarn 4 migration, security-interface
    cleanup (Domain `User` + `SecurityUser` adapter). Production deployment docs
    deferred by decision (docs-only for now).
11. `[x]` **Change password** — `POST /api/auth/password` (current password verified,
    confirmation must match) + Settings page.
12. `[x]` **Email confirmation** — `User` confirmation token fields + migration (existing
    users backfilled confirmed); `UserRegistered` event → Mailer listener (Mailpit);
    public `POST /api/auth/confirm` + `/confirm/:token` page; login gated on
    `isConfirmed()` after the password check.
13. `[x]` **Admin users list** — `GET /api/users` + confirm/promote/demote actions gated
    by `ProjectAccessVoter::GLOBAL_ADMIN`; promote/demote commands switched email →
    `userId` (CLI resolves email); `/admin/users` table in the frontend.

After the baseline works: dedicated discussion of SaaS scenarios (multi-tenant flavor)
before any of it is built.
