#!/usr/bin/env bash
# update-secret.sh NAME -- set or replace one NAME=value line inside secrets.env.enc (value read from stdin, never echoed).
#   printf '%s' "$NEW_KEY" | bash tools/secrets/update-secret.sh ELEVENLABS_API_KEY
#   printf '56000' | bash tools/secrets/update-secret.sh ELEVENLABS_MONTHLY_CREDIT_CAP
# Same scheme as load-secrets.sh (AES-256-CBC, PBKDF2-SHA256 600k, passphrase from two Environment secrets).
# Use it when the owner sends a new ElevenLabs key (decision 15): replace the key and raise the cap by the new
# account's credits, then commit secrets.env.enc. Prints only the variable names it kept.
set -euo pipefail
name="${1:?usage: update-secret.sh NAME  (value on stdin)}"
[[ "$name" =~ ^[A-Z][A-Z0-9_]*$ ]] || { echo "bad name" >&2; exit 2; }
dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; f="$dir/secrets.env.enc"
[ -n "${TELEGRAM_TEST_BOT_TOKEN:-}" ] && [ -n "${PAYPAL_SANDBOX_SECRET:-}" ] || { echo "passphrase sources missing" >&2; exit 3; }
value="$(cat)"; value="${value%$'\n'}"
[ -n "$value" ] && [[ "$value" != *$'\n'* ]] || { echo "empty or multi-line value" >&2; exit 2; }
export EFB_SEC_PASS="$(printf '%s|%s' "$TELEGRAM_TEST_BOT_TOKEN" "$PAYPAL_SANDBOX_SECRET" | sha256sum | cut -d' ' -f1)"
plain=""
[ -s "$f" ] && plain="$(openssl enc -d -aes-256-cbc -pbkdf2 -md sha256 -iter 600000 -a -A -pass env:EFB_SEC_PASS -in "$f")"
new="$(printf '%s\n' "$plain" | grep -v -E "^${name}=" | grep -E '^[A-Z][A-Z0-9_]*=' || true)"
new="$(printf '%s\n%s=%s\n' "$new" "$name" "$value" | sed '/^$/d')"
tmp="$(mktemp)"; printf '%s\n' "$new" | openssl enc -aes-256-cbc -salt -pbkdf2 -md sha256 -iter 600000 -a -A -pass env:EFB_SEC_PASS -out "$tmp"
mv "$tmp" "$f"; unset plain new value
echo "secrets.env.enc now holds: $(openssl enc -d -aes-256-cbc -pbkdf2 -md sha256 -iter 600000 -a -A -pass env:EFB_SEC_PASS -in "$f" | cut -d= -f1 | tr '\n' ' ')"
unset EFB_SEC_PASS
