# VibeCodePC

A pre-configured Raspberry Pi 5 that works as a personal AI-powered coding workstation. Plug it in, scan the QR code, and a guided wizard walks you through connecting AI services, setting up VS Code, and choosing project templates. After setup, a dashboard lets you create projects, manage deployments, and publish through tunnels to `username.vibecodepc.com`.

## Monorepo Structure

```
vibecodepc.com/
├── cloud/                    # Cloud edge app (vibecodepc.com VPS)
├── device/                   # On-device app (Raspberry Pi 5)
├── packages/
│   └── vibecodepc-common/    # Shared DTOs, enums, and API contracts
└── docker-compose.yml        # Full-stack local development
```

| Directory | Description | Tech |
|---|---|---|
| `cloud/` | Device registry, QR pairing, user accounts, admin panel | Laravel 12, Filament 5, Sanctum |
| `device/` | Setup wizard, dashboard, project & tunnel manager | Laravel 12, Livewire 3 |
| `packages/vibecodepc-common/` | Shared PHP library (DTOs, enums) | PHP 8.2 |

## Prerequisites

- PHP 8.2+
- Composer
- Node.js & npm

For Docker setup: Docker & Docker Compose.

## Quick Start (Native)

Each app is set up independently with Composer:

```bash
# Cloud edge
cd cloud
composer setup
php artisan serve

# Device app (in a separate terminal)
cd device
composer setup
php artisan serve --port=8081
```

Both apps use SQLite by default for local development — no MySQL or Redis required.

## Quick Start (Docker)

```bash
docker compose up -d
```

This starts the full stack:

| Service | URL | Description |
|---|---|---|
| Cloud app | http://localhost:8080 | Cloud edge (MySQL + Redis) |
| Device app | http://localhost:8081 | Device simulator (SQLite) |
| Mailpit | http://localhost:8025 | Email testing UI |
| MySQL | localhost:33061 | Cloud database |
| Redis | localhost:6379 | Cloud cache/queue/sessions |

## Development

```bash
# Run dev servers with HMR (from each app directory)
composer run dev

# Run tests
composer run test

# Lint / format
./vendor/bin/pint
```

## Architecture

```
┌─────────────────────────────────────────┐
│  Raspberry Pi 5  ("The VibeCodePC")     │
│                                         │
│  Laravel 12 app (device/)               │
│  ├── Setup wizard (first-run)           │
│  ├── Dashboard (post-setup)             │
│  ├── Project manager                    │
│  └── Tunnel / deploy manager            │
│                                         │
│  code-server (VS Code in browser)       │
│  cloudflared (tunnel to vibecodepc.com) │
│  SQLite + Redis (Valkey)                │
└────────────┬────────────────────────────┘
             │ Tunnel + API
             ▼
┌─────────────────────────────────────────┐
│  vibecodepc.com  (cloud/)               │
│                                         │
│  Device registry & QR pairing           │
│  User accounts & auth                   │
│  Tunnel ingress (*.vibecodepc.com)      │
│  Admin panel (Filament)                 │
│                                         │
│  MySQL + Redis                          │
└─────────────────────────────────────────┘
```

## How Pairing Works

1. Each device ships with a unique UUID burned into `/etc/vibecodepc/device.json`
2. QR code on the device encodes `https://vibecodepc.com/id/{uuid}`
3. User scans QR, creates an account (or logs in), and claims the device
4. Cloud issues an encrypted API token; device polls and retrieves it
5. Device wizard begins on the local web UI

## Cloud Admin Panel

After running `composer setup` in `cloud/`, an admin user is seeded automatically:

- **URL:** http://localhost:8080/admin
- **Email:** admin@vibecodepc.com
- **Password:** password

## Further Reading

- [cloud/README.md](cloud/README.md) — Cloud edge setup, API endpoints, project structure
- [device/README.md](device/README.md) — Device app setup, artisan commands, environment variables
