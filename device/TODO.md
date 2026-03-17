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
- [ ] Feature tests for `AiAgentConfigs` component
- [ ] Pint + formatting pass
- [ ] Manual test on real device

## Open Questions / Decisions Needed

- Should we allow editing **api keys** inside these files? (security risk — maybe redact / separate field)
- Do we want to support **JSONC** (comments) for opencode.json? (needs custom parser / strip comments before validate)
- Should Copilot get its own tab (just instructions.md + settings snippet preview)?
- Do we restart any process after save (e.g. MCP server, code-server)? Probably not automatically — too risky.

Priority: **boost.json** first (already in project), then OpenCode global, then Claude.
