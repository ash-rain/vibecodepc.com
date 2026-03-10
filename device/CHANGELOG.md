# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Retry logic with exponential backoff to `CloudApiClient` for transient network failures
- Port range validation (0-65535) and exhaustion handling to `PortAllocatorService`
- Return value checks for `file_put_contents()` in `TunnelService` with proper error handling
- Disk full validation before token file operations in `TunnelService`
- Unit tests for `PollTunnelStatus` command edge cases (timeout handling, invalid responses, retry logic)
- Unit tests for `PollPairingStatus` command (network failures, already paired state, invalid device responses)
- Unit tests for `CloneProjectJob` (clone failures, retry logic, partial failure scenarios)
- Unit tests for `RequireTunnelAuth` middleware (missing tokens, expired tokens, valid tunnel requests)
- Unit tests for `Project` model (status transitions, relationships, business logic methods)

### Fixed

- Unit tests for `PollTunnelStatus` command now properly cover edge cases
- OptionalTunnelAuthMiddleware test now uses protected route as intended

## [0.1.0] - 2026-03-10

### Added

- Initial release of VibeCodePC Device App
- Monorepo scaffolding with device app and shared package structure
- Device identity system with UUID generation and QR code pairing
- Device pairing and onboarding flow via cloud API
- Multi-step setup wizard with Livewire components:
  - Welcome step with device overview
  - AI provider configuration (OpenAI, Anthropic, OpenRouter, HuggingFace)
  - GitHub OAuth integration for repository access
  - VS Code: Server configuration
  - Cloudflare Tunnel setup for remote access
- Full dashboard with project management:
  - Project creation and deletion
  - Container lifecycle management via Docker
  - Git repository cloning with authentication
  - Code-server integration for browser-based editing
- AI provider management with API key configuration
- Cloudflare Tunnel management (local-only and paired modes)
- Device backup and restore system with encrypted ZIP archives
- Artisan commands:
  - `device:generate-id` — Generate unique device UUID
  - `device:show-qr` — Display pairing QR code
  - `device:poll-pairing` — Poll cloud API for pairing status
  - `device:tunnel-status` — Check tunnel health status
  - `device:configure-tunnel` — Configure Cloudflare tunnel settings
- Laravel 12 integration with Livewire 4 and Tailwind CSS v4
- Pest 4 testing framework with comprehensive test suite
- GitHub Actions CI/CD workflows for automated testing
- API documentation with request/response examples
- Environment-based configuration for local development and production
- SQLite database support with migrations
- Session and cache management via database driver
- Queue system for background job processing
- Tailwind CSS styling with responsive design
- Alpine.js for client-side interactivity

### Security

- Encrypted storage of sensitive data (API keys, tokens) using Laravel Crypt
- Tunnel authentication middleware for secure access control
- GitHub OAuth token management with secure storage
- Device identity validation before cloud pairing

[unreleased]: https://github.com/vibecodepc/device/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/vibecodepc/device/releases/tag/v0.1.0
