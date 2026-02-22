#!/bin/sh
set -e

TOKEN_FILE="/tunnel/token"
POLL_WAIT=5
POLL_RUNNING=10

log() {
    echo "[cloudflared-entrypoint] $(date '+%Y-%m-%d %H:%M:%S') $1"
}

# Wait for token file to appear with content
wait_for_token() {
    while true; do
        if [ -s "$TOKEN_FILE" ]; then
            return 0
        fi
        log "Waiting for tunnel token at $TOKEN_FILE..."
        sleep "$POLL_WAIT"
    done
}

# Main loop: start cloudflared when token is available, restart on changes or crashes
while true; do
    wait_for_token

    TOKEN=$(cat "$TOKEN_FILE")
    log "Token found, starting cloudflared..."

    cloudflared tunnel --no-autoupdate run --token "$TOKEN" &
    PID=$!
    log "cloudflared started (PID $PID)"

    # Monitor for token changes or process exit
    while true; do
        # Check if cloudflared is still running
        if ! kill -0 "$PID" 2>/dev/null; then
            log "cloudflared exited, restarting..."
            break
        fi

        # Check if token file was emptied (stop signal) or changed (reprovisioning)
        if [ ! -s "$TOKEN_FILE" ]; then
            log "Token file emptied, stopping cloudflared..."
            kill "$PID" 2>/dev/null || true
            wait "$PID" 2>/dev/null || true
            log "cloudflared stopped, waiting for new token..."
            break
        fi

        CURRENT_TOKEN=$(cat "$TOKEN_FILE")
        if [ "$CURRENT_TOKEN" != "$TOKEN" ]; then
            log "Token changed, restarting cloudflared..."
            kill "$PID" 2>/dev/null || true
            wait "$PID" 2>/dev/null || true
            break
        fi

        sleep "$POLL_RUNNING"
    done
done
