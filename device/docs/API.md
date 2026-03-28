# VibeCodePC API Documentation

This document describes all public routes and endpoints available in the VibeCodePC device application.

## Table of Contents

- [Overview](#overview)
- [Base URLs](#base-urls)
- [Authentication](#authentication)
- [Rate Limiting](#rate-limiting)
- [API Endpoints](#api-endpoints)
  - [Health Check](#health-check)
- [Web Routes](#web-routes)
  - [Public Routes](#public-routes)
  - [Dashboard Routes (Protected)](#dashboard-routes-protected)
  - [Tunnel Authentication](#tunnel-authentication)
- [Error Responses](#error-responses)
- [Response Headers](#response-headers)

---

## Overview

VibeCodePC exposes two types of public endpoints:

1. **API Endpoints** (`/api/*`) - JSON-based REST API for programmatic access
2. **Web Routes** (`/*`) - Livewire-powered dashboard interface (HTML responses)

All API endpoints return JSON responses and use appropriate HTTP status codes.

---

## Base URLs

| Environment | Base URL | Description |
|------------|----------|-------------|
| Local Development | `http://localhost:8000` | Default local development server |
| Raspberry Pi | `http://raspberrypi.local:8000` | Device on local network |
| Tunneled | `https://*.vibecodepc.com` | Via Cloudflare Tunnel (when paired) |

---

## Authentication

### Local Access (No Authentication Required)

When accessing the device on your local network (`http://raspberrypi.local:8000`), the dashboard is freely accessible without authentication.

### Tunnel Access (Password Authentication)

When accessing via Cloudflare Tunnel (remote access), you must authenticate:

1. **Initial Access**: First request redirects to `/tunnel/login`
2. **Authentication Method**: Device admin password
3. **Session Persistence**: Authenticated via Laravel session (`tunnel_authenticated`)
4. **Session Duration**: Configured by `SESSION_LIFETIME` (default: 1 week)

**Authentication Flow:**
```
User -> Tunnel URL -> CF-Connecting-IP header detected
   -> Redirect to /tunnel/login
   -> Submit device admin password
   -> Session created
   -> Redirect to intended URL
```

**Headers That Trigger Tunnel Auth:**
- `CF-Connecting-IP` - Indicates request came through Cloudflare

---

## Rate Limiting

API endpoints are protected by rate limiting:

| Endpoint | Limit | Window | Key |
|----------|-------|--------|-----|
| All API routes | 60 requests | 1 minute | IP address or User ID |

**Rate Limit Headers (All Responses):**
```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
Retry-After: <seconds> (when limited)
```

**Rate Limit Response (429):**
```json
{
  "error": "Too Many Requests",
  "message": "Rate limit exceeded. Please try again later.",
  "retry_after": 45
}
```

---

## API Endpoints

### Health Check

Monitor the health status of the device and its subsystems.

```
GET /api/health
```

**Authentication:** None required

**Rate Limiting:** 60 requests/minute

**Request:**
```bash
curl -X GET http://localhost:8000/api/health
```

**Success Response (200 OK):**
```json
{
  "status": "healthy",
  "timestamp": "2026-03-10T14:30:22+00:00",
  "checks": {
    "database": "ok"
  },
  "metrics": {
    "cpu_percent": 15.2,
    "ram_used_mb": 2048,
    "ram_total_mb": 8192,
    "ram_percent": 25.0,
    "disk_used_gb": 45.5,
    "disk_total_gb": 128.0,
    "disk_percent": 35.5,
    "temperature_c": 42.5
  }
}
```

**Failed Response (503 Service Unavailable):**
```json
{
  "status": "unhealthy",
  "timestamp": "2026-03-10T14:30:22+00:00",
  "checks": {
    "database": "failed"
  },
  "metrics": {
    "cpu_percent": 15.2,
    "ram_used_mb": 2048,
    "ram_total_mb": 8192,
    "ram_percent": 25.0,
    "disk_used_gb": 45.5,
    "disk_total_gb": 128.0,
    "disk_percent": 35.5,
    "temperature_c": 42.5
  }
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `status` | string | `"healthy"` or `"unhealthy"` |
| `timestamp` | string | ISO 8601 formatted timestamp |
| `checks.database` | string | `"ok"` or `"failed"` |
| `metrics.cpu_percent` | float | CPU usage percentage (0-100) |
| `metrics.ram_used_mb` | int | RAM used in megabytes |
| `metrics.ram_total_mb` | int | Total RAM in megabytes |
| `metrics.ram_percent` | float | RAM usage percentage |
| `metrics.disk_used_gb` | float | Disk space used in gigabytes |
| `metrics.disk_total_gb` | float | Total disk space in gigabytes |
| `metrics.disk_percent` | float | Disk usage percentage |
| `metrics.temperature_c` | float/null | CPU temperature in Celsius (null if unavailable) |

**Common Use Cases:**
- Load balancer health checks
- Monitoring system uptime
- Pre-deployment validation
- Heartbeat monitoring

---

## Web Routes

### Public Routes

Routes accessible without authentication (local network) or via tunnel authentication.

#### Home / Root

Redirects to the appropriate starting point based on device mode.

```
GET /
```

**Behavior:**
| Device Mode | Redirects To |
|-------------|--------------|
| `pairing` | `/pairing` |
| `wizard` | `/wizard` |
| `dashboard` | `/dashboard` |
| (default) | `/pairing` |

**Response:** HTTP 302 Redirect

---

#### Device Pairing Screen

Display QR code and pairing information for cloud registration.

```
GET /pairing
```

**Response:** HTML (Livewire Component: `PairingScreen`)

**Access:** Public

**Features:**
- Displays device QR code
- Shows pairing URL
- Pairing status indicator
- Link to skip pairing (local-only mode)

---

#### Setup Wizard

Multi-step first-run configuration wizard.

```
GET /wizard
```

**Response:** HTML (Livewire Component: `WizardController`)

**Access:** Public (only when device_mode = 'wizard')

**Wizard Steps:**
1. **Welcome** - Introduction and device information
2. **Network Setup** - Configure network settings
3. **Cloudflare Tunnel** - Set up remote access (optional)
4. **GitHub** - Connect GitHub account (optional)
5. **Code Server** - Configure code-server settings
6. **AI Services** - Configure AI provider API keys
7. **Complete** - Final confirmation and redirect to dashboard

---

#### Tunnel Login

Authentication page for tunnel (remote) access.

```
GET /tunnel/login
```

**Response:** HTML (Livewire Component: `TunnelLogin`)

**Access:** Public

**Form Fields:**
- `password` (string, required) - Device admin password

**Authentication:**
- Validates against device admin password stored in `admin_password` in `device_state` table
- Creates session on success
- Redirects to intended URL

---

### Dashboard Routes (Protected)

All dashboard routes are protected by the `tunnel.auth.optional` middleware:
- **Local access**: Passes through freely
- **Tunnel access**: Requires authentication via `/tunnel/login`

#### Dashboard Overview

Main dashboard with system overview and quick actions.

```
GET /dashboard
GET /dashboard/overview
```

**Response:** HTML (Livewire Component: `Overview`)

**Features:**
- System health metrics
- Project status summary
- Quick tunnel management
- Device pairing status

---

#### Project Management

**List All Projects:**
```
GET /dashboard/projects
```

**Response:** HTML (Livewire Component: `ProjectList`)

**Features:**
- View all projects with status
- Filter by status (running, stopped, error)
- Quick actions (start, stop, restart)
- Create new project link

---

**Create New Project:**
```
GET /dashboard/projects/create
```

**Response:** HTML (Livewire Component: `ProjectCreate`)

**Form Fields:**
- `name` (string, required) - Project name
- `framework` (string, required) - One of: `laravel`, `nextjs`, `astro`, `fastapi`, `html`, `custom`
- `description` (string, optional) - Project description
- `repository_url` (string, optional) - GitHub repository URL
- `local_port` (int, optional) - Custom local port (auto-assigned if empty)

---

**View Project Details:**
```
GET /dashboard/projects/{project}
```

**Parameters:**
- `project` (int) - Project ID

**Response:** HTML (Livewire Component: `ProjectDetail`)

**Features:**
- Project information
- Container status and logs
- Environment variables
- Quick tunnel management
- Project actions (start, stop, restart, delete)

---

#### AI Services Hub

Manage AI provider configurations.

```
GET /dashboard/ai-services
```

**Response:** HTML (Livewire Component: `AiServicesHub`)

**Features:**
- Configure OpenAI API key
- Configure Anthropic API key
- Configure OpenRouter API key
- Configure HuggingFace API key
- Test connections

---

#### Code Editor

Access the embedded code-server instance.

```
GET /dashboard/code-editor
```

**Response:** HTML (Livewire Component: `CodeEditor`)

**Features:**
- Launch code-server in iframe
- Configure code-server settings
- GitHub integration status

---

#### Tunnel Management

Manage Cloudflare tunnels and quick tunnels.

```
GET /dashboard/tunnels
```

**Response:** HTML (Livewire Component: `TunnelManager`)

**Features:**
- View tunnel status
- Start/stop tunnel
- Create quick tunnels for projects
- View tunnel metrics
- Configure tunnel settings

**Quick Tunnel Actions:**
- `provision()` - Create new quick tunnel
- `revoke($tunnelId)` - Remove quick tunnel
- `copyUrl($tunnelId)` - Copy tunnel URL to clipboard

---

#### Container Monitor

View and manage Docker containers.

```
GET /dashboard/containers
```

**Response:** HTML (Livewire Component: `ContainerMonitor`)

**Features:**
- View running containers
- Container logs
- Resource usage (CPU, memory)
- Start/stop containers
- View container details

---

#### System Settings

Configure device settings.

```
GET /dashboard/settings
```

**Response:** HTML (Livewire Component: `SystemSettings`)

**Features:**
- System information
- Backup/restore
- Timezone settings
- Admin password management
- Device reset options

---

## Error Responses

### Standard Error Format

All API errors return JSON with consistent structure:

```json
{
  "error": "ErrorType",
  "message": "Human-readable error description"
}
```

### HTTP Status Codes

| Status | Meaning | Common Causes |
|--------|---------|---------------|
| 200 | OK | Request successful |
| 302 | Found | Redirect (e.g., home route) |
| 400 | Bad Request | Invalid parameters |
| 401 | Unauthorized | Not authenticated (tunnel access) |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource not found |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error |
| 503 | Service Unavailable | Health check failed (unhealthy system) |

### Common Error Responses

**Authentication Required (401 equivalent for tunnel):**
```
Redirect: /tunnel/login
```

**Rate Limit Exceeded (429):**
```json
{
  "error": "Too Many Requests",
  "message": "Rate limit exceeded. Please try again later.",
  "retry_after": 45
}
```

**Not Found (404):**
```html
<!-- Laravel's 404 error page -->
```

**Server Error (500):**
```json
{
  "error": "Internal Server Error",
  "message": "An unexpected error occurred"
}
```

---

## Response Headers

### Standard Headers (All Responses)

| Header | Value | Description |
|--------|-------|-------------|
| `Content-Type` | `application/json` (API) / `text/html` (Web) | Response format |
| `X-RateLimit-Limit` | `60` | Request limit per window |
| `X-RateLimit-Remaining` | `<count>` | Requests remaining |
| `Retry-After` | `<seconds>` | Seconds until retry (429 only) |
| `X-Frame-Options` | `SAMEORIGIN` | Clickjacking protection |
| `X-Content-Type-Options` | `nosniff` | MIME sniffing protection |

### Tunnel-Specific Headers

When accessed via Cloudflare Tunnel:

| Header | Description |
|--------|-------------|
| `CF-Connecting-IP` | Original client IP address |
| `CF-RAY` | Cloudflare Ray ID |
| `CF-Visitor` | Visitor scheme (HTTP/HTTPS) |

---

## Middleware Summary

| Middleware | Applied To | Purpose |
|------------|------------|---------|
| `api.rate_limit` | `/api/*` | Rate limiting (60 req/min) |
| `tunnel.auth.optional` | `/dashboard/*` | Optional auth for tunnel access |
| `web` | All web routes | Session, CSRF protection |
| `api` | `/api/*` | JSON responses, stateless |

---

## Testing Examples

### Health Check

```bash
# Basic health check
curl http://localhost:8000/api/health

# With headers
curl -i http://localhost:8000/api/health

# Parse just the status
curl -s http://localhost:8000/api/health | jq '.status'
```

### Rate Limit Testing

```bash
# Make 61 requests to trigger rate limit
for i in {1..61}; do
  curl -s http://localhost:8000/api/health > /dev/null
  echo "Request $i"
done

# Check rate limit headers
curl -i http://localhost:8000/api/health 2>&1 | grep -E "X-RateLimit|Retry-After"
```

### Tunnel Authentication Testing

```bash
# Simulate tunnel request (should redirect to login)
curl -H "CF-Connecting-IP: 1.2.3.4" -L http://localhost:8000/dashboard

# Local request (should pass through)
curl http://localhost:8000/dashboard
```

---

## Livewire Component Actions

Dashboard components expose Livewire actions for real-time updates:

### Tunnel Manager Actions

```javascript
// JavaScript examples for Livewire interactions

// Provision a quick tunnel
Livewire.find('tunnel-manager').component.provision();

// Revoke a tunnel
Livewire.find('tunnel-manager').component.revoke('tunnel-id-123');

// Copy tunnel URL
Livewire.find('tunnel-manager').component.copyUrl('tunnel-id-123');
```

### Project List Actions

```javascript
// Start a project
Livewire.find('project-list').component.startProject(1);

// Stop a project
Livewire.find('project-list').component.stopProject(1);

// Restart a project
Livewire.find('project-list').component.restartProject(1);
```

### Container Monitor Actions

```javascript
// Refresh container list
Livewire.find('container-monitor').component.refresh();

// View container logs
Livewire.find('container-monitor').component.viewLogs('container-id');
```

---

## Rate Limiting Details

### Rate Limit Algorithm

- **Algorithm**: Token bucket (Laravel's RateLimiter)
- **Key Resolution**: User ID if authenticated, otherwise IP address
- **Header Format**: `X-RateLimit-Key: user:1` or `X-RateLimit-Key: ip:192.168.1.1`

### Rate Limit Scenarios

**Authenticated User:**
```http
X-RateLimit-Key: user:42
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
```

**Guest/Anonymous:**
```http
X-RateLimit-Key: ip:192.168.1.100
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 58
```

---

## Changelog

### 2026-03-10
- Initial API documentation
- Documented `/api/health` endpoint
- Documented all dashboard routes
- Documented authentication flow
- Documented rate limiting

---

## Additional Resources

- [Laravel Documentation](https://laravel.com/docs/12.x)
- [Livewire Documentation](https://livewire.laravel.com/docs)
- [Cloudflare Tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/)
- [Device Documentation](../README.md)
