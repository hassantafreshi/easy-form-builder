#!/usr/bin/env bash
# load-secrets.sh -- TEMPORARY fallback while the cloud Environment settings page refuses to save new rows.
#   source tools/secrets/load-secrets.sh
# Decrypts secrets.env.enc (AES-256-CBC, PBKDF2-SHA256 600k iterations) in memory and exports every NAME=value line
# that is NOT already set in the environment (a real environment variable always wins).
# Passphrase = sha256(TELEGRAM_TEST_BOT_TOKEN + "|" + PAYPAL_SANDBOX_SECRET): two secrets that only exist in the
# project Environment. Without both, nothing is decrypted. Never prints a value.
# Once the Environment page works: add the variables there, rotate the keys, delete secrets.env.enc.
_efb_sec_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
_efb_sec_file="$_efb_sec_dir/secrets.env.enc"
if [ -z "${TELEGRAM_TEST_BOT_TOKEN:-}" ] || [ -z "${PAYPAL_SANDBOX_SECRET:-}" ]; then
  echo "[secrets] passphrase sources missing in the environment; skipped" >&2
elif [ ! -s "$_efb_sec_file" ]; then
  echo "[secrets] no $_efb_sec_file; skipped" >&2
else
  _efb_plain="$(EFB_SEC_PASS="$(printf '%s|%s' "$TELEGRAM_TEST_BOT_TOKEN" "$PAYPAL_SANDBOX_SECRET" | sha256sum | cut -d' ' -f1)" \
      openssl enc -d -aes-256-cbc -pbkdf2 -md sha256 -iter 600000 -a -A -pass env:EFB_SEC_PASS -in "$_efb_sec_file" 2>/dev/null)" \
    || { echo "[secrets] decryption failed (passphrase sources changed?)" >&2; _efb_plain=""; }
  _efb_n=0
  while IFS= read -r _l; do
    [[ "$_l" =~ ^([A-Z][A-Z0-9_]*)=(.+)$ ]] || continue
    if [ -z "${!BASH_REMATCH[1]:-}" ]; then export "${BASH_REMATCH[1]}=${BASH_REMATCH[2]}"; _efb_n=$((_efb_n+1)); fi
  done <<< "$_efb_plain"
  echo "[secrets] loaded $_efb_n variable(s) from secrets.env.enc" >&2
  unset _efb_plain _l _efb_n
fi
unset _efb_sec_dir _efb_sec_file
