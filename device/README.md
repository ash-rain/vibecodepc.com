# VibeCodePC — Device App

The on-device Laravel application that powers the VibeCodePC Raspberry Pi 5. Runs the first-run setup wizard, dashboard, project manager, and tunnel/deploy manager.

## Prerequisites

- PHP 8.2+ with SQLite extension
- Composer
- Node.js & npm
- The `vibecodepc/common` package (at `../packages/vibecodepc-common/`)

## Quick Start

```bash
composer setup
php artisan serve
```

This installs dependencies, creates `.env`, generates the app key, runs migrations, generates a device identity, and builds frontend assets.

## Development

```bash
# Dev server with Vite HMR (Laravel + Vite in parallel)
composer run dev

# Run tests
php artisan test

# Run tests with coverage
php artisan test --coverage

# Lint / format
./vendor/bin/pint
```

## Artisan Commands

| Command | Description |
|---|---|
| `device:generate-id` | Generate a unique device UUID and write `storage/device.json` |
| `device:show-qr` | Display the QR code for device pairing |
| `device:poll-pairing` | Poll the cloud API for pairing status |

Options for `device:generate-id`:
- `--force` — Overwrite existing device identity
- `--path=<path>` — Custom output path (default from `VIBECODEPC_DEVICE_JSON` env var)

## Project Structure

```
app/
├── Console/Commands/     # device:generate-id, device:show-qr, device:poll-pairing
├── Livewire/
│   ├── Wizard/           # One component per setup wizard step
│   └── Dashboard/        # Dashboard panel components
├── Models/               # Eloquent models (User, AiProviderConfig, WizardProgress, …)
├── Services/
│   ├── AiProviders/      # OpenAI, Anthropic, OpenRouter, HuggingFace
│   ├── CodeServer/       # code-server lifecycle
│   ├── GitHub/           # GitHub OAuth
│   ├── Tunnel/           # Cloudflare tunnel management
│   └── DeviceRegistry/   # Device identity & QR pairing
└── Http/Controllers/
```

## Environment Variables

Key variables in `.env` (see `.env.example` for the full list):

| Variable | Default | Description |
|---|---|---|
| `VIBECODEPC_CLOUD_URL` | `https://vibecodepc.com` | Cloud edge URL |
| `VIBECODEPC_DEVICE_JSON` | `storage/device.json` | Path to device identity file |
| `CODE_SERVER_PORT` | `8443` | code-server port |
| `GITHUB_CLIENT_ID` | — | GitHub OAuth client ID |
| `CLOUDFLARED_CONFIG` | `/etc/cloudflared/config.yml` | Cloudflare tunnel config path |

> On the actual Raspberry Pi, `VIBECODEPC_DEVICE_JSON` defaults to `storage/device.json`.
