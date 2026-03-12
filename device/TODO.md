## Done

[x] 2026-03-11 - feat: Add `device:health` artisan command to display comprehensive device metrics
[x] 2026-03-11 - test: Add feature tests for DeviceHealth command
[x] 2026-03-11 - test: Add unit tests for FactoryReset command (confirmation prompt, force flag, truncation order)
[x] 2026-03-11 - Fix the tests , run php artisan test
[x] 2026-03-11 - Add circuit breaker pattern to CloudApiClient with exponential backoff
[x] 2026-03-11 - Implement port range validation and exhaustion handling in PortAllocatorService
[x] 2026-03-11 - Add return value checks for file operations in TunnelService
[x] 2026-03-11 - Add disk space validation before token file operations
[x] 2026-03-11 - Unit tests for PollTunnelStatus command edge cases (timeout handling, invalid responses, retry logic)
[x] 2026-03-11 - Unit tests for PollPairingStatus command
[x] 2026-03-11 - Unit tests for CloneProjectJob edge cases
[x] 2026-03-11 - Unit tests for RequireTunnelAuth middleware
[x] 2026-03-11 - Unit tests for Project model
[x] 2026-03-11 - Add retry logic with exponential backoff to CloudApiClient
[x] 2026-03-11 - Refactor to replace generic Exception catches with specific types
[x] 2026-03-11 - Add transaction handling to services updating multiple records
[x] 2026-03-11 - Extract tunnel detection logic into DetectsTunnel trait
[x] 2026-03-11 - Document error handling patterns in CLAUDE.md
[x] 2026-03-11 - Create CHANGELOG.md following Keep a Changelog format
[x] 2026-03-11 - Implement rate limiting differentiation between authenticated and unauthenticated users
[x] 2026-03-11 - feat: Add `device:schedule-status` artisan command to view scheduled task status
[x] 2026-03-11 - test: Add feature tests for ScheduleStatus command

## Todo

- [x] 2026-03-11 - feat: Add `device:export-logs` artisan command to export recent logs for debugging
- [x] 2026-03-11 - test: Add feature tests for HealthBar Livewire component (poll action, metric display)
- [x] 2026-03-11 - feat: Implement project soft-delete with restore functionality in Project model
- [x] 2026-03-11 - test: Add unit tests for AnalyticsService (trackEvent, getAggregatedData, getEventCount)
- [x] 2026-03-11 - test: Add feature tests for ContainerMonitor Livewire component (container actions, log viewing)
- [x] 2026-03-12 - chore: Add scheduled task monitoring to detect missed heartbeat runs
- [x] 2026-03-12 - refactor: Optimize ContainerMonitor to use cursor pagination for large project lists
- [x] 2026-03-12 - feat: Add analytics dashboard Livewire component to view system events
- [x] 2026-03-12 - test: Add edge case tests for CircuitBreaker (half-open state, threshold edge cases)
- [x] 2026-03-12 - docs: Document factory reset safety requirements in README troubleshooting section
- [ ] feat: Add confirmation code requirement to FactoryReset command for safety

## In Progress

