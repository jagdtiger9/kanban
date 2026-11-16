# AGENTS.md

Kanban is a small self-hosted Trello-style board: a Symfony 8.1 API and a Vue 3 SPA,
with asynchronous side effects handled by a Messenger worker. This file is the working
contract for coding agents. Humans start at [README.md](README.md).

## Read before changing code

| Document | What it settles |
| --- | --- |
| [README.md](README.md) | Project overview, stack, and entry points |
| [docs/conventions.md](docs/conventions.md) | Project-specific DDD/CQRS, package usage, frontend, testing, cookbook |
| [docs/plans/](docs/plans/) | Implementation decision records |

The docs are the source of truth. If code and docs disagree, stop and report the
discrepancy before proceeding; update the docs together with the code change.

Cross-project stack patterns live in the `symfony-stack-conventions` skill — load it
before backend or frontend changes. If the skill is unavailable, `docs/conventions.md`
is the self-contained local copy. Environment commands live in the `shared-docker-env`
skill; if that skill is unavailable, ask for the command to run — do not guess or
substitute a host command.

## Principles

- Dependencies point inward: Domain → Application → Infrastructure/Interface. The Domain
  layer has no Symfony imports; entities carry Doctrine mapping attributes only.
- Controllers receive typed CQRS message parameters hydrated by `MessageValueResolver`.
  No `Request`, no `json_decode`, no manual DTO wiring. Assign `actorId` from the
  authenticated user after hydration. Controllers do not perform access checks.
- Authorization lives in Application guards (`MembershipGuard`, `CardGuard`,
  `GlobalAdminGuard`) called by handlers; there are no Symfony voters. Symfony handles
  authentication only (`access_control` in `config/packages/security.php`).
- Handlers are thin and one-use-case. Never inject `EntityManager` or `QueryBuilder`;
  inject repository interfaces from `Domain/Repository/`. No manual `flush()` — the
  command bus' `doctrine_transaction` middleware owns the transaction.
- Entities enforce their invariants and record their own domain events. Handlers never
  dispatch events.
- Symfony config uses the `App::config([...])` array format. Routes live in
  `config/routes.php`, not in `#[Route]` attributes on controllers.
- JSON-body updates use PUT, never PATCH. The resolver hydrates POST/PUT bodies; other
  methods read the query string.
- Schema changes go through a generated migration and the project's migration command;
  never `doctrine:schema:update` or hand-written SQL.
- Timestamps use `datetimetz_micro` (`DatetimeTzMicroType` plus
  `columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE'`). Do not introduce plain datetime
  columns.
- Vue uses `<script setup>`, typed `defineProps`/`defineEmits`, and no `any`. Pinia state
  changes only through actions.
- Tailwind uses scale units (`h-16` = 4rem); arbitrary `h-[Xrem]` only for values that
  are not multiples of 0.25rem.
- Every new Domain and Application class gets unit tests. Repositories and the ACL matrix
  get integration tests against the real PostgreSQL. Run the project's test suite.
- No new dependency, major upgrade, or architectural deviation without an explicit
  decision recorded in `docs/plans/`.

## Working rules

These mirror the global agent rules so this file stays self-contained if the global
setup is unavailable.

- For multi-step or heavy-logic work, state the plan as a numbered list of discrete steps
  and wait for explicit approval before changing code. Then execute one step at a time and
  wait for approval after each. Single-file, obviously scoped changes need no plan.
- Keep the working context small: never keep copied debug, trace, log, SQL, or HTML output
  in the conversation — write it to a temp file and report the conclusion or a one-line
  summary. Read files in ranges and summarize rather than dumping them.

## Discover, ask, don't guess

Framework and package APIs change. Check the project before assuming:
`bin/console about`, `bin/console debug:router`, `bin/console debug:container`,
`bin/console debug:autowiring <id>`, `bin/console debug:config <bundle>`. Read the
installed sources under `vendor/` when the docs and code disagree — then fix the docs.
Run console commands through the shared environment (`shared-docker-env` skill) or the
project's documented wrapper.
Or just ask for clarification.
