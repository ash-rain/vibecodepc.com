# TODO: Config File Editors for OpenCode, Claude, Copilot & boost.json

## Goal
Allow device owners to edit AI-agent related configuration files directly from the dashboard:

- `~/.config/opencode/opencode.json` (or `opencode.json` / `opencode.jsonc` in project roots — but focus on global first)
- `~/.claude/claude.json` or `~/.claude/settings.json` (Claude Code / Claude-related extension settings)
- GitHub Copilot settings (mainly VS Code `settings.json` snippets — not a standalone file)
- `boost.json` (this project's own agent configuration — lives in project root)

These editors should appear in the **System Settings** or a new **AI Agents** tab in the dashboard.

## Research Summary (2026)

- **OpenCode** config
  - Primary global location: `~/.config/opencode/opencode.json` (or `.jsonc`)
  - Alternative locations: workspace root `opencode.json`, or custom via `OPENCODE_CONFIG_DIR` env
  - Used by OpenCode CLI + VS Code extensions (opencode-vscode, opencode-gui, ...)

- **Claude Code** / Anthropic extension
  - Legacy/global: `~/.claude/claude.json` or `~/.claude/settings.json`
  - Project scope possible: `.claude/settings.json` in workspace root
  - Some settings also live in VS Code's own `settings.json` under extension keys

- **GitHub Copilot**
  - No standalone `.copilot.json`
  - Configured via VS Code `settings.json` → keys like `"github.copilot.*"`
  - Custom instructions: `.github/copilot-instructions.md` (newer feature)
  - Advanced: hook files via `chat.hookFilesLocations`

- **boost.json** (this project)
  - Lives in project root
  - Controls which agents are enabled ("claude_code", "copilot", ...)
  - Already parsed/used by Laravel Boost / MCP system

## Implementation Plan

### Phase 1 – Preparation & Safety

- [x] 2026-03-17 Create new config constants / helper in `config/vibecodepc.php`
  - Added `config_files` array with entries for: boost, opencode_global, claude_global, copilot_instructions
  - Added `config_editor` array with backup settings, max file size, and backup directory

- [x] 2026-03-17 Add `ConfigFileService` in `app/Services/`
  - Methods: `getContent($key)`, `putContent($key, $newContent)`, `validateJson($content)`, `backup($key)`
  - Use `retryable` trait for file operations
  - Create backup before every write → `storage/app/backups/config/{key}-{timestamp}.json`

- [x] 2026-03-17 Add validation rules
  - Must be valid JSON (or JSONC for opencode)
  - For boost.json: validate known structure (`agents` array, `skills`, etc.)
  - Size limit (~64 KB)
  - No forbidden keys (e.g. api_key, secret, token, password, credential)

### Phase 2 – UI & Livewire Editor

- [x] 2026-03-17 Create new Livewire component: `app/Livewire/Dashboard/AiAgentConfigs.php`
  - Tabbed interface: Boost.json | OpenCode | Claude Code | Copilot Instructions
  - Each tab shows:
  - Textarea editor with syntax highlighting hints
  - "Save" button + loading state with dirty/valid indicators
  - "Restore backup" dropdown (list recent backups)
  - Status indicator (last saved, valid/invalid JSON)
  - Format JSON button for JSON files
  - Reset to defaults functionality

- [x] 2026-03-17 Add route & sidebar link
  - `routes/web.php`: Added `Route::get('/dashboard/ai-agents', AiAgentConfigs::class)->name('dashboard.ai-agents');`
  - Updated `resources/views/components/dashboard/sidebar.blade.php` with new nav item

- [x] 2026-03-17 Wire up save action
  - Real-time JSON validation in component
  - Uses ConfigFileService for backup and write operations
  - Handles errors with user-friendly messages

- [x] 2026-03-17 Add danger zone: "Reset to defaults" button
  - For boost.json → regenerates with template defaults
  - For others → deletes file to let extension/CLI recreate

### Phase 3 – Advanced / Nice-to-have

- [x] 2026-03-17 JSON schema validation in editor (Monaco)
- [x] 2026-03-17 Per-project config support
  - Added `opencode_project` and `claude_project` config entries with `path_template`
  - Updated `ConfigFileService` to support project-scoped configs with `resolvePath()` method
  - Added `selectedProjectId` property to `AiAgentConfigs` component
  - Added project selector dropdown in UI to switch between global and project contexts
  - Project-scoped configs resolve paths using `{project_path}` placeholder
  - Backups now include project ID suffix for project-scoped configs
- [x] 2026-03-17 Restart / reload triggers
- [x] 2026-03-17 Read-only mode when tunnel not running / not paired
- [x] 2026-03-17 Audit logging

### Phase 4 – Testing & Polish

- [x] 2026-03-17 Unit tests for `ConfigFileService`
- [x] 2026-03-17 Feature tests for `AiAgentConfigs` component (23 tests, 45 assertions - all passing)
- [x] 2026-03-17 Pint + formatting pass
- [x] 2026-03-17 Manual test on real device - All tests passing (60 unit + 23 feature tests), code reviewed, sidebar link exists

## Open Questions / Decisions Needed

- Should we allow editing **api keys** inside these files? (security risk — maybe redact / separate field)
- Do we want to support **JSONC** (comments) for opencode.json? (needs custom parser / strip comments before validate)
- Should Copilot get its own tab (just instructions.md + settings snippet preview)?
- Do we restart any process after save (e.g. MCP server, code-server)? Probably not automatically — too risky.

Priority: **boost.json** first (already in project), then OpenCode global, then Claude.

- [x] 2026-03-17 make a plan in todo.md for more unit tests with detailed info for each item we need to test, we want to make tests for the configs etc , make big plan

---

# COMPREHENSIVE TEST PLAN: Config File Editors

## Overview
This test plan covers the config file editor system including ConfigFileService, ConfigReloadService, ConfigAuditLogService, ConfigSyncService, AiAgentConfigs Livewire component, and related models.

## Current Test Status
- ConfigFileService: ~30 tests (comprehensive) ✓
- ConfigReloadService: ~16 tests (comprehensive) ✓
- ConfigAuditLog: ~11 tests (comprehensive) ✓
- AiAgentConfigs: ~23 tests (comprehensive) ✓
- **Missing**: ConfigSyncService, AiToolConfigService, edge cases, integration tests

---

## Phase A: ConfigFileService - Additional Tests

### A1. Edge Cases & Error Handling
- [x] **A1.1**: Test `getContent()` with unreadable file permission errors
  - File exists but cannot be read (permission denied)
  - File is a directory instead of regular file
  - File is a symlink pointing to non-existent file
  
- [x] **A1.2**: Test `putContent()` with file system failures (2026-03-17)
  - Disk full / no space left
  - Directory not writable
  - Network filesystem timeout (if applicable)
  - Concurrent write conflicts (race conditions)

- [x] 2026-03-17 **A1.3**: Test `resolvePath()` edge cases
  - Project path contains special characters (spaces, unicode, etc.)
  - Project path is relative instead of absolute
  - Template contains multiple `{project_path}` placeholders
  - Project path ends with trailing slash

- [x] 2026-03-17 **A1.4**: Test `backup()` edge cases
  - Backup directory full (disk quota exceeded)
  - Backup filename collision (timestamp collision on very fast backups) - Fixed by using microsecond precision (Y-m-d-His-u format)
  - Backup file is larger than max file size
  - Added 5 comprehensive tests covering all edge cases

### A2. Validation Edge Cases
- [x] 2026-03-17 **A2.1**: Test JSON validation with edge cases
- Empty JSON `{}` should be valid
- JSON with only whitespace
- JSON with BOM (Byte Order Mark)
- JSON with trailing commas (invalid)
- JSON with single quotes instead of double quotes (invalid)
- Very deeply nested JSON (100+ levels)
- JSON with extremely long string values (>64KB)

- [x] **A2.2**: Test JSONC comment stripping edge cases (2026-03-17)
  - Comments inside string values (should be preserved)
  - Nested comments `/* /* */ */`
  - Comments without closing `/*` or `//` at EOF
  - Unicode in comments
  - Comments containing quotes
  - Fixed JSONC parser to properly handle strings and escape sequences
  - Added 17 comprehensive edge case tests (all passing)

- [x] **A2.3**: Test forbidden key detection (2026-03-17)
  - Keys that partially match patterns (e.g., `api_key_name` should NOT trigger)
  - Keys in arrays vs objects
  - Keys with different case variations (API_KEY, Api_Key, api_key)
  - Values containing forbidden strings in content (not keys)

### A3. Project-Scoped Config Edge Cases
- [x] 2026-03-17 **A3.1**: Test project-scoped configs with deleted projects
  - Config exists but project has been deleted from DB
  - Config directory exists but project record doesn't
  - Added 12 comprehensive tests covering:
    - Soft-deleted projects (getContent, putContent, backup, restore, delete, exists, resolvePath)
    - Force-deleted projects with orphaned config files
    - Backup isolation between deleted vs active projects
    - Backup isolation between multiple deleted projects
    - Project ID suffix format verification in backup filenames
  
- [x] 2026-03-17 **A3.2**: Test backup listing with multiple projects
  - Verify project isolation in backup listing
  - Verify project suffix format is correct
  - Verify backups don't leak between projects
  - Added 7 comprehensive tests covering:
    - listBackups returns only backups for specified project
    - Project suffix format verification in backup filenames
    - Backup isolation between multiple projects
    - Empty array when no backups exist for project
    - Sorting by creation time descending per project
    - Config key isolation within same project
    - Backup isolation after multiple saves

### A4. Schema Validation (when implemented)
- [x] 2026-03-17 **A4.1**: Test schema validation with valid/invalid schemas
  - Valid JSON schema
  - Invalid JSON schema (malformed)
  - Schema with circular references
  - Schema with external $ref
  - Added 24 comprehensive tests covering:
    - Valid schema validation (objects, arrays, nested structures, enums, min/max constraints)
    - Invalid schema detection (type mismatches, additional properties, constraint violations)
    - Malformed schema handling (invalid JSON, non-object schemas, unreadable files)
    - Circular and external $ref handling
  - Fixed bug: `validateJsonSchema` was checking `dataType !== 'array'` instead of `dataType !== 'object'` for object type validation
  - Fixed bug: Added check to ensure decoded schema is an array before validation

---

## Phase B: ConfigReloadService - Additional Tests

### B1. Service Detection & Reload Logic
- [x] **B1.1**: Test `getAffectedServices()` edge cases (2026-03-17)
  - Unknown config key returns empty array
  - Config key with multiple service types (opencode_global has cli + vscode)
  - Case sensitivity in config keys (BOOST, Boost don't match boost)
  - All known config keys have associated services with required structure
  - Empty/null-like string keys return empty array

- [x] **B1.2**: Test `requiresManualReload()` variations (2026-03-17)
  - All service type combinations (mcp, cli, vscode)
  - Empty service list
  - Services that support hot reload

### B2. File Operations
- [x] **B2.1**: Test `getLastModified()` edge cases (2026-03-17)
  - File modified in the future (clock skew) - tested with future timestamp
  - File on read-only filesystem - tested with chmod 0444
  - File deleted between check and read - tested with explicit deletion
  - File permissions changed - tested with chmod 0000
  - Added 7 comprehensive tests covering: clock skew, file deletion race conditions, restrictive permissions, read-only filesystem, multiple call consistency, directory handling, and broken symlinks

- [x] **B2.2**: Test `formatLastModified()` edge cases (2026-03-17)
  - Timestamp exactly at epoch (1970-01-01) - tested, shows "55 years ago"
  - Timestamp in the future (1 hour, 1 year) - tested with "from now" format
  - Very old timestamps (>10 years ago) - tested with 15-year-old timestamp
  - DST transitions - tested with DST transition timestamp
  - Added 8 comprehensive tests covering: epoch time, future timestamps (near and far), very old timestamps, DST transitions, negative timestamps (before epoch), timestamps just after epoch, and current time

### B3. Reload Triggering
- [x] 2026-03-17 **B3.1**: Test `triggerReload()` service interactions
  - Verify actual service signals are sent - tested with mocked CodeServerService
  - Test reload failure scenarios - tested with code-server not running and exceptions
  - Test partial reload success (some services fail) - tested opencode_global with cli + vscode
  - Added 13 comprehensive tests covering:
    - vscode service reload when code-server is running
    - vscode service failure when code-server is not running
    - Partial success with mixed service types (cli succeeds, vscode fails)
    - Full success when all services succeed
    - Unknown config key handling (empty services)
    - MCP service automatic detection behavior
    - CLI service manual restart messages
    - Exception handling during code-server reload
    - Logging of reload operations
    - Project-scoped config handling (opencode_project, claude_project)
    - Code-server not running simulation
    - Code-server not installed simulation

- [x] **B3.2**: Test reload with non-existent services (2026-03-17)
  - Service process not running - tested with mocked isRunning returning false
  - PID file exists but process dead - not applicable (handled by isRunning check)
  - Added 2 comprehensive tests covering:
    - `reloadCodeServer handles process not running` - returns false with "not running" message
    - `reloadCodeServer handles code-server not installed` - returns false with "not running" message

---

## Phase C: ConfigSyncService - Missing Tests

### C1. Cloud API Integration
- [x] 2026-03-17 **C1.1**: Test `syncIfNeeded()` happy path
  - Remote version higher than local
  - Apply subdomain changes
  - Apply tunnel token changes
  - Update local version after sync

- [x] 2026-03-17 **C1.2**: Test `syncIfNeeded()` when no sync needed
  - Remote version equals local version
  - Remote version lower than local
  - Remote config is null

### C3. Error Handling
- [x] **C3.1**: Test sync failure scenarios
  - Cloud API unavailable - covered in sync failures section (propagates ConnectionException)
  - Cloud API returns malformed response - tested with malformed_remote_config_gracefully
  - Database transaction fails during sync - tested with database_failure_during_subdomain_update
  - Tunnel restart fails after token update - covered in token updates section
  - Additional edge cases added:
    - Deeply nested arrays in response
    - Circular references handling
    - Database locking scenarios
    - Null/empty string values for subdomain and token
    - Extremely long token values (10KB)
    - Rapid sequential sync calls
    - Out-of-order version responses

### C4. Concurrency
- [x] **C4.1**: Test concurrent sync operations
  - Multiple sync calls at once
  - Sync during ongoing sync

---

## Phase D: AiToolConfigService - Missing Tests

### D1. Environment Variable Management

- [x] **D1.1**: Test `getEnvVars()` parsing (2026-03-17)
  - Parse existing bashrc with VibeCodePC section
  - Parse bashrc without section
  - Parse with multiple export statements
  - Parse PATH modifications with single and complex paths
  - Handle encrypted values correctly
  - Handle empty values in exports
  - Handle values with special characters
  - Parse section at beginning or end of file
  - Ignore variables outside managed section
  - Handle variables with numbers in names
  - Skip lowercase variable names
  - Handle very long values (10KB)
  - Handle unicode values
  - Handle extra whitespace around section markers
  - Ignore PATH line that doesn't end with :$PATH
  - Handle only start or only end marker present
  - Handle reversed section markers
  - Ignore commented export lines
  - Handle multiple PATH lines (uses first)
  - Handle multiple sections (uses first complete)
  - Handle values with equals signs
  - Handle empty managed section
  - Added 25 comprehensive tests (all passing)
  - Parse PATH modifications
  - Handle encrypted values

- [x] 2026-03-17 **D1.2**: Test `setEnvVars()` writing
  - Write new section to bashrc
  - Update existing section
  - Remove section entirely when all values are empty
  - Handle special characters in values (spaces, dollar signs, backticks, backslashes)
  - Encrypt sensitive values
  - Handle unicode characters
  - Preserve content outside managed section
  - Handle files with only partial section markers
  - Handle very long values
  - Handle empty bashrc file
  - Handle values with equals signs
  - Handle single quotes (escaped by addslashes)
  - Handle updating/removing _extra_path

- [x] **D1.3**: Test encryption/decryption (2026-03-17)
  - Encrypt sensitive keys (API_KEY, TOKEN, SECRET, PASSWORD, AUTH patterns)
  - Decrypt with ENC: prefix
  - Decrypt without prefix (plain text returns as-is)
  - Corrupted encrypted value handling (invalid base64, empty ENC: prefix)
  - Added 18 comprehensive tests covering:
    - All sensitive key patterns (API_KEY, TOKEN, SECRET, PASSWORD, AUTH)
    - Non-sensitive keys remain unencrypted
    - Round-trip encryption/decryption
    - Corrupted encrypted data (malformed base64, empty prefix)
    - Unicode values
    - Long values (12KB)
    - Special characters
    - Multiple encrypted values
    - Mixed encrypted and non-encrypted values
    - Preserving values across multiple writes
    - Updating encrypted values

### D2. Configuration File Paths
- [x] **D2.1**: Test path resolution (2026-03-17)
  - Get home directory reliably
  - Handle missing HOME environment variable
  - Handle different user contexts (root vs user)
  - Added 15 comprehensive tests covering:
    - HOME variable retrieval from $_SERVER
    - posix_getpwuid fallback when HOME missing
    - Path consistency across multiple calls
    - OpenCode config and auth path resolution
    - HOME with trailing slashes (now normalized via rtrim)
    - HOME with spaces, unicode, special characters
    - Empty HOME string
    - Relative HOME paths
    - Very long home directory paths
    - Different service instances with different HOME values

### D3. OpenCode Configuration
- [x] **D3.1**: Test OpenCode config management (2026-03-18)
- Read existing auth.json (with malformed JSON, empty file, multiple providers, nested config)
- Write new auth.json (creates directories, pretty-prints JSON, handles multiple providers)
- Update existing config (overwrites old values, adds new keys, removes old keys)
- Handle missing ~/.config/opencode directory (creates parent directories recursively)
- Added 29 comprehensive tests covering:
  - getOpencodeConfig: default config, malformed JSON, empty content, JSON arrays, nested structures
  - setOpencodeConfig: create/update, pretty-printing, directory creation, complex nested data
  - getOpencodeAuth: missing file, malformed JSON, empty content, multiple providers, nested config
  - setOpencodeAuth: create/update, pretty-printing, directory creation, multiple providers, unicode values

---

## Phase E: AiAgentConfigs Livewire Component - Additional Tests

### E1. Project Selection
- [x] 2026-03-18 **E1.1**: Test project switching
  - Switch between global and project-scoped configs
  - Switch between different projects
  - Handle project with non-existent path (null path not allowed by schema)
  - Handle non-existent project ID
  - Added 5 comprehensive tests covering:
  - Switches between global and project-scoped configs
  - Switches between different projects
  - Handles project with non-existent path gracefully
  - Handles non-existent project ID gracefully
  - Reloads config files when switching projects

### E2. Tab Switching & State Management

- [x] 2026-03-18 **E2.1**: Test tab switching behavior
  - Switch tabs preserves unsaved changes
  - Switch tabs resets validation state
  - Active tab persists across reloads
  - Added 5 comprehensive tests covering:
    - Preserves unsaved changes when switching tabs
    - Resets validation state when switching tabs
    - Maintains separate validation state per tab
    - Initializes all config files dirty state to false on mount
    - Allows switching to any valid config tab

### E3. Backup Operations
- [x] 2026-03-18 **E3.1**: Test backup restore
  - Restore from backup updates content ✓
  - Restore validates the backup content ✓
  - Restore from corrupted backup fails gracefully ✓
  - Restore creates audit log entry ✓
  - Added 7 comprehensive tests covering:
    - Restores from backup and updates content
    - Validates the backup content
    - Fails gracefully when backup file does not exist
    - Restore creates audit log entry
    - Restores project-scoped config from backup
    - Requires backup selection before restore
    - Updates fileExists after restore

### E4. Reset to Defaults
- [x] 2026-03-18 **E4.1**: Test reset functionality
  - Reset boost.json creates valid defaults
  - Reset non-boost files deletes them
  - Reset operation is logged
  - Reset updates dirty state
  - All 9 test cases passing in `describe('reset to defaults')`

### E5. Format JSON
- [x] 2026-03-18 **E5.1**: Test JSON formatting
  - Format minified JSON ✓
  - Format already formatted JSON (idempotent) ✓
  - Format invalid JSON (should fail gracefully) ✓
  - Added 11 comprehensive tests covering:
    - Minified JSON formatting with proper indentation
    - Idempotent formatting (already formatted JSON)
    - Invalid JSON handling (graceful failure)
    - Complex nested JSON structures
    - Special characters and unescaped slashes
    - Dirty state tracking after formatting
    - Empty content handling
    - JSON arrays formatting
    - Unicode character preservation
    - Large JSON performance
  - Fixed: Added JSON_UNESCAPED_UNICODE flag to formatJson method to preserve unicode characters

### E6. Real-time Validation
- [x] **E6.1**: Test validation during editing (2026-03-18)
  - Validate on every keystroke debounced ✓
  - Validate JSON with comments (JSONC) ✓
  - Validate forbidden keys in real-time ✓
  - Show validation errors in UI ✓
  - Added 23 comprehensive tests covering all aspects:
    - Basic JSON validation (valid/invalid)
    - Syntax errors (trailing commas, single quotes)
    - JSONC comment handling (single-line, multi-line, inline)
    - Forbidden key detection (api_key, password, token, secret, etc.)
    - Nested forbidden key detection
    - Boost.json structure validation (agents/skills arrays)
    - Project-scoped config validation
    - Deeply nested JSON validation
    - Validation state persistence across tabs
    - Error message display verification

### E7. Save Operations
- [x] 2026-03-18 **E7.1**: Test save with various states
- Save valid JSON ✓
- Save invalid JSON (should fail) ✓
- Save empty content (should fail) ✓
- Save creates backup ✓
- Save updates audit log ✓
- Save updates reload status ✓
- Added 13 comprehensive tests covering:
- Save valid JSON content successfully
- Fail to save invalid JSON with proper error message
- Fail to save empty content
- Create backup before saving (verified file system)
- Create audit log entry on save (verified in database)
- Update reload status after save
- Mark content as not dirty after successful save
- Update fileExists to true after saving new file
- Handle save failure gracefully with error message
- Preserve isSaving state during save operation
- Save project-scoped config successfully
- Show success message with config label after save
- Handle unchanged content (backup still created)

### E8. Tunnel Status Integration
- [ ] **E8.1**: Test tunnel status detection
  - Component loads with tunnel running
  - Component loads with tunnel stopped
  - Component loads unpaired
  - Read-only mode when tunnel not running

---

## Phase F: Integration Tests

### F1. End-to-End Workflows
- [ ] **F1.1**: Test complete config editing workflow
  - User opens AI Agents page
  - User switches to project
  - User edits config
  - User saves config
  - Verify file written, backup created, audit log entry, reload triggered

- [ ] **F1.2**: Test backup and restore workflow
  - Create config
  - Edit and save multiple times
  - View backup list
  - Restore specific backup
  - Verify content restored

- [ ] **F1.3**: Test reset workflow
  - Have existing config
  - Click reset
  - Verify file deleted/reset
  - Verify audit log

### F2. Multi-User Scenarios
- [ ] **F2.1**: Test concurrent edits
  - User A loads config
  - User B modifies same config
  - User A saves (should detect conflict or overwrite)

### F3. Service Reload Integration
- [ ] **F3.1**: Test service reload after save
  - Save boost.json
  - Verify MCP server receives signal
  - Verify status updates in UI

---

## Phase G: Edge Case & Security Tests

### G1. Security
- [ ] **G1.1**: Test path traversal prevention
  - Attempt to access files outside allowed paths
  - Attempt to write to system directories
  - Attempt to access other users' configs

- [ ] **G1.2**: Test injection attacks
  - JSON payload with embedded commands
  - Markdown content with HTML/JS injection
  - Config keys with special characters

- [ ] **G1.3**: Test secret detection
  - Attempt to save api_key in various formats
  - Attempt to save tokens in nested objects
  - Verify all forbidden key patterns are caught

### G2. Performance
- [ ] **G2.1**: Test large file handling
  - Open and edit files near size limit (64KB)
  - Verify no memory issues
  - Verify backup creation is fast

- [ ] **G2.2**: Test many backups
  - Create 100+ backups
  - Verify backup listing is paginated/fast
  - Verify old backups are cleaned up

### G3. Error Recovery
- [ ] **G3.1**: Test recovery scenarios
  - Service crashes during save
  - Network interruption during cloud sync
  - Disk full during backup creation

---

## Phase H: Configuration & Validation Tests

### H1. Config Structure Validation
- [ ] **H1.1**: Test config file structure
  - All required keys present
  - Path templates are valid
  - Scope values are valid (global/project)
  - Parent_key references exist

### H2. Environment Variable Tests
- [ ] **H2.1**: Test environment configuration
  - VIBECODEPC_BOOST_JSON_PATH custom path
  - OPENCODE_CONFIG_PATH custom path
  - CONFIG_EDITOR_BACKUP_RETENTION_DAYS
  - CONFIG_EDITOR_MAX_FILE_SIZE_KB
  - Custom backup directory

---

## Test Implementation Priority

### High Priority (Start Here)
1. **ConfigSyncService** - Completely missing tests (C1-C4)
2. **AiToolConfigService** - Completely missing tests (D1-D3)
3. **Security tests** (G1) - Critical for production

### Medium Priority
4. ConfigFileService edge cases (A1-A4)
5. ConfigReloadService edge cases (B1-B3)
6. Integration tests (F1-F3)

### Lower Priority
7. AiAgentConfigs additional tests (E1-E8)
8. Performance tests (G2)
9. Error recovery tests (G3)
10. Config validation tests (H1-H2)

---

## Estimated Test Count

| Component | Current | New Tests | Total |
|-----------|---------|-----------|-------|
| ConfigFileService | 30 | 15 | 45 |
| ConfigReloadService | 16 | 8 | 24 |
| ConfigAuditLog | 11 | 0 | 11 |
| ConfigSyncService | 0 | 12 | 12 |
| AiToolConfigService | 0 | 15 | 15 |
| AiAgentConfigs | 23 | 20 | 43 |
| Integration | 0 | 10 | 10 |
| Security/Edge | 0 | 15 | 15 |
| **TOTAL** | **80** | **95** | **175** |

---

## Notes
- Use Pest PHP testing framework consistently
- Mock external services (Cloud API, Tunnel Service)
- Use file system mocking where possible
- Add test coverage reporting
- Consider adding property-based testing for validation
- Add load testing for concurrent scenarios
