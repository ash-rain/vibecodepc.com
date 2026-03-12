## Todo

## In Progress

## Done
- [x] 2026-03-12 fix: Corrected CircuitBreaker test expectations for reopen behavior
  - Fixed two tests that incorrectly expected `isClosed()` to return `false` after reopening
  - Updated expectations to correctly reflect that circuit transitions to half-open after recovery timeout
  - Tests: `it transitions to open immediately on first failure in half-open state`
  - Tests: `it reopens on single failure after multiple successes in half-open`
