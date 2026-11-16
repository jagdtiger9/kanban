# Conventions

How code is written here. The agent contract is in [AGENTS.md](../AGENTS.md); implementation
decisions are in [plans/](plans/). This file is the self-contained local convention set for
collaborators; the rules are enforced by review and tests, not by a linter.

## Principles as applied

### DDD

- Four layers: Domain → Application → Infrastructure/Interface, dependencies inward only.
  The interface layer is represented by `Infrastructure/Controller` and `Infrastructure/Cli`.
- The Domain layer has no Symfony imports. Entities carry Doctrine mapping attributes
  (the project's pragmatic exception) — mapping only, no ORM logic in the domain.
- Entities enforce their own invariants. Raw setters that bypass a rule are not allowed.
- Cross-entity logic belongs in a domain service (`CardMoveService`,
  `ColumnReorderService`), not in an entity or a handler.
- Side effects are expressed as domain events, never as direct calls from a handler.
- One module per business capability. A module may use another module's Domain and
  Application contracts, never its Infrastructure.

### SOLID

- **Single responsibility** — one handler per use case; one guard per authorization
  decision; one gateway per external system.
- **Open/closed** — extend behavior with a new message and handler, not with conditionals
  inside an existing one.
- **Liskov** — every repository or gateway implementation honors its interface contract;
  callers never assume the concrete class.
- **Interface segregation** — ports stay small and purpose-specific (`NotifierInterface`,
  `RealtimeNotifierInterface`).
- **Dependency inversion** — inject interfaces; implementations are wired in
  `Infrastructure/`.
- No premature abstraction: three similar cases before extracting a helper.

### GRASP

- **Information expert** — the entity that owns the data owns the transition
  (`Card::moveTo`, `Project::archive`).
- **Creator** — aggregate roots create their internals; controllers never construct domain
  objects.
- **Controller** — controllers only translate HTTP into a message and back.
- **Protected variations** — external systems sit behind ports: Telegram, Centrifugo,
  mailer, token issuance.
- **Low coupling / high cohesion** — modules interact through application/domain
  contracts, not by reaching into each other's infrastructure.

### CQRS

- Commands mutate, queries read. They have separate message classes and separate handlers.
- One use case per handler; no shared handlers for "similar" operations.
- Buses and the input resolver come from `aljerom/symfony-boilerplate`. Do not hand-roll
  them.

### TDD

- Every new Domain and Application class gets unit tests.
- Repositories, the ACL matrix, and other database behavior get integration tests against
  the real PostgreSQL.
- A feature is done when a caller-level test exercises it: an HTTP request for a
  controller, a handler call for a use case — not merely "it did not throw".

## Backend

### Messages and handlers

Messages live in `<Module>/Application/Message/{Command,Query}` and implement the
boilerplate interfaces. Request-supplied properties carry validation constraints; `actorId`
is unvalidated because the controller fills it in after hydration.

```php
final class MoveCardCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $cardId,
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $targetColumnId,
        #[Assert\NotBlank]
        #[Assert\GreaterThanOrEqual(0)]
        public int $targetIndex,
        public string $actorId = '',
    ) {
    }
}
```

Handlers are `#[AsMessageHandler]` classes with `__invoke`, registered automatically. They
load aggregates through repository interfaces, call the domain, and persist through the
repository. They never build queries or manage transactions.

```php
#[AsMessageHandler]
final class MoveCardCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private ColumnRepository $columnRepository,
        private CardRepository $cardRepository,
        private CardMoveService $moveService,
    ) {
    }

    public function __invoke(MoveCardCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);
        $card = $this->guard->requireCard(Uuid::fromString($command->cardId), $actorId);
        $board = $this->guard->resolveBoard($card);

        $targetColumn = $this->columnRepository->findById(Uuid::fromString($command->targetColumnId));
        if ($targetColumn === null || !$targetColumn->boardId()->equals($board->id())) {
            throw new ColumnNotFoundException('Column not found');
        }

        $oldColumnId = $card->columnId();
        $oldColumnCards = $this->cardRepository->findByColumn($oldColumnId);
        $newColumnCards = $this->cardRepository->findByColumn($targetColumn->id());

        $this->moveService->move(
            $card,
            $targetColumn->id(),
            $command->targetIndex,
            $oldColumnCards,
            $newColumnCards,
            $actorId,
        );

        $touched = $oldColumnCards;
        if (!$targetColumn->id()->equals($oldColumnId)) {
            $touched = array_merge($touched, $newColumnCards);
        }
        foreach ($touched as $touchedCard) {
            $this->cardRepository->save($touchedCard);
        }
    }
}
```

### Controllers

Controllers extend `AbstractController`, stay thin, and take typed message parameters.
They assign `actorId` from the authenticated user, dispatch, and return a response. They
perform no access checks — authorization belongs to the handlers (see
[Guards and authorization](#guards-and-authorization)). No `Request`, no `json_decode`,
no manual DTO construction, no domain object construction.

```php
public function moveCard(MoveCardCommand $command, Security $security): Response
{
    $command->actorId = $this->actorId($security);
    $this->commandBus->dispatch($command);

    return new Response(status: Response::HTTP_NO_CONTENT);
}
```

Authentication is enforced by the firewall and `access_control` in
`config/packages/security.php`: every `/api/*` route requires `ROLE_USER` unless it is
listed as `PUBLIC_ACCESS`. Do not add `#[IsGranted]` or `denyAccessUnlessGranted` calls to
controllers.

Routes live in `config/routes.php` as named arrays. Do not put `#[Route]` on base
controllers; the routing config is the single place to see the API surface.

`MessageValueResolver` hydrates the JSON body for POST/PUT and the query string for other
methods. That is why JSON updates use PUT, never PATCH.

### Validation and errors

- Validation constraints belong on the message, not in the controller.
- The bundle's listener turns `ValidationException` into the field-error envelope that the
  frontend's `ApiError` consumes.
- Domain exceptions cover not-found and forbidden cases; `ApiExceptionResponseSubscriber`
  maps them to HTTP responses. A new domain exception must be added to that mapping —
  otherwise it surfaces as a generic 500.
- Do not duplicate validation in controllers or handlers.

### Repositories and transactions

- Interfaces in `Domain/Repository/`; implementations in `Infrastructure/Doctrine/`.
- Implementations contain no business rules and no permission checks.
- No `flush()` in handlers. The command bus'
  `doctrine_transaction` middleware commits after the handler returns.
- Handlers never inject `EntityManager` or `QueryBuilder`. If a query is needed, add a
  repository method behind the interface.

### Domain events

- Entities implement `DomainEventAwareInterface` and use `EventStoreTrait`.
- State-changing methods record events internally; handlers never dispatch.
- Event classes use the boilerplate `DomainEventTrait` and live in `Domain/Event/`.
- The outbox writes and publishes them; event handlers are ordinary
  `#[AsMessageHandler]` classes on the event bus.
- If a side effect must be atomic with the state change, it belongs in the command
  handler. Anything else belongs in an event handler.

### Guards and authorization

Symfony handles authentication only: the firewall and `access_control` decide who may
call the API at all. Authorization is enforced in the Application layer, so the same rule
applies regardless of which interface (HTTP, CLI, Messenger) dispatches the message.

- Guards live in `<Module>/Application/Service/`: `MembershipGuard` (project member or
  admin; a global admin counts as a project admin everywhere), `CardGuard` (card
  membership, card editor, comment editor/manager) and `GlobalAdminGuard`. Guards read the
  live user, never the roles frozen in the token.
- The handler calls the guard before changing anything, using the unvalidated `actorId`
  from the message. Guard violations throw domain exceptions
  (`ProjectForbiddenException`, `CardForbiddenException`, `CommentForbiddenException`,
  `AccessForbiddenException`); `ApiExceptionResponseSubscriber` maps them to 403. Missing
  aggregates throw `*NotFoundException` and map to 404.
- Controllers perform no access checks and there are no Symfony voters: a voter duplicates
  the guard rules at the HTTP edge and cannot protect CLI or Messenger callers.
- Exceptions to handler authorization:
  - public endpoints — ping, register, login and email confirmation by token (listed in
    `access_control`);
  - actor-scoped queries — `ListProjectsQuery` filters by `actorId`;
  - CLI-only commands (`app:user:promote-admin`, `app:user:demote-admin`,
    `app:user:reset-pass`) — shell access is the trust boundary, so those messages carry no
    `actorId`.
- Cross-entity rules go in `Domain/Service/` (`CardMoveService`, `ColumnReorderService`).
- `tests/Integration/Security/AclMatrixTest` is the executable policy: one expectation per
  route × actor, plus a coverage test that fails when an `api_*` route is missing from the
  matrix.

### External systems

- Ports live in Domain or Application; adapters live in Infrastructure. Telegram,
  Centrifugo publishing, and token issuance already follow this pattern.
- No HTTP client calls from controllers or handlers. Add a gateway or notifier adapter.

### Configuration, schema, timestamps

- Symfony config uses the `App::config([...])` array format. New bundle config goes in
  `config/packages/<bundle>.php`.
- Doctrine mappings are registered per module in `config/packages/doctrine.php`.
- Services rely on `autowire` and `autoconfigure`; explicit registration only for
  third-party classes or bindings.
- Schema changes: generate a migration, review it, run the project's migration command.
  Never `doctrine:schema:update` or hand-written SQL.
- Timestamps use `datetimetz_micro` (`DatetimeTzMicroType` plus
  `columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE'`). Do not introduce a plain datetime
  column.

## Packages: symfony-boilerplate and simple-hydrator

`aljerom/symfony-boilerplate` supplies the command/query buses, `MessageValueResolver`,
domain-event contracts, the outbox listener and publisher, and the event bus.
`aljerom/simple-hydrator` performs the input hydration behind the resolver.

- Both are consumed through Composer's path repository `../packages/*` and symlinked into
  `vendor/`. A change in `../packages/<name>` is live in this project immediately.
- After updating the packages, refresh the path repositories and run the test suite.
- Do not copy their classes into `src/` or hand-roll an equivalent. Use the base classes,
  traits, and listeners; if behavior is missing, change the package and record the
  decision when its public API changes.
- Do not deserialize request bodies manually or dispatch domain events directly; those
  are the resolver's and the outbox's jobs.

## Project constraints

- Do not replace `symfony-boilerplate` or `simple-hydrator`; do not add a second ORM or
  query layer. Repositories behind interfaces are the only persistence seam.
- PostgreSQL is assumed (`TIMESTAMP(6) WITH TIME ZONE`, UUIDs, migration SQL); do not
  switch databases.
- Centrifugo is the only realtime transport: no Mercure, Pusher, or polling, and
  publications carry invalidations, not state.
- PrimeVue unstyled plus Tailwind is the styling contract; do not add a styled UI kit or a
  second component library.
- Pinia only (no Vuex), PHPUnit only (no Pest), and one HTTP client (`api/client.ts`).
- Do not serve the app from the host.

## Frontend

### Vue and TypeScript

- `<script setup>` only — no Options API, no `defineComponent` wrapper.
- Props and emits are explicitly typed with `defineProps<{...}>()` / `defineEmits<{...}>()`.
- No `any`. Use `unknown` and narrow, or define the type.
- API response and request types live in the `frontend/src/api/` modules, not inline in
  components.
- Pinia stores have typed state, getters, and actions; state changes only inside actions.
- Reusable stateful logic goes in composables; components stay focused on rendering.
- All HTTP goes through `api()` from `api/client.ts`. Do not add `fetch` calls in
  components or a second HTTP client.
- Markdown is rendered with `marked` and sanitized with `DOMPurify`; never bind unsanitized
  HTML.

### Styling

- PrimeVue is unstyled. Component styles live in `frontend/src/primevue/preset.ts` as
  Tailwind classes.
- Use semantic theme tokens (`bg-page`, `bg-panel`, `text-strong`, `text-muted`,
  `border-line`, `brand`, `danger`, `warn`) rather than raw palette colors. The dark and
  light themes share the same class set; dark is the default.
- Tailwind scale units: never write `h-4rem`, `w-4rem`, `p-4rem`, `gap-4rem`. Use
  `N = rem × 4`: `h-16` for 4rem, `h-40` for 10rem, `max-w-80` for 20rem. Tailwind 4's
  dynamic scale accepts any integer.
- Arbitrary syntax is only for values that are not multiples of 0.25rem, e.g.
  `h-[3.125rem]`.

## Testing

- **Unit** (`tests/Unit`) — Domain and Application classes in isolation. No database, no
  HTTP, no framework bootstrap. Handlers use repository doubles.
- **Integration** (`tests/Integration`) — repositories against the real PostgreSQL, plus
  the route × actor ACL matrix. Tests use `WebTestCase` where HTTP behavior matters. The
  matrix must cover every `api_*` route; `testEveryApiRouteIsCovered` enforces it.
- Tests route domain events through `sync://`, so no worker is involved.
- Run the full suite with the project's test command. For a focused run, use
  `bin/phpunit --filter MoveCard` in the project environment.
- The GrumPHP pre-commit hook runs the `unit` testsuite only (`bin/phpunit --testsuite
  unit`); integration tests stay in the full suite.
- Definition of done: unit tests for new Domain/Application behavior, integration coverage
  for new repository behavior or access rules, and the full suite green.

## Cookbook

### Add a use case and endpoint

1. Add a message under `Application/Message/Command` or `Query`, with constraints on
   request-supplied fields and an unvalidated `actorId`.
2. Add an `#[AsMessageHandler]` handler with `__invoke`; keep it thin and one-use-case.
   Call the appropriate guard with `actorId` before changing state.
3. Add a controller action with the typed message parameter, the `actorId` assignment and
   `dispatch`. No access check in the controller.
4. Register the route in `config/routes.php`.
5. Add a unit test for the handler, including the forbidden case, and add the route's
   expectations to the ACL matrix (the coverage test fails for a missing route).

### Add an entity field

1. Add the property and update the entity's methods and invariants.
2. Use `datetimetz_micro` for timestamps.
3. Generate a migration, review the diff, run the project's migration command.
4. Update the affected tests and any invariant documentation.

### Add a realtime invalidation

1. Record a domain event from the entity method that changes state.
2. Map the event class to a channel in `RealtimeEventForwarder`.
3. Confirm the module's event namespace is covered by the async routing pattern.
4. The frontend needs no payload handling — `useBoardRealtime` refetches on invalidation.

### Add a module

1. Create `Domain`, `Application`, and `Infrastructure` directories.
2. Register Doctrine mappings in `config/packages/doctrine.php` if the module owns entities.
3. Services are autoconfigured; add the route in `config/routes.php`.
4. Add the event namespace to async routing if the module records domain events.
5. Add unit tests, and integration tests for repositories or access rules.

## Do not

- Inject `EntityManager` or `QueryBuilder` into a handler; call `flush()` there.
- Put business rules or access checks in repositories, gateways, or controllers; enforce
  access in Application guards.
- Add Symfony voters, `#[IsGranted]`, or `denyAccessUnlessGranted` checks to controllers.
- Use `Request`, `json_decode`, or `#[MapRequestPayload]` in controllers.
- Add `#[Route]` attributes to controllers or move routing out of `config/routes.php`.
- Use PATCH for JSON updates.
- Run `doctrine:schema:update` or write schema SQL by hand.
- Add a plain datetime column.
- Dispatch domain events from handlers.
- Create shared handlers for "similar" operations.
- Add `any`, use the Options API, or mutate Pinia state outside actions.
- Write bare rem classes in Tailwind (`h-4rem`).
- Call `fetch` directly in a component.
- Add comments that explain what the code does; comments are for non-obvious *why*.
- Keep dead code commented out, or add defensive code for impossible states.
