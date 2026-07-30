#!/usr/bin/env bash
# ============================================================================
#  prod-consultation-status.sh — حالة جلسة استشارة على الإنتاج، للقراءة فقط.
#
#  الاستخدام: bash deploy/prod-consultation-status.sh <session-uuid>
#
#  يطبع: حالة الجلسة · هل بُني تقرير (uuid) · توزيع تشغيلات الأدوات بالحالة.
#  استعلام قراءة فقط عبر tinker — لا يكتب شيئًا.
# ============================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

UUID=${1:?"مرّر uuid الجلسة"}
if ! [[ "$UUID" =~ ^[0-9a-fA-F-]{36}$ ]]; then echo "uuid غير صالح"; exit 1; fi

ENV_FILE=deploy/cpanel.env
KEY=deploy/cpanel_deploy_ed25519
[ -f "$KEY" ] || KEY=deploy/cpanel_deploy.key
get(){ grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2-; }
HOST_ADDR=$(get CPANEL_HOST); PORT=$(get CPANEL_PORT); USER_NAME=$(get CPANEL_USER)
RP=$(get CPANEL_REMOTE_PATH); PORT=${PORT:-22}
HOST="${USER_NAME}@${HOST_ADDR}"
SSHO="-i $KEY -p $PORT -o BatchMode=yes -o ConnectTimeout=25 -o StrictHostKeyChecking=accept-new"

PHP='$s=DB::table("consultation_sessions")->where("uuid",$U)->first();
if(!$s){echo "الجلسة غير موجودة".PHP_EOL;exit;}
echo "session_status=".$s->status.PHP_EOL;
$r=DB::table("agency_reports")->where("consultation_session_id",$s->id)->orderByDesc("id")->first();
echo "report=".($r? $r->uuid." (score=".($r->score??"-").")":"لا يوجد").PHP_EOL;
foreach(DB::table("tool_runs")->select("status",DB::raw("count(*) c"))->where("consultation_session_id",$s->id)->groupBy("status")->get() as $row){echo "  tool_runs.".$row->status."=".$row->c.PHP_EOL;}'

# shellcheck disable=SC2029
ssh $SSHO "$HOST" "cd '$RP' && U='$UUID' php artisan tinker --execute='\$U=getenv(\"U\"); $PHP'" 2>/dev/null
