## Todo

- [x] 2026-03-13 test: add unit tests for AnalyticsEvent model (event creation, aggregation queries, property storage)
- [x] 2026-03-13 test: add edge case tests for AnalyticsService (database failures, large dataset aggregation, concurrent writes)
- [x] 2026-03-13 test: add unit tests for DeviceState model (key-value storage, default values, type casting)
- [x] 2026-03-13 test: add edge case tests for DeviceStateService (invalid state transitions, missing keys, cache interactions)
- [x] 2026-03-13 test: add unit tests for WizardProgress model state transitions (completed steps, percentage calculation)
- [x] 2026-03-13 test: add edge case tests for ProjectLog model (relationships with Project, filtering by type, ordering)
- [x] 2026-03-13 test: add unit tests for ConfigSyncService (sync failures, partial updates, validation errors)
- [x] 2026-03-13 test: add integration tests for rate limiting middleware (burst scenarios, header assertions, boundary conditions)
- [x] 2026-03-13 test: add edge case tests for BackupService (corrupted backup files, disk full scenarios, large file handling)
- [ ] fix: handle edge case where tunnel token file exists but is empty or malformed
- [ ] test: add unit tests for NetworkService (IP detection failures, interface changes, timeout handling)
- [ ] test: add edge case tests for CodeServerService (config write failures, port conflicts, permission errors)
- [ ] docs: document error handling patterns and retry strategies in Services
- [ ] refactor: extract magic numbers in ProjectContainerService to configurable constants
- [ ] chore: add PHPStan static analysis check to CI workflow

## Done

[x] 2026-03-13 run the unit tests and fix all failing
