#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
COMPOSE_ENV_FILE="${COMPOSE_ENV_FILE:-$ROOT_DIR/.env.production}"
BACKUP_DIR="${BACKUP_DIR:-}"
BACKUP_AGE_RECIPIENT="${BACKUP_AGE_RECIPIENT:-}"

fail() {
  printf 'production-backup: %s\n' "$1" >&2
  exit 1
}

[[ -f "$COMPOSE_ENV_FILE" ]] || fail "COMPOSE_ENV_FILE does not exist: $COMPOSE_ENV_FILE"
[[ -n "$BACKUP_DIR" && "$BACKUP_DIR" = /* ]] || fail 'set BACKUP_DIR to an absolute off-host backup destination'
[[ "$BACKUP_AGE_RECIPIENT" =~ ^age1[[:alnum:]]+$ ]] || fail 'set BACKUP_AGE_RECIPIENT to an age public recipient'
command -v docker >/dev/null || fail 'docker is required'
command -v age >/dev/null || fail 'age is required to encrypt backup streams'
command -v gzip >/dev/null || fail 'gzip is required'
command -v sha256sum >/dev/null || fail 'sha256sum is required'

mkdir -p -- "$BACKUP_DIR"
BACKUP_DIR="$(cd -- "$BACKUP_DIR" && pwd -P)"
case "$BACKUP_DIR/" in
  "$ROOT_DIR/"|"$ROOT_DIR"/*) fail 'BACKUP_DIR must resolve outside the repository' ;;
esac

COMPOSE=(docker compose --env-file "$COMPOSE_ENV_FILE" -f "$ROOT_DIR/docker-compose.yml" -f "$ROOT_DIR/docker-compose.production.yml")
TIMESTAMP="$(date -u +'%Y%m%dT%H%M%SZ')"
STAGE_DIR="$(mktemp -d "$BACKUP_DIR/.ott-backup-${TIMESTAMP}.XXXXXX")"
FINAL_ARCHIVE="$BACKUP_DIR/ott-backup-${TIMESTAMP}.tar"

cleanup() {
  if [[ -n "${STAGE_DIR:-}" && -d "$STAGE_DIR" ]]; then
    rm -rf -- "$STAGE_DIR"
  fi
}
trap cleanup EXIT

[[ ! -e "$FINAL_ARCHIVE" ]] || fail "backup already exists: $FINAL_ARCHIVE"

"${COMPOSE[@]}" exec -T mysql sh -ec \
  'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysqldump --user=root --single-transaction --routines --triggers --events --hex-blob "$MYSQL_DATABASE"' \
  | gzip -1 \
  | age --recipient "$BACKUP_AGE_RECIPIENT" --output "$STAGE_DIR/database.sql.gz.age"

"${COMPOSE[@]}" exec -T backend sh -ec \
  'cd /var/www/html/storage/app && tar --numeric-owner -czf - public private' \
  | age --recipient "$BACKUP_AGE_RECIPIENT" --output "$STAGE_DIR/media.tar.gz.age"

DATABASE_NAME="$("${COMPOSE[@]}" exec -T mysql sh -ec 'printf "%s" "$MYSQL_DATABASE"')"
SOURCE_REVISION="$(git -C "$ROOT_DIR" rev-parse HEAD 2>/dev/null || printf 'unknown')"
cat > "$STAGE_DIR/manifest.txt" <<EOF
created_utc=$TIMESTAMP
database=$DATABASE_NAME
source_revision=$SOURCE_REVISION
encryption=age
contents=database.sql.gz.age,media.tar.gz.age
EOF

(cd -- "$STAGE_DIR" && sha256sum database.sql.gz.age media.tar.gz.age > SHA256SUMS)
tar -C "$STAGE_DIR" -cf "$STAGE_DIR/backup.tar" database.sql.gz.age media.tar.gz.age manifest.txt SHA256SUMS
mv -- "$STAGE_DIR/backup.tar" "$FINAL_ARCHIVE"
chmod 0600 "$FINAL_ARCHIVE"
rm -f -- "$STAGE_DIR/database.sql.gz.age" "$STAGE_DIR/media.tar.gz.age" "$STAGE_DIR/manifest.txt" "$STAGE_DIR/SHA256SUMS"
rmdir -- "$STAGE_DIR"
STAGE_DIR=''

printf 'Encrypted backup created: %s\n' "$FINAL_ARCHIVE"
