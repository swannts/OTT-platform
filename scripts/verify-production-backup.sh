#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
BACKUP_ARCHIVE="${1:-}"
BACKUP_AGE_IDENTITY="${BACKUP_AGE_IDENTITY:-}"

fail() {
  printf 'verify-production-backup: %s\n' "$1" >&2
  exit 1
}

[[ -n "$BACKUP_ARCHIVE" && -f "$BACKUP_ARCHIVE" ]] || fail 'usage: verify-production-backup.sh /path/to/ott-backup-<timestamp>.tar'
[[ "$BACKUP_ARCHIVE" = /* ]] || BACKUP_ARCHIVE="$(cd -- "$(dirname -- "$BACKUP_ARCHIVE")" && pwd -P)/$(basename -- "$BACKUP_ARCHIVE")"
[[ -n "$BACKUP_AGE_IDENTITY" && -f "$BACKUP_AGE_IDENTITY" && -r "$BACKUP_AGE_IDENTITY" ]] || fail 'set BACKUP_AGE_IDENTITY to a readable age private identity file'
command -v age >/dev/null || fail 'age is required'
command -v gzip >/dev/null || fail 'gzip is required'
command -v tar >/dev/null || fail 'tar is required'
command -v sha256sum >/dev/null || fail 'sha256sum is required'
command -v stat >/dev/null || fail 'stat is required'

IDENTITY_PATH="$(cd -- "$(dirname -- "$BACKUP_AGE_IDENTITY")" && pwd -P)/$(basename -- "$BACKUP_AGE_IDENTITY")"
case "$IDENTITY_PATH" in
  "$ROOT_DIR"|"$ROOT_DIR"/*) fail 'keep the private age identity outside the repository' ;;
esac
IDENTITY_MODE="$(stat -c '%a' "$IDENTITY_PATH")"
(( (8#$IDENTITY_MODE & 077) == 0 )) || fail 'age identity permissions must exclude group and other access'

EXPECTED_ENTRIES=$'SHA256SUMS\ndatabase.sql.gz.age\nmanifest.txt\nmedia.tar.gz.age'
ACTUAL_ENTRIES="$(tar -tf "$BACKUP_ARCHIVE" | LC_ALL=C sort)"
[[ "$ACTUAL_ENTRIES" == "$EXPECTED_ENTRIES" ]] || fail 'archive contains missing, duplicate, or unexpected paths'

VERIFY_DIR="$(mktemp -d "${TMPDIR:-/tmp}/ott-backup-verify.XXXXXXXX")"
cleanup() {
  rm -rf -- "$VERIFY_DIR"
}
trap cleanup EXIT

tar -xOf "$BACKUP_ARCHIVE" database.sql.gz.age > "$VERIFY_DIR/database.sql.gz.age"
tar -xOf "$BACKUP_ARCHIVE" media.tar.gz.age > "$VERIFY_DIR/media.tar.gz.age"
tar -xOf "$BACKUP_ARCHIVE" manifest.txt > "$VERIFY_DIR/manifest.txt"
tar -xOf "$BACKUP_ARCHIVE" SHA256SUMS > "$VERIFY_DIR/SHA256SUMS"

mapfile -t CHECKSUM_LINES < "$VERIFY_DIR/SHA256SUMS"
[[ "${#CHECKSUM_LINES[@]}" -eq 2 ]] || fail 'checksum manifest must contain exactly two entries'
[[ "${CHECKSUM_LINES[0]}" =~ ^[a-f0-9]{64}\ \ database\.sql\.gz\.age$ ]] || fail 'database checksum entry is invalid'
[[ "${CHECKSUM_LINES[1]}" =~ ^[a-f0-9]{64}\ \ media\.tar\.gz\.age$ ]] || fail 'media checksum entry is invalid'
(cd -- "$VERIFY_DIR" && sha256sum --check --quiet SHA256SUMS) || fail 'backup checksum verification failed'

grep -Fxq 'encryption=age' "$VERIFY_DIR/manifest.txt" || fail 'manifest does not identify age encryption'
grep -Fxq 'contents=database.sql.gz.age,media.tar.gz.age' "$VERIFY_DIR/manifest.txt" || fail 'manifest content list is invalid'

age --decrypt --identity "$IDENTITY_PATH" "$VERIFY_DIR/database.sql.gz.age" | gzip -t
age --decrypt --identity "$IDENTITY_PATH" "$VERIFY_DIR/media.tar.gz.age" | tar -tzf - > "$VERIFY_DIR/media-files.txt"

while IFS= read -r path; do
  [[ -n "$path" ]] || continue
  case "$path" in
    public|public/*|private|private/*) ;;
    *) fail 'decrypted media archive contains a path outside public/ or private/' ;;
  esac
  case "/$path/" in
    *"/../"*|*"/./"*) fail 'decrypted media archive contains an unsafe path' ;;
  esac
done < "$VERIFY_DIR/media-files.txt"

printf 'Backup archive verified. No decrypted database or media contents were written to disk.\n'
cat "$VERIFY_DIR/manifest.txt"
