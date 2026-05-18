#!/usr/bin/env bash
# up.sh — Arranque de Maya Logs. Ver maya_infra/scripts/up-common.sh.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

SERVICE_LABEL="maya-logs"
BACKEND_CONTAINER="maya_log_mgmt"
FRONTEND_URL="https://${HOST_PREFIX:-}logs.${DOMAIN_SUFFIX:-localhost}"
BACKEND_API_URL="https://${HOST_PREFIX:-}logs-api.${DOMAIN_SUFFIX:-localhost}/api/v1"
DEFAULT_BACKEND_PORT="8002"
DEFAULT_FRONTEND_PORT="5176"

setup_frontend_env() {
    upsert_env_var frontend/.env VITE_API_URL            "${VITE_API_URL:-https://${HOST_PREFIX:-}logs-api.${DOMAIN_SUFFIX:-localhost}/api/v1}"
    upsert_env_var frontend/.env VITE_KEYCLOAK_URL       "${VITE_KEYCLOAK_URL:-https://keycloak.${DOMAIN_SUFFIX:-localhost}}"
    upsert_env_var frontend/.env VITE_KEYCLOAK_REALM     "${VITE_KEYCLOAK_REALM:-${MAYA_SLOT:-maya}}"
    upsert_env_var frontend/.env VITE_KEYCLOAK_CLIENT_ID "${VITE_KEYCLOAK_CLIENT_ID:-maya-logs}"
    upsert_env_var frontend/.env VITE_DASHBOARD_API_URL  "${VITE_DASHBOARD_API_URL:-https://${HOST_PREFIX:-}dashboard-api.${DOMAIN_SUFFIX:-localhost}}"
}

# shellcheck source=../maya_infra/scripts/up-common.sh
source "${MAYA_INFRA_DIR:-"$SCRIPT_DIR/../maya_infra"}/scripts/up-common.sh"
