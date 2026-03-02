Core structural & routing changes
[x] 2026-03-02  Update bootstrap/app.php — remove RequireTunnelAuth middleware from global middleware stack (or make it conditional)
[x] 2026-03-02  Create new middleware OptionalTunnelAuth (checks tunnel token exists ? if yes apply auth, if no ? allow through)
[x] 2026-03-02  Replace RequireTunnelAuth with OptionalTunnelAuth in all relevant route groups (api + web dashboard routes)
[x] 2026-03-02  Add named route alias e.g. 'dashboard' that points to /dashboard/overview (used when not paired)

Wizard flow changes
[x] 2026-03-02  Make TunnelManager / QuickTunnel step skippable in WizardController / WizardProgress
     - added new 'tunnel' step to WizardStep enum in common package
     - created Wizard\Tunnel Livewire component with skip functionality
     - created tunnel blade view with skip and continue options
     - updated WizardController labels to include tunnel step
     - WizardProgressService already supports skipped steps for completion
[x] 2026-03-02  Update Wizard\Tunnel Livewire component
     - show big "Skip for now — use locally" button
     - on skip marks wizard step as skipped and advances
[x] 2026-03-02  Update Complete.blade.php
     ? if skipped_tunnel_step ? show different success message ("Device ready for local use  pair later from settings")
     ? add link/button "Pair device later" ? route('tunnel-manager')
[x] 2026-03-02  Modify WizardProgressService
     - allow wizard to reach 'completed' state even when tunnel step is skipped

Dashboard / UI visibility & guards
[x] 2026-03-02  Update Dashboard\Overview & HealthBar Livewire components
     - added isPaired property to Overview component
     - show prominent "Device not paired  limited to local network" banner / alert when !TunnelConfig::current()?->verified_at
     - HealthBar component displays system metrics only, no pairing status needed
[ ]  Add conditional rendering in sidebar / top-bar
     ? hide or disable "Public URL", "Share", "Remote Access" items when not paired
     ? show "Set up remote access" call-to-action button instead
[ ]  Update TunnelManager Livewire component
     ? already has pairing logic ? make it reachable from dashboard sidebar even after wizard is complete
     ? add "Pair now" / "Set up Cloudflare Tunnel" CTA when not configured

Project & Code-server behavior when unpaired
[ ]  Review ProjectContainerService & CodeServerService
     ? confirm they do NOT depend on tunnel being active (should already be true)
[ ]  In CodeEditor Livewire component
     ? if not paired ? show smaller warning "Code Server is only available on local network (http://localhost:8443 or similar)"
[ ]  Add local access hint in ProjectDetail / CodeEditor
     ? "Open in browser: http://raspberrypi.local:{{ $project->port }}" or similar

Backend model & service adjustments
[ ]  TunnelConfig model  add boolean column nullable_skipped_at (or reuse status = 'skipped')
[ ]  Update TunnelService::hasCredentials() ? also return true if status = 'skipped' ? (debate needed)
[ ]  Add TunnelService::isSkipped() helper
[ ]  Update CloudApiClient calls (heartbeat, reconfigureTunnelIngress, etc.)
     ? early return / no-op when tunnel is skipped/not configured
[ ]  Review ProvisionQuickTunnelJob & CloneProjectJob
     ? make sure they tolerate missing tunnel config gracefully

Tests  critical areas
[x] 2026-03-02  Dashboard\OverviewTest ? test unpaired state banner / reduced sidebar
[ ]  WizardFlowTest ? add test case: complete wizard while skipping tunnel step
[ ]  TunnelManagerTest ? test skip button flow + later pairing from dashboard
[ ]  ProjectDetailTest & CodeEditorTest ? test local access hints when unpaired
[ ]  TunnelServiceTest ? add cases for skipped state

Polish & documentation
[ ]  Update README.md ? explain local-only mode vs paired mode
[ ]  Add comment block in TunnelManager blade + TunnelService class:
     # Temporary: pairing is optional. Full remote access requires pairing.
[ ]  Run full Pint + Pest suite after changes
[ ]  (optional) Add feature flag in config/vibecodepc.php ? 'pairing.required' = false

Nice-to-have / later phase (after basic optional works)
[ ]  Add "Pair Device" card/section on dashboard when unpaired
[ ]  Auto-detect if tunnel became available later ? refresh status
[ ]  Add wizard re-entry path: "Continue setup" button that jumps to tunnel step
[ ]  Analytics / telemetry (if implemented) ? track % of users who skip vs complete pairing