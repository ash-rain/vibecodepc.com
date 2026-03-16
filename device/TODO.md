## Todo

- [x] make a plan on how to configuire opencode, we want to edit the files [text](.~/.config/opencode/opencode.json) and [text](~/.local/share/opencode/auth.json) fill the todo.md with the plan

## Done

- [x] 2026-03-16 feat: create comprehensive opencode configuration plan

---

# OpenCode Configuration Plan

## Overview
This document outlines the plan for configuring OpenCode by editing two key files:
1. `~/.config/opencode/opencode.json` - Main configuration file
2. `~/.local/share/opencode/auth.json` - Authentication credentials file

## File Locations

| File | Path | Purpose |
|------|------|---------|
| Config | `~/.config/opencode/opencode.json` | Provider settings, models, timeouts |
| Auth | `~/.local/share/opencode/auth.json` | API keys for each provider |

## Configuration Structure

### 1. opencode.json Schema

```json
{
  "$schema": "https://opencode.ai/config.json",
  "permission": {
    "*": "allow"  // Global permissions
  },
  "provider": {
    "<provider-name>": {
      "npm": "@ai-sdk/<package-name>",
      "name": "Display Name",
      "options": {
        "baseURL": "https://api.provider.com/v1",
        "timeout": false,
        "chunkTimeout": 30000000
      },
      "models": {
        "<model-id>": {
          "name": "Model Name",
          "_launch": true  // Optional: auto-launch
        }
      }
    }
  }
}
```

### 2. auth.json Schema

```json
{
  "<provider-name>": {
    "type": "api",
    "key": "your-api-key-here"
  }
}
```

## Configuration Tasks

### Phase 1: Backup Current Configuration
- [x] 2026-03-16 Create backup of `~/.config/opencode/opencode.json`
- [x] 2026-03-16 Create backup of `~/.local/share/opencode/auth.json`

### Phase 2: Review Current Providers
- [x] 2026-03-16 Document existing providers in config
- [x] 2026-03-16 Verify auth keys are valid and current
- [x] 2026-03-16 Check which providers are actively used

### Phase 3: Configuration Updates Needed

#### For opencode.json:
- [ ] Update/add provider configurations
  - [ ] Moonshot provider (Kimi K2.5 model)
  - [ ] Ollama provider (local models)
  - [ ] Ollama Cloud provider
- [ ] Review timeout settings (currently 30,000,000ms)
- [ ] Verify all model configurations
- [ ] Set permissions appropriately

#### For auth.json:
- [ ] Validate all API keys
- [ ] Update expired keys
- [ ] Remove unused provider keys
- [ ] Ensure proper key format for each provider

### Phase 4: Current Provider Status (Documented)

| Provider | Status | NPM Package | Base URL | Models | Timeout | Auth Key Present |
|----------|--------|-------------|----------|--------|---------|------------------|
| moonshot | Configured | `@ai-sdk/openai-compatible` | `https://integrate.api.nvidia.com/v1` | moonshotai/kimi-k2.5 | `false` (disabled), chunkTimeout: 30000000ms | Yes |
| ollama | Configured | `@ai-sdk/openai-compatible` | `http://127.0.0.1:11434/v1` | glm-5:cloud | Not set | Yes |
| ollama-cloud | Configured | `@ai-sdk/openai-compatible` | `http://localhost:11434/v1` | glm-4.7:cloud | Not set | Yes |
| opencode | Not configured | N/A | N/A | N/A | N/A | Yes (has key but no provider config) |

#### Detailed Configuration Analysis

**Moonshot Provider:**
- Uses NVIDIA API integration endpoint (not moonshot native API)
- High chunkTimeout: 30,000,000ms (30 seconds)
- Timeout disabled globally for this provider
- Model: `moonshotai/kimi-k2.5` - Moonshot AI's K2.5 model

**Ollama (local) Provider:**
- Points to local Ollama instance at `127.0.0.1:11434`
- Model `glm-5:cloud` has `_launch: true` flag (auto-launch enabled)
- No timeout settings configured (uses defaults)

**Ollama Cloud Provider:**
- Points to `localhost:11434` (same as local but named differently)
- Model: `glm-4.7:cloud`
- No timeout settings configured

**Opencode Provider:**
- Has authentication key in auth.json
- No provider configuration in opencode.json
- Model currently being used: `moonshot/moonshotai/kimi-k2.5`

### Phase 5: Validation Steps
- [ ] Run `opencode --version` to verify config loads
- [ ] Test each provider with a simple query
- [ ] Verify timeout behavior works as expected
- [ ] Check permissions are working correctly

### Phase 6: Documentation
- [ ] Document provider-specific settings
- [ ] Add comments explaining custom configurations
- [ ] Create troubleshooting guide for common issues

## Security Considerations

1. **Never commit auth.json** - It contains sensitive API keys
2. **Use environment variables** where possible for API keys
3. **Set appropriate permissions** on config files:
   ```bash
   chmod 600 ~/.local/share/opencode/auth.json
   chmod 644 ~/.config/opencode/opencode.json
   ```

## Notes

- The current configuration has providers for Moonshot, Ollama (local), and Ollama Cloud
- All API keys are currently set and appear valid
- Permission is set to "allow" globally - review if this should be more restrictive
- The opencode provider has an auth key but no provider config in opencode.json

## Next Steps After Configuration

1. Test the configuration with `opencode status` or similar command
2. Run a test prompt to verify providers work
3. Monitor for any configuration errors in logs
4. Adjust timeouts if needed based on actual usage
