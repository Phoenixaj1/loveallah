#!/usr/bin/env bash
# ============================================================
# Manual deploy — mirrors what GitHub Actions does.
# Use when you want to push code without going through git.
#
# Requires: rsync, ssh, .env.deploy with credentials
# Copy .env.deploy.example → .env.deploy and fill in the values.
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env.deploy"

if [ ! -f "${ENV_FILE}" ]; then
  echo "❌ ${ENV_FILE} not found. Copy .env.deploy.example to .env.deploy and fill it in."
  exit 1
fi

# shellcheck disable=SC1090
source "${ENV_FILE}"

: "${SSH_HOST:?SSH_HOST required}"
: "${SSH_USER:?SSH_USER required}"
: "${SSH_PORT:=22}"
: "${WP_PATH:?WP_PATH required}"
: "${SSH_KEY:?SSH_KEY (path to private key file) required}"

RSYNC_OPTS=(-avz --delete --exclude='.git' --exclude='.DS_Store' --exclude='node_modules' --exclude='*.zip')
SSH_CMD="ssh -i ${SSH_KEY} -p ${SSH_PORT}"

echo "📦 Deploying loveallah plugin..."
rsync "${RSYNC_OPTS[@]}" -e "${SSH_CMD}" \
  ./plugins/loveallah/ \
  "${SSH_USER}@${SSH_HOST}:${WP_PATH}/wp-content/plugins/loveallah/"

echo "🎨 Deploying loveallah-starter theme..."
date +%s > ./theme/loveallah-starter/.deploy-time
rsync "${RSYNC_OPTS[@]}" -e "${SSH_CMD}" \
  ./theme/loveallah-starter/ \
  "${SSH_USER}@${SSH_HOST}:${WP_PATH}/wp-content/themes/loveallah-starter/"

echo "🧹 Flushing caches..."
${SSH_CMD} "${SSH_USER}@${SSH_HOST}" "cd ${WP_PATH} && \
  wp cache flush 2>/dev/null || true; \
  wp transient delete --all 2>/dev/null || true; \
  rm -rf wp-content/cache/* 2>/dev/null || true"

echo "✅ Done. Visit https://loveallah.app to confirm."
