#!/usr/bin/env bash
# نسخة متسقة من قاعدة الإنتاج قبل ترحيلات المحتوى. لا تطبع بيانات الاتصال.
set -euo pipefail
cd "$(dirname "$0")/.."

ENV_FILE=deploy/cpanel.env
KEY=deploy/cpanel_deploy_ed25519
[ -f "$KEY" ] || KEY=deploy/cpanel_deploy.key
[ -f "$ENV_FILE" ] || { echo "missing $ENV_FILE"; exit 1; }
[ -f "$KEY" ] || { echo "missing deploy key"; exit 1; }

get(){ grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2-; }
HOST_ADDR=$(get CPANEL_HOST); PORT=$(get CPANEL_PORT); USER_NAME=$(get CPANEL_USER)
RP=$(get CPANEL_REMOTE_PATH); PORT=${PORT:-22}
HOST="${USER_NAME}@${HOST_ADDR}"
SSHO="-i $KEY -p $PORT -o BatchMode=yes -o ConnectTimeout=25 -o StrictHostKeyChecking=accept-new"

REMOTE='set -euo pipefail
cd "'"$RP"'"

env_value(){
    local raw
    raw=$(grep -m1 -E "^$1=" .env | cut -d= -f2-)
    raw=${raw%$'"'"'\r'"'"'}
    if [[ "${raw:0:1}" == "\"" ]] && [[ "${raw: -1}" == "\"" ]]; then
        raw=${raw:1:${#raw}-2}
    fi
    printf "%s" "$raw"
}

DB_HOST_VALUE=$(env_value DB_HOST)
DB_PORT_VALUE=$(env_value DB_PORT)
DB_NAME_VALUE=$(env_value DB_DATABASE)
DB_USER_VALUE=$(env_value DB_USERNAME)
DB_PASS_VALUE=$(env_value DB_PASSWORD)
DUMP_BIN=$(command -v mysqldump || command -v mariadb-dump)
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="_deploy_backups/learning-magazine-db-${STAMP}.sql.gz"
mkdir -p _deploy_backups

MYSQL_PWD="$DB_PASS_VALUE" "$DUMP_BIN" \
    --single-transaction --quick --skip-lock-tables --default-character-set=utf8mb4 \
    -h "$DB_HOST_VALUE" -P "${DB_PORT_VALUE:-3306}" -u "$DB_USER_VALUE" "$DB_NAME_VALUE" \
    | gzip -9 > "$BACKUP"

test -s "$BACKUP"
chmod 600 "$BACKUP"
echo "BACKUP_PATH=$BACKUP"
echo "BACKUP_BYTES=$(wc -c < "$BACKUP")"
echo "BACKUP_SHA256=$(sha256sum "$BACKUP" | cut -d" " -f1)"'

# shellcheck disable=SC2029
ssh $SSHO "$HOST" "$REMOTE"
