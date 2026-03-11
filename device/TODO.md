## Todo

### Priority 1: Critical Missing Tests (Correctness)
- [x] 2026-03-10 test: add unit tests for PollTunnelStatus command edge cases (timeout handling, invalid responses, retry logic)
- [x] 2026-03-10 test: add unit tests for PollPairingStatus command (network failures, already paired state, invalid device responses)
- [x] 2026-03-10 test: add unit tests for CloneProjectJob (clone failures, retry logic, partial failure scenarios)
- [x] 2026-03-10 test: add unit tests for RequireTunnelAuth middleware (missing tokens, expired tokens, valid tunnel requests)
- [x] 2026-03-10 test: add unit tests for Project model (status transitions, relationships, business logic methods)

### Priority 2: Error Handling & Edge Cases (Correctness)
- [x] 2026-03-10 fix: add retry logic with exponential backoff to CloudApiClient for transient failures
- [x] 2026-03-10 fix: add port range validation (0-65535) and exhaustion handling to PortAllocatorService
- [x] 2026-03-10 fix: add return value checks for file_put_contents() in TunnelService with proper error handling
- [x] 2026-03-10 fix: add disk full validation before token file operations in TunnelService

### Priority 3: Documentation
- [x] 2026-03-10 docs: create CHANGELOG.md following Keep a Changelog format with initial version
- [x] 2026-03-10 docs: document error handling patterns and retry strategies in CLAUDE.md

### Priority 4: Code Quality & Refactoring
- [x] 2026-03-10 refactor: add transaction handling to services that update multiple database records
- [x] 2026-03-10 refactor: replace generic \Throwable catches with specific exception types in CloudApiClient

### Priority 5: Features & Enhancements
- [x] 2026-03-11 feat: add circuit breaker pattern for CloudApiClient to prevent cascading failures
- [x] 2026-03-11 feat: add rate limit differentiation between authenticated and unauthenticated users
- [x] 2026-03-11 feat: add middleware for request ID tracking to improve debugging

### Priority 6: Chores & Maintenance
- [x] 2026-03-11 chore: add return type declarations to all service methods missing them
- [ ] chore: add strict types declaration to all service classes
- [ ] chore: verify and fix any Pint code style violations across the codebase

## Done
- [x] 2026-03-11 chore: add return type declarations to all service methods missing them
- [x] 2026-03-10 Fix the unit tests
- [x] 2026-03-10 refactor: extract duplicate tunnel detection logic from RequireTunnelAuth and OptionalTunnelAuth into a shared trait
