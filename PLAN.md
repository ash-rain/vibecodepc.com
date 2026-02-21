# VibeCodePC — Development Plan

## Executive Summary

VibeCodePC is a **plug-and-code personal dev station** built on Raspberry Pi 5. Users scan a QR code, walk through a wizard to connect their AI accounts and IDE, then use a dashboard to create, manage, and deploy projects — all tunneled to `username.vibecodepc.com`.

Two codebases:
1. **Device App** — Laravel 12 running locally on the Pi (wizard + dashboard)
2. **Cloud Edge** — Laravel 12 running on VPS at vibecodepc.com (device registry, DNS, tunnel ingress, marketing site)

---

## Phase 0: Foundation & Tooling (Week 1–2)

### 0.1 Repository Structure
- Monorepo with two Laravel apps: `device/` and `cloud/`
- Shared package `packages/vibecodepc-common/` for DTOs, enums, API contracts
- Unified `docker-compose.yml` for local development (simulates Pi + cloud)

### 0.2 Device App Scaffolding
- [ ] `laravel new device` — Laravel 12, PHP 8.4
- [ ] Install Livewire 3, Tailwind CSS 4, Alpine.js
- [ ] SQLite as default DB
- [ ] Configure Vite for RPi-optimized builds (minimal JS bundles)
- [ ] Set up Pest PHP testing

### 0.3 Cloud Edge Scaffolding
- [ ] `laravel new cloud` — Laravel 12, PHP 8.4
- [ ] MySQL database, Redis cache/queue
- [ ] Install Laravel Sanctum (API auth for device ↔ cloud)
- [ ] DNS wildcard setup: `*.vibecodepc.com` → cloud edge
- [ ] Cloudflare integration for tunnel management

### 0.4 Device Identity System
- [ ] Script to generate unique Device ID (UUID v4) and write to `/etc/vibecodepc/device.json`
- [ ] QR code generation (printed label / on-screen during first boot)
- [ ] `device.json` schema: `{ id, hardware_serial, manufactured_at, firmware_version }`

---

## Phase 1: Device Pairing & Onboarding (Week 2–4)

### 1.1 Cloud: Device Registry
- [ ] `devices` table: `id, uuid, status (unclaimed|claimed|deactivated), user_id, paired_at, ip_hint`
- [ ] `users` table: standard Laravel auth + `username` (for subdomain)
- [ ] API endpoint: `POST /api/devices/{uuid}/claim` — validates device exists & unclaimed, issues pairing token
- [ ] API endpoint: `GET /api/devices/{uuid}/status` — device polls this to check if claimed

### 1.2 Cloud: Pairing Web Flow
- [ ] Route: `id.vibecodepc.com/{device-uuid}` — landing page
- [ ] If device unclaimed → show "Claim this VibeCodePC" → register/login → claim
- [ ] If device already claimed by current user → redirect to device local IP
- [ ] If device claimed by another user → show "Already claimed" message
- [ ] After claiming → display device local IP + link to open wizard
- [ ] mDNS/network discovery hint: `vibecodepc.local` fallback

### 1.3 Device: Pairing Listener
- [ ] On first boot, device starts in **pairing mode**
- [ ] Background job polls `GET /api/devices/{uuid}/status` every 5 seconds
- [ ] When status changes to `claimed`, device receives pairing token + user info
- [ ] Device stores cloud credentials in encrypted SQLite
- [ ] Device transitions from pairing mode → wizard mode
- [ ] Pairing screen on device (if monitor connected): shows QR code + device ID + local IP

### 1.4 Device: Network Setup
- [ ] Auto-detect Ethernet connectivity (preferred)
- [ ] Wi-Fi configuration page (if no Ethernet detected)
- [ ] Connectivity test (ping cloud edge + DNS resolution)
- [ ] Display local IP address prominently for LAN access

---

## Phase 2: The Setup Wizard (Week 4–8)

The wizard is the core UX. Each step is a **Livewire component** with validation, skip capability, and progress persistence.

### 2.1 Wizard Framework
- [ ] `WizardController` — manages step order, progress, skip logic
- [ ] `wizard_progress` table: `step, status (pending|completed|skipped), data_json, completed_at`
- [ ] Progress bar component (top of every step)
- [ ] "Skip for now" on every optional step (come back from dashboard later)
- [ ] State persisted to SQLite — survives reboot mid-wizard

### 2.2 Step 1: Welcome & Account Confirmation
- [ ] Confirm device is paired (show username from cloud)
- [ ] Set local admin password (for SSH + sudo)
- [ ] Set timezone / locale
- [ ] Accept terms of service

### 2.3 Step 2: AI Services — API Keys
Each provider is a sub-step with:
- Input field for API key / token
- "Test Connection" button (async validation)
- Link to provider's API key page
- "Skip" option
- Visual status indicator (connected / not connected / error)

Providers:
- [ ] **OpenAI** (ChatGPT / GPT API) — validate with `GET /v1/models`
- [ ] **Anthropic** (Claude API) — validate with `GET /v1/models`
- [ ] **OpenRouter** — validate with `GET /api/v1/models`
- [ ] **HuggingFace** — validate with `GET /api/whoami-v2`
- [ ] **Custom / Other** — name + base URL + API key (OpenAI-compatible)

Storage:
- [ ] All keys encrypted via `Crypt::encryptString()`
- [ ] Stored in `ai_providers` table: `provider, api_key_encrypted, validated_at, status`
- [ ] Service class per provider in `app/Services/AiProviders/`

### 2.4 Step 3: GitHub Account & Copilot
- [ ] GitHub OAuth device flow (no redirect needed — works on headless Pi)
- [ ] Verify GitHub account has Copilot subscription
- [ ] If no Copilot → show signup link + skip option
- [ ] Store GitHub token (encrypted) for Copilot + git operations
- [ ] Configure git identity (`user.name`, `user.email`) from GitHub profile

### 2.5 Step 4: VS Code (code-server) Setup
- [ ] Install/verify code-server is running
- [ ] Install essential extensions:
  - GitHub Copilot + Copilot Chat
  - Appropriate language extensions (auto-detected or user-selected)
  - Tailwind CSS IntelliSense, ESLint, Prettier
- [ ] Configure code-server auth (tie to device local password)
- [ ] Set theme (user choice: dark/light, 3-4 popular themes)
- [ ] Preview: show embedded code-server frame to confirm it works

### 2.6 Step 5: Subdomain & Tunnel Setup
- [ ] User chooses subdomain: `{username}.vibecodepc.com` (pre-filled from cloud account)
- [ ] Validate subdomain availability via cloud API
- [ ] Generate cloudflared tunnel credentials
- [ ] Test tunnel connectivity (device → cloud edge → public)
- [ ] Show success: "Your VibeCodePC is live at https://username.vibecodepc.com"

### 2.7 Step 6: Completion
- [ ] Summary of everything configured
- [ ] Quick-action cards: "Create First Project", "Open VS Code", "View Dashboard"
- [ ] Mark wizard as complete → redirect to Dashboard on next visit

---

## Phase 3: Dashboard (Week 8–12)

### 3.1 Dashboard Layout
- [ ] Sidebar navigation (collapsible on mobile)
- [ ] Top bar: device health indicators (CPU, RAM, disk, temp)
- [ ] Main content area: context-dependent panels
- [ ] Responsive design (works on phone, tablet, desktop)

### 3.2 Projects Panel
- [ ] "New Project" button → project creation modal/flow
- [ ] Project templates:
  - **Laravel** (full-stack PHP)
  - **Next.js** (React SSR)
  - **Astro** (static site)
  - **Python/FastAPI** (API/ML)
  - **Static HTML** (plain site)
  - **Custom** (empty directory + git init)
- [ ] Each template: scaffolded with AI service configs pre-injected
- [ ] Project list: name, framework, status (running/stopped), port, public URL
- [ ] Project actions: start, stop, open in VS Code, open terminal, view logs, delete

### 3.3 Project Detail View
- [ ] Resource usage (CPU/RAM for this project's container)
- [ ] Environment variables editor
- [ ] Domain/tunnel settings (assign subdomain path or separate subdomain)
- [ ] Deployment log / history
- [ ] One-click "Open in VS Code" (opens code-server to project directory)

### 3.4 Deployments & Tunnels
- [ ] Each project can be exposed via tunnel
- [ ] Routing: `username.vibecodepc.com` → default project, `username.vibecodepc.com/projectname` → specific project
- [ ] Or: `projectname-username.vibecodepc.com` (subdomain per project)
- [ ] HTTPS automatic via Cloudflare
- [ ] Toggle tunnel on/off per project
- [ ] Bandwidth/request stats from cloud edge

### 3.5 AI Services Hub
- [ ] View connected AI providers and their status
- [ ] Add/remove/update API keys (re-enter wizard step)
- [ ] Quick test: send a prompt to each provider and show response
- [ ] Usage hints: which projects are using which API keys

### 3.6 VS Code Integration
- [ ] Embedded code-server iframe (full-screen option)
- [ ] Or: direct link to `vibecodepc.local:8443`
- [ ] Extension management from dashboard
- [ ] Copilot status indicator

### 3.7 System Settings
- [ ] Network settings (Wi-Fi, static IP)
- [ ] Storage usage + cleanup tools
- [ ] System update (pull latest device firmware/app)
- [ ] Factory reset (re-run wizard)
- [ ] SSH access toggle
- [ ] Backup / restore configuration
- [ ] Power: restart / shutdown device

---

## Phase 4: Cloud Edge Platform (Week 10–14)

### 4.1 Marketing Site
- [ ] Landing page at `vibecodepc.com`
- [ ] Product description, pricing, buy button
- [ ] "How it works" animation / video
- [ ] Testimonials / use cases
- [ ] FAQ

### 4.2 User Dashboard (Cloud)
- [ ] Login at `vibecodepc.com/login`
- [ ] View claimed devices
- [ ] Manage subdomain(s)
- [ ] Billing / subscription management
- [ ] View tunnel traffic stats

### 4.3 Tunnel Ingress
- [ ] Wildcard DNS: `*.vibecodepc.com` → cloud edge server
- [ ] Reverse proxy (Caddy or Nginx) routes `username.vibecodepc.com` → device tunnel
- [ ] Cloudflare Tunnel orchestration: cloud edge manages tunnel routing table
- [ ] Rate limiting and basic DDoS protection
- [ ] Custom domain support (CNAME to `username.vibecodepc.com`)

### 4.4 Device Management API
- [ ] `POST /api/devices/{uuid}/heartbeat` — device health telemetry
- [ ] `POST /api/devices/{uuid}/tunnel/register` — register tunnel endpoint
- [ ] `GET /api/devices/{uuid}/config` — pull remote config updates
- [ ] `POST /api/devices/{uuid}/tunnel/routes` — update routing table
- [ ] Webhook: notify device of config changes

---

## Phase 5: OS Image & Hardware (Week 12–16)

### 5.1 Custom Raspberry Pi OS Image
- [ ] Based on Debian 12 Bookworm (64-bit, lite)
- [ ] Built with `pi-gen` (custom stage)
- [ ] Pre-installed:
  - PHP 8.4, Composer, Node.js 22 LTS, npm/pnpm
  - Python 3.12, pip
  - Docker + Docker Compose
  - code-server (latest)
  - cloudflared
  - Redis (Valkey)
  - SQLite, git, curl, jq, htop
  - The Laravel device app (production build)
- [ ] First-boot script:
  - Generate device UUID (if not pre-burned)
  - Start Laravel app
  - Display QR code on HDMI (if connected) and serve pairing page
  - Start mDNS advertising (`vibecodepc.local`)
- [ ] Auto-update mechanism (pull from GitHub releases / apt repo)

### 5.2 Hardware BOM (Bill of Materials)
| Component                  | Spec                          | Est. Cost |
| -------------------------- | ----------------------------- | --------- |
| Raspberry Pi 5             | 8 GB RAM                      | $80       |
| NVMe SSD                   | 256 GB (via M.2 HAT)         | $25       |
| M.2 HAT for Pi 5           | Official or Pimoroni          | $15       |
| Power Supply               | 27W USB-C (official)          | $12       |
| Case                       | Custom branded (3D print/mold)| $8        |
| MicroSD                    | 32 GB (boot only)             | $6        |
| Ethernet cable             | Cat6, 1m                      | $2        |
| QR Code label              | Printed sticker               | $0.50     |
| Quick Start card           | Printed                       | $1        |
| **Total hardware cost**    |                               | **~$150** |
| **Target retail price**    |                               | **$299**  |

### 5.3 Manufacturing & Assembly
- [ ] Source components (RPi distributor, SSD bulk)
- [ ] Flash custom OS image to SSD
- [ ] Burn unique device ID during flashing
- [ ] Print QR code label with device UUID
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
