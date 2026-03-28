<div class="space-y-6" x-data="aiAgentConfigs()" x-init="init()">
  <div>
    <h2 class="text-lg font-semibold text-white">AI Agent Configs</h2>
    <p class="text-gray-400 text-sm mt-0.5">Edit configuration files for AI agents: Boost, OpenCode, Claude Code, and Copilot.</p>
  </div>

  {{-- Read-Only Notice (only shown when pairing is required and device is not paired/verified) --}}
  @if ($isReadOnly)
    <div class="bg-amber-500/10 border border-amber-500/20 rounded-lg p-4">
      <div class="flex items-start gap-3">
        <div class="flex-shrink-0">
          <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <div class="flex-1">
          <h3 class="text-sm font-medium text-amber-400">Read-Only Mode</h3>
          <p class="text-xs text-amber-400/80 mt-1">
            {{ $readOnlyReason }}
            <a href="{{ route('dashboard.tunnels') }}" class="underline hover:text-amber-300">Go to Tunnels</a> to configure remote access.
          </p>
        </div>
      </div>
    </div>
  @endif

  {{-- Unpaired Warning (shown when pairing is optional and device is not paired) --}}
  @if (!$isPairingRequired && !$isPaired)
    <div class="bg-blue-500/10 border border-blue-500/20 rounded-lg p-4">
      <div class="flex items-start gap-3">
        <div class="flex-shrink-0">
          <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="flex-1">
          <h3 class="text-sm font-medium text-blue-400">Running Without Pairing</h3>
          <p class="text-xs text-blue-400/80 mt-1">
            This device is running without cloud pairing. Local editing is enabled, but some features (remote access, cloud sync) require pairing.
            <a href="{{ route('pairing') }}" class="underline hover:text-blue-300">Pair your device</a> for full functionality.
          </p>
        </div>
      </div>
    </div>
  @endif

  {{-- Status Message --}}
@if ($statusMessage)
<div @class([
    'rounded-lg p-4 text-sm border',
    'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' => $statusType === 'success',
    'bg-red-500/10 border-red-500/20 text-red-400' => $statusType === 'error',
    'bg-amber-500/10 border-amber-500/20 text-amber-400' => $statusType === 'warning',
])>
    {{ $statusMessage }}
</div>
@endif

    {{-- Project Selector --}}
    @if ($projects->count() > 0)
        <div class="bg-white/[0.02] rounded-xl border border-white/[0.06] p-4">
            <label class="block text-sm font-medium text-gray-400 mb-2">Project Context</label>
            <select
                wire:model.live="selectedProjectId"
                class="w-full bg-gray-900 border border-white/10 rounded-lg px-3 py-2 text-sm text-gray-300 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20"
            >
                <option value="">Global / Device-level configs</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-2">
                Select a project to edit project-specific configuration files (e.g., OpenCode project settings, Claude Code project settings).
            </p>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="border-b border-white/[0.06] flex gap-1 overflow-x-auto">
        @foreach ($configFiles as $key => $config)
            @php
                $isProjectScoped = $config['scope'] ?? 'global' === 'project';
                $isApplicable = !$isProjectScoped || $selectedProjectId !== null || in_array($key, ['boost', 'copilot_instructions']);
            @endphp
            @if ($isApplicable)
                <button
                    @click="switchTab('{{ $key }}')"
                    :class="activeTab === '{{ $key }}' ? 'text-emerald-400 border-emerald-400' : 'text-gray-500 border-transparent hover:text-gray-300'"
                    class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap"
                >
                    {{ $config['label'] }}
                    @if ($isProjectScoped)
                        <span class="ml-1 text-xs text-gray-600">(project)</span>
                    @endif
                    @if ($isDirty[$key] ?? false)
                        <span class="ml-1.5 w-2 h-2 bg-amber-500 rounded-full inline-block"></span>
                    @endif
                </button>
            @endif
        @endforeach
    </div>

    {{-- Config File Editors --}}
    @foreach ($configFiles as $key => $config)
        @php
            $isProjectScoped = $config['scope'] ?? 'global' === 'project';
            $isApplicable = !$isProjectScoped || $selectedProjectId !== null || in_array($key, ['boost', 'copilot_instructions']);
        @endphp
        @if ($isApplicable)
            <div x-show="activeTab === '{{ $key }}'" x-cloak class="space-y-4">
                <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-4">
                    {{-- Header --}}
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-400">{{ $config['label'] }}</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ $config['description'] }}</p>
                            <div class="mt-2 text-xs font-mono text-gray-600">
                                @if ($selectedProjectId && ($config['scope'] ?? 'global') === 'project' && isset($config['path_template']))
                                    {{ str_replace('{project_path}', $projects->firstWhere('id', $selectedProjectId)?->path ?? '/project/path', $config['path_template']) }}
                                @else
                                    {{ $config['path'] ?? $config['path_template'] ?? 'N/A' }}
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if (!($fileExists[$key] ?? false))
                                <span class="text-xs text-amber-400 bg-amber-500/10 px-2 py-1 rounded">File does not exist</span>
                            @endif
                            @if ($isDirty[$key] ?? false)
                                <span class="text-xs text-amber-400 bg-amber-500/10 px-2 py-1 rounded">Unsaved changes</span>
                            @endif
            @if (!($isValid[$key] ?? true))
              <span class="text-xs text-red-400 bg-red-500/10 px-2 py-1 rounded">Invalid JSON</span>
            @endif
          </div>
        </div>

        {{-- Reload Status --}}
        @if (isset($reloadStatuses[$key]) && !empty($reloadStatuses[$key]['services']))
        <div class="bg-blue-500/5 border border-blue-500/10 rounded-lg p-3">
          <div class="flex items-start gap-3">
            <div class="flex-shrink-0 mt-0.5">
              <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs text-blue-300 font-medium">Affected Services</p>
              <div class="mt-1 flex flex-wrap gap-2">
                @foreach ($reloadStatuses[$key]['services'] as $service)
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-blue-500/10 text-blue-300 border border-blue-500/20">
                  {{ $service['name'] }}
                  @if ($service['type'] === 'mcp')
                  <span class="ml-1 text-blue-400/60">(auto-detect)</span>
                  @elseif ($service['type'] === 'cli')
                  <span class="ml-1 text-amber-400/60">(restart required)</span>
                  @elseif ($service['type'] === 'vscode')
                  <span class="ml-1 text-emerald-400/60">(hot-reload)</span>
                  @endif
                </span>
                @endforeach
              </div>
              @if ($reloadStatuses[$key]['requires_manual_reload'])
              <p class="mt-2 text-xs text-blue-400/80 leading-relaxed">
                <span class="font-medium">Reload Instructions:</span> {{ $reloadStatuses[$key]['instructions'] }}
              </p>
              @endif
            </div>
          </div>
        </div>
        @endif

        {{-- Validation Error --}}
                    @if (!($isValid[$key] ?? true) && ($validationErrors[$key] ?? ''))
                        <div class="bg-red-500/10 border border-red-500/20 rounded-lg p-3 text-red-400 text-xs">
                            {{ $validationErrors[$key] }}
                        </div>
                    @endif

                    {{-- Monaco Editor Container --}}
                    <div class="relative">
                        <div
                            id="editor-{{ $key }}"
                            class="w-full h-[500px] bg-gray-900 border rounded-xl overflow-hidden {{ ($isValid[$key] ?? true) ? 'border-white/10' : 'border-red-500/50' }}"
                        ></div>
                        {{-- Hidden textarea for Livewire binding --}}
                        <textarea
                            wire:model.live="fileContent.{{ $key }}"
                            id="textarea-{{ $key }}"
                            class="hidden"
                        >{{ $fileContent[$key] ?? '' }}</textarea>
                    </div>

                    {{-- Actions Bar --}}
                    <div class="flex items-center justify-between pt-2">
                        <div class="flex items-center gap-2">
                            {{-- Format JSON button (only for JSON files) --}}
                            @if ($key !== 'copilot_instructions')
        <button
          @click="formatEditor('{{ $key }}')"
          type="button"
          @disabled($isReadOnly)
          class="px-3 py-1.5 bg-white/[0.06] hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed text-gray-400 hover:text-white text-xs rounded-lg transition-colors"
        >
          Format JSON
        </button>
        @endif

                            {{-- Backup dropdown --}}
                            @if (!empty($backups[$key] ?? []))
                                <div class="relative" x-data="{ open: false }">
                                    <button
                                        @click="open = !open"
                                        type="button"
                                        class="px-3 py-1.5 bg-white/[0.06] hover:bg-white/10 text-gray-400 hover:text-white text-xs rounded-lg transition-colors flex items-center gap-1"
                                    >
                                        Restore Backup
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div
                                        x-show="open"
                                        @click.away="open = false"
                                        class="absolute left-0 top-full mt-1 w-64 bg-gray-900 border border-white/10 rounded-lg shadow-xl z-10"
                                    >
                                        @foreach ($backups[$key] as $backup)
              <button
                wire:click="restore('{{ $key }}')"
                wire:confirm="Restore this backup? Current unsaved changes will be lost."
                @click="open = false"
                @disabled($isReadOnly)
                class="w-full text-left px-3 py-2 text-xs text-gray-400 hover:bg-white/5 hover:text-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                                                {{ \Carbon\Carbon::createFromTimestamp($backup['created_at'])->format('M j, Y g:i A') }}
                                                ({{ number_format($backup['size'] / 1024, 1) }} KB)
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            {{-- Reset button --}}
        <button
          wire:click="resetToDefaults('{{ $key }}')"
          wire:confirm="Reset {{ $config['label'] }} to defaults? This cannot be undone."
          type="button"
          @disabled($isReadOnly)
          class="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 disabled:opacity-50 disabled:cursor-not-allowed text-red-400 text-sm rounded-lg transition-colors"
        >
          Reset to Defaults
        </button>

        {{-- Save button --}}
        <button
          wire:click="save('{{ $key }}')"
          wire:loading.attr="disabled"
          wire:target="save"
          @disabled(!($isDirty[$key] ?? false) || !($isValid[$key] ?? true) || $isReadOnly)
          class="px-5 py-2 bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 disabled:cursor-not-allowed text-gray-950 text-sm font-medium rounded-xl transition-colors"
        >
          <span wire:loading.remove wire:target="save">Save Changes</span>
          <span wire:loading wire:target="save">Saving...</span>
        </button>
                        </div>
                    </div>
                </div>

{{-- File Info --}}
<div class="flex items-center justify-between text-xs text-gray-600 px-2">
  <div>
    @if ($fileExists[$key] ?? false)
      @php
        $filePath = $config['path'] ?? '';
        if ($selectedProjectId && ($config['scope'] ?? 'global') === 'project' && isset($config['path_template'])) {
          $project = $projects->firstWhere('id', $selectedProjectId);
          if ($project) {
            $filePath = str_replace('{project_path}', $project->path, $config['path_template']);
          }
        }
      @endphp
      @if ($filePath && \Illuminate\Support\Facades\File::exists($filePath))
        <span>Last modified: {{ \Carbon\Carbon::createFromTimestamp(filemtime($filePath))->diffForHumans() }}</span>
      @else
        <span>File exists</span>
      @endif
    @else
      <span>File will be created on save</span>
    @endif
  </div>

  {{-- Reload Services Button --}}
  @if (isset($reloadStatuses[$key]) && !empty($reloadStatuses[$key]['services']))
  <button
    wire:click="triggerReload('{{ $key }}')"
    wire:loading.attr="disabled"
    wire:target="triggerReload"
    type="button"
    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 text-xs rounded-lg transition-colors"
  >
    <svg wire:loading.remove wire:target="triggerReload" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
    </svg>
    <svg wire:loading wire:target="triggerReload" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <span wire:loading.remove wire:target="triggerReload">Reload Services</span>
    <span wire:loading wire:target="triggerReload">Reloading...</span>
  </button>
  @endif
</div>
            </div>
        @endif
    @endforeach

    {{-- Monaco Editor Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.min.js"></script>
    <script>
        function aiAgentConfigs() {
            return {
                activeTab: @entangle('activeTab'),
                editors: {},
                monacoReady: false,
    schemas: @json($schemas),
    selectedProjectId: @entangle('selectedProjectId'),
    isReadOnly: {{ $isReadOnly ? 'true' : 'false' }},

    init() {
                    // Load Monaco Editor
                    require.config({
                        paths: {
                            vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs'
                        }
                    });

                    require(['vs/editor/editor.main'], () => {
                        this.monacoReady = true;
                        this.registerSchemas();
                        this.initEditors();
                    });

                    // Watch for Livewire updates
                    Livewire.on('fileContentUpdated', ({ key, content }) => {
                        if (this.editors[key] && this.editors[key].getValue() !== content) {
                            this.editors[key].setValue(content);
                        }
                    });

                    // Watch for project change to reload content
                    this.$watch('selectedProjectId', () => {
                        // Content will be reloaded by Livewire, re-init editors after delay
                        setTimeout(() => {
                            this.initEditors();
                        }, 100);
                    });
                },

                registerSchemas() {
                    // Register JSON schemas for validation
                    const schemaMapping = {
                        'boost': this.schemas.boost || null,
                        'opencode_global': this.schemas.opencode || null,
                        'opencode_project': this.schemas.opencode || null,
                        'claude_global': this.schemas.claude || null,
                        'claude_project': this.schemas.claude || null,
                        'copilot_instructions': null
                    };

                    monaco.languages.json.jsonDefaults.setDiagnosticsOptions({
                        validate: true,
                        allowComments: true,
                        schemas: Object.entries(schemaMapping)
                            .filter(([_, uri]) => uri !== null)
                            .map(([key, uri]) => ({
                                uri: uri,
                                fileMatch: [`*${key}*`]
                            }))
                    });
                },

                initEditors() {
                    const configKeys = Object.keys(@json($configFiles));

                    configKeys.forEach(key => {
                        const container = document.getElementById(`editor-${key}`);
                        const textarea = document.getElementById(`textarea-${key}`);

                        if (!container || !textarea) return;

                        // Skip if already initialized
                        if (this.editors[key]) {
                            // Update content if changed
                            if (this.editors[key].getValue() !== textarea.value) {
                                this.editors[key].setValue(textarea.value);
                            }
                            return;
                        }

                        // Determine language based on key
                        let language = 'json';
                        if (key === 'copilot_instructions') {
                            language = 'markdown';
                        }

    // Create editor
    this.editors[key] = monaco.editor.create(container, {
      value: textarea.value,
      language: language,
      theme: 'vs-dark',
      automaticLayout: true,
      minimap: { enabled: false },
      scrollBeyondLastLine: false,
      fontSize: 13,
      lineNumbers: 'on',
      renderLineHighlight: 'line',
      matchBrackets: 'always',
      tabSize: 2,
      insertSpaces: true,
      folding: true,
      foldingStrategy: 'auto',
      showFoldingControls: 'always',
      wordWrap: 'on',
      wrappingStrategy: 'advanced',
      suggest: {
        showKeywords: true,
        showSnippets: true
      },
      readOnly: this.isReadOnly
    });

                        // Listen for changes and update Livewire
                        this.editors[key].onDidChangeModelContent(() => {
                            const value = this.editors[key].getValue();
                            textarea.value = value;
                            textarea.dispatchEvent(new Event('input', { bubbles: true }));
                        });

                        // Update validation markers
                        this.editors[key].onDidChangeModelDecorations(() => {
                            const markers = monaco.editor.getModelMarkers({ resource: this.editors[key].getModel().uri });
                            const hasErrors = markers.some(m => m.severity === monaco.MarkerSeverity.Error);
                            // Update Livewire validation state
                            if (hasErrors) {
                                const errorMessages = markers
                                    .filter(m => m.severity === monaco.MarkerSeverity.Error)
                                    .map(m => m.message)
                                    .join('; ');
                                @this.set(`validationErrors.${key}`, errorMessages);
                                @this.set(`isValid.${key}`, false);
                            } else {
                                @this.set(`validationErrors.${key}`, '');
                                @this.set(`isValid.${key}`, true);
                            }
                        });
                    });
                },

                switchTab(key) {
                    this.activeTab = key;
                    // Layout editor after tab switch (needed for Monaco to render correctly)
                    this.$nextTick(() => {
                        if (this.editors[key]) {
                            this.editors[key].layout();
                        }
                    });
                },

                formatEditor(key) {
                    if (this.editors[key]) {
                        this.editors[key].getAction('editor.action.formatDocument').run();
                    }
                }
            };
        }
    </script>
</div>
