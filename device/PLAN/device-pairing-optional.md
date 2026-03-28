# Plan: Make Device Pairing Optional

Purpose
- Allow the application to run without requiring device pairing while preserving security and auditability. Support both global and per-project opt-in for optional pairing.

Assumptions
- Current codebase treats pairing as required for edits and certain actions; Livewire components and middleware enforce pairing.
- There is an existing tunnel/pairing status service used across the app.

High-level Steps
1. Investigate pairing flow
   - Locate pair checks: Livewire components (e.g., `TunnelLogin`, dashboard components), middleware, controller gates, and services that call pairing status.
   - Identify all UX surfaces and services that currently block actions when unpaired.

2. Add optional config flag
   - Add config: `config/vibecodepc.php` → `device => ['pairing_optional' => false]`.
   - Support env override `VIBECODEPC_DEVICE_PAIRING_OPTIONAL`.
   - Add per-project override (optional): `projects` table or project settings (if already present) to allow project-specific behavior.

3. Guard pairing checks with feature flag
   - Replace hard blocks with conditional checks: if pairing is required (default) then enforce existing behavior; if optional, allow actions but with reduced privileges where applicable.
   - Introduce helper: `DevicePairing::isPairingRequired(Project|null $project = null): bool` to centralize logic.

4. Update Livewire UI and routes
   - Update Livewire components (`app/Livewire/*`) to show clear indicators when pairing is optional and the device is unpaired (readonly vs editable states).
   - Make dashboard `AiAgentConfigs` and other sensitive editors show a banner when pairing is optional and unpaired, with a WARNING and an option to pair for full permissions.
   - For actions that mutate external systems (e.g., change cloud tokens), require pairing or additional confirmation even when optional.

5. Adjust middleware & policies
   - Update any middleware that aborts requests when unpaired to consult `DevicePairing::isPairingRequired()`.
   - Update policy checks to restrict high-risk actions when unpaired (e.g., editing secret-containing files), even if pairing is optional.

6. Audit logging & safety
   - Log events when an unpaired device performs actions that would previously require pairing. Include user, project, action, IP, and timestamp.
   - Ensure backups and forbidden-key checks still run regardless of pairing.

7. Tests
   - Unit tests for `DevicePairing` helper (global and project-level flags).
   - Feature tests for Livewire components in both modes (paired/unpaired and pairing_required true/false).
   - Security tests: ensure forbidden keys still blocked when optional pairing enabled.

8. Documentation & rollout
   - Update docs: `docs/TROUBLESHOOTING.md` and dashboard help text.
   - Add migration or admin UI for toggling global/per-project setting (if required).
   - Rollout plan: default OFF (pairing required); enable opt-in behind feature flag and monitor audit logs.

Implementation Details
- Helper: `app/Services/DevicePairingService.php`
  - Methods: `isPairingRequired(Project|null)`, `isPaired()`, `shouldAllowUnpairedAction(string $action, ?Project $project): bool`.

- Config
  - `config/vibecodepc.php` snippet:
    ```php
    'device' => [
        'pairing_optional' => env('VIBECODEPC_DEVICE_PAIRING_OPTIONAL', false),
    ],
    ```

- Middleware changes
  - Replace direct calls to pairing status with `DevicePairingService::isPairingRequired()` and `isPaired()`.

- Livewire changes
  - `AiAgentConfigs`: show a banner and change save behavior: allow local saves but require explicit confirmation for writes affecting secrets.
  - Components that are read-only when unpaired should consult the helper.

Security Considerations
- Never relax forbidden-key checks or backups. Actions that change secrets must remain restricted to paired devices or require additional confirmations (2-step prompt).
- Ensure logs capture when optional mode allows an action: `pairing.optional.allowed_action` events.

Testing Checklist
- [ ] Unit tests for `DevicePairingService`
- [ ] Feature tests for Livewire editors (paired/unpaired)
- [ ] Security tests for forbidden keys
- [ ] Integration tests for project-scoped behavior

PR Checklist
- [ ] Config entry added and documented
- [ ] Central helper/service added
- [ ] Middleware & policies updated
- [ ] UI updated with clear warnings
- [ ] Tests added and passing
- [ ] Audit logging in place

Rollout
- Stage behind env flag in staging environment, run integration tests and monitor logs for 48–72 hours before enabling in production.

Notes
- If project-scoped setting is needed, decide storage (projects table, project settings JSON, or ConfigFileService-backed flag) and add migration accordingly.
