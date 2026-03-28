# Product Scope - VibeCodePC Device

## Vision

VibeCodePC Device is a self-hosted development environment that runs on a Raspberry Pi 5, providing a complete web-based IDE, project management system, and cloud-connected development platform. It enables developers to have a dedicated, always-on development machine accessible from anywhere via secure tunneling, with integrated AI assistance, GitHub integration, and containerized project management.

The product vision is to democratize personal development infrastructure by providing an affordable, open-source alternative to cloud development environments that developers can own and control completely.

## Target Users

### Primary Users
- **Individual developers** who want a dedicated, always-on development machine
- **Remote workers** who need access to their development environment from multiple locations
- **Developers learning new technologies** who want isolated project environments
- **Open source contributors** who need consistent, reproducible development setups
- **Small teams** who need shared development infrastructure without cloud costs

### Secondary Users
- **Educational institutions** teaching web development
- **Workshop instructors** needing portable dev environments
- **Developers with limited local resources** who need containerized environments

## Features

### In Scope (Current Phase)

#### Core Device Management
- [x] Device identity generation and management (UUID-based)
- [x] QR code pairing with cloud service (vibecodepc.com)
- [x] Device mode management (pairing/wizard/dashboard)
- [x] Health monitoring (CPU, RAM, disk, temperature)
- [x] Encrypted backup and restore system
- [x] Factory reset capability

#### Setup Wizard
- [x] Multi-step first-run configuration
- [x] Welcome and introduction
- [x] Cloudflare Tunnel setup (optional local-only mode)
- [x] GitHub OAuth integration
- [x] Code-server (VS Code) configuration
- [x] AI provider setup (OpenAI, Anthropic, OpenRouter, HuggingFace, custom)
- [x] Wizard progress persistence

#### Dashboard
- [x] Overview panel with system health
- [x] Project list and management
- [x] AI Services Hub
- [x] Code Editor integration (VS Code in browser)
- [x] Tunnel management
- [x] Container monitoring
- [x] System settings
- [x] Analytics dashboard

#### Project Management
- [x] Create projects from templates (Laravel, React, Vue, etc.)
- [x] Clone projects from GitHub
- [x] Docker container lifecycle management
- [x] Port allocation for running projects
- [x] Public URL generation via tunnel
- [x] Environment variable management
- [x] Project activity logging
- [x] Soft deletes for projects

#### Tunneling & Remote Access
- [x] Cloudflare Tunnel integration
- [x] Quick tunnel support for temporary access
- [x] Subdomain-based project URLs
- [x] Optional tunnel authentication
- [x] Tunnel status monitoring

#### AI Integration
- [x] Multi-provider AI configuration
- [x] API key management with validation
- [x] Provider-specific settings (models, URLs, timeouts)
- [x] AI Services Hub for tool integration

#### Developer Experience
- [x] Livewire 4 reactive UI components
- [x] Real-time health monitoring display
- [x] Toast notifications for user feedback
- [x] Form validation with error messages
- [x] Loading states for async operations

### Out of Scope (Future Phase)

- Multi-user support beyond single-device owner
- Kubernetes orchestration
- Built-in CI/CD pipelines
- Database management UI (phpMyAdmin-style)
- File browser beyond code-server
- Built-in terminal (relies on code-server)
- Mobile app companion
- Collaborative editing features
- Automatic SSL certificate management (handled by Cloudflare)
- Built-in package registry
- Git hosting (relies on GitHub/GitLab)

## Technical Architecture

### Technology Stack

#### Backend
- **Framework**: Laravel 12 (PHP 8.2+)
- **Architecture**: Service-oriented with Livewire components
- **Database**: SQLite (default), with support for MySQL/MariaDB/PostgreSQL
- **Queue**: Database-driven job processing
- **Cache**: Database cache with Redis support
- **Authentication**: Session-based with optional tunnel auth

#### Frontend
- **Styling**: Tailwind CSS v4
- **Components**: Livewire 4 with Alpine.js
- **Build Tool**: Vite 7
- **Icons**: Blade Icons (assumed based on conventions)

#### Infrastructure
- **Target Hardware**: Raspberry Pi 5 (ARM64)
- **Containerization**: Docker with Docker Compose
- **Web Server**: PHP built-in server (production uses external server)
- **Tunnel**: Cloudflare (cloudflared daemon)
- **IDE**: code-server (VS Code in browser)

### Key Components

#### Service Layer
| Service | Responsibility |
|---------|---------------|
| `DeviceRegistryService` | Device identity, QR pairing, cloud registration |
| `DeviceStateService` | Mode management (pairing/wizard/dashboard) |
| `DeviceHealthService` | System resource monitoring |
| `WizardProgressService` | Wizard state persistence and navigation |
| `ProjectScaffoldService` | Create projects from templates |
| `ProjectCloneService` | Clone GitHub repositories |
| `ProjectContainerService` | Docker container lifecycle |
| `PortAllocatorService` | Dynamic port allocation for projects |
| `TunnelService` | Cloudflare tunnel management |
| `QuickTunnelService` | Temporary tunnel creation |
| `CloudApiClient` | Cloud API communication with circuit breaker |
| `GitHubDeviceFlowService` | GitHub OAuth device flow |
| `CodeServerService` | VS Code server lifecycle |
| `AiToolConfigService` | AI provider configuration |
| `BackupService` | Encrypted backup/restore |
| `AnalyticsService` | Event tracking and metrics |
| `ConfigSyncService` | Configuration synchronization |
| `NetworkService` | Network connectivity checks |

#### Livewire Components
| Component | Purpose |
|-----------|---------|
| `WizardController` | Orchestrates setup wizard flow |
| `Wizard/*` | Individual wizard steps (Welcome, Tunnel, GitHub, etc.) |
| `Dashboard/Overview` | Main dashboard with health metrics |
| `Dashboard/ProjectList` | Project listing and management |
| `Dashboard/ProjectCreate` | Project creation wizard |
| `Dashboard/ProjectDetail` | Project details and actions |
| `Dashboard/TunnelManager` | Tunnel configuration and status |
| `Dashboard/ContainerMonitor` | Docker container monitoring |
| `Dashboard/SystemSettings` | Device configuration |
| `Dashboard/AiToolsConfig` | AI provider management |
| `Dashboard/AiServicesHub` | AI-powered tools |
| `Dashboard/CodeEditor` | VS Code integration |
| `Dashboard/AnalyticsDashboard` | Usage analytics |
| `Dashboard/HealthBar` | System health display |
| `Pairing/PairingScreen` | QR code pairing UI |
| `TunnelLogin` | Tunnel authentication prompt |

#### Console Commands
| Command | Purpose |
|---------|---------|
| `device:generate-id` | Generate device UUID |
| `device:show-qr` | Display pairing QR code |
| `device:poll-pairing` | Poll cloud for pairing status |
| `device:health` | Display health metrics |
| `device:factory-reset` | Reset device to initial state |
| `heartbeat:check` | Check component heartbeats |
| `schedule:status` | Monitor scheduled tasks |
| `logs:export` | Export application logs |
| `tunnel:poll-status` | Poll tunnel status |

### Data Models

#### Core Models

**Project**
- `name`, `slug` - Project identifiers
- `framework` - Laravel, React, Vue, etc. (Enum)
- `status` - Stopped, Running, Scaffolding, Cloning (Enum)
- `path` - Local filesystem path
- `port` - Allocated port number
- `clone_url` - GitHub repository URL
- `container_id` - Docker container ID
- `tunnel_subdomain_path` - Public URL path
- `tunnel_enabled` - Whether tunnel is active
- `env_vars` - Encrypted environment variables
- `last_started_at`, `last_stopped_at` - Activity tracking
- Soft deletes supported

**DeviceState**
- Key-value store for device configuration
- Used for: device_mode, pairing status, etc.

**CloudCredential**
- Device UUID, hardware serial
- Cloud API credentials
- Pairing status tracking

**TunnelConfig**
- Cloudflare tunnel token
- Subdomain configuration
- Skip/enable flags

**QuickTunnel**
- Temporary tunnel configurations
- Project association
- Expiration tracking

**WizardProgress**
- Current wizard step
- Step completion status
- Navigation history

**AiProviderConfig**
- Provider type (OpenAI, Anthropic, etc.)
- API keys (encrypted)
- Model selection
- Custom endpoints
- Timeout settings

**GitHubCredential**
- Access token (encrypted)
- Refresh token (encrypted)
- Expiration timestamp
- Scopes granted

**ProjectLog**
- Activity logging for projects
- Timestamps and descriptions

**AnalyticsEvent**
- Event tracking data
- Timestamps and metadata

### External Integrations

#### Required Services
1. **VibeCodePC Cloud** (`vibecodepc.com`)
   - Device pairing and registration
   - API for tunnel management
   - Heartbeat monitoring

2. **Cloudflare**
   - Tunnel daemon (cloudflared)
   - DNS and SSL termination
   - Edge network routing

3. **GitHub**
   - OAuth device flow for authentication
   - Repository access
   - Webhook support (future)

#### Optional Services
1. **AI Providers**
   - OpenAI (GPT models)
   - Anthropic (Claude models)
   - OpenRouter (unified API)
   - HuggingFace (open models)
   - Custom endpoints

2. **Docker Hub**
   - Container image pulling
   - Private registry support

3. **Package Registries**
   - npm, Packagist, PyPI, etc.
   - Project dependency resolution

### Infrastructure Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     User Browser                           │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                  Cloudflare Edge                          │
│          (SSL termination, DDoS protection)               │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│              Cloudflare Tunnel                              │
│         (cloudflared daemon on Raspberry Pi)               │
└────────────────────┬────────────────────────────────────────┘
                     │
         ┌───────────┴────────────┐
         │                        │
         ▼                        ▼
┌──────────────────┐   ┌──────────────────┐
│   Laravel App    │   │   code-server    │
│   (Port 8000)    │   │   (Port 8443)    │
│                  │   │                  │
│  ┌────────────┐  │   │  ┌────────────┐  │
│  │  Dashboard │  │   │  │ VS Code    │  │
│  │  Wizard    │  │   │  │ Editor     │  │
│  │  API       │  │   │  │ Terminal   │  │
│  └────────────┘  │   │  └────────────┘  │
└──────────────────┘   └──────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│              Docker Containers                              │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐                   │
│  │ Project 1│ │ Project 2│ │ Project N│ ...                  │
│  │ (Port X) │ │ (Port Y) │ │ (Port Z) │                     │
│  └──────────┘ └──────────┘ └──────────┘                     │
└─────────────────────────────────────────────────────────────┘
```

## Requirements

### Functional Requirements

1. **Device Setup**
   - Device must generate unique identity on first run
   - Must support QR code pairing with cloud service
   - Must allow local-only mode without cloud pairing
   - Must persist configuration across restarts

2. **Wizard Experience**
   - Multi-step wizard with progress persistence
   - Skip options for optional steps
   - Validation before step completion
   - Resume capability if interrupted

3. **Project Management**
   - Create projects from templates (Laravel, React, Vue, Python)
   - Clone from GitHub repositories
   - Run/stop/restart containers
   - View project logs and status
   - Configure environment variables
   - Generate public URLs via tunnel

4. **Remote Access**
   - Secure tunnel via Cloudflare
   - Optional authentication for tunnel access
   - Subdomain-based project URLs
   - Quick tunnels for temporary access

5. **AI Integration**
   - Configure multiple AI providers
   - Validate API keys before saving
   - AI-powered coding assistance via code-server extensions

6. **System Management**
   - Monitor CPU, RAM, disk, temperature
   - Color-coded health indicators
   - Encrypted backup/restore
   - Factory reset capability
   - Update notifications (future)

### Non-Functional Requirements

#### Performance
- Dashboard load time: < 2 seconds
- Project creation: < 30 seconds (including container build)
- Health metrics update: Real-time (every 5 seconds)
- Tunnel establishment: < 10 seconds
- Support for up to 10 concurrent projects

#### Security
- All sensitive data encrypted at rest (API keys, tokens)
- Session-based authentication
- Optional tunnel authentication
- CSRF protection on all forms
- Input validation and sanitization
- No secrets in logs or error messages

#### Reliability
- Graceful degradation when cloud is unavailable
- Automatic retry with circuit breaker pattern
- Health checks for critical services
- Queue-based job processing for long operations
- Transaction safety for critical operations

#### Scalability
- SQLite for single-device (supports up to ~1GB)
- Migration path to MySQL/PostgreSQL for larger deployments
- Configurable resource limits (CPU, RAM per container)
- Queue workers for background processing

#### Maintainability
- 90%+ test coverage
- PSR-12 compliant code style
- Comprehensive documentation
- Structured logging
- Circuit breaker for external APIs

### Technical Constraints

1. **Hardware**
   - Target: Raspberry Pi 5 (ARM64)
   - Minimum: Raspberry Pi 4 (4GB RAM)
   - Storage: SD card (recommend high-endurance)

2. **Software**
   - PHP 8.2+ required
   - Docker 24.0+ required
   - Linux-based OS (Raspberry Pi OS or Ubuntu)

3. **Network**
   - Internet required for cloud pairing
   - Local-only mode works without internet
   - Port 8000 (device app) and 8443 (code-server) default

4. **Dependencies**
   - cloudflared daemon for tunneling
   - Docker for containerization
   - Composer for PHP dependencies
   - Node.js for frontend build

## Dependencies

### System Dependencies
- Docker Engine 24.0+
- Docker Compose
- cloudflared
- PHP 8.2+ with extensions: sqlite3, mbstring, xml, ctype, json, openssl
- Composer 2.x
- Node.js 18+ and npm

### PHP Dependencies
- laravel/framework ^12.0
- livewire/livewire ^4.1
- chillerlan/php-qrcode ^5.0
- vibecodepc/common @dev (local package)

### Node.js Dependencies
- tailwindcss ^4.0
- vite ^7.0
- laravel-vite-plugin ^2.0
- axios ^1.11

### External Services
- VibeCodePC Cloud API
- Cloudflare (tunnel daemon)
- GitHub OAuth
- AI Provider APIs (optional)

## Assumptions

1. Device runs on Raspberry Pi or compatible ARM64 system
2. Single user per device (no multi-tenancy)
3. Internet connection available for initial setup
4. User has basic familiarity with web development
5. Docker and cloudflared can be installed on target system
6. Cloud service (vibecodepc.com) remains operational
7. User manages their own AI API keys

## Constraints

### Technical
- SQLite default database (single file, limited concurrency)
- ARM64 architecture (affects Docker image availability)
- Resource-constrained environment (RPi hardware)
- Single-device deployment (not horizontally scalable)

### Business
- Open source MIT license
- No built-in revenue model (user manages own infrastructure)
- Cloud service dependency for tunnel discovery

### Timeline
- Initial release: Q2 2025
- Target stability: Production-ready by Q3 2025

## Success Criteria

1. User can complete wizard setup in < 10 minutes
2. Project creation success rate > 95%
3. Tunnel uptime > 99% when configured
4. Dashboard responsive (< 2s load time)
5. 90%+ test coverage maintained
6. Zero critical security vulnerabilities
7. Documentation covers all user-facing features

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Cloud service unavailable | High | Local-only mode fallback, circuit breaker |
| Docker resource exhaustion | High | Resource limits, health monitoring |
| SD card failure | High | Backup system, external storage option |
| Security vulnerabilities | Critical | Regular audits, encrypted storage |
| Cloudflare rate limiting | Medium | Circuit breaker, exponential backoff |
| GitHub API changes | Low | Abstraction layer, graceful degradation |

## Future Considerations

### Phase 2 (Post-MVP)
- Multi-project templates
- Built-in database management
- Enhanced analytics
- Plugin system
- Mobile companion app

### Phase 3 (Long-term)
- Multi-device sync
- Team collaboration
- CI/CD pipeline integration
- Kubernetes support
- Enterprise features
