FROM cloudflare/cloudflared:latest AS bin
FROM alpine:3.21

COPY --from=bin /usr/local/bin/cloudflared /usr/local/bin/cloudflared

ENTRYPOINT ["/entrypoint.sh"]
