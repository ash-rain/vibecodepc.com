# VibeCodePC — Development Plan

## Executive Summary

VibeCodePC is a **plug-and-code personal dev station** built on Raspberry Pi 5. Users scan a QR code, walk through a wizard to connect their AI accounts and IDE, then use a dashboard to create, manage, and deploy projects — all tunneled to `username.vibecodepc.com`.

Two codebases:
1. **Device App** — Laravel 12 running locally on the Pi (wizard + dashboard)
2. **Cloud Edge** — Laravel 12 running on VPS at vibecodepc.com (device registry, DNS, tunnel ingress, marketing site)

---

## Phase 0: Foundation & Tooling (Week 1–2)

### 0.1 Repository Structure
- [x] Monorepo with two Laravel apps: `device/` and `cloud/`
- [x] Shared package `packages/vibecodepc-common/` for DTOs, enums, API contracts
- [x] Unified `docker-compose.yml` for local development (simulates Pi + cloud)

### 0.2 Device App Scaffolding
- [x] `laravel new device` — Laravel 12, PHP 8.4
- [x] Install Livewire 3, Tailwind CSS 4, Alpine.js
- [x] SQLite as default DB
- [x] Configure Vite for RPi-optimized builds (minimal JS bundles)
- [x] Set up Pest PHP testing

### 0.3 Cloud Edge Scaffolding
- [x] `laravel new cloud` — Laravel 12, PHP 8.4
- [x] MySQL database, Redis cache/queue
- [x] Install Laravel Sanctum (API auth for device ↔ cloud)
- [ ] DNS wildcard setup: `*.vibecodepc.com` → cloud edge (ops task — requires Cloudflare dashboard config)
- [x] Cloudflare integration for tunnel management

### 0.4 Device Identity System
- [x] Script to generate unique Device ID (UUID v4) and write to `storage/device.json`
- [x] QR code generation (printed label / on-screen during first boot)
- [x] `device.json` schema: `{ id, hardware_serial, manufactured_at, firmware_version }`

---

## Phase 1: Device Pairing & Onboarding (Week 2–4)

### 1.1 Cloud: Device Registry
- [x] `devices` table: `id, uuid, status (unclaimed|claimed|deactivated), user_id, paired_at, ip_hint`
- [x] `users` table: standard Laravel auth + `username` (for subdomain)
- [x] API endpoint: `POST /api/devices/{uuid}/claim` — validates device exists & unclaimed, issues pairing token
- [x] API endpoint: `GET /api/devices/{uuid}/status` — device polls this to check if claimed

### 1.2 Cloud: Pairing Web Flow
- [x] Route: `vibecodepc.com/pair/{device-uuid}` — landing page
- [x] If device unclaimed → show "Claim this VibeCodePC" → register/login → claim
- [x] If device already claimed by current user → redirect to device local IP
- [x] If device claimed by another user → show "Already claimed" message
- [ ] After claiming → display device local IP + link to open wizard (ip_hint stored but not shown on success page)
- [ ] mDNS/network discovery hint: `vibecodepc.local` fallback

### 1.3 Device: Pairing Listener
- [x] On first boot, device starts in **pairing mode**
- [x] Background job polls `GET /api/devices/{uuid}/status` every 5 seconds
- [x] When status changes to `claimed`, device receives pairing token + user info
- [x] Device stores cloud credentials in encrypted SQLite
- [x] Device transitions from pairing mode → wizard mode
- [x] Pairing screen on device (if monitor connected): shows QR code + device ID + local IP

### 1.4 Device: Network Setup
- [x] Auto-detect Ethernet connectivity (preferred)
- [x] Wi-Fi configuration page (if no Ethernet detected)
- [x] Connectivity test (ping cloud edge + DNS resolution)
- [x] Display local IP address prominently for LAN access

---

## Phase 2: The Setup Wizard (Week 4–8)

The wizard is the core UX. Each step is a **Livewire component** with validation, skip capability, and progress persistence.

### 2.1 Wizard Framework
- [x] `WizardController` — manages step order, progress, skip logic
- [x] `wizard_progress` table: `step, status (pending|completed|skipped), data_json, completed_at`
- [x] Progress bar component (top of every step)
- [x] "Skip for now" on every optional step (come back from dashboard later)
- [x] State persisted to SQLite — survives reboot mid-wizard

### 2.2 Step 1: Welcome & Account Confirmation
- [x] Confirm device is paired (show username from cloud)
- [x] Set local admin password (for SSH + sudo)
- [x] Set timezone / locale
- [x] Accept terms of service

### 2.3 Step 2: AI Services — API Keys
Each provider is a sub-step with:
- Input field for API key / token
- "Test Connection" button (async validation)
- Link to provider's API key page
- "Skip" option
- Visual status indicator (connected / not connected / error)

Providers:
- [x] **OpenAI** (ChatGPT / GPT API) — validate with `GET /v1/models`
- [x] **Anthropic** (Claude API) — validate with `GET /v1/models`
- [x] **OpenRouter** — validate with `GET /api/v1/models`
- [x] **HuggingFace** — validate with `GET /api/whoami-v2`
- [x] **Custom / Other** — name + base URL + API key (OpenAI-compatible)

Storage:
- [x] All keys encrypted via `Crypt::encryptString()`
- [x] Stored in `ai_providers` table: `provider, api_key_encrypted, validated_at, status`
- [x] Service class per provider in `app/Services/AiProviders/`

### 2.4 Step 3: GitHub Account & Copilot
- [x] GitHub OAuth device flow (no redirect needed — works on headless Pi)
- [x] Verify GitHub account has Copilot subscription
- [x] If no Copilot → show signup link + skip option
- [x] Store GitHub token (encrypted) for Copilot + git operations
- [x] Configure git identity (`user.name`, `user.email`) from GitHub profile

### 2.5 Step 4: VS Code (code-server) Setup
- [x] Install/verify code-server is running
- [x] Install essential extensions:
  - GitHub Copilot + Copilot Chat
  - Appropriate language extensions (auto-detected or user-selected)
  - Tailwind CSS IntelliSense, ESLint, Prettier
- [x] Configure code-server auth (tie to device local password)
- [x] Set theme (user choice: dark/light, 3-4 popular themes)
- [x] Preview: show embedded code-server frame to confirm it works

### 2.6 Step 5: Subdomain & Tunnel Setup
- [x] User chooses subdomain: `{username}.vibecodepc.com` (pre-filled from cloud account)
- [x] Validate subdomain availability via cloud API
- [x] Generate cloudflared tunnel credentials
- [x] Test tunnel connectivity (device → cloud edge → public)
- [x] Show success: "Your VibeCodePC is live at https://username.vibecodepc.com"

### 2.7 Step 6: Completion
- [x] Summary of everything configured
- [x] Quick-action cards: "Create First Project", "Open VS Code", "View Dashboard"
- [x] Mark wizard as complete → redirect to Dashboard on next visit

---

## Phase 3: Dashboard (Week 8–12)

### 3.1 Dashboard Layout
- [x] Sidebar navigation (collapsible on mobile)
- [x] Top bar: device health indicators (CPU, RAM, disk, temp)
- [x] Main content area: context-dependent panels
- [x] Responsive design (works on phone, tablet, desktop)

### 3.2 Projects Panel
- [x] "New Project" button → project creation modal/flow
- [x] Project templates:
  - **Laravel** (full-stack PHP)
  - **Next.js** (React SSR)
  - **Astro** (static site)
  - **Python/FastAPI** (API/ML)
  - **Static HTML** (plain site)
  - **Custom** (empty directory + git init)
- [x] Each template: scaffolded with AI service configs pre-injected
- [x] Project list: name, framework, status (running/stopped), port, public URL
- [x] Project actions: start, stop, open in VS Code, open terminal, view logs, delete

### 3.3 Project Detail View
- [x] Resource usage (CPU/RAM for this project's container)
- [x] Environment variables editor
- [x] Domain/tunnel settings (assign subdomain path or separate subdomain)
- [x] Deployment log / history
- [x] One-click "Open in VS Code" (opens code-server to project directory)

### 3.4 Deployments & Tunnels
- [x] Each project can be exposed via tunnel
- [x] Routing: `username.vibecodepc.com` → default project, `username.vibecodepc.com/projectname` → specific project
- [ ] Or: `projectname-username.vibecodepc.com` (subdomain per project — path-based routing exists, subdomain-per-project not yet)
- [x] HTTPS automatic via Cloudflare
- [x] Toggle tunnel on/off per project
- [ ] Bandwidth/request stats from cloud edge (TunnelRequestLog model exists, needs dashboard UI)

### 3.5 AI Services Hub
- [x] View connected AI providers and their status
- [x] Add/remove/update API keys (re-enter wizard step)
- [x] Quick test: send a prompt to each provider and show response
- [ ] Usage hints: which projects are using which API keys

### 3.6 VS Code Integration
- [x] Embedded code-server iframe (full-screen option)
- [x] Or: direct link to `vibecodepc.local:8443`
- [ ] Extension management from dashboard
- [ ] Copilot status indicator (GitHubCredential.has_copilot exists, needs dashboard UI)

### 3.7 System Settings
- [x] Network settings (Wi-Fi, static IP)
- [x] Storage usage + cleanup tools
- [x] System update (pull latest device firmware/app)
- [x] Factory reset (re-run wizard)
- [x] SSH access toggle
- [ ] Backup / restore configuration
- [x] Power: restart / shutdown device

---

## Phase 4: Cloud Edge Platform (Week 10–14)

### 4.1 Marketing Site
- [x] Landing page at `vibecodepc.com`
- [x] Product description, pricing, buy button
- [x] "How it works" animation / video
- [x] Testimonials / use cases
- [x] FAQ

### 4.2 User Dashboard (Cloud)
- [x] Login at `vibecodepc.com/login`
- [x] View claimed devices
- [x] Manage subdomain(s)
- [ ] Billing / subscription management
- [x] View tunnel traffic stats

### 4.3 Tunnel Ingress
- [x] Wildcard DNS: `*.vibecodepc.com` → cloud edge server
- [x] Reverse proxy (Caddy or Nginx) routes `username.vibecodepc.com` → device tunnel
- [x] Cloudflare Tunnel orchestration: cloud edge manages tunnel routing table
- [x] Rate limiting and basic DDoS protection
- [x] Custom domain support (CNAME to `username.vibecodepc.com`)

### 4.4 Device Management API
- [x] `POST /api/devices/{uuid}/heartbeat` — device health telemetry
- [x] `POST /api/devices/{uuid}/tunnel/register` — register tunnel endpoint
- [x] `GET /api/devices/{uuid}/config` — pull remote config updates
- [x] `POST /api/devices/{uuid}/tunnel/routes` — update routing table
- [ ] Webhook: notify device of config changes (config_version polling exists, true push not yet)

---

## Phase 5: OS Image & Hardware (Week 12–16)

### 5.1 Custom Raspberry Pi OS Image
- [x] Based on Debian 12 Bookworm (64-bit, lite)
- [x] Built with `pi-gen` (custom stage) — see `os-image/`
- [x] Pre-installed:
  - PHP 8.4, Composer, Node.js 22 LTS, npm/pnpm
  - Python 3.12, pip
  - Docker + Docker Compose
  - code-server (latest)
  - cloudflared
  - Redis (Valkey)
  - SQLite, git, curl, jq, htop
  - The Laravel device app (production build)
- [x] First-boot script (`os-image/stage-vibecodepc/03-first-boot/`):
  - Generate device UUID (if not pre-burned)
  - Start Laravel app (Nginx + PHP-FPM + Horizon)
  - Display QR code on HDMI (TTY1 display service)
  - Start mDNS advertising (`vibecodepc.local`)
- [x] Auto-update mechanism (`os-image/stage-vibecodepc/04-autoupdate/`) — systemd timer, daily check against GitHub Releases

### 5.2 Hardware BOM (Bill of Materials)
| Component                  | Spec                          | Est. Cost |
| -------------------------- | ----------------------------- | --------- |
| Raspberry Pi 5             | 16 GB LPDDR4X RAM             | $120      |
| M.2 HAT+                   | Official Raspberry Pi         | $12       |
| NVMe SSD                   | 128 GB M.2 2242               | $20       |
| Power Supply               | 27W USB-C (official)          | $12       |
| Case                       | Custom branded                | $15       |
| Ethernet cable             | Cat6, 1m                      | $2        |
| QR Code label              | Printed sticker               | $0.50     |
| Quick Start card           | Printed                       | $1        |
| **Total hardware cost**    |                               | **~$183** |
| **Target retail price**    |                               | **$349**  |

### 5.3 Manufacturing & Assembly
- [ ] Source components (RPi distributor, SSD bulk)
- [x] Flash custom OS image to SSD — `os-image/scripts/flash.sh`
- [x] Burn unique device ID during flashing — `flash.sh` writes `device.json` post-flash
- [x] Print QR code label with device UUID — `flash.sh --qr-output labels.csv` for batch printing
- [ ] Assembly: Pi + HAT + SSD + case
- [ ] QA test: boot, verify wizard loads, test network
- [ ] Package with quick start card, cable, PSU

---

## Phase 6: Subscription & Billing (Week 14–16)

### 6.1 Pricing Model
| Tier          | Price       | Includes                                                |
| ------------- | ----------- | ------------------------------------------------------- |
| **Free**      | $0/mo       | Local use only, no tunnel, no subdomain                  |
| **Starter**   | $5/mo       | 1 subdomain, 10 GB tunnel bandwidth, community support   |
| **Pro**       | $15/mo      | 3 subdomains, 100 GB bandwidth, custom domain, priority support |
| **Team**      | $39/mo      | 10 devices, shared projects, team subdomains, 500 GB BW  |

### 6.2 Billing Implementation
- [ ] Stripe integration on cloud edge
- [ ] Subscription management (upgrade/downgrade/cancel)
- [ ] Usage metering for bandwidth
- [ ] Grace period for overages (soft limit, notify, then throttle)
- [ ] Device is always fully functional locally — subscription only gates tunnel/subdomain

---

## Phase 7: Testing & QA (Week 16–18)

### 7.1 Automated Testing
- [ ] Device app: Pest PHP unit + feature tests (≥80% coverage)
- [ ] Cloud edge: Pest PHP unit + feature tests
- [ ] E2E: Playwright tests for wizard flow + dashboard
- [ ] Tunnel integration tests (device ↔ cloud)
- [ ] Load testing: 100 concurrent tunnels on cloud edge

### 7.2 Hardware Testing
- [ ] Thermal testing (sustained load on Pi 5)
- [ ] Power failure recovery (graceful restart, data integrity)
- [ ] SD card / SSD failure modes
- [ ] Wi-Fi range and reliability
- [ ] 24/7 uptime soak test (1 week continuous)

### 7.3 Security Audit
- [ ] Encrypted storage review
- [ ] Tunnel security (no unauthorized access)
- [ ] API authentication review
- [ ] Dependency vulnerability scan
- [ ] Penetration test on cloud edge

---

## Phase 8: Pre-Launch (Week 18–20)

- [ ] Beta program: 20 devices to early adopters
- [ ] Feedback collection + iteration
- [ ] Documentation site: `docs.vibecodepc.com`
- [ ] Video: unboxing → QR scan → wizard → first deploy (under 5 min)
- [ ] Shopify / WooCommerce store setup
- [ ] Legal: terms of service, privacy policy, warranty
- [ ] Support system: help desk + community Discord

---

## Milestones Summary

| Milestone              | Target     | Key Deliverable                        |
| ---------------------- | ---------- | -------------------------------------- |
| M0: Scaffolding        | Week 2     | Both apps bootstrapped, CI running      |
| M1: Pairing works      | Week 4     | QR scan → claim → wizard starts         |
| M2: Wizard complete    | Week 8     | All 6 wizard steps functional           |
| M3: Dashboard MVP      | Week 12    | Projects + deploy + VS Code working     |
| M4: OS Image v1        | Week 16    | Bootable image with everything included |
| M5: Beta launch        | Week 18    | 20 devices shipped to testers           |
| M6: Public launch      | Week 20    | Store live, accepting orders             |

---

## Risk Register

| Risk                              | Impact | Mitigation                                     |
| --------------------------------- | ------ | ---------------------------------------------- |
| RPi 5 supply shortage             | High   | Pre-order stock, consider alternatives (CM5)    |
| Tunnel reliability                | High   | Fallback to ngrok/bore, multi-tunnel support    |
| User can't find device on network | Medium | mDNS, display IP on HDMI, fallback QR flow     |
| API key validation fails offline  | Low    | Graceful skip, validate later when online       |
| code-server performance on Pi     | Medium | Optimize extensions, limit pre-installed ones   |
| Cloudflare policy changes         | Medium | Abstract tunnel layer, support self-hosted alt  |
