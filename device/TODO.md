## Todo

### Phase 1: Foundation & Performance (In Progress)

#### Database & Model Optimizations
- [x] 2026-03-17 `perf:` Add caching layer for high-frequency port allocation queries (S)
  - Implemented `ProjectRepository::getUsedPorts()` with 60-second cache TTL
  - Added `ProjectObserver` to invalidate cache on project create/update/delete
  - PortAllocatorService now uses repository method instead of raw queries
- [x] 2026-03-17 `perf:` Optimize tunnel display queries with column selection (S)
  - Implemented `ProjectRepository::allForTunnelDisplay()` with selective column loading
  - Added comprehensive test coverage for column selection optimization
  - Reduces memory usage by only loading required columns for tunnel display

#### Service Layer Enhancements
- [ ] `refactor:` Implement repository pattern for complex queries (M)
  - Create ProjectRepository class
  - Move raw SQL from services to repository
  - See PLAN.md Phase 1 for implementation details
- [ ] `refactor:` Extract hardcoded timeout values to configuration (S)
  - Move HTTP client timeouts to config/vibecodepc.php
  - See PLAN.md Phase 1 for implementation details

#### Event-Driven Architecture
- [ ] `feat:` Replace polling with event-driven tunnel status updates (M)
  - Implement PollTunnelUrlJob
  - Create QuickTunnelUrlDiscovered event
  - Remove sleep-based polling in QuickTunnelService
  - See PLAN.md Phase 1 for implementation details

### Reference
- See [PLAN.md](./PLAN.md) for complete implementation roadmap
- See [SCOPE.md](./SCOPE.md) for product requirements and architecture

---

## Done

### OpenCode Configuration Plan (Completed 2026-03-16)
- [x] 2026-03-16 Create backup of `~/.config/opencode/opencode.json`
- [x] 2026-03-16 Create backup of `~/.local/share/opencode/auth.json`
- [x] 2026-03-16 Document existing providers in config
- [x] 2026-03-16 Verify auth keys are valid and current
- [x] 2026-03-16 Check which providers are actively used
- [x] 2026-03-16 Update/add provider configurations
- [x] 2026-03-16 Review timeout settings
- [x] 2026-03-16 Verify all model configurations
- [x] 2026-03-16 Set permissions appropriately
- [x] 2026-03-16 Ensure proper key format for each provider
- [x] 2026-03-16 Run `opencode --version` to verify config loads
- [x] 2026-03-16 Test each provider with a simple query
- [x] 2026-03-16 Verify timeout behavior works as expected
- [x] 2026-03-16 Check permissions are working correctly
- [x] 2026-03-16 Document provider-specific settings
- [x] 2026-03-16 Add comments explaining custom configurations
- [x] 2026-03-16 Create troubleshooting guide for common issues

### Code Review Findings (Completed 2026-03-17)
- [x] 2026-03-17 verify: Product scope documented in SCOPE.md, ready to proceed to planning phase
- [x] 2026-03-16 verify: All interface tests passing, ready for production
- [x] 2026-03-17 review: Code review complete - code quality is high with only minor recommendations
- [x] 2026-03-16 verify: All tests passing, ready to proceed to next workflow step
- [x] 2026-03-16 feat: add OpenAI and Cohere API key fields to Environment tab in AI Tools Config UI
- [x] 2026-03-16 feat: add Opencode API key field to Environment tab in AI Tools Config UI
- [x] 2026-03-17 verify: Code review complete, no critical issues found
- [x] 2026-03-17 refactor: Add repository pattern abstraction for complex queries
- [x] 2026-03-16 docs: Add inline comments for complex port allocation logic in PortAllocatorService
- [x] 2026-03-17 verify: No security vulnerabilities found
- [x] 2026-03-17 security: Encrypt sensitive data in `~/.bashrc` section markers (enhancement)
- [x] 2026-03-16 performance: PortAllocatorService line 226 - `Project::pluck('port')` could use caching for high-frequency allocations
- [x] 2026-03-16 performance: `Project::all()` queries in TunnelManager lines 411, 478 - optimized with `allForTunnelDisplay()` method that selects only required columns
- [x] 2026-03-17 performance: QuickTunnelService line 239 - sleep-based polling replaced with event-driven approach using PollTunnelUrlJob and events
- [x] PSR-12 compliance (Pint formatting passes)
- [x] Proper error handling with try/catch blocks
- [x] Logging throughout (73 Log calls, 61 structured log calls)
- [x] Design patterns: Service Layer, Repository, Circuit Breaker, DTOs
- [x] Queue jobs for long-running operations
- [x] Constructor property promotion used throughout
- [x] Enum-driven configuration
- [x] 2026-03-17 improve: Add comprehensive PHPDoc array shapes for complex return types (enhancement)
- [x] 2026-03-17 improve: Add specific exception types instead of generic \Throwable catches
