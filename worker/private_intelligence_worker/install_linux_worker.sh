#!/usr/bin/env bash
# ============================================================================
#  install_linux_worker.sh — تنصيب «عقل المنصة» على خادم Linux (VPS) بأمر واحد
#
#  ينصّب: Python 3 + Ollama + النماذج + Tesseract (عربي) + pdftotext،
#  ثم يسجّل worker.py كخدمة systemd تعمل دائماً وتعيد التشغيل تلقائياً.
#
#  الاستخدام (على VPS بنظام Ubuntu/Debian، كمستخدم root أو sudo):
#    bash install_linux_worker.sh
#
#  بعده: عبّئ /etc/ksgrowth-worker.env ببيانات الاعتماد من:
#    php artisan private-worker:provision "Site VPS" \
#      --capability=deterministic_echo --capability=ocr \
#      --capability=document_extract --capability=embeddings \
#      --capability=local_llm --json
#  ثم: systemctl restart ksgrowth-worker && journalctl -u ksgrowth-worker -f
# ============================================================================
set -euo pipefail

# ── نماذج Ollama (عدّلها حسب عتاد الخادم — انظر LINUX_VPS_SETUP.md) ──
GATEWAY_MODEL="${GATEWAY_MODEL:-qwen3:1.7b}"       # المهام الخاطفة
REASONING_MODEL="${REASONING_MODEL:-qwen3:8b}"      # التوليد والاستدلال
EMBEDDING_MODEL="${EMBEDDING_MODEL:-nomic-embed-text}"  # التضمينات

WORKER_DIR="/opt/ksgrowth-worker"
ENV_FILE="/etc/ksgrowth-worker.env"
SERVICE_FILE="/etc/systemd/system/ksgrowth-worker.service"

echo "==> [1/6] حزم النظام (Python + Tesseract عربي + pdftotext)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y python3 python3-venv curl tesseract-ocr tesseract-ocr-ara poppler-utils

echo "==> [2/6] تنصيب Ollama"
if ! command -v ollama >/dev/null 2>&1; then
  curl -fsSL https://ollama.com/install.sh | sh
fi
systemctl enable --now ollama

echo "==> [3/6] سحب النماذج (قد يستغرق دقائق حسب سرعة الشبكة)"
ollama pull "$GATEWAY_MODEL"
ollama pull "$REASONING_MODEL"
ollama pull "$EMBEDDING_MODEL"

echo "==> [4/6] نسخ العامل إلى $WORKER_DIR"
mkdir -p "$WORKER_DIR"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cp "$SCRIPT_DIR/worker.py" "$SCRIPT_DIR/document_extractors.py" "$WORKER_DIR/"

echo "==> [5/6] ملف البيئة $ENV_FILE (عبّئ المعرّف والسر بعد provision)"
if [ ! -f "$ENV_FILE" ]; then
  cat > "$ENV_FILE" <<ENV
AI_WORKER_SERVER_URL=https://khaledsaad.net
AI_WORKER_ID=wrk_replace_me
AI_WORKER_SECRET=replace_with_one_time_secret
AI_WORKER_CAPABILITIES=deterministic_echo,ocr,document_extract,embeddings,local_llm
AI_WORKER_VERSION=site-vps-1
AI_WORKER_POLL_SECONDS=5
AI_WORKER_HTTP_TIMEOUT=120
AI_WORKER_COMMAND_TIMEOUT=300
AI_WORKER_OCR_LANGUAGE=ara+eng
AI_WORKER_OLLAMA_URL=http://127.0.0.1:11434
AI_WORKER_OLLAMA_MODEL=$REASONING_MODEL
ENV
  chmod 600 "$ENV_FILE"
fi

echo "==> [6/6] خدمة systemd دائمة"
cat > "$SERVICE_FILE" <<SERVICE
[Unit]
Description=KS Growth Private Intelligence Worker
After=network-online.target ollama.service
Wants=network-online.target

[Service]
Type=simple
EnvironmentFile=$ENV_FILE
WorkingDirectory=$WORKER_DIR
ExecStart=/usr/bin/python3 $WORKER_DIR/worker.py
Restart=always
RestartSec=5
# عزل أساسي: لا صلاحيات إضافية ولا كتابة خارج مجلد العمل.
NoNewPrivileges=true
ProtectSystem=full
ProtectHome=true

[Install]
WantedBy=multi-user.target
SERVICE

systemctl daemon-reload
systemctl enable ksgrowth-worker

echo ""
echo "تم. الخطوات المتبقية:"
echo "  1) على خادم Laravel: php artisan private-worker:provision \"Site VPS\" --capability=deterministic_echo --capability=ocr --capability=document_extract --capability=embeddings --capability=local_llm --json"
echo "  2) ضع AI_WORKER_ID و AI_WORKER_SECRET الناتجين في $ENV_FILE"
echo "  3) systemctl restart ksgrowth-worker && journalctl -u ksgrowth-worker -f"
echo "  4) في .env الإنتاج: AI_PRIVATE_WORKER_ENABLED=true"
