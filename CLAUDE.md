# VibeCodePC.com — Project Instructions

## What Is This?

VibeCodePC is a product + platform business. We sell a **pre-configured Raspberry Pi 5** that works as a personal AI-powered coding workstation and self-hosting station. The user plugs it in, scans a QR code (printed on the device/box), and a guided wizard walks them through connecting AI services, setting up VS Code + Copilot, and choosing project templates. After the wizard, a dashboard replaces it — for creating projects, managing deployments, and publishing through tunnels to `username.vibecodepc.com`.

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│  RASPBERRY PI 5 (8 GB)  —  "The VibeCodePC"        │
│                                                     │
│  OS: Debian 12 (Bookworm) custom image              │
│  ┌───────────────────────────────────────────────┐  │
│  │  Laravel 12 Web App  (port 80/443 local)      │  │
│  │  ├── Wizard (first-run, multi-step)           │  │
│  │  ├── Dashboard (post-setup)                   │  │
│  │  ├── Project Manager                          │  │
│  │  └── Tunnel / Deploy Manager                  │  │
│  ├───────────────────────────────────────────────┤  │
│  │  code-server (VS Code in browser, port 8443)  │  │
│  ├───────────────────────────────────────────────┤  │
│  │  cloudflared  (tunnel to vibecodepc.com)       │  │
│  ├───────────────────────────────────────────────┤  │
│  │  SQLite (local DB) + Redis (queue/cache)      │  │
│  ├───────────────────────────────────────────────┤  │
│  │  Docker (optional project containers)         │  │
│  └───────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────┘
          │                          │
          │  Tunnel (cloudflared)    │  API calls
          ▼                          ▼
┌──────────────────┐      ┌─────────────────────┐
│ vibecodepc.com   │      │ OpenAI / Anthropic / │
│ (cloud edge)     │      │ OpenRouter / HF /    │
│ ├─ /pair/...     │      │ GitHub Copilot       │
│ ├─ user.vibe...  │      └─────────────────────┘
│ └─ api.vibe...   │
└──────────────────┘
```

## Tech Stack

| Layer             | Technology                                      |
| ----------------- | ----------------------------------------------- |
| Framework         | Laravel 12 (PHP 8.4)                            |
| Frontend          | Livewire 3 + Alpine.js + Tailwind CSS 4         |
| Database          | SQLite (on-device) + MySQL on cloud edge         |
| Cache / Queue     | Redis (on-device via Valkey)                     |
| IDE               | code-server (VS Code in browser)                |
| Tunnel            | cloudflared (Cloudflare Tunnel)                  |
| Process Manager   | systemd + Laravel Horizon (queue)               |
| Containers        | Docker (for user project isolation)             |
| OS Image          | Debian 12 Bookworm (RPi 5) custom build via pi-gen |
| Cloud Edge        | Laravel app on vibecodepc.com (VPS)             |

## Coding Conventions

- **PHP**: PSR-12, strict types, PHP 8.4 features (property hooks, asymmetric visibility)
- **Naming**: snake_case for DB columns, camelCase for PHP variables/methods, kebab-case for routes/URLs
- **Views**: Livewire components in `app/Livewire/`, Blade views in `resources/views/`
- **Wizard steps**: Each step is a separate Livewire component under `app/Livewire/Wizard/`
- **Dashboard modules**: Each panel is a Livewire component under `app/Livewire/Dashboard/`
- **API integrations**: Service classes in `app/Services/`, one per provider
- **Config/Secrets**: All AI keys stored encrypted in SQLite via Laravel's `Crypt` facade
- **Tests**: Pest PHP, minimum 80% coverage on business logic
- **No premature abstraction** — keep it simple, only extract when repeated 3+ times

## Key Directories

```
app/
├── Livewire/
│   ├── Wizard/          # One component per wizard step
│   └── Dashboard/       # Dashboard panel components
├── Services/
│   ├── AiProviders/     # OpenAI, Anthropic, OpenRouter, HuggingFace
│   ├── Tunnel/          # Cloudflare tunnel management
│   ├── CodeServer/      # code-server lifecycle
│   └── DeviceRegistry/  # Device registration & QR pairing
├── Models/
├── Http/Controllers/
└── Console/Commands/    # Setup, health-check, tunnel CLIs
```

## Device Onboarding Flow

1. Each device ships with a **unique Device ID** (UUID v4) stored in `storage/device.json`
2. A **QR code** on the device/box encodes `https://vibecodepc.com/pair/{device-id}`
3. User scans QR (or types the URL) on their phone/laptop
4. Cloud edge (`vibecodepc.com/pair/{device-id}`) looks up device ID, confirms it's unclaimed
5. User creates account (or logs in), claims the device
6. Cloud edge returns a **pairing token** and redirects to the device's local IP
7. Device receives the pairing token, links to the cloud account
8. Wizard begins on the device's local web UI

## Important Commands

```bash
# Development
php artisan serve                    # Local dev server
php artisan livewire:make Wizard/StepName  # New wizard step
npm run dev                          # Vite dev server (Tailwind HMR)

# Testing
php artisan test                     # Run Pest test suite
php artisan test --coverage          # With coverage report

# Device
sudo vibecodepc status               # System health
sudo vibecodepc tunnel start         # Start cloudflared tunnel
sudo vibecodepc reset                # Factory reset (re-run wizard)
```

## Non-Negotiables

- The wizard MUST work fully offline (except for API key validation which gracefully degrades)
- All credentials stored encrypted at rest — never in plaintext
- The device must be usable within 5 minutes of first power-on
- Dashboard must load in under 2 seconds on RPi 5
- Every tunnel deployment must have HTTPS by default
