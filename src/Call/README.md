# Call module

Audio/video calls between project members: WebRTC mesh in the browser (up to 4 people,
one room per project). Signaling goes through the backend and Centrifugo; media goes
browser to browser, or through coturn when a direct connection is impossible. No
external services.

## Requirements

- **Centrifugo** with a `call` namespace (personal channel `call:<userId>`):
  ```json
  { "name": "call", "allow_subscribe_for_client": false }
  ```
- **Redis**: room state, hash `call:room:<projectId>` (userId → session + heartbeat,
  TTL 60 s). No database tables.
- **coturn** (STUN/TURN) reachable from the internet, with shared-secret auth
  (`use-auth-secret`, `static-auth-secret`). Ports: 3478 udp/tcp, 5349 tcp (TLS), and
  the relay range (`min-port`/`max-port`) over udp.
- **HTTPS** for the site: browsers allow the microphone only in a secure context.

## Configuration

`.env.local`:

```
CALL_ICE_URLS=stun:turn.example.com:3478,turn:turn.example.com:3478?transport=udp,turn:turn.example.com:3478?transport=tcp,turns:turn.example.com:5349?transport=tcp
CALL_TURN_SECRET=<same value as static-auth-secret in coturn>
```

- `CALL_ICE_URLS`: comma-separated `stun:` / `turn:` / `turns:` URLs. `turn*` entries get
  temporary credentials (TURN REST scheme, 12 h); they are skipped if the secret is
  empty.
- Other parameters live in `config/packages/call.php`: `call.max_participants` (4),
  `call.participant_ttl` (60 s), `call.turn_credential_ttl` (43200 s).

Centrifugo settings (`CENTRIFUGO_*`) are shared with the realtime board updates.

## API

All routes need an authenticated user; project routes need project membership
(`MembershipGuard`).

| Route | Purpose |
| --- | --- |
| `GET /api/call/token` | Centrifugo connection + subscription tokens for `call:<userId>` |
| `GET /api/call/rooms` | non-empty rooms of the user's projects |
| `POST /api/projects/{projectId}/call/join` | join (`sessionId`); returns participants and ICE servers; 409 if full |
| `POST …/call/leave` | leave (also sent with `sendBeacon` on page close) |
| `POST …/call/heartbeat` | every 20 s; `{inRoom: false}` means the user dropped out |
| `POST …/call/signal` | relay `offer` / `answer` / `ice` / `state` to a participant |
| `POST …/call/invite` | ring one project member |
| `POST …/call/decline` | decline an invitation |

Messages on `call:<userId>`: `room.changed`, `invite`, `invite.declined`, `signal`.

## Frontend

`frontend/src/call/`. Integration points:

- `<CallLayer/>` once in the app shell (`ShellView.vue`): realtime connection, call
  window, incoming call popup. The call survives page navigation.
- `<CallButton :project-id/>` in the project header.
- `<CallBadge :project-id/>` / `<CallBadge :project-id :user-id/>`: "in a call"
  markers.

Connection diagnostics are logged to the browser console with the `[call]` prefix.

## Tests

- Unit: `tests/Unit/Call/`
- Redis repository: `tests/Integration/Call/`
- Routes: `tests/Integration/Security/AclMatrixTest.php`
