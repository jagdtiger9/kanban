# Docker reference configs

Example configuration for the pieces this project adds to the shared Docker env at
`~/work/Docker`. Nothing is wired automatically — copy each block into the base env by
hand. The app itself runs in the shared `fpm` container; there is no project compose
stack.

## What goes where

| Repo file | Destination in the base env |
| --- | --- |
| `compose.example.yaml` | merge the `kanban-centrifugo` service into `~/work/Docker/custom.yaml` |
| `.env.example` | secrets into `~/work/Docker/.env`; app values into this project's `.env` |
| `centrifugo/config.json` | `~/work/Docker/data/config/centrifugo/config.json` (the running container's bind mount) |
| `proxy/kanban.local.conf` | `~/work/Docker/data/config/hosts/kanban.local.conf` |
| `worker/kanban-messenger.conf` | `~/work/Docker/data/config/worker/kanban-messenger.conf` |

`HOSTS_DATA` is `./../web/` relative to `~/work/Docker`, so this directory is
`/home/aljerom/work/web/kanban/docker` on the host and `/var/www/kanban/docker` inside
the containers.

## What the base env must provide

- profiles `proxy_ws`, `main`, and `database_pg` running (`main` includes `php-worker`,
  RabbitMQ, Redis, and Mailpit)
- `127.0.0.1 kanban.local` in `/etc/hosts`
- the `docker_net` network (the base env's `net`, 192.168.17.0/24)

## Setup

### 1. Secrets

Generate two values once:

```bash
openssl rand -hex 32   # subscription token secret
openssl rand -hex 32   # HTTP API key
```

Add them to `~/work/Docker/.env` as shown in `.env.example`, then add the app-side
variables to this project's `.env`. The two paired names must share the same value —
Centrifugo signs with one name, Symfony verifies with the other.

### 2. TLS certificate

The proxy mounts `~/work/Docker/data/letsencrypt` at `/etc/nginx/ssl`:

```bash
cd ~/work/Docker
make cert.local.create DOMAIN=kanban.local
cp .cert/kanban.local.crt .cert/kanban.local.key data/letsencrypt/
```

### 3. Vhost

Copy `proxy/kanban.local.conf` to `~/work/Docker/data/config/hosts/` and reload the
proxy:

```bash
make -C ~/work/Docker nginx.reload
```

The `/_ws_connection/` location rewrites to Centrifugo's native `/connection/` path
upstream, so the public path does not expose the server's internal route.

### 4. Messenger worker

Copy `worker/kanban-messenger.conf` to `~/work/Docker/data/config/worker/` and restart
the worker so supervisor picks it up:

```bash
docker restart php-worker
```

The program recycles every 60 s (`--time-limit=60`), so code changes need no manual
restart.

### 5. Centrifugo

Merge the service from `compose.example.yaml` into the `services:` map of
`~/work/Docker/custom.yaml`, copy the config into the base env, then start it:

```bash
mkdir -p ~/work/Docker/data/config/centrifugo
cp ~/work/web/kanban/docker/centrifugo/config.json ~/work/Docker/data/config/centrifugo/
cd ~/work/Docker
docker compose up -d kanban-centrifugo
```

### Verify

```bash
docker ps --filter name=kanban-centrifugo     # running and on docker_net
docker logs kanban-centrifugo                 # no config errors
make ping                                     # API reachable through angie
```

Open the board in two browser tabs and move a card — the second tab should refresh over
the WebSocket without a page reload.

## Environment variables

| Base env (`~/work/Docker/.env`) | App env (`.env`) | Purpose |
| --- | --- | --- |
| `CENTRIFUGO_CLIENT_TOKEN_HMAC_SECRET_KEY` | `CENTRIFUGO_TOKEN_SECRET` | HS256 subscription token secret — must match |
| `CENTRIFUGO_HTTP_API_KEY` | `CENTRIFUGO_API_KEY` | server HTTP API key — must match |
| — | `CENTRIFUGO_WS_URL` | browser WebSocket endpoint (`wss://kanban.local/_ws_connection/websocket`) |
| — | `CENTRIFUGO_URL` | server-to-server publish URL (`http://kanban-centrifugo:8000`) |

## Updating configs

Edit the copy in this repo first, then re-copy it into the base env:

| Changed file | Apply with |
| --- | --- |
| `proxy/kanban.local.conf` | `make -C ~/work/Docker nginx.reload` |
| `worker/kanban-messenger.conf` | `docker restart php-worker` |
| `centrifugo/config.json` | copy to `~/work/Docker/data/config/centrifugo/`, then `docker restart kanban-centrifugo` |
| `compose.example.yaml` | `docker compose up -d kanban-centrifugo` |

## Troubleshooting

- **502 on `/_ws_connection/websocket`** — the Centrifugo container is not running, or it
  is not attached to `docker_net` (the proxy resolves it by container name
  `kanban-centrifugo`).
- **WebSocket connects but subscription is denied** — the token secret in the app `.env`
  does not match the server's; the `kanban` namespace requires an HS256 token.
- **Vhost changes have no effect** — reload the proxy; angie only re-reads `conf.d` on
  reload.
- **Worker does not consume** — the fragment is missing from
  `data/config/worker/`, or `php-worker` was not restarted after adding it.

Production deployment is deferred; these configs target the shared development env.
