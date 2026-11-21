# Calls: server setup

Audio/video calls (`src/Call`, `frontend/src/call`) need three things on the server:

1. **Centrifugo** with the `call` namespace — signalling (invites, SDP, ICE) goes over the
   personal channel `call:<userId>`.
2. **coturn** — STUN/TURN, so browsers behind NAT or on mobile networks can connect.
3. **App env** — Centrifugo and TURN settings for Symfony.

Placeholders: `<site>` — the site domain, `<turn-host>` — host name for TURN (may be the
site domain), `<server-ip>` — public IP, `<secret-…>` — values from `openssl rand -hex 32`.

The call button on the board is hidden until the browser has subscribed to its call
channel. If it is missing, open the browser console and look for `[call]` — see
[Console errors](#console-errors).

## 1. Centrifugo

`channel.namespaces` must contain all three namespaces, as in
`docker/centrifugo/config.json`:

```json
{ "name": "kanban", "allow_subscribe_for_client": false, "history_size": 100, "history_ttl": "300s", "force_recovery": true },
{ "name": "call", "allow_subscribe_for_client": false },
{ "name": "user" }
```

`client.allowed_origins` must contain `https://<site>`.

- Docker: see [docker/README.md](../docker/README.md) (service `kanban-centrifugo`, the
  config is mounted from the repo).
- Without Docker: binary in `/usr/local/bin/centrifugo`, config in
  `/etc/centrifugo/config.json`, secrets in `/etc/centrifugo/env` (mode 600), systemd unit
  with `EnvironmentFile=/etc/centrifugo/env` and
  `ExecStart=/usr/local/bin/centrifugo -c /etc/centrifugo/config.json`.

Centrifugo reads the config at start only: **restart it after every config change**
(`docker restart kanban-centrifugo` or `systemctl restart centrifugo`).

The web server proxies the browser WebSocket `/_ws_connection/` to Centrifugo's
`/connection/` (see `docker/proxy/kanban.local.conf`).

## 2. coturn

Install `coturn`. TLS (`turns:` on 5349) needs a certificate for `<turn-host>` readable by
the `turnserver` user — copy it to `/etc/coturn/certs` (mode 600, owner `turnserver`) and
re-copy after every renewal.

`/etc/turnserver.conf`:

```
listening-port=3478
tls-listening-port=5349
external-ip=<server-ip>
min-port=49160
max-port=49999

realm=<turn-host>
server-name=<turn-host>
use-auth-secret
static-auth-secret=<secret-turn>

cert=/etc/coturn/certs/fullchain.pem
pkey=/etc/coturn/certs/privkey.pem
no-tlsv1
no-tlsv1_1

fingerprint
no-cli
no-multicast-peers
stale-nonce=600
user-quota=12
total-quota=1200
log-file=syslog
simple-log

# Do not relay into private networks
no-loopback-peers
denied-peer-ip=0.0.0.0-0.255.255.255
denied-peer-ip=10.0.0.0-10.255.255.255
denied-peer-ip=100.64.0.0-100.127.255.255
denied-peer-ip=127.0.0.0-127.255.255.255
denied-peer-ip=169.254.0.0-169.254.255.255
denied-peer-ip=172.16.0.0-172.31.255.255
denied-peer-ip=192.168.0.0-192.168.255.255
denied-peer-ip=::1
denied-peer-ip=fc00::-fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff
denied-peer-ip=fe80::-febf:ffff:ffff:ffff:ffff:ffff:ffff:ffff
```

`chmod 640 /etc/turnserver.conf`, group `turnserver`, then `systemctl restart coturn`.

Open ports (host firewall and the hosting panel): `3478/udp`, `3478/tcp`, `5349/tcp`,
`5349/udp`, `49160-49999/udp`.

TURN passwords are temporary (12 h): the app derives them from `static-auth-secret`
(TURN REST scheme); browsers never see the secret.

## 3. App env

```
CENTRIFUGO_URL=http://<centrifugo-host>:8000
CENTRIFUGO_WS_URL=wss://<site>/_ws_connection/websocket
CENTRIFUGO_TOKEN_SECRET=<secret-token>
CENTRIFUGO_API_KEY=<secret-api>
CALL_ICE_URLS=stun:<turn-host>:3478,turn:<turn-host>:3478?transport=udp,turn:<turn-host>:3478?transport=tcp,turns:<turn-host>:5349?transport=tcp
CALL_TURN_SECRET=<secret-turn>
```

Pairs that must match:

| App | Server |
| --- | --- |
| `CENTRIFUGO_TOKEN_SECRET` | Centrifugo `CENTRIFUGO_CLIENT_TOKEN_HMAC_SECRET_KEY` |
| `CENTRIFUGO_API_KEY` | Centrifugo `CENTRIFUGO_HTTP_API_KEY` |
| `CALL_TURN_SECRET` | coturn `static-auth-secret` |

## 4. Verify

- `ss -lunpt | grep turnserver` — ports 3478 (udp, tcp) and 5349.
- TURN from outside: WebRTC samples → Trickle ICE, `turn:<turn-host>:3478` with a
  temporary login (`<unix time + 3600>:test`, password
  `base64(hmac-sha1(<secret-turn>, login))`) — a `relay` candidate with `<server-ip>`
  must appear.
- Site: the call button is visible on a board, the console has no `[call] Calls disabled`.

## Console errors

All messages start with `[call] Calls disabled:` and are printed once per page load.

| Message | Meaning | Fix |
| --- | --- | --- |
| `the call token request (/api/call/token) failed` | The app could not issue Centrifugo tokens | App env: `CENTRIFUGO_WS_URL`, `CENTRIFUGO_TOKEN_SECRET`; app logs |
| `cannot connect to Centrifugo at <url>` | WebSocket does not open | Centrifugo running? Proxy `/_ws_connection/` in place? `CENTRIFUGO_WS_URL` correct? `allowed_origins` contains the site? |
| `Centrifugo rejected the connection token (3500 …)` | Token signed with another secret | `CENTRIFUGO_TOKEN_SECRET` = `CENTRIFUGO_CLIENT_TOKEN_HMAC_SECRET_KEY`, restart Centrifugo |
| `Centrifugo has no "call" namespace (call:…: 102 unknown channel)` | Namespace `call` missing in the running config | Add it (section 1), restart Centrifugo |
| `Centrifugo denied the call channel subscription (… 103 …)` | Subscription token rejected | Same secret pair as above; Centrifugo and app clocks in sync |
| `unsubscribed from the call channel (… <code> …)` | Any other refusal | Look the code up in Centrifugo docs (server error codes) |

Calls connect but there is no sound/video between different networks — TURN problem:
check ports, `CALL_ICE_URLS`, and that `CALL_TURN_SECRET` equals `static-auth-secret`
(otherwise coturn answers `401`).
