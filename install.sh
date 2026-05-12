#!/usr/bin/env bash

set -euo pipefail

# ─────────────────────────────────────────────
# DevSquad Sidecar — Install Script
#
# Usage (from inside your Laravel project root):
#   bash <(curl -fsSL https://raw.githubusercontent.com/EliteDevSquad/sidecar-laravel/main/install.sh)
# ─────────────────────────────────────────────

BOLD="\033[1m"
GREEN="\033[0;32m"
YELLOW="\033[0;33m"
RED="\033[0;31m"
CYAN="\033[0;36m"
RESET="\033[0m"

step()  { echo -e "\n${CYAN}${BOLD}▶ $1${RESET}"; }
ok()    { echo -e "  ${GREEN}✔ $1${RESET}"; }
warn()  { echo -e "  ${YELLOW}⚠ $1${RESET}"; }
error() { echo -e "  ${RED}✖ $1${RESET}"; exit 1; }
info()  { echo -e "  $1"; }

# ─────────────────────────────────────────────
# Detect project root (where artisan lives).
# When piped via curl, BASH_SOURCE[0] is empty — fall back to PWD.
# ─────────────────────────────────────────────

# Allow tests to inject the project root directly, bypassing auto-detection.
if [[ -n "${SIDECAR_PROJECT_ROOT:-}" ]]; then
    PROJECT_ROOT="$SIDECAR_PROJECT_ROOT"
else
    if [[ -n "${BASH_SOURCE[0]:-}" && "${BASH_SOURCE[0]}" != "bash" ]]; then
        SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    else
        SCRIPT_DIR="$PWD"
    fi

    PROJECT_ROOT="$SCRIPT_DIR"
    while [[ "$PROJECT_ROOT" != "/" ]]; do
        [[ -f "$PROJECT_ROOT/artisan" ]] && break
        PROJECT_ROOT="$(dirname "$PROJECT_ROOT")"
    done
fi

[[ -f "$PROJECT_ROOT/artisan" ]] || error "artisan not found. Run this script from inside your Laravel project."

step "DevSquad Sidecar installer"
info "Project root: $PROJECT_ROOT"

ENV_FILE="$PROJECT_ROOT/.env"
ENV_EXAMPLE_FILE="$PROJECT_ROOT/.env.example"

[[ -f "$ENV_FILE" ]] || error ".env not found at $PROJECT_ROOT — copy .env.example first."

# ─────────────────────────────────────────────
# Helpers
# ─────────────────────────────────────────────

_SIDECAR_HEADER_WRITTEN_ENV=0
_SIDECAR_HEADER_WRITTEN_EXAMPLE=0

_ensure_header() {
    local file="$1" flag_var="$2"
    if [[ "${!flag_var}" -eq 0 ]] && ! grep -q "# DevSquad Sidecar" "$file" 2>/dev/null; then
        printf "\n# DevSquad Sidecar\n" >> "$file"
    fi
    eval "$flag_var=1"
}

env_get() {
    grep -E "^${1}=" "$ENV_FILE" 2>/dev/null | head -1 | cut -d'=' -f2- | tr -d '"' | tr -d "'"
}

env_has() {
    grep -qE "^$1=" "$ENV_FILE" 2>/dev/null
}

# env_set KEY ENV_VALUE EXAMPLE_VALUE
# Writes KEY=ENV_VALUE to .env (add or update).
# Writes KEY=EXAMPLE_VALUE to .env.example (add only, never overwrite).
env_set() {
    local key="$1" value="$2" example_value="$3"

    if grep -qE "^${key}=" "$ENV_FILE" 2>/dev/null; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
        else
            sed -i "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
        fi
        ok "Updated .env:       ${key}=${value}"
    else
        _ensure_header "$ENV_FILE" _SIDECAR_HEADER_WRITTEN_ENV
        echo "${key}=${value}" >> "$ENV_FILE"
        ok "Added .env:         ${key}=${value}"
    fi

    example_ensure "$key" "$example_value"
}

# example_ensure KEY VALUE
# Adds KEY=VALUE to .env.example if the key is not already there.
example_ensure() {
    local key="$1" value="$2"
    [[ -f "$ENV_EXAMPLE_FILE" ]] || return 0
    grep -qE "^${key}=" "$ENV_EXAMPLE_FILE" 2>/dev/null && return 0
    _ensure_header "$ENV_EXAMPLE_FILE" _SIDECAR_HEADER_WRITTEN_EXAMPLE
    echo "${key}=${value}" >> "$ENV_EXAMPLE_FILE"
    ok "Added .env.example: ${key}=${value}"
}

# env_ensure KEY ENV_VALUE EXAMPLE_VALUE
# Sets the key in .env only if not already present; always ensures .env.example.
env_ensure() {
    local key="$1" value="$2" example_value="$3"
    env_has "$key" || env_set "$key" "$value" "$example_value"
    example_ensure "$key" "$example_value"
}

# ─────────────────────────────────────────────
# 1. Composer install
# ─────────────────────────────────────────────

step "Installing composer package"

cd "$PROJECT_ROOT"

if composer show elitedevsquad/sidecar-laravel &>/dev/null; then
    ok "Package already present in vendor — skipping"
else
    info "Running: composer require elitedevsquad/sidecar-laravel --dev"
    composer require elitedevsquad/sidecar-laravel --dev
    ok "Package installed"
fi

# ─────────────────────────────────────────────
# 2. Publish config
# ─────────────────────────────────────────────

step "Publishing config"

CONFIG_FILE="$PROJECT_ROOT/config/devsquad-sidecar.php"

if [[ -f "$CONFIG_FILE" ]]; then
    warn "config/devsquad-sidecar.php already exists — skipping"
else
    php artisan vendor:publish --tag="devsquad-sidecar" --quiet
    ok "Published config/devsquad-sidecar.php"
fi

# ─────────────────────────────────────────────
# 3. Configure .env (and .env.example)
# ─────────────────────────────────────────────

step "Configuring .env"

APP_URL="$(env_get APP_URL)"
info "Detected APP_URL: ${APP_URL:-<empty>}"

# DS_SIDECAR_ENABLED is always forced to true (overwrite if already set)
env_set    "VITE_DS_SIDECAR_ENABLED"       "true"                        "true"
env_set    "DS_SIDECAR_ENABLED"            '"$VITE_DS_SIDECAR_ENABLED"'  '"$VITE_DS_SIDECAR_ENABLED"'
env_ensure "DS_SIDECAR_AUTO_INJECT_ASSETS" "true"                        "true"

env_ensure "DS_SIDECAR_TINKER_ENABLED"     "true"           "true"
env_ensure "DS_SIDECAR_TINKER_USE_BATCH"   "true"           "true"
env_ensure "DS_SIDECAR_COMMANDS_ENABLED"   "true"           "true"
env_ensure "DS_SIDECAR_FAKE_CLOCK_ENABLED" "true"           "true"
env_ensure "DS_SIDECAR_ALLOWED_IPS"        '"127.0.0.1"'    '"127.0.0.1"'

# DS_SIDECAR_BRANCH_URL — auto-detect from git remote
GIT_REMOTE=""
if git -C "$PROJECT_ROOT" remote get-url origin &>/dev/null; then
    RAW_URL="$(git -C "$PROJECT_ROOT" remote get-url origin 2>/dev/null || true)"
    if [[ "$RAW_URL" == git@github.com:* ]]; then
        GIT_REMOTE="https://github.com/$(echo "$RAW_URL" | sed 's/git@github.com://' | sed 's/\.git$//')/tree/"
    elif [[ "$RAW_URL" == git@bitbucket.org:* ]]; then
        GIT_REMOTE="https://bitbucket.org/$(echo "$RAW_URL" | sed 's/git@bitbucket.org://' | sed 's/\.git$//')/branch/"
    elif [[ "$RAW_URL" == git@gitlab.com:* ]]; then
        GIT_REMOTE="https://gitlab.com/$(echo "$RAW_URL" | sed 's/git@gitlab.com://' | sed 's/\.git$//')/-/tree/"
    elif [[ "$RAW_URL" == https://* ]]; then
        GIT_REMOTE="$(echo "$RAW_URL" | sed 's/\.git$//')/tree/"
    fi
fi
env_ensure "DS_SIDECAR_BRANCH_URL" "$GIT_REMOTE" ""

# DS_SIDECAR_LINK_MAIL — auto-detect mailpit/mailhog
MAIL_URL=""
MAIL_HOST="$(env_get MAIL_HOST)"
MAIL_PORT="$(env_get MAIL_PORT)"
if [[ "$MAIL_HOST" == *"mailpit"* ]] || [[ "$MAIL_HOST" == *"mailhog"* ]] || [[ "$MAIL_PORT" == "8025" ]]; then
    APP_BASE="${APP_URL%%:*}://$(echo "$APP_URL" | sed 's|.*://||' | cut -d':' -f1)"
    MAIL_URL="${APP_BASE}:8025"
fi
env_ensure "DS_SIDECAR_LINK_MAIL"    "$MAIL_URL"      ""
env_ensure "DS_SIDECAR_LINK_ENVOYER" '""'             '""'

# HEADER_BRANCH_NAME — current git branch
CURRENT_BRANCH="$(git -C "$PROJECT_ROOT" branch --show-current 2>/dev/null || true)"
env_ensure "HEADER_BRANCH_NAME" "$CURRENT_BRANCH" ""

# ─────────────────────────────────────────────
# 4. CSRF meta tag — scan all blade files under
#    resources/views that look like full layouts
#    (contain <html>, <body> and at least one <meta>)
# ─────────────────────────────────────────────

step "Checking CSRF meta tag"

CSRF_TAG='<meta name="csrf-token" content="{{ csrf_token() }}">'
CSRF_ADDED=0
CSRF_SKIPPED=0

while IFS= read -r -d '' BLADE; do
    # Skip mail/email templates — any path segment (folder or filename) that is
    # exactly mail, mails, email, or emails (e.g. views/mail/, views/vendor/mail/html/, notifications/mail.blade.php)
    RELATIVE_BLADE="${BLADE#$PROJECT_ROOT/resources/views/}"
    if echo "$RELATIVE_BLADE" | grep -qiE "(^|/)e?mails?(/|\.blade\.php$)"; then
        continue
    fi

    grep -qi '<html'  "$BLADE" || continue
    grep -qi '<body'  "$BLADE" || continue
    grep -qi '<meta'  "$BLADE" || continue

    if grep -q 'csrf-token' "$BLADE"; then
        ok "Already present: ${BLADE#$PROJECT_ROOT/}"
        CSRF_SKIPPED=$((CSRF_SKIPPED + 1))
        continue
    fi

    if grep -qi '<head' "$BLADE"; then
        RELATIVE="${BLADE#$PROJECT_ROOT/}"
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' "s|<head>|<head>\n    ${CSRF_TAG}|I" "$BLADE" 2>/dev/null || \
            sed -i '' "/<[Hh][Ee][Aa][Dd]>/s|<[Hh][Ee][Aa][Dd]>|<head>\n    ${CSRF_TAG}|" "$BLADE"
        else
            sed -i "s|<head>|<head>\n    ${CSRF_TAG}|I" "$BLADE"
        fi
        ok "Injected into: ${RELATIVE}"
        CSRF_ADDED=$((CSRF_ADDED + 1))
    fi

done < <(find "$PROJECT_ROOT/resources/views" -name "*.blade.php" -print0 2>/dev/null)

if [[ $CSRF_ADDED -eq 0 && $CSRF_SKIPPED -eq 0 ]]; then
    warn "No full layout blade files found. Add the CSRF tag manually inside <head>:"
    warn "  ${CSRF_TAG}"
else
    info "CSRF tag: ${CSRF_ADDED} added, ${CSRF_SKIPPED} already present"
fi

# ─────────────────────────────────────────────
# Done
# ─────────────────────────────────────────────

echo ""
echo -e "${GREEN}${BOLD}✔ DevSquad Sidecar installed successfully!${RESET}"
echo ""
echo -e "  Next steps:"
echo -e "  ${BOLD}1.${RESET} Configure ${YELLOW}Sidecar::\$userMap${RESET} in AppServiceProvider (see README)"
echo -e "  ${BOLD}2.${RESET} Fill in   ${YELLOW}DS_SIDECAR_LINK_ENVOYER${RESET} in .env if you use Envoyer"
echo ""
