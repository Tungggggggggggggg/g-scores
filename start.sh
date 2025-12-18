#!/usr/bin/env bash
set -euo pipefail

if [[ -z "${APP_KEY:-}" ]]; then
  echo "APP_KEY is missing. Set APP_KEY in Render environment variables."
  exit 1
fi

mkdir -p \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/app/dataset \
  storage/logs \
  bootstrap/cache

chmod -R ug+rwX storage bootstrap/cache

php artisan config:clear
php artisan view:clear

php artisan migrate --force

AUTO_IMPORT_FLAG="${SCORES_AUTO_IMPORT:-}"
if [[ "${AUTO_IMPORT_FLAG}" == "1" || "${AUTO_IMPORT_FLAG,,}" == "true" ]]; then
  if [[ -z "${SCORES_CSV_URL:-}" ]]; then
    echo "SCORES_AUTO_IMPORT is enabled but SCORES_CSV_URL is missing. Skipping CSV import."
  else
    (
      set -euo pipefail

      if ! command -v curl >/dev/null 2>&1; then
        echo "curl is not installed. Skipping CSV import."
        exit 0
      fi

      CSV_PATH="storage/app/dataset/diem_thi_thpt_2024.csv"
      TMP_PATH="${CSV_PATH}.tmp"
      CHUNK_SIZE="${SCORES_IMPORT_CHUNK:-2000}"

      echo "Auto-import enabled. Downloading CSV from SCORES_CSV_URL..."
      curl -L --fail --retry 5 --retry-delay 2 -o "${TMP_PATH}" "${SCORES_CSV_URL}"
      mv "${TMP_PATH}" "${CSV_PATH}"

      echo "Starting CSV import (chunk=${CHUNK_SIZE})..."
      FORCE_REIMPORT_FLAG="${SCORES_FORCE_REIMPORT:-}"
      if [[ "${FORCE_REIMPORT_FLAG}" == "1" || "${FORCE_REIMPORT_FLAG,,}" == "true" ]]; then
        php artisan scores:import --path="${CSV_PATH}" --chunk="${CHUNK_SIZE}"
      else
        php artisan scores:import --path="${CSV_PATH}" --chunk="${CHUNK_SIZE}" --skip-if-complete
      fi
      echo "CSV import finished."
    ) &
  fi
fi

PORT_TO_BIND="${PORT:-8080}"
exec php -S "0.0.0.0:${PORT_TO_BIND}" -t public public/index.php
