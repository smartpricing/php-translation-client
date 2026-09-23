#!/usr/bin/env bash
# Read-only smoke check for the translation server this client talks to.
# GET/HEAD only — SAFE to run against stage AND prod. No writes, no auth token.
#
#   scripts/smoke/smoke.sh https://pms-intool.smartness.com/api
#
# Checks:
#   1. base host reachable over HTTPS with a valid certificate
#   2. protected API endpoints require auth (401/403 without a token)
#   3. the connection is TLS and HSTS is advertised
#   4. no obvious debug/listing exposure (.env, .git, phpinfo)
set -u
BASE="${1:?usage: smoke.sh <api-base-url e.g. https://host/api>}"
BASE="${BASE%/}"
HOST_ROOT="$(printf '%s' "$BASE" | sed -E 's#(https?://[^/]+).*#\1#')"
fail=0; warn=0
ok(){ printf '  ok   %s\n' "$1"; }
bad(){ printf '  FAIL %s\n' "$1"; fail=$((fail+1)); }
wrn(){ printf '  warn %s\n' "$1"; warn=$((warn+1)); }
code(){ curl -sS -m 15 -o /dev/null -w '%{http_code}' "$@" 2>/dev/null; }

echo "Smoke: $BASE"

case "$BASE" in
  https://*) ok "base URL uses https" ;;
  *) bad "base URL is not https ($BASE) — API token would travel in cleartext" ;;
esac

# valid TLS cert (curl fails closed on bad cert)
if curl -sS -m 15 -o /dev/null "$HOST_ROOT" 2>/dev/null; then ok "TLS certificate validates"; else bad "TLS handshake/cert failed for $HOST_ROOT"; fi

# HSTS
if curl -sS -m 15 -D - -o /dev/null "$HOST_ROOT" 2>/dev/null | grep -qi '^strict-transport-security:'; then
  ok "HSTS present"; else wrn "no Strict-Transport-Security header"; fi

# protected endpoints must reject anonymous callers
for ep in "/translation-projects/translations" "/translation-projects/config"; do
  c="$(code -H 'Accept: application/json' "$BASE$ep")"
  case "$c" in
    401|403) ok "auth gate on $ep ($c)";;
    000) bad "$ep unreachable (000)";;
    2*) bad "$ep returned $c WITHOUT a token — auth gate missing";;
    *) wrn "$ep returned $c (expected 401/403)";;
  esac
done

# debug/listing exposure at the host root
for path in "/.env" "/.git/config" "/phpinfo.php"; do
  c="$(code "$HOST_ROOT$path")"
  if [ "$c" = "200" ]; then bad "$path is exposed (200)"; else ok "$path not exposed ($c)"; fi
done

echo "---- $fail failed, $warn warnings"
[ "$fail" -eq 0 ]
