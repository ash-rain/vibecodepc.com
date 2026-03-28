# OpenCode Configuration Documentation

This document describes the OpenCode AI tool configuration used by VibeCodePC, including provider-specific settings, model configurations, and troubleshooting guidance.

## Table of Contents

- [Overview](#overview)
- [Configuration Files](#configuration-files)
- [Provider Settings](#provider-settings)
  - [Moonshot AI](#moonshot-ai)
  - [Opencode](#opencode)
  - [Ollama (Local)](#ollama-local)
  - [Ollama Cloud](#ollama-cloud)
- [Authentication](#authentication)
- [Timeout Configuration](#timeout-configuration)
- [Model Configuration](#model-configuration)
- [Troubleshooting](#troubleshooting)

---

## Overview

OpenCode is configured with multiple AI providers to support different use cases:
- **Moonshot AI**: Primary cloud provider with high-performance models
- **Opencode**: Secondary cloud provider with fallback capabilities
- **Ollama (Local)**: Local AI inference for offline development
- **Ollama Cloud**: Remote Ollama instance for distributed development

---

## Configuration Files

### Main Configuration

**Path:** `~/.config/opencode/opencode.json`

Contains provider definitions, model configurations, timeout settings, and permissions.

```json
{
  "$schema": "https://opencode.ai/config.json",
  "permission": {
    "*": "allow"
  },
  "provider": {
    // Provider configurations...
  }
}
```

### Authentication

**Path:** `~/.local/share/opencode/auth.json`

Contains API keys for each provider.

```json
{
  "provider-name": {
    "type": "api",
    "key": "your-api-key-here"
  }
}
```

**Security Note:** This file contains sensitive credentials. Set appropriate permissions:

```bash
chmod 600 ~/.local/share/opencode/auth.json
chmod 644 ~/.config/opencode/opencode.json
```

---

## Provider Settings

### Moonshot AI

**Provider Key:** `moonshot`

Primary cloud AI provider using Moonshot AI's Kimi K2.5 model.

| Setting | Value | Description |
|---------|-------|-------------|
| `name` | "Moonshot AI" | Display name |
| `npm` | `@ai-sdk/openai-compatible` | SDK package |
| `baseURL` | `https://integrate.api.nvidia.com/v1` | NVIDIA API integration endpoint |
| `timeout` | `false` | No global timeout (unlimited) |
| `chunkTimeout` | `300000` | 5 minutes per chunk |
| `model` | `moonshotai/kimi-k2.5` | Moonshot Kimi K2.5 |

**Configuration:**

```json
{
  "moonshot": {
    "name": "Moonshot AI",
    "npm": "@ai-sdk/openai-compatible",
    "options": {
      "baseURL": "https://integrate.api.nvidia.com/v1",
      "timeout": false,
      "chunkTimeout": 300000
    },
    "models": {
      "moonshotai/kimi-k2.5": {
        "name": "Moonshot Kimi K2.5"
      }
    }
  }
}
```

**Notes:**
- Uses NVIDIA's integration API rather than Moonshot's native API
- High chunk timeout accommodates long-form content generation
- Global timeout disabled to prevent premature interruptions

---

### Opencode

**Provider Key:** `opencode`

Secondary cloud provider offering access to various models through the Opencode platform.

| Setting | Value | Description |
|---------|-------|-------------|
| `name` | "Opencode" | Display name |
| `npm` | `@ai-sdk/opencode` | SDK package |
| `timeout` | `300000` | 5 minutes |
| `chunkTimeout` | `600000` | 10 minutes per chunk |
| `model` | `moonshot/moonshotai/kimi-k2.5` | Moonshot via Opencode |

**Configuration:**

```json
{
  "opencode": {
    "name": "Opencode",
    "npm": "@ai-sdk/opencode",
    "options": {
      "timeout": 300000,
      "chunkTimeout": 600000
    },
    "models": {
      "moonshot/moonshotai/kimi-k2.5": {
        "name": "Moonshot Kimi K2.5 via Opencode"
      }
    }
  }
}
```

**Notes:**
- Higher chunk timeout for complex reasoning tasks
- Acts as fallback when Moonshot is unavailable
- No baseURL required (uses Opencode's default endpoint)

---

### Ollama (Local)

**Provider Key:** `ollama`

Local AI inference server running on the device.

| Setting | Value | Description |
|---------|-------|-------------|
| `name` | "Ollama (Local)" | Display name |
| `npm` | `@ai-sdk/openai-compatible` | SDK package |
| `baseURL` | `http://127.0.0.1:11434/v1` | Local Ollama API |
| `timeout` | `300000` | 5 minutes |
| `chunkTimeout` | `600000` | 10 minutes per chunk |
| `model` | `glm-5` | GLM-5 model |

**Configuration:**

```json
{
  "ollama": {
    "name": "Ollama (Local)",
    "npm": "@ai-sdk/openai-compatible",
    "options": {
      "baseURL": "http://127.0.0.1:11434/v1",
      "timeout": 300000,
      "chunkTimeout": 600000
    },
    "models": {
      "glm-5": {
        "name": "GLM-5",
        "_launch": true
      }
    }
  }
}
```

**Notes:**
- Points to local Ollama instance at `127.0.0.1:11434`
- `_launch: true` flag enables auto-launch capability
- Useful for offline development and privacy-sensitive work
- Requires Ollama to be installed and running locally

---

### Ollama Cloud

**Provider Key:** `ollama-cloud`

Remote Ollama instance for team collaboration.

| Setting | Value | Description |
|---------|-------|-------------|
| `name` | "Ollama Cloud" | Display name |
| `npm` | `@ai-sdk/openai-compatible` | SDK package |
| `baseURL` | `http://localhost:11434/v1` | Cloud Ollama endpoint |
| `timeout` | `300000` | 5 minutes |
| `chunkTimeout` | `600000` | 10 minutes per chunk |
| `models` | `glm-4.7`, `glm-4.7:cloud` | Available models |

**Configuration:**

```json
{
  "ollama-cloud": {
    "name": "Ollama Cloud",
    "npm": "@ai-sdk/openai-compatible",
    "options": {
      "baseURL": "http://localhost:11434/v1",
      "timeout": 300000,
      "chunkTimeout": 600000
    },
    "models": {
      "glm-4.7": {
        "name": "GLM-4.7"
      },
      "glm-4.7:cloud": {
        "name": "GLM-4.7 Cloud"
      }
    }
  }
}
```

**Notes:**
- Configured for cloud-based Ollama deployment
- Multiple model variants available
- Suitable for team environments with shared infrastructure

---

## Authentication

Each provider requires authentication via the `auth.json` file:

### API Key Format

```json
{
  "provider-name": {
    "type": "api",
    "key": "your-api-key"
  }
}
```

### Provider Authentication Details

| Provider | Key Location | Format |
|----------|--------------|--------|
| `moonshot` | auth.json | NVIDIA API key |
| `opencode` | auth.json | Opencode API key (`sk-...`) |
| `ollama-cloud` | auth.json | Cloud authentication token |

**Note:** The local Ollama provider (`ollama`) typically does not require authentication when running locally.

---

## Timeout Configuration

### Understanding Timeouts

OpenCode uses two timeout settings:

1. **`timeout`**: Global request timeout (milliseconds)
   - `false`: No timeout (unlimited)
   - `300000`: 5 minutes

2. **`chunkTimeout`**: Maximum time to wait for each response chunk (milliseconds)
   - `300000`: 5 minutes
   - `600000`: 10 minutes

### Timeout Values by Provider

| Provider | Global Timeout | Chunk Timeout | Use Case |
|----------|---------------|---------------|----------|
| Moonshot | `false` (unlimited) | 300000 (5min) | Long-running tasks |
| Opencode | 300000 (5min) | 600000 (10min) | Complex reasoning |
| Ollama (Local) | 300000 (5min) | 600000 (10min) | Local inference |
| Ollama Cloud | 300000 (5min) | 600000 (10min) | Cloud inference |

### Adjusting Timeouts

To modify timeout settings:

1. Edit `~/.config/opencode/opencode.json`
2. Update the `timeout` and/or `chunkTimeout` values in the provider's `options`
3. Save the file (no restart required)

**Example - Increasing Moonshot chunk timeout:**

```json
{
  "moonshot": {
    "options": {
      "timeout": false,
      "chunkTimeout": 600000
    }
  }
}
```

---

## Model Configuration

### Model Definition Schema

```json
{
  "model-id": {
    "name": "Human-readable name",
    "_launch": true  // Optional: auto-launch on first use
  }
}
```

### Available Models

| Provider | Model ID | Display Name | Auto-launch |
|----------|----------|--------------|-------------|
| Moonshot | `moonshotai/kimi-k2.5` | Moonshot Kimi K2.5 | No |
| Opencode | `moonshot/moonshotai/kimi-k2.5` | Moonshot Kimi K2.5 via Opencode | No |
| Ollama | `glm-5` | GLM-5 | Yes |
| Ollama Cloud | `glm-4.7` | GLM-4.7 | No |
| Ollama Cloud | `glm-4.7:cloud` | GLM-4.7 Cloud | No |

### Adding New Models

To add a new model to an existing provider:

1. Open `~/.config/opencode/opencode.json`
2. Locate the provider's `models` section
3. Add the new model configuration:

```json
{
  "provider-name": {
    "models": {
      "new-model-id": {
        "name": "New Model Display Name"
      }
    }
  }
}
```

---

## Troubleshooting

### Configuration Not Loading

**Symptoms:** OpenCode fails to start or shows default configuration.

**Solutions:**

1. **Verify JSON syntax:**
   ```bash
   python3 -m json.tool ~/.config/opencode/opencode.json > /dev/null
   python3 -m json.tool ~/.local/share/opencode/auth.json > /dev/null
   ```

2. **Check file permissions:**
   ```bash
   ls -la ~/.config/opencode/opencode.json
   ls -la ~/.local/share/opencode/auth.json
   ```

3. **Validate file paths:**
   ```bash
   opencode --version
   # Should show version without config errors
   ```

### Authentication Errors

**Symptoms:** "Authentication failed" or "Invalid API key" errors.

**Solutions:**

1. **Verify API key format:**
   - Moonshot: Should start with `nvapi-`
   - Opencode: Should start with `sk-`

2. **Check auth.json structure:**
   ```json
   {
     "provider": {
       "type": "api",
       "key": "valid-key-here"
     }
   }
   ```

3. **Test with curl:**
   ```bash
   # Test Moonshot/NVIDIA endpoint
   curl -H "Authorization: Bearer YOUR_API_KEY" \
        https://integrate.api.nvidia.com/v1/models
   ```

### Timeout Errors

**Symptoms:** Requests timeout before completion.

**Solutions:**

1. **Increase chunkTimeout:**
   ```json
   {
     "provider": {
       "options": {
         "chunkTimeout": 600000
       }
     }
   }
   ```

2. **Disable global timeout for long tasks:**
   ```json
   {
     "provider": {
       "options": {
         "timeout": false
       }
     }
   }
   ```

3. **Check network connectivity:**
   ```bash
   ping integrate.api.nvidia.com
   ```

### Ollama Connection Failed

**Symptoms:** Cannot connect to local Ollama instance.

**Solutions:**

1. **Verify Ollama is running:**
   ```bash
   curl http://127.0.0.1:11434/api/tags
   ```

2. **Check Ollama service status:**
   ```bash
   systemctl status ollama
   # or
   pgrep -la ollama
   ```

3. **Restart Ollama:**
   ```bash
   systemctl restart ollama
   # or
   ollama serve
   ```

4. **Verify baseURL configuration:**
   - Local: `http://127.0.0.1:11434/v1`
   - Docker: `http://host.docker.internal:11434/v1`

### Model Not Found

**Symptoms:** "Model not found" or "Invalid model ID" errors.

**Solutions:**

1. **Verify model ID in configuration:**
   ```bash
   cat ~/.config/opencode/opencode.json | grep -A5 "models"
   ```

2. **Check model availability:**
   ```bash
   # For Ollama
   curl http://127.0.0.1:11434/api/tags | jq '.models[].name'
   ```

3. **Pull missing models:**
   ```bash
   ollama pull glm-5
   ```

### Provider Not Responding

**Symptoms:** Requests hang indefinitely.

**Solutions:**

1. **Switch to alternative provider temporarily:**
   ```bash
   # Use Opencode instead of Moonshot
   opencode --provider opencode
   ```

2. **Check provider status:**
   ```bash
   # Test Moonshot/NVIDIA
   curl -I https://integrate.api.nvidia.com/v1
   
   # Test Opencode
   curl -I https://api.opencode.ai/health
   ```

3. **Enable fallback provider:**
   Configure multiple providers in `opencode.json` so OpenCode can automatically fall back if one fails.

### Permission Denied

**Symptoms:** "Permission denied" when reading configuration files.

**Solutions:**

```bash
# Fix permissions
chmod 600 ~/.local/share/opencode/auth.json
chmod 644 ~/.config/opencode/opencode.json

# Ensure directories exist
mkdir -p ~/.config/opencode
mkdir -p ~/.local/share/opencode
```

### Configuration Backup and Restore

**Create backup:**
```bash
cp ~/.config/opencode/opencode.json ~/.config/opencode/opencode.json.backup.$(date +%Y%m%d_%H%M%S)
cp ~/.local/share/opencode/auth.json ~/.local/share/opencode/auth.json.backup.$(date +%Y%m%d_%H%M%S)
```

**Restore from backup:**
```bash
cp ~/.config/opencode/opencode.json.backup.20260316_175156 ~/.config/opencode/opencode.json
cp ~/.local/share/opencode/auth.json.backup.20260316_175156 ~/.local/share/opencode/auth.json
```

---

## Security Best Practices

1. **Never commit auth.json** to version control
2. **Rotate API keys** regularly
3. **Use environment variables** for API keys when possible
4. **Set restrictive permissions** on auth files (`chmod 600`)
5. **Backup configurations** before making changes

---

## Configuration Validation

After making changes, verify the configuration:

```bash
# Check OpenCode can load the config
opencode --version

# Test a simple query
echo "Hello" | opencode --provider moonshot --model moonshotai/kimi-k2.5
```

---

## Related Documentation

- [OpenCode Documentation](https://opencode.ai/docs)
- [Moonshot AI Documentation](https://platform.moonshot.cn/docs)
- [NVIDIA API Documentation](https://developer.nvidia.com/docs)
- [Ollama Documentation](https://ollama.ai/docs)
