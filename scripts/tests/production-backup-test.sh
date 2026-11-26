#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
test_dir="$(mktemp -d)"
trap 'rm -rf -- "$test_dir"' EXIT
mkdir "$test_dir/bin" "$test_dir/backups" "$test_dir/arrivals"
touch "$test_dir/compose.env" "$test_dir/compose.yaml"

cat > "$test_dir/bin/date" <<'STUB'
#!/usr/bin/env bash
printf '20261126T000000Z\n'
STUB

cat > "$test_dir/bin/docker" <<'STUB'
#!/usr/bin/env bash
case "${*: -1}" in
  *mysqldump*)
    touch "$TEST_ARRIVALS/$TEST_BACKUP_ID"
    for ((attempt = 0; attempt < 100; attempt++)); do
      if [[ -f "$TEST_ARRIVALS/first" && -f "$TEST_ARRIVALS/second" ]]; then
        printf '%s\n' "$TEST_BACKUP_ID"
        exit 0
      fi
      sleep 0.05
    done
    exit 1 ;;
  *'tar --numeric-owner'*) printf '%s\n' 'media stream fixture' ;;
  *) printf '%s\n' 'backup_test' ;;
esac
STUB

cat > "$test_dir/bin/age" <<'STUB'
#!/usr/bin/env bash
cat > "${*: -1}"
STUB

chmod +x "$test_dir/bin/"*
export PATH="$test_dir/bin:$PATH"
export COMPOSE_ENV_FILE="$test_dir/compose.env"
export PRODUCTION_COMPOSE_FILE="$test_dir/compose.yaml"
export BACKUP_DIR="$test_dir/backups"
export BACKUP_AGE_RECIPIENT=age1testfixture
export TEST_ARRIVALS="$test_dir/arrivals"

TEST_BACKUP_ID=first bash "$script_dir/../production-backup.sh" > "$test_dir/first.log" 2>&1 &
first_pid=$!
TEST_BACKUP_ID=second bash "$script_dir/../production-backup.sh" > "$test_dir/second.log" 2>&1 &
second_pid=$!
first_status=0
second_status=0
wait "$first_pid" || first_status=$?
wait "$second_pid" || second_status=$?

if [[ "$first_status" == 0 && "$second_status" == 1 ]]; then
  winner=first
  loser=second
elif [[ "$first_status" == 1 && "$second_status" == 0 ]]; then
  winner=second
  loser=first
else
  cat "$test_dir/first.log" "$test_dir/second.log" >&2
  exit 1
fi

archive="$BACKUP_DIR/ott-backup-20261126T000000Z.tar"
[[ "$(stat -c '%a' "$archive")" == 600 ]]
[[ "$(tar -xOf "$archive" database.sql.gz.age | gzip -dc)" == "$winner" ]]
[[ "$(find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -printf '%f\n')" == "$(basename -- "$archive")" ]]
grep -Fq 'could not publish backup' "$test_dir/$loser.log"
printf '%s\n' 'Concurrent backup publication passed.'
