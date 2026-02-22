# VibeCodePC

A pre-configured Raspberry Pi 5 that works as a personal AI-powered coding workstation. Plug it in, scan the QR code, and start coding with AI.

## Prerequisites

Install Docker Desktop for your platform:

| Platform | Link |
|---|---|
| macOS | https://docs.docker.com/desktop/install/mac-install/ |
| Windows | https://docs.docker.com/desktop/install/windows-install/ |
| Linux | https://docs.docker.com/desktop/install/linux/ |

Make sure Docker is running before continuing.

## Setup

```bash
git clone <repo-url> vibecodepc.com
cd vibecodepc.com
docker compose up -d
```

That's it. Docker builds the device image (installs code-server, cloudflared) and starts everything.

## What's Running

| Service | URL | Description |
|---|---|---|
| **Device app** | http://localhost:8081 | Setup wizard & dashboard |
| **Redis** | localhost:6380 | Cache & queue (Valkey) |

## Project Structure

```
vibecodepc.com/
├── device/              # Device app (Laravel 12 + Livewire 3)
│   ├── bin/setup        # Provisioning script (runs during image build)
│   └── Dockerfile       # Builds on serversideup/php:8.4-fpm-nginx
├── cloud/               # Cloud edge app (vibecodepc.com)
├── packages/
│   └── vibecodepc-common/   # Shared PHP library
└── docker-compose.yml
```

## Common Commands

```bash
# Start everything
docker compose up -d

# Rebuild after changes to Dockerfile or bin/setup
docker compose build device
docker compose up -d device

# View logs
docker compose logs -f device

# Stop everything
docker compose down

# Full reset (removes volumes)
docker compose down -v
```

## Development

Source code is mounted into the container via volumes, so file changes are reflected immediately. The Vite dev server can be started inside the container for HMR:

```bash
docker compose exec device npm run dev
```

## Further Reading

- [device/README.md](device/README.md) — Device app details, artisan commands, environment config
- [cloud/README.md](cloud/README.md) — Cloud edge setup & API docs
