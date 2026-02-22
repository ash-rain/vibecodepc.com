# VibeCodePC

A pre-configured Raspberry Pi 5 that works as a personal AI-powered coding workstation. Plug it in, scan the QR code, and start coding with AI.

The repo contains two Laravel apps and shared packages:

```
vibecodepc.com/
├── device/              # On-device app (wizard, dashboard, project manager)
├── cloud/               # Cloud edge app (vibecodepc.com — pairing, billing, tunnel API)
├── packages/            # Shared PHP packages (DTOs, enums, API contracts)
├── docker-compose.yml   # Device services
└── cloud/docker-compose.yml  # Cloud services
```

## Prerequisites

Install Docker Desktop for your platform:

| Platform | Link |
|---|---|
| macOS | https://docs.docker.com/desktop/install/mac-install/ |
| Windows | https://docs.docker.com/desktop/install/windows-install/ |
| Linux | https://docs.docker.com/desktop/install/linux/ |

Make sure Docker is running before continuing.

## Running the Device App

The device app simulates the Raspberry Pi environment. It runs a Laravel app with SQLite, Redis, background workers, and a cloudflared tunnel container.

```bash
docker compose up -d
```

| Service | Description | Port |
|---|---|---|
| `device` | Laravel app (wizard + dashboard) | [localhost:8081](http://localhost:8081) |
| `cloudflared` | Cloudflare tunnel (waits for token from wizard) | shares device network |
| `device-scheduler` | Runs `php artisan schedule:work` | — |
| `device-queue` | Queue worker for scaffolding/cloning jobs | — |
| `redis-device` | Valkey (Redis-compatible) for cache/queue | localhost:6380 |

```bash
docker compose logs -f device       # View device logs
docker compose logs -f cloudflared  # View tunnel logs
docker compose exec device php artisan test --compact  # Run tests
docker compose down                 # Stop everything
```

## Running the Cloud App

The cloud app is the public-facing edge at vibecodepc.com. It handles device pairing, user accounts, Stripe billing, and tunnel provisioning via the Cloudflare API. It uses PostgreSQL and Redis.

```bash
docker compose -f cloud/docker-compose.yml up -d
```

| Service | Description | Port |
|---|---|---|
| `cloud` | Laravel app (pairing, admin panel, API) | [localhost:8082](http://localhost:8082) |
| `cloudflared` | Cloudflare tunnel (waits for token) | shares cloud network |
| `postgres` | PostgreSQL 17 | localhost:5432 |
| `redis-cloud` | Valkey (Redis-compatible) for session/cache/queue | localhost:6381 |

To activate the cloud tunnel, write a token into the shared volume (obtain from the Cloudflare dashboard or API):

```bash
docker compose -f cloud/docker-compose.yml exec cloud \
  sh -c 'echo "YOUR_TUNNEL_TOKEN" > /tunnel/token'
```

```bash
docker compose -f cloud/docker-compose.yml logs -f cloud        # View cloud logs
docker compose -f cloud/docker-compose.yml logs -f cloudflared   # View tunnel logs
docker compose -f cloud/docker-compose.yml exec cloud php artisan test --compact  # Run tests
docker compose -f cloud/docker-compose.yml down                  # Stop everything
```

## Running Both

The device and cloud use separate compose files with non-overlapping ports and can run side-by-side:

```bash
docker compose up -d
docker compose -f cloud/docker-compose.yml up -d
```

## Cloudflare Tunnel (cloudflared)

Both projects use a containerized cloudflared instead of a host-installed binary. The same entrypoint script (`device/docker/cloudflared-entrypoint.sh`) is shared by both compose files. It:

1. Polls for a token file at `/tunnel/token` every 5 seconds
2. Starts `cloudflared tunnel run` when a token appears
3. Monitors the file every 10 seconds and restarts on token changes (reprovisioning)
4. Stops cloudflared if the token file is emptied
5. Restarts automatically if the process crashes

No environment variables are used for tunnel tokens. The token is always read from a shared volume.

## Development

Source code is mounted into containers via volumes, so file changes are reflected immediately. Start Vite for HMR:

```bash
docker compose exec device npm run dev
```

## Rebuilding

```bash
# Device
docker compose up -d --build

# Cloud
docker compose -f cloud/docker-compose.yml up -d --build
```

Full reset (removes volumes — database data will be lost):

```bash
# Device
docker compose down -v && docker compose up -d --build

# Cloud
docker compose -f cloud/docker-compose.yml down -v \
  && docker compose -f cloud/docker-compose.yml up -d --build
```
