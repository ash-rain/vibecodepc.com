Here is a proposed **TODO.md** file content you can create (or append to) in the root of your project. It focuses on adding editors for **OpenCode config files**, **boost.json** (which controls agents like "claude_code" and "copilot"), and related configuration files inside your Laravel-based **VibeCodePC Device App**.

The plan assumes:

- You want to let the **device owner** edit these configs via the dashboard UI (Livewire components)
- The files are stored on the device filesystem (not inside projects)
- Editing should be safe, with validation, backups, and reload/restart logic where needed
- We'll reuse the existing **CodeEditor** Livewire component as much as possible

```markdown
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

### Phase 1 – Preparation & Safety (1-2 days)

- [ ] Create new config constants / helper in `config/vibecodepc.php`
  ```php
  'config_files' => [
      'boost' => base_path('boost.json'),
      'opencode_global' => home_path('.config/opencode/opencode.json'),  // use Helpers or Carbon::now()->format...
      'claude_global' => home_path('.claude/claude.json'),
      // optional: opencode_project => null (per-project later)
  ],
  ```

- [ ] Add `ConfigFileService` in `app/Services/`
  - Methods: `getContent($key)`, `putContent($key, $newContent)`, `validateJson($content)`, `backup($key)`
  - Use `retryable` trait for file operations
  - Create backup before every write → `storage/app/backups/config/{key}-{timestamp}.json`

- [ ] Add validation rules
  - Must be valid JSON (or JSONC for opencode)
  - For boost.json: validate known structure (`agents` array, `skills`, etc.)
  - Size limit (~64 KB)
  - No forbidden keys (e.g. remove api keys if they appear)

### Phase 2 – UI & Livewire Editor (2-4 days)

- [ ] Create new Livewire component: `app/Livewire/Dashboard/AiAgentConfigs.php`
  - Tabbed interface: Boost.json | OpenCode | Claude Code | Copilot Instructions
  - Each tab shows:
    - Monaco / CodeMirror editor (reuse existing `CodeEditor` component)
    - "Save" button + loading state
    - "Restore backup" dropdown (list recent backups)
    - Status indicator (last saved, valid/invalid JSON)

- [ ] Add route & sidebar link
  - `routes/web.php`: `Route::livewire('/system/ai-agents', AiAgentConfigs::class)->name('system.ai-agents');`
  - Update `resources/views/components/dashboard/sidebar.blade.php`

- [ ] Wire up save action
  ```php
  public function save(string $key)
  {
      $this->validate(['content' => 'required|json']);   // or custom JSONC validator
      $service = app(ConfigFileService::class);
      $service->backup($key);
      $ok = $service->putContent($key, $this->content);
      if (!$ok) $this->addError('Failed to write file (permissions / disk full?)');
  }
  ```

- [ ] Add danger zone: "Reset to defaults" button
  - For boost.json → regenerate from template
  - For others → delete or empty file + let extension/CLI recreate

### Phase 3 – Advanced / Nice-to-have (after core works)

- [ ] JSON schema validation in editor (Monaco)
  - Provide schema URLs / inline schemas for each file type
  - e.g. boost.json → own simple schema
  - opencode.json → `"$schema": "https://opencode.ai/config.json"`

- [ ] Per-project config support (phase 2 extension)
  - Dropdown: "Global" vs "Current Project"
  - Store project-specific paths in `Project` model or config

- [ ] Restart / reload triggers
  - After saving `boost.json` → show toast: "Some changes require page refresh or MCP restart"
  - After opencode/claude → suggest "reload VS Code window" or "restart extension host"

- [ ] Read-only mode when tunnel not running / not paired
  - Show warning banner: "Remote editing disabled until tunnel is active"

- [ ] Audit logging
  - Create `ConfigChange` model / log entry when file is modified

### Phase 4 – Testing & Polish

- [ ] Unit tests for `ConfigFileService`
  - Happy path, disk full, permission denied, invalid JSON
- [ ] Feature tests for `AiAgentConfigs` component
  - Save → file changes
  - Validation errors shown
  - Backup created
- [ ] Pint + formatting pass
- [ ] Manual test on real device
  - Change agent list in boost.json → see if claude_code / copilot behavior changes
  - Add model in opencode.json → verify OpenCode CLI sees it

## Open Questions / Decisions Needed

- Should we allow editing **api keys** inside these files? (security risk — maybe redact / separate field)
- Do we want to support **JSONC** (comments) for opencode.json? (needs custom parser / strip comments before validate)
- Should Copilot get its own tab (just instructions.md + settings snippet preview)?
- Do we restart any process after save (e.g. MCP server, code-server)? Probably not automatically — too risky.

Start with Phase 1 → get ConfigFileService working + basic file read/write tested.

Priority: **boost.json** first (already in project), then OpenCode global, then Claude.
```

Feel free to copy-paste this content into `TODO.md` and start checking off items.

If you want to prioritize one file (e.g. only boost.json first), or change the UI location (e.g. inside System Settings instead of new page), let me know — I can refine the plan. Good luck!